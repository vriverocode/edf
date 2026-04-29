<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncWaterReadingsFromMonthlyBillJob;
use App\Models\MonthlyBills;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MonthlyBillsController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'years' => ['nullable', 'array'],
                'years.*' => ['integer'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $perPage = $validated['per_page'] ?? 12;

        $query = MonthlyBills::query()->orderBy('year', 'desc')->orderBy('month', 'desc');

        $yearsFilter = $validated['years'] ?? null;
        if (is_array($yearsFilter) && count($yearsFilter) > 0) {
            $query->whereIn('year', $yearsFilter);
        }

        $paginator = $query->paginate($perPage);

        $availableYears = MonthlyBills::query()
            ->select('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->values();

        return $this->returnSuccess(200, [
            'pagination' => $paginator,
            'available_years' => $availableYears,
        ]);
    }

    public function show(int $id)
    {
        $monthlyBill = MonthlyBills::find($id);
        if (! $monthlyBill) {
            return $this->returnFail(404, 'Presupuesto mensual no encontrado');
        }

        return $this->returnSuccess(200, $monthlyBill);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'month' => ['required', 'integer', 'between:1,12',
                    Rule::unique('monthly_bills', 'month')->where(function ($query) use ($request) {
                        return $query->where('year', $request->input('year'));
                    }),
                ],
                'year' => ['required', 'integer'],
                'total_maintenance_budget' => ['required', 'numeric'],
                'water_price_per_m3' => ['required', 'numeric'],
                'total_water_bill_amount' => ['nullable', 'numeric'],
                'total_water_consumption_m3' => ['nullable', 'numeric'],
            ], [
                'month.required' => 'El mes es requerido.',
                'month.integer' => 'El mes debe ser un número entero.',
                'month.between' => 'El mes debe estar entre 1 y 12.',
                'month.unique' => 'Ya existe un presupuesto registrado para ese mes y año.',
                'year.required' => 'El año es requerido.',
                'year.integer' => 'El año debe ser un número entero.',
                'total_maintenance_budget.required' => 'El presupuesto total a distribuir es requerido.',
                'total_maintenance_budget.numeric' => 'El presupuesto total a distribuir debe ser numérico.',
                'water_price_per_m3.required' => 'El costo unitario de agua por m3 es requerido.',
                'water_price_per_m3.numeric' => 'El costo unitario de agua por m3 debe ser numérico.',
                'total_water_bill_amount.numeric' => 'El monto total del recibo de agua debe ser numérico.',
                'total_water_consumption_m3.numeric' => 'El consumo total de agua debe ser numérico.',
            ]);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $monthlyBill = MonthlyBills::create([
            'month' => $validated['month'],
            'year' => $validated['year'],
            'total_maintenance_budget' => $validated['total_maintenance_budget'],
            'water_price_per_m3' => $validated['water_price_per_m3'],
            'total_water_bill_amount' => $validated['total_water_bill_amount'] ?? null,
            'total_water_consumption_m3' => $validated['total_water_consumption_m3'] ?? null,
        ]);

        SyncWaterReadingsFromMonthlyBillJob::dispatch($monthlyBill->id);

        return $this->returnSuccess(200, $monthlyBill);
    }

    public function update(Request $request, int $id)
    {
        $monthlyBill = MonthlyBills::find($id);
        if (! $monthlyBill) {
            return $this->returnFail(404, 'Presupuesto mensual no encontrado');
        }

        try {
            $validated = $request->validate([
                'month' => ['required', 'integer', 'between:1,12',
                    Rule::unique('monthly_bills', 'month')
                        ->where(fn ($query) => $query->where('year', $request->input('year')))
                        ->ignore($monthlyBill->id),
                ],
                'year' => ['required', 'integer'],
                'total_maintenance_budget' => ['required', 'numeric'],
                'water_price_per_m3' => ['required', 'numeric'],
                'total_water_bill_amount' => ['nullable', 'numeric'],
                'total_water_consumption_m3' => ['nullable', 'numeric'],
            ], [
                'month.required' => 'El mes es requerido.',
                'month.integer' => 'El mes debe ser un número entero.',
                'month.between' => 'El mes debe estar entre 1 y 12.',
                'month.unique' => 'Ya existe un presupuesto registrado para ese mes y año.',
                'year.required' => 'El año es requerido.',
                'year.integer' => 'El año debe ser un número entero.',
                'total_maintenance_budget.required' => 'El presupuesto total a distribuir es requerido.',
                'total_maintenance_budget.numeric' => 'El presupuesto total a distribuir debe ser numérico.',
                'water_price_per_m3.required' => 'El costo unitario de agua por m3 es requerido.',
                'water_price_per_m3.numeric' => 'El costo unitario de agua por m3 debe ser numérico.',
                'total_water_bill_amount.numeric' => 'El monto total del recibo de agua debe ser numérico.',
                'total_water_consumption_m3.numeric' => 'El consumo total de agua debe ser numérico.',
            ]);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $monthlyBill->update([
            'month' => $validated['month'],
            'year' => $validated['year'],
            'total_maintenance_budget' => $validated['total_maintenance_budget'],
            'water_price_per_m3' => $validated['water_price_per_m3'],
            'total_water_bill_amount' => $validated['total_water_bill_amount'] ?? null,
            'total_water_consumption_m3' => $validated['total_water_consumption_m3'] ?? null,
        ]);

        SyncWaterReadingsFromMonthlyBillJob::dispatch($monthlyBill->id);

        return $this->returnSuccess(200, $monthlyBill);
    }
}

