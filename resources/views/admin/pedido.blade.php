@extends('layouts.admin')
@section('titulo', 'Pedido '.$filho->protocolo)

@php
    use App\Models\AtestadoPedido;
    $p = $filho->pedido;
    $fmtCpf = function ($v) {
        $c = preg_replace('/\D/', '', (string) $v);
        return strlen($c) === 11 ? substr($c, 0, 3).'.'.substr($c, 3, 3).'.'.substr($c, 6, 3).'-'.substr($c, 9, 2) : $c;
    };
    $nascResp = $p->data_nascimento_responsavel ? \Carbon\Carbon::parse($p->data_nascimento_responsavel) : null;
@endphp

@section('conteudo')
    <p><a class="link" href="{{ route('admin.pedidos') }}">← Voltar à lista</a></p>
    <h1>{{ $filho->protocolo ?? 'Pedido sem protocolo' }}</h1>
    <p class="sub"><span class="selo {{ $filho->status === 'aguardando' ? '' : $filho->status }}">{{ $filho->statusRotulo() }}</span> &nbsp; {{ AtestadoPedido::TIPOS[$p->tipo] ?? '' }}</p>

    <section class="cartao">
        <h2>{{ $p->ehEmpresa() ? 'Funcionário' : ($p->tipo === AtestadoPedido::PROPRIO ? 'Beneficiário' : 'Filho(a)') }}</h2>
        <dl class="dados">
            <div><dt>Nome</dt><dd>{{ $filho->nome }}</dd></div>
            @if ($filho->serie)<div><dt>Série</dt><dd>{{ $filho->serie }}</dd></div>@endif
            <div><dt>Data de nascimento</dt><dd>{{ $filho->data_nascimento->format('d/m/Y') }}</dd></div>
            @if ($filho->cpf)<div><dt>CPF</dt><dd>{{ $fmtCpf($filho->cpf) }}</dd></div>@endif
            @if ($filho->nome_mae)<div><dt>Nome da mãe</dt><dd>{{ $filho->nome_mae }}</dd></div>@endif
        </dl>
    </section>

    <section class="cartao">
        <h2>{{ $p->ehEmpresa() ? 'Empresa' : 'Responsável' }}</h2>
        <dl class="dados">
            <div><dt>{{ $p->ehEmpresa() ? 'Nome da empresa' : 'Nome' }}</dt><dd>{{ $p->responsavel_nome }}</dd></div>
            @if (! $p->ehEmpresa() && $p->tipo !== AtestadoPedido::PROPRIO)
                <div><dt>Vínculo</dt><dd>{{ $p->solicitanteRotulo() }}</dd></div>
            @endif
            @if ($p->ehEmpresa())
                <div><dt>CNPJ</dt><dd>{{ AtestadoPedido::formatarCnpj($p->cnpj) }}</dd></div>
            @else
                <div><dt>Data de nascimento</dt><dd>{{ $nascResp ? $nascResp->format('d/m/Y').' ('.$nascResp->age.' anos)' : '—' }}</dd></div>
                <div><dt>CPF</dt><dd>{{ $fmtCpf($p->cpf) }}</dd></div>
            @endif
            <div><dt>Telefone</dt><dd>{{ $p->telefone }}</dd></div>
            <div><dt>E-mail</dt><dd>{{ $p->email }}</dd></div>
            <div><dt>Endereço</dt><dd>{{ $p->enderecoLinha() ?: '—' }}</dd></div>
        </dl>
    </section>

    <section class="cartao">
        <h2>Atendimento</h2>
        <dl class="dados">
            <div><dt>Unidade de saúde</dt><dd>{{ $filho->unidade_nome ?? $p->unidade_nome }}</dd></div>
            <div><dt>ACS</dt><dd>{{ $p->acs_nome ?: '—' }}</dd></div>
            <div><dt>Pedido feito em</dt><dd>{{ $filho->created_at->format('d/m/Y H:i') }}</dd></div>
            <div><dt>Declaração pronta em</dt><dd>{{ $filho->pronto_em?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            <div><dt>Baixada pelo responsável em</dt><dd>{{ $filho->baixado_em?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            @if ($filho->status === 'cancelado')
                <div><dt>Cancelado em</dt><dd>{{ $filho->cancelado_em?->format('d/m/Y H:i') }} — por {{ $filho->cancelado_por === 'unidade' ? 'a unidade de saúde' : 'o responsável' }}</dd></div>
                @if ($filho->cancelado_motivo)<div><dt>Motivo informado</dt><dd>{{ $filho->cancelado_motivo }}</dd></div>@endif
            @endif
        </dl>
    </section>

    @if ($irmaos->isNotEmpty())
        <section class="cartao">
            <h2>{{ $p->ehEmpresa() ? 'Outros funcionários do mesmo pedido' : 'Outros do mesmo pedido' }}</h2>
            @foreach ($irmaos as $i)
                <p style="margin:0 0 8px"><a class="link" href="{{ route('admin.pedido', $i) }}">{{ $i->protocolo }} — {{ $i->nome }}</a> <span class="selo {{ $i->status === 'aguardando' ? '' : $i->status }}">{{ $i->statusRotulo() }}</span></p>
            @endforeach
        </section>
    @endif
@endsection
