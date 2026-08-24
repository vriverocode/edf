<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exports\BookingsExport;
use App\Exports\DelinquentsExport;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Departament;
use App\Models\Quota;
use App\Models\Rol;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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

        $bookings = $query->orderBy('date', 'desc')->paginate($perPage);

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
            'per_page' => 25,
            'include_cancelled' => false,
        ], $validFilters);
    }

    public function delinquents(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 15);
        $search = $request->query('search');
        $twoMonthsAgo = now()->subMonths(2);

        $morosoUsers = User::where('status', 2)
            ->whereNotIn('rol_id', [Rol::ADMIN, Rol::SUPER_ADMIN, Rol::TRABAJADOR, Rol::PARCIAL])
            ->with(['units:id,number,user_id', 'departmentsInquilino.departament:id,number'])
            ->get(['id', 'name', 'email', 'phone', 'dni', 'status', 'rol_id'])
            ->map(function ($user) {
                $departments = $user->units->pluck('number')->merge(
                    $user->departmentsInquilino->pluck('departament.number')
                )->filter()->unique()->values();

                return [
                    'type' => 'user_status',
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'dni' => $user->dni,
                    'status_label' => 'Moroso (estado usuario)',
                    'departments' => $departments,
                    'total_debt' => 0,
                    'quotas_count' => 0,
                    'quotas' => [],
                ];
            });

        $overdueQuotas = Quota::where('status', 1)
            ->where('due_date', '<=', $twoMonthsAgo)
            ->with(['departament.owner:id,name,email,phone,dni', 'responsiblePivot.user:id,name,email,phone,dni'])
            ->get()
            ->groupBy(function ($quota) {
                return $quota->responsiblePivot?->user_id ?? $quota->departament->user_id;
            })
            ->map(function ($quotas, $userId) {
                $firstQuota = $quotas->first();
                $owner = $firstQuota->responsiblePivot?->user ?? $firstQuota->departament->owner;

                $departments = $quotas->pluck('departament.number')->unique()->values();
                $totalAmount = $quotas->sum('amount');
                $oldestDueDate = $quotas->min('due_date');

                return [
                    'type' => 'overdue_quotas',
                    'user_id' => $owner?->id,
                    'name' => $owner?->name ?? 'Sin propietario',
                    'email' => $owner?->email,
                    'phone' => $owner?->phone,
                    'dni' => $owner?->dni,
                    'status_label' => 'Cuotas pendientes >2 meses',
                    'departments' => $departments,
                    'total_debt' => round($totalAmount, 2),
                    'oldest_due_date' => $oldestDueDate,
                    'quotas_count' => $quotas->count(),
                    'quotas' => $quotas->map(fn ($q) => [
                        'id' => $q->id,
                        'department' => $q->departament->number,
                        'amount' => (float) $q->amount,
                        'due_date' => $q->due_date,
                        'month' => $q->month,
                        'month_label' => $q->month_label,
                    ])->values()->all(),
                ];
            });

        $all = $morosoUsers->concat($overdueQuotas)
            ->groupBy('user_id')
            ->map(function ($group) {
                $first = $group->first();
                $types = $group->pluck('type')->unique()->values()->all();

                return [
                    'user_id' => $first['user_id'],
                    'name' => $first['name'],
                    'email' => $first['email'],
                    'phone' => $first['phone'],
                    'dni' => $first['dni'],
                    'types' => $types,
                    'departments' => $group->pluck('departments')->flatten()->unique()->values()->all(),
                    'total_debt' => $group->sum('total_debt'),
                    'quotas_count' => $group->sum('quotas_count'),
                    'quotas' => $group->pluck('quotas')->flatten()->values()->all(),
                ];
            })
            ->values();

        if ($search) {
            $searchLower = strtolower($search);
            $all = $all->filter(function ($row) use ($searchLower) {
                $nameMatch = str_contains(strtolower($row['name'] ?? ''), $searchLower);
                $deptMatch = collect($row['departments'] ?? [])
                    ->contains(fn ($d) => str_contains(strtolower($d), $searchLower));
                $dniMatch = str_contains(strtolower($row['dni'] ?? ''), $searchLower);
                return $nameMatch || $deptMatch || $dniMatch;
            })->values();
        }

        $total = $all->count();
        $currentPage = (int) $request->query('page', 1);
        $items = $all->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $paginator = new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $currentPage,
            ['path' => $request->url()]
        );

        return $this->returnSuccess(200, $paginator);
    }

    public function sendReminderDelinquents(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return $this->returnFail(403, 'No autorizado');
        }

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $twoMonthsAgo = now()->subMonths(2);

        $delinquentUsers = User::whereIn('id', $validated['user_ids'])
            ->whereNotIn('rol_id', [Rol::ADMIN, Rol::SUPER_ADMIN, Rol::TRABAJADOR, Rol::PARCIAL])
            ->get(['id', 'name', 'email', 'phone']);

        $sent = 0;
        $failed = 0;

        foreach ($delinquentUsers as $delinquent) {
            try {
                $overdueQuotas = Quota::where('status', 1)
                    ->where('due_date', '<=', $twoMonthsAgo)
                    ->whereHas('departament', function ($q) use ($delinquent) {
                        $q->where(function ($q2) use ($delinquent) {
                            $q2->where('user_id', $delinquent->id)
                                ->orWhereHas('peoples', fn ($p) => $p->where('user_id', $delinquent->id)->where('type', Rol::INQUILINO));
                        });
                    })
                    ->with('departament')
                    ->get();

                $totalDebt = $overdueQuotas->sum('amount');
                $quotasCount = $overdueQuotas->count();
                $oldestDue = $overdueQuotas->min('due_date');

                $defaultMessage = "Estimado {$delinquent->name},\n\n";
                $defaultMessage .= "Le recordamos que tiene {$quotasCount} cuota(s) pendiente(s) de pago con vencimiento superior a 2 meses.\n";
                $defaultMessage .= "Deuda total: S/ " . number_format($totalDebt, 2) . "\n";
                $defaultMessage .= "Cuota más antigua: " . \Carbon\Carbon::parse($oldestDue)->format('d/m/Y') . "\n\n";
                $defaultMessage .= "Por favor regularice su situación a la brevedad.\n\n";
                $defaultMessage .= "Administración EDF";

                $message = $validated['message'] ?? $defaultMessage;

                $delinquent->notify(new RealtimeNotification(
                    title: 'Recordatorio de cuotas pendientes',
                    message: $message,
                    url: '/client/quotas',
                    meta: [
                        'type' => 'delinquent_reminder',
                        'total_debt' => $totalDebt,
                        'quotas_count' => $quotasCount,
                    ],
                ));

                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                \Log::error("Error enviando recordatorio a usuario {$delinquent->id}: " . $e->getMessage());
            }
        }

        return $this->returnSuccess(200, [
            'sent' => $sent,
            'failed' => $failed,
            'message' => "Recordatorios enviados: {$sent}. Fallidos: {$failed}.",
        ]);
    }

    public function delinquentsMetrics(): JsonResponse
    {
        $twoMonthsAgo = now()->subMonths(2);

        $totalOverdue = Quota::where('status', 1)
            ->where('due_date', '<=', $twoMonthsAgo)
            ->count();

        $totalDebt = Quota::where('status', 1)
            ->where('due_date', '<=', $twoMonthsAgo)
            ->sum('amount');

        $uniqueDelinquents = User::where(function ($q) {
                $q->where('status', 2)
                    ->whereNotIn('rol_id', [Rol::ADMIN, Rol::SUPER_ADMIN, Rol::TRABAJADOR, Rol::PARCIAL]);
            })
            ->orWhereHas('quotas', function ($q) use ($twoMonthsAgo) {
                $q->where('status', 1)->where('due_date', '<=', $twoMonthsAgo);
            })
            ->count();

        return $this->returnSuccess(200, [
            'total_delinquents' => $uniqueDelinquents,
            'total_debt' => round($totalDebt, 2),
            'total_overdue_quotas' => $totalOverdue,
        ]);
    }

    public function exportDelinquents(Request $request): BinaryFileResponse
    {
        $data = $this->getDelinquentsData($request);

        $filename = 'reporte-morosos-'.now()->format('Y-m-d-His').'.xlsx';

        return Excel::download(new DelinquentsExport($data), $filename);
    }

    private function getDelinquentsData(Request $request)
    {
        $search = $request->query('search');
        $twoMonthsAgo = now()->subMonths(2);

        $morosoUsers = User::where('status', 2)
            ->whereNotIn('rol_id', [Rol::ADMIN, Rol::SUPER_ADMIN, Rol::TRABAJADOR, Rol::PARCIAL])
            ->with(['units:id,number,user_id', 'departmentsInquilino.departament:id,number'])
            ->get(['id', 'name', 'email', 'phone', 'dni', 'status', 'rol_id'])
            ->map(function ($user) {
                $departments = $user->units->pluck('number')->merge(
                    $user->departmentsInquilino->pluck('departament.number')
                )->filter()->unique()->values();

                return [
                    'type' => 'user_status',
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'dni' => $user->dni,
                    'status_label' => 'Moroso (estado usuario)',
                    'departments' => $departments,
                    'total_debt' => 0,
                    'quotas_count' => 0,
                    'quotas' => [],
                ];
            });

        $overdueQuotas = Quota::where('status', 1)
            ->where('due_date', '<=', $twoMonthsAgo)
            ->with(['departament.owner:id,name,email,phone,dni', 'responsiblePivot.user:id,name,email,phone,dni'])
            ->get()
            ->groupBy(function ($quota) {
                return $quota->responsiblePivot?->user_id ?? $quota->departament->user_id;
            })
            ->map(function ($quotas, $userId) {
                $firstQuota = $quotas->first();
                $owner = $firstQuota->responsiblePivot?->user ?? $firstQuota->departament->owner;

                $departments = $quotas->pluck('departament.number')->unique()->values();
                $totalAmount = $quotas->sum('amount');

                return [
                    'type' => 'overdue_quotas',
                    'user_id' => $owner?->id,
                    'name' => $owner?->name ?? 'Sin propietario',
                    'email' => $owner?->email,
                    'phone' => $owner?->phone,
                    'dni' => $owner?->dni,
                    'status_label' => 'Cuotas pendientes >2 meses',
                    'departments' => $departments,
                    'total_debt' => round($totalAmount, 2),
                    'quotas_count' => $quotas->count(),
                    'quotas' => $quotas->map(fn ($q) => [
                        'id' => $q->id,
                        'department' => $q->departament->number,
                        'amount' => (float) $q->amount,
                        'due_date' => $q->due_date,
                        'month' => $q->month,
                        'month_label' => $q->month_label,
                    ])->values()->all(),
                ];
            });

        $all = $morosoUsers->concat($overdueQuotas)
            ->groupBy('user_id')
            ->map(function ($group) {
                $first = $group->first();
                $types = $group->pluck('type')->unique()->values()->all();

                return [
                    'user_id' => $first['user_id'],
                    'name' => $first['name'],
                    'email' => $first['email'],
                    'phone' => $first['phone'],
                    'dni' => $first['dni'],
                    'types' => $types,
                    'departments' => $group->pluck('departments')->flatten()->unique()->values()->all(),
                    'total_debt' => $group->sum('total_debt'),
                    'quotas_count' => $group->sum('quotas_count'),
                    'quotas' => $group->pluck('quotas')->flatten()->values()->all(),
                ];
            })
            ->values();

        if ($search) {
            $searchLower = strtolower($search);
            $all = $all->filter(function ($row) use ($searchLower) {
                $nameMatch = str_contains(strtolower($row['name'] ?? ''), $searchLower);
                $deptMatch = collect($row['departments'] ?? [])
                    ->contains(fn ($d) => str_contains(strtolower($d), $searchLower));
                $dniMatch = str_contains(strtolower($row['dni'] ?? ''), $searchLower);
                return $nameMatch || $deptMatch || $dniMatch;
            })->values();
        }

        return $all;
    }
}
