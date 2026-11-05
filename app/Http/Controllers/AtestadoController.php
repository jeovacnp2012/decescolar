<?php

namespace App\Http\Controllers;

use App\Models\AtestadoFilho;
use App\Services\ChamadosClient;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Página de acompanhamento/download do atestado (link com token longo e aleatório, ou consulta por CPF + protocolo). */
class AtestadoController extends Controller
{
    public function show(string $token)
    {
        $filho = AtestadoFilho::with('pedido')->where('token', $token)->firstOrFail();

        return view('atestado.show', ['filho' => $filho]);
    }

    /** O responsável cancela/exclui o pedido: apaga o PDF guardado e avisa a unidade de saúde (chamados). */
    public function cancelar(string $token, ChamadosClient $chamados)
    {
        $filho = AtestadoFilho::with('pedido')->where('token', $token)->firstOrFail();

        if ($filho->status === AtestadoFilho::CANCELADO) {
            return redirect()->route('atestado.show', $token);
        }

        if (! $filho->podeCancelar()) {
            return redirect()->route('atestado.show', $token)
                ->with('aviso', 'Este pedido já foi atendido pela unidade de saúde e não pode mais ser cancelado.');
        }

        if ($filho->arquivo) {
            Storage::disk('local')->delete($filho->arquivo);
        }
        $filho->update(['status' => AtestadoFilho::CANCELADO, 'cancelado_em' => now(), 'cancelado_por' => 'responsavel', 'arquivo' => null]);

        // Avisa a unidade. Se o chamados estiver fora do ar, o comando atestados:avisar-atendidos repete depois.
        try {
            if ($filho->protocolo && $chamados->avisarCancelamento($filho)) {
                $filho->update(['cancelamento_avisado_em' => now()]);
            }
        } catch (Throwable $e) {
            report($e);
        }

        return redirect()->route('atestado.show', $token)->with('aviso', $filho->cancelamento_avisado_em
            ? 'Pedido cancelado. A unidade de saúde foi informada.'
            : 'Pedido cancelado. A unidade de saúde será informada em instantes.');
    }

    public function baixar(string $token, ChamadosClient $chamados)
    {
        $filho = AtestadoFilho::with('pedido')->where('token', $token)->firstOrFail();
        abort_unless($filho->disponivel() && Storage::disk('local')->exists($filho->arquivo), 404);

        if ($filho->status === AtestadoFilho::PRONTO) {
            $filho->update(['status' => AtestadoFilho::BAIXADO, 'baixado_em' => now()]);
        }

        // Avisa o chamados (Atendido). Se falhar, o comando atestados:avisar-atendidos repete depois.
        if (! $filho->atendido_avisado_em) {
            try {
                if ($chamados->avisarAtendido($filho)) {
                    $filho->update(['atendido_avisado_em' => now()]);
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return Storage::disk('local')->download(
            $filho->arquivo,
            'declaracao-vacinal-'.$filho->protocolo.'.pdf',
            ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store'],
        );
    }
}
