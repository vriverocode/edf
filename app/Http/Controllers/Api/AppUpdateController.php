<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class AppUpdateController extends Controller
{
    private const DOWNLOAD_URL = 'https://github.com/vriverocode/edf/releases/download/apk/pacifik.apk';

    public function notifyUpdateApp(Request $request)
    {
        $users = User::where('status', 1)
            ->whereNotNull('device_token')
            ->where('device_token', '!=', '')
            ->get();

        if ($users->isEmpty()) {
            return $this->returnFail(404, 'No hay usuarios con token de notificación registrado.');
        }

        $notification = new RealtimeNotification(
            title: 'Nueva versión de PACIFIK disponible',
            message: 'Actualiza para seguir usando la app.',
            url: self::DOWNLOAD_URL
        );

        Notification::send($users, $notification);

        return $this->returnSuccess(200, [
            'message' => 'Notificaciones enviadas correctamente.',
            'users_notified' => $users->count(),
        ]);
    }
}
