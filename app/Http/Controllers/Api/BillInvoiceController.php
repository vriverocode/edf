<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\BillInvoiceMail;
use App\Models\MonthlyBills;
use App\Models\Quota;
use App\Models\Rol;
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
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

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
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

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

        $filename = 'recibo-'.
            $invoiceData['departament']->number.
            '-'.strtolower($invoiceData['monthLabel']).
            '-'.$invoiceData['year'].'.pdf';

        return $pdf->download($filename);
    }

    public function downloadReceipt(Request $request, int $quotaId)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

        $quota = Quota::with([
            'departament.owner',
            'waterReading',
        ])->find($quotaId);

        if (! $quota) {
            return $this->returnFail(404, 'Cuota no encontrada');
        }

        if ((int) $quota->status !== 3) {
            return $this->returnFail(403, 'El recibo solo está disponible para cuotas pagadas');
        }

        $receiptData = $this->billInvoiceService->buildReceiptData($quota);

        $pdf = Pdf::loadView('bills.receipt', $receiptData)
            ->setPaper('letter')
            ->setOption('isRemoteEnabled', true);

        $filename = 'recibo-mantenimiento-'.
            $receiptData['departament']->number.
            '-'.strtolower($receiptData['monthLabel']).
            '-'.$receiptData['year'].'.pdf';

        return $pdf->download($filename);
    }

    public function clientDownloadPdf(Request $request, int $quotaId)
    {
        $user = request()->user();

        $quota = Quota::with([
            'departament.owner',
            'responsiblePivot.user',
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

        if (! $this->canAccessQuota($quota, $user)) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

        $invoiceData = $this->billInvoiceService->buildInvoiceData($quota);

        $pdf = Pdf::loadView('bills.invoice', $invoiceData)
            ->setPaper('letter')
            ->setOption('isRemoteEnabled', true);

        $filename = 'recibo-'.
            $invoiceData['departament']->number.
            '-'.strtolower($invoiceData['monthLabel']).
            '-'.$invoiceData['year'].'.pdf';

        return $pdf->download($filename);
    }

    /**
     * Estado de cuenta combinado (una seccion por cuota) para el cliente.
     * A diferencia del recibo, funciona con cualquier estado de la cuota.
     */
    public function clientStatementPdf(Request $request)
    {
        $user = $request->user();

        $rawIds = $request->input('quota_ids', []);
        if (is_string($rawIds)) {
            $rawIds = explode(',', $rawIds);
        }

        if (! is_array($rawIds)) {
            return $this->returnFail(422, 'Debes seleccionar al menos una cuota.');
        }

        $quotaIds = array_values(array_unique(array_filter(
            array_map('intval', $rawIds),
            fn ($id) => $id > 0
        )));

        if ($quotaIds === []) {
            return $this->returnFail(422, 'Debes seleccionar al menos una cuota.');
        }

        if (count($quotaIds) > 20) {
            return $this->returnFail(422, 'Solo se pueden incluir hasta 20 cuotas por documento.');
        }

        $quotas = Quota::with([
            'departament.owner',
            'responsiblePivot.user',
            'waterReading',
        ])->whereIn('id', $quotaIds)->get();

        if ($quotas->count() !== count($quotaIds)) {
            return $this->returnFail(404, 'Cuota no encontrada');
        }

        foreach ($quotas as $quota) {
            if (! $this->canAccessQuota($quota, $user)) {
                return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
            }
        }

        // Respeta el orden enviado por el cliente (mas antiguo primero).
        $ordered = collect($quotaIds)
            ->map(fn ($id) => $quotas->firstWhere('id', $id))
            ->filter()
            ->values();

        $statementData = $ordered
            ->map(fn (Quota $quota) => $this->billInvoiceService->buildStatementData($quota))
            ->all();

        $first = $statementData[0];
        $period = $first['is_monthly']
            ? str_pad((string) $first['month'], 2, '0', STR_PAD_LEFT).'-'.$first['year']
            : (string) $first['year'];

        $pdf = Pdf::loadView('bills.quota-statement', [
            'quotas' => $statementData,
            'emissionDate' => now()->format('d/m/Y'),
        ])->setPaper('letter')
            ->setOption('isRemoteEnabled', true);

        return $pdf->download('estado-cuenta-'.$period.'.pdf');
    }

    private function canAccessQuota(Quota $quota, $user): bool
    {
        if (in_array((int) $user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN], true)) {
            return true;
        }

        if ((int) ($quota->departament->user_id ?? 0) === (int) $user->id) {
            return true;
        }

        return $quota->peoples_x_departments_id
            && (int) ($quota->responsiblePivot?->user_id ?? 0) === (int) $user->id;
    }

    public function previewPdf(Request $request, int $quotaId)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

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
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

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
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

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
            'message' => 'Recibo enviado exitosamente a '.$owner->email,
        ]);
    }

    public function sendBulkEmails(Request $request, int $monthlyBillId)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

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
                $failedEmails[] = $quota->departament->number.' (sin email)';

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
                $failedEmails[] = $quota->departament->number.' ('.$owner->email.')';
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
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

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
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
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
