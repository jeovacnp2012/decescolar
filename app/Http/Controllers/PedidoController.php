<?php

namespace App\Http\Controllers;

use App\Models\AtestadoFilho;
use App\Models\AtestadoPedido;
use App\Rules\Cpf;
use App\Services\ChamadosClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class PedidoController extends Controller
{
    public function __construct(private ChamadosClient $chamados)
    {
    }

    public function create()
    {
        try {
            $unidades = $this->chamados->unidades();
            $erro = null;
        } catch (Throwable $e) {
            report($e);
            $unidades = [];
            $erro = 'No momento não conseguimos carregar as unidades de saúde. Tente novamente em alguns minutos.';
        }

        return view('pedido.create', ['unidades' => $unidades, 'erroUnidades' => $erro]);
    }

    public function store(Request $request): RedirectResponse
    {
        $unidades = collect($this->chamados->unidades());

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'cpf' => ['required', new Cpf()],
            'telefone' => ['required', 'regex:/^\(?\d{2}\)?\s?9?\d{4}-?\d{4}$/'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'cep' => ['required', 'regex:/^\d{5}-?\d{3}$/'],
            'escola' => ['required', 'string', 'max:255'],
            'unidade_id' => ['required', 'integer', Rule::in($unidades->pluck('id')->all())],
            'pedir_atestado' => ['accepted'],
            'filhos' => ['required', 'array', 'min:1', 'max:15'],
            'filhos.*.nome' => ['required', 'string', 'max:255'],
            'filhos.*.serie' => ['required', 'string', 'max:100'],
            'filhos.*.data_nascimento' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after:1990-01-01'],
            'filhos.*.nome_mae' => ['required', 'string', 'max:255'],
        ], [
            'pedir_atestado.accepted' => 'Marque a opção "Peço atestado vacinal" para continuar.',
            'filhos.required' => 'Informe os dados de pelo menos um filho.',
            'unidade_id.in' => 'Selecione uma unidade de saúde da lista.',
            'telefone.regex' => 'Informe o telefone com DDD, por exemplo (65) 99999-9999.',
            'cep.regex' => 'Informe o CEP no formato 00000-000.',
            'filhos.*.data_nascimento.*' => 'Informe uma data de nascimento válida.',
        ], [
            'filhos.*.nome' => 'nome do filho',
            'filhos.*.serie' => 'série',
            'filhos.*.nome_mae' => 'nome da mãe',
        ]);

        $unidade = $unidades->firstWhere('id', (int) $dados['unidade_id']);

        try {
            $pedido = DB::transaction(function () use ($dados, $unidade) {
                $cpf = preg_replace('/\D/', '', $dados['cpf']);
                $pedido = AtestadoPedido::create([
                    'uuid' => (string) Str::uuid(),
                    'responsavel_nome' => trim($dados['nome']),
                    'cpf' => $cpf,
                    'cpf_hash' => AtestadoPedido::hashCpf($cpf),
                    'telefone' => $dados['telefone'],
                    'cep' => $dados['cep'],
                    'email' => strtolower(trim($dados['email'])),
                    'escola' => trim($dados['escola']),
                    'unidade_id' => $unidade['id'],
                    'unidade_nome' => $unidade['name'],
                ]);
                foreach ($dados['filhos'] as $f) {
                    $pedido->filhos()->create([
                        'nome' => trim($f['nome']),
                        'serie' => trim($f['serie']),
                        'data_nascimento' => $f['data_nascimento'],
                        'nome_mae' => trim($f['nome_mae']),
                        'token' => Str::random(48),
                    ]);
                }
                $this->chamados->enviarPedido($pedido);
                $pedido->update(['envio_status' => 'enviado']);

                return $pedido;
            });
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->withErrors([
                'geral' => 'Não foi possível registrar o pedido agora. Seus dados não foram perdidos: confira e tente enviar novamente em instantes.',
            ]);
        }

        return redirect()->route('pedido.show', $pedido->uuid);
    }

    public function show(string $uuid)
    {
        $pedido = AtestadoPedido::with('filhos')->where('uuid', $uuid)->firstOrFail();

        return view('pedido.show', ['pedido' => $pedido]);
    }
}
