@extends('layouts.app')
@section('titulo', 'Consultar pedido')

@section('conteudo')
    <h1>Consultar declaração vacinal</h1>
    <p class="sub">Informe o CPF do responsável (ou o CNPJ, se o pedido foi de uma empresa) e o protocolo do pedido (exemplo: ATV000123).</p>

    <form method="POST" action="{{ route('consulta.buscar') }}" class="cartao" novalidate>
        @csrf
        <div class="campo {{ $errors->has('cpf') ? 'invalido' : '' }}">
            <label for="cpf">CPF ou CNPJ</label>
            <input type="text" id="cpf" name="cpf" value="{{ old('cpf') }}" placeholder="CPF ou CNPJ" maxlength="18" autocomplete="off" autocapitalize="characters" data-mascara="doc" required>
            @error('cpf')<div class="erro">{{ $message }}</div>@enderror
        </div>
        <div class="campo {{ $errors->has('protocolo') ? 'invalido' : '' }}">
            <label for="protocolo">Protocolo</label>
            <input type="text" id="protocolo" name="protocolo" value="{{ old('protocolo') }}" placeholder="ATV000123" autocapitalize="characters" autocomplete="off" required>
            @error('protocolo')<div class="erro">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn">Consultar</button>
    </form>
    <p style="text-align:center"><a class="link" href="{{ route('pedido.create') }}">Fazer um novo pedido</a></p>
@endsection

@push('scripts')
    <script src="{{ asset('js/pedido.js') }}?v={{ filemtime(public_path('js/pedido.js')) }}"></script>
@endpush
