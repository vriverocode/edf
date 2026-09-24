<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditTransaction;
use App\Models\Departament;
use App\Models\Pay;
use App\Models\Rol;
use App\Services\CreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreditController extends Controller
{
    public function getBalance(Request $request, ?int $id = null)
    {
        $creditService = new CreditService;

        if ($id) {
            return $this->getBalanceSingle($creditService, $id, $request);
        }

        return $this->getBalanceMultiple($creditService, $request);
    }

    private function getBalanceSingle(CreditService $creditService, int $id, Request $request)
    {
        $dept = Departament::find($id);
        if (! $dept) {
            return $this->returnFail(404, 'Departamento no encontrado');
        }

        $user = $request->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            if ($dept->user_id !== $user->id) {
                $isTenant = $dept->peoples()
                    ->where('user_id', $user->id)
                    ->exists();
                if (! $isTenant) {
                    return $this->returnFail(403, 'No autorizado');
                }
            }
        }

        $month = $request->filled('month') ? (int) $request->input('month') : null;
        $year = $request->filled('year') ? (int) $request->input('year') : null;
        $balance = $creditService->getBalance($dept, $month, $year);

        return $this->returnSuccess(200, [
            'departament' => $dept,
            'balance' => $balance,
        ]);
    }

    private function getBalanceMultiple(CreditService $creditService, Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return $this->returnFail(422, 'Se requiere al menos un ID de departamento');
        }

        $ids = array_map('intval', $ids);
        $month = $request->filled('month') ? (int) $request->input('month') : null;
        $year = $request->filled('year') ? (int) $request->input('year') : null;
        $result = $creditService->getBalanceForDepartments($ids, $month, $year);

        return $this->returnSuccess(200, $result);
    }

    public function getTransactions(Request $request, int $id)
    {
        $dept = Departament::find($id);
        if (! $dept) {
            return $this->returnFail(404, 'Departamento no encontrado');
        }

        $user = $request->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            if ($dept->user_id !== $user->id) {
                $isTenant = $dept->peoples()
                    ->where('user_id', $user->id)
                    ->exists();
                if (! $isTenant) {
                    return $this->returnFail(403, 'No autorizado');
                }
            }
        }

        $creditService = new CreditService;
        $transactions = $creditService->getHistory($dept);

        return $this->returnSuccess(200, $transactions);
    }

    public function listAll(Request $request)
    {
        $creditService = new CreditService;
        $search = $request->get('search');
        $balances = $creditService->getAllWithBalance($search);

        $result = $balances->sortBy(function ($balance) {
            return $balance->departament->inter_number;
        })->map(function ($balance) {
            $dept = $balance->departament;
            $owner = $dept->owner ?? $dept->availableOwner;

            return [
                'departament_id' => $dept->id,
                'number' => $dept->number,
                'type' => $dept->type,
                'owner_name' => $owner?->name ?? '—',
                'balance' => (float) $balance->balance,
                'applicable_month' => (int) $balance->applicable_month,
                'applicable_year' => $balance->applicable_year,
            ];
        })->values();

        $total = round((float) $balances->sum('balance'), 2);

        return $this->returnSuccess(200, [
            'data' => $result,
            'total' => $total,
        ]);
    }

    public function listAllTransactions(Request $request)
    {
        $creditService = new CreditService;

        $filters = $request->only(['departament_id', 'type', 'date_from', 'date_to', 'per_page']);
        $paginator = $creditService->getAllTransactions($filters);

        return $this->returnSuccess(200, $paginator);
    }

    public function getPaymentsForCredit(Request $request)
    {
        $payments = Pay::query()
            ->where('type', 1)
            ->whereIn('status', [1, 3])
            ->with(['user', 'quotas.departament', 'payMethod'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($pay) {
                $quotas = $pay->quotas;
                $deptNumbers = $quotas->pluck('departament.number')->filter()->unique()->implode(', ');
                $deptIds = $quotas->pluck('departament_id')->filter()->unique()->values()->all();
                $userName = $pay->user?->name ?? '—';

                return [
                    'id' => $pay->id,
                    'pay_id' => $pay->pay_id,
                    'user_name' => $userName,
                    'departament_numbers' => $deptNumbers ?: '—',
                    'departament_ids' => $deptIds,
                    'amount' => (float) $pay->amount,
                    'reference' => $pay->reference ?? '—',
                    'status' => $pay->status,
                    'status_label' => $pay->status_label,
                    'pay_date' => $pay->pay_date,
                    'created_at' => $pay->created_at,
                    'credit_applied' => (float) $pay->credit_applied,
                ];
            });

        return $this->returnSuccess(200, $payments);
    }

    public function storeManualCredit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pay_id' => ['required', 'exists:pays,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->returnFail(422, $validator->errors()->first());
        }

        $pay = Pay::with(['quotas.departament'])->find($request->pay_id);

        if ((int) $pay->type !== 1) {
            return $this->returnFail(422, 'El pago seleccionado no es una cuota.');
        }

        if (! in_array((int) $pay->status, [1, 3])) {
            return $this->returnFail(422, 'El pago debe estar completado o pendiente de validación.');
        }

        $creditAmount = round((float) $request->amount, 2);
        $payAmount = round((float) $pay->amount, 2);

        if ($creditAmount > $payAmount) {
            return $this->returnFail(422, 'El saldo a favor no puede ser mayor al monto del pago (S/. '.number_format($payAmount, 2).').');
        }

        $deptIds = $pay->quotas->pluck('departament_id')->filter()->unique()->values()->all();

        if (empty($deptIds)) {
            return $this->returnFail(422, 'El pago no tiene departamentos asociados.');
        }

        DB::beginTransaction();
        try {
            $creditService = new CreditService;
            $appliedTotal = 0.0;
            $remaining = $creditAmount;

            foreach ($deptIds as $deptId) {
                if ($remaining <= 0) {
                    break;
                }

                $dept = Departament::find($deptId);
                if (! $dept) {
                    continue;
                }

                $applyAmount = round(min($remaining, $creditAmount - $appliedTotal), 2);
                if ($applyAmount <= 0) {
                    break;
                }

                $creditService->createCredit($dept, $applyAmount, $pay);
                $appliedTotal += $applyAmount;
                $remaining = round($creditAmount - $appliedTotal, 2);
            }

            // Nota: createCredit usa fila flexible (applicable_month=0)

            if ($request->description) {
                foreach ($deptIds as $deptId) {
                    CreditTransaction::where('pay_id', $pay->id)
                        ->where('departament_id', $deptId)
                        ->where('type', CreditTransaction::TYPE_CREATED)
                        ->update(['description' => $request->description]);
                }
            }

            DB::commit();

            return $this->returnSuccess(200, [
                'message' => 'Saldo a favor creado correctamente.',
                'amount' => $appliedTotal,
                'pay_id' => $pay->id,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->returnFail(500, 'Error al crear el saldo a favor: '.$e->getMessage());
        }
    }
}
