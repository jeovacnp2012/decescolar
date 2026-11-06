<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipos de interessado (filhos, responsável legal, próprio, empresa): tipo, CNPJ (empresa) e endereço no pedido;
     * CPF e unidade por item (funcionário) e unidade/CPF opcionais no pedido.
     */
    public function up(): void
    {
        Schema::table('atestado_pedidos', function (Blueprint $table) {
            $table->string('tipo', 20)->default('filhos')->after('uuid');
            $table->string('cnpj', 14)->nullable()->index();
            $table->string('logradouro')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('complemento', 100)->nullable();
            $table->string('bairro', 100)->nullable();
            $table->string('cidade', 100)->nullable();
            $table->string('uf', 2)->nullable();
            $table->text('cpf')->nullable()->change();
            $table->string('cpf_hash', 64)->nullable()->change();
            $table->unsignedBigInteger('unidade_id')->nullable()->change();
            $table->string('unidade_nome')->nullable()->change();
        });

        Schema::table('atestado_filhos', function (Blueprint $table) {
            $table->text('cpf')->nullable();                       // cifrado (funcionário / próprio)
            $table->unsignedBigInteger('unidade_id')->nullable()->index();
            $table->string('unidade_nome')->nullable();
            $table->string('serie', 100)->nullable()->change();
            $table->string('nome_mae')->nullable()->change();
        });

        // Pedidos já existentes: cada filho herda a unidade do pedido.
        DB::table('atestado_pedidos')->select('id', 'unidade_id', 'unidade_nome')->orderBy('id')->each(function ($p) {
            DB::table('atestado_filhos')->where('atestado_pedido_id', $p->id)
                ->update(['unidade_id' => $p->unidade_id, 'unidade_nome' => $p->unidade_nome]);
        });
    }

    public function down(): void
    {
        Schema::table('atestado_filhos', function (Blueprint $table) {
            $table->dropColumn(['cpf', 'unidade_id', 'unidade_nome']);
        });
        Schema::table('atestado_pedidos', function (Blueprint $table) {
            $table->dropColumn(['tipo', 'cnpj', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'uf']);
        });
    }
};
