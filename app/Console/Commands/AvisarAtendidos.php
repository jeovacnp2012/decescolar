<?php

namespace App\Console\Commands;

use App\Models\AtestadoFilho;
use App\Services\ChamadosClient;
use Illuminate\Console\Command;
use Throwable;

/** Reenvia ao chamados o aviso "atendido" dos PDFs já baixados cujo aviso ainda não foi confirmado. */
class AvisarAtendidos extends Command
{
    protected $signature = 'atestados:avisar-atendidos';

    protected $description = 'Avisa o chamados dos atestados já baixados pelos responsáveis (pendentes de aviso)';

    public function handle(ChamadosClient $chamados): int
    {
        $avisados = 0;

        AtestadoFilho::with('pedido')
            ->where('status', AtestadoFilho::BAIXADO)
            ->whereNull('atendido_avisado_em')
            ->whereNotNull('protocolo')
            ->orderBy('id')
            ->chunkById(50, function ($filhos) use ($chamados, &$avisados) {
                foreach ($filhos as $filho) {
                    try {
                        if ($chamados->avisarAtendido($filho)) {
                            $filho->update(['atendido_avisado_em' => now()]);
                            $avisados++;
                        }
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            });

        $this->info("Avisos de atendimento enviados: {$avisados}");

        return self::SUCCESS;
    }
}
