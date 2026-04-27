<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
            ->orderBy('departament_id', 'asc');

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

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'departament_id' => ['required', 'integer', 'exists:departaments,id',
                    Rule::unique('water_readings', 'departament_id')->where(function ($query) use ($request) {
                        return $query
                            ->where('month', $request->input('month'))
                            ->where('year', $request->input('year'));
                    }),
                ],
                'month' => ['required', 'integer', 'between:1,12'],
                'year' => ['required', 'integer'],
                'previous_reading' => ['required', 'numeric', 'min:0'],
                'current_reading' => ['required', 'numeric', 'gte:previous_reading'],
                'm3_price' => ['nullable', 'numeric', 'min:0'],
                'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            ], [
                'departament_id.required' => 'El departamento es requerido.',
                'departament_id.exists' => 'El departamento no existe.',
                'departament_id.unique' => 'Ya existe una medición para ese departamento en el mes y año seleccionados.',
                'month.required' => 'El mes es requerido.',
                'month.between' => 'El mes debe estar entre 1 y 12.',
                'year.required' => 'El año es requerido.',
                'previous_reading.required' => 'La lectura anterior es requerida.',
                'current_reading.required' => 'La lectura actual es requerida.',
                'current_reading.gte' => 'La lectura actual debe ser mayor o igual a la lectura anterior.',
                'photo.required' => 'La foto comprobante del medidor es requerida.',
                'photo.image' => 'El archivo debe ser una imagen válida.',
            ]);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $photoUrl = $this->storePhoto($request);
        $payload = [
            'departament_id' => $validated['departament_id'],
            'month' => $validated['month'],
            'year' => $validated['year'],
            'previous_reading' => $validated['previous_reading'],
            'current_reading' => $validated['current_reading'],
            'm3_price' => $validated['m3_price'] ?? 0,
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
        $reading = WaterReading::find($id);
        if (! $reading) {
            return $this->returnFail(404, 'Medición de agua no encontrada');
        }

        try {
            $validated = $request->validate([
                'departament_id' => ['required', 'integer', 'exists:departaments,id',
                    Rule::unique('water_readings', 'departament_id')->where(function ($query) use ($request) {
                        return $query
                            ->where('month', $request->input('month'))
                            ->where('year', $request->input('year'));
                    })->ignore($reading->id),
                ],
                'month' => ['required', 'integer', 'between:1,12'],
                'year' => ['required', 'integer'],
                'previous_reading' => ['required', 'numeric', 'min:0'],
                'current_reading' => ['required', 'numeric', 'gte:previous_reading'],
                'm3_price' => ['nullable', 'numeric', 'min:0'],
                'photo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            ], [
                'departament_id.required' => 'El departamento es requerido.',
                'departament_id.exists' => 'El departamento no existe.',
                'departament_id.unique' => 'Ya existe una medición para ese departamento en el mes y año seleccionados.',
                'month.required' => 'El mes es requerido.',
                'month.between' => 'El mes debe estar entre 1 y 12.',
                'year.required' => 'El año es requerido.',
                'previous_reading.required' => 'La lectura anterior es requerida.',
                'current_reading.required' => 'La lectura actual es requerida.',
                'current_reading.gte' => 'La lectura actual debe ser mayor o igual a la lectura anterior.',
            ]);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $payload = [
            'departament_id' => $validated['departament_id'],
            'month' => $validated['month'],
            'year' => $validated['year'],
            'previous_reading' => $validated['previous_reading'],
            'current_reading' => $validated['current_reading'],
            'm3_price' => $validated['m3_price'] ?? $reading->m3_price ?? 0,
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
        $name = $rand . '_' . time() . '.' . $photo->extension();
        $destination = public_path('storage/images/water-readings');
        if (!is_dir($destination)) {
            @mkdir($destination, 0775, true);
        }
        $photo->move($destination, $name);

        return config('app.url') . "/storage/images/water-readings/{$name}";
    }
}

