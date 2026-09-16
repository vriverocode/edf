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
            $q->orderBy('sort_order');
        }])->find($id);

        if (! $budget) {
            return $this->returnFail(404, 'Presupuesto anual no encontrado');
        }

        $data = $budget->toArray();
        $data['total_template_amount'] = $budget->templates->sum('amount');

        return $this->returnSuccess(200, $data);
    }

    public function availableExpenses()
    {
        $budget = AnnualBudget::active()->first();
        if (! $budget) {
            return $this->returnSuccess(200, []);
        }

        $templates = Expense::where('annual_budget_id', $budget->id)
            ->where('is_template', true)
            ->with('serviceCategory:id,name')
            ->orderBy('sort_order')
            ->get(['id', 'description', 'amount', 'expense_type', 'service_category_id', 'sort_order']);

        return $this->returnSuccess(200, $templates);
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
                'expenses.*.description' => ['required_with:expenses', 'string', 'max:255'],
                'expenses.*.amount' => ['required_with:expenses', 'numeric', 'min:0'],
                'expenses.*.expense_type' => ['nullable', 'integer'],
                'expenses.*.service_category_id' => ['nullable', 'integer'],
                'expenses.*.sort_order' => ['nullable', 'integer'],
            ]);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $expensesData = $validated['expenses'] ?? [];
        $totalBudget = $validated['total_monthly_budget'] ?? collect($expensesData)->sum('amount');

        $budget = DB::transaction(function () use ($validated, $totalBudget, $expensesData) {
            $budget = AnnualBudget::create([
                'year' => $validated['year'],
                'name' => $validated['name'],
                'total_monthly_budget' => $totalBudget,
                'status' => $validated['status'] ?? AnnualBudget::STATUS_BORRADOR,
            ]);

            foreach ($expensesData as $index => $expense) {
                Expense::create([
                    'annual_budget_id' => $budget->id,
                    'is_template' => true,
                    'description' => $expense['description'],
                    'amount' => $expense['amount'],
                    'expense_type' => $expense['expense_type'] ?? 1,
                    'service_category_id' => $expense['service_category_id'] ?? null,
                    'sort_order' => $expense['sort_order'] ?? $index,
                    'status' => 1,
                ]);
            }

            return $budget;
        });

        return $this->returnSuccess(201, $budget);
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
                'expenses.*.description' => ['required_with:expenses', 'string', 'max:255'],
                'expenses.*.amount' => ['required_with:expenses', 'numeric', 'min:0'],
                'expenses.*.expense_type' => ['nullable', 'integer'],
                'expenses.*.service_category_id' => ['nullable', 'integer'],
                'expenses.*.sort_order' => ['nullable', 'integer'],
            ]);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $expensesData = $validated['expenses'] ?? [];
        $totalBudget = $validated['total_monthly_budget'] ?? collect($expensesData)->sum('amount');

        DB::transaction(function () use ($budget, $validated, $totalBudget, $expensesData) {
            $budget->update([
                'year' => $validated['year'],
                'name' => $validated['name'],
                'total_monthly_budget' => $totalBudget,
                'status' => $validated['status'] ?? $budget->status,
            ]);

            if ($expensesData !== []) {
                Expense::where('annual_budget_id', $budget->id)
                    ->where('is_template', true)
                    ->delete();

                foreach ($expensesData as $index => $expense) {
                    Expense::create([
                        'annual_budget_id' => $budget->id,
                        'is_template' => true,
                        'description' => $expense['description'],
                        'amount' => $expense['amount'],
                        'expense_type' => $expense['expense_type'] ?? 1,
                        'service_category_id' => $expense['service_category_id'] ?? null,
                        'sort_order' => $expense['sort_order'] ?? $index,
                        'status' => 1,
                    ]);
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
