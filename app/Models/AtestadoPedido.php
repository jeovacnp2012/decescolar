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

    public const FILHOS = 'filhos';
    public const RESPONSAVEL_LEGAL = 'responsavel_legal';
    public const PROPRIO = 'proprio';
    public const EMPRESA = 'empresa';

    /** Tipos de interessado (valor enviado ao chamados em guardian.type). */
    public const TIPOS = [
        self::FILHOS => 'Para filhos',
        self::RESPONSAVEL_LEGAL => 'Responsável legal',
        self::PROPRIO => 'Meu atestado',
        self::EMPRESA => 'Empresa/indústria',
    ];

    /** Vínculo (só no tipo "filhos"); os demais valores existem apenas em pedidos antigos. */
    public const VINCULOS = [
        'pai' => 'Pai',
        'mae' => 'Mãe',
        'responsavel_legal' => 'Responsável legal',
        'outro' => 'Outro',
    ];

    /** Idade mínima de quem pede: pai/mãe e "meu atestado" a partir de 15 anos; responsável legal a partir de 18. */
    public static function idadeMinima(?string $tipo, ?string $vinculo = null): int
    {
        return match ($tipo) {
            self::RESPONSAVEL_LEGAL => 18,
            self::PROPRIO => 15,
            default => in_array($vinculo, ['pai', 'mae'], true) ? 15 : 18,
        };
    }

    public function ehEmpresa(): bool
    {
        return $this->tipo === self::EMPRESA;
    }

    public function filhos(): HasMany
    {
        return $this->hasMany(AtestadoFilho::class);
    }

    public static function hashCpf(string $cpf): string
    {
        return hash_hmac('sha256', preg_replace('/\D/', '', $cpf), config('app.key'));
    }

    /** CPF mascarado para exibição: ***.456.789-** (empresa: CNPJ formatado). */
    public function cpfMascarado(): string
    {
        if ($this->ehEmpresa()) {
            return self::formatarCnpj((string) $this->cnpj);
        }
        $d = preg_replace('/\D/', '', (string) $this->cpf);

        return '***.'.substr($d, 3, 3).'.'.substr($d, 6, 3).'-**';
    }

    /** "CPF ***.456.789-**" ou "CNPJ 12.345.678/0001-95". */
    public function documentoRotulo(): string
    {
        return ($this->ehEmpresa() ? 'CNPJ ' : 'CPF ').$this->cpfMascarado();
    }

    /** Quem pede, em poucas palavras: "Mãe", "Responsável legal (tio)", "Próprio", "Empresa". */
    public function solicitanteRotulo(): string
    {
        return match ($this->tipo) {
            self::EMPRESA => 'Empresa',
            self::PROPRIO => 'Próprio',
            self::RESPONSAVEL_LEGAL => 'Responsável legal'.($this->parentesco ? ' ('.$this->parentesco.')' : ''),
            default => (self::VINCULOS[$this->vinculo] ?? 'Familiar').($this->vinculo === 'outro' && $this->parentesco ? ' ('.$this->parentesco.')' : ''),
        };
    }

    public static function formatarCnpj(string $c): string
    {
        $c = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $c));

        return strlen($c) === 14
            ? substr($c, 0, 2).'.'.substr($c, 2, 3).'.'.substr($c, 5, 3).'/'.substr($c, 8, 4).'-'.substr($c, 12, 2)
            : $c;
    }

    /** Endereço em uma linha: "Rua X, 123, compl. — Bairro — Cidade/UF — CEP". */
    public function enderecoLinha(): string
    {
        $rua = trim(($this->logradouro ?? '').($this->numero ? ', '.$this->numero : '').($this->complemento ? ', '.$this->complemento : ''), ', ');

        return collect([$rua, $this->bairro, trim(($this->cidade ?? '').($this->uf ? '/'.$this->uf : ''), '/'), $this->cep ? 'CEP '.$this->cep : null])
            ->filter()->implode(' — ');
    }
}
