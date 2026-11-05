<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AtestadoFilho;
use App\Services\AtestadoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AtestadoProntoController extends Controller
{
    /**
     * POST /api/v1/atestados/{protocolo}/pronto — o chamados entrega o PDF emitido pela unidade de saúde.
     * multipart/form-data: certificate (PDF, até 10 MB), protocol, child_name, guardian_cpf, unit, sent_at.
     * Qualquer 2xx = entregue. O mesmo protocolo pode chegar de novo: substitui o PDF.
     */
    public function store(Request $request, string $protocolo, AtestadoService $servico): JsonResponse
    {
        $request->validate(['certificate' => ['required', 'file', 'mimes:pdf', 'max:10240']]);

        $filho = AtestadoFilho::with('pedido')->where('protocolo', strtoupper($protocolo))->first();
        if (! $filho) {
            return response()->json(['message' => 'Protocolo não encontrado.'], 404);
        }

        if ($filho->status === AtestadoFilho::CANCELADO) {
            return response()->json(['message' => 'Pedido cancelado pelo responsável; o atestado não será recebido.'], 409);
        }

        try {
            $filho = $servico->receberPdf($filho, file_get_contents($request->file('certificate')->getRealPath()));
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Não foi possível registrar o atestado: '.$e->getMessage()], 422);
        }

        return response()->json(['data' => $this->apresentar($filho)]);
    }

    /**
     * POST /api/v1/atestados/{protocolo}/cancelado — a unidade de saúde excluiu o pedido no chamados.
     * JSON: protocol, external_id, status="canceled", message, reason, child_name, guardian_cpf, unit, canceled_at.
     * O chamados só exclui se receber 2xx; por isso é idempotente e responde 2xx também para protocolo desconhecido ou já cancelado.
     */
    public function cancelado(Request $request, string $protocolo): JsonResponse
    {
        $filho = AtestadoFilho::where('protocolo', strtoupper($protocolo))->first();
        if (! $filho) {
            return response()->json(['data' => ['protocolo' => strtoupper($protocolo), 'status' => 'desconhecido']]);
        }

        if ($filho->status !== AtestadoFilho::CANCELADO) {
            if ($filho->arquivo) {
                Storage::disk('local')->delete($filho->arquivo);
            }
            $filho->update([
                'status' => AtestadoFilho::CANCELADO,
                'cancelado_em' => $request->date('canceled_at') ?? now(),
                'cancelado_por' => 'unidade',
                'cancelado_motivo' => $request->filled('reason') ? mb_substr((string) $request->input('reason'), 0, 1000) : null,
                'cancelamento_avisado_em' => now(), // o chamados já sabe: foi ele quem excluiu
                'arquivo' => null,
            ]);
        }

        return response()->json(['data' => $this->apresentar($filho->fresh())]);
    }

    /** GET /api/v1/atestados/{protocolo} — situação do atestado no decescolar. */
    public function show(string $protocolo): JsonResponse
    {
        $filho = AtestadoFilho::where('protocolo', strtoupper($protocolo))->first();

        return $filho
            ? response()->json(['data' => $this->apresentar($filho)])
            : response()->json(['message' => 'Protocolo não encontrado.'], 404);
    }

    private function apresentar(AtestadoFilho $f): array
    {
        return [
            'protocolo' => $f->protocolo,
            'status' => $f->status,
            'pronto_em' => $f->pronto_em?->toIso8601String(),
            'baixado_em' => $f->baixado_em?->toIso8601String(),
            'cancelado_em' => $f->cancelado_em?->toIso8601String(),
        ];
    }
}
