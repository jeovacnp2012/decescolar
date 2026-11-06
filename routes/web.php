<?php

use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\PedidoAdminController;
use App\Http\Controllers\Admin\RelatorioController;
use App\Http\Controllers\AtestadoController;
use App\Http\Controllers\CepController;
use App\Http\Controllers\ConsultaController;
use App\Http\Controllers\PedidoController;
use App\Http\Controllers\TriagemController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'inicio')->name('inicio');
Route::get('/solicitar', [TriagemController::class, 'vinculada'])->name('pedido.create');
Route::get('/solicitar/nome-da-unidade', [TriagemController::class, 'nome'])->name('triagem.nome');
Route::get('/solicitar/procure-uma-unidade', [TriagemController::class, 'procure'])->name('triagem.procure');
Route::get('/solicitar/formulario', [PedidoController::class, 'create'])->name('pedido.formulario');
Route::get('/cep/{cep}', [CepController::class, 'buscar'])->where('cep', '[0-9-]{8,9}')->middleware('throttle:30,1')->name('cep');
Route::post('/pedido', [PedidoController::class, 'store'])->middleware('throttle:10,1')->name('pedido.store');
Route::get('/pedido/{uuid}', [PedidoController::class, 'show'])->name('pedido.show');

Route::get('/consultar', [ConsultaController::class, 'form'])->name('consulta.form');
Route::post('/consultar', [ConsultaController::class, 'buscar'])->middleware('throttle:10,1')->name('consulta.buscar');

// Área administrativa (acesso por e-mail e senha; não depende do chamados)
Route::prefix('admin')->name('admin.')->middleware('sem.cache')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [LoginController::class, 'form'])->name('login');
        Route::post('/login', [LoginController::class, 'entrar'])->middleware('throttle:20,1')->name('entrar');
    });
    Route::middleware('auth')->group(function () {
        Route::redirect('/', '/admin/pedidos');
        Route::get('/pedidos', [PedidoAdminController::class, 'index'])->name('pedidos');
        Route::get('/pedidos/{filho}', [PedidoAdminController::class, 'show'])->whereNumber('filho')->name('pedido');
        Route::get('/relatorios/pedidos.pdf', [RelatorioController::class, 'pedidos'])->name('relatorio.pedidos');
        Route::get('/relatorios/resumo.pdf', [RelatorioController::class, 'resumo'])->name('relatorio.resumo');
        Route::post('/sair', [LoginController::class, 'sair'])->name('sair');
    });
});

Route::get('/atestado/{token}', [AtestadoController::class, 'show'])->name('atestado.show');
Route::get('/atestado/{token}/baixar', [AtestadoController::class, 'baixar'])->name('atestado.baixar');
Route::post('/atestado/{token}/cancelar', [AtestadoController::class, 'cancelar'])->middleware('throttle:10,1')->name('atestado.cancelar');
