<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\BillInvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
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
        $quota = $this->invoiceData['quota'] ?? null;
        if (! $quota) {
            return [];
        }

        $service = app(BillInvoiceService::class);
        $receiptData = $service->buildReceiptData($quota);

        $pdf = Pdf::loadView('bills.receipt', $receiptData)
            ->setPaper('letter')
            ->setOption('isRemoteEnabled', true);

        $filename = 'recibo-mantenimiento-'.
            $receiptData['departament']->number.
            '-'.strtolower($receiptData['monthLabel']).
            '-'.$receiptData['year'].'.pdf';

        return [
            $this->attachData($pdf->output(), $filename),
        ];
    }
}
