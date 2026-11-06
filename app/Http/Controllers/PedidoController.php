<?php

namespace App\Http\Controllers;

use App\Models\AtestadoFilho;
use App\Models\AtestadoPedido;
use App\Rules\Cpf;
use App\Services\ChamadosClient;
use Carbon\Carbon;
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
            'vinculo' => ['required', Rule::in(array_keys(AtestadoPedido::VINCULOS))],
            'parentesco' => ['required_if:vinculo,outro', 'nullable', 'string', 'max:60'],
            'data_nascimento_responsavel' => ['bail', 'required', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01', function ($attr, $valor, $falhar) use ($request) {
                $minima = AtestadoPedido::idadeMinima($request->input('vinculo'));
                if (Carbon::createFromFormat('Y-m-d', $valor)->age < $minima) {
                    $falhar(in_array($request->input('vinculo'), ['pai', 'mae'], true)
                        ? 'Para pedir como pai ou mãe é preciso ter pelo menos 15 anos.'
                        : 'Para pedir como responsável legal ou outro parentesco é preciso ter pelo menos 18 anos.');
                }
            }],
            'cpf' => ['required', new Cpf()],
            'telefone' => ['required', 'regex:/^\(?\d{2}\)?\s?9?\d{4}-?\d{4}$/'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'cep' => ['required', 'regex:/^\d{5}-?\d{3}$/'],
            'acs_nome' => ['nullable', 'string', 'max:255'],
            'unidade_id' => ['required', 'integer', Rule::in($unidades->pluck('id')->all())],
            'pedir_atestado' => ['accepted'],
            'filhos' => ['required', 'array', 'min:1', 'max:15'],
            'filhos.*.nome' => ['required', 'string', 'max:255'],
            'filhos.*.serie' => ['required', 'string', 'max:100'],
            'filhos.*.data_nascimento' => ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after:1990-01-01'],
            'filhos.*.nome_mae' => ['required', 'string', 'max:255'],
        ], [
            'vinculo.required' => 'Informe se você é pai, mãe, responsável legal ou outro.',
            'vinculo.in' => 'Informe se você é pai, mãe, responsável legal ou outro.',
            'parentesco.required_if' => 'Informe o seu parentesco com a criança.',
            'data_nascimento_responsavel.required' => 'Informe a sua data de nascimento.',
            'data_nascimento_responsavel.date_format' => 'Informe uma data de nascimento válida.',
            'data_nascimento_responsavel.before' => 'Informe uma data de nascimento válida.',
            'data_nascimento_responsavel.after' => 'Informe uma data de nascimento válida.',
            'pedir_atestado.accepted' => 'Marque a opção "Peço atestado vacinal" para continuar.',
            'filhos.required' => 'Informe os dados de pelo menos um filho.',
            'unidade_id.in' => 'Selecione uma unidade de saúde da lista.',
            'telefone.regex' => 'Informe o telefone com DDD, por exemplo (65) 99999-9999.',
            'cep.regex' => 'Informe o CEP no formato 00000-000.',
            'filhos.*.data_nascimento.*' => 'Informe uma data de nascimento válida.',
        ], [
            'data_nascimento_responsavel' => 'data de nascimento',
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
                    'vinculo' => $dados['vinculo'],
                    'parentesco' => $dados['vinculo'] === 'outro' ? trim($dados['parentesco']) : null,
                    'data_nascimento_responsavel' => $dados['data_nascimento_responsavel'],
                    'cpf' => $cpf,
                    'cpf_hash' => AtestadoPedido::hashCpf($cpf),
                    'telefone' => $dados['telefone'],
                    'cep' => $dados['cep'],
                    'email' => strtolower(trim($dados['email'])),
                    'unidade_id' => $unidade['id'],
                    'unidade_nome' => $unidade['name'],
                    'acs_nome' => filled($dados['acs_nome'] ?? null) ? trim($dados['acs_nome']) : null,
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
