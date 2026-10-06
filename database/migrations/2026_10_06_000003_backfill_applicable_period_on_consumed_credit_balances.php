<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Lote importado desde Excel ("DEVOL PARA SEPT. 26"). Todos los saldos de
     * esa importacion comparten este created_at.
     */
    private const IMPORT_BATCH_CREATED_AT = '2026-09-21 23:53:18';

    private const TARGET_MONTH = 9;

    private const TARGET_YEAR = 2026;

    public function up(): void
    {
        DB::transaction(function () {
            $rows = DB::table('credit_balances')
                ->where('applicable_month', 0)
                ->whereNull('applicable_year')
                ->where('created_at', self::IMPORT_BATCH_CREATED_AT)
                ->get(['id', 'departament_id', 'balance']);

            if ($rows->isEmpty()) {
                return;
            }

            $updated = 0;
            $skipped = [];

            foreach ($rows as $row) {
                // Solo saldos ya consumidos. Fijar el periodo en una fila con
                // saldo positivo cambiaria su semantica (de flexible a fijada),
                // asi que esas se dejan intactas a proposito.
                if ((float) $row->balance !== 0.0) {
                    continue;
                }

                // El indice unico es (departament_id, applicable_month,
                // applicable_year): no se puede fijar si el depto ya tiene fila
                // para septiembre 2026.
                $alreadyPinned = DB::table('credit_balances')
                    ->where('departament_id', $row->departament_id)
                    ->where('applicable_month', self::TARGET_MONTH)
                    ->where('applicable_year', self::TARGET_YEAR)
                    ->exists();

                if ($alreadyPinned) {
                    $skipped[] = $row->departament_id;

                    continue;
                }

                DB::table('credit_balances')
                    ->where('id', $row->id)
                    ->update([
                        'applicable_month' => self::TARGET_MONTH,
                        'applicable_year' => self::TARGET_YEAR,
                    ]);

                $updated++;
            }

            if ($updated > 0) {
                Log::info('credit_balances fijadas a '.self::TARGET_MONTH.'/'.self::TARGET_YEAR.": $updated");
            }

            if ($skipped !== []) {
                Log::warning('Omitidas por conflicto con el indice unico (deptos): '.implode(', ', $skipped));
            }
        });
    }

    /**
     * up() no es reversible de forma segura y down() es deliberadamente un no-op.
     *
     * up() lleva filas (month=0, balance=0) al estado (month=9, year=2026), que es
     * exactamente el mismo estado en el que ya estaban las 36 filas que la
     * migracion 2026_09_23 dejo fijadas y que luego se consumieron. Al no existir
     * ninguna marca que distinga "fijada por up()" de "fijada antes", un down()
     * basado en columnas revertiria tambien esas 36 y dejaria el lote de nuevo con
     * saldos flexibles que el reporte de septiembre no ve.
     *
     * El efecto de up() es inerte a nivel financiero (solo toca filas con saldo
     * 0.00, que ninguna consulta considera elegible por exigir balance > 0), asi
     * que revertir no aporta nada y sí introduce riesgo.
     */
    public function down(): void
    {
        Log::warning('backfill:quotas credit_balances: down() noop. La transformación no es invertible de forma segura; ver el docblock de la migración.');
    }
};
