<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransactionCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TransactionCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = TransactionCategory::query()->where('status', 1);

        if ($request->filled('type')) {
            $query->where('type', (int) $request->get('type'));
        }

        $categories = $query->orderBy('name')->get(['id', 'name', 'type', 'status']);

        return $this->returnSuccess(200, $categories);
    }

    public function store(Request $request)
    {
        $user = request()->user();
        if ($user->rol_id != 1 && $user->rol_id != 8) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'nullable|integer|in:1,2',
            'status' => 'nullable|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(400, $validator->errors()->first());
        }

        try {
            $category = TransactionCategory::create([
                'name' => $request->name,
                'type' => $request->has('type') ? (int) $request->type : 1,
                'status' => $request->has('status') ? (int) $request->status : 1,
            ]);

            return $this->returnSuccess(200, $category);
        } catch (\Exception $e) {
            return $this->returnFail(500, 'Error al crear la categoría contable: '.$e->getMessage());
        }
    }
}
