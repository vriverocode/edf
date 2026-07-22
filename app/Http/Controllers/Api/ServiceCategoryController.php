<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\Rol;
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
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
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

    public function update(Request $request, int $id)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

        $category = ServiceCategory::find($id);
        if (! $category) {
            return $this->returnFail(404, 'Categoría de servicio no encontrada');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'status' => 'nullable|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(400, $validator->errors()->first());
        }

        try {
            $category->update([
                'name' => $request->name,
                'status' => $request->has('status') ? (int) $request->status : $category->status,
            ]);

            return $this->returnSuccess(200, $category);
        } catch (Exception $e) {
            return $this->returnFail(500, 'Error al actualizar la categoría de servicio: '.$e->getMessage());
        }
    }

    public function destroy(int $id)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

        $category = ServiceCategory::find($id);
        if (! $category) {
            return $this->returnFail(404, 'Categoría de servicio no encontrada');
        }

        $providersCount = Provider::where('service_category_id', $id)->count();
        if ($providersCount > 0) {
            return $this->returnFail(409, 'No se puede eliminar la categoría porque tiene proveedores asociados.');
        }

        try {
            $category->delete();

            return $this->returnSuccess(200, 'Categoría de servicio eliminada con éxito');
        } catch (Exception $e) {
            return $this->returnFail(500, 'Error al eliminar la categoría de servicio: '.$e->getMessage());
        }
    }
}
