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

        // Filtro por periodo: un saldo solo es elegible en su mes/año exacto.
        if ($month !== null) {
            $effectiveYear = $year ?? (int) Carbon::now()->year;
            $query->where('applicable_month', $month)
                ->where(function ($q) use ($effectiveYear) {
                    $q->whereNull('applicable_year')
                        ->orWhere('applicable_year', $effectiveYear);
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

    /**
     * Saldo a favor aplicable a las cuotas pendientes (status 1) de los
     * departamentos dados: por cada depto se suman los créditos cuyo
     * mes/año coincide con el de alguna cuota pendiente, con tope al saldo
     * pendiente de ese depto (un crédito solo descuenta la cuota de su depto).
     */
    public function getBalanceForPendingQuotas(array $deptIds): float
    {
        if (empty($deptIds)) {
            return 0.0;
        }

        $pending = Quota::whereIn('departament_id', $deptIds)
            ->where('status', 1)
            ->whereNotNull('month')
            ->get(['departament_id', 'month', 'year', 'due_date']);

        if ($pending->isEmpty()) {
            return 0.0;
        }

        $periodsByDept = $pending->groupBy('departament_id')->map(function ($quotas) {
            return $quotas->map(function (Quota $quota) {
                $year = $quota->year !== null && $quota->year !== ''
                    ? (int) $quota->year
                    : ($quota->due_date ? (int) Carbon::parse($quota->due_date)->year : (int) Carbon::now()->year);

                return [(int) $quota->month, $year];
            })->unique()->values();
        });

        $pendingByDept = $pending->groupBy('departament_id')
            ->map(fn ($quotas) => round((float) $quotas->sum('amount'), 2));

        $balances = CreditBalance::whereIn('departament_id', array_keys($periodsByDept->all()))
            ->where('balance', '>', 0)
            ->get();

        $total = 0.0;
        foreach ($balances->groupBy('departament_id') as $deptId => $rows) {
            $periods = $periodsByDept->get($deptId) ?? collect();
            if ($periods->isEmpty()) {
                continue;
            }

            $applicable = (float) $rows
                ->filter(function (CreditBalance $balance) use ($periods) {
                    return $periods->contains(function (array $period) use ($balance) {
                        return $period[0] === (int) $balance->applicable_month
                            && ($balance->applicable_year === null
                                || $period[1] === (int) $balance->applicable_year);
                    });
                })
                ->sum('balance');

            $total += min($applicable, (float) ($pendingByDept->get($deptId) ?? 0.0));
        }

        return round($total, 2);
    }

    /**
     * Primer mes calendario posterior a $fromDate que NO tenga cuota creada
     * para el departamento. Ej.: pago 15/09 → candidate Oct; si Oct ya tiene
     * cuota → Nov, y así sucesivamente (máx. 24 meses de búsqueda).
     *
     * @param  \DateTimeInterface|string  $fromDate  fecha del pago (día de pago)
     * @return array{0: int, 1: int} [month, year]
     */
    public function nextMonthWithoutQuota(Departament $dept, $fromDate): array
    {
        $cursor = Carbon::parse($fromDate ?? now())->startOfMonth()->addMonth();

        for ($i = 0; $i < 24; $i++) {
            $exists = Quota::where('departament_id', $dept->id)
                ->where('month', $cursor->month)
                ->where(function ($q) use ($cursor) {
                    $q->where('year', $cursor->year)
                        ->orWhereNull('year');
                })
                ->exists();

            if (! $exists) {
                return [(int) $cursor->month, (int) $cursor->year];
            }

            $cursor->addMonth();
        }

        return [(int) $cursor->month, (int) $cursor->year];
    }

    public function createCredit(Departament $dept, float $amount, Pay $pay, int $month, int $year): CreditBalance
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('El monto del crédito debe ser mayor a 0.');
        }

        if ($month < 1 || $month > 12) {
            throw new \InvalidArgumentException('El mes de aplicación debe estar entre 1 y 12.');
        }

        $creditBalance = CreditBalance::firstOrCreate(
            [
                'departament_id' => $dept->id,
                'applicable_month' => (int) $month,
                'applicable_year' => (int) $year,
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

    /**
     * Máximo de crédito consumible sobre un set de cuotas: por cada
     * periodo seleccionado y cada depto, min(saldo elegible del depto en
     * ese periodo, suma de las cuotas del depto en ese periodo). Un saldo
     * de junio solo puede descontar cuotas de junio de ese depto.
     */
    public function maxApplicableForQuotas(Collection $quotas): float
    {
        $amounts = $this->quotaAmountsByDeptPeriod($quotas);
        if ($amounts === []) {
            return 0.0;
        }

        // Una consulta de saldos por periodo (mes/año) para todos sus deptos.
        $byPeriod = [];
        foreach ($amounts as $key => $amount) {
            [$deptId, $month, $year] = array_map('intval', explode('|', $key));
            $byPeriod[$month.'|'.$year][] = ['dept_id' => $deptId, 'amount' => $amount];
        }

        $total = 0.0;
        foreach ($byPeriod as $periodKey => $rows) {
            [$month, $year] = array_map('intval', explode('|', $periodKey));
            $detail = $this->getBalanceForDepartments(
                array_column($rows, 'dept_id'),
                $month,
                $year
            )['detail'];

            foreach ($rows as $row) {
                $total += min($row['amount'], (float) ($detail[$row['dept_id']] ?? 0.0));
            }
        }

        return round($total, 2);
    }

    /**
     * Suma de montos de cuotas por "depto|mes|año" (solo cuotas mensuales).
     */
    private function quotaAmountsByDeptPeriod(Collection $quotas): array
    {
        $amounts = [];
        foreach ($quotas as $quota) {
            if (! $quota->isMonthly()) {
                continue;
            }
            $key = (int) $quota->departament_id.'|'.(int) $quota->month.'|'.$quota->resolvedYear();
            $amounts[$key] = round(($amounts[$key] ?? 0) + (float) $quota->amount, 2);
        }

        return $amounts;
    }

    /**
     * Consume el saldo a favor de un pago consolidado multi-mes: recorre
     * cada depto y periodo del set, tomando solo los saldos elegibles de
     * ese periodo con tope igual a la suma de las cuotas del depto en ese
     * periodo, hasta agotar $creditToApply.
     */
    public function applyCreditToConsolidated(Collection $quotas, float $creditToApply, Pay $pay): float
    {
        if ($creditToApply <= 0 || $quotas->isEmpty()) {
            return 0.0;
        }

        if ($this->wasAppliedToPay($pay)) {
            return round((float) CreditTransaction::query()
                ->where('pay_id', $pay->id)
                ->where('type', CreditTransaction::TYPE_APPLIED)
                ->sum('amount'), 2);
        }

        $amounts = $this->quotaAmountsByDeptPeriod($quotas);
        if ($amounts === []) {
            // Sin periodo determinable no se consume crédito: un crédito
            // fijado a un mes nunca se aplica a una cuota de otro mes.
            return 0.0;
        }

        // Orden determinista depto+periodo entre el alta y la aprobación.
        ksort($amounts);

        $totalApplied = 0.0;
        $remaining = $creditToApply;

        foreach ($amounts as $key => $quotaAmount) {
            if ($remaining <= 0) {
                break;
            }

            [$deptId, $month, $year] = array_map('intval', explode('|', $key));

            $dept = Departament::find($deptId);
            if (! $dept) {
                continue;
            }

            $quotaRemaining = (float) $quotaAmount;
            $eligible = $this->eligibleBalancesFor([$deptId], $month, $year);

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

    /**
     * Una fila por credit_balance (no se fusionan los meses): cada saldo
     * conserva su applicable_month/applicable_year originales para que el
     * listado admin muestre a qué mes aplica cada uno.
     */
    public function getAllWithBalance(?string $search = null): Collection
    {
        return CreditBalance::where('balance', '>', 0)
            ->with(['departament.owner', 'departament.availableOwner'])
            ->when($search, function ($query, $search) {
                $query->whereHas('departament', function ($q) use ($search) {
                    $q->where('number', 'like', "%{$search}%")
                        ->orWhereHas('owner', fn ($uq) => $uq->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('balance')
            ->get();
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
        return CreditBalance::whereIn('departament_id', $deptIds)
            ->where('balance', '>', 0)
            ->where('applicable_month', $month)
            ->where(function ($q) use ($year) {
                $q->whereNull('applicable_year')
                    ->orWhere('applicable_year', $year);
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }
}
