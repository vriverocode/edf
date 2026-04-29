<?php

namespace App\Jobs;

use App\Models\MonthlyBills;
use App\Models\WaterReading;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncWaterReadingsFromMonthlyBillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $monthlyBillId
    ) {}

    public function handle(): void
    {
        $bill = MonthlyBills::find($this->monthlyBillId);
        if (! $bill) {
            return;
        }

        $price = (float) $bill->water_price_per_m3;

        WaterReading::query()
            ->where('month', (int) $bill->month)
            ->where('year', (int) $bill->year)
            ->orderBy('id')
            ->chunkById(200, function ($readings) use ($price) {
                foreach ($readings as $reading) {
                    $consumption = (float) $reading->current_reading - (float) $reading->previous_reading;
                    if ($consumption < 0) {
                        $consumption = 0;
                    }
                    $reading->update([
                        'm3_price' => $price,
                        'amount' => round($consumption * $price, 2),
                    ]);
                }
            });
    }
}
