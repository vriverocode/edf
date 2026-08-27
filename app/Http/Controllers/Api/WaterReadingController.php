<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MonthlyBills;
use App\Models\Rol;
use App\Models\WaterReading;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WaterReadingController extends Controller
{
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'month' => ['required', 'integer', 'between:1,12'],
                'year' => ['nullable', 'integer'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
                'is_common' => ['nullable', 'boolean'],
            ]);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $month = (int) $validated['month'];
        $year = (int) ($validated['year'] ?? now()->year);
        $perPage = (int) ($validated['per_page'] ?? 12);

        $query = WaterReading::with(['departament.owner'])
            ->where('month', $month)
            ->where('year', $year)
            ->orderBy('is_common', 'asc')
            ->orderBy('departament_id', 'asc');

        if (isset($validated['is_common'])) {
            $query->where('is_common', (bool) $validated['is_common']);
        }

        $paginator = $query->paginate($perPage);

        $availableYears = WaterReading::query()
            ->select('year')
            ->distinct()
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->values();

        return $this->returnSuccess(200, [
            'pagination' => $paginator,
            'available_years' => $availableYears,
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
        ]);
    }

    public function show(int $id)
    {
        $reading = WaterReading::with(['departament.owner'])->find($id);
        if (! $reading) {
            return $this->returnFail(404, 'Medición de agua no encontrada');
        }

        return $this->returnSuccess(200, $reading);
    }

    public function getLastByDepartment(int $departmentId)
    {
        $reading = WaterReading::where('departament_id', $departmentId)
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->first();

        if (! $reading) {
            return $this->returnSuccess(200, null);
        }

        return $this->returnSuccess(200, $reading);
    }

    public function consumptionByMonth(Request $request)
    {
        try {
            $validated = $request->validate([
                'month' => ['required', 'integer', 'between:1,12'],
                'year' => ['required', 'integer'],
            ]);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];

        $readings = WaterReading::with('departament')
            ->where('month', $month)
            ->where('year', $year)
            ->orderBy('is_common', 'asc')
            ->orderBy('departament_id', 'asc')
            ->get()
            ->map(function ($reading) {
                return [
                    'department_name' => $reading->is_common ? 'Área Común' : ($reading->departament->nombre ?? 'Sin departamento'),
                    'previous_reading' => (float) $reading->previous_reading,
                    'current_reading' => (float) $reading->current_reading,
                    'consumption' => $reading->consumption,
                    'is_common' => (bool) $reading->is_common,
                ];
            });

        $totalConsumption = round($readings->sum('consumption'), 3);

        return $this->returnSuccess(200, [
            'readings' => $readings,
            'total_consumption' => $totalConsumption,
        ]);
    }

    public function store(Request $request)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

        $isCommon = (bool) $request->input('is_common', false);
        $isInitial = (bool) $request->input('is_initial', false);

        $priceWater = MonthlyBills::where('month', $request->input('month'))->where('year', $request->input('year'))->exists()
            ? MonthlyBills::where('month', $request->input('month'))->where('year', $request->input('year'))->first()
            : 0;

        try {
            $rules = [
                'month' => ['required', 'integer', 'between:1,12'],
                'year' => ['required', 'integer'],
                'previous_reading' => $isInitial ? ['nullable', 'numeric', 'min:0'] : ['required', 'numeric', 'min:0'],
                'current_reading' => ['required', 'numeric', 'gt:previous_reading'],
                'm3_price' => ['nullable', 'numeric', 'min:0'],
                'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'is_common' => ['nullable', 'boolean'],
            ];

            $messages = [
                'month.required' => 'El mes es requerido.',
                'month.between' => 'El mes debe estar entre 1 y 12.',
                'year.required' => 'El año es requerido.',
                'previous_reading.required' => 'La lectura anterior es requerida.',
                'current_reading.required' => 'La lectura actual es requerida.',
                'current_reading.gt' => 'La lectura actual debe ser mayor a la lectura anterior.',
                'photo.required' => 'La foto comprobante del medidor es requerida.',
                'photo.image' => 'El archivo debe ser una imagen válida.',
            ];

            if ($isCommon) {
                $rules['departament_id'] = ['nullable'];
                $rules[Rule::unique('water_readings')->where(function ($query) use ($request) {
                    return $query
                        ->where('is_common', true)
                        ->where('month', $request->input('month'))
                        ->where('year', $request->input('year'));
                })] = ['unique:water_readings'];
            } else {
                $rules['departament_id'] = ['required', 'integer', 'exists:departaments,id',
                    Rule::unique('water_readings', 'departament_id')->where(function ($query) use ($request) {
                        return $query
                            ->where('month', $request->input('month'))
                            ->where('year', $request->input('year'));
                    }),
                ];
                $messages['departament_id.required'] = 'El departamento es requerido.';
                $messages['departament_id.exists'] = 'El departamento no existe.';
                $messages['departament_id.unique'] = 'Ya existe una medición para ese departamento en el mes y año seleccionados.';
            }

            $validated = $request->validate($rules, $messages);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $previousReading = $isInitial ? 0 : ($validated['previous_reading'] ?? 0);

        $photoUrl = $this->storePhoto($request);
        $payload = [
            'departament_id' => $validated['departament_id'] ?? null,
            'month' => $validated['month'],
            'year' => $validated['year'],
            'previous_reading' => $previousReading,
            'current_reading' => $validated['current_reading'],
            'm3_price' => $priceWater->water_price_per_m3 ?? $validated['m3_price'] ?? 0,
            'amount' => ($validated['current_reading'] - $previousReading) * ($priceWater->water_price_per_m3 ?? $validated['m3_price'] ?? 0),
            'is_initial' => $isInitial,
            'is_common' => $isCommon,
        ];

        $photoColumn = $this->resolvePhotoColumn();
        if ($photoColumn && $photoUrl) {
            $payload[$photoColumn] = $photoUrl;
        }

        $reading = WaterReading::create($payload);

        return $this->returnSuccess(200, $reading->load(['departament.owner']));
    }

    public function update(Request $request, int $id)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

        $reading = WaterReading::find($id);
        if (! $reading) {
            return $this->returnFail(404, 'Medición de agua no encontrada');
        }

        $isCommon = (bool) $request->input('is_common', $reading->is_common);

        $priceWater = MonthlyBills::where('month', $request->input('month'))->where('year', $request->input('year'))->exists()
            ? MonthlyBills::where('month', $request->input('month'))->where('year', $request->input('year'))->first()
            : 0;

        try {
            $rules = [
                'month' => ['required', 'integer', 'between:1,12'],
                'year' => ['required', 'integer'],
                'previous_reading' => ['required', 'numeric', 'min:0'],
                'current_reading' => ['required', 'numeric', 'gt:previous_reading'],
                'm3_price' => ['nullable', 'numeric', 'min:0'],
                'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'is_common' => ['nullable', 'boolean'],
            ];

            $messages = [
                'month.required' => 'El mes es requerido.',
                'month.between' => 'El mes debe estar entre 1 y 12.',
                'year.required' => 'El año es requerido.',
                'previous_reading.required' => 'La lectura anterior es requerida.',
                'current_reading.required' => 'La lectura actual es requerida.',
                'current_reading.gt' => 'La lectura actual debe ser mayor a la lectura anterior.',
            ];

            if ($isCommon) {
                $rules['departament_id'] = ['nullable'];
                $rules[Rule::unique('water_readings')->where(function ($query) use ($request) {
                    return $query
                        ->where('is_common', true)
                        ->where('month', $request->input('month'))
                        ->where('year', $request->input('year'));
                })->ignore($reading->id)] = ['unique:water_readings'];
            } else {
                $rules['departament_id'] = ['required', 'integer', 'exists:departaments,id',
                    Rule::unique('water_readings', 'departament_id')->where(function ($query) use ($request) {
                        return $query
                            ->where('month', $request->input('month'))
                            ->where('year', $request->input('year'));
                    })->ignore($reading->id),
                ];
                $messages['departament_id.required'] = 'El departamento es requerido.';
                $messages['departament_id.exists'] = 'El departamento no existe.';
                $messages['departament_id.unique'] = 'Ya existe una medición para ese departamento en el mes y año seleccionados.';
            }

            $validated = $request->validate($rules, $messages);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $payload = [
            'departament_id' => $validated['departament_id'] ?? null,
            'month' => $validated['month'],
            'year' => $validated['year'],
            'previous_reading' => $validated['previous_reading'],
            'current_reading' => $validated['current_reading'],
            'm3_price' => $validated['m3_price'] ?? $reading->m3_price ?? 0,
            'amount' => ($validated['current_reading'] - $validated['previous_reading']) * ($priceWater->water_price_per_m3 ?? $validated['m3_price'] ?? 0),
            'is_common' => $isCommon,
        ];

        $photoUrl = $this->storePhoto($request);
        $photoColumn = $this->resolvePhotoColumn();
        if ($photoColumn && $photoUrl) {
            $payload[$photoColumn] = $photoUrl;
        }

        $reading->update($payload);

        return $this->returnSuccess(200, $reading->load(['departament.owner']));
    }

    private function resolvePhotoColumn(): ?string
    {
        foreach (['photo', 'meter_photo', 'proof_photo'] as $column) {
            if (Schema::hasColumn('water_readings', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function storePhoto(Request $request): ?string
    {
        if (! $request->hasFile('photo')) {
            return null;
        }

        $photo = $request->file('photo');
        $rand = rand(1000000, 9999999);
        $name = $rand.'_'.time().'.'.$photo->extension();
        $destination = public_path('storage/images/water-readings');
        if (! is_dir($destination)) {
            @mkdir($destination, 0775, true);
        }
        $photo->move($destination, $name);

        return "/public/storage/images/water-readings/{$name}";
    }
}
