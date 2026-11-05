<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Multa;
use App\Models\Quota;
use App\Models\Rol;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MultaController extends Controller
{
    public function index(Request $request)
    {
        $multas = Multa::with(['rule', 'departament.owner'])->orderBy('created_at', 'desc');

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

        if ($request->filled('search')) {
            $search = $request->search;
            $multas->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhereHas('departament', fn ($qd) => $qd->where('number', 'like', "%{$search}%"));
            });
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

        $perPage = $request->get('per_page', 20);

        return $this->returnSuccess(200, $multas->paginate($perPage));
    }

    public function multaById($id)
    {
        $multa = Multa::with(['rule', 'departament.owner', 'quotas'])->find($id);

        if (! $multa) {
            return $this->returnFail(404, 'Multa no encontrada');
        }

        return $this->returnSuccess(200, $multa);
    }

    public function store(Request $request)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }

        $validated = $this->validateFieldsFromInput($request->all());
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }

        $incidentDate = $request->incident_date ? Carbon::parse($request->incident_date) : now();
        $startMonth = $request->filled('start_month') ? (int) $request->start_month : (int) $incidentDate->month;
        $startYear = $request->filled('start_year') ? (int) $request->start_year : (int) $incidentDate->year;
        $installments = $request->filled('installments') ? max(1, (int) $request->installments) : 1;

        $multa = Multa::create([
            'rule_id' => $request->rule_id,
            'departament_id' => $request->departament_id,
            'type' => $request->type ?? 1,
            'description' => $request->description,
            'incident_date' => $request->incident_date,
            'amount' => $request->amount,
            'installments' => $installments,
            'paid_installments' => 0,
            'start_month' => $startMonth,
            'start_year' => $startYear,
            'pay_id' => $request->pay_id,
            'status' => $request->status ?? 1,
        ]);

        $this->updateExistingQuotas($multa);

        return $this->returnSuccess(200, $multa->load('departament'));
    }

    public function update(Request $request, $id)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }
        $multa = Multa::find($id);
        if (! $multa) {
            return $this->returnFail(404, 'Multa no encontrada');
        }

        $validated = $this->validateFieldsFromInput($request->all(), $id, true);
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }

        $this->removeExtrasFromQuotas($multa);

        $multa->update([
            'rule_id' => $request->has('rule_id') ? $request->rule_id : $multa->rule_id,
            'departament_id' => $request->has('departament_id') ? $request->departament_id : $multa->departament_id,
            'type' => $request->has('type') ? $request->type : $multa->type,
            'description' => $request->has('description') ? $request->description : $multa->description,
            'incident_date' => $request->has('incident_date') ? $request->incident_date : $multa->incident_date,
            'amount' => $request->has('amount') ? $request->amount : $multa->amount,
            'installments' => $request->has('installments') ? max(1, (int) $request->installments) : $multa->installments,
            'start_month' => $request->has('start_month') ? (int) $request->start_month : $multa->start_month,
            'start_year' => $request->has('start_year') ? (int) $request->start_year : $multa->start_year,
            'pay_id' => $request->has('pay_id') ? $request->pay_id : $multa->pay_id,
            'status' => $request->has('status') ? (int) $request->status : $multa->status,
        ]);

        if ($multa->status == 1) {
            $this->updateExistingQuotas($multa);
        }

        return $this->returnSuccess(200, $multa);
    }

    public function deleteMulta($id)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'No autorizado'], 403);
        }
        $multa = Multa::find($id);
        if (! $multa) {
            return $this->returnFail(404, 'Multa no encontrada');
        }

        $this->removeExtrasFromQuotas($multa);
        $multa->delete();

        return $this->returnSuccess(200, 'ok');
    }

    private function updateExistingQuotas(Multa $multa): void
    {
        if (! $multa->start_year || ! $multa->start_month || $multa->status != 1) {
            return;
        }

        $existingQuotas = DB::table('quotas')
            ->where('departament_id', $multa->departament_id)
            ->whereRaw('(year * 12 + month) >= ? * 12 + ?', [$multa->start_year, $multa->start_month])
            ->whereRaw('(year * 12 + month) < ? * 12 + ?', [
                $multa->start_year + (int) (($multa->start_month + $multa->installments - 1) / 12),
                (($multa->start_month + $multa->installments - 1) % 12) + 1,
            ])
            ->get();

        foreach ($existingQuotas as $quota) {
            $currentMonthIndex = ($quota->year * 12 + $quota->month) - ($multa->start_year * 12 + $multa->start_month) + 1;

            if ($currentMonthIndex >= 1 && $currentMonthIndex <= $multa->installments) {
                DB::table('multa_quota')->updateOrInsert(
                    ['multa_id' => $multa->id, 'quota_id' => $quota->id],
                    ['installment_number' => $currentMonthIndex]
                );

                $this->recalculateQuotaExtraAmount($quota->id);
            }
        }
    }

    private function removeExtrasFromQuotas(Multa $multa): void
    {
        $linkedQuotas = DB::table('multa_quota')
            ->where('multa_id', $multa->id)
            ->pluck('quota_id');

        DB::table('multa_quota')->where('multa_id', $multa->id)->delete();

        foreach ($linkedQuotas as $quotaId) {
            $this->recalculateQuotaExtraAmount($quotaId);
        }
    }

    private function recalculateQuotaExtraAmount(int $quotaId): void
    {
        $totalCharges = DB::table('charge_quota')
            ->join('department_charges', 'department_charges.id', '=', 'charge_quota.department_charge_id')
            ->where('charge_quota.quota_id', $quotaId)
            ->sum('department_charges.monthly_amount');

        $multasLinked = DB::table('multa_quota')
            ->join('multas', 'multas.id', '=', 'multa_quota.multa_id')
            ->where('multa_quota.quota_id', $quotaId)
            ->select('multas.amount', 'multas.installments')
            ->get();

        $totalMultas = 0;
        foreach ($multasLinked as $m) {
            $inst = max(1, (int) ($m->installments ?? 1));
            $totalMultas += round(((float) $m->amount) / $inst, 2);
        }

        $totalExtra = round($totalCharges + $totalMultas, 2);

        $quota = Quota::find($quotaId);
        if ($quota) {
            $quota->update([
                'extra_amount' => $totalExtra,
                'amount' => round($quota->maintenance_amount + $quota->water_amount + $totalExtra, 2),
            ]);
        }
    }

    private function validateFieldsFromInput($inputs, $id = null, $isUpdate = false)
    {
        $baseRules = [
            'rule_id' => ['nullable', 'integer'],
            'departament_id' => ['required', 'integer'],
            'type' => ['nullable', 'integer'],
            'description' => ['required', 'string'],
            'incident_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'installments' => ['nullable', 'integer', 'min:1'],
            'start_month' => ['nullable', 'integer', 'between:1,12'],
            'start_year' => ['nullable', 'integer', 'min:2020'],
            'pay_id' => ['nullable', 'integer'],
            'status' => ['nullable', 'integer'],
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
            'description.required' => 'La descripción es requerida.',
            'incident_date.required' => 'La fecha del incidente es requerida.',
            'incident_date.date' => 'La fecha del incidente no es válida.',
            'amount.required' => 'El monto es requerido.',
            'amount.numeric' => 'El monto no es válido.',
            'amount.min' => 'El monto debe ser mayor a 0.',
        ];

        $validator = Validator::make($inputs, $baseRules, $messages)->errors();

        return $validator->all();
    }
}
