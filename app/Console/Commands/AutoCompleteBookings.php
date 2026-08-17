<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\BookingController;
use App\Models\Booking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoCompleteBookings extends Command
{
    protected $signature = 'app:auto-complete-bookings';

    protected $description = 'Cada 30 min: completa automáticamente reservas en estado Exitoso cuyo horario ya finalizó. Si el área tiene garantía, pasa a Pend. devolución.';

    public function handle(): int
    {
        Booking::where('status', Booking::STATUS_SUCCESS)
            ->with('comunArea', 'pay')
            ->chunkById(100, function ($bookings) {
                foreach ($bookings as $booking) {
                    $eventDateTime = Carbon::parse(
                        $booking->date->toDateString().' '.$booking->time_from,
                        'America/Lima'
                    );
                    if ($eventDateTime->gt(now('America/Lima'))) {
                        continue;
                    }

                    $needsRefund = (float) ($booking->comunArea->warranty_price ?? 0) > 0
                        && $booking->pay != null
                        && (int) $booking->pay->status == 2;

                    $booking->update([
                        'status' => $needsRefund ? Booking::STATUS_PENDING_DEVO : Booking::STATUS_COMPLETED,
                        'kind' => $needsRefund ? 'warranty' : null,
                    ]);

                    BookingController::completeReserveNotification([
                        'admin' => User::find(1),
                        'client' => User::find($booking->user_id),
                    ], $booking);
                }
            });

        return self::SUCCESS;
    }
}
