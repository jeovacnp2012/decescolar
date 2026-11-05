<?php

namespace App\Mail;

use App\Models\AtestadoFilho;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AtestadoProntoMail extends Mailable
{
    public function __construct(public AtestadoFilho $filho)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Declaração vacinal pronta — '.$this->filho->nome);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.atestado-pronto', with: [
            'link' => route('atestado.show', $this->filho->token),
            'anexado' => config('decescolar.attach_pdf_in_email') && $this->filho->arquivo,
        ]);
    }

    public function attachments(): array
    {
        if (! config('decescolar.attach_pdf_in_email') || ! $this->filho->arquivo) {
            return [];
        }

        return [Attachment::fromStorageDisk('local', $this->filho->arquivo)
            ->as('declaracao-vacinal-'.$this->filho->protocolo.'.pdf')->withMime('application/pdf')];
    }
}
