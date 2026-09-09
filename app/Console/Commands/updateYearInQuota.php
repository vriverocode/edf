<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\Quota;
use Illuminate\Console\Command;

class UpdateYearInQuota extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-year-in-quota';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $quotas = Quota::all();
        foreach ($quotas as $quota) {
            $date = Carbon::parse($quota->due_date);
            $quota->update([
                'year' => (int) $date->year,
            ]);
        }

        $this->info('Year updated successfully');
    }
}
