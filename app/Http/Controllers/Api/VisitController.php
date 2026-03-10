<?php

namespace App\Http\Controllers\Api;

use App\Models\Visit;
use App\Models\PeoplesXDepartaments;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class VisitController extends Controller
{
    /**
     * Obtiene las visitas registradas en los departamentos del usuario (como propietario o residente).
     */
    public function getVisitsByUser(Request $request)
    {
        $user = $request->user();
        $ownedIds = $user->apartments()->pluck('id');
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
                    'departament'   => $visit->departament,
                    'created_at'    => $visit->created_at,
                ];
            });

        return $this->returnSuccess(200, $visits);
    }

    private function getTypeLabel(int $type): string
    {
        $labels = [
            1 => 'Personal',
            2 => 'Entrega',
            3 => 'Servicio',
            4 => 'Otro',
        ];
        return $labels[$type] ?? 'Visita';
    }
}
