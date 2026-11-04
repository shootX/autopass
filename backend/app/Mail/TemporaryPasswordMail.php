<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class TemporaryPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $accountName,
        public string $login,
        public string $temporaryPassword,
        public int $hours
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Autopass · დროებითი პაროლი',
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<mailto:noreply@autopass.ge?subject=unsubscribe>',
            'X-Mailer' => 'Autopass',
        ]);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.temporary-password',
        );
    }
}
