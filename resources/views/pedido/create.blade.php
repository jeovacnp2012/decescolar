@extends('layouts.app')
@section('titulo', 'Pedir declaração vacinal')

@php
    $filhosAntigos = old('filhos', []);
    $funcAntigos = old('funcionarios', []);
    $tipoAtual = old('tipo', '');
    $campoErro = fn ($n) => $errors->has($n) ? 'invalido' : '';
    $T = \App\Models\AtestadoPedido::class;
    $porTipo = fn (array $m) => e(json_encode($m, JSON_UNESCAPED_UNICODE));
    $obr = '<span class="obrig" aria-hidden="true">*</span>';
@endphp

@section('conteudo')
    <h1>Declaração vacinal para matrícula escolar</h1>
    <p class="sub">Preencha os dados abaixo. Quando a unidade de saúde emitir a declaração, avisaremos você por e-mail para baixar o PDF.</p>
    <p class="legenda-obrig"><span class="obrig" aria-hidden="true">*</span> Campo obrigatório</p>

    @if ($erroUnidades)
        <div class="alerta erro-box" role="alert">{{ $erroUnidades }}</div>
    @endif
    @if ($errors->has('geral'))
        <div class="alerta erro-box" role="alert">{{ $errors->first('geral') }}</div>
    @elseif ($errors->any())
        <div class="alerta erro-box" role="alert">Confira os campos destacados em vermelho.</div>
    @endif

    <script type="application/json" id="dados-unidades">@json($unidades)</script>

    <form method="POST" action="{{ route('pedido.store') }}" id="form-pedido" data-cep-url="{{ url('/cep') }}" novalidate>
        @csrf

        <section class="cartao">
            <div class="campo {{ $campoErro('tipo') }}">
                <label for="tipo">Para quem é o atestado? {!! $obr !!}</label>
                <select id="tipo" name="tipo">
                    <option value="">Selecione…</option>
                    <option value="filhos" @selected($tipoAtual === 'filhos')>Para filhos</option>
                    <option value="responsavel_legal" @selected($tipoAtual === 'responsavel_legal')>Responsável legal</option>
                    <option value="proprio" @selected($tipoAtual === 'proprio')>Meu atestado</option>
                    <option value="empresa" @selected($tipoAtual === 'empresa')>Para um funcionário de empresa/indústria</option>
                </select>
                @error('tipo')<div class="erro">{{ $message }}</div>@enderror
            </div>
        </section>

        <div id="corpo" class="oculto">
            <section class="cartao">
                <h2 data-por-tipo='{!! $porTipo(['filhos' => 'Seus dados', 'responsavel_legal' => 'Seus dados', 'proprio' => 'Seus dados', 'empresa' => 'Dados da empresa']) !!}'>Seus dados</h2>

                <div class="campo {{ $campoErro('nome') }}">
                    <label for="nome"><span data-por-tipo='{!! $porTipo(['filhos' => 'Nome do familiar', 'responsavel_legal' => 'Nome do responsável legal', 'proprio' => 'Nome completo', 'empresa' => 'Nome da empresa']) !!}'>Nome completo</span> {!! $obr !!}</label>
                    <input type="text" id="nome" name="nome" value="{{ old('nome') }}" autocomplete="name" autocapitalize="words">
                    @error('nome')<div class="erro">{{ $message }}</div>@enderror
                </div>

                <fieldset class="campo opcoes-grupo {{ $campoErro('vinculo') }}" data-tipos="filhos">
                    <legend>Você é {!! $obr !!}</legend>
                    <div class="opcoes">
                        @foreach (['pai', 'mae'] as $valor)
                            <label class="opcao">
                                <input type="radio" name="vinculo" value="{{ $valor }}" @checked(old('vinculo') === $valor)>
                                <span>{{ $T::VINCULOS[$valor] }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('vinculo')<div class="erro">{{ $message }}</div>@enderror
                </fieldset>

                <div class="campo {{ $campoErro('parentesco') }}" data-tipos="responsavel_legal">
                    <label for="parentesco">Parentesco com a criança <span class="opcional">— opcional</span></label>
                    <input type="text" id="parentesco" name="parentesco" value="{{ old('parentesco') }}" maxlength="60" placeholder="Ex.: tia, avó, tutor" autocapitalize="sentences" autocomplete="off">
                    @error('parentesco')<div class="erro">{{ $message }}</div>@enderror
                </div>

                <div class="campo {{ $campoErro('data_nascimento_responsavel') }}" data-tipos="filhos responsavel_legal proprio">
                    <label for="data_nascimento_responsavel">Data de nascimento {!! $obr !!}</label>
                    <input type="date" id="data_nascimento_responsavel" name="data_nascimento_responsavel" value="{{ old('data_nascimento_responsavel') }}" max="{{ now()->format('Y-m-d') }}" autocomplete="bday">
                    <div class="dica" data-por-tipo='{!! $porTipo(['filhos' => 'Pai ou mãe: a partir de 15 anos.', 'responsavel_legal' => 'Responsável legal: a partir de 18 anos.', 'proprio' => 'A partir de 15 anos.']) !!}'></div>
                    @error('data_nascimento_responsavel')<div class="erro">{{ $message }}</div>@enderror
                </div>

                <div class="campo {{ $campoErro('cpf') }}" data-tipos="filhos responsavel_legal proprio">
                    <label for="cpf">CPF {!! $obr !!}</label>
                    <input type="text" id="cpf" name="cpf" value="{{ old('cpf') }}" inputmode="numeric" placeholder="000.000.000-00" maxlength="14" autocomplete="off" data-mascara="cpf">
                    @error('cpf')<div class="erro">{{ $message }}</div>@enderror
                </div>

                <div class="campo {{ $campoErro('cnpj') }}" data-tipos="empresa">
                    <label for="cnpj">CNPJ {!! $obr !!}</label>
                    <input type="text" id="cnpj" name="cnpj" value="{{ old('cnpj') }}" placeholder="00.000.000/0000-00" maxlength="18" autocomplete="off" autocapitalize="characters" data-mascara="cnpj">
                    @error('cnpj')<div class="erro">{{ $message }}</div>@enderror
                </div>

                <div class="grade dois">
                    <div class="campo {{ $campoErro('telefone') }}">
                        <label for="telefone">Telefone (WhatsApp) {!! $obr !!}</label>
                        <input type="tel" id="telefone" name="telefone" value="{{ old('telefone') }}" inputmode="tel" placeholder="(65) 99999-9999" maxlength="15" autocomplete="tel-national" data-mascara="telefone">
                        @error('telefone')<div class="erro">{{ $message }}</div>@enderror
                    </div>
                    <div class="campo {{ $campoErro('email') }}">
                        <label for="email">E-mail {!! $obr !!}</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" inputmode="email" autocomplete="email" placeholder="voce@exemplo.com">
                        @error('email')<div class="erro">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="dica" style="margin:-8px 0 16px">Enviaremos o aviso e a declaração vacinal para este e-mail.</div>

                <div class="campo {{ $campoErro('cep') }}">
                    <label for="cep">CEP {!! $obr !!}</label>
                    <input type="text" id="cep" name="cep" value="{{ old('cep') }}" inputmode="numeric" placeholder="00000-000" maxlength="9" autocomplete="postal-code" data-mascara="cep">
                    <div class="dica" id="cep-status">Ao informar o CEP, buscamos o endereço para você.</div>
                    @error('cep')<div class="erro">{{ $message }}</div>@enderror
                </div>
                <div class="campo {{ $campoErro('logradouro') }}">
                    <label for="logradouro">Logradouro (rua/avenida) {!! $obr !!}</label>
                    <input type="text" id="logradouro" name="logradouro" value="{{ old('logradouro') }}" maxlength="255" autocomplete="address-line1" autocapitalize="words">
                    @error('logradouro')<div class="erro">{{ $message }}</div>@enderror
                </div>
                <div class="grade dois">
                    <div class="campo {{ $campoErro('numero') }}">
                        <label for="numero">Número {!! $obr !!}</label>
                        <input type="text" id="numero" name="numero" value="{{ old('numero') }}" maxlength="20" autocomplete="off" placeholder="S/N se não houver">
                        @error('numero')<div class="erro">{{ $message }}</div>@enderror
                    </div>
                    <div class="campo {{ $campoErro('complemento') }}">
                        <label for="complemento">Complemento <span class="opcional">— opcional</span></label>
                        <input type="text" id="complemento" name="complemento" value="{{ old('complemento') }}" maxlength="100" autocomplete="address-line2">
                        @error('complemento')<div class="erro">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="campo {{ $campoErro('bairro') }}">
                    <label for="bairro">Bairro {!! $obr !!}</label>
                    <input type="text" id="bairro" name="bairro" value="{{ old('bairro') }}" maxlength="100" autocomplete="off" autocapitalize="words">
                    @error('bairro')<div class="erro">{{ $message }}</div>@enderror
                </div>
                <div class="grade cidade-uf">
                    <div class="campo {{ $campoErro('cidade') }}">
                        <label for="cidade">Cidade {!! $obr !!}</label>
                        <input type="text" id="cidade" name="cidade" value="{{ old('cidade') }}" maxlength="100" autocomplete="address-level2" autocapitalize="words">
                        @error('cidade')<div class="erro">{{ $message }}</div>@enderror
                    </div>
                    <div class="campo {{ $campoErro('uf') }}">
                        <label for="uf">UF {!! $obr !!}</label>
                        <input type="text" id="uf" name="uf" value="{{ old('uf') }}" maxlength="2" autocomplete="address-level1" autocapitalize="characters" style="text-transform:uppercase">
                        @error('uf')<div class="erro">{{ $message }}</div>@enderror
                    </div>
                </div>
            </section>

            <section class="cartao">
                <h2 data-por-tipo='{!! $porTipo(['filhos' => 'Unidade de saúde', 'responsavel_legal' => 'Unidade de saúde', 'proprio' => 'Unidade de saúde', 'empresa' => 'Agente Comunitário de Saúde']) !!}'>Unidade de saúde</h2>
                <div class="campo {{ $campoErro('unidade_id') }}" data-tipos="filhos responsavel_legal proprio">
                    <label>Unidade de saúde da sua família {!! $obr !!}</label>
                    @include('pedido._combo', ['name' => 'unidade_id', 'valor' => old('unidade_id')])
                    <div class="dica">Escolha o posto/unidade onde sua família é atendida.</div>
                    @error('unidade_id')<div class="erro">{{ $message }}</div>@enderror
                </div>
                <div class="campo {{ $campoErro('acs_nome') }}">
                    <label for="acs_nome">Nome do ACS (Agente Comunitário de Saúde) <span class="opcional">— opcional</span></label>
                    <input type="text" id="acs_nome" name="acs_nome" value="{{ old('acs_nome') }}" maxlength="255" autocapitalize="words" autocomplete="off">
                    @error('acs_nome')<div class="erro">{{ $message }}</div>@enderror
                </div>
            </section>

            <section class="cartao" data-tipos="filhos responsavel_legal">
                <h2 data-por-tipo='{!! $porTipo(['filhos' => 'Filhos', 'responsavel_legal' => 'Filhos sob sua responsabilidade legal']) !!}'>Filhos</h2>
                <div id="lista-filhos">
                    @foreach ($filhosAntigos as $i => $f)
                        @include('pedido._filho', ['i' => $i, 'f' => $f])
                    @endforeach
                </div>
                @error('filhos')<div class="erro">{{ $message }}</div>@enderror
                <button type="button" class="btn contorno" id="add-filho">+ Adicionar outro filho</button>
            </section>

            <section class="cartao" data-tipos="empresa">
                <h2>Funcionários</h2>
                <div id="lista-funcionarios">
                    @foreach ($funcAntigos as $i => $f)
                        @include('pedido._funcionario', ['i' => $i, 'f' => $f])
                    @endforeach
                </div>
                @error('funcionarios')<div class="erro">{{ $message }}</div>@enderror
                <button type="button" class="btn contorno" id="add-funcionario">+ Adicionar outro funcionário</button>
            </section>

            <button type="submit" class="btn" id="enviar">Enviar pedido</button>
        </div>
        <p style="text-align:center;margin-top:16px"><a class="link" href="{{ route('inicio') }}">← Voltar ao início</a></p>
    </form>

    <template id="modelo-filho">
        @include('pedido._filho', ['i' => '__I__', 'f' => []])
    </template>
    <template id="modelo-funcionario">
        @include('pedido._funcionario', ['i' => '__I__', 'f' => []])
    </template>
@endsection

@push('scripts')
    <script src="{{ asset('js/pedido.js') }}?v={{ filemtime(public_path('js/pedido.js')) }}"></script>
@endpush
