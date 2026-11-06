<?php

use App\Http\Middleware\ApiToken;
use App\Http\Middleware\SemCache;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['api.token' => ApiToken::class, 'sem.cache' => SemCache::class]);
        // Área administrativa: quem não entrou vai para o login; quem já entrou e abre o login vai para a lista.
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.pedidos'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
