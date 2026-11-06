@php($erroF = fn ($c) => $errors->has("filhos.$i.$c") ? 'invalido' : '')
<div class="filho" data-filho>
    <div class="filho-topo">
        <strong data-titulo>Filho(a)</strong>
        <button type="button" class="btn perigo pequeno" data-remover>Remover</button>
    </div>
    <div class="campo {{ $erroF('nome') }}">
        <label>Nome do filho(a)</label>
        <input type="text" name="filhos[{{ $i }}][nome]" value="{{ $f['nome'] ?? '' }}" autocomplete="off" autocapitalize="words" data-req>
        @error("filhos.$i.nome")<div class="erro">{{ $message }}</div>@enderror
    </div>
    <div class="grade dois">
        <div class="campo {{ $erroF('serie') }}">
            <label>Série</label>
            <input type="text" name="filhos[{{ $i }}][serie]" value="{{ $f['serie'] ?? '' }}" placeholder="Ex.: 3º ano" autocomplete="off" data-req>
            @error("filhos.$i.serie")<div class="erro">{{ $message }}</div>@enderror
        </div>
        <div class="campo {{ $erroF('data_nascimento') }}">
            <label>Data de nascimento</label>
            <input type="date" name="filhos[{{ $i }}][data_nascimento]" value="{{ $f['data_nascimento'] ?? '' }}" max="{{ now()->format('Y-m-d') }}" data-req>
            @error("filhos.$i.data_nascimento")<div class="erro">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="campo {{ $erroF('nome_mae') }}">
        <label>Nome da mãe</label>
        <input type="text" name="filhos[{{ $i }}][nome_mae]" value="{{ $f['nome_mae'] ?? '' }}" autocomplete="off" autocapitalize="words" data-req>
        @error("filhos.$i.nome_mae")<div class="erro">{{ $message }}</div>@enderror
    </div>
</div>
