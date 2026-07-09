<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayMethod;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PayMethodController extends Controller
{
    //
    public function index()
    {
        // $payMethod->dataList = json_decode($payMethod->data);
        $payMethods = PayMethod::get();
        $formattedPayMethods = $this->formattedData($payMethods);

        return $this->returnSuccess(200, $formattedPayMethods);
    }

    public function store(Request $request)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'dataList' => 'required|array|min:1',
            'dataList.*.title' => 'required|string',
            'dataList.*.data' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(400, $validator->errors()->first());
        }
        try {
            PayMethod::create([
                'name' => $request->name,
                'data' => json_encode($request->dataList),
                'status' => 1,
            ]);

            return $this->returnSuccess(200, 'ok');

        } catch (\Exception $e) {
            return $this->returnFail(500, 'Error al crear el área y sus reglas: '.$e->getMessage());
        }
    }

    public function show($id)
    {
        $payMethod = PayMethod::find($id);

        if (! $payMethod) {
            return $this->returnFail(404, 'Método de pago no encontrado');
        }

        // Convertimos el string JSON a un arreglo para enviarlo limpio al frontend
        $payMethod->dataList = json_decode($payMethod->data);

        return $this->returnSuccess(200, $payMethod);
    }

    public function update(Request $request, $id)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

        // Validaciones en backend
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'dataList' => 'required|array|min:1',
            'dataList.*.title' => 'required|string',
            'dataList.*.data' => 'required|string',
            'status' => 'nullable|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(400, $validator->errors()->first());
        }

        try {
            $payMethod = PayMethod::find($id);

            if (! $payMethod) {
                return $this->returnFail(404, 'Método de pago no encontrado');
            }

            $payMethod->update([
                'name' => $request->name,
                'data' => json_encode($request->dataList), // Volvemos a guardar como string
                'status' => $request->has('status') ? $request->status : $payMethod->status,
            ]);

            return $this->returnSuccess(200, 'Método de pago actualizado exitosamente');
        } catch (\Exception $e) {
            return $this->returnFail(500, 'Error al actualizar el método de pago: '.$e->getMessage());
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

        // Validaciones en backend
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|integer|in:0,1',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(400, $validator->errors()->first());
        }

        try {
            $payMethod = PayMethod::find($id);

            if (! $payMethod) {
                return $this->returnFail(404, 'Método de pago no encontrado');
            }

            $payMethod->update([
                'status' => $request->has('status') ? $request->status : $payMethod->status,
            ]);

            return $this->returnSuccess(200, 'Método de pago actualizado exitosamente');
        } catch (\Exception $e) {
            return $this->returnFail(500, 'Error al actualizar el método de pago: '.$e->getMessage());
        }
    }

    public function formattedData($MethodsData)
    {
        foreach ($MethodsData as $key) {
            $key->data = json_decode($key->data);
        }

        return $MethodsData;
    }
}
