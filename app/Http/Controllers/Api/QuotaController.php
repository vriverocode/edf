<?php

namespace App\Http\Controllers\Api;

use App\Models\Quota;
use App\Models\MonthlyBills;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

class QuotaController extends Controller
{
    private function findQuotaForAuthUser(string $id, Request $request): Quota
    {
        $query = Quota::query();

        if ((int) $request->user()->id !== 1) {
            $query->whereHas('departament', function (Builder $builder) use ($request) {
                $builder->where('user_id', $request->user()->id);
            });
        }

        return $query->findOrFail($id);
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Quota::with(["pay", "departament.owner"])->orderBy('created_at', 'desc');

        // Filtrar por usuario si no es admin
        if ($request->user()->id != 1) {
            $query->whereHas('departament', function ($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            });
        }

        // Obtenemos todas las cuotas planas
        $quotas = $query->get();

        // Agrupamos usando Colecciones de Laravel
        $groupedQuotas = $quotas->groupBy(function ($quota) {
            // Creamos una llave única para agrupar: "ID_USUARIO - MES - AÑO"
            $userId = $quota->departament->user_id ?? '0';
            $year = date('Y', strtotime($quota->due_date));
            return $userId . '_' . $quota->month . '_' . $year;
            
        })->map(function ($group) {
            // $group contiene todas las cuotas de ese mes para ese usuario (Depa, Estacionamiento, etc.)
            $firstQuota = $group->first();
            $owner = $firstQuota->departament->owner;

            return [
                // Generamos un ID virtual uniendo los IDs (útil para el :key en Vue)
                'id' => 'group-' . $group->pluck('id')->join('-'),
                
                // Datos generales de la cuota agrupada
                'month' => $firstQuota->month,
                'due_date' => $firstQuota->due_date,
                'description' => 'Cuota Consolidada (' . $group->count() . ' unidades asignadas)',
                'owner_name' => $owner ? $owner->name : 'Desconocido',
                
                // Sumamos los montos automáticamente
                'maintenance_amount' => $group->sum('maintenance_amount'),
                'water_amount' => $group->sum('water_amount'),
                'amount' => $group->sum('amount'), // Total final a pagar
                
                // Lógica de Status: Si AL MENOS UNA cuota del grupo está pendiente (status 1), 
                // marcamos todo el bloque como pendiente. Si no, asumimos que está pagado (status 2).
                'status' => $group->contains('status', 1) ? 1 : 2,

                // Guardamos las cuotas originales por si la vista necesita desglosar
                'details' => $group->values()->all(),
            ];
        })->values(); // values() resetea las llaves del array para que el JSON quede limpio

        // Retornamos la data agrupada
        return $this->returnSuccess(200, $groupedQuotas);
    }
    public function byMonth(Request $request, $month)
    {
        //
        $quotas = Quota::with(["pay", "departament.owner"])->orderBy('created_at', 'desc');

        // Filtrar por usuario si no es admin
        if ($request->user()->id != 1) {
            $quotas->whereHas('departament', function (Builder $query) use ($request) {
                $query->where('user_id', $request->user()->id);
            });
        }

        // Aplicar filtros
        // $this->applyPaysFilter($quotas, $request);

        return $this->returnSuccess(200, $quotas->where('month',$month)->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $baseQuota = Quota::with("departament")->findOrFail($id);
        $userId = $baseQuota->departament->user_id;
        $month = $baseQuota->month;
        $year = Carbon::parse($baseQuota->due_date)->year;

        $quotas = Quota::with([
            "departament.owner",
            "waterReading:id,month,year,previous_reading,current_reading,m3_price"
        ])->whereHas('departament', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
        ->where('month', $month)
        ->whereYear('due_date', $year)
        ->get();

        $monthlyBill = MonthlyBills::query()
            ->select('id', 'total_maintenance_budget', 'water_price_per_m3')
            ->where('month', $month)
            ->where('year', $year)
            ->latest('id')
            ->first();

        $totalMaintenance = 0;
        $totalWater = 0;
        $totalAmount = 0;
        $totalParticipation = 0;
        $waterConsumptionM3 = 0;
        $waterPricePerM3 = $monthlyBill?->water_price_per_m3 ?? 0;
        
        $quotaIds = [];
        $descriptionLines = [];
        $breakdown = []; // <-- NUEVO: Array para guardar el detalle por unidad

        foreach ($quotas as $q) {
            $totalMaintenance += $q->maintenance_amount;
            $totalWater += $q->water_amount;
            $totalAmount += $q->amount;
            $totalParticipation += $q->departament?->participation_percentage ?? 0;
            $quotaIds[] = $q->id;

            $waterM3 = 0;
            if ($q->waterReading) {
                $waterM3 = max(0, (float) $q->waterReading->current_reading - (float) $q->waterReading->previous_reading);
                $waterConsumptionM3 += $waterM3;
                $waterPricePerM3 = $q->waterReading->m3_price ?? $waterPricePerM3;
            }

            // <-- NUEVO: Llenamos el array de desglose
            $breakdown[] = [
                'id' => $q->id,
                'unit_type' => $q->departament->type ?? 1,
                'unit_number' => $q->departament->number,
                'maintenance_amount' => $q->maintenance_amount,
                'water_amount' => $q->water_amount,
                'water_consumption_m3' => $waterM3,
                'amount' => $q->amount,
                'participation' => $q->departament->participation_percentage
            ];

            $unitType = match ((int) ($q->departament->type ?? 1)) {
                2 => 'Estacionamiento',
                3 => 'Deposito',
                default => 'Departamento',
            };

            $descriptionLines[] = sprintf(
                '%s %s: Mantenimiento %.2f | Agua %.2f | Total %.2f',
                $unitType,
                $q->departament->number,
                (float) $q->maintenance_amount,
                (float) $q->water_amount,
                (float) $q->amount
            );
        }

        $quotaData = $baseQuota->toArray();
        $quotaData['maintenance_amount'] = $totalMaintenance;
        $quotaData['water_amount'] = $totalWater;
        $quotaData['amount'] = $totalAmount;
        $quotaData['water_consumption_m3'] = $waterConsumptionM3;
        $quotaData['water_price_per_m3'] = $waterPricePerM3;
        $quotaData['maintenance_participation_percentage'] = $totalParticipation;
        $quotaData['maintenance_budget_total'] = $monthlyBill?->total_maintenance_budget;
        $quotaData['monthly_bill_id'] = $monthlyBill?->id;
        $quotaData['consolidated_ids'] = $quotaIds;
        $quotaData['description'] = implode("\n", $descriptionLines);
        $quotaData['breakdown'] = $breakdown; // <-- NUEVO: Lo pasamos al front

        return $this->returnSuccess(200, $quotaData);
    }

    public function clientWaterDetail(Request $request, string $id)
    {
        $quota = $this->findQuotaForAuthUser($id, $request)
            ->load([
                'waterReading:id,departament_id,month,year,previous_reading,current_reading,m3_price,photo,created_at',
                'departament:id,number,participation_percentage,user_id'
            ]);

        if (!$quota->waterReading) {
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
                'waterReading:id,month,year'
            ]);

        $month = $quota->waterReading?->month ?? $quota->month;
        $year = $quota->waterReading?->year;

        if (!$year && $quota->due_date) {
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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Quota $quota)
    {
        //
    }
}
