<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;

class ConfigController extends Controller
{
    public function getAppVersion()
    {
        // Obtenemos el último registro basado en la fecha de creación
        $latestVersion = AppVersion::latest()->first();

        if (! $latestVersion) {
            return response()->json(['message' => 'No versions found'], 404);
        }

        return $this->returnSuccess(200, $latestVersion);
    }
}
