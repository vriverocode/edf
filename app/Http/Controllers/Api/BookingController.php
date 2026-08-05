<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ComunArea;
use App\Models\ComunAreaSchedule;
use App\Models\Event;
use App\Models\Maintenance;
use App\Models\PeoplesXDepartaments;
use App\Models\Rol;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use App\Services\BookingPendingPayNotifier;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    //
    public function storeBooking(Request $request)
    {
        $validated = $this->validateFieldsFromInput($request->all());
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }
        $lo = '';
        try {
            $user = $request->user();
            if ($user->rol_id === Rol::ADMIN || $user->rol_id === Rol::TRABAJADOR || $user->rol_id === Rol::PARCIAL) {
                return $this->returnFail(400, ['Usuario no valido', $user]);
            }
            if ($user->status !== 1) {
                return $this->returnFail(400, ['Usuario inactivo', $user]);
            }
            $departament_id = $request->departament_id;

            $ownedIds = $user->apartaments()->pluck('id');
            $residentIds = PeoplesXDepartaments::where('user_id', $user->id)->pluck('departament_id');
            $allowedIds = $ownedIds->merge($residentIds)->unique()->values();

            if ($allowedIds->isEmpty()) {
                return $this->returnFail(422, 'Debes tener una unidad asignada para reservar áreas comunes.');
            }

            if (! $departament_id && ($user->rol_id === Rol::AIRBNB || $user->rol_id === Rol::FAMILIAR || $user->rol_id === Rol::INQUILINO)) {
                $residentRecord = PeoplesXDepartaments::where('user_id', $user->id)
                    ->whereIn('type', [5, 4, 3])
                    ->first();
                if ($residentRecord) {
                    $departament_id = $residentRecord->departament_id;
                }
            }

            if (! $departament_id) {
                return $this->returnFail(400, 'No se encontró un departamento asociado a tu cuenta');
            }

            if (! $allowedIds->contains((int) $departament_id)) {
                return $this->returnFail(403, 'No tienes permisos para crear reservas en este departamento');
            }

            $allowedAreas = $user->availableComunAreas()->pluck('comun_area_id')->toArray();
            if (! empty($allowedAreas) && ! in_array((int) $request->comun_area, $allowedAreas)) {
                return $this->returnFail(403, 'No tienes permiso para reservar esta área común');
            }

            $area = ComunArea::find($request->comun_area);
            if (! $area) {
                return $this->returnFail(404, 'Área común no encontrada');
            }
            if (! $area->status) {
                return $this->returnFail(409, 'El área común está temporalmente deshabilitada');
            }

            $date = date('Y-m-d', strtotime($request->date));
            if ($this->hasActiveReservationForDepartment($departament_id, (int) $request->comun_area)) {
                return $this->returnFail(409, 'Ya tienes una reserva activa para esta área. Solo podrás reservarla nuevamente cuando finalice el día de tu reserva.');
            }

            if ($this->hasOverlappingBookingForDepartment($departament_id, (int) $request->comun_area, $date, $request->time_from, $request->time_to)) {
                return $this->returnFail(409, 'Ya tienes una reserva activa que se superpone con el horario seleccionado. Cancela la reserva existente para poder crear una nueva.');
            }

            if (! $this->hasAvailableSlots($request->comun_area, $date, $request->time_from, $request->time_to, $request->exclusive)) {
                return $this->returnFail(409, 'No hay cupos disponibles para el horario seleccionado.');
            }

            $booking = Booking::create([
                'user_id' => $user->id,
                'departament_id' => $departament_id,
                'comun_area_id' => $request->comun_area,
                'booking_number' => $request->user()->id.'00'.rand(1000, 9999),
                'date' => date('Y-m-d', strtotime($request->date)),
                'time_from' => $request->time_from,
                'time_to' => $request->time_to,
                'amount' => $request->amount,
                'type' => $request->typeOfReserve,
                'note' => $request->note,
                'status' => ($request->typeOfReserve == 1 && $request->amount == 0) ? 3 : 1,
                'is_exclusive' => $request->exclusive,
            ]);

            $lo = $this->createEventIfPublicCine($booking);
        } catch (Exception $th) {
            return $this->returnFail(500, $th->getMessage());
        }
        $this->sendNotification($booking);

        return $this->returnSuccess(200, [
            'toPay' => ((! ($booking->type == 1) || $booking->amount > 0) && $request->pay_later == false),
            'id' => $booking->id,
            'cine' => $lo,
        ]);
    }

    public function getBookingsByUser(Request $request)
    {
        $bookings = Booking::with('comunArea', 'user', 'pay', 'departament');
        if ($request->user()->rol_id !== Rol::ADMIN) {
            $bookings->where('user_id', $request->user()->id)->orderBy('date', 'asc');
        } elseif ($request->filled('user_id')) {
            $bookings->where('user_id', (int) $request->user_id);
        }
        $this->applyFilter($bookings, $request);

        $perPage = $request->integer('per_page', 10);

        return $this->returnSuccess(200, $bookings->paginate($perPage));
    }

    public function getBookingsByDepartment(Request $request, int $departamentId)
    {
        $user = $request->user();

        $ownedIds = $user->apartaments()->pluck('id');
        $residentIds = PeoplesXDepartaments::where('user_id', $user->id)->pluck('departament_id');
        $allowedIds = $ownedIds->merge($residentIds)->unique()->values();

        if (! $allowedIds->contains($departamentId)) {
            return $this->returnFail(403, 'No tienes permisos para ver las reservas de este departamento');
        }

        $bookings = Booking::with('comunArea')
            ->where('departament_id', $departamentId)
            ->where('status', '>', 0)
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date', 'asc')
            ->orderBy('time_from', 'asc')
            ->get();

        return $this->returnSuccess(200, $bookings);
    }

    public function getBookingById($id)
    {
        $booking = Booking::with('comunArea', 'user', 'pay.payMethod', 'departament')->find($id);
        if (! $booking) {
            return $this->returnFail(404, 'Reserva no encontrada');
        }
        $user = request()->user();
        if ($user->rol_id !== Rol::TRABAJADOR && ! $this->verifyBookingOwnership($booking)) {
            return $this->returnFail(403, 'No autorizado');
        }

        return $this->returnSuccess(200, $booking);
    }

    public function getBookingByAreaId(Request $request, $areaId)
    {
        $bookings = Booking::with('pay.payMethod', 'user')->where('comun_area_id', $areaId);

        if ($request->filled('date_from')) {
            $bookings->whereDate('date', '>=', $request->get('date_from'));
        }
        if ($request->filled('date_to')) {
            $bookings->whereDate('date', '<=', $request->get('date_to'));
        }
        if ($request->filled('status')) {
            $bookings->where('status', $request->integer('status'));
        } else {
            $bookings->where('status', '>', 0);
        }

        $bookings->orderBy('date', 'desc')->orderBy('time_from', 'asc');

        $perPage = $request->integer('per_page', 10);

        return $this->returnSuccess(200, $bookings->paginate($perPage));
    }

    private function applyFilter($query, Request $request)
    {
        $VIEW_ALL_STATUS = -1;
        $FREE_AMOUNT = 0;
        if ($request->filled('status')) {
            $statusParam = $request->get('status');
            if ($statusParam == $VIEW_ALL_STATUS) {
                // Status -1 = todos (incluye cancelados)
            } else {
                $statuses = array_map('intval', explode(',', $statusParam));
                $query->whereIn('status', $statuses);
            }
        } else {
            $query->where('status', '>', 0);
        }
        if ($request->filled('area_id')) {
            $query->where('comun_area_id', intval($request->area_id));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('date', '>=', $request->get('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date', '<=', $request->get('date_to'));
        }
        if ($request->filled('amount_type')) {
            if ($request->amount_type === 'free') {
                $query->where('amount', $FREE_AMOUNT);
            } elseif ($request->amount_type === 'paid') {
                $query->where('amount', '>', $FREE_AMOUNT);
            }
        }

        $sortBy = in_array($request->get('sort_by'), ['created_at', 'date', 'status', 'amount']) ? $request->get('sort_by') : 'created_at';
        $sortDir = $request->get('sort_dir') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);
    }

    public function updateBooking(Request $request, $id)
    {
        $validated = $this->validateFieldsFromInput($request->all());
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }

        $booking = Booking::find($id);
        if (! $booking) {
            return $this->returnFail(400, 'Reserva no encontrada');
        }
        if (! $this->verifyBookingOwnership($booking)) {
            return $this->returnFail(403, 'No autorizado');
        }
        $booking->update($request->only(['date', 'time_from', 'time_to', 'note', 'amount']));

        return $this->returnSuccess(200, 'ok');
    }

    public function deleteBooking($id)
    {
        $booking = Booking::find($id);
        if (! $booking) {
            return $this->returnFail(400, 'Reserva no encontrada');
        }
        if (! $this->verifyBookingOwnership($booking)) {
            return $this->returnFail(403, 'No autorizado');
        }
        $booking->delete();

        return $this->returnSuccess(200, 'ok');
    }

    public function cancelBooking(Request $request, $id)
    {

        $booking = Booking::with('pay')->find($id);
        if (! $booking) {
            return $this->returnFail(400, 'Reserva no encontrada');
        }
        if (! $this->verifyBookingOwnership($booking)) {
            return $this->returnFail(403, 'No autorizado');
        }

        $isPaidBookingNotCompleted = (float) $booking->amount > 0
            && (int) $booking->status !== Booking::STATUS_COMPLETED;

        DB::beginTransaction();

        try {
            $pay = $booking->pay;

            if ($isPaidBookingNotCompleted && $pay && in_array((int) $pay->status, [1, 2, 4])) {
                $booking->update([
                    'status' => Booking::STATUS_PENDING_DEVO,
                    'motive' => $request->motive,
                    'kind' => 'cancellation',
                ]);
                $pay->update(['status' => 6]);

                DB::commit();
                $this->cancelReserveNotification([
                    'admin' => User::find(1),
                    'client' => User::find($booking->user_id),
                ], $booking);

                return $this->returnSuccess(200, 'ok');
            }

            $booking->update([
                'status' => Booking::STATUS_CANCELLED,
                'motive' => $request->motive,
            ]);
            DB::commit();
            $this->sendNotification($booking);

            return $this->returnSuccess(200, 'ok');
        } catch (Exception $e) {
            DB::rollBack();

            return $this->returnFail(500, 'Error al procesar la cancelación: '.$e->getMessage());
        }
    }

    public function getBookingsForSecurity(Request $request)
    {
        $bookings = Booking::with('comunArea', 'user', 'pay', 'departament');
        $this->applyFilter($bookings, $request);

        return $this->returnSuccess(200, $bookings->get());
    }

    public function cancelBookingForMaintenance(Request $request, $id)
    {
        $booking = Booking::find($id);
        if (! $booking) {
            return $this->returnFail(400, 'Reserva no encontrada');
        }
        $booking->update([
            'status' => 0,
            'motive' => 'Cancelada por mantenimiento',
        ]);
        $this->sendNotification($booking);

        return $this->returnSuccess(200, 'ok');
    }

    public function completeBooking(Request $request, $id)
    {
        $booking = Booking::with('pay')->find($id);
        if (! $booking) {
            return $this->returnFail(400, 'Reserva no encontrada');
        }
        if (! in_array($booking->status, [
            Booking::STATUS_PENDING_PAY,
            Booking::STATUS_PENDING_APPROVAL,
            Booking::STATUS_SUCCESS,
        ])) {
            return $this->returnFail(400, 'La reserva no está en un estado válido para completarse');
        }

        $needsRefund = (float) ($booking->comunArea?->warranty_price ?? 0) > 0
            && $booking->pay != null
            && $booking->pay->status == 2;

        $booking->update([
            'status' => $needsRefund ? Booking::STATUS_PENDING_DEVO : Booking::STATUS_COMPLETED,
            'kind' => $needsRefund ? 'warranty' : null,
        ]);
        $this->sendNotification($booking);

        return $this->returnSuccess(200, 'ok');
    }

    public function getAvaibleBookingByDay(Request $request, $idArea)
    {
        date_default_timezone_set('America/Lima');

        $area = ComunArea::find($idArea);
        if (! $area) {
            return $this->returnFail(404, 'Área no encontrada');
        }

        $dateStr = date('Y-m-d', strtotime($request->date));
        $isToday = $dateStr === date('Y-m-d');

        $carbonDate = Carbon::parse($dateStr);
        $dayOfWeek = $carbonDate->dayOfWeek;

        $schedules = ComunAreaSchedule::where('comun_area_id', $idArea)
            ->where('day', $dayOfWeek)
            ->get();

        $bookingsInDay = Booking::where('comun_area_id', $idArea)
            ->where('date', $dateStr)
            ->where('status', '>', 0)
            ->get();

        $maintenancesInDay = Maintenance::where('comun_area_id', $idArea)
            ->where('date', $dateStr)
            ->where('status', 1)
            ->get(['time_from', 'time_to']);

        $intervalSize = $request->reserve_type == 2 ? $area->max_time_reserve_exclusive : $area->max_time_reserve;

        $blocks = [
            'ma' => [],
            'ta' => [],
            'no' => [],
        ];
        $mm = [];
        if ($schedules->isEmpty()) {
            return $this->returnSuccess(200, [
                'blocks' => $blocks,
            ]);
        }
        $currentCarbonNow = Carbon::now()->setTimezone('America/lima');
        $isCine = str_contains(strtolower($area->name), 'cine');
        if ($isToday && $isCine) {
            $currentCarbonNow->addHours(5);
        }

        foreach ($schedules as $schedule) {
            $scheduleTimeStart = Carbon::parse($schedule->time_from);
            $scheduleTimeEnd = Carbon::parse($schedule->time_to);

            $time = $scheduleTimeStart->copy();
            while ($time->lessThan($scheduleTimeEnd)) {
                $timeInitInterval = $time->copy();
                $slotEnd = $time->copy()->addHours($intervalSize);
                if ($slotEnd->greaterThan($scheduleTimeEnd)) {
                    $slotEnd = $scheduleTimeEnd->copy();
                }

                if ($isToday && $slotEnd->lessThanOrEqualTo($currentCarbonNow)) {
                    $time = $slotEnd->copy();

                    continue;
                }
                $availability = $this->calculateIntervalAvailability($timeInitInterval, $slotEnd, $area->max_cupo ?? 100, $bookingsInDay);
                $intervalData = [
                    'time_from' => $timeInitInterval->format('H:i'),
                    'time_to' => $slotEnd->format('H:i'),
                    'capacity' => $area->max_cupo,
                    'occupancy' => ($area->max_cupo ?? 100) - $availability['spots'],
                    'available' => $availability['spots'],
                    'status' => $availability['status'],
                ];
                $blockCategory = $this->getTimeBlockCategory((int) $timeInitInterval->format('H'));
                $blocks[$blockCategory][] = $intervalData;
                $time = $slotEnd->copy();
            }
        }

        // Marcar como ocupados los intervalos que coincidan con mantenimiento
        foreach ($blocks as $category => $intervals) {
            foreach ($intervals as $i => $interval) {
                $iStart = Carbon::parse($interval['time_from']);
                $iEnd = Carbon::parse($interval['time_to']);
                foreach ($maintenancesInDay as $m) {
                    if (is_null($m->time_from) || is_null($m->time_to)) {
                        $blocks[$category][$i]['available'] = 0;
                        $blocks[$category][$i]['occupancy'] = $blocks[$category][$i]['capacity'];
                        $blocks[$category][$i]['status'] = 'Ocupado';
                        $blocks[$category][$i]['maintenance'] = true;
                        break;
                    }
                    $mStart = Carbon::parse($m->time_from);
                    $mEnd = Carbon::parse($m->time_to);
                    if ($iStart->lessThan($mEnd) && $iEnd->greaterThan($mStart)) {
                        $blocks[$category][$i]['available'] = 0;
                        $blocks[$category][$i]['occupancy'] = $blocks[$category][$i]['capacity'];
                        $blocks[$category][$i]['status'] = 'Ocupado';
                        $blocks[$category][$i]['maintenance'] = true;
                        break;
                    }
                }
            }
        }

        return $this->returnSuccess(200, [
            'blocks' => $blocks,
            'maintenances' => $maintenancesInDay,
        ]);
    }

    /**
     * Calcula los cupos disponibles y el estado de un intervalo de tiempo específico.
     */
    private function calculateIntervalAvailability(Carbon $slotStart, Carbon $slotEnd, int $capacity, $bookings): array
    {
        $occupancy = 0;

        foreach ($bookings as $booking) {
            $bStart = Carbon::parse($booking->time_from);
            $bEnd = Carbon::parse($booking->time_to);

            if ($bStart->lessThan($slotEnd) && $bEnd->greaterThan($slotStart)) {
                if ($booking->is_exclusive) {
                    $occupancy = $capacity;
                    break; // Saturación total por exclusividad, paramos la iteración
                }
                $occupancy++;
            }
        }
        $availableSpots = max(0, $capacity - $occupancy);

        $status = 'Disponible';
        if ($availableSpots == 0) {
            $status = 'Ocupado';
        } elseif ($availableSpots > 0 && $availableSpots <= max(1, round($capacity * 0.3))) {
            $status = 'Últimos';
        }

        return [
            'spots' => $availableSpots,
            'status' => $status,
        ];
    }

    /**
     * Devuelve la categoría del bloque de tiempo (mañana, tarde, noche) basado en la hora.
     */
    private function getTimeBlockCategory(int $hora): string
    {
        if ($hora < 12) {
            return 'ma';
        }
        if ($hora >= 12 && $hora < 18) {
            return 'ta';
        }

        return 'no';
    }

    public function getExtensionSlots(Request $request, int $bookingId)
    {
        $booking = Booking::with('comunArea')->find($bookingId);

        if (! $booking) {
            return $this->returnFail(404, 'Reserva no encontrada');
        }

        if (! $this->verifyBookingOwnership($booking)) {
            return $this->returnFail(403, 'No autorizado');
        }

        if ($booking->status != Booking::STATUS_SUCCESS) {
            return $this->returnFail(400, 'Solo se pueden extender reservas exitosas');
        }

        $area = $booking->comunArea;

        if (! $area || ! $area->has_extension) {
            return $this->returnFail(400, 'Este área no permite extensiones de tiempo');
        }

        $existingExtension = $this->existingExtension($area, $booking);

        if ($existingExtension) {
            return $this->returnFail(400, 'Ya se solicitó una extensión para esta reserva');
        }

        $intervalSize = $area->max_time_reserve ?? 1;
        $maxExtension = $area->max_time_extension ?? 0;
        $dateStr = $booking->date->format('Y-m-d');
        $isToday = $dateStr === now()->format('Y-m-d');

        $carbonDate = Carbon::parse($dateStr);
        $dayOfWeek = $carbonDate->dayOfWeek;

        $schedules = ComunAreaSchedule::where('comun_area_id', $area->id)
            ->where('day', $dayOfWeek)
            ->get();

        if ($schedules->isEmpty()) {
            return $this->returnSuccess(200, ['slots' => []]);
        }

        $bookingsInDay = Booking::where('comun_area_id', $area->id)
            ->where('date', $dateStr)
            ->where('status', '>', 0)
            ->where('id', '!=', $bookingId)
            ->get();

        $bookingTimeFrom = Carbon::parse($booking->time_from);
        $bookingTimeTo = Carbon::parse($booking->time_to);

        $slots = [];

        foreach ($schedules as $schedule) {
            $scheduleTimeStart = Carbon::parse($schedule->time_from);
            $scheduleTimeEnd = Carbon::parse($schedule->time_to);

            $time = $scheduleTimeStart->copy();
            while ($time->lessThan($scheduleTimeEnd)) {
                $slotStart = $time->copy();
                $slotEnd = $time->copy()->addHours($intervalSize);

                if ($slotEnd->greaterThan($scheduleTimeEnd)) {
                    $slotEnd = $scheduleTimeEnd->copy();
                }

                if ($isToday && $slotStart->lessThanOrEqualTo(now()->setTimezone('America/Lima'))) {
                    $time = $slotEnd->copy();

                    continue;
                }

                $isBefore = $slotEnd->equalTo($bookingTimeFrom);
                $isAfter = $slotStart->equalTo($bookingTimeTo);

                if ($isBefore || $isAfter) {
                    $availability = $this->calculateIntervalAvailability($slotStart, $slotEnd, $area->max_cupo ?? 100, $bookingsInDay);

                    $slots[] = [
                        'time_from' => $slotStart->format('H:i'),
                        'time_to' => $slotEnd->format('H:i'),
                        'position' => $isBefore ? 'before' : 'after',
                        'available' => $availability['spots'],
                        'status' => $availability['status'],
                        'duration' => $slotEnd->diffInHours($slotStart),
                    ];
                }

                $time = $slotEnd->copy();
            }
        }

        $slots = array_filter($slots, fn ($s) => $s['status'] !== 'Ocupado');

        if ($maxExtension > 0) {
            $currentHours = $booking->booking_hour;
            $slots = array_filter($slots, fn ($s) => ($currentHours + $s['duration']) <= $maxExtension);
        }

        return $this->returnSuccess(200, [
            'booking' => $booking,
            'area' => $area,
            'slots' => array_values($slots),
        ]);
    }

    public function storeExtension(Request $request)
    {
        try {
            $validated = $request->validate([
                'booking_id' => ['required', 'integer', 'exists:bookings,id'],
                'time_from' => ['required', 'string'],
                'time_to' => ['required', 'string'],
            ], [
                'booking_id.required' => 'La reserva original es requerida.',
                'booking_id.exists' => 'La reserva original no existe.',
                'time_from.required' => 'El horario de inicio es requerido.',
                'time_to.required' => 'El horario de fin es requerido.',
            ]);
        } catch (ValidationException $e) {
            return $this->returnFail(422, $e->validator->errors()->first());
        }

        $originalBooking = Booking::with('comunArea')->find($validated['booking_id']);

        if (! $originalBooking) {
            return $this->returnFail(404, 'Reserva original no encontrada');
        }

        if (! $this->verifyBookingOwnership($originalBooking)) {
            return $this->returnFail(403, 'No autorizado');
        }

        if ($originalBooking->status != Booking::STATUS_SUCCESS) {
            return $this->returnFail(400, 'Solo se pueden extender reservas exitosas');
        }

        $area = $originalBooking->comunArea;

        if (! $area || ! $area->has_extension) {
            return $this->returnFail(400, 'Este área no permite extensiones');
        }

        $existingExtension = $this->existingExtension($area, $originalBooking);

        if ($existingExtension) {
            return $this->returnFail(400, 'Ya se solicitó una extensión para esta reserva');
        }

        $hours = Carbon::parse($validated['time_to'])->diffInHours(Carbon::parse($validated['time_from']));
        $amount = $area->extension_price;

        $bookingType = $originalBooking->type;
        $newStatus = $bookingType == 1 ? Booking::STATUS_SUCCESS : Booking::STATUS_PENDING_PAY;

        $newBooking = Booking::create([
            'user_id' => $originalBooking->user_id,
            'departament_id' => $originalBooking->departament_id,
            'comun_area_id' => $originalBooking->comun_area_id,
            'booking_number' => $originalBooking->user_id.'00'.rand(1000, 9999),
            'date' => $originalBooking->date,
            'time_from' => $validated['time_from'],
            'time_to' => $validated['time_to'],
            'amount' => $amount,
            'type' => 4,
            'note' => 'Extensión de reserva #'.$originalBooking->booking_number,
            'status' => $newStatus,
            'is_exclusive' => $originalBooking->is_exclusive,
        ]);

        $this->sendNotification($newBooking);

        return $this->returnSuccess(200, [
            'id' => $newBooking->id,
            'toPay' => $newStatus == Booking::STATUS_PENDING_PAY,
        ]);
    }

    public function getPendings()
    {
        $user = request()->user();
        if ($user->rol_id !== Rol::ADMIN) {
            return $this->returnFail(403, 'No autorizado');
        }

        $waitStatus = 2;
        $pendings = Booking::where('status', $waitStatus)->get();

        return $this->returnSuccess(200, $pendings);
    }

    private function validateFieldsFromInput($inputs)
    {
        $rules = [
            'comun_area' => ['required', 'numeric'],
            'date' => ['required', 'date'],
            'time_from' => ['required', 'regex:/^[0-9 : &]+$/i'],
            'time_to' => ['required', 'regex:/^[0-9 : &]+$/i'],
            'amount' => ['required', 'numeric'],
        ];
        $messages = [
            'comun_area.required' => 'El area común es requerida',
            'comun_area.numeric' => 'El area común no es valido',
            'date.required' => 'La fecha es requerida',
            'date.date' => 'La fecha no es valida',
            'time_from.required' => 'El horario de inicio es requerido',
            'time_from.regex' => 'El horario de inicio no es valido',
            'time_to.required' => 'El horario de fin es requerido',
            'time_to.regex' => 'El horario de fin no es valido',
            'amount.required' => 'El monto es requerido',
            'amount.numeric' => 'El monto no es valido',
        ];

        $validator = Validator::make($inputs, $rules, $messages)->errors();

        return $validator->all();
    }

    private function verifyBookingOwnership(Booking $booking): bool
    {
        $user = request()->user();

        return $booking->user_id === $user->id || $user->rol_id === Rol::ADMIN;
    }

    private function hasActiveReservationForDepartment(int $departamentId, int $comunAreaId): bool
    {
        return Booking::where('departament_id', $departamentId)
            ->where('comun_area_id', $comunAreaId)
            ->where('date', '>=', now()->toDateString())
            ->where('status', '>', 0)
            ->exists();
    }

    private function hasOverlappingBookingForDepartment(int $departamentId, int $comunAreaId, string $date, string $timeFrom, string $timeTo): bool
    {
        $bookings = Booking::where('departament_id', $departamentId)
            ->where('date', $date)
            ->where('status', '>', 0)
            ->where('time_from', '<', $timeTo)
            ->where('time_to', '>', $timeFrom)
            ->get();

        foreach ($bookings as $booking) {
            if ((int) $booking->comun_area_id === $comunAreaId) {
                return true;
            }
            if ($booking->is_exclusive) {
                return true;
            }
        }

        return false;
    }

    private function hasAvailableSlots(int $comunAreaId, string $date, string $timeFrom, string $timeTo, int $isExclusive): bool
    {
        $area = ComunArea::find($comunAreaId);
        if (! $area) {
            return false;
        }

        $capacity = $area->max_cupo ?? 100;

        $hasMaintenance = Maintenance::where('comun_area_id', $comunAreaId)
            ->where('date', $date)
            ->where('status', 1)
            ->where(function ($q) use ($timeFrom, $timeTo) {
                $q->whereNull('time_from')
                    ->orWhereNull('time_to')
                    ->orWhere(function ($q2) use ($timeFrom, $timeTo) {
                        $q2->where('time_from', '<', $timeTo)
                            ->where('time_to', '>', $timeFrom);
                    });
            })
            ->exists();

        if ($hasMaintenance) {
            return false;
        }

        $bookingsInSlot = Booking::where('comun_area_id', $comunAreaId)
            ->where('date', $date)
            ->where('status', '>', 0)
            ->where('time_from', '<', $timeTo)
            ->where('time_to', '>', $timeFrom)
            ->get();

        $occupancy = 0;
        foreach ($bookingsInSlot as $booking) {
            if ($booking->is_exclusive) {
                return false;
            }
            $occupancy++;
        }

        if ($isExclusive) {
            return $occupancy === 0;
        }

        return $occupancy < $capacity;
    }

    private function sendNotification($booking)
    {
        $users = [
            'admin' => User::find(1),
            'client' => User::find($booking->user_id),
        ];

        if ($booking->status == 0) {
            $this->cancelReserveNotification($users, $booking);

            return;
        }
        if (in_array($booking->status, [
            Booking::STATUS_COMPLETED,
            Booking::STATUS_PENDING_REFUND,
            Booking::STATUS_PENDING_DEVO,
        ])) {
            self::completeReserveNotification($users, $booking);

            return;
        }
        $this->successReserveNotification($users, $booking);
    }

    public static function completeReserveNotification($users, $booking)
    {
        $isPendingRefund = in_array((int) $booking->status, [
            Booking::STATUS_PENDING_REFUND,
            Booking::STATUS_PENDING_DEVO,
        ]);

        try {
            $users['client']->notify(new RealtimeNotification(
                title: $isPendingRefund ? 'Reserva completada - reembolso pendiente' : 'Reserva completada',
                message: $isPendingRefund
                    ? 'Tu reserva #'.$booking->booking_number.' fue completada. El reembolso será registrado por administración.'
                    : 'Tu reserva #'.$booking->booking_number.' fue completada.',
                url: '/client/reserves/view/'.$booking->id,
                meta: [
                    'booking_id' => $booking->id,
                    'icon' => $booking->icon_status,
                ]
            ));

            if ($users['admin']) {
                $users['admin']->notify(new RealtimeNotification(
                    title: $isPendingRefund ? 'Reserva completada - reembolso pendiente' : 'Reserva completada',
                    message: $isPendingRefund
                        ? 'La reserva #'.$booking->booking_number.' fue completada. Requiere reembolso.'
                        : 'La reserva #'.$booking->booking_number.' fue completada.',
                    url: '/admin/reserves',
                    meta: [
                        'booking_id' => $booking->id,
                        'icon' => $booking->icon_status,
                    ]
                ));
            }
        } catch (Exception $e) {
            Log::error('Fallo al enviar notificación de reserva completada: '.$e->getMessage());
        }
    }

    private function successReserveNotification($users, $booking)
    {
        try {
            $users['client']->notify(new RealtimeNotification(
                title: 'Reserva creada',
                message: 'Tu reserva #'.$booking->booking_number.' fue creada.',
                url: '/client/reserves/view/'.$booking->id,
                meta: [
                    'booking_id' => $booking->id,
                    'icon' => $booking->icon_status,
                ]
            ));

            if ($users['admin']) {
                $users['admin']->notify(new RealtimeNotification(
                    title: 'Nueva reserva',
                    message: 'Se creó la reserva #'.$booking->booking_number.'.',
                    url: '/client/reserves/view/'.$booking->id,
                    meta: [
                        'booking_id' => $booking->id,
                        'icon' => $booking->icon_status,
                    ]
                ));
            }
        } catch (Exception $e) {
            Log::error('Fallo al enviar notificación de reserva: '.$e->getMessage());
        }
    }

    private function pedingToPayReserveNotification($users, $booking)
    {
        BookingPendingPayNotifier::notify($booking);
    }

    public static function cancelReserveNotification($users, $booking)
    {
        $isPendingDevo = (int) $booking->status === Booking::STATUS_PENDING_DEVO;

        try {
            $users['client']->notify(new RealtimeNotification(
                title: $isPendingDevo ? 'Reserva cancelada - devolución pendiente' : 'Reserva cancelada',
                message: $isPendingDevo
                    ? 'Tu reserva #'.$booking->booking_number.' fue cancelada. La devolución será registrada por administración.'
                    : 'Tu reserva #'.$booking->booking_number.' fue cancelada.',
                url: '/client/reserves/view/'.$booking->id,
                meta: [
                    'booking_id' => $booking->id,
                    'icon' => $booking->icon_status,
                ]
            ));

            if ($users['admin']) {
                $users['admin']->notify(new RealtimeNotification(
                    title: $isPendingDevo ? 'Reserva cancelada - devolución pendiente' : 'Reserva cancelada',
                    message: $isPendingDevo
                        ? 'Se canceló la reserva #'.$booking->booking_number.'. Requiere devolución.'
                        : 'Se canceló la reserva #'.$booking->booking_number.'.',
                    url: '/admin/reserves',
                    meta: [
                        'booking_id' => $booking->id,
                        'icon' => $booking->icon_status,
                    ]
                ));
            }
        } catch (Exception $e) {
            Log::error('Fallo al enviar notificación de reserva: '.$e->getMessage());
        }
    }

    private function createEventIfPublicCine($booking)
    {
        $area = ComunArea::find($booking->comun_area_id);
        $isCine = $area && str_contains(strtolower($area->name), 'cine');

        if ($isCine && $booking->type == 1) {
            // Creamos el evento y lo atamos a la reserva recién creada
            $event = Event::create([
                'title' => 'Cine: '.$booking->note, // El nombre de la película
                'description' => 'Proyección compartida en el área de Cine para el día '.date('d/m/Y', strtotime($booking->date)).'. ¡Todos los residentes están invitados! ',
                'date' => date('Y-m-d', strtotime($booking->date)),
                'time_from' => $booking->time_from,
                'time_to' => $booking->time_to,
                'location' => $area->name,
                'booking_id' => $booking->id,
            ]);

            // Notificamos a los usuarios sobre el nuevo evento
            $this->sendEventCreatedNotification($event, $booking->user_id);
        }

        return $area;
    }

    private function sendEventCreatedNotification($event, $creatorId)
    {
        $users = User::where('rol_id', 2)->get();
        $creator = User::find($creatorId);

        if ($creator && ! $users->contains('id', $creator->id)) {
            $users->push($creator);
        }

        $dataNotificaction = [
            'title' => 'Nuevo evento programado',
            'message' => $event->title.', fue programado. Entra y confirma tu asistencia.',
            'url' => '/client/events/view/'.$event->id,
            'meta' => ['event_id' => $event->id],
        ];

        try {
            foreach ($users as $user) {
                $user->notify(new RealtimeNotification(
                    title: $dataNotificaction['title'],
                    message: $dataNotificaction['message'],
                    url: $dataNotificaction['url'],
                    meta: $dataNotificaction['meta'],
                ));
            }
        } catch (Exception $e) {
            Log::error('Fallo al enviar notificación de evento automático: '.$e->getMessage());
        }
    }

    private function existingExtension($area, $booking)
    {
        return Booking::where('type', 4)
            ->where('comun_area_id', $area->id)
            ->where('date', $booking->date)
            ->where('user_id', $booking->user_id)
            ->where('status', '>', 0)
            ->where('note', 'like', '%'.$booking->booking_number.'%')
            ->exists();
    }
}
