<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProviderController extends Controller
{
    public function store(Request $request)
    {
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
}
