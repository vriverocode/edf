<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\BillInvoiceMail;
use App\Models\Departament;
use App\Models\MonthlyBills;
use App\Models\Pay;
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
