<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class VerificationCodeMail extends Mailable
{
    public function __construct(
        public string $userName,
        public string $code,
        public int $minutes,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Je verificatiecode voor '.config('app.name'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.verification-code');
    }
}
