<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Quem cancelou (responsavel|unidade) e o motivo informado pela unidade de saúde. */
    public function up(): void
    {
        Schema::table('atestado_filhos', function (Blueprint $table) {
            $table->string('cancelado_por', 20)->nullable()->after('cancelado_em');
            $table->text('cancelado_motivo')->nullable()->after('cancelado_por');
        });
    }

    public function down(): void
    {
        Schema::table('atestado_filhos', function (Blueprint $table) {
            $table->dropColumn(['cancelado_por', 'cancelado_motivo']);
        });
    }
};
