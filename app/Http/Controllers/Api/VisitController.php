<?php

namespace App\Http\Controllers\Api;

use App\Models\Visit;
use App\Models\PeoplesXDepartaments;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\AirbnbRent;
use App\Models\Departament;
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
        $search = trim((string) $request->query('search', ''));
        $statusFilters = $this->parseStatusFilters($request->query('status', []));
        $departamentId = $request->query('departament_id');
        $ownedIds = $user->apartaments()->pluck('id');
        $residentIds = PeoplesXDepartaments::where('user_id', $user->id)->pluck('departament_id');
        $apartmentIds = $ownedIds->merge($residentIds)->unique()->values();

        $visitsQuery = Visit::with('departament')
            ->whereIn('departament_id', $apartmentIds)
            ->when($departamentId, function ($query) use ($departamentId) {
                $query->where('departament_id', (int) $departamentId);
            })
            ->when(count($statusFilters) > 0, function ($query) use ($statusFilters) {
                $query->whereIn('status', $statusFilters);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('fullname', 'like', "%{$search}%")
                        ->orWhere('dni', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('departament', function ($departamentQuery) use ($search) {
                            $departamentQuery->where('number', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $visits = $visitsQuery
            ->map(function ($visit) {
                return [
                    'id'            => $visit->id,
                    'fullname'      => $visit->fullname,
                    'dni'           => $visit->dni,
                    'type'          => $visit->type,
                    'type_label'    => $visit->type_label,
                    'description'   => $visit->description,
                    'date'          => $visit->date,
                    'hour'          => $visit->hour,
                    'departament'   => $visit->departament,
                    'created_at'    => $visit->created_at,
                    'status_label'  => $visit->status_label,
                    'status_color'  => $visit->status_color,
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

    /**
     * Lista de visitas para conserje/seguridad: pendientes de llegada.
     * Restringido por middleware `role:trabajador`.
     */
    public function getVisitsForSecurity(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $statusFilters = $this->parseStatusFilters($request->query('status', []));
        $departamentId = $request->query('departament_id');

        $visits = Visit::with(['departament', 'airbnb'])
            ->where('status', 1) // Pendiente de llegada
            ->where('airbnb_rent_id', null)
            ->when($departamentId, function ($query) use ($departamentId) {
                $query->where('departament_id', (int) $departamentId);
            })
            ->when(count($statusFilters) > 0, function ($query) use ($statusFilters) {
                $query->whereIn('status', $statusFilters);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('fullname', 'like', "%{$search}%")
                        ->orWhere('dni', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('departament', function ($departamentQuery) use ($search) {
                            $departamentQuery->where('number', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('date', 'asc')
            ->orderBy('hour', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->returnSuccess(200, $visits);
    }
    public function getAirbnbForSecurity(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $statusFilters = $this->parseStatusFilters($request->query('status', []));
        $departamentId = $request->query('departament_id');

        $visits = AirbnbRent::with(['guest', 'departament', 'user', 'creator'])
            ->where('status', 1) // Pendiente de llegada
            ->when($departamentId, function ($query) use ($departamentId) {
                $query->where('departament_id', (int) $departamentId);
            })
            ->when(count($statusFilters) > 0, function ($query) use ($statusFilters) {
                $query->whereIn('status', $statusFilters);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name_to', 'like', "%{$search}%")
                        ->orWhere('quantity', 'like', "%{$search}%")
                        ->orWhereHas('departament', function ($departamentQuery) use ($search) {
                            $departamentQuery->where('number', 'like', "%{$search}%");
                        })
                        ->orWhereHas('guest', function ($guestQuery) use ($search) {
                            $guestQuery->where('fullname', 'like', "%{$search}%")
                                ->orWhere('dni', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('init_day', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->returnSuccess(200, $visits);
    }

    public function getVisitFilterOptionsByUser(Request $request)
    {
        $user = $request->user();
        $ownedIds = $user->apartaments()->pluck('id');
        $residentIds = PeoplesXDepartaments::where('user_id', $user->id)->pluck('departament_id');
        $apartmentIds = $ownedIds->merge($residentIds)->unique()->values();

        $apartments = Departament::query()
            ->whereIn('id', $apartmentIds)
            ->whereHas('visits')
            ->orderBy('number', 'asc')
            ->get(['id', 'number'])
            ->map(function ($departament) {
                return [
                    'label' => 'Apt. #' . $departament->number,
                    'value' => $departament->id,
                ];
            })
            ->values();

        return $this->returnSuccess(200, [
            'statuses' => $this->getVisitStatusOptions(),
            'apartments' => $apartments,
        ]);
    }

    public function getVisitFilterOptionsForSecurity(Request $request)
    {
        $apartments = Departament::query()
            ->whereHas('visits', function ($query) {
                $query->whereNull('airbnb_rent_id');
            })
            ->orderBy('number', 'asc')
            ->get(['id', 'number'])
            ->map(function ($departament) {
                return [
                    'label' => 'Apt. #' . $departament->number,
                    'value' => $departament->id,
                ];
            })
            ->values();

        return $this->returnSuccess(200, [
            'statuses' => $this->getVisitStatusOptions(),
            'apartments' => $apartments,
        ]);
    }

    public function getAirbnbFilterOptionsForSecurity(Request $request)
    {
        $apartments = Departament::query()
            ->whereHas('visits', function ($query) {
                $query->whereNotNull('airbnb_rent_id');
            })
            ->orderBy('number', 'asc')
            ->get(['id', 'number'])
            ->map(function ($departament) {
                return [
                    'label' => 'Apt. #' . $departament->number,
                    'value' => $departament->id,
                ];
            })
            ->values();

        return $this->returnSuccess(200, [
            'statuses' => [
                ['label' => 'Cancelada', 'value' => 0],
                ['label' => 'Pendiente de llegada', 'value' => 1],
                ['label' => 'Completada', 'value' => 2],
            ],
            'apartments' => $apartments,
        ]);
    }

    /**
     * Marca una visita como llegada confirmada (status = 2).
     * Restringido por middleware `role:trabajador`.
     */
    public function markVisitArrived(Request $request, int $id)
    {
        $visit = Visit::find($id);
        if (! $visit) {
            return $this->returnFail(404, 'Visita no encontrada');
        }

        if ((int) $visit->status === 2) {
            return $this->returnSuccess(200, [
                'message' => 'La visita ya está marcada como llegada',
                'id' => $visit->id,
                'status' => $visit->status,
                'status_label' => $visit->status_label,
                'status_color' => $visit->status_color,
            ]);
        }

        try {
            $visit->status = 2;
            $visit->save();
        } catch (Exception $e) {
            return $this->returnFail(500, $e->getMessage());
        }

        return $this->returnSuccess(200, [
            'message' => 'Llegada confirmada',
            'id' => $visit->id,
            'status' => $visit->status,
            'status_label' => $visit->status_label,
            'status_color' => $visit->status_color,
        ]);
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

    private function parseStatusFilters($statusInput): array
    {
        if (is_string($statusInput) && $statusInput !== '') {
            $statusInput = explode(',', $statusInput);
        }

        if (!is_array($statusInput)) {
            return [];
        }

        return collect($statusInput)
            ->filter(function ($value) {
                return $value !== '' && $value !== null;
            })
            ->map(function ($value) {
                return (int) $value;
            })
            ->unique()
            ->values()
            ->all();
    }

    private function getVisitStatusOptions(): array
    {
        return [
            ['label' => 'Cancelada', 'value' => 0],
            ['label' => 'Pendiente de llegada', 'value' => 1],
            ['label' => 'Llegada confirmada', 'value' => 2],
        ];
    }
}
