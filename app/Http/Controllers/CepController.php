<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Consulta de CEP no ViaCEP feita pelo servidor (o navegador do cidadão não fala com terceiros). */
class CepController extends Controller
{
    public function buscar(string $cep): JsonResponse
    {
        $cep = preg_replace('/\D/', '', $cep);
        if (strlen($cep) !== 8) {
            return response()->json(['message' => 'CEP inválido.'], 422);
        }

        $dados = Cache::remember("cep.$cep", now()->addDay(), function () use ($cep) {
            try {
                $r = Http::timeout(5)->acceptJson()->get("https://viacep.com.br/ws/$cep/json/");
            } catch (Throwable $e) {
                return null; // erro de rede: não guarda no cache
            }
            if (! $r->successful()) {
                return null;
            }
            if ($r->json('erro')) {
                return ['encontrado' => false];
            }

            return [
                'encontrado' => true,
                'logradouro' => (string) $r->json('logradouro'),
                'bairro' => (string) $r->json('bairro'),
                'cidade' => (string) $r->json('localidade'),
                'uf' => (string) $r->json('uf'),
            ];
        });

        if ($dados === null) {
            Cache::forget("cep.$cep");

            return response()->json(['encontrado' => false, 'indisponivel' => true]);
        }

        return response()->json($dados);
    }
}
