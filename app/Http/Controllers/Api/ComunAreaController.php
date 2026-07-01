<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ComunArea;
use App\Models\ComunAreaSchedule;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ComunAreaController extends Controller
{
    public function paginationAreas(Request $request)
    {
        $comunAreas = ComunArea::withCount(["bookings", "bookingsToValidate", "schedules"])->orderBy('name', 'asc')->paginate(40);
        return $this->returnSuccess(200, $comunAreas);
    }

    public function getAll()
    {
        $comunAreas = ComunArea::with(['rulesArea', 'schedules'])->orderBy('name', 'asc')->get();
        return $this->returnSuccess(200, $comunAreas);
    }

    public function comunAreaById($id)
    {
        $area = ComunArea::with(['rulesArea', 'schedules'])->find($id);
        return $this->returnSuccess(200, $area);
    }

    public function storeArea(Request $request)
    {
        $validated = $this->validateFieldsFromInput($request->all());
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }
        DB::beginTransaction();
        try {
            $area = ComunArea::create([
                'name' => $request->name,
                'capacity' => $request->capacity,
                'price' => $request->price,
                'warranty_price' => $request->warrantyPrice,
                'description'  => $request->description,
                'max_time_reserve' => $request->maxTime,
                'max_time_reserve_exclusive' => $request->maxTimeExclusive,
                'icon' => $request->imageIcon,
                'max_cupo' => $request->max_cupo,
                'type' => $request->type ? (is_array($request->type) ? $request->type['value'] : $request->type) : 1,
            ]);

            if ($request->has('rulesList') && is_array($request->rulesList)) {
                $this->createRulesForArea($area, $request->rulesList);
            }

            // NUEVO: Sincronizar horarios
            if ($request->has('schedules') && is_array($request->schedules)) {
                $this->syncSchedules($area, $request->schedules);
            }

            DB::commit();
            return $this->returnSuccess(200, 'ok');
        } catch (Exception $e) {
            DB::rollBack();
            return $this->returnFail(500, 'Error al crear el área: ' . $e->getMessage());
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

            $area->update([
                'name' => $request->name ?? $area->name,
                'capacity' => $request->capacity ?? $area->capacity,
                'price' => $request->price ?? $area->price,
                'warranty_price' => $request->warrantyPrice ?? $area->warranty_price,
                'description'  => $request->description ?? $area->description,
                'max_time_reserve' => $request->maxTime ?? $area->max_time_reserve,
                'max_time_reserve_exclusive' => $request->maxTimeExclusive ?? $area->max_time_reserve_exclusive,
                'max_cupo' => $request->max_cupo ?? $area->max_cupo,
                'icon' => $request->icon ? (is_array($request->icon) ? $request->icon['value'] : $request->icon) : $area->icon,
                'type' => $request->type ? (is_array($request->type) ? $request->type['value'] : $request->type) : $area->type,
            ]);

            if ($request->has('rulesList') && is_array($request->rulesList)) {
                $this->syncRulesForArea($area, $request->rulesList);
            }
            if ($request->has('schedules') && is_array($request->schedules)) {
                $this->syncSchedules($area, $request->schedules);
            }

            DB::commit();
            return $this->returnSuccess(200, 'ok');
        } catch (Exception $e) {
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
    private function syncSchedules(ComunArea $area, array $schedules)
    {
        $area->schedules()->delete();
        $insertData = [];

        foreach ($schedules as $schedule) {
            if (isset($schedule['isOpen']) && $schedule['isOpen']) {
                foreach ($schedule['intervals'] as $interval) {
                    if (!empty($interval['from']) && !empty($interval['to'])) {
                        $insertData[] = [
                            'comun_area_id' => $area->id,
                            'day' => $schedule['day'],
                            'time_from' => $interval['from'],
                            'time_to' => $interval['to'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }
                }
            }
        }

        if (count($insertData) > 0) {
            ComunAreaSchedule::insert($insertData);
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
            'max_cupo'      => ['nullable', 'numeric'],
            'notAvailable'  => ['nullable', 'array'],
            'icon'          => ['nullable'],
            'rulesList'                  => ['nullable', 'array'],
            'rulesList.*.title'          => ['required_with:rulesList', 'string'],
            'rulesList.*.type.value'     => ['required_with:rulesList', 'integer'],
            'rulesList.*.severity.value' => ['required_with:rulesList', 'integer'],
            'rulesList.*.suggest_amount' => ['nullable', 'numeric'],
            'rulesList.*.id' => ['nullable', 'integer'],
            'schedules' => ['required', 'array'],
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
