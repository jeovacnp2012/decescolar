@extends('layouts.app')
@section('titulo', 'Unidade de saúde')

@section('conteudo')
    <h1>{{ $titulo }}</h1>
    <p class="sub">{{ $dica }}</p>

    <section class="cartao">
        <div class="duas-opcoes">
            <a class="btn" href="{{ $sim }}">Sim</a>
            <a class="btn contorno" href="{{ route('triagem.procure') }}">Não</a>
        </div>
    </section>
    <p style="text-align:center"><a class="link" href="{{ route('inicio') }}">← Voltar ao início</a></p>
@endsection
