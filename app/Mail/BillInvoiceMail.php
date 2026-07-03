<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BillInvoiceMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly array $invoiceData,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('admin@pacifik.com', 'Administrador Pacifik'),
            replyTo: [
                new Address('admin@pacifik.com', 'Administrador Pacifik'),
            ],
            subject: 'Recibo de Cuotas - '.
                $this->invoiceData['monthLabel'].' '.
                $this->invoiceData['year'].
                ' - Unidad '.$this->invoiceData['departament']->number,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mails.bills.invoice',
            with: $this->invoiceData,
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
