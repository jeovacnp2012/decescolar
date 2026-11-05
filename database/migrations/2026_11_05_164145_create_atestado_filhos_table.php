<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atestado_filhos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('atestado_pedido_id')->constrained('atestado_pedidos')->cascadeOnDelete();
            $table->string('nome');
            $table->string('serie', 100);
            $table->date('data_nascimento');
            $table->string('nome_mae');
            $table->string('protocolo', 20)->nullable()->unique();   // devolvido pelo chamados (ATV000123)
            $table->string('status', 20)->default('aguardando');      // aguardando | pronto | baixado
            $table->string('token', 64)->unique();                     // link de download (sem login)
            $table->string('arquivo')->nullable();                    // PDF guardado em disco privado
            $table->timestamp('pronto_em')->nullable();
            $table->timestamp('baixado_em')->nullable();
            $table->timestamp('email_enviado_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atestado_filhos');
    }
};
