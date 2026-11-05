<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Cpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $c = preg_replace('/\D/', '', (string) $value);
        $ok = strlen($c) === 11 && ! preg_match('/^(\d)\1{10}$/', $c);
        if ($ok) {
            for ($t = 9; $t < 11; $t++) {
                $s = 0;
                for ($i = 0; $i < $t; $i++) {
                    $s += (int) $c[$i] * (($t + 1) - $i);
                }
                $d = ((10 * $s) % 11) % 10;
                if ((int) $c[$t] !== $d) {
                    $ok = false;
                    break;
                }
            }
        }
        if (! $ok) {
            $fail('Informe um CPF válido.');
        }
    }
}
