@extends('layouts.admin')
@section('titulo', 'Pedido '.$filho->protocolo)

@php
    use App\Models\AtestadoPedido;
    $p = $filho->pedido;
    $cpf = preg_replace('/\D/', '', $p->cpf);
    $cpfFormatado = strlen($cpf) === 11 ? substr($cpf, 0, 3).'.'.substr($cpf, 3, 3).'.'.substr($cpf, 6, 3).'-'.substr($cpf, 9, 2) : $cpf;
    $nascResp = $p->data_nascimento_responsavel ? \Carbon\Carbon::parse($p->data_nascimento_responsavel) : null;
@endphp

@section('conteudo')
    <p><a class="link" href="{{ route('admin.pedidos') }}">← Voltar à lista</a></p>
    <h1>{{ $filho->protocolo ?? 'Pedido sem protocolo' }}</h1>
    <p class="sub"><span class="selo {{ $filho->status === 'aguardando' ? '' : $filho->status }}">{{ $filho->statusRotulo() }}</span></p>

    <section class="cartao">
        <h2>Filho(a)</h2>
        <dl class="dados">
            <div><dt>Nome</dt><dd>{{ $filho->nome }}</dd></div>
            <div><dt>Série</dt><dd>{{ $filho->serie }}</dd></div>
            <div><dt>Data de nascimento</dt><dd>{{ $filho->data_nascimento->format('d/m/Y') }}</dd></div>
            <div><dt>Nome da mãe</dt><dd>{{ $filho->nome_mae }}</dd></div>
        </dl>
    </section>

    <section class="cartao">
        <h2>Responsável</h2>
        <dl class="dados">
            <div><dt>Nome</dt><dd>{{ $p->responsavel_nome }}</dd></div>
            <div><dt>Vínculo</dt><dd>{{ AtestadoPedido::VINCULOS[$p->vinculo] ?? '—' }}{{ $p->parentesco ? ' — '.$p->parentesco : '' }}</dd></div>
            <div><dt>Data de nascimento</dt><dd>{{ $nascResp ? $nascResp->format('d/m/Y').' ('.$nascResp->age.' anos)' : '—' }}</dd></div>
            <div><dt>CPF</dt><dd>{{ $cpfFormatado }}</dd></div>
            <div><dt>Telefone</dt><dd>{{ $p->telefone }}</dd></div>
            <div><dt>E-mail</dt><dd>{{ $p->email }}</dd></div>
            <div><dt>CEP</dt><dd>{{ $p->cep }}</dd></div>
        </dl>
    </section>

    <section class="cartao">
        <h2>Atendimento</h2>
        <dl class="dados">
            <div><dt>Unidade de saúde</dt><dd>{{ $p->unidade_nome }}</dd></div>
            <div><dt>ACS</dt><dd>{{ $p->acs_nome ?: '—' }}</dd></div>
            <div><dt>Pedido feito em</dt><dd>{{ $filho->created_at->format('d/m/Y H:i') }}</dd></div>
            <div><dt>Declaração pronta em</dt><dd>{{ $filho->pronto_em?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            <div><dt>Baixada pelo responsável em</dt><dd>{{ $filho->baixado_em?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            @if ($filho->status === 'cancelado')
                <div><dt>Cancelado em</dt><dd>{{ $filho->cancelado_em?->format('d/m/Y H:i') }} — por {{ $filho->cancelado_por === 'unidade' ? 'a unidade de saúde' : 'o responsável' }}</dd></div>
                @if ($filho->cancelado_motivo)<div><dt>Motivo informado</dt><dd>{{ $filho->cancelado_motivo }}</dd></div>@endif
            @endif
        </dl>
    </section>

    @if ($irmaos->isNotEmpty())
        <section class="cartao">
            <h2>Outros filhos do mesmo pedido</h2>
            @foreach ($irmaos as $i)
                <p style="margin:0 0 8px"><a class="link" href="{{ route('admin.pedido', $i) }}">{{ $i->protocolo }} — {{ $i->nome }}</a> <span class="selo {{ $i->status === 'aguardando' ? '' : $i->status }}">{{ $i->statusRotulo() }}</span></p>
            @endforeach
        </section>
    @endif
@endsection
