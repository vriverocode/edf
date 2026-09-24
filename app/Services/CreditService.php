<?php

namespace App\Services;

use App\Models\CreditBalance;
use App\Models\CreditTransaction;
use App\Models\Departament;
use App\Models\Pay;
use App\Models\Quota;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CreditService
{
    public function getBalance(Departament $dept, ?int $month = null, ?int $year = null): float
    {
        return $this->getBalanceForDepartments([$dept->id], $month, $year)['total'];
    }

    public function getBalanceForDepartments(array $deptIds, ?int $month = null, ?int $year = null): array
    {
        if (empty($deptIds)) {
            return ['total' => 0.0, 'detail' => [], 'items' => collect()];
        }

        $query = CreditBalance::whereIn('departament_id', $deptIds)->where('balance', '>', 0);

        if ($month !== null && $year !== null) {
            $isOpen = Quota::isOpenPeriod($month, $year, $deptIds);
            $query->where(function ($q) use ($month, $year, $isOpen) {
                $q->where(function ($q2) use ($month, $year) {
                    $q2->where('applicable_month', $month)
                        ->where(function ($q3) use ($year) {
                            $q3->whereNull('applicable_year')
                                ->orWhere('applicable_year', $year);
                        });
                });
                if ($isOpen) {
                    $q->orWhere('applicable_month', CreditBalance::MONTH_FLEXIBLE);
                }
            });
        }

        $balances = $query->get();
        $total = round((float) $balances->sum('balance'), 2);

        $detail = $balances->groupBy('departament_id')
            ->map(fn ($rows) => round((float) $rows->sum('balance'), 2))
            ->map(fn ($v) => (float) $v)
            ->toArray();

        // Incluir depts sin saldo en 0 para no romper consumers
        foreach ($deptIds as $id) {
            if (! array_key_exists($id, $detail)) {
                $detail[$id] = 0.0;
            }
        }

        $items = $balances->map(fn ($b) => [
            'departament_id' => (int) $b->departament_id,
            'balance' => (float) $b->balance,
            'applicable_month' => (int) $b->applicable_month,
            'applicable_year' => $b->applicable_year !== null ? (int) $b->applicable_year : null,
        ])->values();

        return ['total' => $total, 'detail' => $detail, 'items' => $items];
    }

    public function createCredit(Departament $dept, float $amount, Pay $pay): CreditBalance
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('El monto del crédito debe ser mayor a 0.');
        }

        $creditBalance = CreditBalance::firstOrCreate(
            [
                'departament_id' => $dept->id,
                'applicable_month' => CreditBalance::MONTH_FLEXIBLE,
                'applicable_year' => null,
            ],
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
        $month = (int) $quota->month;
        $year = (int) ($quota->year ?? Carbon::parse($quota->due_date)->year ?? Carbon::now()->year);
        $eligible = $this->eligibleBalancesFor([$dept->id], $month, $year);

        if ($eligible->isEmpty()) {
            return 0.0;
        }

        $quotaAmount = (float) $quota->amount;
        $appliedTotal = 0.0;
        $remaining = $amount !== null
            ? round(min((float) $amount, $quotaAmount), 2)
            : round($quotaAmount, 2);

        foreach ($eligible as $creditBalance) {
            if ($remaining <= 0) {
                break;
            }

            $available = (float) $creditBalance->balance;
            if ($available <= 0) {
                continue;
            }

            $applied = round(min($remaining, $available), 2);
            if ($applied <= 0) {
                continue;
            }

            $newBalance = round($available - $applied, 2);
            $creditBalance->update(['balance' => $newBalance]);

            CreditTransaction::create([
                'departament_id' => $dept->id,
                'type' => CreditTransaction::TYPE_APPLIED,
                'amount' => $applied,
                'balance_after' => $newBalance,
                'pay_id' => $pay->id,
                'quota_id' => $quota->id,
                'description' => "Crédito aplicado a cuota #{$quota->id} del pago #{$pay->pay_id}",
                'created_at' => now(),
            ]);

            $appliedTotal = round($appliedTotal + $applied, 2);
            $remaining = round($remaining - $applied, 2);
        }

        return $appliedTotal;
    }

    public function wasAppliedToPay(Pay $pay): bool
    {
        return CreditTransaction::query()
            ->where('pay_id', $pay->id)
            ->where('type', CreditTransaction::TYPE_APPLIED)
            ->exists();
    }

    public function applyCreditToConsolidated(array $deptQuotas, float $creditToApply, Pay $pay, ?int $month = null, ?int $year = null): float
    {
        if ($creditToApply <= 0 || empty($deptQuotas)) {
            return 0.0;
        }

        if ($this->wasAppliedToPay($pay)) {
            return round((float) CreditTransaction::query()
                ->where('pay_id', $pay->id)
                ->where('type', CreditTransaction::TYPE_APPLIED)
                ->sum('amount'), 2);
        }

        if ($month === null || $year === null) {
            $quotas = Quota::whereIn('departament_id', array_keys($deptQuotas))->get();
            $period = Quota::resolvePeriod($quotas);
            if ($period) {
                $month = $period['month'];
                $year = $period['year'];
            }
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

            $quotaRemaining = (float) $quotaAmount;
            $eligible = $month !== null && $year !== null
                ? $this->eligibleBalancesFor([$deptId], $month, $year)
                : CreditBalance::where('departament_id', $deptId)
                    ->where('balance', '>', 0)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

            foreach ($eligible as $creditBalance) {
                if ($remaining <= 0 || $quotaRemaining <= 0) {
                    break;
                }

                $creditBalance = CreditBalance::whereKey($creditBalance->id)->lockForUpdate()->first();
                if (! $creditBalance || (float) $creditBalance->balance <= 0) {
                    continue;
                }

                $available = (float) $creditBalance->balance;
                $applied = round(min($remaining, $available, $quotaRemaining), 2);

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
                    'description' => 'Crédito consolidado aplicado en pago #'.$pay->pay_id,
                    'created_at' => now(),
                ]);

                $totalApplied = round($totalApplied + $applied, 2);
                $remaining = round($remaining - $applied, 2);
                $quotaRemaining = round($quotaRemaining - $applied, 2);
            }
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

    public function getAllWithBalance(?string $search = null): Collection
    {
        $balances = CreditBalance::where('balance', '>', 0)
            ->with(['departament.owner'])
            ->when($search, function ($query, $search) {
                $query->whereHas('departament', function ($q) use ($search) {
                    $q->where('number', 'like', "%{$search}%")
                        ->orWhereHas('owner', fn ($uq) => $uq->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('balance')
            ->get();

        // Una fila por depto en el listado admin (suma de periodos)
        return $balances
            ->groupBy('departament_id')
            ->map(function ($rows) {
                $first = $rows->first();
                $first->balance = round((float) $rows->sum('balance'), 2);
                $months = $rows->pluck('applicable_month')->unique()->values();
                $first->applicable_month = $months->count() === 1 ? $months->first() : CreditBalance::MONTH_FLEXIBLE;

                return $first;
            })
            ->values();
    }

    public function getAllTransactions(array $filters = []): LengthAwarePaginator
    {
        $query = CreditTransaction::with(['departament.owner', 'pay', 'quota'])
            ->orderByDesc('created_at');

        if (! empty($filters['departament_id'])) {
            $query->where('departament_id', $filters['departament_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->paginate($filters['per_page'] ?? 20);
    }

    private function eligibleBalancesFor(array $deptIds, int $month, int $year)
    {
        $isOpen = Quota::isOpenPeriod($month, $year, $deptIds);

        return CreditBalance::whereIn('departament_id', $deptIds)
            ->where('balance', '>', 0)
            ->where(function ($q) use ($month, $year, $isOpen) {
                $q->where(function ($q2) use ($month, $year) {
                    $q2->where('applicable_month', $month)
                        ->where(function ($q3) use ($year) {
                            $q3->whereNull('applicable_year')
                                ->orWhere('applicable_year', $year);
                        });
                });
                if ($isOpen) {
                    $q->orWhere('applicable_month', CreditBalance::MONTH_FLEXIBLE);
                }
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
