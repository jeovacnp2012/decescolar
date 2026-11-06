@php($erroF = fn ($c) => $errors->has("filhos.$i.$c") ? 'invalido' : '')
<div class="filho" data-item>
    <div class="filho-topo">
        <strong data-titulo>Filho(a)</strong>
        <button type="button" class="btn perigo pequeno" data-remover>Remover</button>
    </div>
    <div class="campo {{ $erroF('nome') }}">
        <label>Nome do filho(a) <span class="obrig" aria-hidden="true">*</span></label>
        <input type="text" name="filhos[{{ $i }}][nome]" value="{{ $f['nome'] ?? '' }}" autocomplete="off" autocapitalize="words">
        @error("filhos.$i.nome")<div class="erro">{{ $message }}</div>@enderror
    </div>
    <div class="grade dois">
        <div class="campo {{ $erroF('serie') }}">
            <label>Série <span class="obrig" aria-hidden="true">*</span></label>
            <input type="text" name="filhos[{{ $i }}][serie]" value="{{ $f['serie'] ?? '' }}" placeholder="Ex.: 3º ano" autocomplete="off">
            @error("filhos.$i.serie")<div class="erro">{{ $message }}</div>@enderror
        </div>
        <div class="campo {{ $erroF('data_nascimento') }}">
            <label>Data de nascimento <span class="obrig" aria-hidden="true">*</span></label>
            <input type="date" name="filhos[{{ $i }}][data_nascimento]" value="{{ $f['data_nascimento'] ?? '' }}" max="{{ now()->format('Y-m-d') }}">
            @error("filhos.$i.data_nascimento")<div class="erro">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="campo {{ $erroF('nome_mae') }}">
        <label>Nome da mãe <span class="obrig" aria-hidden="true">*</span></label>
        <input type="text" name="filhos[{{ $i }}][nome_mae]" value="{{ $f['nome_mae'] ?? '' }}" autocomplete="off" autocapitalize="words" data-nome-mae>
        <div class="dica oculto" data-dica-mae>Preenchido com o seu nome (você é a mãe).</div>
        @error("filhos.$i.nome_mae")<div class="erro">{{ $message }}</div>@enderror
    </div>
</div>
