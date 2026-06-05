<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\FinancialAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FinancialAccountController extends Controller
{
    public function index()
    {
        $accounts = FinancialAccount::with('currency:id,name,symbol')
            ->orderByDesc('id')
            ->get();

        $formatted = $accounts->map(function ($account) {
            $account->status_label = (int) $account->status === 1 ? 'Activo' : 'Inactivo';
            $account->status_color = (int) $account->status === 1 ? 'bg-green-500' : 'bg-red-500';
            return $account;
        });

        return $this->returnSuccess(200, $formatted);
    }

    public function show($id)
    {
        $account = FinancialAccount::with('currency:id,name,symbol')->find($id);
        if (!$account) {
            return $this->returnFail(404, 'Cuenta financiera no encontrada');
        }

        return $this->returnSuccess(200, $account);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'currency_id' => 'required|exists:currencies,id',
            'initial_balance' => 'required|numeric|min:0',
            'current_balance' => 'nullable|numeric|min:0',
            'status' => 'nullable|integer|in:0,1',
            'type' => 'nullable|integer|in:1,2,3',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(400, $validator->errors()->first());
        }

        try {
            $initialBalance = (float) $request->initial_balance;
            $currentBalance = $request->filled('current_balance')
                ? (float) $request->current_balance
                : $initialBalance;

            $account = FinancialAccount::create([
                'name' => $request->name,
                'currency_id' => $request->currency_id,
                'initial_balance' => $initialBalance,
                'current_balance' => $currentBalance,
                'status' => $request->has('status') ? (int) $request->status : 1,
                'type' => $request->has('type') ? (int) $request->type : 1,
            ]);

            return $this->returnSuccess(200, $account->load('currency:id,name,symbol'));
        } catch (\Exception $e) {
            return $this->returnFail(500, 'Error al crear la cuenta financiera: ' . $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'currency_id' => 'required|exists:currencies,id',
            'initial_balance' => 'required|numeric|min:0',
            'current_balance' => 'required|numeric|min:0',
            'status' => 'nullable|integer|in:0,1',
            'type' => 'nullable|integer|in:1,2,3',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(400, $validator->errors()->first());
        }

        try {
            $account = FinancialAccount::find($id);
            if (!$account) {
                return $this->returnFail(404, 'Cuenta financiera no encontrada');
            }

            $account->update([
                'name' => $request->name,
                'currency_id' => $request->currency_id,
                'initial_balance' => (float) $request->initial_balance,
                'current_balance' => (float) $request->current_balance,
                'status' => $request->has('status') ? (int) $request->status : $account->status,
                'type' => $request->has('type') ? (int) $request->type : $account->type,
            ]);

            return $this->returnSuccess(200, $account->fresh('currency:id,name,symbol'));
        } catch (\Exception $e) {
            return $this->returnFail(500, 'Error al actualizar la cuenta financiera: ' . $e->getMessage());
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(400, $validator->errors()->first());
        }

        try {
            $account = FinancialAccount::find($id);
            if (!$account) {
                return $this->returnFail(404, 'Cuenta financiera no encontrada');
            }

            $account->update(['status' => (int) $request->status]);
            return $this->returnSuccess(200, 'Estado actualizado');
        } catch (\Exception $e) {
            return $this->returnFail(500, 'Error al actualizar estado: ' . $e->getMessage());
        }
    }

    public function currencies()
    {
        $currencies = Currency::where('status', 1)->orderBy('name')->get();
        return $this->returnSuccess(200, $currencies);
    }
}
