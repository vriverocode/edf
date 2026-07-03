<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\BillInvoiceMail;
use App\Models\MonthlyBills;
use App\Models\Quota;
use App\Services\BillInvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class BillInvoiceController extends Controller
{
    private BillInvoiceService $billInvoiceService;

    public function __construct(BillInvoiceService $billInvoiceService)
    {
        $this->billInvoiceService = $billInvoiceService;
    }

    public function show(Request $request, int $quotaId)
    {
        $quota = Quota::with([
            'departament.owner',
            'waterReading',
            'pays' => function ($query) {
                $query->where('status', 2)
                    ->where('type', 1)
                    ->orderByDesc('pay_date');
            },
        ])->find($quotaId);

        if (! $quota) {
            return $this->returnFail(404, 'Cuota no encontrada');
        }

        if ((int) $quota->status !== 3) {
            return $this->returnFail(403, 'El recibo solo está disponible para cuotas pagadas');
        }

        $invoiceData = $this->billInvoiceService->buildInvoiceData($quota);

        return $this->returnSuccess(200, $invoiceData);
    }

    public function downloadPdf(Request $request, int $quotaId)
    {
        $quota = Quota::with([
            'departament.owner',
            'waterReading',
            'pays' => function ($query) {
                $query->where('status', 2)
                    ->where('type', 1)
                    ->orderByDesc('pay_date');
            },
        ])->find($quotaId);

        if (! $quota) {
            return $this->returnFail(404, 'Cuota no encontrada');
        }

        if ((int) $quota->status !== 3) {
            return $this->returnFail(403, 'El recibo solo está disponible para cuotas pagadas');
        }

        $invoiceData = $this->billInvoiceService->buildInvoiceData($quota);

        $pdf = Pdf::loadView('bills.invoice', $invoiceData)
            ->setPaper('letter')
            ->setOption('isRemoteEnabled', true);

        $filename = 'recibo-' .
            $invoiceData['departament']->number .
            '-' . strtolower($invoiceData['monthLabel']) .
            '-' . $invoiceData['year'] . '.pdf';

        return $pdf->download($filename);
    }

    public function previewPdf(Request $request, int $quotaId)
    {
        $quota = Quota::with([
            'departament.owner',
            'waterReading',
            'pays' => function ($query) {
                $query->where('status', 2)
                    ->where('type', 1)
                    ->orderByDesc('pay_date');
            },
        ])->find($quotaId);

        if (! $quota) {
            return $this->returnFail(404, 'Cuota no encontrada');
        }

        if ((int) $quota->status !== 3) {
            return $this->returnFail(403, 'El recibo solo está disponible para cuotas pagadas');
        }

        $invoiceData = $this->billInvoiceService->buildInvoiceData($quota);

        $pdf = Pdf::loadView('bills.invoice', $invoiceData)
            ->setPaper('letter')
            ->setOption('isRemoteEnabled', true);

        // Change from inline() to stream()
        return $pdf->stream('preview.pdf');
    }
    public function previewHtml(Request $request, int $quotaId)
    {
        $quota = Quota::with([
            'departament.owner',
            'waterReading',
            'pays' => function ($query) {
                $query->where('status', 2)
                    ->where('type', 1)
                    ->orderByDesc('pay_date');
            },
        ])->find($quotaId);

        if (! $quota) {
            return $this->returnFail(404, 'Cuota no encontrada');
        }

        if ((int) $quota->status !== 3) {
            return $this->returnFail(403, 'El recibo solo está disponible para cuotas pagadas');
        }

        $invoiceData = $this->billInvoiceService->buildInvoiceData($quota);

        // En lugar de cargar DOMPDF, retornas la vista de Blade directamente
        return view('bills.invoice', $invoiceData);
    }

    public function sendEmail(Request $request, int $quotaId)
    {
        $quota = Quota::with([
            'departament.owner',
            'waterReading',
            'pays' => function ($query) {
                $query->where('status', 2)
                    ->where('type', 1)
                    ->orderByDesc('pay_date');
            },
        ])->find($quotaId);

        if (! $quota) {
            return $this->returnFail(404, 'Cuota no encontrada');
        }

        if ((int) $quota->status !== 3) {
            return $this->returnFail(403, 'El recibo solo está disponible para cuotas pagadas');
        }

        $owner = $quota->departament->owner;
        if (! $owner || ! $owner->email) {
            return $this->returnFail(422, 'El propietario no tiene un correo electrónico registrado');
        }

        $invoiceData = $this->billInvoiceService->buildInvoiceData($quota);

        $invoiceData['downloadUrl'] = route('bill-invoice.download', [
            'quotaId' => $quota->id,
        ]);

        Mail::to($owner->email)->send(new BillInvoiceMail($invoiceData));

        return $this->returnSuccess(200, [
            'message' => 'Recibo enviado exitosamente a ' . $owner->email,
        ]);
    }

    public function sendBulkEmails(Request $request, int $monthlyBillId)
    {
        $monthlyBill = MonthlyBills::find($monthlyBillId);
        if (! $monthlyBill) {
            return $this->returnFail(404, 'Presupuesto mensual no encontrado');
        }

        $quotas = Quota::with([
            'departament.owner',
            'waterReading',
            'pays' => function ($query) {
                $query->where('status', 2)
                    ->where('type', 1)
                    ->orderByDesc('pay_date');
            },
        ])
            ->where('month', $monthlyBill->month)
            ->whereYear('due_date', $monthlyBill->year)
            ->where('status', 3)
            ->get();

        if ($quotas->isEmpty()) {
            return $this->returnFail(404, 'No hay cuotas pagadas para este período');
        }

        $sentCount = 0;
        $failedCount = 0;
        $failedEmails = [];

        foreach ($quotas as $quota) {
            $owner = $quota->departament->owner;
            if (! $owner || ! $owner->email) {
                $failedCount++;
                $failedEmails[] = $quota->departament->number . ' (sin email)';

                continue;
            }

            $invoiceData = $this->billInvoiceService->buildInvoiceData($quota);
            $invoiceData['downloadUrl'] = route('bill-invoice.download', [
                'quotaId' => $quota->id,
            ]);

            try {
                Mail::to($owner->email)->send(new BillInvoiceMail($invoiceData));
                $sentCount++;
            } catch (\Exception $e) {
                $failedCount++;
                $failedEmails[] = $quota->departament->number . ' (' . $owner->email . ')';
            }
        }

        return $this->returnSuccess(200, [
            'message' => "Enviados: {$sentCount}, Fallidos: {$failedCount}",
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'failed_emails' => $failedEmails,
        ]);
    }

    public function testSend(Request $request, int $quotaId)
    {
        $quota = Quota::with([
            'departament.owner',
            'waterReading',
            'pays' => function ($query) {
                $query->where('status', 2)
                    ->where('type', 1)
                    ->orderByDesc('pay_date');
            },
        ])->find($quotaId);

        if (! $quota) {
            return $this->returnFail(404, 'Cuota no encontrada');
        }

        $result = $this->billInvoiceService->sendBillInvoiceForQuota($quota);

        if ($result) {
            return $this->returnSuccess(200, [
                'message' => 'Recibo enviado exitosamente',
                'quota_id' => $quota->id,
                'owner_email' => $quota->departament->owner->email ?? 'N/A',
            ]);
        }

        return $this->returnFail(500, 'Error al enviar el recibo');
    }

    public function listPaidQuotas(Request $request)
    {
        $quotas = Quota::with([
            'departament.owner',
            'waterReading',
            'pays' => function ($query) {
                $query->where('status', 2)
                    ->where('type', 1)
                    ->orderByDesc('pay_date');
            },
        ])
            ->where('status', 3)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($quota) {
                return [
                    'id' => $quota->id,
                    'department_number' => $quota->departament->number,
                    'owner_name' => $quota->departament->owner->name ?? 'N/A',
                    'owner_email' => $quota->departament->owner->email ?? 'N/A',
                    'month' => $quota->month,
                    'month_label' => $quota->month_label,
                    'amount' => $quota->amount,
                    'due_date' => $quota->due_date,
                    'status' => $quota->status,
                    'status_label' => $quota->status_label,
                ];
            });

        return $this->returnSuccess(200, [
            'count' => $quotas->count(),
            'quotas' => $quotas,
        ]);
    }
}
