<?php

namespace App\Http\Controllers\Api;

use App\Models\ComunArea;
// Importamos la fachada DB para las transacciones
use Illuminate\Support\Facades\DB; 
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class ComunAreaController extends Controller
{
    public function paginationAreas(Request $request)
    {
        $comunAreas = ComunArea::withCount(["bookings", "bookingsToValidate"])->orderBy('name', 'asc')->paginate(40);
        return $this->returnSuccess(200, $comunAreas);
    }

    public function getAll()
    {
        $comunAreas = ComunArea::with(['rulesArea'])->orderBy('name', 'asc')->get();
        return $this->returnSuccess(200, $comunAreas);
    }

    public function comunAreaById($id)
    {
        $area = ComunArea::with(['rulesArea'])->find($id);
        return $this->returnSuccess(200, $area);
    }

    public function storeArea(Request $request)
    {
        $validated = $this->validateFieldsFromInput($request->all());
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }

        // Iniciamos la transacción
        DB::beginTransaction();

        try {
            // 1. Crear el Área Común
            $area = ComunArea::create([
                'name' => $request->name,
                'capacity' => $request->capacity,
                'price' => $request->price,
                'warranty_price' => $request->warrantyPrice,
                'description'  => $request->description,
                'max_time_reserve' => $request->maxTime,
                'timeFrom' => $request->timeFrom,
                'timeTo' => $request->timeTo,
                'icon' => $request->imageIcon,
                'max_cupo' => $request->max_cupo,
                'max_cupo' => $request->max_cupo,
                // Si viene notAvailable lo pasamos a JSON, sino dejamos el que estaba
                'not_available_days' => $request->has('notAvailable') ? json_encode($request->notAvailable) : null,
                'type' => $request->type ? (is_array($request->type) ? $request->type['value'] : $request->type) : 1,
           
                // Se removió 'rules' de aquí ya que ahora tendrán su propia tabla
            ]);

            // 2. Verificar si viene el array de reglas y pasarlo a la nueva función
            if ($request->has('rulesList') && is_array($request->rulesList)) {
                $this->createRulesForArea($area, $request->rulesList);
            }

            // Si todo salió bien, guardamos los cambios en la BD
            DB::commit();

            return $this->returnSuccess(200, 'ok');

        } catch (\Exception $e) {
            // Si hay algún error, revertimos todo
            DB::rollBack();
            return $this->returnFail(500, 'Error al crear el área y sus reglas: ' . $e->getMessage());
        }
    }

    /**
     * Función separada para procesar y crear las reglas del área común
     */
    private function createRulesForArea(ComunArea $area, array $rulesList)
    {
        foreach ($rulesList as $ruleData) {
            // En tu Vue, type y severity son objetos { value: X, name: 'Y' }
            // Extraemos solo el 'value' para guardarlo en la base de datos
            $typeValue = is_array($ruleData['type']) ? $ruleData['type']['value'] : $ruleData['type'];
            $severityValue = is_array($ruleData['severity']) ? $ruleData['severity']['value'] : $ruleData['severity'];

            // Usamos la relación rulesArea() de tu modelo ComunArea para crear la regla
            // Esto automáticamente asigna el comun_area_id a la regla
            $area->rulesArea()->create([
                'code' => $ruleData['code'] ?? '',
                'title' => $ruleData['title'] ?? '',
                'description' => $ruleData['description'] ?? '',
                'type' => $typeValue,
                'severity' => $severityValue,
                'suggest_amount' => $ruleData['suggest_amount'] ?? null,
                'active' => 1, // Por defecto, la regla nace activa
                'punish' => '', // Valor vacío por defecto ya que no viene desde el frontend
            ]);
        }
    }

    public function updateArea(Request $request, $id)
    {
        $validated = $this->validateFieldsFromInput($request->all());
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }

        DB::beginTransaction();

        try {
            $area = ComunArea::find($id);

            if (!$area) {
                return $this->returnFail(400, 'Area común no encontrada');
            }

            // Actualizamos los datos principales del área
            $area->update([
                'name' => $request->name ?? $area->name,
                'capacity' => $request->capacity ?? $area->capacity,
                'price' => $request->price ?? $area->price,
                'warranty_price' => $request->warrantyPrice ?? $area->warranty_price,
                'description'  => $request->description ?? $area->description,
                'max_time_reserve' => $request->maxTime ?? $area->max_time_reserve,
                'timeFrom' => $request->timeFrom ?? $area->timeFrom,
                'timeTo' => $request->timeTo ?? $area->timeTo,
                'max_cupo' => $request->max_cupo ?? $area->max_cupo,
                // Si viene notAvailable lo pasamos a JSON, sino dejamos el que estaba
                'not_available_days' => $request->has('notAvailable') ? json_encode($request->notAvailable) : $area->not_available_days,
                // Extraemos el valor del objeto select para icon y type
                'icon' => $request->icon ? (is_array($request->icon) ? $request->icon['value'] : $request->icon) : $area->icon,
                'type' => $request->type ? (is_array($request->type) ? $request->type['value'] : $request->type) : $area->type,
           
            ]);

            // Sincronizamos las reglas
            if ($request->has('rulesList') && is_array($request->rulesList)) {
                $this->syncRulesForArea($area, $request->rulesList);
            }

            DB::commit();
            return $this->returnSuccess(200, 'ok');

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->returnFail(500, 'Error al actualizar: ' . $e->getMessage());
        }
    }

    /**
     * Función para actualizar, crear y eliminar reglas dinámicamente
     */
    private function syncRulesForArea(ComunArea $area, array $rulesList)
    {
        // 1. Extraemos todos los IDs de las reglas que vienen del frontend (ignorando los nulos)
        $incomingRuleIds = collect($rulesList)->pluck('id')->filter()->toArray();

        // 2. Eliminamos las reglas de la BD que pertenecen a este área pero que YA NO vienen en el frontend
        $area->rulesArea()->whereNotIn('id', $incomingRuleIds)->delete();

        // 3. Iteramos para Crear o Actualizar
        foreach ($rulesList as $ruleData) {
            $typeValue = is_array($ruleData['type']) ? $ruleData['type']['value'] : $ruleData['type'];
            $severityValue = is_array($ruleData['severity']) ? $ruleData['severity']['value'] : $ruleData['severity'];

            if (isset($ruleData['id']) && !empty($ruleData['id'])) {
                // Si tiene ID, buscamos la regla y la actualizamos
                $rule = $area->rulesArea()->find($ruleData['id']);
                if ($rule) {
                    $rule->update([
                        'title' => $ruleData['title'],
                        'description' => $ruleData['description'] ?? '',
                        'type' => $typeValue,
                        'severity' => $severityValue,
                        'suggest_amount' => $ruleData['suggest_amount'] ?? null,
                    ]);
                }
            } else {
                // Si no tiene ID, es una regla nueva añadida desde el botón "Agregar regla"
                $area->rulesArea()->create([
                    'code' => $ruleData['code'] ?? '',
                    'title' => $ruleData['title'],
                    'description' => $ruleData['description'] ?? '',
                    'type' => $typeValue,
                    'severity' => $severityValue,
                    'suggest_amount' => $ruleData['suggest_amount'] ?? null,
                    'active' => 1,
                    'punish' => '',
                ]);
            }
        }
    }

    public function deleteArea($id)
    {
        $area = ComunArea::find($id);
        if (!$area) {
            return $this->returnFail(400, 'Area común no encontrada');
        }
        $area->delete();
        return $this->returnSuccess(200, 'ok');
    }

    private function validateFieldsFromInput($inputs)
    {
        $rules = [
            // Campos originales
            'name'          => ['required', 'regex:/^[a-z 0-9 A-Z-À-ÿ .\-]+$/i'],
            'capacity'      => ['required', 'numeric'],
            'price'         => ['required', 'numeric'],
            'warrantyPrice' => ['required', 'numeric'],
            'description'   => ['nullable',],
            'maxTime'       => ['required', 'numeric'],
            'timeFrom'      => ['required'],
            'timeTo'        => ['required'],
            'max_cupo'      => ['nullable', 'numeric'],
            'notAvailable'  => ['nullable', 'array'],
            'icon'          => ['nullable'],
            'rulesList'                  => ['nullable', 'array'],
            'rulesList.*.title'          => ['required_with:rulesList', 'string'],
            'rulesList.*.type.value'     => ['required_with:rulesList', 'integer'],
            'rulesList.*.severity.value' => ['required_with:rulesList', 'integer'],
            'rulesList.*.suggest_amount' => ['nullable', 'numeric'],
            'rulesList.*.id' => ['nullable', 'integer'],
        ];

        $messages = [
            'name.required'          => 'Nombre del area es requerido.',
            'name.regex'             => 'Nombre no valido.',
            'capacity.required'      => 'Capacidad es requerida.',
            'capacity.numeric'       => 'Capacidad no valida.',
            'price.required'         => 'Precio es requerido.',
            'price.numeric'          => 'Precio no valido.',
            'warrantyPrice.required' => 'Garantia es requerida.',
            'warrantyPrice.numeric'  => 'Garantia no valida.',
            'maxTime.required'       => 'Maximo de tiempo de reserva es requerido.',
            'maxTime.numeric'        => 'Formato de tiempo de reserva invalido.',
            'timeFrom.required'      => 'Horario de apertura es requerido.',
            'timeTo.required'        => 'Horario de cierre es requerido.',
            'max_cupo.numeric'       => 'El cupo maximo debe ser numérico.',
            'notAvailable.array'     => 'Formato de dias no disponibles invalido.',
            'rulesList.array'                  => 'El formato de la lista de reglas es inválido.',
            'rulesList.*.title.required_with'  => 'Todas las reglas añadidas deben tener un título.',
            'rulesList.*.type.value.required_with'     => 'Debes seleccionar el tipo para todas las reglas.',
            'rulesList.*.severity.value.required_with' => 'Debes seleccionar la severidad para todas las reglas.',
            'rulesList.*.suggest_amount.numeric'       => 'El monto de amonestación ingresado debe ser un número válido.',
            'rulesList.*.id.integer'                   => 'Esta regla no existe'
        ];

        $validator = Validator::make($inputs, $rules, $messages)->errors();
        return $validator->all();
    }
}