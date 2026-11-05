<?php

namespace App\Http\Controllers;

use App\Models\AtestadoFilho;
use App\Models\AtestadoPedido;
use App\Rules\Cpf;
use Illuminate\Http\Request;

/** Quem perdeu o e-mail consulta os pedidos pelo CPF + protocolo (o CPF sozinho não basta — LGPD). */
class ConsultaController extends Controller
{
    public function form()
    {
        return view('pedido.consulta');
    }

    public function buscar(Request $request)
    {
        $dados = $request->validate([
            'cpf' => ['required', new Cpf()],
            'protocolo' => ['required', 'string', 'max:20'],
        ]);

        $filho = AtestadoFilho::where('protocolo', strtoupper(trim($dados['protocolo'])))
            ->whereHas('pedido', fn ($q) => $q->where('cpf_hash', AtestadoPedido::hashCpf($dados['cpf'])))
            ->first();

        if (! $filho) {
            return back()->withInput()->withErrors(['protocolo' => 'Não encontramos um pedido com esse CPF e protocolo.']);
        }

        return redirect()->route('atestado.show', $filho->token);
    }
}
