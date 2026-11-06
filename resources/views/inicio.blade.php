@extends('layouts.app')
@section('titulo', 'Declaração vacinal')

@section('conteudo')
    <h1>Declaração vacinal para matrícula escolar</h1>
    <p class="sub">Peça o atestado vacinal do seu filho ou consulte e baixe um pedido que você já fez.</p>

    <section class="cartao">
        <a class="btn contorno" href="{{ route('consulta.form') }}">Já fiz um pedido — consultar / baixar</a>
        <p class="dica" style="margin:8px 0 20px">Use o seu CPF e o número do protocolo para acompanhar e baixar o PDF.</p>

        <a class="btn" href="{{ route('pedido.create') }}">Solicitar atestado</a>
        <p class="dica" style="margin:8px 0 0">Preencha os seus dados e os dos filhos. A unidade de saúde emite a declaração e você baixa o PDF aqui.</p>
    </section>
@endsection
