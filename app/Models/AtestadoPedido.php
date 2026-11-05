<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AtestadoPedido extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['cpf' => 'encrypted', 'telefone' => 'encrypted'];
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
