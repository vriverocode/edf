<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\Rol;
use App\Models\User;
use App\Notifications\RealtimeNotification;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class IncidentController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $isOwnerOrResident = in_array($user->rol_id, [Rol::PROPIETARIO, Rol::INQUILINO, Rol::FAMILIAR, Rol::AIRBNB]);
            $incidents = Incident::when($isOwnerOrResident, function ($q) use ($user) {
                return $q->where('user_id', $user->id);
            })
                ->orderBy('created_at', 'desc')
                ->paginate(15);
        } catch (Exception $e) {
            return $this->returnFail(505, $e->getMessage());
        }

        return $this->returnSuccess(200, $incidents);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'date' => 'required|date',
            'hour' => 'required|string',
            'location' => 'nullable|string|max:255',
            'type' => 'required|integer',
            'image' => 'nullable|image|max:10240', // Max 10MB
        ]);

        $incident = new Incident($request->except('image'));
        $incident->user_id = Auth::id();
        $incident->status = 1; // Pendiente
        $incident->images = '';
        $incident->videos = '';
        $incident->files = '';
        $incident->location = $request->location ?? '';

        if ($request->hasFile('image')) {
            $file = $request->file('image');
            if ($file->isValid()) {
                $rand = rand(1000000, 9999999);
                $name = $rand.'_'.time().'.'.$file->extension();
                $destination = public_path('images/incidents');

                if (! is_dir($destination)) {
                    @mkdir($destination, 0775, true);
                }

                $file->move($destination, $name);
                $incident->images = config('app.url')."/images/incidents/{$name}";
            }
        }

        $incident->save();

        // Notificar a admins, super-admins y conserjes
        try {
            $targetRoles = [Rol::ADMIN, Rol::SUPER_ADMIN, Rol::TRABAJADOR];
            $users = User::whereIn('rol_id', $targetRoles)->where('status', 1)->get();

            if ($users->isNotEmpty()) {
                Notification::send($users, new RealtimeNotification(
                    title: 'Nueva incidencia reportada',
                    message: $incident->title,
                    url: '/admin/incidents',
                    meta: ['incident_id' => $incident->id, 'icon' => 'alert-circle']
                ));
            }
        } catch (\Throwable $e) {
            Log::error('Error al notificar incidencia: '.$e->getMessage());
        }

        return $this->returnSuccess(200, ['data' => $incident, 'message' => 'Incidencia creada con éxito']);
    }

    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $incident = Incident::find($id);
            if (! $incident) {
                return $this->returnFail(404, 'Incidencia no encontrada');
            }
            $isOwnerOrResident = in_array($user->rol_id, [Rol::PROPIETARIO, Rol::INQUILINO, Rol::FAMILIAR, Rol::AIRBNB]);
            if ($isOwnerOrResident && $incident->user_id !== $user->id) {
                return $this->returnFail(403, 'No tienes permiso para ver esta incidencia');
            }

            return $this->returnSuccess(200, $incident);
        } catch (Exception $e) {
            return $this->returnFail(505, $e->getMessage());
        }
    }
}
