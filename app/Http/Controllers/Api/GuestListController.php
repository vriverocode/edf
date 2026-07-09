<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\GuestList;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GuestListController extends Controller
{
    private const MAX_GUESTS = 40;

    private const BLOCK_MINUTES = 60;

    public function getByBooking($bookingId)
    {
        $booking = Booking::with('comunArea')->find($bookingId);

        if (! $booking) {
            return $this->returnFail(404, 'Reserva no encontrada');
        }

        $user = request()->user();
        if ($booking->user_id !== $user->id && $user->rol_id != 1) {
            return $this->returnFail(403, 'No autorizado');
        }

        $guests = GuestList::where('booking_id', $bookingId)->orderBy('created_at', 'asc')->get();

        return $this->returnSuccess(200, [
            'booking' => $booking,
            'guests' => $guests,
            'max_guests' => self::MAX_GUESTS,
            'is_locked' => $this->isLocked($booking),
        ]);
    }

    public function store(Request $request, $bookingId)
    {
        $booking = Booking::with('comunArea')->find($bookingId);

        if (! $booking) {
            return $this->returnFail(404, 'Reserva no encontrada');
        }

        if ($booking->user_id !== request()->user()->id) {
            return $this->returnFail(403, 'No autorizado');
        }

        if ($booking->comunArea->type != 4) {
            return $this->returnFail(400, 'Esta reserva no permite lista de invitados');
        }

        if ($booking->status != 3) {
            return $this->returnFail(400, 'Solo se pueden agregar invitados a reservas exitosas');
        }

        if ($this->isLocked($booking)) {
            return $this->returnFail(400, 'No se pueden agregar invitados. Ya falta 1 hora o menos para el inicio de la reserva');
        }

        $currentCount = GuestList::where('booking_id', $bookingId)->count();
        if ($currentCount >= self::MAX_GUESTS) {
            return $this->returnFail(400, 'Se alcanzó el límite máximo de '.self::MAX_GUESTS.' invitados');
        }

        $validated = $this->validateGuestInput($request->all());
        if (! empty($validated)) {
            return $this->returnFail(400, $validated[0]);
        }

        try {
            $guest = GuestList::create([
                'name' => $request->name,
                'dni' => $request->dni,
                'age' => $request->age,
                'booking_id' => $bookingId,
                'status' => 1,
            ]);
        } catch (Exception $th) {
            return $this->returnFail(500, $th->getMessage());
        }

        return $this->returnSuccess(200, $guest);
    }

    public function update(Request $request, $id)
    {
        $guest = GuestList::with('booking.comunArea')->find($id);

        if (! $guest) {
            return $this->returnFail(404, 'Invitado no encontrado');
        }

        $booking = $guest->booking;

        if ($booking->user_id !== request()->user()->id) {
            return $this->returnFail(403, 'No autorizado');
        }

        if ($this->isLocked($booking)) {
            return $this->returnFail(400, 'No se pueden editar invitados. Ya falta 1 hora o menos para el inicio de la reserva');
        }

        $validated = $this->validateGuestInput($request->all());
        if (! empty($validated)) {
            return $this->returnFail(400, $validated[0]);
        }

        try {
            $guest->update([
                'name' => $request->name,
                'dni' => $request->dni,
                'age' => $request->age,
            ]);
        } catch (Exception $th) {
            return $this->returnFail(500, $th->getMessage());
        }

        return $this->returnSuccess(200, $guest);
    }

    public function destroy($id)
    {
        $guest = GuestList::with('booking')->find($id);

        if (! $guest) {
            return $this->returnFail(404, 'Invitado no encontrado');
        }

        $booking = $guest->booking;

        if ($booking->user_id !== request()->user()->id) {
            return $this->returnFail(403, 'No autorizado');
        }

        if ($this->isLocked($booking)) {
            return $this->returnFail(400, 'No se pueden eliminar invitados. Ya falta 1 hora o menos para el inicio de la reserva');
        }

        try {
            $guest->delete();
        } catch (Exception $th) {
            return $this->returnFail(500, $th->getMessage());
        }

        return $this->returnSuccess(200, 'ok');
    }

    private function isLocked(Booking $booking): bool
    {
        date_default_timezone_set('America/Lima');
        $now = Carbon::now();
        $bookingDateTime = Carbon::parse($booking->date->format('Y-m-d').' '.$booking->time_from);

        return $now->diffInMinutes($bookingDateTime, false) <= self::BLOCK_MINUTES;
    }

    private function validateGuestInput(array $inputs): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'dni' => ['nullable', 'string', 'max:20'],
            'age' => ['nullable', 'integer', 'min:0', 'max:150'],
        ];
        $messages = [
            'name.required' => 'El nombre es requerido',
            'name.string' => 'El nombre no es válido',
            'name.max' => 'El nombre no debe exceder 255 caracteres',
            'dni.string' => 'El DNI no es válido',
            'dni.max' => 'El DNI no debe exceder 20 caracteres',
            'age.integer' => 'La edad no es válida',
            'age.min' => 'La edad no puede ser negativa',
            'age.max' => 'La edad no es válida',
        ];

        $validator = Validator::make($inputs, $rules, $messages)->errors();

        return $validator->all();
    }
}
