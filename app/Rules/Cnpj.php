<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** CNPJ numérico ou alfanumérico (módulo 11; cada caractere vale ord(c) - 48). */
class Cnpj implements ValidationRule
{
    public static function normalizar(string $valor): string
    {
        return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $valor));
    }

    public static function valido(string $valor): bool
    {
        $c = self::normalizar($valor);
        if (! preg_match('/^[0-9A-Z]{12}[0-9]{2}$/', $c) || preg_match('/^(.)\1{13}$/', $c)) {
            return false;
        }
        $v = array_map(fn ($ch) => ord($ch) - 48, str_split($c));
        foreach ([12, 13] as $pos) {
            $pesos = $pos === 12 ? [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2] : [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
            $soma = 0;
            foreach ($pesos as $i => $p) {
                $soma += $v[$i] * $p;
            }
            $dv = $soma % 11 < 2 ? 0 : 11 - ($soma % 11);
            if ($v[$pos] !== $dv) {
                return false;
            }
        }

        return true;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::valido((string) $value)) {
            $fail('Informe um CNPJ válido.');
        }
    }
}
