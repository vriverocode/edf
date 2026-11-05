<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\BillInvoiceMail;
use App\Models\Departament;
use App\Models\MonthlyBills;
use App\Models\Pay;
use App\Models\PayMethod;
use App\Models\Quota;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BillInvoiceService
{
    private const MONTH_NAMES = [
        '',
        'Enero',
        'Febrero',
        'Marzo',
        'Abril',
        'Mayo',
        'Junio',
        'Julio',
        'Agosto',
        'Septiembre',
        'Octubre',
        'Noviembre',
        'Diciembre',
    ];

    public function sendBillInvoiceForQuota(Quota $quota): bool
    {
        try {
            $quota->load([
                'departament.owner',
                'responsiblePivot.user',
                'waterReading',
                'pays' => function ($query) {
                    $query->where('status', 2)
                        ->where('type', 1)
                        ->orderByDesc('pay_date');
                },
            ]);

            $recipient = $quota->responsiblePivot?->user ?? $quota->departament->owner;
            if (! $recipient || ! $recipient->email) {
                Log::warning("BillInvoiceService: Recipient not found or no email for quota {$quota->id}");

                return false;
            }

            $invoiceData = $this->buildInvoiceData($quota);
            $invoiceData['downloadUrl'] = route('bill-invoice.download', [
                'quotaId' => $quota->id,
            ]);

            Mail::to($recipient->email)->send(new BillInvoiceMail($invoiceData));

            $recipientType = $quota->isTenantResponsible() ? 'inquilino' : 'propietario';
            Log::info("BillInvoiceService: Invoice sent for quota {$quota->id} to {$recipient->email} ({$recipientType})");

            return true;
        } catch (\Throwable $e) {
            Log::error("BillInvoiceService: Failed to send invoice for quota {$quota->id}: {$e->getMessage()}");

            return false;
        }
    }

    public function sendBillInvoicesForPay(Pay $pay): array
    {
        $sentCount = 0;
        $failedCount = 0;

        $quotaIds = $pay->consolidatedQuotaIds();
        if (empty($quotaIds)) {
            return ['sent' => 0, 'failed' => 0];
        }

        $quotas = Quota::whereIn('id', $quotaIds)->where('status', 3)->get();

        foreach ($quotas as $quota) {
            $result = $this->sendBillInvoiceForQuota($quota);
            if ($result) {
                $sentCount++;
            } else {
                $failedCount++;
            }
        }

        return ['sent' => $sentCount, 'failed' => $failedCount];
    }

    public function buildInvoiceData(Quota $quota): array
    {
        $departament = $quota->departament;
        $owner = $departament->owner;
        $responsiblePivot = $quota->responsiblePivot;
        $responsibleUser = $responsiblePivot?->user;
        $waterReading = $quota->waterReading;

        $month = (int) $quota->month;
        $year = $quota->due_date ? Carbon::parse($quota->due_date)->year : now()->year;

        $maintenanceAmount = (float) $quota->maintenance_amount;
        $waterAmount = (float) $quota->water_amount;
        $totalAmount = (float) $quota->amount;

        $waterConsumption = 0;
        $waterPricePerM3 = 0;
        if ($waterReading) {
            $waterConsumption = max(0, (float) $waterReading->current_reading - (float) $waterReading->previous_reading);
            $waterPricePerM3 = (float) $waterReading->m3_price;
        }

        $previousMonth = $month === 1 ? 12 : $month - 1;
        $previousMonthYear = $month === 1 ? $year - 1 : $year;
        $waterYear = $previousMonthYear;

        $monthlyBill = MonthlyBills::query()
            ->where('month', $month)
            ->where('year', $year)
            ->latest('id')
            ->first();

        $units = Departament::where('user_id', $owner->id ?? 0)->get();
        $totalArea = $units->sum('area');
        $totalParticipation = $units->sum('participation_percentage');

        $pays = $quota->pays->take(10);
        $paymentHistory = $pays->map(function ($pay) {
            return [
                'date' => $pay->pay_date ? Carbon::parse($pay->pay_date)->format('d/m/Y') : 'N/A',
                'description' => 'Recibo De Mantenimiento '.
                    self::MONTH_NAMES[(int) Carbon::parse($pay->created_at)->format('m')].
                    ' '.Carbon::parse($pay->created_at)->format('Y'),
                'amount' => (float) $pay->amount,
                'interest' => 0,
                'total_paid' => (float) $pay->amount,
            ];
        })->toArray();

        $initialBalance = Quota::where('departament_id', $departament->id)
            ->whereIn('status', [1, 4])
            ->where('id', '!=', $quota->id)
            ->sum('amount');

        $monthlyPayments = Pay::where('user_id', $owner->id ?? 0)
            ->where('type', 1)
            ->where('status', 2)
            ->whereMonth('pay_date', now()->month)
            ->whereYear('pay_date', now()->year)
            ->sum('amount');

        $overdueQuotas = Quota::where('departament_id', $departament->id)
            ->where('status', 4)
            ->count();

        $overdueDays = 0;
        if ($quota->due_date && $quota->status === 3) {
            $lastPay = $pays->first();
            if ($lastPay && $lastPay->pay_date) {
                $dueDate = Carbon::parse($quota->due_date);
                $payDate = Carbon::parse($lastPay->pay_date);
                if ($payDate->isAfter($dueDate)) {
                    $overdueDays = $dueDate->diffInDays($payDate);
                }
            }
        }

        $finalBalance = $initialBalance + $totalAmount - $monthlyPayments;

        $emissionDate = now()->format('d/m/Y');
        $dueDateFormatted = $quota->due_date
            ? Carbon::parse($quota->due_date)->format('d/m/Y')
            : now()->addDays(15)->format('d/m/Y');

        return [
            'quota' => $quota,
            'departament' => $departament,
            'owner' => $owner,
            'waterReading' => $waterReading,
            'monthLabel' => self::MONTH_NAMES[$month],
            'year' => $year,
            'maintenanceAmount' => $maintenanceAmount,
            'waterAmount' => $waterAmount,
            'totalAmount' => $totalAmount,
            'waterConsumption' => $waterConsumption,
            'waterPricePerM3' => $waterPricePerM3,
            'commonWaterConsumption' => $monthlyBill->common_water_consumption_m3 ?? 0,
            'commonWaterCost' => round(($monthlyBill->common_water_consumption_m3 ?? 0) * $waterPricePerM3, 2),
            'previousMonthLabel' => self::MONTH_NAMES[$previousMonth],
            'waterYear' => $waterYear,
            'emissionDate' => $emissionDate,
            'dueDate' => $dueDateFormatted,
            'amountInWords' => $this->numberToWords($totalAmount),
            'initialBalance' => $initialBalance,
            'monthlyPayments' => $monthlyPayments,
            'overdueQuotas' => $overdueQuotas,
            'overdueDays' => $overdueDays,
            'finalBalance' => $finalBalance,
            'units' => $units,
            'totalArea' => $totalArea,
            'totalParticipation' => $totalParticipation,
            'paymentHistory' => $paymentHistory,
            'monthlyBill' => $monthlyBill,
            'depositCode' => $departament->code_pay ?? '-',
            'receiptNumber' => '001 - '.str_pad((string) $quota->id, 6, '0', STR_PAD_LEFT),
            'totalMaintenanceBudget' => (float) ($monthlyBill->total_maintenance_budget ?? 0),
        ];
    }

    /**
     * Datos de una cuota para el estado de cuenta combinado. Funciona con
     * cualquier estado (pagada, pendiente, en revision, vencida).
     */
    public function buildStatementData(Quota $quota): array
    {
        $quota->loadMissing([
            'departament.owner',
            'responsiblePivot.user',
            'waterReading',
        ]);

        $departament = $quota->departament;
        $owner = $departament?->owner;
        $responsible = $quota->responsiblePivot?->user;
        $waterReading = $quota->waterReading;

        $month = (int) $quota->month;
        $year = $quota->resolvedYear();
        $isMonthly = $quota->isMonthly();

        $waterConsumption = 0;
        $waterPricePerM3 = 0;
        if ($waterReading) {
            $waterConsumption = max(0, (float) $waterReading->current_reading - (float) $waterReading->previous_reading);
            $waterPricePerM3 = (float) $waterReading->m3_price;
        }

        $totalAmount = (float) $quota->amount;
        $creditAvailable = $isMonthly
            ? (new CreditService)->maxApplicableForQuotas(collect([$quota]))
            : 0.0;
        $creditAvailable = round($creditAvailable, 2);

        $lastPay = $quota->pays()
            ->where('pays.status', 2)
            ->where('pays.type', 1)
            ->with('payMethod')
            ->orderByDesc('pay_date')
            ->first();
        $payment = null;
        if ($lastPay) {
            $payment = [
                'date' => $lastPay->pay_date ? Carbon::parse($lastPay->pay_date)->format('d/m/Y') : '-',
                'method' => $lastPay->payMethod?->name ?? '-',
                'reference' => $lastPay->reference ?? '-',
                'amount' => (float) $lastPay->amount,
                'credit_applied' => (float) ($lastPay->credit_applied ?? 0),
                'consolidated_count' => count($lastPay->consolidatedQuotaIds()),
            ];
        }

        return [
            'quota' => $quota,
            'departament' => $departament,
            'owner' => $owner,
            'responsible' => $responsible,
            'is_tenant' => $quota->isTenantResponsible(),
            'month' => $month,
            'year' => $year,
            'is_monthly' => $isMonthly,
            'period_label' => $isMonthly ? self::MONTH_NAMES[$month].' '.$year : 'Periodo '.$year,
            'due_date' => $quota->due_date ? Carbon::parse($quota->due_date)->format('d/m/Y') : '-',
            'maintenance_amount' => (float) $quota->maintenance_amount,
            'water_amount' => (float) $quota->water_amount,
            'extra_amount' => (float) $quota->extra_amount,
            'total_amount' => $totalAmount,
            'water_consumption' => $waterConsumption,
            'water_price_per_m3' => $waterPricePerM3,
            'status' => (int) $quota->status,
            'status_label' => $quota->status_label,
            'is_paid' => (int) $quota->status === 3,
            'credit_available' => $creditAvailable,
            'amount_after_credit' => max(0, round($totalAmount - $creditAvailable, 2)),
            'payment' => $payment,
        ];
    }

    public function buildReceiptData(Quota $quota): array
    {
        $quota->load([
            'departament.owner',
            'responsiblePivot.user',
            'waterReading',
        ]);

        $departament = $quota->departament;
        $owner = $departament->owner;
        $waterReading = $quota->waterReading;

        $month = (int) $quota->month;
        $year = $quota->due_date ? Carbon::parse($quota->due_date)->year : now()->year;

        $maintenanceAmount = (float) $quota->maintenance_amount;
        $waterAmount = (float) $quota->water_amount;
        $totalAmount = (float) $quota->amount;

        $waterConsumption = 0;
        $waterPricePerM3 = 0;
        if ($waterReading) {
            $waterConsumption = max(0, (float) $waterReading->current_reading - (float) $waterReading->previous_reading);
            $waterPricePerM3 = (float) $waterReading->m3_price;
        }

        $monthlyBill = MonthlyBills::query()
            ->where('month', $month)
            ->where('year', $year)
            ->latest('id')
            ->first();

        $expenses = $monthlyBill
            ? $monthlyBill->expenses()->with(['provider', 'serviceCategory'])->get()
            : collect();
        $totalExpenses = $expenses->sum('amount');

        $units = Departament::where('user_id', $owner->id ?? 0)->get();
        $totalParticipation = $units->where('tenant_pays_quota', false)->sum('participation_percentage');

        $emissionDate = now()->format('d/m/Y');
        $dueDateFormatted = $quota->due_date
            ? Carbon::parse($quota->due_date)->format('d/m/Y')
            : now()->addDays(15)->format('d/m/Y');

        $initialBalance = Quota::where('departament_id', $departament->id)
            ->whereIn('status', [1, 4])
            ->where('id', '!=', $quota->id)
            ->sum('amount');

        $payMethod = PayMethod::find(1);
        $bankData = $payMethod ? json_decode($payMethod->data, true) : [];

        $collectionRate = $monthlyBill && $monthlyBill->total_maintenance_budget > 0
            ? round(($monthlyBill->monthly_budget / $monthlyBill->total_maintenance_budget) * 100, 4)
            : 0;

        return [
            'quota' => $quota,
            'departament' => $departament,
            'owner' => $owner,
            'waterReading' => $waterReading,
            'monthLabel' => self::MONTH_NAMES[$month],
            'year' => $year,
            'maintenanceAmount' => $maintenanceAmount,
            'waterAmount' => $waterAmount,
            'totalAmount' => $totalAmount,
            'waterConsumption' => $waterConsumption,
            'waterPricePerM3' => $waterPricePerM3,
            'expenses' => $expenses,
            'totalExpenses' => $totalExpenses,
            'units' => $units,
            'totalParticipation' => $totalParticipation,
            'totalArea' => $units->sum('area'),
            'emissionDate' => $emissionDate,
            'dueDate' => $dueDateFormatted,
            'amountInWords' => $this->numberToWords($totalAmount),
            'initialBalance' => $initialBalance,
            'bankData' => $bankData,
            'monthlyBill' => $monthlyBill,
            'collectionRate' => $collectionRate,
        ];
    }

    private function numberToWords(float $number): string
    {
        $ones = [
            '', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO',
            'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ',
            'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE',
            'DIECISEIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE',
        ];

        $tens = [
            '', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA',
            'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA',
        ];

        $hundreds = [
            '', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS',
            'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS',
        ];

        $number = round($number, 2);
        $intPart = (int) $number;
        $decPart = round(($number - $intPart) * 100);

        if ($intPart === 0) {
            return 'CERO';
        }

        $result = '';

        if ($intPart >= 100) {
            if ($intPart === 100) {
                return 'CIEN';
            }
            $result .= $hundreds[(int) ($intPart / 100)].' ';
            $intPart %= 100;
        }

        if ($intPart >= 20) {
            $result .= $tens[(int) ($intPart / 10)].' ';
            $intPart %= 10;
        }

        if ($intPart > 0) {
            $result .= $ones[$intPart].' ';
        }

        $result = trim($result);

        if ($decPart > 0) {
            $result .= ' Y '.$decPart.'/100';
        }

        return $result;
    }
}
