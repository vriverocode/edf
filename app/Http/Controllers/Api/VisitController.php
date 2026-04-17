<?php

namespace App\Http\Controllers\Api;

use App\Models\Visit;
use App\Models\PeoplesXDepartaments;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Support\Facades\Validator;

class VisitController extends Controller
{
    /**
     * Obtiene las visitas registradas en los departamentos del usuario (como propietario o residente).
     */
    public function getVisitsByUser(Request $request)
    {
        $user = $request->user();
        $ownedIds = $user->apartaments()->pluck('id');
        $residentIds = PeoplesXDepartaments::where('user_id', $user->id)->pluck('departament_id');
        $apartmentIds = $ownedIds->merge($residentIds)->unique()->values();

        $visits = Visit::with('departament')
            ->whereIn('departament_id', $apartmentIds)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($visit) {
                return [
                    'id'            => $visit->id,
                    'fullname'      => $visit->fullname,
                    'dni'           => $visit->dni,
                    'type'          => $visit->type,
                    'type_label'    => $this->getTypeLabel($visit->type),
                    'description'   => $visit->description,
                    'date'          => $visit->date,
                    'hour'          => $visit->hour,
                    'departament'   => $visit->departament,
                    'created_at'    => $visit->created_at,
                ];
            });

        return $this->returnSuccess(200, $visits);
    }

    public function storeVisit(Request $request)
    {
        $validated = $this->validateFieldsFromInput($request->all());
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }

        $user = $request->user();
        $ownedIds = $user->apartaments()->pluck('id');
        $residentIds = PeoplesXDepartaments::where('user_id', $user->id)->pluck('departament_id');
        $apartmentIds = $ownedIds->merge($residentIds)->unique()->values();

        if (!$apartmentIds->contains((int)$request->departament_id)) {
            return $this->returnFail(403, 'No puedes registrar visitas para este apartamento');
        }

        try {
            $visit = Visit::create([
                'departament_id' => $request->departament_id,
                'fullname'       => $request->fullname,
                'dni'            => $request->dni,
                'type'           => (int)$request->type,
                'description'    => $request->description,
                'date'           => date('Y-m-d', strtotime($request->date)),
                'hour'           => $request->hour,
            ]);
        } catch (Exception $e) {
            return $this->returnFail(500, $e->getMessage());
        }

        return $this->returnSuccess(200, [
            'message' => 'Visita registrada con éxito',
            'id'      => $visit->id,
        ]);

    }

    private function getTypeLabel(int $type): string
    {
        $labels = [
            1 => 'Personal',
            2 => 'Entrega',
            3 => 'Servicio',
            4 => 'AirBnb',
            5 => 'Otro',
        ];
        return $labels[$type] ?? 'Visita';
    }

    private function validateFieldsFromInput($inputs)
    {
        $rules = [
            'departament_id' => ['required', 'numeric'],
            'fullname'       => ['required', 'regex:/^[a-zA-ZÀ-ÿ0-9 .\-]+$/u'],
            'dni'            => ['required', 'regex:/^[0-9A-Za-z.\-]+$/'],
            'type'           => ['required', 'numeric', 'between:1,4'],
            'date'           => ['required', 'date'],
            'hour'           => ['nullable', 'regex:/^[0-9]{2}:[0-9]{2}$/'],
            'description'    => ['nullable', 'string'],
        ];

        $messages = [
            'departament_id.required' => 'El apartamento es requerido',
            'departament_id.numeric'  => 'El apartamento no es válido',
            'fullname.required'       => 'El nombre del visitante es requerido',
            'fullname.regex'          => 'El nombre del visitante no es válido',
            'dni.required'            => 'El documento de identidad es requerido',
            'dni.regex'               => 'El documento de identidad no es válido',
            'type.required'           => 'El tipo de visita es requerido',
            'type.numeric'            => 'El tipo de visita no es válido',
            'type.between'            => 'El tipo de visita no es válido',
            'date.required'           => 'La fecha de la visita es requerida',
            'date.date'               => 'La fecha de la visita no es válida',
            'hour.regex'              => 'La hora de llegada no es válida',
        ];

        $validator = Validator::make($inputs, $rules, $messages)->errors();

        return $validator->all();
    }
}
