<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DepartmentCharge;
use App\Models\Expense;
use App\Models\Quota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DepartmentChargeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = DepartmentCharge::with(['departament', 'expense'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('departament_id')) {
            $query->where('departament_id', $request->departament_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('departament', fn ($q) => $q->where('number', 'like', "%{$search}%"))
                ->orWhere('description', 'like', "%{$search}%");
        }

        if ($request->filled('paginate') && intval($request->paginate) > 0) {
            return $this->returnSuccess(200, $query->paginate(intval($request->paginate)));
        }

        return $this->returnSuccess(200, $query->get());
    }

    public function show(int $id): JsonResponse
    {
        $charge = DepartmentCharge::with(['departament', 'expense', 'quotas'])->find($id);

        if (! $charge) {
            return $this->returnFail(404, 'Cargo no encontrado');
        }

        return $this->returnSuccess(200, $charge);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'departament_id' => 'required|exists:departaments,id',
            'expense_id' => 'nullable|exists:expenses,id',
            'description' => 'required|string|max:500',
            'total_amount' => 'required|numeric|min:0.01',
            'installments' => 'required|integer|min:1',
            'start_month' => 'required|integer|between:1,12',
            'start_year' => 'required|integer|min:2020',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(422, $validator->errors()->first());
        }

        $monthlyAmount = round($request->total_amount / $request->installments, 2);

        $charge = DepartmentCharge::create([
            'departament_id' => $request->departament_id,
            'expense_id' => $request->expense_id,
            'description' => $request->description,
            'total_amount' => $request->total_amount,
            'monthly_amount' => $monthlyAmount,
            'installments' => $request->installments,
            'paid_installments' => 0,
            'status' => 1,
            'start_month' => $request->start_month,
            'start_year' => $request->start_year,
        ]);

        $this->updateExistingQuotas($charge);

        return $this->returnSuccess(201, $charge->load('departament'));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $charge = DepartmentCharge::find($id);

        if (! $charge) {
            return $this->returnFail(404, 'Cargo no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'departament_id' => 'required|exists:departaments,id',
            'expense_id' => 'nullable|exists:expenses,id',
            'description' => 'required|string|max:500',
            'total_amount' => 'required|numeric|min:0.01',
            'installments' => 'required|integer|min:1',
            'start_month' => 'required|integer|between:1,12',
            'start_year' => 'required|integer|min:2020',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(422, $validator->errors()->first());
        }

        $monthlyAmount = round($request->total_amount / $request->installments, 2);

        $charge->update([
            'departament_id' => $request->departament_id,
            'expense_id' => $request->expense_id,
            'description' => $request->description,
            'total_amount' => $request->total_amount,
            'monthly_amount' => $monthlyAmount,
            'installments' => $request->installments,
            'start_month' => $request->start_month,
            'start_year' => $request->start_year,
        ]);

        $this->updateExistingQuotas($charge);

        return $this->returnSuccess(200, $charge->fresh()->load('departament'));
    }

    public function destroy(int $id): JsonResponse
    {
        $charge = DepartmentCharge::find($id);

        if (! $charge) {
            return $this->returnFail(404, 'Cargo no encontrado');
        }

        $this->removeExtrasFromQuotas($charge);
        $charge->delete();

        return $this->returnSuccess(200, ['message' => 'Cargo eliminado correctamente']);
    }

    public function history(int $id): JsonResponse
    {
        $charge = DepartmentCharge::with(['quotas' => function ($q) {
            $q->with('pays')->orderBy('month')->orderBy('year');
        }])->find($id);

        if (! $charge) {
            return $this->returnFail(404, 'Cargo no encontrado');
        }

        $history = $charge->quotas->map(function ($quota) {
            $pay = $quota->pays->firstWhere('status', 2) ?? $quota->pays->first();

            return [
                'quota_id' => $quota->id,
                'month' => $quota->month,
                'year' => $quota->year,
                'amount' => $quota->extra_amount,
                'status' => $quota->status_label,
                'paid' => (int) $quota->status === 3,
                'pay_date' => $pay?->pay_date,
            ];
        });

        return $this->returnSuccess(200, [
            'charge' => $charge,
            'history' => $history,
        ]);
    }

    public function availableExpenses(): JsonResponse
    {
        $expenses = Expense::where('status', 3)
            ->whereNull('department_charges.expense_id')
            ->where('is_template', false)
            ->leftJoin('department_charges', 'expenses.id', '=', 'department_charges.expense_id')
            ->select('expenses.*')
            ->whereNull('department_charges.id')
            ->orderBy('expenses.created_at', 'desc')
            ->get();

        return $this->returnSuccess(200, $expenses);
    }

    private function updateExistingQuotas(DepartmentCharge $charge): void
    {
        $existingQuotas = DB::table('quotas')
            ->where('departament_id', $charge->departament_id)
            ->whereRaw('(year * 12 + month) >= ? * 12 + ?', [$charge->start_year, $charge->start_month])
            ->whereRaw('(year * 12 + month) < ? * 12 + ?', [
                $charge->start_year + (int) (($charge->start_month + $charge->installments - 1) / 12),
                (($charge->start_month + $charge->installments - 1) % 12) + 1,
            ])
            ->get();

        foreach ($existingQuotas as $quota) {
            $quotaModel = Quota::find($quota->id);
            $currentMonthIndex = ($quota->year * 12 + $quota->month) - ($charge->start_year * 12 + $charge->start_month) + 1;

            if ($currentMonthIndex >= 1 && $currentMonthIndex <= $charge->installments) {
                DB::table('charge_quota')->updateOrInsert(
                    ['department_charge_id' => $charge->id, 'quota_id' => $quota->id],
                    ['installment_number' => $currentMonthIndex]
                );

                $totalExtra = DB::table('charge_quota')
                    ->join('department_charges', 'department_charges.id', '=', 'charge_quota.department_charge_id')
                    ->where('charge_quota.quota_id', $quota->id)
                    ->sum('department_charges.monthly_amount');

                Quota::where('id', $quota->id)->update([
                    'extra_amount' => round($totalExtra, 2),
                    'amount' => round($quota->maintenance_amount + $quota->water_amount + $totalExtra, 2),
                ]);
            }
        }
    }

    private function removeExtrasFromQuotas(DepartmentCharge $charge): void
    {
        $linkedQuotas = DB::table('charge_quota')
            ->where('department_charge_id', $charge->id)
            ->pluck('quota_id');

        DB::table('charge_quota')->where('department_charge_id', $charge->id)->delete();

        foreach ($linkedQuotas as $quotaId) {
            $totalExtra = DB::table('charge_quota')
                ->join('department_charges', 'department_charges.id', '=', 'charge_quota.department_charge_id')
                ->where('charge_quota.quota_id', $quotaId)
                ->sum('department_charges.monthly_amount');

            $quota = Quota::find($quotaId);
            if ($quota) {
                $quota->update([
                    'extra_amount' => round($totalExtra, 2),
                    'amount' => round($quota->maintenance_amount + $quota->water_amount + $totalExtra, 2),
                ]);
            }
        }
    }
}
