<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Cancelamento pelo responsável e quando o chamados (unidade de saúde) foi avisado. */
    public function up(): void
    {
        Schema::table('atestado_filhos', function (Blueprint $table) {
            $table->timestamp('cancelado_em')->nullable()->after('baixado_em');
            $table->timestamp('cancelamento_avisado_em')->nullable()->after('cancelado_em');
        });
    }

    public function down(): void
    {
        Schema::table('atestado_filhos', function (Blueprint $table) {
            $table->dropColumn(['cancelado_em', 'cancelamento_avisado_em']);
        });
    }
};
