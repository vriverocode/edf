<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ComunArea;
use App\Models\ComunAreaSchedule;
use App\Models\PeoplesXDepartaments;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use App\Services\BookingPendingPayNotifier;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    //
    public function storeBooking(Request $request)
    {
        $validated = $this->validateFieldsFromInput($request->all());
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }

        try {
            $user = $request->user();
            if ($user->rol_id == 1 || $user->rol_id == 6 || $user->rol_id == 7) {
                return $this->returnFail(400, ['Usuario no valido', $user] );
            }
            if ($user->status !== 1) {
                return $this->returnFail(400, ['Usuario inactivo', $user] );
            }
            $departament_id = $request->departament_id;

            if (!$departament_id && ($user->rol_id == 5 || $user->rol_id == 4)) {
                $residentRecord = PeoplesXDepartaments::where('user_id', $user->id)
                    ->where('type', 5)
                    ->orWhere('type', 4)
                    ->first();
                if ($residentRecord) {
                    $departament_id = $residentRecord->departament_id;
                }
            }

            $booking = Booking::create([
                'user_id' => $user->id,
                'departament_id' => $departament_id,
                'comun_area_id' => $request->comun_area,
                'booking_number' => $request->user()->id . '00' . rand(1000, 9999),
                'date' => date('Y-m-d', strtotime($request->date)),
                'time_from' => $request->time_from,
                'time_to' => $request->time_to,
                'amount' => $request->amount,
                'type' => $request->typeOfReserve,
                'note' => $request->note,
                'status' =>  $request->typeOfReserve == 1 ? 3 : 1,
                'is_exclusive' => $request->exclusive
            ]);
        } catch (Exception $th) {
            return $this->returnFail(500, "Error al intentar crear reservación");
        }
        $this->sendNotification($booking);

        return $this->returnSuccess(200, ['toPay' => (!($booking->type == 1) && $request->pay_later == false), 'id' => $booking->id]);
    }

    public function getBookingsByUser(Request $request)
    {
        $bookings = Booking::with('comunArea', 'user', 'pay');
        if ($request->user()->id != 1) {
            $bookings->where('user_id', $request->user()->id)->orderBy('date', 'asc');
        }
        $this->applyFilter($bookings, $request);
        return $this->returnSuccess(200, $bookings->get());
    }

    public function getBookingById($id)
    {
        $booking = Booking::with('comunArea', 'user', 'pay')->find($id);
        return $this->returnSuccess(200, $booking);
    }
    public function getBookingByAreaId($areaId)
    {

        $bookings = Booking::with('pay', 'user')->where('comun_area_id', $areaId)->orderBy("created_at", "desc");
        return $this->returnSuccess(200, $bookings->get());
    }
    private function applyFilter($query, Request $request)
    {
        $VIEW_ALL_STATUS = 4;
        $FREE_AMOUNT = 0;
        if ($request->filled('status') && intval($request->status) !== $VIEW_ALL_STATUS) {
            $query->where('status', intval($request->status));
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

        $sortBy = in_array($request->get('sort_by'), ['created_at','date','status','amount']) ? $request->get('sort_by') : 'created_at';
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
        if (!$booking) {
            return $this->returnFail(400, 'Reserva no encontrada');
        }
        $booking->update($request->all());
        return $this->returnSuccess(200, 'ok');
    }

    public function deleteBooking($id)
    {
        $booking = Booking::find($id);
        if (!$booking) {
            return $this->returnFail(400, 'Reserva no encontrada');
        }
        $booking->delete();
        return $this->returnSuccess(200, 'ok');
    }

    public function cancelBooking(Request $request, $id)
    {

        $booking = Booking::find($id);
        if (!$booking) {
            return $this->returnFail(400, "Reserva no encontrada");
        }
        $booking->update([
            "status" => 0,
            "motive" => $request->motive

        ]);
        $this->sendNotification($booking);

        return $this->returnSuccess(200, 'ok');
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
        if (!$booking) {
            return $this->returnFail(400, "Reserva no encontrada");
        }
        $booking->update([
            "status" => 0,
            "motive" => "Cancelada por mantenimiento"
        ]);
        $this->sendNotification($booking);

        return $this->returnSuccess(200, 'ok');
    }

    public function getAvaibleBookingByDay(Request $request, $idArea)
    {
        date_default_timezone_set('America/Lima');

        $area = ComunArea::find($idArea);
        if (!$area) {
            return $this->returnFail(404, 'Área no encontrada');
        }

        $dateStr = date('Y-m-d', strtotime($request->date));
        $isToday = $dateStr === date('Y-m-d');
        
        // Obtenemos el día de la semana (0 = Domingo, 6 = Sábado)
        $carbonDate = Carbon::parse($dateStr);
        $dayOfWeek = $carbonDate->dayOfWeek;

        // 1. Traemos los turnos configurados para ESE día específico
        $schedules = ComunAreaSchedule::where('comun_area_id', $idArea)
            ->where('day', $dayOfWeek)
            ->get();

        // 2. Solo traemos reservas activas del día
        $bookingsInDay = Booking::where('comun_area_id', $idArea)
            ->where('date', $dateStr)
            ->where('status', '>', 0)
            ->get();

        $intervalSize = $area->max_time_reserve ?? 1; // Tamaño del bloque en horas

        $blocks = [
            'ma' => [],
            'ta' => [],
            'no' => []
        ];
        $mm = [];
        $currentCarbonNow = Carbon::now('America/lima');

        // Si el área no tiene horarios ese día, devolvemos todo cerrado
        if ($schedules->isEmpty()) {
            return $this->returnSuccess(200, [
                'blocks' => $blocks,
                'ss' => $mm,
            ]);
        }

        // 3. Iteramos por cada turno del día (Ej: Turno 1: 08:00 - 12:00, Turno 2: 14:00 - 18:00)
        foreach ($schedules as $schedule) {
            $scheduleTimeStart = Carbon::parse($schedule->time_from);
            $scheduleTimeEnd = Carbon::parse($schedule->time_to);

            $time = $scheduleTimeStart->copy();

            // Usamos un ciclo while que avanza mientras no lleguemos al cierre del turno
            while ($time->lessThan($scheduleTimeEnd)) {
                
                $timeInitInterval = $time->copy();
                // Calculamos el fin "ideal" del bloque sumando el max_time_reserve
                $slotEnd = $time->copy()->addHours($intervalSize);

                // TRUCO: Si el fin ideal supera el cierre del área, lo limitamos a la hora de cierre.
                // Así, un bloque de 21:00 + 4 horas (01:00) se recorta automáticamente a las 23:00.
                if ($slotEnd->greaterThan($scheduleTimeEnd)) {
                    $slotEnd = $scheduleTimeEnd->copy();
                }

                // Omitir intervalos pasados si el usuario está viendo el día actual
                if ($isToday && $timeInitInterval->lessThanOrEqualTo($currentCarbonNow)) {
                    $time = $slotEnd->copy(); // Avanzamos el puntero para la siguiente iteración
                    continue;
                }

                // Calculamos la disponibilidad para este bloque específico
                $availability = $this->calculateIntervalAvailability($timeInitInterval, $slotEnd, $area->max_cupo ?? 100, $bookingsInDay);
                array_push($mm, $availability);
                
                $intervalData = [
                    'time_from' => $timeInitInterval->format('H:i'), 
                    'time_to'   => $slotEnd->format('H:i'),
                    'capacity'  => $area->capacity,
                    'available' => $availability['spots'],
                    'status'    => $availability['status']
                ];

                // Determinamos si es mañana, tarde o noche
                $blockCategory = $this->getTimeBlockCategory((int)$timeInitInterval->format('H'));
                $blocks[$blockCategory][] = $intervalData;

                // Preparamos el $time para el siguiente ciclo, arrancando exactamente donde terminó este bloque
                $time = $slotEnd->copy();
            }
        }

        return $this->returnSuccess(200, [
            'blocks' => $blocks,
            'ss' => $mm,
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
            
            // Hay solapamiento si el inicio de la reserva es ANTES del fin del bloque
            // y el fin de la reserva es DESPUÉS del inicio del bloque.
            if ($bStart->lessThan($slotEnd) && $bEnd->greaterThan($slotStart)) {
                if ($booking->is_exclusive) {
                    $occupancy = $capacity;
                    break; // Saturación total por exclusividad, paramos la iteración
                }
                $occupancy++;
            }
        }

        // Asegura que los cupos nunca sean negativos
        $availableSpots = max(0, $capacity - $occupancy);

        $status = 'Disponible';
        if ($availableSpots == 0) {
            $status = 'Ocupado';
        } elseif ($availableSpots > 0 && $availableSpots <= max(1, round($capacity * 0.3))) {
            $status = 'Últimos';
        }

        return [
            'spots'  => $availableSpots,
            'status' => $status
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
    public function getPendings()
    {
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

        return $validator->all() ;
    }


    private function sendNotification($booking)
    {
        $users = [
            "admin" => User::find(1),
            "client" => User::find($booking->user_id),
        ];

        if ($booking->status == 0) {
            $this->cancelReserveNotification($users, $booking);
            return;
        }
        // if ($booking->status == 1) {
        //     $this->pedingToPayReserveNotification($users, $booking);
        //     return;
        // }

        $this->successReserveNotification($users, $booking);
    }
    private function successReserveNotification($users, $booking)
    {
        try {
            $users["client"]->notify(new RealtimeNotification(
                title: 'Reserva creada',
                message: 'Tu reserva #' . $booking->booking_number . ' fue creada.',
                url: '/client/reserves/view/' . $booking->id,
                meta: [
                        'booking_id' => $booking->id,
                        'icon' => $booking->icon_status
                    ]
            ));

            if ($users["admin"]) {
                $users["admin"]->notify(new RealtimeNotification(
                    title: 'Nueva reserva',
                    message: 'Se creó la reserva #' . $booking->booking_number . '.',
                    url: '/client/reserves/view/' . $booking->id,
                    meta: [
                        'booking_id' => $booking->id,
                        'icon' => $booking->icon_status
                    ]
                ));
            }
        } catch (\Throwable $e) {
            Log::error('Fallo al enviar notificación de reserva: ' . $e->getMessage());
        }
    }
    private function pedingToPayReserveNotification($users, $booking)
    {
        BookingPendingPayNotifier::notify($booking);
    }
    static public function cancelReserveNotification($users, $booking)
    {
        try {
            $users["client"]->notify(new RealtimeNotification(
                title: 'Reserva cancelada',
                message: 'Tu reserva #' . $booking->booking_number . ' fue cancelada.',
                url: '/client/reserves/view/' . $booking->id,
                meta: [
                    'booking_id' => $booking->id,
                    'icon' => $booking->icon_status
                ]
            ));

            if ($users["admin"]) {
                $users["admin"]->notify(new RealtimeNotification(
                    title: 'Reserva cancelada',
                    message: 'Se canceló la reserva #' . $booking->booking_number . '.',
                    url: '/admin/reserves',
                    meta: [
                        'booking_id' => $booking->id,
                        'icon' => $booking->icon_status
                    ]
                ));
            }
        } catch (\Throwable $e) {
            Log::error('Fallo al enviar notificación de reserva: ' . $e->getMessage());
        }
    }
}
