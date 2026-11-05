<?php

use App\Http\Controllers\AtestadoController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\PedidoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PedidoController::class, 'create'])->name('pedido.create');
Route::post('/pedido', [PedidoController::class, 'store'])->middleware('throttle:10,1')->name('pedido.store');
Route::get('/pedido/{uuid}', [PedidoController::class, 'show'])->name('pedido.show');

Route::get('/consultar', [ConsultaController::class, 'form'])->name('consulta.form');
Route::post('/consultar', [ConsultaController::class, 'buscar'])->middleware('throttle:10,1')->name('consulta.buscar');

Route::get('/atestado/{token}', [AtestadoController::class, 'show'])->name('atestado.show');
Route::get('/atestado/{token}/baixar', [AtestadoController::class, 'baixar'])->name('atestado.baixar');
Route::post('/atestado/{token}/cancelar', [AtestadoController::class, 'cancelar'])->middleware('throttle:10,1')->name('atestado.cancelar');
