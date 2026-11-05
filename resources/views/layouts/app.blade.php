<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#006440">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo', 'Declaração Vacinal') — Prefeitura de Nova Mutum</title>
    <link rel="icon" type="image/png" href="{{ asset('img/brasao.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>
    <header class="topo">
        <a href="{{ route('pedido.create') }}" aria-label="Início — Declaração vacinal">
            <img src="{{ asset('img/logo-horizontal.png') }}" alt="Prefeitura de Nova Mutum" width="170" height="56">
        </a>
    </header>
    <main>
        @yield('conteudo')
    </main>
    <div class="faixa"></div>
    <footer class="rodape">
        Prefeitura Municipal de Nova Mutum — MT<br>
        Seus dados são usados somente para emitir a declaração vacinal (LGPD).
    </footer>
    @stack('scripts')
</body>
</html>
