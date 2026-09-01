<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use Illuminate\Http\Request;

class AppUpdateController extends Controller
{
    public function notifyUpdateApp(Request $request)
    {
        $users = User::whereNotNull('device_token')
            ->where('device_token', '!=', '')
            ->get();

        if ($users->isEmpty()) {
            return $this->returnSuccess(200, [
                'message' => 'No hay usuarios con device_token registrado.',
                'notified' => 0,
            ]);
        }

        $notified = 0;

        foreach ($users as $user) {
            try {
                $user->notify(new RealtimeNotification(
                    title: '¡Nueva versión de PACIFIK disponible!',
                    message: 'Hay una actualización disponible. Descárgala e instálala para disfrutar de las últimas mejoras.',
                    url: '/client/app-update',
                    meta: ['type' => 'app_update'],
                ));
                $notified++;
            } catch (\Throwable $e) {
                return $this->returnError(500, 'Error al enviar la notificación.');
            }
        }

        return $this->returnSuccess(200, [
            'message' => "Notificación de actualización enviada correctamente.",
            'notified' => $notified,
            'total_with_token' => $users->count(),
        ]);
    }
}
