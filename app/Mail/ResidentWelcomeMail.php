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

class ResidentWelcomeMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $username,
        public readonly string $password,
    ) {}

    public function envelope(): Envelope
    {
        $urbanismoName = $this->user->urbanismo?->nombre ?? 'Easycondo';

        return new Envelope(
            from: new Address(
                (string) config('mail.from.address'),
                (string) config('mail.from.name'),
            ),
            subject: 'Bienvenido a '.$urbanismoName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.onboarding.welcome',
            with: [
                'userName' => $this->user->name,
                'urbanismoName' => $this->user->urbanismo?->nombre ?? 'tu comunidad',
                'username' => $this->username,
                'password' => $this->password,
                'unidad' => $this->user->unidad,
                'loginUrl' => rtrim((string) config('app.frontend_url'), '/').'/login',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
