@extends('layouts.app')
@section('titulo', 'Pedido registrado')

@section('conteudo')
    <h1>Pedido registrado!</h1>
    <p class="sub">{{ $pedido->ehEmpresa() ? "Enviamos o pedido às unidades de saúde dos funcionários." : "Enviamos seu pedido para a unidade" }}{!! $pedido->ehEmpresa() ? "" : " <strong>".e($pedido->unidade_nome)."</strong>." !!} Quando a declaração vacinal for emitida, avisaremos em <strong>{{ $pedido->email }}</strong>.</p>

    <div class="alerta info">Guarde os números de protocolo abaixo. Com {{ $pedido->ehEmpresa() ? "o CNPJ" : "o seu CPF" }} e o protocolo você consulta e baixa a declaração a qualquer momento.</div>

    @foreach ($pedido->filhos as $filho)
        <section class="cartao">
            <span class="selo {{ $filho->status === 'aguardando' ? '' : $filho->status }}">{{ $filho->statusRotulo() }}</span>
            <h2 style="margin-top:10px">{{ $filho->nome }}</h2>
            <div class="protocolo">{{ $filho->protocolo }}</div>
            <dl class="dados" style="margin-top:12px">
                @if ($filho->serie)<div><dt>Série</dt><dd>{{ $filho->serie }}</dd></div>@endif
                <div><dt>Unidade de saúde</dt><dd>{{ $filho->unidade_nome ?? $pedido->unidade_nome }}</dd></div>
            </dl>
            <p style="margin:14px 0 0"><a class="btn contorno" href="{{ route('atestado.show', $filho->token) }}">Acompanhar este pedido</a></p>
        </section>
    @endforeach

    <p style="text-align:center"><a class="link" href="{{ route('pedido.create') }}">Fazer outro pedido</a></p>
@endsection
