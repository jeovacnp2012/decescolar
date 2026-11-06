{{-- Seleção pesquisável de unidade de saúde. Parâmetros: $name (campo enviado), $valor (id atual). --}}
<div class="combo" data-combo>
    <input type="search" class="combo-busca" role="combobox" aria-autocomplete="list" aria-expanded="false"
           placeholder="Digite para pesquisar…" autocomplete="off" enterkeyhint="search">
    <span class="combo-seta" aria-hidden="true">▼</span>
    <ul class="combo-lista oculto" role="listbox"></ul>
    <input type="hidden" class="combo-valor" name="{{ $name }}" value="{{ $valor ?? '' }}">
</div>
