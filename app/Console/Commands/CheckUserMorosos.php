<?php

namespace App\Console\Commands;

use App\Models\Quota;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckUserMorosos extends Command
{
    protected $signature = 'app:check-user-morosos';

    protected $description = 'Marca como morosos (status=2) a usuarios con cuotas pendientes/vencidas > 2 meses';

    private array $stats = [
        'old_quotas_found' => 0,
        'users_marked_moroso' => 0,
        'users_restored_al_dia' => 0,
        'already_moroso' => 0,
        'inactive_skipped' => 0,
    ];

    public function handle(): int
    {
        $cutoff = Carbon::now()->subMonthsNoOverflow(2);

        $this->info('Fecha de corte: ' . $cutoff->toDateString());

        $oldQuotas = $this->getOldPendingQuotas($cutoff);
        $this->stats['old_quotas_found'] = $oldQuotas->count();

        $responsibleIds = $this->markMorosos($oldQuotas);
        $this->restoreAlDia($responsibleIds);
        $this->printSummary();

        return 0;
    }

    private function getOldPendingQuotas(Carbon $cutoff)
    {
        return Quota::whereIn('status', [1, 4])
            ->where('due_date', '<', $cutoff)
            ->with('departament.owner', 'responsiblePivot.user')
            ->get();
    }

    private function markMorosos($oldQuotas): array
    {
        if ($oldQuotas->isEmpty()) {
            return [];
        }

        $userIds = collect();

        foreach ($oldQuotas as $quota) {
            $user = $quota->responsiblePivot?->user ?? $quota->departament?->owner;

            if (! $user) {
                continue;
            }

            if ((int) $user->status === 3) {
                $this->stats['inactive_skipped']++;

                continue;
            }

            if ((int) $user->status === 2) {
                $this->stats['already_moroso']++;
                continue;
            }

            $userIds->push($user->id);
        }

        $uniqueIds = $userIds->unique()->values()->toArray();

        if (! empty($uniqueIds)) {
            $updated = User::whereIn('id', $uniqueIds)->update(['status' => 2, 'parentesco' => 'moroso']);
            $this->stats['users_marked_moroso'] = $updated;

            Log::info('[CheckUserMorosos] Marcados como morosos', [
                'user_ids' => $uniqueIds,
                'cutoff' => now()->toDateTimeString(),
            ]);
        }

        // Return all users currently responsible for old debt (including already morosos)
        return $oldQuotas
            ->map(fn ($q) => $q->responsiblePivot?->user?->id ?? $q->departament?->owner?->id)
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    private function restoreAlDia(array $currentDebtorIds): void
    {
        $cutoff = Carbon::now()->subMonthsNoOverflow(2);

        // Collect all users currently marked as moroso
        $allMorosos = User::where('status', 2)->pluck('id')->toArray();

        if (empty($allMorosos)) {
            return;
        }

        // A moroso can only be restored if they have zero old pending/overdue quotas.
        // We verify directly against the DB so that quotas that moved to status=3
        // (approved/paid) are correctly excluded from "still has debt" check.
        $stillInDebt = Quota::whereIn('status', [1, 4])
            ->where('due_date', '<', $cutoff)
            ->where(function ($query) {
                // Quota linked via responsiblePivot OR via departament owner
                $query->whereHas('responsiblePivot.user', fn ($q) => $q->where('users.status', 2))
                      ->orWhereHas('departament.owner', fn ($q) => $q->where('users.status', 2));
            })
            ->get()
            ->map(fn ($q) => $q->responsiblePivot?->user?->id ?? $q->departament?->owner?->id)
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        $toRestore = array_values(array_diff($allMorosos, $stillInDebt));

        if (empty($toRestore)) {
            return;
        }

        $restored = User::whereIn('id', $toRestore)->update(['status' => 1]);
        $this->stats['users_restored_al_dia'] = $restored;

        if ($restored > 0) {
            Log::info('[CheckUserMorosos] Restaurados a Pagos al día', [
                'user_ids' => $toRestore,
            ]);
        }
    }

    private function printSummary(): void
    {
        $this->newLine();
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Cuotas viejas encontradas (status 1/4, due_date > 2 meses)', $this->stats['old_quotas_found']],
                ['Usuarios marcados como morosos', $this->stats['users_marked_moroso']],
                ['Usuarios restaurados a Pagos al día', $this->stats['users_restored_al_dia']],
                ['Omitidos (ya estaban morosos)', $this->stats['already_moroso']],
                ['Omitidos (inactivos, status=3)', $this->stats['inactive_skipped']],
            ]
        );
    }
}
