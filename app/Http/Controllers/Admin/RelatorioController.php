<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AtestadoFilho;
use App\Support\FiltroPedidos;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

/** Relatórios em PDF para impressão, com os mesmos filtros da lista. */
class RelatorioController extends Controller
{
    private const LIMITE_LINHAS = 1500;

    /** Lista de pedidos (uma linha por filho/protocolo), em paisagem. */
    public function pedidos(Request $request)
    {
        $filtro = FiltroPedidos::daRequisicao($request);
        $total = $filtro->consulta()->count();
        $filhos = $filtro->consulta()->limit(self::LIMITE_LINHAS)->get();

        $pdf = Pdf::loadView('admin.pdf.pedidos', [
            'filhos' => $filhos,
            'total' => $total,
            'cortado' => $total > self::LIMITE_LINHAS,
            'limite' => self::LIMITE_LINHAS,
            'filtros' => $filtro->descricao(),
            'geradoEm' => now(),
            'geradoPor' => $request->user()->name,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('pedidos-declaracao-vacinal-'.now()->format('Ymd-His').'.pdf');
    }

    /** Resumo por unidade de saúde e situação, em retrato. */
    public function resumo(Request $request)
    {
        $filtro = FiltroPedidos::daRequisicao($request);
        $linhas = $filtro->base()
            ->selectRaw('atestado_filhos.unidade_nome as unidade, atestado_filhos.status as situacao, COUNT(*) as total')
            ->groupBy('atestado_filhos.unidade_nome', 'atestado_filhos.status')->get();

        $porUnidade = [];
        foreach ($linhas as $l) {
            $porUnidade[$l->unidade] ??= ['total' => 0] + array_fill_keys(array_keys(FiltroPedidos::STATUS), 0);
            $porUnidade[$l->unidade][$l->situacao] += $l->total;
            $porUnidade[$l->unidade]['total'] += $l->total;
        }
        ksort($porUnidade, SORT_NATURAL | SORT_FLAG_CASE);

        $totais = ['total' => 0] + array_fill_keys(array_keys(FiltroPedidos::STATUS), 0);
        foreach ($porUnidade as $u) {
            foreach ($totais as $k => $_) {
                $totais[$k] += $u[$k];
            }
        }

        $pdf = Pdf::loadView('admin.pdf.resumo', [
            'porUnidade' => $porUnidade,
            'totais' => $totais,
            'filtros' => $filtro->descricao(),
            'geradoEm' => now(),
            'geradoPor' => $request->user()->name,
        ])->setPaper('a4', 'portrait');

        return $pdf->download('resumo-por-unidade-'.now()->format('Ymd-His').'.pdf');
    }
}
