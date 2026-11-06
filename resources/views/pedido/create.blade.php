@extends('layouts.app')
@section('titulo', 'Pedir declaração vacinal')

@php
    $filhosAntigos = old('filhos', []);
    $marcado = old('pedir_atestado') ? true : false;
    $campoErro = fn ($n) => $errors->has($n) ? 'invalido' : '';
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

    <form method="POST" action="{{ route('pedido.store') }}" id="form-pedido" novalidate>
        @csrf

        <section class="cartao">
            <h2>Seus dados</h2>
            <div class="campo {{ $campoErro('nome') }}">
                <label for="nome">Nome completo <span class="obrig" aria-hidden="true">*</span></label>
                <input type="text" id="nome" name="nome" value="{{ old('nome') }}" autocomplete="name" autocapitalize="words" required>
                @error('nome')<div class="erro">{{ $message }}</div>@enderror
            </div>
            <fieldset class="campo opcoes-grupo {{ $campoErro('vinculo') }}">
                <legend>Você é <span class="obrig" aria-hidden="true">*</span></legend>
                <div class="opcoes">
                    @foreach (\App\Models\AtestadoPedido::VINCULOS as $valor => $rotulo)
                        <label class="opcao">
                            <input type="radio" name="vinculo" value="{{ $valor }}" @checked(old('vinculo') === $valor) required>
                            <span>{{ $rotulo }}</span>
                        </label>
                    @endforeach
                </div>
                @error('vinculo')<div class="erro">{{ $message }}</div>@enderror
            </fieldset>
            <div class="campo {{ $campoErro('parentesco') }} {{ old('vinculo') === 'outro' ? '' : 'oculto' }}" id="campo-parentesco">
                <label for="parentesco">Qual o seu parentesco com a criança? <span class="obrig" aria-hidden="true">*</span></label>
                <input type="text" id="parentesco" name="parentesco" value="{{ old('parentesco') }}" maxlength="60" placeholder="Ex.: tia, avó, tutor" autocapitalize="sentences" autocomplete="off">
                @error('parentesco')<div class="erro">{{ $message }}</div>@enderror
            </div>
            <div class="campo {{ $campoErro('data_nascimento_responsavel') }}">
                <label for="data_nascimento_responsavel">Sua data de nascimento <span class="obrig" aria-hidden="true">*</span></label>
                <input type="date" id="data_nascimento_responsavel" name="data_nascimento_responsavel" value="{{ old('data_nascimento_responsavel') }}" max="{{ now()->format('Y-m-d') }}" autocomplete="bday" required>
                <div class="dica">Pai ou mãe: a partir de 15 anos. Responsável legal ou outro parentesco: a partir de 18 anos.</div>
                @error('data_nascimento_responsavel')<div class="erro">{{ $message }}</div>@enderror
            </div>
            <div class="grade dois">
                <div class="campo {{ $campoErro('cpf') }}">
                    <label for="cpf">CPF <span class="obrig" aria-hidden="true">*</span></label>
                    <input type="text" id="cpf" name="cpf" value="{{ old('cpf') }}" inputmode="numeric" placeholder="000.000.000-00" maxlength="14" autocomplete="off" data-mascara="cpf" required>
                    @error('cpf')<div class="erro">{{ $message }}</div>@enderror
                </div>
                <div class="campo {{ $campoErro('telefone') }}">
                    <label for="telefone">Telefone (WhatsApp) <span class="obrig" aria-hidden="true">*</span></label>
                    <input type="tel" id="telefone" name="telefone" value="{{ old('telefone') }}" inputmode="tel" placeholder="(65) 99999-9999" maxlength="15" autocomplete="tel-national" data-mascara="telefone" required>
                    @error('telefone')<div class="erro">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="campo {{ $campoErro('email') }}">
                <label for="email">E-mail <span class="obrig" aria-hidden="true">*</span></label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" inputmode="email" autocomplete="email" placeholder="voce@exemplo.com" required>
                <div class="dica">Enviaremos o aviso e a declaração vacinal para este e-mail.</div>
                @error('email')<div class="erro">{{ $message }}</div>@enderror
            </div>
            <div class="campo {{ $campoErro('cep') }}">
                <label for="cep">CEP <span class="obrig" aria-hidden="true">*</span></label>
                <input type="text" id="cep" name="cep" value="{{ old('cep') }}" inputmode="numeric" placeholder="00000-000" maxlength="9" autocomplete="postal-code" data-mascara="cep" required>
                @error('cep')<div class="erro">{{ $message }}</div>@enderror
            </div>
        </section>

        <section class="cartao">
            <h2>Unidade de saúde</h2>
            <div class="campo {{ $campoErro('unidade_id') }}">
                <label for="unidade-busca" id="unidade-rotulo">Unidade de saúde da sua família <span class="obrig" aria-hidden="true">*</span></label>
                <div class="combo" id="combo-unidade" data-unidades='@json($unidades)'>
                    <input type="search" id="unidade-busca" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="unidade-lista"
                           placeholder="Digite para pesquisar…" autocomplete="off" enterkeyhint="search" @disabled(empty($unidades))>
                    <span class="combo-seta" aria-hidden="true">▼</span>
                    <ul class="combo-lista oculto" id="unidade-lista" role="listbox" aria-labelledby="unidade-rotulo"></ul>
                    <input type="hidden" name="unidade_id" id="unidade_id" value="{{ old('unidade_id') }}">
                </div>
                <div class="dica">Escolha o posto/unidade onde sua família é atendida.</div>
                @error('unidade_id')<div class="erro">{{ $message }}</div>@enderror
            </div>
            <div class="campo {{ $campoErro('acs_nome') }}">
                <label for="acs_nome">Nome do ACS (Agente Comunitário de Saúde) <span class="opcional">— opcional</span></label>
                <input type="text" id="acs_nome" name="acs_nome" value="{{ old('acs_nome') }}" maxlength="255" autocapitalize="words" autocomplete="off">
                @error('acs_nome')<div class="erro">{{ $message }}</div>@enderror
            </div>
        </section>

        <section class="cartao">
            <label class="check" for="pedir_atestado">
                <input type="checkbox" id="pedir_atestado" name="pedir_atestado" value="1" @checked($marcado)>
                <span>Peço atestado vacinal</span>
            </label>
            @error('pedir_atestado')<div class="erro">{{ $message }}</div>@enderror

            <div id="area-filhos" class="{{ $marcado ? '' : 'oculto' }}" style="margin-top:16px">
                <div id="lista-filhos">
                    @foreach ($filhosAntigos as $i => $f)
                        @include('pedido._filho', ['i' => $i, 'f' => $f])
                    @endforeach
                </div>
                @error('filhos')<div class="erro">{{ $message }}</div>@enderror
                <button type="button" class="btn contorno" id="add-filho">+ Adicionar outro filho</button>
            </div>
        </section>

        <button type="submit" class="btn" id="enviar">Enviar pedido</button>
        <p style="text-align:center;margin-top:16px"><a class="link" href="{{ route('inicio') }}">← Voltar ao início</a></p>
    </form>

    <template id="modelo-filho">
        @include('pedido._filho', ['i' => '__I__', 'f' => []])
    </template>
@endsection

@push('scripts')
    <script src="{{ asset('js/pedido.js') }}?v={{ filemtime(public_path('js/pedido.js')) }}"></script>
@endpush
