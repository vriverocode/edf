<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Departament;
use App\Models\Rol;
use App\Services\CreditService;
use Illuminate\Http\Request;

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

        $balance = $creditService->getBalance($dept);

        return $this->returnSuccess(200, [
            'departament_id' => $dept->id,
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
        $result = $creditService->getBalanceForDepartments($ids);

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
}
