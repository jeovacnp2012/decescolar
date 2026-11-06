@extends('layouts.admin')
@section('titulo', 'Pedidos')

@php
    use App\Models\AtestadoPedido;
    use App\Support\FiltroPedidos;
    $semStatus = array_diff_key(request()->query(), ['status' => 1, 'page' => 1]);
    $totalGeral = $totais->sum();
    $temFiltro = collect($filtro)->filter(fn ($v) => $v !== '')->isNotEmpty();
@endphp

@section('conteudo')
    <h1>Pedidos de declaração vacinal</h1>
    <p class="sub">{{ $filhos->total() }} {{ $filhos->total() === 1 ? 'registro' : 'registros' }}{{ $temFiltro ? ' com os filtros aplicados' : '' }}. Cada linha é um filho (um protocolo).</p>

    <div class="totais">
        <a class="total {{ $filtro['status'] === '' ? 'ativo' : '' }}" href="{{ route('admin.pedidos', $semStatus) }}"><strong>{{ $totalGeral }}</strong><span>Todos</span></a>
        @foreach (FiltroPedidos::STATUS as $chave => $rotulo)
            <a class="total {{ $filtro['status'] === $chave ? 'ativo' : '' }}" href="{{ route('admin.pedidos', $semStatus + ['status' => $chave]) }}"><strong>{{ $totais[$chave] ?? 0 }}</strong><span>{{ $rotulo }}</span></a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.pedidos') }}" class="cartao filtros">
        <div class="campo">
            <label for="q">Buscar</label>
            <input type="search" id="q" name="q" value="{{ $filtro['q'] }}" placeholder="Protocolo, nome, CPF ou e-mail" autocomplete="off">
        </div>
        <div class="campo">
            <label for="unidade">Unidade de saúde</label>
            <select id="unidade" name="unidade">
                <option value="">Todas</option>
                @foreach ($unidades as $id => $nome)
                    <option value="{{ $id }}" @selected((string) $filtro['unidade'] === (string) $id)>{{ $nome }}</option>
                @endforeach
            </select>
        </div>
        <div class="campo">
            <label for="status">Situação</label>
            <select id="status" name="status">
                <option value="">Todas</option>
                @foreach (FiltroPedidos::STATUS as $chave => $rotulo)
                    <option value="{{ $chave }}" @selected($filtro['status'] === $chave)>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>
        <div class="campo">
            <label for="vinculo">Vínculo</label>
            <select id="vinculo" name="vinculo">
                <option value="">Todos</option>
                @foreach (AtestadoPedido::VINCULOS as $chave => $rotulo)
                    <option value="{{ $chave }}" @selected($filtro['vinculo'] === $chave)>{{ $rotulo }}</option>
                @endforeach
            </select>
        </div>
        <div class="campo">
            <label for="de">Pedidos de</label>
            <input type="date" id="de" name="de" value="{{ $filtro['de'] }}">
        </div>
        <div class="campo">
            <label for="ate">Pedidos até</label>
            <input type="date" id="ate" name="ate" value="{{ $filtro['ate'] }}">
        </div>
        <div class="filtros-acoes">
            <button type="submit" class="btn">Filtrar</button>
            @if ($temFiltro)<a class="btn contorno" href="{{ route('admin.pedidos') }}">Limpar</a>@endif
        </div>
    </form>

    <div class="cartao imprimir">
        <strong>Imprimir (PDF)</strong>
        <span class="dica">Os relatórios usam os filtros acima.</span>
        <div class="imprimir-botoes">
            <a class="btn contorno pequeno" href="{{ route('admin.relatorio.pedidos', request()->query()) }}">Lista de pedidos</a>
            <a class="btn contorno pequeno" href="{{ route('admin.relatorio.resumo', request()->query()) }}">Resumo por unidade</a>
        </div>
    </div>

    @if ($filhos->isEmpty())
        <div class="alerta info">Nenhum pedido encontrado{{ $temFiltro ? ' com esses filtros' : ' ainda' }}.</div>
    @else
        <table class="tabela">
            <thead>
                <tr><th>Protocolo</th><th>Filho(a)</th><th>Responsável</th><th>Unidade</th><th>Situação</th><th>Pedido em</th></tr>
            </thead>
            <tbody>
                @foreach ($filhos as $f)
                    <tr>
                        <td data-label="Protocolo"><a class="link" href="{{ route('admin.pedido', $f) }}"><strong>{{ $f->protocolo ?? '—' }}</strong></a></td>
                        <td data-label="Filho(a)">{{ $f->nome }}<small>{{ $f->serie }}</small></td>
                        <td data-label="Responsável">{{ $f->pedido->responsavel_nome }}<small>{{ AtestadoPedido::VINCULOS[$f->pedido->vinculo] ?? '' }}{{ $f->pedido->parentesco ? ' ('.$f->pedido->parentesco.')' : '' }} · CPF {{ $f->pedido->cpfMascarado() }}</small></td>
                        <td data-label="Unidade">{{ $f->pedido->unidade_nome }}</td>
                        <td data-label="Situação"><span class="selo {{ $f->status === 'aguardando' ? '' : $f->status }}">{{ $f->statusRotulo() }}</span></td>
                        <td data-label="Pedido em">{{ $f->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <nav class="paginacao" aria-label="Páginas">
            @if ($filhos->previousPageUrl())<a class="btn contorno pequeno" href="{{ $filhos->previousPageUrl() }}">← Anteriores</a>@else<span></span>@endif
            <span class="dica">Página {{ $filhos->currentPage() }} de {{ $filhos->lastPage() }}</span>
            @if ($filhos->nextPageUrl())<a class="btn contorno pequeno" href="{{ $filhos->nextPageUrl() }}">Próximos →</a>@else<span></span>@endif
        </nav>
    @endif
@endsection
