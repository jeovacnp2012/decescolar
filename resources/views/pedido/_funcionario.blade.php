@php($erroF = fn ($c) => $errors->has("funcionarios.$i.$c") ? 'invalido' : '')
<div class="filho" data-item>
    <div class="filho-topo">
        <strong data-titulo>Funcionário</strong>
        <button type="button" class="btn perigo pequeno" data-remover>Remover</button>
    </div>
    <div class="campo {{ $erroF('nome') }}">
        <label>Nome do funcionário <span class="obrig" aria-hidden="true">*</span></label>
        <input type="text" name="funcionarios[{{ $i }}][nome]" value="{{ $f['nome'] ?? '' }}" autocomplete="off" autocapitalize="words">
        @error("funcionarios.$i.nome")<div class="erro">{{ $message }}</div>@enderror
    </div>
    <div class="grade dois">
        <div class="campo {{ $erroF('data_nascimento') }}">
            <label>Data de nascimento <span class="obrig" aria-hidden="true">*</span></label>
            <input type="date" name="funcionarios[{{ $i }}][data_nascimento]" value="{{ $f['data_nascimento'] ?? '' }}" max="{{ now()->format('Y-m-d') }}">
            @error("funcionarios.$i.data_nascimento")<div class="erro">{{ $message }}</div>@enderror
        </div>
        <div class="campo {{ $erroF('cpf') }}">
            <label>CPF <span class="obrig" aria-hidden="true">*</span></label>
            <input type="text" name="funcionarios[{{ $i }}][cpf]" value="{{ $f['cpf'] ?? '' }}" inputmode="numeric" placeholder="000.000.000-00" maxlength="14" autocomplete="off" data-mascara="cpf">
            @error("funcionarios.$i.cpf")<div class="erro">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="campo {{ $erroF('nome_mae') }}">
        <label>Nome da mãe <span class="obrig" aria-hidden="true">*</span></label>
        <input type="text" name="funcionarios[{{ $i }}][nome_mae]" value="{{ $f['nome_mae'] ?? '' }}" autocomplete="off" autocapitalize="words">
        @error("funcionarios.$i.nome_mae")<div class="erro">{{ $message }}</div>@enderror
    </div>
    <div class="campo {{ $erroF('unidade_id') }}">
        <label>Unidade de saúde do funcionário <span class="obrig" aria-hidden="true">*</span></label>
        @include('pedido._combo', ['name' => "funcionarios[$i][unidade_id]", 'valor' => $f['unidade_id'] ?? ''])
        @error("funcionarios.$i.unidade_id")<div class="erro">{{ $message }}</div>@enderror
    </div>
</div>
