<?php

namespace App\Services;

use App\Models\AtestadoFilho;
use App\Models\AtestadoPedido;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Cliente da API de integração de atestado vacinal do UYSI Service (CHAMADOS_API_URL = https://<empresa>.<dominio>/api/v1/integration).
 * Os nomes ("chamados") vêm do sistema antigo; o formato é o do UYSI: unidades em "units", pedido com "unit_id" e "guardian_type", resposta em "requests".
 */
class ChamadosClient
{
    /** O UYSI aceita até 10 pessoas por pedido, todas da mesma unidade. */
    private const MAX_POR_PEDIDO = 10;

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

        $unidades = collect($resp->json('units') ?? $resp->json('data', []))
            ->map(fn ($u) => ['id' => (int) $u['id'], 'name' => (string) $u['name']])
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()->all();

        // Lista vazia nunca vai para o cache: assim que uma unidade for marcada no chamados, ela aparece.
        if ($unidades !== []) {
            Cache::put('chamados.unidades', $unidades, now()->addMinutes(config('chamados.units_cache_minutes')));
        }

        return $unidades;
    }

    /**
     * Envia o pedido ao UYSI e grava o protocolo de cada filho. O UYSI recebe uma unidade por pedido (e até 10 pessoas):
     * empresa com funcionários de unidades diferentes (ou mais de 10 pessoas) vira vários pedidos. Se algum falhar,
     * cancela os já criados e lança a exceção, para o pedido não ficar pela metade.
     */
    public function enviarPedido(AtestadoPedido $pedido): void
    {
        $pedido->loadMissing('filhos');
        $filhos = $pedido->filhos->sortBy('id')->values();

        $preenchido = fn ($v) => $v !== null && $v !== '';
        $legal = $pedido->tipo === AtestadoPedido::RESPONSAVEL_LEGAL;

        $guardian = array_filter([
            'name' => $pedido->responsavel_nome,
            'cpf' => $pedido->cpf,
            'cnpj' => $pedido->cnpj,
            'phone' => $pedido->telefone,
            'zip' => $pedido->cep,
            'street' => $pedido->logradouro,
            'number' => $pedido->numero,
            'complement' => $pedido->complemento,
            'district' => $pedido->bairro,
            'city' => $pedido->cidade,
            'state' => $pedido->uf,
            'email' => $pedido->email,
            'relationship' => $legal ? 'responsavel_legal' : $pedido->vinculo,
            'relationship_other' => $legal ? $pedido->parentesco : null,
            'birth_date' => $pedido->data_nascimento_responsavel,
        ], $preenchido);

        // Empresa: cada funcionário leva a sua unidade; os demais usam a do pedido.
        $porUnidade = [];
        foreach ($filhos as $f) {
            $porUnidade[(int) ($pedido->ehEmpresa() ? $f->unidade_id : $pedido->unidade_id)][] = $f;
        }
        $lotes = [];
        foreach ($porUnidade as $unidade => $lista) {
            foreach (array_chunk($lista, self::MAX_POR_PEDIDO) as $lote) {
                $lotes[] = [$unidade, $lote];
            }
        }

        $criados = []; // [filho, protocolo]
        try {
            foreach ($lotes as $i => [$unidade, $lote]) {
                $resp = $this->http()->post('/vaccination/requests', [
                    'external_id' => count($lotes) === 1 ? $pedido->uuid : $pedido->uuid.'-'.($i + 1),
                    'unit_id' => $unidade,
                    'guardian_type' => $pedido->tipo,
                    'guardian' => $guardian,
                    'children' => array_map(fn (AtestadoFilho $f) => array_filter([
                        'name' => $f->nome,
                        'grade' => $f->serie,
                        'birth_date' => $f->data_nascimento->toDateString(),
                        'mother_name' => $f->nome_mae,
                        'cpf' => $pedido->tipo === AtestadoPedido::FILHOS || $legal ? null : $f->cpf,
                        'acs_name' => $pedido->acs_nome,
                    ], $preenchido), $lote),
                ]);

                if (! $resp->successful()) {
                    $msg = $resp->json('error.message') ?: $resp->json('message') ?: 'resposta '.$resp->status();
                    throw new RuntimeException('O sistema de chamados recusou o pedido: '.$msg);
                }

                $dados = $resp->json('requests') ?? $resp->json('data', []);
                if (count($dados) !== count($lote)) {
                    throw new RuntimeException('Resposta inesperada do sistema de chamados.');
                }
                foreach ($lote as $j => $filho) {
                    $criados[] = [$filho, $dados[$j]['protocol']];
                }
            }
        } catch (Throwable $e) {
            foreach ($criados as [, $protocolo]) {
                $this->cancelarSemAviso($protocolo);
            }
            throw $e;
        }

        foreach ($criados as [$filho, $protocolo]) {
            $filho->update(['protocolo' => $protocolo]);
        }
    }

    /** Desfaz um pedido já criado no UYSI quando outro lote do mesmo pedido falhou. Nunca lança exceção. */
    private function cancelarSemAviso(string $protocolo): void
    {
        try {
            $this->http()->post("/vaccination/requests/{$protocolo}/cancel");
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Baixa o PDF do atestado só para guardar uma cópia (?peek=1: o UYSI NÃO marca como baixado; isso vem de avisarAtendido).
     * Devolve o conteúdo do PDF; lança exceção se o UYSI não entregar (o aviso "pronto" é reenviado por ele depois).
     */
    public function baixarAtestado(string $protocolo): string
    {
        $resp = $this->http()->get("/vaccination/requests/{$protocolo}/certificate", ['peek' => 1]);
        if (! $resp->successful()) {
            throw new RuntimeException('Não foi possível buscar o atestado no sistema de chamados ('.$resp->status().').');
        }

        return $resp->body();
    }

    /** Avisa o chamados que o responsável recebeu o PDF (POST /vaccination/requests/{protocolo}/served?cpf=) → Atendido (o UYSI ignora ?cpf=/?cnpj=). */
    public function avisarAtendido(AtestadoFilho $filho): bool
    {
        $resp = $this->http()->post("/vaccination/requests/{$filho->protocolo}/served?".http_build_query($this->identificacao($filho)));

        return $resp->successful();
    }

    /** Avisa o chamados que o responsável cancelou o pedido (POST /vaccination/requests/{protocolo}/cancel?cpf=). Só 2xx conta como avisado. */
    public function avisarCancelamento(AtestadoFilho $filho): bool
    {
        $resp = $this->http()->post("/vaccination/requests/{$filho->protocolo}/cancel?".http_build_query($this->identificacao($filho)));

        // 409 = a unidade já atendeu o pedido: o aviso não tem mais efeito, encerra o reenvio.
        return $resp->successful() || $resp->status() === 409 || $this->pedidoRemovido($resp);
    }

    /** Empresa se identifica pelo CNPJ; os demais pelo CPF (?cnpj= / ?cpf= nas rotas do chamados). */
    private function identificacao(AtestadoFilho $filho): array
    {
        $p = $filho->pedido;

        return $p->ehEmpresa() ? ['cnpj' => $p->cnpj] : ['cpf' => $p->cpf];
    }

    /**
     * 404 do chamados para um protocolo = pedido já excluído lá: encerra o reenvio automático.
     * Um 404 de ROTA inexistente ("The route ... could not be found", endpoint ainda não publicado) NÃO conta:
     * nesse caso o aviso continua pendente.
     */
    private function pedidoRemovido(Response $resp): bool
    {
        return $resp->status() === 404 && ! str_contains((string) $resp->json('message'), 'could not be found');
    }
}
