<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Um pedido = um envio do responsável (pode ter vários filhos). CPF cifrado + hash para consulta (LGPD). */
    public function up(): void
    {
        Schema::create('atestado_pedidos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();            // id do pedido enviado ao chamados (external_id) e do link de confirmação
            $table->string('responsavel_nome');
            $table->text('cpf');                        // cifrado (cast encrypted)
            $table->string('cpf_hash', 64)->index();    // sha256(cpf + APP_KEY) para consultas sem expor o CPF
            $table->text('telefone');                   // cifrado
            $table->string('cep', 9);
            $table->string('email');
            $table->string('escola');
            $table->unsignedBigInteger('unidade_id');   // id do setor no chamados-multi-tenant
            $table->string('unidade_nome');
            $table->string('envio_status', 20)->default('pendente'); // pendente | enviado | falhou
            $table->text('envio_erro')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atestado_pedidos');
    }
};
