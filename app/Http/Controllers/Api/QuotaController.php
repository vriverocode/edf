<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Departament;
use App\Models\MonthlyBills;
use App\Models\Pay;
use App\Models\Quota;
use App\Models\Rol;
use App\Services\MonthlyQuotaService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QuotaController extends Controller
{
    private function findQuotaForAuthUser(string $id, Request $request): Quota
    {
        $query = Quota::query();

        if ($request->user()->rol_id !== Rol::ADMIN) {
            $query->where(function (Builder $queryBuilder) use ($request) {
                $queryBuilder->whereHas('departament', fn (Builder $builder) => $builder->where('user_id', $request->user()->id))
                    ->orWhereHas('responsiblePivot', fn (Builder $builder) => $builder->where('user_id', $request->user()->id));
            });
        }

        return $query->findOrFail($id);
    }

    private function ensureAdmin(Request $request): ?JsonResponse
    {
        if (! in_array($request->user()->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return $this->returnFail(403, ['message' => 'No autorizado']);
        }

        return null;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Quota::baseAdminQuery();

        if ($request->user()->rol_id !== Rol::ADMIN) {
            $query->where(function (Builder $queryBuilder) use ($request) {
                $queryBuilder->whereHas('departament', fn (Builder $builder) => $builder->where('user_id', $request->user()->id))
                    ->orWhereHas('responsiblePivot', fn (Builder $builder) => $builder->where('user_id', $request->user()->id));
            });
        }

        $groupedQuotas = Quota::groupConsolidatedByMonth($query->get());

        return $this->returnSuccess(200, $groupedQuotas);
    }

    public function adminMonthlySummary(Request $request)
    {
        if ($denied = $this->ensureAdmin($request)) {
            return $denied;
        }

        $quotas = Quota::baseAdminQuery()->get();

        $summaries = $quotas
            ->groupBy(function ($quota) {
                $year = $quota->due_date
                    ? Carbon::parse($quota->due_date)->year
                    : now()->year;

                return (int) $quota->month.'_'.$year;
            })
            ->map(function ($group) {
                $first = $group->first();
                $month = (int) $first->month;
                $year = $first->due_date
                    ? (int) Carbon::parse($first->due_date)->year
                    : (int) now()->year;
                $aggregates = Quota::aggregateMonthlySummary($group);
                $pendingCount = Quota::countPendingQuotaPaymentsForMonth($month, $year);

                return array_merge([
                    'month' => $month,
                    'year' => $year,
                    'month_label' => $first->month_label,
                    'due_date' => $first->due_date,
                    'pending_validation_count' => $pendingCount,
                    'has_pending_validation' => $pendingCount > 0,
                ], $aggregates);
            })
            ->values()
            ->sortByDesc(fn ($row) => $row['year'] * 100 + $row['month'])
            ->values();

        return $this->returnSuccess(200, $summaries);
    }

    public function adminGroupedByOwnerForMonth(Request $request, int $month)
    {
        if ($denied = $this->ensureAdmin($request)) {
            return $denied;
        }

        $year = (int) $request->query('year');
        if ($year < 2000) {
            return $this->returnFail(422, ['message' => 'El parámetro year es obligatorio.']);
        }

        $query = Quota::baseAdminQuery()
            ->forMonthYear($month, $year);

        $statusFilter = $request->query('status');
        if ($statusFilter !== null && $statusFilter !== '' && (int) $statusFilter !== 4) {
            $statusFilter = (int) $statusFilter;
            if ($statusFilter >= 0 && $statusFilter <= 3) {
                $query->where('status', $statusFilter);
            }
        }

        $ownerSearch = $request->query('owner_search');
        if ($ownerSearch) {
            $query->whereHas('departament.owner', function ($q) use ($ownerSearch) {
                $q->where('name', 'like', "%{$ownerSearch}%");
            });
        }

        $deptSearch = $request->query('dept_search');
        if ($deptSearch) {
            $query->whereHas('departament', function ($q) use ($deptSearch) {
                $q->where('number', 'like', "%{$deptSearch}%");
            });
        }

        $quotas = $query->get();

        $grouped = Quota::groupConsolidatedByOwner($quotas);

        return $this->returnSuccess(200, $grouped);
    }

    public function getByMonth(Request $request, $month)
    {
        $quotas = Quota::with([
            'pays' => function ($query) {
                $query->where('status', '!=', 0)->orderByDesc('pay_date');
            },
            'pays.payMethod',
            'departament.owner',
            'responsiblePivot.user',
        ])->orderBy('created_at', 'desc');

        $userQuota = $request->owner ?? $request->user()->id;

        $quotas->where(function (Builder $queryBuilder) use ($userQuota) {
            $queryBuilder->whereHas('departament', fn (Builder $builder) => $builder->where('user_id', $userQuota))
                ->orWhereHas('responsiblePivot', fn (Builder $builder) => $builder->where('user_id', $userQuota));
        });

        $quotas->where('month', $month);

        if ($request->filled('status')) {
            $quotas->where('status', (int) $request->status);
        }

        if ($request->filled('year')) {
            $quotas->whereYear('due_date', (int) $request->query('year'));
        }

        return $this->returnSuccess(200, $quotas->get());
    }

    public function getByPay($payId)
    {
        $pay = Pay::with(['quotas.departament.owner', 'quotas.waterReading', 'payMethod', 'user'])->find($payId);
        if (! $pay) {
            return $this->returnFail(404, 'Pago no encontrado');
        }
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            $userDepartments = $user->apartaments()->pluck('id');
            $payDepartmentIds = $pay->quotas->pluck('departament_id');
            if ($payDepartmentIds->intersect($userDepartments)->isEmpty()) {
                return $this->returnFail(403, 'No autorizado');
            }
        }

        return $this->returnSuccess(200, $pay);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if ($denied = $this->ensureAdmin($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'departament_id' => ['required', 'integer', 'exists:departaments,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'due_date' => ['required', 'date'],
            'maintenance_amount' => ['nullable', 'numeric', 'min:0'],
            'water_amount' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['required', 'numeric', 'min:0'],
            'type' => ['nullable', 'integer', 'in:1,2'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'integer', 'in:0,1,2,3'],
        ]);

        if ($validator->fails()) {
            return $this->returnFail(422, $validator->errors()->first());
        }

        $quota = Quota::create([
            'departament_id' => $request->departament_id,
            'month' => $request->month,
            'due_date' => $request->due_date,
            'maintenance_amount' => $request->maintenance_amount ?? 0,
            'water_amount' => $request->water_amount ?? 0,
            'amount' => $request->amount,
            'type' => $request->type ?? 1,
            'description' => $request->description,
            'status' => $request->status ?? 1,
        ]);

        return $this->returnSuccess(200, $quota->load(['departament.owner']));
    }

    /**
     * Generate quotas for all departments for a given month/year.
     */
    public function generate(Request $request)
    {
        if ($denied = $this->ensureAdmin($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2000'],
            'maintenance_amount' => ['nullable', 'numeric', 'min:0'],
            'water_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($validator->fails()) {
            return $this->returnFail(422, $validator->errors()->first());
        }

        $month = (int) $request->month;
        $year = (int) $request->year;

        $existingCount = Quota::where('month', $month)
            ->whereYear('due_date', $year)
            ->count();

        if ($existingCount > 0) {
            return $this->returnFail(409, "Ya existen {$existingCount} cuotas generadas para {$month}/{$year}. Elimínalas primero si deseas regenerarlas.");
        }

        $departaments = Departament::with('owner')->get();
        if ($departaments->isEmpty()) {
            return $this->returnFail(400, 'No hay departamentos registrados');
        }

        $created = 0;
        $dueDate = Carbon::create($year, $month, 10)->format('Y-m-d');

        foreach ($departaments as $departament) {
            $participation = (float) ($departament->participation_percentage ?? 100);
            $maintenanceAmount = $request->maintenance_amount
                ? round(($request->maintenance_amount * $participation) / 100, 2)
                : 0;
            $waterAmount = $request->water_amount ?? 0;
            $totalAmount = $maintenanceAmount + $waterAmount;

            if ($totalAmount <= 0) {
                continue;
            }

            $tenantPivotId = MonthlyQuotaService::findActiveTenantPivotId($departament->id);
            $tenantPays = $departament->tenant_pays_quota ?? false;

            Quota::create([
                'departament_id' => $departament->id,
                'peoples_x_departments_id' => ($tenantPays && $tenantPivotId) ? $tenantPivotId : null,
                'month' => $month,
                'due_date' => $dueDate,
                'maintenance_amount' => $maintenanceAmount,
                'water_amount' => $waterAmount,
                'amount' => $totalAmount,
                'type' => 1,
                'status' => 1,
            ]);

            $created++;
        }

        return $this->returnSuccess(200, [
            'created' => $created,
            'month' => $month,
            'year' => $year,
            'message' => "{$created} cuotas generadas para {$month}/{$year}",
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $quota = Quota::with(['departament', 'waterReading'])->find($id);
        if (! $quota) {
            return $this->returnFail(404, 'Cuota no encontrada');
        }
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            $userDepartments = $user->units()->pluck('id');
            if (! $userDepartments->contains($quota->departament_id)) {
                return $this->returnFail(403, 'No autorizado');
            }
        }

        return $this->returnSuccess(200, $quota);
    }

    public function clientWaterDetail(Request $request, string $id)
    {
        $quota = $this->findQuotaForAuthUser($id, $request)
            ->load([
                'waterReading:id,departament_id,month,year,previous_reading,current_reading,m3_price,photo,created_at',
                'departament:id,number,participation_percentage,user_id',
            ]);

        if (! $quota->waterReading) {
            return $this->returnFail(404, 'La cuota no tiene medición de agua asociada');
        }

        $consumptionM3 = max(
            0,
            (float) $quota->waterReading->current_reading - (float) $quota->waterReading->previous_reading
        );

        return $this->returnSuccess(200, [
            'quota_id' => $quota->id,
            'water_reading_id' => $quota->water_reading_id,
            'month' => $quota->waterReading->month,
            'year' => $quota->waterReading->year,
            'departament' => $quota->departament,
            'previous_reading' => $quota->waterReading->previous_reading,
            'current_reading' => $quota->waterReading->current_reading,
            'water_consumption_m3' => round($consumptionM3, 2),
            'water_price_per_m3' => $quota->waterReading->m3_price,
            'water_amount' => $quota->water_amount,
            'photo' => $quota->waterReading->photo,
            'created_at' => $quota->waterReading->created_at,
        ]);
    }

    public function clientMaintenanceDetail(Request $request, string $id)
    {
        $quota = $this->findQuotaForAuthUser($id, $request)
            ->load([
                'departament:id,number,participation_percentage,user_id',
                'waterReading:id,month,year',
            ]);

        $month = $quota->waterReading?->month ?? $quota->month;
        $year = $quota->waterReading?->year;

        if (! $year && $quota->due_date) {
            $year = Carbon::parse($quota->due_date)->year;
        }

        $monthlyBill = null;
        if ($month && $year) {
            $monthlyBill = MonthlyBills::query()
                ->select('id', 'month', 'year', 'total_maintenance_budget')
                ->where('month', $month)
                ->where('year', $year)
                ->latest('id')
                ->first();
        }

        return $this->returnSuccess(200, [
            'quota_id' => $quota->id,
            'monthly_bill_id' => $monthlyBill?->id,
            'month' => $month,
            'year' => $year,
            'departament' => $quota->departament,
            'maintenance_amount' => $quota->maintenance_amount,
            'maintenance_participation_percentage' => $quota->departament?->participation_percentage,
            'maintenance_budget_total' => $monthlyBill?->total_maintenance_budget,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Quota $quota)
    {
        if ($denied = $this->ensureAdmin($request)) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'departament_id' => ['nullable', 'integer', 'exists:departaments,id'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'due_date' => ['nullable', 'date'],
            'maintenance_amount' => ['nullable', 'numeric', 'min:0'],
            'water_amount' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'type' => ['nullable', 'integer', 'in:1,2'],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'integer', 'in:0,1,2,3'],
        ]);

        if ($validator->fails()) {
            return $this->returnFail(422, $validator->errors()->first());
        }

        $data = $request->only([
            'departament_id', 'month', 'due_date',
            'maintenance_amount', 'water_amount', 'amount',
            'type', 'description', 'status',
        ]);
        $data = array_filter($data, fn ($v) => $v !== null && $v !== '');

        $quota->update($data);

        return $this->returnSuccess(200, $quota->fresh()->load(['departament.owner']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Quota $quota)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return $this->returnFail(403, 'No autorizado');
        }

        try {
            $quota->delete();

            return $this->returnSuccess(200, 'Cuota eliminada correctamente');
        } catch (Exception $e) {
            return $this->returnFail(500, 'Error al eliminar la cuota');
        }
    }
}
