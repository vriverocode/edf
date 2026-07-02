<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ComunArea;
use App\Models\Maintenance;
use App\Models\Notice;
use App\Models\User;
use App\Notifications\RealtimeNotification;
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

    public function store(Request $request)
    {
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
            $dateFormatted = date('Y-m-d', strtotime($request->date));
            $durationText = $request->duration;

            $maintenance = Maintenance::create([
                'title'         => 'Mantenimiento programado: ' . $area->name,
                'description'   => htmlspecialchars($request->motive),
                'comun_area_id' => $area->id,
                'date'          => $dateFormatted,
                'time_from'     => '08:00',
                'time_to'       => '20:00',
                'status'        => 1,
            ]);

            if ($request->hasFile('photo')) {
                $photo = $request->file('photo');
                $rand = rand(1000000, 9999999);
                $fileName = $rand . '_maintenance_' . $maintenance->id . '.' . $photo->extension();
                $photo->move(public_path('storage') . '/images/maintenance/', $fileName);
                $photoUrl = config('app.url') . '/storage/images/maintenance/' . $fileName;
                $maintenance->update(['photo' => $photoUrl]);
            }

            $descriptionNotice = $request->motive
                . "\nFecha: " . date('d/m/Y', strtotime($dateFormatted))
                . "\nDuración: " . $durationText;

            Notice::create([
                'title'       => 'Mantenimiento programado: ' . $area->name,
                'description' => $descriptionNotice,
                'type'        => 4,
                'user_id'     => $request->user()->id,
                'status'      => 2,
                'category'    => 0,
                'group'       => 0,
                'views'       => '[]',
            ]);

            $bookingsToCancel = Booking::where('comun_area_id', $area->id)
                ->where('date', $dateFormatted)
                ->where('status', '>', 0)
                ->get();

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

            $this->sendMaintenanceNotification($maintenance, $area);

            DB::commit();
            return $this->returnSuccess(200, 'Mantenimiento programado con éxito');
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al registrar mantenimiento: ' . $e->getMessage());
            return $this->returnFail(500, $e->getMessage());
        }
    }

    private function sendBookingCancelNotification($booking)
    {
        try {
            $client = User::find($booking->user_id);
            $admin = User::find(1);

            if ($client) {
                $client->notify(new RealtimeNotification(
                    title: 'Reserva cancelada',
                    message: 'Tu reserva #' . $booking->booking_number . ' fue cancelada. ' . $booking->motive,
                    url: '/client/reserves/view/' . $booking->id,
                    meta: ['booking_id' => $booking->id, 'icon' => 'cancel']
                ));
            }
            if ($admin) {
                $admin->notify(new RealtimeNotification(
                    title: 'Reserva cancelada por mantenimiento',
                    message: 'La reserva #' . $booking->booking_number . ' fue cancelada.',
                    url: '/admin/reserves',
                    meta: ['booking_id' => $booking->id, 'icon' => 'cancel']
                ));
            }
        } catch (\Throwable $e) {
            Log::error('Error al notificar cancelación: ' . $e->getMessage());
        }
    }

    private function sendMaintenanceNotification($maintenance, $area)
    {
        $users = User::where('rol_id', '!=', 1)->where('status', 1)->get();
        $fechaFormateada = date('d/m/Y', strtotime($maintenance->date));

        try {
            foreach ($users as $user) {
                $user->notify(new RealtimeNotification(
                    title: 'Mantenimiento programado',
                    message: "Se programó un mantenimiento en {$area->name} el día {$fechaFormateada}.",
                    url: '/client/notices',
                    meta: [
                        'maintenance_id' => $maintenance->id,
                        'icon' => 'build',
                    ]
                ));
            }
        } catch (\Throwable $e) {
            Log::error('Fallo al enviar notificaciones de mantenimiento: ' . $e->getMessage());
        }
    }

    private function validateFieldsFromInput($inputs)
    {
        $rules = [
            'comun_area_id' => ['required', 'integer', 'exists:comun_areas,id'],
            'date'          => ['required', 'date'],
            'duration'      => ['required', 'string'],
            'motive'        => ['required', 'string', 'max:500'],
        ];

        $messages = [
            'comun_area_id.required' => 'El área común es requerida.',
            'comun_area_id.exists'   => 'El área común no es válida.',
            'date.required'          => 'La fecha es requerida.',
            'date.date'              => 'La fecha no es válida.',
            'duration.required'      => 'La duración es requerida.',
            'duration.string'        => 'La duración no es válida.',
            'motive.required'        => 'El motivo es requerido.',
        ];

        $validator = Validator::make($inputs, $rules, $messages)->errors();
        return $validator->all();
    }
}
