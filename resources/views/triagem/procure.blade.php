@extends('layouts.app')
@section('titulo', 'Procure uma unidade de saúde')

@section('conteudo')
    <section class="cartao aviso-unidade" role="alert">
        <div class="aviso-icone" aria-hidden="true">
            <svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 12 2.5a7 7 0 0 1 7 7C19 14.800 12 21 12 21z"/><path d="M12 7v5M9.500 9.500h5"/></svg>
        </div>
        <h1>Procure uma unidade de saúde</h1>
        <p>Favor procure uma unidade de saúde mais próxima para adquirir seu atestado vacinal.</p>
        <a class="btn" href="{{ route('inicio') }}">OK</a>
    </section>
@endsection
