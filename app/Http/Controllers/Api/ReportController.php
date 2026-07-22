<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exports\BookingsExport;
use App\Http\Controllers\Controller;
use App\Models\Booking;
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
            'top_areas' => $topAreas,
            'top_dias' => $topDias,
        ]);
    }

    private function getValidatedFilters(Request $request): array
    {
        $validFilters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'integer', 'in:0,1,2,3,4'],
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
            'status' => 4,
            'area_id' => null,
            'date_from' => null,
            'date_to' => null,
            'sort_by' => 'created_at',
            'sort_dir' => 'desc',
            'include_cancelled' => false,
        ], $validFilters);
    }
}
