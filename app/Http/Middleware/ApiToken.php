<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Autentica o chamados-multi-tenant (e qualquer integração autorizada) pelo token Bearer deste sistema. */
class ApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $esperado = (string) config('decescolar.api_token');
        $enviado = (string) $request->bearerToken();

        if ($esperado === '' || $enviado === '' || ! hash_equals($esperado, $enviado)) {
            return response()->json(['message' => 'Token de API inválido.'], 401);
        }

        return $next($request);
    }
}
