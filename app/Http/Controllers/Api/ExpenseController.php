<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\MonthlyBills;
use App\Models\Provider;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'month' => ['nullable', 'integer', 'between:1,12'],
                'year' => ['nullable', 'integer'],
                'status' => ['nullable', 'integer', 'in:1,2,3'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $perPage = $validated['per_page'] ?? 12;

        $paginator = Expense::query()
            ->with(['provider:id,name', 'monthlyBill:id,month,year'])
            ->filter($validated)
            ->orderBy('issue_date', 'desc')
            ->paginate($perPage);

        return $this->returnSuccess(200, [
            'pagination' => $paginator,
        ]);
    }

    public function formOptions()
    {
        $providers = Provider::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        $monthlyBills = MonthlyBills::query()
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->limit(36)
            ->get(['id', 'month', 'year']);

        $serviceCategories = ServiceCategory::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        return $this->returnSuccess(200, [
            'providers' => $providers,
            'monthly_bills' => $monthlyBills,
            'service_categories' => $serviceCategories,
        ]);
    }

    public function show(int $id)
    {
        $expense = Expense::query()
            ->with(['provider:id,name', 'monthlyBill:id,month,year'])
            ->find($id);

        if (! $expense) {
            return $this->returnFail(404, 'Gasto no encontrado');
        }

        return $this->returnSuccess(200, $expense);
    }

    public function store(Request $request)
    {
        try {
            $validated = $this->validateExpense($request);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $validated['status'] = $validated['status'] ?? 1;
        $expense = Expense::create($validated);

        return $this->returnSuccess(200, $expense->load(['provider:id,name', 'monthlyBill:id,month,year']));
    }

    public function update(Request $request, int $id)
    {
        $expense = Expense::find($id);
        if (! $expense) {
            return $this->returnFail(404, 'Gasto no encontrado');
        }

        try {
            $validated = $this->validateExpense($request);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $expense->update($validated);

        return $this->returnSuccess(200, $expense->load(['provider:id,name', 'monthlyBill:id,month,year']));
    }

    private function validateExpense(Request $request): array
    {
        return $request->validate([
            'provider_id' => ['required', 'integer', 'exists:providers,id'],
            'monthly_bill_id' => ['nullable', 'integer', 'exists:monthly_bills,id'],
            'invoice_number' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'expense_type' => ['required', 'integer', 'in:1,2'],
            'location_scope' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'attachment_url' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'integer', 'in:1,2,3'],
        ], [
            'provider_id.required' => 'El proveedor es requerido.',
            'provider_id.exists' => 'El proveedor seleccionado no es válido.',
            'monthly_bill_id.exists' => 'El presupuesto mensual seleccionado no es válido.',
            'amount.required' => 'El monto es requerido.',
            'amount.numeric' => 'El monto debe ser numérico.',
            'issue_date.required' => 'La fecha de emisión es requerida.',
            'due_date.required' => 'La fecha de vencimiento es requerida.',
            'due_date.after_or_equal' => 'La fecha de vencimiento debe ser igual o posterior a la de emisión.',
            'expense_type.required' => 'El tipo de gasto es requerido.',
            'description.required' => 'La descripción es requerida.',
        ]);
    }
}
