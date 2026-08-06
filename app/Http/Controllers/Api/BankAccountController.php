<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BankAccountController extends Controller
{
    public function index(Request $request)
    {
        if ($request->filled('user_id')) {
            if (! in_array($request->user()->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
                return $this->returnFail(403, 'No autorizado');
            }

            $accounts = BankAccount::where('user_id', (int) $request->user_id)->get();
        } else {
            $accounts = BankAccount::where('user_id', $request->user()->id)->get();
        }

        return $this->returnSuccess(200, $accounts);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'data' => 'required|json',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(422, $validator->errors()->first());
        }

        $data = [
            'user_id' => $request->user()->id,
            'name' => $request->name,
            'data' => $request->data,
        ];

        if ($request->has('status')) {
            $data['status'] = $request->boolean('status');
        }

        $account = BankAccount::create($data);

        return $this->returnSuccess(200, $account);
    }

    public function show(Request $request, $id)
    {
        $account = BankAccount::where('user_id', $request->user()->id)->findOrFail($id);

        return $this->returnSuccess(200, $account);
    }

    public function update(Request $request, $id)
    {
        $account = BankAccount::where('user_id', $request->user()->id)->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'data' => 'required|json',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(422, $validator->errors()->first());
        }

        $data = [
            'name' => $request->name,
            'data' => $request->data,
        ];

        if ($request->has('status')) {
            $data['status'] = $request->boolean('status');
        }

        $account->update($data);

        return $this->returnSuccess(200, $account);
    }

    public function destroy(Request $request, $id)
    {
        $account = BankAccount::where('user_id', $request->user()->id)->findOrFail($id);
        $account->delete();

        return $this->returnSuccess(200, 'ok');
    }
}
