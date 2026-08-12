<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ComunArea;
use App\Models\Maintenance;
use App\Models\Notice;
use App\Models\Rol;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use DateInterval;
use DatePeriod;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MaintenanceController extends Controller
{
    public function index()
    {
        $maintenances = Maintenance::with(['comunArea'])->orderBy('date', 'desc')->get();

        return $this->returnSuccess(200, $maintenances);
    }

    public function show($id)
    {
        $maintenance = Maintenance::with('comunArea')->find($id);

        if (! $maintenance) {
            return $this->returnFail(404, 'Mantenimiento no encontrado');
        }

        return $this->returnSuccess(200, $maintenance);
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

        $area = ComunArea::find($request->comun_area_id);
        if (! $area) {
            return $this->returnFail(404, 'Área común no encontrada');
        }

        DB::beginTransaction();

        try {
            $dateStart = date('Y-m-d', strtotime($request->date));
            $dateEnd = $request->date_to ? date('Y-m-d', strtotime($request->date_to)) : $dateStart;
            $timeFrom = $request->time_from;
            $timeTo = $request->time_to;
            $isMultiDay = $dateEnd > $dateStart;

            $durationText = $this->buildDurationText($dateStart, $dateEnd, $timeFrom, $timeTo);

            $period = new DatePeriod(
                new DateTime($dateStart),
                new DateInterval('P1D'),
                (new DateTime($dateEnd))->modify('+1 day')
            );

            $maintenances = [];
            foreach ($period as $day) {
                $maintenances[] = Maintenance::create([
                    'title' => 'Mantenimiento programado: '.$area->name,
                    'description' => htmlspecialchars($request->motive),
                    'comun_area_id' => $area->id,
                    'date' => $day->format('Y-m-d'),
                    'time_from' => $timeFrom,
                    'time_to' => $timeTo,
                    'status' => 1,
                ]);
            }

            $maintenance = $maintenances[0];

            if ($request->hasFile('photo')) {
                $photo = $request->file('photo');
                $rand = rand(1000000, 9999999);
                $fileName = $rand.'_maintenance_'.$maintenance->id.'.'.$photo->extension();
                $photo->move(public_path('storage').'/images/maintenance/', $fileName);
                $photoUrl = config('app.url').'/storage/images/maintenance/'.$fileName;
                $maintenance->update(['photo' => $photoUrl]);
            }

            $fechaTexto = $isMultiDay
                ? 'Del '.date('d/m/Y', strtotime($dateStart)).' al '.date('d/m/Y', strtotime($dateEnd))
                : 'El día '.date('d/m/Y', strtotime($dateStart));
            $horarioTexto = $timeFrom && $timeTo ? $timeFrom.' - '.$timeTo : 'Todo el día';

            $descriptionNotice = $request->motive
                ."\nFecha: ".$fechaTexto
                ."\nHorario: ".$horarioTexto
                ."\nDuración: ".$durationText;

            Notice::create([
                'title' => 'Mantenimiento programado: '.$area->name,
                'description' => $descriptionNotice,
                'type' => 4,
                'user_id' => $request->user()->id,
                'status' => 2,
                'category' => 0,
                'group' => 0,
                'views' => '[]',
            ]);

            $bookingsToCancel = $this->cancelConflictingBookings($area->id, $dateStart, $dateEnd, $timeFrom, $timeTo);

            foreach ($bookingsToCancel as $booking) {
                $motive = 'Cancelada por mantenimiento';
                if ($booking->type != 1 && $booking->amount > 0) {
                    $motive .= '. El dinero será reembolsado';
                }
                $booking->update([
                    'status' => 0,
                    'motive' => $motive,
                ]);
                $this->sendBookingCancelNotification($booking);
            }

            $this->sendMaintenanceNotification($maintenance, $area, $dateEnd);

            DB::commit();

            return $this->returnSuccess(200, 'Mantenimiento programado con éxito');
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error al registrar mantenimiento: '.$e->getMessage());

            return $this->returnFail(500, $e->getMessage());
        }
    }

    private function cancelConflictingBookings(int $areaId, string $dateStart, string $dateEnd, ?string $timeFrom, ?string $timeTo)
    {
        $bookingsQuery = Booking::with('pay')
            ->where('comun_area_id', $areaId)
            ->where('status', '>', 0);

        if ($dateEnd > $dateStart) {
            $bookingsQuery->whereBetween('date', [$dateStart, $dateEnd]);
        } else {
            $bookingsQuery->where('date', $dateStart)
                ->where('time_from', '<', $timeTo)
                ->where('time_to', '>', $timeFrom);
        }

        return $bookingsQuery->get();
    }

    public function update(Request $request, $id)
    {
        $maintenance = Maintenance::find($id);
        if (! $maintenance) {
            return $this->returnFail(404, 'Mantenimiento no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'date' => ['required', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date'],
            'time_from' => ['nullable', 'date_format:H:i'],
            'time_to' => ['nullable', 'date_format:H:i', 'after:time_from'],
            'motive' => ['required', 'string', 'max:500'],
            'photo' => ['nullable', 'image', 'max:8192'],
        ], [
            'date.required' => 'La fecha es requerida.',
            'date.date' => 'La fecha no es válida.',
            'date_to.date' => 'La fecha de fin no es válida.',
            'date_to.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la de inicio.',
            'time_from.date_format' => 'La hora de inicio debe tener formato HH:MM.',
            'time_to.date_format' => 'La hora de fin debe tener formato HH:MM.',
            'time_to.after' => 'La hora de fin debe ser posterior a la de inicio.',
            'motive.required' => 'El motivo es requerido.',
            'motive.max' => 'El motivo no puede superar los 500 caracteres.',
            'photo.image' => 'La foto debe ser una imagen.',
            'photo.max' => 'La foto no puede superar los 8MB.',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(400, $validator->errors()->first());
        }

        try {
            $dateStart = date('Y-m-d', strtotime($request->date));
            $dateEnd = $request->date_to ? date('Y-m-d', strtotime($request->date_to)) : $dateStart;
            $timeFrom = $request->time_from;
            $timeTo = $request->time_to;

            $data = [
                'date' => $dateStart,
                'time_from' => $timeFrom,
                'time_to' => $timeTo,
                'description' => htmlspecialchars($request->motive),
            ];

            if ($request->hasFile('photo')) {
                $data['photo'] = $this->storeMaintenancePhoto($request->file('photo'), $maintenance->id);
            }

            $maintenance->update($data);

            $bookingsToCancel = $this->cancelConflictingBookings($maintenance->comun_area_id, $dateStart, $dateEnd, $timeFrom, $timeTo);

            foreach ($bookingsToCancel as $booking) {
                $motive = 'Cancelada por mantenimiento';
                if ($booking->type != 1 && $booking->amount > 0) {
                    $motive .= '. El dinero será reembolsado';
                }
                $booking->update([
                    'status' => 0,
                    'motive' => $motive,
                ]);
                $this->sendBookingCancelNotification($booking);
            }

            return $this->returnSuccess(200, 'Mantenimiento actualizado con éxito');
        } catch (Exception $e) {
            Log::error('Error al actualizar mantenimiento: '.$e->getMessage());

            return $this->returnFail(500, 'No se pudo actualizar el mantenimiento');
        }
    }

    private function storeMaintenancePhoto($photo, int $maintenanceId): string
    {
        $rand = rand(1000000, 9999999);
        $fileName = $rand.'_maintenance_'.$maintenanceId.'.'.$photo->extension();
        $photo->move(public_path('storage').'/images/maintenance/', $fileName);

        return config('app.url').'/storage/images/maintenance/'.$fileName;
    }

    private function sendBookingCancelNotification($booking)
    {
        try {
            $client = User::find($booking->user_id);
            $admin = User::find(1);
            $needsRefund = $booking->type != 1
                && $booking->amount > 0
                && $booking->pay !== null
                && (int) $booking->pay->status === 2;

            if ($client) {
                $client->notify(new RealtimeNotification(
                    title: 'Reserva cancelada',
                    message: 'Tu reserva #'.$booking->booking_number.' fue cancelada. '.$booking->motive,
                    url: '/client/reserves/view/'.$booking->id,
                    meta: ['booking_id' => $booking->id, 'icon' => 'cancel']
                ));
            }
            if ($admin) {
                $admin->notify(new RealtimeNotification(
                    title: 'Reserva cancelada por mantenimiento',
                    message: 'La reserva #'.$booking->booking_number.' fue cancelada.',
                    url: '/client/reserves/view/'.$booking->id,
                    meta: ['booking_id' => $booking->id, 'icon' => 'cancel']
                ));

                if ($needsRefund) {
                    $admin->notify(new RealtimeNotification(
                        title: 'Reembolso pendiente',
                        message: 'La reserva #'.$booking->booking_number.' fue cancelada por mantenimiento. Registra el reembolso manualmente.',
                        url: '/client/reserves/view/'.$booking->id,
                        meta: ['booking_id' => $booking->id, 'icon' => 'refund']
                    ));
                }
            }
        } catch (\Throwable $e) {
            Log::error('Error al notificar cancelación: '.$e->getMessage());
        }
    }

    private function sendMaintenanceNotification($maintenance, $area, $dateEnd = null)
    {
        $users = User::where('rol_id', '!=', 1)->where('status', 1)->get();
        $fechaFormateada = $dateEnd && $dateEnd !== $maintenance->date
            ? 'del '.date('d/m/Y', strtotime($maintenance->date)).' al '.date('d/m/Y', strtotime($dateEnd))
            : 'el día '.date('d/m/Y', strtotime($maintenance->date));

        try {
            foreach ($users as $user) {
                $url = (int) $user->rol_id === Rol::SUPER_ADMIN
                    ? '/admin/maintenances/'.$maintenance->id
                    : '/client/maintenances/'.$maintenance->id;

                $user->notify(new RealtimeNotification(
                    title: 'Mantenimiento programado',
                    message: "Se programó un mantenimiento en {$area->name} {$fechaFormateada}.",
                    url: $url,
                    meta: [
                        'maintenance_id' => $maintenance->id,
                        'icon' => 'build',
                    ]
                ));
            }
        } catch (\Throwable $e) {
            Log::error('Fallo al enviar notificaciones de mantenimiento: '.$e->getMessage());
        }
    }

    private function validateFieldsFromInput($inputs)
    {
        $isSingleDay = empty($inputs['date_to']) || $inputs['date_to'] === $inputs['date'];

        $rules = [
            'comun_area_id' => ['required', 'integer', 'exists:comun_areas,id'],
            'date' => ['required', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date'],
            'time_from' => $isSingleDay
                ? ['required', 'date_format:H:i']
                : ['nullable', 'required_with:time_to', 'date_format:H:i'],
            'time_to' => $isSingleDay
                ? ['required', 'date_format:H:i', 'after:time_from']
                : ['nullable', 'required_with:time_from', 'date_format:H:i', 'after:time_from'],
            'motive' => ['required', 'string', 'max:500'],
        ];

        $messages = [
            'comun_area_id.required' => 'El área común es requerida.',
            'comun_area_id.exists' => 'El área común no es válida.',
            'date.required' => 'La fecha es requerida.',
            'date.date' => 'La fecha no es válida.',
            'date_to.date' => 'La fecha de fin no es válida.',
            'date_to.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la de inicio.',
            'time_from.required' => 'La hora de inicio es requerida.',
            'time_from.date_format' => 'La hora de inicio debe tener formato HH:MM.',
            'time_to.required' => 'La hora de fin es requerida.',
            'time_to.date_format' => 'La hora de fin debe tener formato HH:MM.',
            'time_to.after' => 'La hora de fin debe ser posterior a la de inicio.',
            'motive.required' => 'El motivo es requerido.',
        ];

        $validator = Validator::make($inputs, $rules, $messages)->errors();

        return $validator->all();
    }

    private function buildDurationText(?string $dateStart, ?string $dateEnd, ?string $timeFrom, ?string $timeTo): string
    {
        if ($dateEnd !== $dateStart) {
            $days = (new DateTime($dateEnd))->diff(new DateTime($dateStart))->days + 1;

            return $days.' día'.($days > 1 ? 's' : '');
        }

        if ($timeFrom && $timeTo) {
            $diff = (new DateTime($timeTo))->diff(new DateTime($timeFrom));
            $totalMinutes = $diff->days * 1440 + $diff->h * 60 + $diff->i;

            if ($totalMinutes % 60 === 0) {
                return ($totalMinutes / 60).' horas';
            }

            $hours = $diff->days * 24 + $diff->h;
            $minutes = $diff->i;

            if ($hours > 0 && $minutes > 0) {
                return $hours.' h '.$minutes.' min';
            }
            if ($hours > 0) {
                return $hours.' horas';
            }

            return $minutes.' min';
        }

        return '1 día';
    }

    public function getByArea(int $areaId, Request $request)
    {
        $date = $request->query('date');

        $query = Maintenance::where('comun_area_id', $areaId)
            ->whereIn('status', [Maintenance::STATUS_PENDING, Maintenance::STATUS_PENDING_MATERIAL]);

        if ($date) {
            $query->where('date', date('Y-m-d', strtotime($date)));
        }

        $maintenances = $query->orderBy('date', 'desc')
            ->orderBy('time_from', 'asc')
            ->get(['id', 'title', 'description', 'date', 'time_from', 'time_to', 'status', 'photo']);

        return $this->returnSuccess(200, $maintenances);
    }

    public function complete(Request $request, $id)
    {
        $maintenance = Maintenance::find($id);

        if (! $maintenance) {
            return $this->returnFail(404, 'Mantenimiento no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'evidence' => ['required', 'image', 'max:8192'],
            'description' => ['required', 'string', 'max:500'],
        ], [
            'evidence.required' => 'Debes adjuntar una evidencia.',
            'evidence.image' => 'La evidencia debe ser una imagen.',
            'evidence.max' => 'La evidencia no puede superar los 8MB.',
            'description.required' => 'La descripción corta es requerida.',
            'description.max' => 'La descripción no puede superar los 500 caracteres.',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(400, $validator->errors()->first());
        }

        try {
            $photoUrl = $this->storeEvidencePhoto($request->file('evidence'), $maintenance->id);

            $maintenance->update([
                'status' => Maintenance::STATUS_COMPLETED,
                'evidence_photo' => $photoUrl,
                'completion_description' => htmlspecialchars($request->description),
                'completed_at' => now(),
                'completed_by' => $request->user()->id,
            ]);

            return $this->returnSuccess(200, 'Mantenimiento completado con éxito');
        } catch (Exception $e) {
            Log::error('Error al completar mantenimiento: '.$e->getMessage());

            return $this->returnFail(500, 'No se pudo completar el mantenimiento');
        }
    }

    public function changeStatus(Request $request, $id)
    {
        $maintenance = Maintenance::find($id);

        if (! $maintenance) {
            return $this->returnFail(404, 'Mantenimiento no encontrado');
        }

        $validator = Validator::make($request->all(), [
            'status' => ['required', 'integer', 'in:0,1,2,3'],
            'evidence' => ['nullable', 'image', 'max:8192'],
            'description' => ['nullable', 'string', 'max:500'],
        ], [
            'status.required' => 'El estado es requerido.',
            'status.in' => 'El estado no es válido.',
            'evidence.image' => 'La evidencia debe ser una imagen.',
            'evidence.max' => 'La evidencia no puede superar los 8MB.',
            'description.max' => 'La descripción no puede superar los 500 caracteres.',
        ]);

        if ($validator->fails()) {
            return $this->returnFail(400, $validator->errors()->first());
        }

        try {
            $data = ['status' => (int) $request->status];

            if ((int) $request->status === Maintenance::STATUS_COMPLETED && $request->hasFile('evidence')) {
                $data['evidence_photo'] = $this->storeEvidencePhoto($request->file('evidence'), $maintenance->id);
                $data['completion_description'] = $request->description ? htmlspecialchars($request->description) : null;
                $data['completed_at'] = now();
                $data['completed_by'] = $request->user()->id;
            }

            $maintenance->update($data);

            return $this->returnSuccess(200, 'Estado actualizado con éxito');
        } catch (Exception $e) {
            Log::error('Error al actualizar estado del mantenimiento: '.$e->getMessage());

            return $this->returnFail(500, 'No se pudo actualizar el estado');
        }
    }

    private function storeEvidencePhoto($photo, int $maintenanceId): string
    {
        $rand = rand(1000000, 9999999);
        $fileName = $rand.'_evidence_'.$maintenanceId.'.'.$photo->extension();
        $photo->move(public_path('storage').'/images/maintenance/', $fileName);

        return config('app.url').'/storage/images/maintenance/'.$fileName;
    }
}
