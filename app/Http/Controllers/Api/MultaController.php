<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Multa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MultaController extends Controller
{
    public function index(Request $request)
    {
        $multas = Multa::with(['rule', 'user'])->orderBy('created_at', 'desc');

        if ($request->filled('departament_id')) {
            $multas->where('departament_id', intval($request->departament_id));
        }

        if ($request->filled('rule_id')) {
            $multas->where('rule_id', intval($request->rule_id));
        }

        if ($request->filled('status')) {
            $multas->where('status', intval($request->status));
        }

        if ($request->filled('type')) {
            $multas->where('type', intval($request->type));
        }

        if ($request->filled('date_from')) {
            $multas->whereDate('incident_date', '>=', $request->get('date_from'));
        }

        if ($request->filled('date_to')) {
            $multas->whereDate('incident_date', '<=', $request->get('date_to'));
        }

        $sortBy = in_array($request->get('sort_by'), ['created_at', 'incident_date', 'amount', 'status'])
            ? $request->get('sort_by')
            : 'created_at';
        $sortDir = $request->get('sort_dir') === 'asc' ? 'asc' : 'desc';

        $multas->orderBy($sortBy, $sortDir);

        $perPage = $request->get('per_page', 40);

        return $this->returnSuccess(200, $multas->paginate($perPage));
    }

    public function multaById($id)
    {
        $multa = Multa::with(['rule', 'user'])->find($id);

        if (! $multa) {
            return $this->returnFail(404, 'Multa no encontrada');
        }

        return $this->returnSuccess(200, $multa);
    }

    public function store(Request $request)
    {
        $validated = $this->validateFieldsFromInput($request->all());
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }

        Multa::create([
            'rule_id' => $request->rule_id,
            'departament_id' => $request->departament_id,
            'type' => $request->type,
            'description' => $request->description,
            'incident_date' => $request->incident_date,
            'amount' => $request->amount,
            'pay_id' => $request->pay_id,
            'status' => $request->status,
        ]);

        return $this->returnSuccess(200, 'ok');
    }

    public function update(Request $request, $id)
    {
        $multa = Multa::find($id);
        if (! $multa) {
            return $this->returnFail(404, 'Multa no encontrada');
        }

        $validated = $this->validateFieldsFromInput($request->all(), $id, true);
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }

        $multa->update([
            'rule_id' => $request->rule_id ?? $multa->rule_id,
            'departament_id' => $request->departament_id ?? $multa->departament_id,
            'type' => $request->type ?? $multa->type,
            'description' => $request->description ?? $multa->description,
            'incident_date' => $request->incident_date ?? $multa->incident_date,
            'amount' => $request->amount ?? $multa->amount,
            'pay_id' => $request->pay_id ?? $multa->pay_id,
            'status' => $request->status ?? $multa->status,
        ]);

        return $this->returnSuccess(200, 'ok');
    }

    public function deleteMulta($id)
    {
        $multa = Multa::find($id);
        if (! $multa) {
            return $this->returnFail(404, 'Multa no encontrada');
        }

        $multa->delete();

        return $this->returnSuccess(200, 'ok');
    }

    private function validateFieldsFromInput($inputs, $id = null, $isUpdate = false)
    {
        $baseRules = [
            'rule_id' => ['nullable', 'integer'],
            'departament_id' => ['required', 'integer'],
            'type' => ['required', 'integer'],
            'description' => ['required', 'string'],
            'incident_date' => ['required', 'date'],
            'amount' => ['nullable', 'numeric'],
            'pay_id' => ['nullable', 'integer'],
            'status' => ['required', 'integer'],
        ];

        if ($isUpdate) {
            foreach ($baseRules as $field => &$rules) {
                if (in_array('required', $rules)) {
                    $rules = array_diff($rules, ['required']);
                }
            }
        }

        $messages = [
            'departament_id.required' => 'El departamento es requerido.',
            'departament_id.integer' => 'El departamento no es válido.',
            'type.required' => 'El tipo de multa es requerido.',
            'type.integer' => 'El tipo de multa no es válido.',
            'description.required' => 'La descripción es requerida.',
            'incident_date.required' => 'La fecha del incidente es requerida.',
            'incident_date.date' => 'La fecha del incidente no es válida.',
            'amount.numeric' => 'El monto no es válido.',
            'status.required' => 'El estado es requerido.',
            'status.integer' => 'El estado no es válido.',
        ];

        $validator = Validator::make($inputs, $baseRules, $messages)->errors();

        return $validator->all();
    }
}
