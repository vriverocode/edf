<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use Illuminate\Http\Request;

class AppUpdateController extends Controller
{
    private const DOWNLOAD_URL = 'https://github.com/vriverocode/edf/releases/download/apk/pacifik.apk';

    public function notifyUpdateApp(Request $request)
    {
        $description = "Se ha publicado una nueva versión de la app PACIFIK.\n\n".
            "Pasos para actualizar:\n".
            "1. Haz clic en el enlace de descarga\n".
            "2. Descarga el archivo APK\n".
            "3. Abre el archivo e instálalo\n\n".
            "Enlace de descarga:\n".self::DOWNLOAD_URL;

        $notice = Notice::create([
            'title' => 'Nueva versión de PACIFIK disponible',
            'description' => $description,
            'data_contact' => 'Admin',
            'category' => 0,
            'group' => 0,
            'type' => 4,
            'views' => '[]',
            'status' => 2,
            'user_id' => $request->user()->id,
        ]);

        return $this->returnSuccess(200, [
            'message' => 'Aviso de actualización publicado correctamente.',
            'notice_id' => $notice->id,
        ]);
    }
}
