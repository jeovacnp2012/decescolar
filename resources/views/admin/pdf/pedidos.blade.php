<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Pedidos de declaração vacinal</title>
<style>
    @page { margin: 28px 28px 40px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #1f2a27; }
    .cab { border-bottom: 3px solid #b3d353; padding-bottom: 8px; margin-bottom: 10px; }
    .cab table { width: 100%; border-collapse: collapse; }
    .cab td { vertical-align: middle; border: 0; padding: 0; }
    h1 { font-size: 14px; color: #004236; margin: 0 0 2px; }
    .meta { color: #555; font-size: 8px; }
    table.lista { width: 100%; border-collapse: collapse; }
    table.lista th { background: #006440; color: #fff; text-align: left; padding: 5px 4px; font-size: 8px; }
    table.lista td { border-bottom: 1px solid #d5dfda; padding: 4px; vertical-align: top; }
    table.lista tr:nth-child(even) td { background: #f3f6f4; }
    small { color: #666; font-size: 7.5px; }
    .aviso { margin-top: 8px; padding: 6px; background: #fdf3d6; border: 1px solid #e3c969; font-size: 8px; }
    .lgpd { margin-top: 10px; color: #777; font-size: 7.5px; }
</style>
</head>
<body>
<script type="text/php">
    if (isset($pdf)) {
        $fonte = $fontMetrics->getFont('DejaVu Sans', 'normal');
        $pdf->page_text($pdf->get_width() - 140, $pdf->get_height() - 26, 'Página {PAGE_NUM} de {PAGE_COUNT}', $fonte, 8, [0.4, 0.4, 0.4]);
    }
</script>

<div class="cab">
    <table>
        <tr>
            <td style="width:46px"><img src="{{ public_path('img/brasao.png') }}" style="height:40px" alt=""></td>
            <td>
                <h1>Pedidos de declaração vacinal</h1>
                <div class="meta">Prefeitura Municipal de Nova Mutum — MT</div>
            </td>
            <td style="text-align:right" class="meta">
                Gerado em {{ $geradoEm->format('d/m/Y H:i') }}<br>por {{ $geradoPor }}
            </td>
        </tr>
    </table>
</div>

<p class="meta"><strong>Filtros:</strong> {{ $filtros }}<br><strong>Total:</strong> {{ $total }} {{ $total === 1 ? 'registro' : 'registros' }}</p>

<table class="lista">
    <thead>
        <tr>
            <th style="width:62px">Protocolo</th>
            <th>Filho(a)</th>
            <th>Responsável</th>
            <th>Unidade de saúde</th>
            <th style="width:76px">Situação</th>
            <th style="width:64px">Pedido em</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($filhos as $f)
            <tr>
                <td><strong>{{ $f->protocolo ?? '—' }}</strong></td>
                <td>{{ $f->nome }}<br><small>{{ $f->serie }} · nasc. {{ $f->data_nascimento->format('d/m/Y') }}</small></td>
                <td>{{ $f->pedido->responsavel_nome }}<br><small>{{ \App\Models\AtestadoPedido::VINCULOS[$f->pedido->vinculo] ?? '' }}{{ $f->pedido->parentesco ? ' ('.$f->pedido->parentesco.')' : '' }} · CPF {{ $f->pedido->cpfMascarado() }}</small></td>
                <td>{{ $f->pedido->unidade_nome }}{{ $f->pedido->acs_nome ? ' — ACS: '.$f->pedido->acs_nome : '' }}</td>
                <td>{{ $f->statusRotulo() }}</td>
                <td>{{ $f->created_at->format('d/m/Y') }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;padding:14px">Nenhum pedido encontrado com esses filtros.</td></tr>
        @endforelse
    </tbody>
</table>

@if ($cortado)
    <div class="aviso">Este relatório mostra os {{ $limite }} pedidos mais recentes de {{ $total }}. Use os filtros (período, unidade ou situação) para gerar o restante.</div>
@endif
<div class="lgpd">Documento com dados pessoais protegidos pela LGPD (CPF parcialmente ocultado). Uso interno; não compartilhe.</div>
</body>
</html>
