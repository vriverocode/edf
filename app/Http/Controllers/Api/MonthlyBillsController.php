<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncWaterReadingsFromMonthlyBillJob;
use App\Models\Expense;
use App\Models\MonthlyBills;
use App\Models\Rol;
use App\Services\MonthlyQuotaService;
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

        $totalExpenses = (float) Expense::where('monthly_bill_id', $monthlyBill->id)->sum('amount');

        $data = $monthlyBill->toArray();
        $data['total_expenses'] = $totalExpenses;

        return $this->returnSuccess(200, $data);
    }

    /**
     * Indica si ya existe un presupuesto cargado para un mes/año (solo administradores).
     */
    public function existsForPeriod(Request $request)
    {
        if ((int) $request->user()->rol_id !== Rol::ADMIN) {
            return $this->returnFail(403, 'No autorizado');
        }

        $month = (int) ($request->query('month', now()->month));
        $year = (int) ($request->query('year', now()->year));

        if ($month < 1 || $month > 12) {
            return $this->returnFail(422, 'Mes inválido');
        }

        $monthlyBill = MonthlyBills::query()
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        return $this->returnSuccess(200, [
            'exists' => $monthlyBill !== null,
            'monthly_bill_id' => $monthlyBill?->id,
        ]);
    }

    public function store(Request $request)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }
            $validated = $request->validate([
                'month' => ['required', 'integer', 'between:1,12',
                    Rule::unique('monthly_bills', 'month')->where(function ($query) use ($request) {
                        return $query->where('year', $request->input('year'));
                    }),
                ],
                'year' => ['required', 'integer'],
                'monthly_budget' => ['required', 'numeric', 'min:0'],
                'total_maintenance_budget' => ['required', 'numeric', 'min:0'],
                'water_price_per_m3' => ['required', 'numeric'],
                'total_water_bill_amount' => ['nullable', 'numeric'],
                'total_water_consumption_m3' => ['nullable', 'numeric'],
                'expense_ids' => ['nullable', 'array'],
                'expense_ids.*' => ['integer', 'exists:expenses,id'],
            ], [
                'month.required' => 'El mes es requerido.',
                'month.integer' => 'El mes debe ser un número entero.',
                'month.between' => 'El mes debe estar entre 1 y 12.',
                'month.unique' => 'Ya existe un presupuesto registrado para ese mes y año.',
                'year.required' => 'El año es requerido.',
                'year.integer' => 'El año debe ser un número entero.',
                'monthly_budget.required' => 'El presupuesto mensual base es requerido.',
                'monthly_budget.numeric' => 'El presupuesto mensual base debe ser numérico.',
                'monthly_budget.min' => 'El presupuesto mensual base no puede ser negativo.',
                'total_maintenance_budget.required' => 'El presupuesto total a distribuir es requerido.',
                'total_maintenance_budget.numeric' => 'El presupuesto total a distribuir debe ser numérico.',
                'total_maintenance_budget.min' => 'El presupuesto total a distribuir no puede ser negativo.',
                'water_price_per_m3.required' => 'El costo unitario de agua por m3 es requerido.',
                'water_price_per_m3.numeric' => 'El costo unitario de agua por m3 debe ser numérico.',
                'total_water_bill_amount.numeric' => 'El monto total del recibo de agua debe ser numérico.',
                'total_water_consumption_m3.numeric' => 'El consumo total de agua debe ser numérico.',
                'expense_ids.array' => 'Los IDs de gastos deben ser un arreglo.',
                'expense_ids.*.integer' => 'Cada ID de gasto debe ser un número entero.',
                'expense_ids.*.exists' => 'Uno o más gastos seleccionados no son válidos.',
            ]);
        

        $expenseIds = $validated['expense_ids'] ?? [];
        unset($validated['expense_ids']);

        $expensesTotal = Expense::whereIn('id', $expenseIds)->sum('amount');
        $calculatedTotal = $validated['monthly_budget'] + $expensesTotal;

        if (abs($calculatedTotal - $validated['total_maintenance_budget']) > 0.01) {
            return $this->returnFail(422, 'El total a distribuir no coincide: Presupuesto base + Gastos = '.number_format($calculatedTotal, 2));
        }

        $monthlyBill = MonthlyBills::create([
            'month' => $validated['month'],
            'year' => $validated['year'],
            'monthly_budget' => $validated['monthly_budget'],
            'total_maintenance_budget' => $calculatedTotal,
            'water_price_per_m3' => $validated['water_price_per_m3'],
            'total_water_bill_amount' => $validated['total_water_bill_amount'] ?? null,
            'total_water_consumption_m3' => $validated['total_water_consumption_m3'] ?? null,
        ]);

        if (count($expenseIds) > 0) {
            Expense::whereIn('id', $expenseIds)
                ->whereNull('monthly_bill_id')
                ->update(['monthly_bill_id' => $monthlyBill->id]);
        }

        SyncWaterReadingsFromMonthlyBillJob::dispatch($monthlyBill->id);

        return $this->returnSuccess(200, $monthlyBill);
    }

    public function update(Request $request, int $id)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }
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
                'monthly_budget' => ['required', 'numeric', 'min:0'],
                'total_maintenance_budget' => ['required', 'numeric', 'min:0'],
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
                'monthly_budget.required' => 'El presupuesto mensual base es requerido.',
                'monthly_budget.numeric' => 'El presupuesto mensual base debe ser numérico.',
                'monthly_budget.min' => 'El presupuesto mensual base no puede ser negativo.',
                'total_maintenance_budget.required' => 'El presupuesto total a distribuir es requerido.',
                'total_maintenance_budget.numeric' => 'El presupuesto total a distribuir debe ser numérico.',
                'total_maintenance_budget.min' => 'El presupuesto total a distribuir no puede ser negativo.',
                'water_price_per_m3.required' => 'El costo unitario de agua por m3 es requerido.',
                'water_price_per_m3.numeric' => 'El costo unitario de agua por m3 debe ser numérico.',
                'total_water_bill_amount.numeric' => 'El monto total del recibo de agua debe ser numérico.',
                'total_water_consumption_m3.numeric' => 'El consumo total de agua debe ser numérico.',
            ]);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $expenseIds = $monthlyBill->expenses()->pluck('id')->toArray();
        $expensesTotal = Expense::whereIn('id', $expenseIds)->sum('amount');
        $calculatedTotal = $validated['monthly_budget'] + $expensesTotal;

        if (abs($calculatedTotal - $validated['total_maintenance_budget']) > 0.01) {
            return $this->returnFail(422, 'El total a distribuir no coincide: Presupuesto base + Gastos = '.number_format($calculatedTotal, 2));
        }

        $monthlyBill->update([
            'month' => $validated['month'],
            'year' => $validated['year'],
            'monthly_budget' => $validated['monthly_budget'],
            'total_maintenance_budget' => $calculatedTotal,
            'water_price_per_m3' => $validated['water_price_per_m3'],
            'total_water_bill_amount' => $validated['total_water_bill_amount'] ?? null,
            'total_water_consumption_m3' => $validated['total_water_consumption_m3'] ?? null,
        ]);

        SyncWaterReadingsFromMonthlyBillJob::dispatch($monthlyBill->id);

        return $this->returnSuccess(200, $monthlyBill);
    }

    public function generateQuotas(int $id, MonthlyQuotaService $service)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

        $monthlyBill = MonthlyBills::find($id);
        if (! $monthlyBill) {
            return $this->returnFail(404, 'Presupuesto mensual no encontrado');
        }

        $result = $service->generateForPeriod((int) $monthlyBill->month, (int) $monthlyBill->year);

        $monthlyBill->update([
            'is_published' => true,
            'generated_at' => now(),
        ]);

        return $this->returnSuccess(200, [
            'generated' => $result['generated'],
            'skipped' => $result['skipped'],
            'monthly_bill' => $monthlyBill,
        ]);
    }
}
