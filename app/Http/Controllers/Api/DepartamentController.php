<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Departament;
use App\Models\Rol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DepartamentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function paginationApartment(Request $request)
    {
        // Por defecto busca tipo 1 (Departamentos)
        $type = $request->input('type', 1);
        $search = trim((string) $request->query('search', ''));
        $number = trim((string) $request->query('number', ''));

        $departaments = Departament::with('owner')
            ->where('type', $type)
            ->when($number !== '', fn ($q) => $q->where('number', 'like', "%{$number}%"))
            ->when($search !== '', fn ($q) => $q->whereHas('owner', fn ($o) => $o->where('name', 'like', "%{$search}%")))
            ->paginate(15);

        return $this->returnSuccess(200, $departaments);
    }

    /**
     * Display a listing of the resource.
     */
    public function apartmentsByfind(Request $request)
    {
        $departaments = Departament::with('owner');
        if ($request->find == 'available') {
            $departaments->where('user_id', null);
            if ($request->has('type')) {
                $departaments->where('type', $request->type);
            }
        }
        if ($request->find == 'allWithUser') {
            $departaments->where('user_id', '!=', null);
        }
        if ($request->find == 'allDepartmentWithoutReadingThisMonth') {
            $departaments->where('type', 1)->with(['waterReadings'])->whereDoesntHave('waterReadings');
        }

        return $this->returnSuccess(200, $departaments->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function storeApartment(Request $request)
    {
        $validated = $this->validateFieldsFromInput($request->all());
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }

        Departament::create([
            'number' => $request->number,
            'address' => $request->address,
            'block' => $request->block,
            'area' => $request->area ?? 0, // Si es nulo, guardamos 0
            'floor' => $request->floor ?? 'N/A', // Si es nulo
            'description' => $request->description,
            'participation_percentage' => $request->participation_percentage,
            'type' => $request->type ?? 1, // Nuevo campo
        ]);

        return $this->returnSuccess(200, 'Creado con éxito');
    }

    public function getApartmentById($id)
    {
        $apartments = Departament::with(['owner'])->find($id);

        if (! $apartments) {
            return $this->returnFail(400, 'Departamento no encontrado');
        }

        return $this->returnSuccess(200, $apartments);
    }

    public function assingApartment(Request $request)
    {
        $user = $request->user();
        if ($user->rol_id !== Rol::ADMIN) {
            return $this->returnFail(403, 'Solo los administradores pueden asignar departamentos');
        }

        $departament = Departament::find($request->idApartament);
        if (! $departament) {
            return $this->returnFail(404, 'Departamento no encontrado');
        }

        $departament->update([
            'user_id' => $request->user,
        ]);

        return $this->returnSuccess(200, 'Asignado con éxito');
    }

    public function getApartmentsByUser(Request $request)
    {
        $apartments = Departament::with(['owner', 'dueQuotas'])->where('user_id', $request->user()->id)->get();

        if (! $apartments) {
            return $this->returnFail(400, 'Departamentos no encontrados');
        }

        return $this->returnSuccess(200, $apartments);
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateApartment(Request $request, $id)
    {
        $validated = $this->validateFieldsFromInput($request->all());
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }

        $apartment = Departament::find($id);
        if (! $apartment) {
            return $this->returnFail(404, 'No encontrado');
        }

        if (! $this->verifyApartmentOwnership($apartment)) {
            return $this->returnFail(403, 'No tienes permiso para modificar este departamento');
        }

        $apartment->update([
            'number' => $request->number,
            'address' => $request->address,
            'block' => $request->block,
            'area' => $request->area,
            'floor' => $request->floor,
            'description' => $request->description,
            'participation_percentage' => $request->participation_percentage,
            'type' => $request->type,
            'tenant_pays_quota' => $request->boolean('tenant_pays_quota'),
        ]);

        return $this->returnSuccess(200, $apartment);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Departament $departament)
    {
        //
    }

    public function getInhabitedDepartments(Request $request)
    {
        $departments = Departament::with('owner')
            ->withCount('peoples')
            ->where('type', Departament::TYPE_DEPARTAMENTO)
            ->whereNotNull('user_id');

        if ($request->filled('number')) {
            $departments->where('number', 'like', "%{$request->number}%");
        }

        if ($request->filled('name')) {
            $departments->whereHas('owner', fn ($q) => $q->where('name', 'like', "%{$request->name}%"));
        }

        $departments = $departments->paginate(15);

        return $this->returnSuccess(200, $departments);
    }

    public function getDepartmentResidents(Request $request, $id)
    {
        $department = Departament::with(['owner', 'peoples.user'])->find($id);
        if (! $department) {
            return $this->returnFail(404, 'Departamento no encontrado');
        }

        $residents = [];
        // Add owner
        if ($department->owner) {
            $ownerData = $department->owner->toArray();
            $ownerData['type_label'] = 'Propietario';
            $ownerData['type_id'] = Rol::PROPIETARIO;
            $residents[] = $ownerData;
        }

        // Add other residents
        foreach ($department->peoples as $people) {
            if ($people->user) {
                // Evitar duplicar al propietario si estuviera en la tabla por alguna razon
                if ($department->user_id == $people->user_id && $people->type == Rol::PROPIETARIO) {
                    continue;
                }

                $userData = $people->user->toArray();
                $typeLabel = 'Residente';
                if ($people->type == Rol::AIRBNB) {
                    $typeLabel = 'Airbnb';
                } elseif ($people->type == Rol::PROPIETARIO) {
                    $typeLabel = 'Propietario';
                }
                $userData['type_label'] = $typeLabel;
                $userData['type_id'] = $people->type;
                $residents[] = $userData;
            }
        }

        return $this->returnSuccess(200, $residents);
    }

    private function verifyApartmentOwnership($departament): bool
    {
        $user = request()->user();

        return $user->rol_id === Rol::ADMIN || $departament->user_id === $user->id;
    }

    private function validateFieldsFromInput($inputs)
    {
        $isDepartment = isset($inputs['type']) && $inputs['type'] == 1;

        $rules = [
            'type' => ['required', 'in:1,2,3'],
            'number' => ['required', 'regex:/^[a-z 0-9 A-Z-À-ÿ .\\-]+$/i'],
            'participation_percentage' => ['required', 'numeric'],
            'description' => ['nullable', 'regex:/^[a-z a-z 0-9 A-Z-À-ÿ ., \\-]+$/i'],
        ];

        // Validaciones estrictas solo si es Departamento
        if ($isDepartment) {
            $rules['address'] = ['required', 'regex:/^[a-z 0-9 A-Z-À-ÿ .,# &]+$/i'];
            $rules['area'] = ['required', 'numeric'];
            $rules['floor'] = ['required'];
            $rules['block'] = ['nullable', 'regex:/^[a-z 0-9 A-Z À-ÿ .]+$/i'];
        } else {
            $rules['address'] = ['nullable'];
            $rules['area'] = ['nullable'];
            $rules['floor'] = ['nullable'];
            $rules['block'] = ['nullable'];
        }

        $validator = Validator::make($inputs, $rules);

        return $validator->errors()->all();
    }
}
