<?php

namespace App\Http\Controllers;

use App\Models\AtestadoPedido;
use App\Rules\Cnpj;
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
        $ids = $unidades->pluck('id')->all();

        $tipo = $request->input('tipo');
        $empresa = $tipo === AtestadoPedido::EMPRESA;
        $proprio = $tipo === AtestadoPedido::PROPRIO;
        $legal = $tipo === AtestadoPedido::RESPONSAVEL_LEGAL;
        $filhosTipo = ! $empresa && ! $proprio && ! $legal; // "filhos" (padrão enquanto o tipo não for válido)

        $request->merge(['uf' => strtoupper(trim((string) $request->input('uf')))]);

        $regras = [
            'tipo' => ['required', Rule::in(array_keys(AtestadoPedido::TIPOS))],
            'nome' => ['required', 'string', 'max:255'],
            'telefone' => ['required', 'regex:/^\(?\d{2}\)?\s?9?\d{4}-?\d{4}$/'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'cep' => ['required', 'regex:/^\d{5}-?\d{3}$/'],
            'logradouro' => ['required', 'string', 'max:255'],
            'numero' => ['required', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:100'],
            'bairro' => ['required', 'string', 'max:100'],
            'cidade' => ['required', 'string', 'max:100'],
            'uf' => ['required', 'regex:/^[A-Z]{2}$/'],
            'acs_nome' => ['nullable', 'string', 'max:255'],
        ];

        if ($empresa) {
            $regras['cnpj'] = ['required', new Cnpj()];
            $regras['funcionarios'] = ['required', 'array', 'min:1', 'max:30'];
            $regras['funcionarios.*.nome'] = ['required', 'string', 'max:255'];
            $regras['funcionarios.*.data_nascimento'] = ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after:1900-01-01'];
            $regras['funcionarios.*.cpf'] = ['required', new Cpf(), 'distinct'];
            $regras['funcionarios.*.nome_mae'] = ['required', 'string', 'max:255'];
            $regras['funcionarios.*.unidade_id'] = ['required', 'integer', Rule::in($ids)];
        } else {
            $regras['cpf'] = ['required', new Cpf()];
            $regras['unidade_id'] = ['required', 'integer', Rule::in($ids)];
            $regras['data_nascimento_responsavel'] = ['bail', 'required', 'date_format:Y-m-d', 'before:today', 'after:1900-01-01', function ($attr, $valor, $falhar) use ($request, $tipo) {
                $minima = AtestadoPedido::idadeMinima($tipo, $request->input('vinculo'));
                if (Carbon::createFromFormat('Y-m-d', $valor)->age < $minima) {
                    $falhar(match ($tipo) {
                        AtestadoPedido::RESPONSAVEL_LEGAL => 'Para pedir como responsável legal é preciso ter pelo menos 18 anos.',
                        AtestadoPedido::PROPRIO => 'Para pedir o seu atestado é preciso ter pelo menos 15 anos.',
                        default => 'Para pedir como pai ou mãe é preciso ter pelo menos 15 anos.',
                    });
                }
            }];
        }
        if ($filhosTipo) {
            $regras['vinculo'] = ['required', Rule::in(['pai', 'mae'])];
        }
        if ($legal) {
            $regras['parentesco'] = ['nullable', 'string', 'max:60'];
        }
        if ($filhosTipo || $legal) {
            $regras['filhos'] = ['required', 'array', 'min:1', 'max:15'];
            $regras['filhos.*.nome'] = ['required', 'string', 'max:255'];
            $regras['filhos.*.serie'] = ['required', 'string', 'max:100'];
            $regras['filhos.*.data_nascimento'] = ['required', 'date_format:Y-m-d', 'before_or_equal:today', 'after:1990-01-01'];
            $regras['filhos.*.nome_mae'] = ['required', 'string', 'max:255'];
        }

        $dados = $request->validate($regras, [
            'tipo.required' => 'Escolha para quem é o atestado.',
            'tipo.in' => 'Escolha para quem é o atestado.',
            'vinculo.required' => 'Informe se você é pai ou mãe.',
            'vinculo.in' => 'Informe se você é pai ou mãe.',
            'data_nascimento_responsavel.required' => 'Informe a data de nascimento.',
            'data_nascimento_responsavel.date_format' => 'Informe uma data de nascimento válida.',
            'data_nascimento_responsavel.before' => 'Informe uma data de nascimento válida.',
            'data_nascimento_responsavel.after' => 'Informe uma data de nascimento válida.',
            'filhos.required' => 'Informe os dados de pelo menos um filho.',
            'funcionarios.required' => 'Informe os dados de pelo menos um funcionário.',
            'unidade_id.in' => 'Selecione uma unidade de saúde da lista.',
            'funcionarios.*.unidade_id.in' => 'Selecione uma unidade de saúde da lista.',
            'funcionarios.*.unidade_id.required' => 'Selecione a unidade de saúde do funcionário.',
            'funcionarios.*.cpf.distinct' => 'Este CPF aparece repetido na lista de funcionários.',
            'telefone.regex' => 'Informe o telefone com DDD, por exemplo (65) 99999-9999.',
            'cep.regex' => 'Informe o CEP no formato 00000-000.',
            'uf.regex' => 'Informe a sigla do estado, por exemplo MT.',
            'filhos.*.data_nascimento.*' => 'Informe uma data de nascimento válida.',
            'funcionarios.*.data_nascimento.*' => 'Informe uma data de nascimento válida.',
        ], [
            'filhos.*.nome' => 'nome do filho',
            'filhos.*.serie' => 'série',
            'filhos.*.nome_mae' => 'nome da mãe',
            'funcionarios.*.nome' => 'nome do funcionário',
            'funcionarios.*.nome_mae' => 'nome da mãe',
            'funcionarios.*.cpf' => 'CPF',
            'logradouro' => 'logradouro',
            'numero' => 'número',
            'bairro' => 'bairro',
            'cidade' => 'cidade',
        ]);

        $unidade = fn ($id) => $unidades->firstWhere('id', (int) $id);
        $tipo = $dados['tipo'];

        try {
            $pedido = DB::transaction(function () use ($dados, $tipo, $unidade, $empresa, $proprio) {
                $cpf = $empresa ? null : preg_replace('/\D/', '', $dados['cpf']);
                $unPedido = $empresa ? null : $unidade($dados['unidade_id']);
                $pedido = AtestadoPedido::create([
                    'uuid' => (string) Str::uuid(),
                    'tipo' => $tipo,
                    'responsavel_nome' => trim($dados['nome']),
                    'vinculo' => $tipo === AtestadoPedido::FILHOS ? $dados['vinculo'] : null,
                    'parentesco' => $tipo === AtestadoPedido::RESPONSAVEL_LEGAL && filled($dados['parentesco'] ?? null) ? trim($dados['parentesco']) : null,
                    'data_nascimento_responsavel' => $empresa ? null : $dados['data_nascimento_responsavel'],
                    'cpf' => $cpf,
                    'cpf_hash' => $cpf ? AtestadoPedido::hashCpf($cpf) : null,
                    'cnpj' => $empresa ? Cnpj::normalizar($dados['cnpj']) : null,
                    'telefone' => $dados['telefone'],
                    'cep' => $dados['cep'],
                    'logradouro' => trim($dados['logradouro']),
                    'numero' => trim($dados['numero']),
                    'complemento' => filled($dados['complemento'] ?? null) ? trim($dados['complemento']) : null,
                    'bairro' => trim($dados['bairro']),
                    'cidade' => trim($dados['cidade']),
                    'uf' => $dados['uf'],
                    'email' => strtolower(trim($dados['email'])),
                    'unidade_id' => $unPedido['id'] ?? null,
                    'unidade_nome' => $unPedido['name'] ?? null,
                    'acs_nome' => filled($dados['acs_nome'] ?? null) ? trim($dados['acs_nome']) : null,
                ]);

                if ($empresa) {
                    foreach ($dados['funcionarios'] as $f) {
                        $un = $unidade($f['unidade_id']);
                        $pedido->filhos()->create([
                            'nome' => trim($f['nome']),
                            'data_nascimento' => $f['data_nascimento'],
                            'cpf' => preg_replace('/\D/', '', $f['cpf']),
                            'nome_mae' => trim($f['nome_mae']),
                            'unidade_id' => $un['id'],
                            'unidade_nome' => $un['name'],
                            'token' => Str::random(48),
                        ]);
                    }
                } elseif ($proprio) {
                    $pedido->filhos()->create([
                        'nome' => trim($dados['nome']),
                        'data_nascimento' => $dados['data_nascimento_responsavel'],
                        'cpf' => $cpf,
                        'unidade_id' => $unPedido['id'],
                        'unidade_nome' => $unPedido['name'],
                        'token' => Str::random(48),
                    ]);
                } else {
                    foreach ($dados['filhos'] as $f) {
                        $pedido->filhos()->create([
                            'nome' => trim($f['nome']),
                            'serie' => trim($f['serie']),
                            'data_nascimento' => $f['data_nascimento'],
                            'nome_mae' => trim($f['nome_mae']),
                            'unidade_id' => $unPedido['id'],
                            'unidade_nome' => $unPedido['name'],
                            'token' => Str::random(48),
                        ]);
                    }
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
