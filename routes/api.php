<?php

use App\Http\Controllers\Api\AtestadoProntoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['throttle:120,1', 'api.token'])->group(function () {
    Route::get('/ping', fn () => response()->json(['status' => 'ok', 'sistema' => 'decescolar']));

    // O chamados avisa que o atestado da unidade de saúde está pronto. Pode enviar o PDF (multipart, campo "arquivo")
    // ou apenas avisar — nesse caso o decescolar busca o PDF na API do chamados.
    Route::post('/atestados/{protocolo}/pronto', [AtestadoProntoController::class, 'store']);
    // A unidade de saúde excluiu o pedido no chamados (o chamados só exclui se aqui responder 2xx).
    Route::post('/atestados/{protocolo}/cancelado', [AtestadoProntoController::class, 'cancelado']);
    Route::get('/atestados/{protocolo}', [AtestadoProntoController::class, 'show']);
});
