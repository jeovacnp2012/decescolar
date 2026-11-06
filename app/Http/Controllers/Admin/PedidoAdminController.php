<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AtestadoFilho;
use App\Support\FiltroPedidos;
use Illuminate\Http\Request;

class PedidoAdminController extends Controller
{
    public function index(Request $request)
    {
        $filtro = FiltroPedidos::daRequisicao($request);
        $filhos = $filtro->consulta()->paginate(25)->withQueryString();

        // Totais por situação respeitando os mesmos filtros (exceto a própria situação)
        $semStatus = new FiltroPedidos(array_merge($filtro->valores, ['status' => '']));
        $totais = $semStatus->base()->selectRaw('atestado_filhos.status as situacao, COUNT(*) as total')
            ->groupBy('atestado_filhos.status')->pluck('total', 'situacao');

        return view('admin.pedidos', [
            'filhos' => $filhos,
            'filtro' => $filtro->valores,
            'totais' => $totais,
            'unidades' => FiltroPedidos::unidades(),
        ]);
    }

    public function show(AtestadoFilho $filho)
    {
        $filho->load('pedido');

        return view('admin.pedido', ['filho' => $filho, 'irmaos' => $filho->pedido->filhos()->where('id', '!=', $filho->id)->orderBy('id')->get()]);
    }
}
