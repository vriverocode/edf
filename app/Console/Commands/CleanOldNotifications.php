<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

class CleanOldNotifications extends Command
{
    protected $signature = 'app:clean-old-notifications
                            {--months=2 : Antiguedad maxima a conservar, en meses}
                            {--chunk=1000 : Cantidad de registros a eliminar por tanda}
                            {--dry-run : Solo informa cuantas notificaciones se eliminarian}';

    protected $description = 'Elimina de la base de datos las notificaciones con mas de N meses de antiguedad';

    public function handle(): int
    {
        $months = (int) $this->option('months');

        if ($months < 1) {
            $this->error('El valor de --months debe ser 1 o mayor.');

            return 1;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $cutoff = Carbon::now()->subMonths($months);

        $total = DatabaseNotification::where('created_at', '<', $cutoff)->count();

        $this->info(sprintf(
            'Notificaciones con created_at anterior a %s: %d',
            $cutoff->toDateTimeString(),
            $total
        ));

        if ($total === 0) {
            $this->info('No hay notificaciones que eliminar.');

            return 0;
        }

        if ($dryRun) {
            $this->info("[dry-run] Se eliminarian $total notificacion(es). No se modifico nada.");

            return 0;
        }

        $deleted = 0;

        // Se borra por tandas de ids en vez de un unico DELETE para no bloquear
        // la tabla notifications mientras el where corre sobre created_at.
        while (true) {
            $ids = DatabaseNotification::where('created_at', '<', $cutoff)
                ->orderBy('created_at')
                ->limit($chunk)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deletedNow = DatabaseNotification::whereIn('id', $ids)->delete();
            $deleted += $deletedNow;

            // Si el lote venia completo pero no se borro nada, algo cambio el
            // conjunto entre el select y el delete: salir evita un bucle infinito.
            if ($deletedNow === 0) {
                break;
            }

            if ($ids->count() < $chunk) {
                break;
            }
        }

        $this->info("Se eliminaron $deleted notificacion(es).");

        if ($deleted !== $total) {
            $this->warn("Se esperaban $total y se eliminaron $deleted. Revisar si otra tarea escribio Notifications durante la limpieza.");
        }

        return 0;
    }
}
