<?php

namespace App\Console\Commands;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateEventStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-event-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza el status de los eventos segun su fecha y hora: 1 proximamente, 2 en curso, 3 completada';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $timezone = 'America/Lima';
        $now = now($timezone);

        Event::whereIn('status', [Event::STATUS_UPCOMING, Event::STATUS_IN_PROGRESS])
            ->whereNotNull('date')
            ->chunkById(100, function ($events) use ($now, $timezone) {
                foreach ($events as $event) {
                    $start = Carbon::parse($event->date.' '.$event->time_from, $timezone);
                    $end = Carbon::parse($event->date.' '.$event->time_to, $timezone);

                    // Eventos que cruzan la medianoche (time_to < time_from)
                    if ($end->lt($start)) {
                        $end->addDay();
                    }

                    $target = match (true) {
                        $now->lt($start) => Event::STATUS_UPCOMING,
                        $now->gt($end) => Event::STATUS_COMPLETED,
                        default => Event::STATUS_IN_PROGRESS,
                    };

                    if ((int) $event->status !== $target) {
                        $event->update(['status' => $target]);
                    }
                }
            });

        return self::SUCCESS;
    }
}
