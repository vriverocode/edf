<?php

namespace App\Jobs;

use App\Models\AnnualBudget;
use App\Models\Expense;
use App\Models\MonthlyBills;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateMonthlyBudgetJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $month,
        public int $year,
    ) {}

    public function handle(): array
    {
        $budget = AnnualBudget::active()->where('year', $this->year)->first();
        if (! $budget) {
            return ['error' => 'No hay presupuesto activo para el año '.$this->year];
        }

        $monthlyBill = MonthlyBills::firstOrCreate(
            ['month' => $this->month, 'year' => $this->year],
            [
                'monthly_budget' => $budget->total_monthly_budget,
                'total_maintenance_budget' => $budget->total_monthly_budget,
                'water_price_per_m3' => 0,
                'is_published' => false,
            ]
        );

        $templates = Expense::where('is_template', true)
            ->where('annual_budget_id', $budget->id)
            ->orderBy('sort_order')
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($templates as $template) {
            $exists = Expense::where('is_template', false)
                ->where('monthly_bill_id', $monthlyBill->id)
                ->where('description', $template->description)
                ->where('amount', $template->amount)
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $firstDay = Carbon::create($this->year, $this->month, 1);
            $lastDay = $firstDay->copy()->endOfMonth();

            Expense::create([
                'provider_id' => null,
                'service_category_id' => $template->service_category_id,
                'monthly_bill_id' => $monthlyBill->id,
                'amount' => $template->amount,
                'issue_date' => $firstDay,
                'due_date' => $lastDay,
                'expense_type' => $template->expense_type,
                'description' => $template->description,
                'status' => 1,
                'is_template' => false,
            ]);

            $created++;
        }

        return [
            'monthly_bill_id' => $monthlyBill->id,
            'templates' => $templates->count(),
            'created' => $created,
            'skipped' => $skipped,
        ];
    }
}
