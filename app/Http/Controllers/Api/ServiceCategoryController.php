<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceCategory;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ServiceCategoryController extends Controller
{
    public function index()
    {
        $categories = ServiceCategory::query()
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

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
            'status' => 'nullable|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(400, $validator->errors()->first());
        }

        try {
            $category = ServiceCategory::create([
                'name' => $request->name,
                'status' => $request->has('status') ? (int) $request->status : 1,
            ]);

            return $this->returnSuccess(200, $category);
        } catch (Exception $e) {
            return $this->returnFail(500, 'Error al crear la categoría de servicio: '.$e->getMessage());
        }
    }
}
