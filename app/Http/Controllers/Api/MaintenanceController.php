<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\Notice; // Asegúrate de que este sea el nombre exacto de tu modelo de Noticias
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

        DB::beginTransaction();

        try {
            // 1. Registrar el mantenimiento
            $maintenance = Maintenance::create([
                'title'         => $request->title,
                'description'   => htmlspecialchars($request->description),
                'comun_area_id' => $request->comun_area_id, // Puede ser null
                'date'          => date('Y-m-d', strtotime($request->date)),
                'time_from'     => $request->time_from,
                'time_to'       => $request->time_to,
                'status'        => 1 // Programado
            ]);

            // 2. Crear la noticia automáticamente para la comunidad
            // Adapta los campos de este bloque al esquema real de tu tabla 'notices'
            Notice::create([
                'title'       => 'AVISO: ' . $request->title,
                'description' => htmlspecialchars($request->description) . ' - Fecha programada: ' . date('d-m-Y', strtotime($request->date)),
                'active'      => 1,
                // Si tu modelo 'Notice' requiere un user_id, puedes recuperarlo con $request->user()->id
            ]);

            // 3. Enviar las notificaciones push en tiempo real a los residentes
            $this->sendMaintenanceNotification($maintenance);

            DB::commit();
            return $this->returnSuccess(200, 'Mantenimiento programado y publicado con éxito');

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error al registrar mantenimiento: ' . $e->getMessage());
            return $this->returnFail(500, 'Error interno del servidor al procesar el mantenimiento.');
        }
    }

    /**
     * Envía notificaciones a todos los copropietarios/residentes
     */
    private function sendMaintenanceNotification($maintenance)
    {
        // Traemos todos los usuarios residentes (rol_id = 2 según tu EventController)
        $users = User::where('rol_id', 2)->where('status', 1)->get();
        
        $fechaFormateada = date('d/m/Y', strtotime($maintenance->date));
        
        try {
            foreach ($users as $user) {
                $user->notify(new RealtimeNotification(
                    title: '🛠️ Mantenimiento Programado',
                    message: "Aviso importante: Se efectuará un mantenimiento de '{$maintenance->title}' el día {$fechaFormateada}.",
                    url: '/client/notices', // Los redirige al muro de noticias del condominio
                    meta: [
                        'maintenance_id' => $maintenance->id,
                        'icon' => 'build' // Icono genérico de Quasar/Material para herramientas
                    ]
                ));
            }
        } catch (\Throwable $e) {
            Log::error('Fallo al despachar notificaciones de mantenimiento: ' . $e->getMessage());
        }
    }

    /**
     * Validaciones del Request
     */
    private function validateFieldsFromInput($inputs)
    {
        $rules = [
            'title'         => ['required', 'string', 'max:255'],
            'description'   => ['required', 'string'],
            'date'          => ['required', 'date'],
            'comun_area_id' => ['nullable', 'integer', 'exists:comun_areas,id'],
            'time_from'     => ['nullable'],
            'time_to'       => ['nullable']
        ];

        $messages = [
            'title.required'       => 'El título del mantenimiento es obligatorio.',
            'description.required' => 'Debes ingresar los detalles o instrucciones del mantenimiento.',
            'date.required'        => 'La fecha de ejecución es obligatoria.',
            'comun_area_id.exists' => 'El área común seleccionada no es válida.'
        ];

        $validator = Validator::make($inputs, $rules, $messages)->errors();
        return $validator->all();
    }
}