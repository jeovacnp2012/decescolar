<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Cria (ou troca a senha de) um acesso à área administrativa. A senha é pedida escondida no terminal e só o hash
 * fica no banco: ela nunca é escrita no código, no .env nem no repositório.
 */
class CriarAdmin extends Command
{
    protected $signature = 'admin:criar {email : E-mail de acesso} {--nome=Administrador : Nome exibido} {--password-stdin : Lê a senha da entrada padrão (sem digitar no terminal)}';

    protected $description = 'Cria ou atualiza um acesso à área administrativa (a senha é pedida de forma oculta)';

    public function handle(): int
    {
        $email = Str::lower(trim((string) $this->argument('email')));
        if (Validator::make(['email' => $email], ['email' => 'required|email'])->fails()) {
            $this->error('E-mail inválido.');

            return self::FAILURE;
        }

        if ($this->option('password-stdin')) {
            $senha = rtrim((string) fgets(STDIN), "\r\n");
        } else {
            $senha = (string) $this->secret('Senha de acesso');
            if ($senha !== (string) $this->secret('Repita a senha')) {
                $this->error('As senhas não conferem. Nada foi alterado.');

                return self::FAILURE;
            }
        }

        if (mb_strlen($senha) < 8) {
            $this->error('A senha precisa ter pelo menos 8 caracteres. Nada foi alterado.');

            return self::FAILURE;
        }

        $existia = User::where('email', $email)->exists();
        User::updateOrCreate(['email' => $email], ['name' => (string) $this->option('nome'), 'password' => $senha]); // cast "hashed" grava só o hash

        $this->info($existia ? "Senha de {$email} atualizada." : "Acesso criado para {$email}.");

        return self::SUCCESS;
    }
}
