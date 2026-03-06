<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class RuleController extends Controller
{
    public function index(Request $request)
    {
        $rules = Rule::orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->get('search');
            $rules->where(function ($query) use ($search) {
                $query->where('code', 'like', '%' . $search . '%')
                    ->orWhere('title', 'like', '%' . $search . '%')
                    ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        if ($request->filled('type')) {
            $rules->where('type', intval($request->type));
        }

        if ($request->filled('severity')) {
            $rules->where('severity', intval($request->severity));
        }

        if ($request->filled('active')) {
            $rules->where('active', intval($request->active));
        }

        $perPage = $request->get('per_page', 40);

        return $this->returnSuccess(200, $rules->paginate($perPage));
    }

    public function ruleById($id)
    {
        $rule = Rule::find($id);

        if (!$rule) {
            return $this->returnFail(404, 'Regla no encontrada');
        }

        return $this->returnSuccess(200, $rule);
    }

    public function store(Request $request)
    {
        $validated = $this->validateFieldsFromInput($request->all());
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }

        Rule::create([
            'code' => $request->code,
            'title' => $request->title,
            'description' => $request->description,
            'punish' => $request->punish,
            'type' => $request->type,
            'severity' => $request->severity,
            'active' => $request->active,
            'suggest_amount' => $request->suggest_amount,
        ]);

        return $this->returnSuccess(200, 'ok');
    }

    public function update(Request $request, $id)
    {
        $rule = Rule::find($id);
        if (!$rule) {
            return $this->returnFail(404, 'Regla no encontrada');
        }

        $validated = $this->validateFieldsFromInput($request->all(), $id);
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }

        $rule->update([
            'code' => $request->code ?? $rule->code,
            'title' => $request->title ?? $rule->title,
            'description' => $request->description ?? $rule->description,
            'punish' => $request->punish ?? $rule->punish,
            'type' => $request->type ?? $rule->type,
            'severity' => $request->severity ?? $rule->severity,
            'active' => $request->active ?? $rule->active,
            'suggest_amount' => $request->suggest_amount ?? $rule->suggest_amount,
        ]);

        return $this->returnSuccess(200, 'ok');
    }

    public function deleteRule($id)
    {
        $rule = Rule::find($id);
        if (!$rule) {
            return $this->returnFail(404, 'Regla no encontrada');
        }

        $rule->delete();

        return $this->returnSuccess(200, 'ok');
    }

    private function validateFieldsFromInput($inputs, $id = null)
    {
        $rules = [
            'code' => ['required', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'punish' => ['required', 'string'],
            'type' => ['required', 'integer'],
            'severity' => ['required', 'integer'],
            'active' => ['required', 'integer'],
            'suggest_amount' => ['nullable', 'numeric'],
        ];

        $messages = [
            'code.required' => 'El código de la regla es requerido.',
            'title.required' => 'El título de la regla es requerido.',
            'description.required' => 'La descripción de la regla es requerida.',
            'punish.required' => 'La sanción es requerida.',
            'type.required' => 'El tipo de regla es requerido.',
            'type.integer' => 'El tipo de regla no es válido.',
            'severity.required' => 'La severidad es requerida.',
            'severity.integer' => 'La severidad no es válida.',
            'active.required' => 'El estado activo es requerido.',
            'active.integer' => 'El estado activo no es válido.',
            'suggest_amount.numeric' => 'El monto sugerido no es válido.',
        ];

        $validator = Validator::make($inputs, $rules, $messages)->errors();

        return $validator->all();
    }
}

