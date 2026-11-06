<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AtestadoPedido extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['cpf' => 'encrypted', 'telefone' => 'encrypted', 'data_nascimento_responsavel' => 'encrypted'];
    }

    public const VINCULOS = [
        'pai' => 'Pai',
        'mae' => 'Mãe',
        'responsavel_legal' => 'Responsável legal',
        'outro' => 'Outro',
    ];

    /** Idade mínima para pedir: pai/mãe a partir de 15 anos; responsável legal e outros a partir de 18. */
    public static function idadeMinima(?string $vinculo): int
    {
        return in_array($vinculo, ['pai', 'mae'], true) ? 15 : 18;
    }

    public function filhos(): HasMany
    {
        return $this->hasMany(AtestadoFilho::class);
    }

    public static function hashCpf(string $cpf): string
    {
        return hash_hmac('sha256', preg_replace('/\D/', '', $cpf), config('app.key'));
    }

    /** CPF mascarado para exibição: ***.456.789-** */
    public function cpfMascarado(): string
    {
        $d = preg_replace('/\D/', '', $this->cpf);

        return '***.'.substr($d, 3, 3).'.'.substr($d, 6, 3).'-**';
    }
}
