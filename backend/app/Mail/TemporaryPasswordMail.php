<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
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
            subject: 'autopass temporary password',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.temporary-password',
        );
    }
}
