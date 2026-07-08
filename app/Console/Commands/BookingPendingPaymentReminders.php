<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\BookingController;
use App\Models\Booking;
use App\Models\User;
use App\Services\BookingPendingPayNotifier;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BookingPendingPaymentReminders extends Command
{
    protected $signature = 'app:booking-pending-pay-reminders';

    protected $description = 'Reservas con pago pendiente (status 1): recordatorio cada ~24 h; si pasaron 72 h desde creación, pasa a status 0 (cancelada).';

    public function handle(): int
    {
        // Usamos Carbon para manejar las fechas cómodamente
        Booking::where('status', 1)->orderBy('id')->chunkById(100, function ($bookings) {
            foreach ($bookings as $booking) {
                // 1. Calcular fecha y hora exacta del evento
                $eventDateTime = Carbon::parse($booking->date.' '.$booking->time_from);

                // 2. REGLA NUEVA: Cancelar si faltan 23 horas o menos para el evento
                // Comparamos: si "ahora + 23 horas" es mayor o igual al momento del evento, quedan menos de 23h.
                if (now()->addHours(23)->gte($eventDateTime)) {
                    $booking->update(['status' => 0]);
                    $users = [
                        'admin' => User::find(1),
                        'client' => User::find($booking->user_id),
                    ];

                    BookingController::cancelReserveNotification($users, $booking);

                    continue;
                }

                $createdAt = Carbon::parse($booking->created_at);
                $anchor = $booking->pending_pay_notification_sent_at
                    ? Carbon::parse($booking->pending_pay_notification_sent_at)
                    : $createdAt;

                if ($anchor->lte(now()->subHours(24))) {
                    BookingPendingPayNotifier::notify($booking);
                    $booking->update(['pending_pay_notification_sent_at' => now()]);
                }
            }
        });

        return self::SUCCESS;
    }
}
