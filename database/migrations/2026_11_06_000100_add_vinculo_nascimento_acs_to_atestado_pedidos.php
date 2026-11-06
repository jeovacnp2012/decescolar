<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vínculo do responsável (pai/mae/responsavel_legal/outro), parentesco quando "outro", data de nascimento do
     * responsável (criptografada) e nome do ACS (opcional). A escola deixa de ser informada (coluna fica opcional
     * só para preservar pedidos antigos).
     */
    public function up(): void
    {
        Schema::table('atestado_pedidos', function (Blueprint $table) {
            $table->string('vinculo', 20)->nullable()->after('responsavel_nome');
            $table->string('parentesco', 60)->nullable()->after('vinculo');
            $table->text('data_nascimento_responsavel')->nullable()->after('parentesco');
            $table->string('acs_nome')->nullable()->after('unidade_nome');
            $table->string('escola')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('atestado_pedidos', function (Blueprint $table) {
            $table->dropColumn(['vinculo', 'parentesco', 'data_nascimento_responsavel', 'acs_nome']);
        });
    }
};
