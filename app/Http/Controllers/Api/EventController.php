<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Event;
use App\Models\Rol;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EventController extends Controller
{
    //
    public function get(Request $request)
    {
        $events = Event::with(['booking.comunArea'])->orderBy('created_at', 'desc')
            ->get();

        return $this->returnSuccess(200, $events);
    }

    public function show($id)
    {
        $event = Event::with(['booking.comunArea'])->find($id);

        if (! $event) {
            return $this->returnFail(404, 'Evento no encontrado');
        }

        return $this->returnSuccess(200, $event);
    }

    public function create(Request $request)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'Solo el administrador puede gestionar eventos'], 403);
        }

        $LOCATION_TYPE_COMUN_AREA = 1;

        $validated = $this->validateFieldsFromInput($request->all());
        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }
        $event = Event::create([
            'title' => $request->title,
            'description' => htmlspecialchars($request->description),
            'date' => date('Y-m-d', strtotime($request->date)),
            'time_from' => $request->time_from,
            'time_to' => $request->time_to,
            'location' => $request->location,
            'assits' => json_encode([]),
            'not_assits' => json_encode([]),
        ]);
        if ($request->type_location == $LOCATION_TYPE_COMUN_AREA) {
            $bookingToEvent = $this->createEventReserve($request);

            $event->update([
                'booking_id' => $bookingToEvent->id,
            ]);
        }
        $this->sendNotification($event, (int) $request->user()->id);

        return $this->returnSuccess(200, 'ok');
    }

    public function update(Request $request, $id)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'Solo el administrador puede gestionar eventos'], 403);
        }

        $LOCATION_TYPE_COMUN_AREA = 1;
        $LOCATION_TYPE_STANDAR = 2;
        $validated = $this->validateFieldsFromInput($request->all());

        $event = Event::with(['booking.comunArea'])->find($id);
        $bookingToEvent = $event->booking_id;
        $previousDate = $event->date;

        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }
        if (! $event) {
            return $this->returnFail(404, 'Evento no encontrado');
        }
        $event->update([
            'title' => $request->title,
            'description' => htmlspecialchars($request->description),
            'date' => date('Y-m-d', strtotime($request->date)),
            'time_from' => $request->time_from,
            'time_to' => $request->time_to,
            'location' => $request->location ?? $event->location,
        ]);

        if (
            $request->type_location == $LOCATION_TYPE_COMUN_AREA
            && $event->booking?->comunArea->id != $request->area
        ) {
            $this->deleteEventReserve($event);
            $bookingToEvent = $this->createEventReserve($request)->id;
        }
        if ($request->type_location == $LOCATION_TYPE_STANDAR) {
            $this->deleteEventReserve($event);
            $bookingToEvent = null;
        }

        $event->update([
            'booking_id' => $bookingToEvent,
        ]);

        if ($previousDate !== date('Y-m-d', strtotime($request->date))) {
            $this->sendNotification($event, (int) $request->user()->id, true);
        }

        return $this->returnSuccess(200, 'ok');
    }

    public function destroy($id)
    {
        $user = request()->user();
        if (! in_array($user->rol_id, [Rol::ADMIN, Rol::SUPER_ADMIN])) {
            return response()->json(['code' => 403, 'error' => 'Solo el administrador puede gestionar eventos'], 403);
        }

        $event = Event::find($id);
        if (! $event) {
            return $this->returnFail(404, 'Evento no encontrado');
        }
        $this->deleteEventReserve($event);
        $event->delete();

        return $this->returnSuccess(200, 'ok');
    }

    public function setAssist(Request $request, $id)
    {
        $event = Event::with(['booking.comunArea'])->find($id);
        $assits = $this->selectTypeAssit($request, $event);

        if (in_array($request->user()->id, $assits)) {
            return $this->returnSuccess(200, 'ok');
        }
        array_push($assits, $request->user()->id);

        $event->update([
            $request->assitType == 0 ? 'not_assits' : 'assits' => $assits,
        ]);

        return $this->returnSuccess(200, ['assits' => $event->assits, 'not_assits' => $event->not_assits]);
    }

    public function attendance($id)
    {
        $event = Event::with(['booking.comunArea'])->find($id);

        if (! $event) {
            return $this->returnFail(404, 'Evento no encontrado');
        }

        $mapUsers = function ($ids) {
            return User::with('units')->whereIn('id', $ids)->get()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'departments' => $user->units->map(function ($departament) {
                        return [
                            'number' => $departament->number,
                            'type_label' => $departament->type_label,
                        ];
                    })->values(),
                ];
            })->values();
        };

        return $this->returnSuccess(200, [
            'event' => $event,
            'assits' => $mapUsers($event->assits ?? []),
            'not_assits' => $mapUsers($event->not_assits ?? []),
        ]);
    }

    private function validateFieldsFromInput($inputs)
    {
        $rules = [
            'title' => ['required', 'regex:/^[a-z 0-9 A-Z-À-ÿ ,.\-]+$/i'],
            'date' => ['required', 'date'],
            'time_from' => ['required'],
            'time_to' => ['required'],
            'location' => ['regex:/^[a-z 0-9 A-Z-À-ÿ\w\s* .\-]+$/i'],
            'type_location' => ['required', 'numeric'],
        ];

        $messages = [
            'title.required' => 'Titulo del evento es requerido.',
            'title.regex' => 'Titulo de evento no valido',
            'time_from.required' => 'Horario de inicio es requerida',
            'time_to.required' => 'Horario de finalización es requerida',
            'date.required' => 'Fecha del evento es requerida',
            'date.date' => 'Fecha no valida',
            'location.regex' => 'Localidad no valida no valida',
            'type_location.required' => 'Tipo de localidad es requerid',
            'type_location.numeric' => 'Tipo de localidad no valida',
        ];
        $validator = Validator::make($inputs, $rules, $messages)->errors();

        return $validator->all();
    }

    private function createEventReserve(Request $request)
    {
        $booking = Booking::create([
            'user_id' => $request->user()->id,
            'comun_area_id' => $request->area,
            'booking_number' => $request->user()->id.'00'.rand(1000, 9999),
            'date' => date('Y-m-d', strtotime($request->date)),
            'time_from' => $request->time_from,
            'time_to' => $request->time_to,
            'amount' => 0,
            'status' => 3,
            'is_exclusive' => 1,
        ]);

        return $booking;
    }

    private function deleteEventReserve(Event $event)
    {
        $booking = Booking::find($event->booking_id);
        if (! $booking) {
            return $this->returnFail(404, 'Reserva no encontrada');
        }
        $booking->delete();
    }

    private function sendNotification($event, ?int $creatorId = null, bool $modified = false)
    {
        $users = User::whereIn('rol_id', [2, 3, 4, 5])->get();
        $creator = User::find($creatorId);

        if ($creator && $creator->rol_id != 1 && ! $users->contains('id', $creator->id)) {
            $users->push($creator);
        }
        $dataNotificaction = $this->getDataToNotification($event, $modified);

        try {
            foreach ($users as $user) {
                $user->notify(new RealtimeNotification(
                    title: $dataNotificaction['title'],
                    message: $dataNotificaction['message'],
                    url: $dataNotificaction['url'],
                    meta: $dataNotificaction['meta'],
                ));
            }
        } catch (\Throwable $e) {
            // Silenciar errores de notificación para no romper el flujo
        }
    }

    private function getDataToNotification($event, bool $modified = false)
    {
        if ($modified) {
            return [
                'title' => 'Evento modificado',
                'message' => 'El evento: '.$event->title.', fue modificado. Revisa la nueva fecha',
                'url' => '/client/events/view/'.$event->id,
                'meta' => ['event_id' => $event->id],
            ];
        }

        return [
            'title' => 'Nuevo evento programado',
            'message' => 'El evento: '.$event->title.', fue programado entra y confirma tu asistencia',
            'url' => '/client/events/view/'.$event->id,
            'meta' => ['event_id' => $event->id],
        ];
    }

    private function selectTypeAssit($request, $event)
    {
        $assit = [];
        if ($request->assitType == 0) {
            $assit = $event->not_assits ?? [];
        }
        if ($request->assitType == 1) {
            $assit = $event->assits ?? [];
        }

        return $assit;
    }
}
