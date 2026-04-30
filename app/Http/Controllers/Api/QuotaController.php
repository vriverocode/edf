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

        return $this->returnSuccess(200, $quotas->get());
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
        $quota = Quota::with([
            "pay",
            "departament.owner",
            "waterReading:id,month,year,previous_reading,current_reading,m3_price"
        ])->findOrFail($id);

        $month = $quota->waterReading?->month ?? $quota->month;
        $year = $quota->waterReading?->year;

        if (!$year && $quota->due_date) {
            $year = Carbon::parse($quota->due_date)->year;
        }

        $monthlyBill = null;
        if ($month && $year) {
            $monthlyBill = MonthlyBills::query()
                ->select('id', 'total_maintenance_budget', 'water_price_per_m3')
                ->where('month', $month)
                ->where('year', $year)
                ->latest('id')
                ->first();
        }

        $waterConsumptionM3 = null;
        if ($quota->waterReading) {
            $waterConsumptionM3 = max(
                0,
                (float) $quota->waterReading->current_reading - (float) $quota->waterReading->previous_reading
            );
        }

        $quotaData = $quota->toArray();
        $quotaData['water_consumption_m3'] = $waterConsumptionM3;
        $quotaData['water_price_per_m3'] = $monthlyBill?->water_price_per_m3 ?? $quota->waterReading?->m3_price;
        $quotaData['water_reading_id'] = $quota->water_reading_id;
        $quotaData['maintenance_participation_percentage'] = $quota->departament?->participation_percentage;
        $quotaData['maintenance_budget_total'] = $monthlyBill?->total_maintenance_budget;
        $quotaData['monthly_bill_id'] = $monthlyBill?->id;

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
