<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exports\BookingsExport;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Departament;
use App\Models\Quota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function bookings(Request $request): JsonResponse
    {
        $filters = $this->getValidatedFilters($request);

        $perPage = (int) ($filters['per_page'] ?? 25);

        $query = Booking::with(['user', 'departament', 'comunArea', 'pay'])
            ->filter($filters);

        if (! $filters['include_cancelled']) {
            $query->where('status', '>', 0);
        }

        if ($filters['search']) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('booking_number', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('departament', fn ($d) => $d->where('number', 'like', "%{$search}%"))
                    ->orWhereHas('comunArea', fn ($a) => $a->where('name', 'like', "%{$search}%"));
            });
        }

        $bookings = $query->paginate($perPage);

        return $this->returnSuccess(200, $bookings);
    }

    public function exportBookings(Request $request): BinaryFileResponse
    {
        $filters = $this->getValidatedFilters($request);

        if ($filters['search']) {
            $search = $filters['search'];
            $filters['searchCallback'] = function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('booking_number', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('departament', fn ($d) => $d->where('number', 'like', "%{$search}%"))
                        ->orWhereHas('comunArea', fn ($a) => $a->where('name', 'like', "%{$search}%"));
                });
            };
        }

        $filename = 'reporte-reservas-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new BookingsExport($filters), $filename);
    }

    public function bookingsMetrics(Request $request): JsonResponse
    {
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $base = Booking::query();
        if ($dateFrom) {
            $base->whereDate('date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $base->whereDate('date', '<=', $dateTo);
        }

        $total = (clone $base)->count();
        $canceladas = (clone $base)->where('status', 0)->count();
        $pendientesPago = (clone $base)->where('status', 1)->count();
        $pendientesAprob = (clone $base)->where('status', 2)->count();
        $exitosas = (clone $base)->where('status', 3)->count();
        $completadas = (clone $base)->where('status', Booking::STATUS_COMPLETED)->count();
        $pendReembolso = (clone $base)->whereIn('status', [
            Booking::STATUS_PENDING_REFUND,
            Booking::STATUS_PENDING_DEVO,
        ])->count();

        $active = (clone $base)->where('status', '!=', 0);

        $activeTotal = (clone $active)->count();

        $topAreas = (clone $active)
            ->select('comun_area_id', DB::raw('count(*) as total'))
            ->groupBy('comun_area_id')
            ->orderByDesc('total')
            ->with('comunArea:id,name')
            ->take(5)
            ->get()
            ->map(function ($item) use ($activeTotal) {
                return [
                    'name' => $item->comunArea?->name ?? '—',
                    'total' => (int) $item->total,
                    'percentage' => $activeTotal ? round(((int) $item->total / $activeTotal) * 100, 1) : 0,
                ];
            });

        $dayNames = [1 => 'Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $dayOrder = ['Lunes' => 0, 'Martes' => 1, 'Miércoles' => 2, 'Jueves' => 3, 'Viernes' => 4, 'Sábado' => 5, 'Domingo' => 6];

        $topDias = (clone $active)
            ->select(DB::raw('DAYOFWEEK(date) as day_num'), DB::raw('count(*) as total'))
            ->whereNotNull('date')
            ->groupBy('day_num')
            ->orderByDesc('total')
            ->get()
            ->map(function ($item) use ($activeTotal, $dayNames) {
                return [
                    'day_name' => $dayNames[(int) $item->day_num] ?? '—',
                    'total' => (int) $item->total,
                    'percentage' => $activeTotal ? round(((int) $item->total / $activeTotal) * 100, 1) : 0,
                ];
            })
            ->sortBy(function ($i) use ($dayOrder) {
                return $dayOrder[$i['day_name']] ?? 99;
            })
            ->values();

        return $this->returnSuccess(200, [
            'total' => $total,
            'canceladas' => $canceladas,
            'pendientes_pago' => $pendientesPago,
            'pendientes_aprob' => $pendientesAprob,
            'exitosas' => $exitosas,
            'completadas' => $completadas,
            'pend_reembolso' => $pendReembolso,
            'top_areas' => $topAreas,
            'top_dias' => $topDias,
        ]);
    }

    public function monthlyPayments(Request $request): JsonResponse
    {
        $year = (int) ($request->query('year', now()->year));
        if ($year < 2000) {
            return $this->returnFail(422, ['message' => 'Año inválido']);
        }

        $departments = Departament::with([
            'owner:id,name',
            'peoples.user:id,name',
        ])->where(function ($q) {
            $q->whereNotNull('user_id')
                ->orWhereHas('peoples');
        })->get()->sortBy(fn ($d) => [$d->type, $d->inter_number])->values();

        $quotas = Quota::whereYear('due_date', $year)
            ->whereIn('departament_id', $departments->pluck('id'))
            ->get(['id', 'departament_id', 'month', 'amount', 'maintenance_amount', 'water_amount', 'status', 'due_date']);

        $quotasByDept = $quotas->groupBy('departament_id');

        $months = [
            1 => ['label' => 'Ene', 'name' => 'Enero'],
            2 => ['label' => 'Feb', 'name' => 'Febrero'],
            3 => ['label' => 'Mar', 'name' => 'Marzo'],
            4 => ['label' => 'Abr', 'name' => 'Abril'],
            5 => ['label' => 'May', 'name' => 'Mayo'],
            6 => ['label' => 'Jun', 'name' => 'Junio'],
            7 => ['label' => 'Jul', 'name' => 'Julio'],
            8 => ['label' => 'Ago', 'name' => 'Agosto'],
            9 => ['label' => 'Sep', 'name' => 'Septiembre'],
            10 => ['label' => 'Oct', 'name' => 'Octubre'],
            11 => ['label' => 'Nov', 'name' => 'Noviembre'],
            12 => ['label' => 'Dic', 'name' => 'Diciembre'],
        ];

        $data = $departments->map(function ($dept) use ($quotasByDept, $months) {
            $deptQuotas = $quotasByDept->get($dept->id, collect());
            $deptQuotasByMonth = $deptQuotas->keyBy('month');

            $responsible = $dept->owner?->name;
            if (! $responsible) {
                $tenant = $dept->peoples->first();
                $responsible = $tenant?->user?->name;
            }

            $monthData = [];
            foreach ($months as $monthNum => $monthInfo) {
                $quota = $deptQuotasByMonth->get($monthNum);
                $monthData[$monthNum] = $quota ? [
                    'quota_id' => $quota->id,
                    'amount' => (float) $quota->amount,
                    'maintenance_amount' => (float) $quota->maintenance_amount,
                    'water_amount' => (float) $quota->water_amount,
                    'status' => (int) $quota->status,
                ] : null;
            }

            return [
                'departament_id' => $dept->id,
                'number' => $dept->number,
                'inter_number' => $dept->inter_number,
                'block' => $dept->block,
                'type' => $dept->type,
                'type_label' => $dept->type_label,
                'responsible' => $responsible ?? '—',
                'months' => $monthData,
            ];
        });

        $totals = [];
        foreach ($months as $monthNum => $monthInfo) {
            $amount = 0;
            $paid = 0;
            $pending = 0;
            $overdue = 0;
            foreach ($data as $row) {
                $m = $row['months'][$monthNum];
                if (! $m) {
                    continue;
                }
                $amount += $m['amount'];
                if ($m['status'] === 3) {
                    $paid += $m['amount'];
                } elseif ($m['status'] === 4) {
                    $overdue += $m['amount'];
                } else {
                    $pending += $m['amount'];
                }
            }
            $totals[$monthNum] = [
                'amount' => round($amount, 2),
                'paid' => round($paid, 2),
                'pending' => round($pending, 2),
                'overdue' => round($overdue, 2),
            ];
        }

        return $this->returnSuccess(200, [
            'year' => $year,
            'months' => $months,
            'departments' => $data,
            'totals' => $totals,
        ]);
    }

    private function getValidatedFilters(Request $request): array
    {
        $validFilters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'integer', 'in:-1,0,1,2,3,4,5,6'],
            'area_id' => ['nullable', 'integer', 'exists:comun_areas,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'sort_by' => ['nullable', 'string', 'in:created_at,date,status,amount'],
            'sort_dir' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
            'include_cancelled' => ['nullable', 'boolean'],
        ]);

        return array_merge([
            'search' => null,
            'status' => -1,
            'area_id' => null,
            'date_from' => null,
            'date_to' => null,
            'sort_by' => 'created_at',
            'sort_dir' => 'desc',
            'include_cancelled' => false,
        ], $validFilters);
    }
}
