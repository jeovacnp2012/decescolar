<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    private const TENTATIVAS = 5;
    private const BLOQUEIO_SEGUNDOS = 300;

    public function form()
    {
        return view('admin.login');
    }

    public function entrar(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Informe o e-mail.',
            'email.email' => 'Informe um e-mail válido.',
            'password.required' => 'Informe a senha.',
        ]);

        $email = Str::lower(trim($dados['email']));
        $chave = $email.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($chave, self::TENTATIVAS)) {
            $espera = RateLimiter::availableIn($chave);
            throw ValidationException::withMessages(['email' => 'Muitas tentativas. Tente novamente em '.ceil($espera / 60).' minuto(s).']);
        }

        // Mensagem única para e-mail desconhecido e senha errada (não revela se o e-mail existe).
        if (! Auth::attempt(['email' => $email, 'password' => $dados['password']])) {
            RateLimiter::hit($chave, self::BLOQUEIO_SEGUNDOS);
            throw ValidationException::withMessages(['email' => 'E-mail ou senha incorretos.']);
        }

        RateLimiter::clear($chave);
        $request->session()->regenerate();

        return redirect()->intended(route('admin.pedidos'));
    }

    public function sair(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('inicio');
    }
}
