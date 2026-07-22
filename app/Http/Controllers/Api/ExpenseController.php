<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\MonthlyBills;
use App\Models\Provider;
use App\Models\Rol;
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
                'provider_id' => ['nullable', 'integer', 'exists:providers,id'],
                'category_id' => ['nullable', 'integer', 'exists:service_categories,id'],
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
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

    public function unassigned(Request $request)
    {
        try {
            $validated = $request->validate([
                'month' => ['required', 'integer', 'between:1,12'],
                'year' => ['required', 'integer'],
            ]);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $expenses = Expense::query()
            ->with(['provider:id,name'])
            ->whereNull('monthly_bill_id')
            ->whereMonth('issue_date', $validated['month'])
            ->whereYear('issue_date', $validated['year'])
            ->orderBy('issue_date', 'desc')
            ->get();

        $totalAmount = $expenses->sum('amount');

        return $this->returnSuccess(200, [
            'expenses' => $expenses,
            'total_amount' => (float) $totalAmount,
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
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }
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
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }
        $expense = Expense::find($id);
        if (! $expense) {
            return $this->returnFail(404, 'Gasto no encontrado');
        }

        try {
            $validated = $this->validateExpense($request, true, $expense);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $expense->update($validated);

        return $this->returnSuccess(200, $expense->load(['provider:id,name', 'monthlyBill:id,month,year']));
    }

    private function validateExpense(Request $request, bool $isUpdate = false, ?Expense $expense = null): array
    {
        $attachmentRules = ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'];

        if (! $isUpdate) {
            $attachmentRules = ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'];
        } elseif (! $expense?->attachment_url) {
            $attachmentRules = ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'];
        }

        $validated = $request->validate([
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
            'attachment' => $attachmentRules,
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
            'attachment.required' => 'La factura adjunta es requerida.',
            'attachment.file' => 'El archivo de factura no es válido.',
            'attachment.mimes' => 'La factura debe ser imagen (JPG, PNG, WEBP) o PDF.',
            'attachment.max' => 'La factura no debe superar 10 MB.',
        ]);

        if ($request->hasFile('attachment')) {
            $validated['attachment_url'] = $this->storeAttachment($request);
        }

        unset($validated['attachment']);

        return $validated;
    }

    private function storeAttachment(Request $request): string
    {
        $file = $request->file('attachment');
        $rand = rand(1000000, 9999999);
        $name = $rand.'_'.time().'.'.$file->extension();
        $destination = public_path('storage/images/expenses');

        if (! is_dir($destination)) {
            @mkdir($destination, 0775, true);
        }

        $file->move($destination, $name);

        return config('app.url')."/storage/images/expenses/{$name}";
    }
}
