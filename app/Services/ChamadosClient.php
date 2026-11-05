<?php

namespace App\Services;

use App\Models\AtestadoFilho;
use App\Models\AtestadoPedido;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Cliente da API de atestado vacinal do chamados-multi-tenant (/vaccination/*). */
class ChamadosClient
{
    private function http(): PendingRequest
    {
        if (! config('chamados.url') || ! config('chamados.token')) {
            throw new RuntimeException('Integração com o sistema de chamados não configurada.');
        }

        return Http::baseUrl(config('chamados.url'))
            ->withToken(config('chamados.token'))
            ->acceptJson()
            ->timeout(config('chamados.timeout'))
            ->withOptions(['verify' => config('chamados.verify_ssl')]);
    }

    /** @return array<int, array{id:int,name:string}> */
    public function unidades(): array
    {
        $cached = Cache::get('chamados.unidades');
        if (! empty($cached)) {
            return $cached;
        }

        $resp = $this->http()->get('/vaccination/units');
        if (! $resp->successful()) {
            throw new RuntimeException('Não foi possível carregar as unidades de saúde ('.$resp->status().').');
        }

        $unidades = collect($resp->json('data', []))
            ->map(fn ($u) => ['id' => (int) $u['id'], 'name' => (string) $u['name']])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()->all();

        // Lista vazia nunca vai para o cache: assim que uma unidade for marcada no chamados, ela aparece.
        if ($unidades !== []) {
            Cache::put('chamados.unidades', $unidades, now()->addMinutes(config('chamados.units_cache_minutes')));
        }

        return $unidades;
    }

    /** Envia o pedido ao chamados e grava o protocolo de cada filho (mesma ordem do envio). */
    public function enviarPedido(AtestadoPedido $pedido): void
    {
        $pedido->loadMissing('filhos');
        $filhos = $pedido->filhos->sortBy('id')->values();

        $resp = $this->http()->post('/vaccination/requests', [
            'external_id' => $pedido->uuid,
            'sector_id' => $pedido->unidade_id,
            'guardian' => [
                'name' => $pedido->responsavel_nome,
                'cpf' => $pedido->cpf,
                'phone' => $pedido->telefone,
                'zip' => $pedido->cep,
                'email' => $pedido->email,
                'school' => $pedido->escola,
            ],
            'children' => $filhos->map(fn (AtestadoFilho $f) => [
                'name' => $f->nome,
                'grade' => $f->serie,
                'birth_date' => $f->data_nascimento->toDateString(),
                'mother_name' => $f->nome_mae,
            ])->all(),
        ]);

        if (! $resp->successful()) {
            $msg = $resp->json('message') ?: 'resposta '.$resp->status();
            throw new RuntimeException('O sistema de chamados recusou o pedido: '.$msg);
        }

        $dados = $resp->json('data', []);
        if (count($dados) !== $filhos->count()) {
            throw new RuntimeException('Resposta inesperada do sistema de chamados.');
        }
        foreach ($filhos as $i => $filho) {
            $filho->update(['protocolo' => $dados[$i]['protocol']]);
        }
    }

    /** Avisa o chamados que o responsável recebeu o PDF (POST /vaccination/requests/{protocolo}/served?cpf=) → Atendido. */
    public function avisarAtendido(AtestadoFilho $filho): bool
    {
        $resp = $this->http()->post("/vaccination/requests/{$filho->protocolo}/served?".http_build_query(['cpf' => $filho->pedido->cpf]));

        return $resp->successful();
    }
}
