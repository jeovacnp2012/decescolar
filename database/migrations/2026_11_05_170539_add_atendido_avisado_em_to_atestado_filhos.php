<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Quando o decescolar avisou o chamados que o responsável recebeu o PDF (POST .../served). */
    public function up(): void
    {
        Schema::table('atestado_filhos', function (Blueprint $table) {
            $table->timestamp('atendido_avisado_em')->nullable()->after('baixado_em');
        });
    }

    public function down(): void
    {
        Schema::table('atestado_filhos', function (Blueprint $table) {
            $table->dropColumn('atendido_avisado_em');
        });
    }
};
