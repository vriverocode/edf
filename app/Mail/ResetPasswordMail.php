<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $token,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('test@edificiopacifik.com', 'Administración Pacifik'),
            replyTo: [
                new Address('test@edificiopacifik.com', 'Administración Pacifik'),
            ],
            subject: 'Restablecer contraseña - Pacifik',
        );
    }

    public function content(): Content
    {
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173'));
        $resetUrl = $frontendUrl.'/reset-password?token='.$this->token;

        return new Content(
            view: 'mails.auth.reset-password',
            with: [
                'userName' => $this->user->name,
                'resetUrl' => $resetUrl,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
