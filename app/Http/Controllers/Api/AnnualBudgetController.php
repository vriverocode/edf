<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnnualBudget;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AnnualBudgetController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'year' => ['nullable', 'integer'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $perPage = $validated['per_page'] ?? 12;

        $query = AnnualBudget::query()->orderBy('year', 'desc');

        if (isset($validated['year'])) {
            $query->where('year', $validated['year']);
        }

        $paginator = $query->paginate($perPage);

        $availableYears = AnnualBudget::query()
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
        $budget = AnnualBudget::with(['templates' => function ($q) {
            $q->with('serviceCategory:id,name')->orderBy('sort_order');
        }])->find($id);

        if (! $budget) {
            return $this->returnFail(404, 'Presupuesto anual no encontrado');
        }

        $data = $budget->toArray();
        $data['total_template_amount'] = $budget->templates->sum('monthly_amount');

        return $this->returnSuccess(200, $data);
    }

    public function availableExpenses()
    {
        $templates = Expense::where('is_template', true)
            ->whereNull('parent_template_id')
            ->with('serviceCategory:id,name')
            ->orderBy('sort_order')
            ->get(['id', 'description', 'amount', 'monthly_amount', 'expense_type', 'service_category_id', 'sort_order']);

        // Filtrar duplicados por descripción para no mostrar el mismo gasto padre varias veces
        $uniqueTemplates = $templates->unique('description')->values();

        return $this->returnSuccess(200, $uniqueTemplates);
    }

    public function storeTemplate(Request $request)
    {
        try {
            $validated = $request->validate([
                'description' => ['required', 'string', 'max:255'],
                'monthly_amount' => ['required', 'numeric', 'min:0'],
                'expense_type' => ['nullable', 'integer', 'in:1,2'],
                'service_category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
                'annual_budget_id' => ['nullable', 'integer', 'exists:annual_budgets,id'],
            ]);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $monthlyAmount = $validated['monthly_amount'];
        $expense = Expense::create([
            'description' => $validated['description'],
            'monthly_amount' => $monthlyAmount,
            'amount' => $monthlyAmount * 12,
            'expense_type' => $validated['expense_type'] ?? 1,
            'service_category_id' => $validated['service_category_id'] ?? null,
            'annual_budget_id' => $validated['annual_budget_id'] ?? null,
            'is_template' => true,
            'status' => 4,
        ]);

        return $this->returnSuccess(200, $expense->load('serviceCategory:id,name'));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'year' => ['required', 'integer', 'unique:annual_budgets,year'],
                'name' => ['required', 'string', 'max:255'],
                'total_monthly_budget' => ['nullable', 'numeric', 'min:0'],
                'status' => ['nullable', 'integer', 'in:1,2,3'],
                'expenses' => ['nullable', 'array'],
                'expenses.*.id' => ['nullable', 'integer', 'exists:expenses,id'],
                'expenses.*.description' => ['required_with:expenses', 'string', 'max:255'],
                'expenses.*.amount' => ['nullable', 'numeric', 'min:0'],
                'expenses.*.monthly_amount' => ['required_with:expenses', 'numeric', 'min:0'],
                'expenses.*.expense_type' => ['nullable', 'integer'],
                'expenses.*.service_category_id' => ['nullable', 'integer'],
                'expenses.*.sort_order' => ['nullable', 'integer'],
            ], [
                'year.required' => 'El año es obligatorio.',
                'year.integer' => 'El año debe ser un número entero.',
                'year.unique' => 'Ya existe un presupuesto para este año.',
                'name.required' => 'El nombre es obligatorio.',
                'name.string' => 'El nombre debe ser un texto.',
                'name.max' => 'El nombre no debe exceder los 255 caracteres.',
                'total_monthly_budget.numeric' => 'El presupuesto total debe ser un número.',
                'total_monthly_budget.min' => 'El presupuesto total no puede ser negativo.',
                'status.integer' => 'El estado debe ser un número entero.',
                'status.in' => 'El estado seleccionado no es válido.',
                'expenses.array' => 'Los gastos deben ser una lista válida.',
                'expenses.*.id.integer' => 'El ID del gasto debe ser un número entero.',
                'expenses.*.id.exists' => 'Uno de los gastos seleccionados no existe.',
                'expenses.*.description.required_with' => 'La descripción del gasto es obligatoria.',
                'expenses.*.description.string' => 'La descripción del gasto debe ser un texto.',
                'expenses.*.description.max' => 'La descripción del gasto no debe exceder los 255 caracteres.',
                'expenses.*.amount.numeric' => 'El monto anual del gasto debe ser un número.',
                'expenses.*.amount.min' => 'El monto anual del gasto no puede ser negativo.',
                'expenses.*.monthly_amount.required_with' => 'El monto mensual del gasto es obligatorio.',
                'expenses.*.monthly_amount.numeric' => 'El monto mensual del gasto debe ser un número.',
                'expenses.*.monthly_amount.min' => 'El monto mensual del gasto no puede ser negativo.',
                'expenses.*.expense_type.integer' => 'El tipo de gasto debe ser un número entero.',
                'expenses.*.service_category_id.integer' => 'La categoría del servicio debe ser un número entero.',
                'expenses.*.sort_order.integer' => 'El orden debe ser un número entero.',
            ]);
        } catch (ValidationException $e) {
            return $this->returnFail(404, $e->validator->errors()->first());
        }

        $expensesData = $validated['expenses'] ?? [];
        $totalBudget = $validated['total_monthly_budget'] ?? collect($expensesData)->sum('monthly_amount');

        $budget = DB::transaction(function () use ($validated, $totalBudget, $expensesData) {
            $budget = AnnualBudget::create([
                'year' => $validated['year'],
                'name' => $validated['name'],
                'total_monthly_budget' => $totalBudget,
                'status' => $validated['status'] ?? AnnualBudget::STATUS_BORRADOR,
            ]);

            foreach ($expensesData as $index => $expense) {
                $monthlyAmount = $expense['monthly_amount'];
                if (! empty($expense['id'])) {
                    $existing = Expense::find($expense['id']);
                    if ($existing && $existing->annual_budget_id && $existing->annual_budget_id !== $budget->id) {
                        // El template pertenece a otro presupuesto, lo clonamos como hijo
                        Expense::create([
                            'annual_budget_id' => $budget->id,
                            'parent_template_id' => $existing->id,
                            'is_template' => true,
                            'description' => $expense['description'],
                            'amount' => $monthlyAmount * 12,
                            'monthly_amount' => $monthlyAmount,
                            'expense_type' => $expense['expense_type'] ?? 1,
                            'service_category_id' => $expense['service_category_id'] ?? null,
                            'sort_order' => $expense['sort_order'] ?? $index,
                            'status' => 4,
                        ]);
                    } else {
                        Expense::where('id', $expense['id'])->update([
                            'annual_budget_id' => $budget->id,
                            'amount' => $monthlyAmount * 12,
                            'monthly_amount' => $monthlyAmount,
                            'sort_order' => $expense['sort_order'] ?? $index,
                        ]);
                    }
                } else {
                    Expense::create([
                        'annual_budget_id' => $budget->id,
                        'is_template' => true,
                        'description' => $expense['description'],
                        'amount' => $monthlyAmount * 12,
                        'monthly_amount' => $monthlyAmount,
                        'expense_type' => $expense['expense_type'] ?? 1,
                        'service_category_id' => $expense['service_category_id'] ?? null,
                        'sort_order' => $expense['sort_order'] ?? $index,
                        'status' => 4,
                    ]);
                }
            }

            return $budget;
        });

        return $this->returnSuccess(200, $budget);
    }

    public function update(Request $request, int $id)
    {
        $budget = AnnualBudget::find($id);
        if (! $budget) {
            return $this->returnFail(404, 'Presupuesto anual no encontrado');
        }

        try {
            $validated = $request->validate([
                'year' => ['required', 'integer', 'unique:annual_budgets,year,'.$id],
                'name' => ['required', 'string', 'max:255'],
                'total_monthly_budget' => ['nullable', 'numeric', 'min:0'],
                'status' => ['nullable', 'integer', 'in:1,2,3'],
                'expenses' => ['nullable', 'array'],
                'expenses.*.id' => ['nullable', 'integer', 'exists:expenses,id'],
                'expenses.*.description' => ['required_with:expenses', 'string', 'max:255'],
                'expenses.*.amount' => ['nullable', 'numeric', 'min:0'],
                'expenses.*.monthly_amount' => ['required_with:expenses', 'numeric', 'min:0'],
                'expenses.*.expense_type' => ['nullable', 'integer'],
                'expenses.*.service_category_id' => ['nullable', 'integer'],
                'expenses.*.sort_order' => ['nullable', 'integer'],
            ], [
                'year.required' => 'El año es obligatorio.',
                'year.integer' => 'El año debe ser un número entero.',
                'year.unique' => 'Ya existe un presupuesto para este año.',
                'name.required' => 'El nombre es obligatorio.',
                'name.string' => 'El nombre debe ser un texto.',
                'name.max' => 'El nombre no debe exceder los 255 caracteres.',
                'total_monthly_budget.numeric' => 'El presupuesto total debe ser un número.',
                'total_monthly_budget.min' => 'El presupuesto total no puede ser negativo.',
                'status.integer' => 'El estado debe ser un número entero.',
                'status.in' => 'El estado seleccionado no es válido.',
                'expenses.array' => 'Los gastos deben ser una lista válida.',
                'expenses.*.id.integer' => 'El ID del gasto debe ser un número entero.',
                'expenses.*.id.exists' => 'Uno de los gastos seleccionados no existe.',
                'expenses.*.description.required_with' => 'La descripción del gasto es obligatoria.',
                'expenses.*.description.string' => 'La descripción del gasto debe ser un texto.',
                'expenses.*.description.max' => 'La descripción del gasto no debe exceder los 255 caracteres.',
                'expenses.*.amount.numeric' => 'El monto anual del gasto debe ser un número.',
                'expenses.*.amount.min' => 'El monto anual del gasto no puede ser negativo.',
                'expenses.*.monthly_amount.required_with' => 'El monto mensual del gasto es obligatorio.',
                'expenses.*.monthly_amount.numeric' => 'El monto mensual del gasto debe ser un número.',
                'expenses.*.monthly_amount.min' => 'El monto mensual del gasto no puede ser negativo.',
                'expenses.*.expense_type.integer' => 'El tipo de gasto debe ser un número entero.',
                'expenses.*.service_category_id.integer' => 'La categoría del servicio debe ser un número entero.',
                'expenses.*.sort_order.integer' => 'El orden debe ser un número entero.',
            ]);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $expensesData = $validated['expenses'] ?? [];
        $totalBudget = $validated['total_monthly_budget'] ?? collect($expensesData)->sum('monthly_amount');

        DB::transaction(function () use ($budget, $validated, $totalBudget, $expensesData) {
            $budget->update([
                'year' => $validated['year'],
                'name' => $validated['name'],
                'total_monthly_budget' => $totalBudget,
                'status' => $validated['status'] ?? $budget->status,
            ]);

            if ($expensesData !== []) {
                $linkedIds = collect($expensesData)->pluck('id')->filter()->toArray();

                Expense::where('annual_budget_id', $budget->id)
                    ->where('is_template', true)
                    ->whereNotIn('id', $linkedIds)
                    ->delete();

                foreach ($expensesData as $index => $expense) {
                    $monthlyAmount = $expense['monthly_amount'];
                    if (! empty($expense['id'])) {
                        $existing = Expense::find($expense['id']);
                        if ($existing && $existing->annual_budget_id && $existing->annual_budget_id !== $budget->id) {
                            // Pertenece a otro presupuesto, lo clonamos
                            Expense::create([
                                'annual_budget_id' => $budget->id,
                                'parent_template_id' => $existing->id,
                                'is_template' => true,
                                'description' => $expense['description'],
                                'amount' => $monthlyAmount * 12,
                                'monthly_amount' => $monthlyAmount,
                                'expense_type' => $expense['expense_type'] ?? 1,
                                'service_category_id' => $expense['service_category_id'] ?? null,
                                'sort_order' => $expense['sort_order'] ?? $index,
                                'status' => 4,
                            ]);
                        } else {
                            Expense::where('id', $expense['id'])->update([
                                'annual_budget_id' => $budget->id,
                                'amount' => $monthlyAmount * 12,
                                'monthly_amount' => $monthlyAmount,
                                'sort_order' => $expense['sort_order'] ?? $index,
                            ]);
                        }
                    } else {
                        Expense::create([
                            'annual_budget_id' => $budget->id,
                            'is_template' => true,
                            'description' => $expense['description'],
                            'amount' => $monthlyAmount * 12,
                            'monthly_amount' => $monthlyAmount,
                            'expense_type' => $expense['expense_type'] ?? 1,
                            'service_category_id' => $expense['service_category_id'] ?? null,
                            'sort_order' => $expense['sort_order'] ?? $index,
                            'status' => 4,
                        ]);
                    }
                }
            }
        });

        return $this->returnSuccess(200, $budget);
    }

    public function destroy(int $id)
    {
        $budget = AnnualBudget::find($id);
        if (! $budget) {
            return $this->returnFail(404, 'Presupuesto anual no encontrado');
        }

        if ($budget->status !== AnnualBudget::STATUS_BORRADOR) {
            return $this->returnFail(422, 'Solo se pueden eliminar presupuestos en estado Borrador');
        }

        Expense::where('annual_budget_id', $budget->id)
            ->where('is_template', true)
            ->delete();

        $budget->delete();

        return $this->returnSuccess(200, ['message' => 'Presupuesto eliminado correctamente']);
    }
}
