<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\User;
use App\Notifications\RealtimeNotification;

/**
 * Envío de recordatorio "reserva creada, falta pago" (mismo contenido que PayController).
 */
final class BookingPendingPayNotifier
{
    public static function notify(Booking $booking): void
    {
        $users = [
            'admin' => User::find(1),
            'client' => User::find($booking->user_id),
        ];

        try {
            if ($users['client']) {
                $users['client']->notify(new RealtimeNotification(
                    title: 'Reserva no completada',
                    message: 'Tu reserva #' . $booking->booking_number . ' fue creada, pero falta que realices el pago',
                    url: '/client/reserves/view/' . $booking->id,
                    meta: [
                        'booking_id' => $booking->id,
                        'icon' => $booking->icon_status,
                    ]
                ));
            }

            if ($users['admin']) {
                $users['admin']->notify(new RealtimeNotification(
                    title: 'Nueva reserva no completada',
                    message: 'Se creó la reserva #' . $booking->booking_number . ', pero falta que se realice el pago correspondiente',
                    url: '/admin/reserves',
                    meta: [
                        'booking_id' => $booking->id,
                        'icon' => $booking->icon_status,
                    ]
                ));
            }
        } catch (\Throwable $e) {
        }
    }
}
