<?php

namespace App\Http\Controllers\Api;

use App\Models\Booking;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\ComunArea;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Notifications\RealtimeNotification;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Notification as NotificationFacade;

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
            $booking = Booking::create([
                'user_id' => $request->user()->id,
                'comun_area_id' => $request->comun_area,
                'booking_number' => $request->user()->id . '00' . rand(1000, 9999),
                'date' => date('Y-m-d', strtotime($request->date)),
                'time_from' => $request->time_from,
                'time_to' => $request->time_to,
                'amount' => $request->amount,
                'note' => $request->note,
                'status' => $request->amount > 0 ? 1 : 3,
                'is_exclusive' => $request->exclusive
            ]);
        } catch (Exception $th) {
            return $this->returnFail(500, "Error al intentar crear reservación");
        }
        $this->sendNotification($booking);

        return $this->returnSuccess(200, ['toPay' => $booking->amount > 0, 'id' => $booking->id]);
    }

    public function getBookingsByUser(Request $request)
    {
        $bookings = Booking::with('comunArea', 'user', 'pay');
        if ($request->user()->id != 1) {
            $bookings->where('user_id', $request->user()->id)->orderBy('date', 'desc');
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

    public function cancelBooking($id)
    {

        $booking = Booking::find($id);
        if (!$booking) {
            return $this->returnFail(400, "Reserva no encontrada");
        }
        $booking->update([
            "status" => 0
        ]);
        $this->sendNotification($booking);

        return $this->returnSuccess(200, 'ok');
    }
    public function getAvaibleBookingByDay(Request $request, $idArea)
    {
        // Configuramos la zona horaria
        $tz = 'America/Caracas';
        date_default_timezone_set($tz);

        $area = ComunArea::find($idArea);
        if (!$area) {
            return $this->returnFail(404, 'Área no encontrada');
        }

        $date = date('Y-m-d', strtotime($request->date));
        $isToday = $date === date('Y-m-d');
        $currentHour = (int) date('H');

        // Solo traemos reservas activas (status > 0, ya que 0 es "Cancelada")
        $bookingsInDay = Booking::where('comun_area_id', $idArea)
            ->where('date', $date)
            ->where('status', '>', 0)
            ->get();

        // Convertimos las horas de inicio y fin del área a enteros (ej: "09:00" -> 9)
        $startHour = (int) substr($area->timeFrom, 0, 2);
        $endHour = (int) substr($area->timeTo, 0, 2);
        
        $intervals = [];
        $intervalSize = 1; // Bloques de 1 hora (puedes vincularlo a $area->max_time_reserve si lo deseas)

        for ($h = $startHour; $h < $endHour; $h += $intervalSize) {
            $intervalStart = sprintf('%02d:00', $h);
            $intervalEnd = sprintf('%02d:00', $h + $intervalSize);

            // Si es hoy, no mostrar bloques de horas que ya pasaron
            if ($isToday && $h <= $currentHour) {
                continue; 
            }

            // Contar cuántos cupos están ocupados en este intervalo
            $occupancy = 0;
            foreach ($bookingsInDay as $booking) {
                $bStart = (int) substr($booking->time_from, 0, 2);
                $bEnd = (int) substr($booking->time_to, 0, 2);

                // Validamos si la reserva se cruza con este intervalo de tiempo
                if ($bStart < ($h + $intervalSize) && $bEnd > $h) {
                    if ($booking->is_exclusive) {
                        // Si la reserva es exclusiva, toma toda la capacidad
                        $occupancy = $area->capacity;
                        break; 
                    } else {
                        // Si no, suma 1 cupo ocupado
                        $occupancy++;
                    }
                }
            }

            // Calculamos los cupos disponibles
            $availableSpots = $area->capacity - $occupancy;
            if ($availableSpots < 0) $availableSpots = 0;

            // Determinamos el estado para enviarlo fácil al Frontend
            $status = 'Disponible';
            if ($availableSpots == 0) {
                $status = 'Ocupado';
            } elseif ($availableSpots > 0 && $availableSpots <= 2 && $area->capacity > 2) {
                $status = 'Últimos';
            }

            $intervals[] = [
                'time_from' => $intervalStart,
                'time_to'   => $intervalEnd,
                'capacity'  => $area->capacity,
                'available' => $availableSpots,
                'status'    => $status
            ];
        }

        return $this->returnSuccess(200, [
            'intervals' => $intervals
        ]);
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
    private function setRangeAvailableBooking($date, $area)
    {

        if ($date == date('Y-m-d')) {
            $hour = date('H'); // get formatDate
            return range(intval($hour + 1), $this->formatTimeForAvailable($area->timeTo)); // set default available hours
        }
        return range($this->formatTimeForAvailable($area->timeFrom), $this->formatTimeForAvailable($area->timeTo));
    }
    private function filterAvailableTimeBooking($bookingInDay, $area)
    {
        if ($area->type == 2) {
            return $this->filterIsExclusiveArea($bookingInDay);
        }
        return $this->filterNoExclusiveArea($bookingInDay, $area);
    }
    private function filterIsExclusiveArea($bookingInDay)
    {
        $notAvailableFrom = [];
        $notAvailableTo = [];
        foreach ($bookingInDay as $booking) {
            array_push($notAvailableFrom, $this->formatTimeForAvailable($booking->time_from)); //add init hour of booking

            array_push($notAvailableTo, $this->formatTimeForAvailable($booking->time_to)); //add more hours of booking

            if ($booking->booking_hour > 1) {
                for ($i = 1; $i < $booking->booking_hour; $i++) {
                    array_push($notAvailableFrom, $this->formatTimeForAvailable($booking->time_from, + $i)); //add more hours of booking
                    array_push($notAvailableTo, $this->formatTimeForAvailable($booking->time_from, + $i)); //add more hours of booking
                }
            }
        }
        return [
            "From" => $notAvailableFrom,
            "To" => $notAvailableTo,
        ];
    }
    private function filterNoExclusiveArea($booking, $area)
    {
        $bookingInDay = $booking->groupBy(function ($item, $key) {
            return substr($item->time_from, 0, 2);
        });

        $notAvailableFrom = [];
        $notAvailableTo = [];

        foreach ($bookingInDay as $booking) {
            if ($area->capacity == count($booking)) {
                array_push($notAvailableFrom, $this->formatTimeForAvailable($booking[0]->time_from)); //add init hour of booking
                array_push($notAvailableTo, $this->formatTimeForAvailable($booking[0]->time_to)); //add more hours of booking
                if ($booking[0]->booking_hour > 1) {
                    for ($i = 1; $i < $booking[0]->booking_hour; $i++) {
                        array_push($notAvailableFrom, $this->formatTimeForAvailable($booking[0]->time_from) + $i);
                        array_push($notAvailableTo, $this->formatTimeForAvailable($booking[0]->time_from) + $i);
                    }
                }
            }
        }
        return [
            "From" => $notAvailableFrom,
            "To" => $notAvailableTo,
        ];
    }
    private function formattedResult($availableFrom, $notAvailable)
    {
        $format = array_diff($availableFrom, $notAvailable); // diff between available and not available
        return array_values($format); // format simple array
    }
    private function formatTimeForAvailable($day)
    {
        return intval(substr($day, 0, 2));
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
        if ($booking->status == 1) {
            $this->pedingToPayReserveNotification($users, $booking);
            return;
        }

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
                    url: '/admin/reserves',
                    meta: [
                        'booking_id' => $booking->id,
                        'icon' => $booking->icon_status
                    ]
                ));
            }
        } catch (\Throwable $e) {
            // Silenciar errores de notificación para no romper el flujo
        }
    }
    private function pedingToPayReserveNotification($users, $booking)
    {
        try {
            $users["client"]->notify(new RealtimeNotification(
                title: 'Reserva no completada',
                message: 'Tu reserva #' . $booking->booking_number . ' fue creada, pero falta que realices el pago',
                url: '/client/reserves/view/' . $booking->id,
                meta: [
                        'booking_id' => $booking->id,
                        'icon' => $booking->icon_status
                    ]
            ));

            if ($users["admin"]) {
                $users["admin"]->notify(new RealtimeNotification(
                    title: 'Nueva reserva no completada',
                    message: 'Se creó la reserva #' . $booking->booking_number . ', pero falta que se realice el pago correspondiente',
                    url: '/admin/reserves',
                    meta: [
                        'booking_id' => $booking->id,
                        'icon' => $booking->icon_status
                    ]
                ));
            }
        } catch (\Throwable $e) {
            // Silenciar errores de notificación para no romper el flujo
        }
    }
    private function cancelReserveNotification($users, $booking)
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
            // Silenciar errores de notificación para no romper el flujo
        }
    }
}
