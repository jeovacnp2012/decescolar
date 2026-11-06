<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AtestadoFilho extends Model
{
    public const AGUARDANDO = 'aguardando';
    public const PRONTO = 'pronto';
    public const BAIXADO = 'baixado';
    public const CANCELADO = 'cancelado';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data_nascimento' => 'date',
            'pronto_em' => 'datetime',
            'baixado_em' => 'datetime',
            'cancelado_em' => 'datetime',
            'cancelamento_avisado_em' => 'datetime',
            'email_enviado_em' => 'datetime',
            'cpf' => 'encrypted',
        ];
    }

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(AtestadoPedido::class, 'atestado_pedido_id');
    }

    public function statusRotulo(): string
    {
        return match ($this->status) {
            self::PRONTO => 'Pronto para baixar',
            self::BAIXADO => 'Baixado',
            self::CANCELADO => 'Pedido cancelado',
            default => 'Aguardando a unidade de saúde',
        };
    }

    /** O responsável só cancela enquanto a unidade não atendeu (ainda não baixou o PDF). */
    public function podeCancelar(): bool
    {
        return in_array($this->status, [self::AGUARDANDO, self::PRONTO], true);
    }

    public function disponivel(): bool
    {
        return in_array($this->status, [self::PRONTO, self::BAIXADO], true) && $this->arquivo;
    }
}
