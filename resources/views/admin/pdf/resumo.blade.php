<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Resumo por unidade de saúde</title>
<style>
    @page { margin: 30px 34px 44px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2a27; }
    .cab { border-bottom: 3px solid #b3d353; padding-bottom: 8px; margin-bottom: 12px; }
    .cab table { width: 100%; border-collapse: collapse; }
    .cab td { vertical-align: middle; border: 0; padding: 0; }
    h1 { font-size: 16px; color: #004236; margin: 0 0 2px; }
    .meta { color: #555; font-size: 9px; }
    table.res { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.res th { background: #006440; color: #fff; padding: 6px 5px; font-size: 9px; text-align: center; }
    table.res th:first-child, table.res td:first-child { text-align: left; }
    table.res td { border-bottom: 1px solid #d5dfda; padding: 6px 5px; text-align: center; }
    table.res tr:nth-child(even) td { background: #f3f6f4; }
    table.res tr.total td { background: #e4f0e9; font-weight: bold; border-top: 2px solid #006440; }
    .lgpd { margin-top: 14px; color: #777; font-size: 8px; }
</style>
</head>
<body>
<script type="text/php">
    if (isset($pdf)) {
        $fonte = $fontMetrics->getFont('DejaVu Sans', 'normal');
        $pdf->page_text($pdf->get_width() - 150, $pdf->get_height() - 28, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fonte, 8, [0.4, 0.4, 0.4]);
    }
</script>

<div class="cab">
    <table>
        <tr>
            <td style="width:52px"><img src="{{ public_path('img/brasao.png') }}" style="height:44px" alt=""></td>
            <td>
                <h1>Resumo por unidade de saúde</h1>
                <div class="meta">Declaração vacinal — Prefeitura Municipal de Nova Mutum — MT</div>
            </td>
            <td style="text-align:right" class="meta">
                Gerado em {{ $geradoEm->format('d/m/Y H:i') }}<br>por {{ $geradoPor }}
            </td>
        </tr>
    </table>
</div>

<p class="meta"><strong>Filtros:</strong> {{ $filtros }}</p>

<table class="res">
    <thead>
        <tr>
            <th>Unidade de saúde</th>
            <th>Total</th>
            @foreach (\App\Support\FiltroPedidos::STATUS as $rotulo)
                <th>{{ $rotulo }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($porUnidade as $unidade => $n)
            <tr>
                <td>{{ $unidade }}</td>
                <td><strong>{{ $n['total'] }}</strong></td>
                @foreach (\App\Support\FiltroPedidos::STATUS as $chave => $_)
                    <td>{{ $n[$chave] }}</td>
                @endforeach
            </tr>
        @empty
            <tr><td colspan="{{ 2 + count(\App\Support\FiltroPedidos::STATUS) }}" style="padding:14px">Nenhum pedido encontrado com esses filtros.</td></tr>
        @endforelse
        @if ($porUnidade)
            <tr class="total">
                <td>Total geral</td>
                <td>{{ $totais['total'] }}</td>
                @foreach (\App\Support\FiltroPedidos::STATUS as $chave => $_)
                    <td>{{ $totais[$chave] }}</td>
                @endforeach
            </tr>
        @endif
    </tbody>
</table>

<div class="lgpd">Cada filho (protocolo) é contado uma vez. Relatório de uso interno.</div>
</body>
</html>
