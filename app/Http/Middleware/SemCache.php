<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Páginas com dados pessoais não ficam no cache do navegador nem de proxies. */
class SemCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $resposta = $next($request);
        $resposta->headers->set('Cache-Control', 'no-store, private, max-age=0');
        $resposta->headers->set('Pragma', 'no-cache');
        $resposta->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $resposta;
    }
}
