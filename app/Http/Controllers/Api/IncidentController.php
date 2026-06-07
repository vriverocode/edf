<?php

namespace App\Http\Controllers\Api;

use Exception;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Incident;
use Illuminate\Support\Facades\Auth;

class IncidentController extends Controller
{
    public function index(Request $request)
    {   
        try{

            $user = $request->user();
            
            $incidents = Incident::when($user->hasRole('propietario') || $user->hasRole('residente'), function ($q) use ($user) {
                return $q->where('user_id', $user->id);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        }catch(Exception $e){
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
        ]);

        $incident = new Incident($request->all());
        $incident->user_id = Auth::id();
        $incident->status = 1; // Pendiente
        $incident->save();

        return $this->returnSuccess(200, ["data" => $incident, 'message' => 'Incidencia creada con éxito',]);
    }
}
