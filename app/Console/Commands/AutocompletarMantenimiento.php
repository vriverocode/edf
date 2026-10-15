<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\Maintenance;
use Illuminate\Console\Command;

class AutocompletarMantenimiento extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:autocompletar-mantenimiento';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        Maintenance::where('status', 1)
            ->whereDate('date', '<=', now('America/Lima'))
            ->chunkById(100, function ($maintenances) {
                foreach ($maintenances as $maintenance) {
                    $maintenanceOverDateTime = Carbon::parse(
                        $maintenance->date->toDateString() . ' ' . $maintenance->time_to,
                        'America/Lima'
                    );
                    if ($maintenanceOverDateTime->gt(now('America/Lima'))) {
                        continue;
                    }

                    $maintenance->update([
                        'status' => 2,
                    ]);
                }
            });

        return self::SUCCESS;
    }
}
