<?php

namespace App\Support;

use App\Models\AtestadoFilho;
use App\Models\AtestadoPedido;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** Filtros da lista de pedidos e dos relatórios (um registro por filho/protocolo). */
class FiltroPedidos
{
    public const STATUS = [
        AtestadoFilho::AGUARDANDO => 'Aguardando a unidade',
        AtestadoFilho::PRONTO => 'Pronto para baixar',
        AtestadoFilho::BAIXADO => 'Baixado',
        AtestadoFilho::CANCELADO => 'Cancelado',
    ];

    /** @var array{q:string,unidade:string,status:string,vinculo:string,de:string,ate:string} */
    public array $valores;

    public function __construct(array $entrada)
    {
        $texto = fn ($k) => trim((string) ($entrada[$k] ?? ''));
        $data = fn ($k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto($k)) ? $texto($k) : '';

        $this->valores = [
            'q' => mb_substr($texto('q'), 0, 100),
            'unidade' => ctype_digit($texto('unidade')) ? $texto('unidade') : '',
            'status' => array_key_exists($texto('status'), self::STATUS) ? $texto('status') : '',
            'vinculo' => array_key_exists($texto('vinculo'), AtestadoPedido::VINCULOS) ? $texto('vinculo') : '',
            'de' => $data('de'),
            'ate' => $data('ate'),
        ];
    }

    public static function daRequisicao(Request $request): self
    {
        return new self($request->query());
    }

    /** Lista: um registro por filho/protocolo, mais recentes primeiro. */
    public function consulta(): Builder
    {
        return $this->base()->with('pedido')->select('atestado_filhos.*')->orderByDesc('atestado_filhos.id');
    }

    /** Junção e filtros, sem colunas nem ordem (serve também para totais agrupados). */
    public function base(): Builder
    {
        $v = $this->valores;
        $q = AtestadoFilho::query()->join('atestado_pedidos', 'atestado_pedidos.id', '=', 'atestado_filhos.atestado_pedido_id');

        if ($v['q'] !== '') {
            $busca = $v['q'];
            $digitos = preg_replace('/\D/', '', $busca);
            $q->where(function (Builder $w) use ($busca, $digitos) {
                $like = '%'.mb_strtolower($busca).'%';
                $w->where('atestado_filhos.protocolo', 'like', '%'.strtoupper($busca).'%')
                    ->orWhereRaw('LOWER(atestado_filhos.nome) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(atestado_pedidos.responsavel_nome) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(atestado_pedidos.email) LIKE ?', [$like]);
                if (strlen($digitos) === 11) {
                    $w->orWhere('atestado_pedidos.cpf_hash', AtestadoPedido::hashCpf($digitos));
                }
            });
        }
        if ($v['unidade'] !== '') {
            $q->where('atestado_pedidos.unidade_id', (int) $v['unidade']);
        }
        if ($v['status'] !== '') {
            $q->where('atestado_filhos.status', $v['status']);
        }
        if ($v['vinculo'] !== '') {
            $q->where('atestado_pedidos.vinculo', $v['vinculo']);
        }
        if ($v['de'] !== '') {
            $q->whereDate('atestado_filhos.created_at', '>=', $v['de']);
        }
        if ($v['ate'] !== '') {
            $q->whereDate('atestado_filhos.created_at', '<=', $v['ate']);
        }

        return $q;
    }

    /** Descrição legível dos filtros ativos, para o cabeçalho dos relatórios. */
    public function descricao(): string
    {
        $v = $this->valores;
        $partes = [];
        if ($v['q'] !== '') {
            $partes[] = 'busca: "'.$v['q'].'"';
        }
        if ($v['unidade'] !== '') {
            $nome = AtestadoPedido::where('unidade_id', (int) $v['unidade'])->value('unidade_nome');
            $partes[] = 'unidade: '.($nome ?? $v['unidade']);
        }
        if ($v['status'] !== '') {
            $partes[] = 'situação: '.self::STATUS[$v['status']];
        }
        if ($v['vinculo'] !== '') {
            $partes[] = 'vínculo: '.AtestadoPedido::VINCULOS[$v['vinculo']];
        }
        if ($v['de'] !== '') {
            $partes[] = 'a partir de '.date('d/m/Y', strtotime($v['de']));
        }
        if ($v['ate'] !== '') {
            $partes[] = 'até '.date('d/m/Y', strtotime($v['ate']));
        }

        return $partes ? implode(' · ', $partes) : 'sem filtros (todos os pedidos)';
    }

    /** Unidades que já apareceram em pedidos: [id => nome]. */
    public static function unidades(): array
    {
        return AtestadoPedido::query()->select('unidade_id', 'unidade_nome')->distinct()->orderBy('unidade_nome')
            ->pluck('unidade_nome', 'unidade_id')->all();
    }
}
