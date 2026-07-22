<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Provider;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProviderController extends Controller
{
    public function index(Request $request)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

        try {
            $validated = $request->validate([
                'search' => ['nullable', 'string', 'max:255'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            ]);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $perPage = $validated['per_page'] ?? 12;

        $paginator = Provider::query()
            ->with('category:id,name')
            ->when($validated['search'] ?? null, function ($q, $search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('tax_id', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage);

        return $this->returnSuccess(200, [
            'pagination' => $paginator,
        ]);
    }

    public function show(int $id)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

        $provider = Provider::with('category:id,name')->find($id);

        if (! $provider) {
            return $this->returnFail(404, 'Proveedor no encontrado');
        }

        return $this->returnSuccess(200, $provider);
    }

    public function store(Request $request)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'tax_id' => ['required', 'string', 'max:50', Rule::unique('providers', 'tax_id')],
                'service_category_id' => ['required', 'integer', 'exists:service_categories,id'],
                'contact_name' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'email' => ['nullable', 'email', 'max:255'],
            ], [
                'name.required' => 'El nombre del proveedor es requerido.',
                'tax_id.required' => 'El RUC / documento tributario es requerido.',
                'tax_id.unique' => 'Ya existe un proveedor con ese RUC / documento.',
                'service_category_id.required' => 'La categoría de servicio es requerida.',
                'service_category_id.exists' => 'La categoría de servicio seleccionada no es válida.',
                'email.email' => 'El correo electrónico no es válido.',
            ]);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $provider = Provider::create([
            ...$validated,
            'status' => 1,
        ]);

        return $this->returnSuccess(200, $provider->only(['id', 'name']));
    }

    public function update(Request $request, int $id)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

        $provider = Provider::find($id);
        if (! $provider) {
            return $this->returnFail(404, 'Proveedor no encontrado');
        }

        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'tax_id' => ['required', 'string', 'max:50', Rule::unique('providers', 'tax_id')->ignore($provider->id)],
                'service_category_id' => ['required', 'integer', 'exists:service_categories,id'],
                'contact_name' => ['nullable', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:50'],
                'email' => ['nullable', 'email', 'max:255'],
                'status' => ['nullable', 'integer', 'in:0,1'],
            ], [
                'name.required' => 'El nombre del proveedor es requerido.',
                'tax_id.required' => 'El RUC / documento tributario es requerido.',
                'tax_id.unique' => 'Ya existe un proveedor con ese RUC / documento.',
                'service_category_id.required' => 'La categoría de servicio es requerida.',
                'service_category_id.exists' => 'La categoría de servicio seleccionada no es válida.',
                'email.email' => 'El correo electrónico no es válido.',
            ]);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $provider->update($validated);

        return $this->returnSuccess(200, $provider->load('category:id,name'));
    }

    public function destroy(int $id)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

        $provider = Provider::find($id);
        if (! $provider) {
            return $this->returnFail(404, 'Proveedor no encontrado');
        }

        $expensesCount = Expense::where('provider_id', $id)->count();
        if ($expensesCount > 0) {
            return $this->returnFail(409, 'No se puede eliminar el proveedor porque tiene gastos asociados.');
        }

        $provider->delete();

        return $this->returnSuccess(200, 'Proveedor eliminado con éxito');
    }
}
