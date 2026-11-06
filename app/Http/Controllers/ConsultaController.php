<?php

namespace App\Http\Controllers;

use App\Models\AtestadoFilho;
use App\Models\AtestadoPedido;
use App\Rules\Cnpj;
use App\Rules\Cpf;
use Illuminate\Http\Request;

/** Quem perdeu o e-mail consulta os pedidos pelo CPF (ou CNPJ, no caso de empresa) + protocolo — o documento sozinho não basta (LGPD). */
class ConsultaController extends Controller
{
    public function form()
    {
        return view('pedido.consulta');
    }

    public function buscar(Request $request)
    {
        $dados = $request->validate([
            'cpf' => ['required', 'string', 'max:30', function ($attr, $valor, $falhar) {
                $limpo = Cnpj::normalizar($valor);
                if (strlen($limpo) === 14) {
                    (new Cnpj())->validate($attr, $valor, $falhar);
                } else {
                    (new Cpf())->validate($attr, $valor, fn () => $falhar('Informe um CPF ou CNPJ válido.'));
                }
            }],
            'protocolo' => ['required', 'string', 'max:20'],
        ]);

        $limpo = Cnpj::normalizar($dados['cpf']);
        $filho = AtestadoFilho::where('protocolo', strtoupper(trim($dados['protocolo'])))
            ->whereHas('pedido', fn ($q) => strlen($limpo) === 14
                ? $q->where('cnpj', $limpo)
                : $q->where('cpf_hash', AtestadoPedido::hashCpf($dados['cpf'])))
            ->first();

        if (! $filho) {
            return back()->withInput()->withErrors(['protocolo' => 'Não encontramos um pedido com esse documento e protocolo.']);
        }

        return redirect()->route('atestado.show', $filho->token);
    }
}
