@extends('layouts.app')
@section('titulo', 'Declaração vacinal')

@section('conteudo')
    <h1>Declaração vacinal</h1>
    <p class="sub">Acompanhe o pedido de <strong>{{ $filho->nome }}</strong>.</p>

    <section class="cartao">
        <span class="selo {{ $filho->status === 'aguardando' ? '' : $filho->status }}">{{ $filho->statusRotulo() }}</span>
        <div class="protocolo" style="margin-top:8px">{{ $filho->protocolo }}</div>
        <dl class="dados" style="margin-top:12px">
            <div><dt>Filho(a)</dt><dd>{{ $filho->nome }}</dd></div>
            <div><dt>Série</dt><dd>{{ $filho->serie }}</dd></div>
            <div><dt>Responsável</dt><dd>{{ $filho->pedido->responsavel_nome }} — CPF {{ $filho->pedido->cpfMascarado() }}</dd></div>
            <div><dt>Unidade de saúde</dt><dd>{{ $filho->pedido->unidade_nome }}</dd></div>
        </dl>
    </section>

    @if ($filho->disponivel())
        <a class="btn limao" href="{{ route('atestado.baixar', $filho->token) }}" download>⬇ Baixar declaração vacinal (PDF)</a>
        <button type="button" class="btn contorno oculto" id="compartilhar" style="margin-top:12px">Compartilhar</button>
        <p class="dica" style="text-align:center;margin-top:10px">Depois de baixar, você pode abrir, imprimir ou compartilhar o arquivo como preferir.</p>
    @else
        <div class="alerta info">A unidade de saúde ainda não emitiu a declaração. Você receberá um e-mail assim que ela estiver pronta — e pode voltar a esta página quando quiser.</div>
        <a class="btn contorno" href="{{ url()->current() }}">Atualizar</a>
    @endif

    <p style="text-align:center;margin-top:20px"><a class="link" href="{{ route('consulta.form') }}">Consultar outro pedido</a></p>
@endsection

@if ($filho->disponivel())
@push('scripts')
<script>
(function () {
  var btn = document.getElementById('compartilhar');
  if (!btn || !navigator.share || !window.File) return;
  btn.classList.remove('oculto');
  btn.addEventListener('click', function () {
    btn.disabled = true;
    fetch(@json(route('atestado.baixar', $filho->token)), { credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error(); return r.blob(); })
      .then(function (b) {
        var f = new File([b], 'declaracao-vacinal.pdf', { type: 'application/pdf' });
        if (navigator.canShare && navigator.canShare({ files: [f] })) return navigator.share({ files: [f], title: 'Declaração vacinal' });
        return navigator.share({ title: 'Declaração vacinal', url: location.href });
      })
      .catch(function () {})
      .then(function () { btn.disabled = false; });
  });
})();
</script>
@endpush
@endif
