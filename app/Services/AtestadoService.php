<?php

namespace App\Services;

use App\Mail\AtestadoProntoMail;
use App\Models\AtestadoFilho;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class AtestadoService
{
    /**
     * Guarda o PDF recebido do chamados e marca o atestado como pronto. Reenvio do mesmo protocolo substitui o
     * arquivo (idempotente). O e-mail ao responsável sai do chamados; aqui só se envia se DECESCOLAR_SEND_EMAIL=true.
     */
    public function receberPdf(AtestadoFilho $filho, string $pdf): AtestadoFilho
    {
        if (! str_starts_with($pdf, '%PDF')) {
            throw new RuntimeException('O arquivo recebido não é um PDF válido.');
        }

        $caminho = 'atestados/'.now()->format('Y/m').'/'.Str::uuid().'.pdf';
        Storage::disk('local')->put($caminho, $pdf);
        if ($filho->arquivo) {
            Storage::disk('local')->delete($filho->arquivo);
        }

        $filho->update([
            'arquivo' => $caminho,
            'status' => $filho->status === AtestadoFilho::BAIXADO ? AtestadoFilho::BAIXADO : AtestadoFilho::PRONTO,
            'pronto_em' => now(),
        ]);

        if (config('decescolar.send_email')) {
            $this->avisarPorEmail($filho->refresh());
        }

        return $filho->refresh();
    }

    private function avisarPorEmail(AtestadoFilho $filho): void
    {
        try {
            Mail::to($filho->pedido->email)->send(new AtestadoProntoMail($filho));
            $filho->update(['email_enviado_em' => now()]);
        } catch (Throwable $e) {
            report($e); // o atestado continua disponível pelo link/consulta
        }
    }
}
