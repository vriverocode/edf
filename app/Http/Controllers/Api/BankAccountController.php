<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BankAccountController extends Controller
{
    public function index(Request $request)
    {
        $accounts = BankAccount::where('user_id', $request->user()->id)->get();

        return $this->returnSuccess(200, $accounts);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:bank,yape',
            'entity' => 'required_if:type,bank|string|max:255',
            'account_number' => 'required_if:type,bank|string|max:50',
            'cci' => 'nullable|string|max:50',
            'holder_name' => 'required_if:type,bank|string|max:255',
            'yape_phone' => 'required_if:type,yape|string|max:20',
            'yape_name' => 'required_if:type,yape|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(422, $validator->errors()->first());
        }

        $data = $request->only([
            'type', 'entity', 'account_number', 'cci',
            'holder_name', 'yape_phone', 'yape_name',
        ]);
        $data['user_id'] = $request->user()->id;

        if ($request->boolean('is_default')) {
            BankAccount::where('user_id', $request->user()->id)->update(['is_default' => false]);
            $data['is_default'] = true;
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
            'type' => 'required|in:bank,yape',
            'entity' => 'required_if:type,bank|string|max:255',
            'account_number' => 'required_if:type,bank|string|max:50',
            'cci' => 'nullable|string|max:50',
            'holder_name' => 'required_if:type,bank|string|max:255',
            'yape_phone' => 'required_if:type,yape|string|max:20',
            'yape_name' => 'required_if:type,yape|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(422, $validator->errors()->first());
        }

        $data = $request->only([
            'type', 'entity', 'account_number', 'cci',
            'holder_name', 'yape_phone', 'yape_name',
        ]);

        if ($request->boolean('is_default')) {
            BankAccount::where('user_id', $request->user()->id)->update(['is_default' => false]);
            $data['is_default'] = true;
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
