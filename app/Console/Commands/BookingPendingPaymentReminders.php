<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Services\BookingPendingPayNotifier;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BookingPendingPaymentReminders extends Command
{
    protected $signature = 'app:booking-pending-pay-reminders';

    protected $description = 'Reservas con pago pendiente (status 1): recordatorio cada ~24 h; si pasaron 72 h desde creación, pasa a status 0 (cancelada).';

    public function handle(): int
    {
        Booking::where('status', 1)->orderBy('id')->chunkById(100, function ($bookings) {
            foreach ($bookings as $booking) {
                $createdAt = Carbon::parse($booking->created_at);

                if ($createdAt->lte(now()->subHours(72))) {
                    $booking->update(['status' => 0]);

                    continue;
                }

                $anchor = $booking->pending_pay_notification_sent_at
                    ? Carbon::parse($booking->pending_pay_notification_sent_at)
                    : $createdAt;

                if ($anchor->lte(now()->subHours(24))) {
                    BookingPendingPayNotifier::notify($booking);
                    $booking->pending_pay_notification_sent_at = now();
                    $booking->save();
                }
            }
        });

        return self::SUCCESS;
    }
}
