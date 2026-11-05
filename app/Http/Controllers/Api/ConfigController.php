<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppVersion;
use App\Models\Config;

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

    public function getBalanceUrl()
    {
        $url = Config::query()
            ->where('name', 'balance_url')
            ->where('status', true)
            ->value('value');

        if ($url === null || $url === '') {
            return $this->returnFail(404, 'Configuración balance_url no encontrada');
        }

        return $this->returnSuccess(200, ['url' => $url]);
    }
}
