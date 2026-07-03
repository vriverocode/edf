<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use App\Models\Event;
use App\Models\Booking;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Notifications\RealtimeNotification;

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

        if (!$event) {
            return $this->returnFail(404, 'Evento no encontrado');
        }

        return $this->returnSuccess(200, $event);
    }

    public function create(Request $request)
    {
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
            'location' => $request->location
        ]);
        if ($request->type_location == $LOCATION_TYPE_COMUN_AREA) {
            $bookingToEvent =  $this->createEventReserve($request);

            $event->update([
                'booking_id' => $bookingToEvent->id
            ]);
        }
        $this->sendNotification($event, (int) $request->user()->id);
        return $this->returnSuccess(200, 'ok');
    }
    public function update(Request $request, $id)
    {
        $LOCATION_TYPE_COMUN_AREA = 1;
        $LOCATION_TYPE_STANDAR = 2;
        $validated = $this->validateFieldsFromInput($request->all());

        $event = Event::with(['booking.comunArea'])->find($id);
        $bookingToEvent = $event->booking_id;

        if (count($validated) > 0) {
            return $this->returnFail(400, $validated[0]);
        }
        if (!$event) {
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
            $bookingToEvent =  $this->createEventReserve($request)->id;
        }
        if ($request->type_location == $LOCATION_TYPE_STANDAR) {
            $this->deleteEventReserve($event);
            $bookingToEvent = null;
        }

        $event->update([
            'booking_id' => $bookingToEvent,
        ]);

        $this->sendNotification($event, (int) $request->user()->id);
        return $this->returnSuccess(200, 'ok');
    }
    public function destroy($id)
    {
        $event = Event::find($id);
        if (!$event) {
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
            $request->assitType == 0 ? "not_assits" : "assits"
            => json_encode($assits)
        ]);

        return $this->returnSuccess(200, ['assits' => $event->assits, 'not_assits' => $event->not_assits]);
    }
    private function validateFieldsFromInput($inputs)
    {
        $rules = [
            'title'         => ['required', 'regex:/^[a-z 0-9 A-Z-À-ÿ ,.\-]+$/i'],
            'date'          => ['required', 'date'],
            'time_from'     => ['required'],
            'time_to'       => ['required'],
            'location'      => ['regex:/^[a-z 0-9 A-Z-À-ÿ\w\s* .\-]+$/i'],
            'type_location' => ['required', 'numeric'],
        ];

        $messages = [
            'title.required'         => 'Titulo del evento es requerido.',
            'title.regex'            => 'Titulo de evento no valido',
            'time_from.required'     => 'Horario de inicio es requerida',
            'time_to.required'       => 'Horario de finalización es requerida',
            'date.required'          => 'Fecha del evento es requerida',
            'date.date'              => 'Fecha no valida',
            'location.regex'         => 'Localidad no valida no valida',
            'type_location.required' => 'Tipo de localidad es requerid',
            'type_location.numeric'  => 'Tipo de localidad no valida',
        ];
        $validator = Validator::make($inputs, $rules, $messages)->errors();
        return $validator->all() ;
    }
    private function createEventReserve(Request $request)
    {
        $booking = Booking::create([
            'user_id' => $request->user()->id,
            'comun_area_id' => $request->area,
            'booking_number' => $request->user()->id . '00' . rand(1000, 9999),
            'date' => date('Y-m-d', strtotime($request->date)),
            'time_from' => $request->time_from,
            'time_to' => $request->time_to,
            'amount' => 0,
            'status' => 3,
            'is_exclusive' => 1
        ]);
        return $booking;
    }
    private function deleteEventReserve(Event $event)
    {
        $booking = Booking::find($event->booking_id);
        if (!$booking) {
            return $this->returnFail(404, 'Reserva no encontrada');
        }
        $booking->delete();
    }
    private function sendNotification($event, ?int $creatorId = null)
    {
        $users = User::where('rol_id', 2)->get();
        $creator = $creatorId ? User::find($creatorId) : null;
        if ($creator && !$users->contains('id', $creator->id)) {
            $users->push($creator);
        }
        $dataNotificaction = $this->getDataToNotification($event);

        try {
            foreach ($users as $user) {
                $user->notify(new RealtimeNotification(
                    title: $dataNotificaction["title"],
                    message: $dataNotificaction["message"],
                    url: $dataNotificaction["url"],
                    meta: $dataNotificaction["meta"],
                ));
            }
        } catch (\Throwable $e) {
            // Silenciar errores de notificación para no romper el flujo
        }
    }
    private function getDataToNotification($event)
    {
        return [
            "title" => "Nuevo evento programado",
            "message" => "El evento: " . $event->title
                . ", fue programado entra y confirma tu asistencia",
            "url" => "/client/events/view/" . $event->id,
            "meta" =>  ['event_id' => $event->id],
        ];
    }
    private function selectTypeAssit($request, $event)
    {
        $assit = [];
        if ($request->assitType == 0) {
            $assit = json_decode($event->not_assits, true);
        }
        if ($request->assitType == 1) {
            $assit = json_decode($event->assits, true);
        }
        return $assit;
    }
}
