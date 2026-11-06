@extends('layouts.app')
@section('titulo', 'Acesso administrativo')

@section('conteudo')
    <h1>Acesso administrativo</h1>
    <p class="sub">Entre com o seu e-mail e a sua senha.</p>

    <form method="POST" action="{{ route('admin.entrar') }}" class="cartao" novalidate>
        @csrf
        <div class="campo {{ $errors->has('email') ? 'invalido' : '' }}">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" value="{{ old('email') }}" inputmode="email" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
            @error('email')<div class="erro">{{ $message }}</div>@enderror
        </div>
        <div class="campo {{ $errors->has('password') ? 'invalido' : '' }}">
            <label for="password">Senha</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
            @error('password')<div class="erro">{{ $message }}</div>@enderror
        </div>
        <button type="submit" class="btn">Entrar</button>
    </form>
    <p style="text-align:center"><a class="link" href="{{ route('inicio') }}">← Voltar ao início</a></p>
@endsection
