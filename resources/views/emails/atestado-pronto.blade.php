<!DOCTYPE html>
<html lang="pt-BR">
<body style="margin:0;background:#f3f6f4;font-family:Montserrat,Arial,sans-serif;color:#1f2a27">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:16px">
  <table role="presentation" width="100%" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden" cellpadding="0" cellspacing="0">
    <tr><td style="background:#006440;border-bottom:4px solid #b3d353;padding:14px 20px;color:#ffffff;font-weight:700;font-size:16px">
      PREFEITURA DE NOVA MUTUM
    </td></tr>
    <tr><td style="padding:24px 20px">
      <h1 style="margin:0 0 12px;font-size:20px;color:#004236">Sua declaração vacinal está pronta</h1>
      <p style="margin:0 0 12px">Olá, {{ $filho->pedido->responsavel_nome }}.</p>
      <p style="margin:0 0 12px">A unidade de saúde <strong>{{ $filho->pedido->unidade_nome }}</strong> emitiu a declaração vacinal de <strong>{{ $filho->nome }}</strong> (protocolo <strong>{{ $filho->protocolo }}</strong>).</p>
      @if ($anexado)
        <p style="margin:0 0 12px">O arquivo PDF está anexado a este e-mail. Você também pode baixá-lo pelo botão abaixo.</p>
      @else
        <p style="margin:0 0 12px">Use o botão abaixo para baixar o PDF.</p>
      @endif
      <p style="margin:20px 0"><a href="{{ $link }}" style="background:#006440;color:#ffffff;text-decoration:none;padding:14px 22px;border-radius:10px;font-weight:700;display:inline-block">Baixar declaração vacinal</a></p>
      <p style="margin:0;font-size:12px;color:#6b6e6e">Se o botão não funcionar, copie e cole este endereço no navegador:<br>{{ $link }}</p>
    </td></tr>
    <tr><td style="background:#f3f6f4;padding:12px 20px;font-size:12px;color:#6b6e6e">Mensagem automática. Não responda este e-mail.</td></tr>
  </table>
</td></tr></table>
</body>
</html>
