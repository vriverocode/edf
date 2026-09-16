<?php

namespace App\Services;

use App\Models\CreditBalance;
use App\Models\CreditTransaction;
use App\Models\Departament;
use App\Models\Pay;
use App\Models\Quota;
use Illuminate\Support\Collection;

class CreditService
{
    public function getBalance(Departament $dept): float
    {
        $balance = CreditBalance::where('departament_id', $dept->id)->first();

        return $balance ? (float) $balance->balance : 0.0;
    }

    public function getBalanceForDepartments(array $deptIds): array
    {
        if (empty($deptIds)) {
            return ['total' => 0.0, 'detail' => []];
        }

        $balances = CreditBalance::whereIn('departament_id', $deptIds)->get();
        $total = round((float) $balances->sum('balance'), 2);
        $detail = $balances->pluck('balance', 'departament_id')->map(fn ($v) => (float) $v)->toArray();

        return ['total' => $total, 'detail' => $detail];
    }

    public function createCredit(Departament $dept, float $amount, Pay $pay): CreditBalance
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('El monto del crédito debe ser mayor a 0.');
        }

        $creditBalance = CreditBalance::firstOrCreate(
            ['departament_id' => $dept->id],
            ['balance' => 0]
        );

        $newBalance = round((float) $creditBalance->balance + $amount, 2);
        $creditBalance->update(['balance' => $newBalance]);

        CreditTransaction::create([
            'departament_id' => $dept->id,
            'type' => CreditTransaction::TYPE_CREATED,
            'amount' => round($amount, 2),
            'balance_after' => $newBalance,
            'pay_id' => $pay->id,
            'description' => "Crédito por sobrepago en pago #{$pay->pay_id}",
            'created_at' => now(),
        ]);

        return $creditBalance;
    }

    public function applyCredit(Departament $dept, Quota $quota, Pay $pay, ?float $amount = null): float
    {
        $creditBalance = CreditBalance::where('departament_id', $dept->id)->first();

        if (! $creditBalance || (float) $creditBalance->balance <= 0) {
            return 0.0;
        }

        $availableCredit = (float) $creditBalance->balance;
        $quotaAmount = (float) $quota->amount;

        if ($amount !== null) {
            $appliedAmount = min($amount, $availableCredit, $quotaAmount);
        } else {
            $appliedAmount = min($availableCredit, $quotaAmount);
        }
        $appliedAmount = round($appliedAmount, 2);

        if ($appliedAmount <= 0) {
            return 0.0;
        }

        $newBalance = round($availableCredit - $appliedAmount, 2);
        $creditBalance->update(['balance' => $newBalance]);

        CreditTransaction::create([
            'departament_id' => $dept->id,
            'type' => CreditTransaction::TYPE_APPLIED,
            'amount' => $appliedAmount,
            'balance_after' => $newBalance,
            'pay_id' => $pay->id,
            'quota_id' => $quota->id,
            'description' => "Crédito aplicado a cuota #{$quota->id} del pago #{$pay->pay_id}",
            'created_at' => now(),
        ]);

        return $appliedAmount;
    }

    public function applyCreditToConsolidated(array $deptQuotas, float $creditToApply, Pay $pay): float
    {
        if ($creditToApply <= 0 || empty($deptQuotas)) {
            return 0.0;
        }

        $totalApplied = 0.0;
        $remaining = $creditToApply;

        foreach ($deptQuotas as $deptId => $quotaAmount) {
            if ($remaining <= 0) {
                break;
            }

            $dept = Departament::find($deptId);
            if (! $dept) {
                continue;
            }

            $creditBalance = CreditBalance::where('departament_id', $deptId)->first();
            if (! $creditBalance || (float) $creditBalance->balance <= 0) {
                continue;
            }

            $available = (float) $creditBalance->balance;
            $applied = min($remaining, $available, (float) $quotaAmount);
            $applied = round($applied, 2);

            if ($applied <= 0) {
                continue;
            }

            $newBalance = round($available - $applied, 2);
            $creditBalance->update(['balance' => $newBalance]);

            CreditTransaction::create([
                'departament_id' => $deptId,
                'type' => CreditTransaction::TYPE_APPLIED,
                'amount' => $applied,
                'balance_after' => $newBalance,
                'pay_id' => $pay->id,
                'description' => "Crédito consolidado aplicado en pago #{$pay->pay_id}",
                'created_at' => now(),
            ]);

            $totalApplied += $applied;
            $remaining = round($remaining - $applied, 2);
        }

        return round($totalApplied, 2);
    }

    public function getHistory(Departament $dept): Collection
    {
        return CreditTransaction::where('departament_id', $dept->id)
            ->with(['pay', 'quota'])
            ->orderByDesc('created_at')
            ->get();
    }
}
