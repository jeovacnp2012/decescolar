<?php

namespace App\Http\Controllers;

/** Antes do formulário: a pessoa precisa estar vinculada a uma unidade de saúde e saber o nome dela. */
class TriagemController extends Controller
{
    public function vinculada()
    {
        return view('triagem.pergunta', [
            'titulo' => 'Está devidamente vinculado a uma unidade de saúde?',
            'dica' => 'Ser vinculado é ter a sua família cadastrada em uma unidade básica de saúde (posto, ESF ou PSF).',
            'sim' => route('triagem.nome'),
        ]);
    }

    public function nome()
    {
        return view('triagem.pergunta', [
            'titulo' => 'Sabe o nome de sua unidade básica de saúde?',
            'dica' => 'Você vai escolher a unidade na lista do formulário.',
            'sim' => route('pedido.formulario'),
        ]);
    }

    public function procure()
    {
        return view('triagem.procure');
    }
}
