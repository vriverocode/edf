# Cambios - 10 de Septiembre 2026

## Resumen

Limpieza de basura en tabla `pays` (732 soft-deleted) y fortalecimiento de la dedup en el comando de importación de cuotas/pagos agosto.

---

## 1. Limpieza de pagos soft-deleted

### Problema
Las limpiezas anteriores usaban `Pay::whereIn(...)->delete()` que ejecuta `UPDATE pays SET deleted_at = ...` (SoftDeletes). Resultado: **732 pagos soft-deleted** invisibles a las queries del modelo pero presentes en la tabla raw.

La tabla `pays` tenía 1,118 filas (386 activas + 732 soft-deleted). 347 cuotas tenían 3 registros en `pays` (2 soft-deleted + 1 activo).

### Solución
Limpieza manual de la tabla `pays` eliminando permanentemente los registros soft-deleted y sus pivotes huérfanos en `pay_quota`.

### Resultado final
| Métrica | Antes | Después |
|---------|-------|---------|
| Pagos en tabla raw | 1,118 | 428 |
| Pagos activos (model) | 386 | 428 |
| Pay_quota pivots | 386 | 388 |
| Quotas status=3 | 385 | 387 |
| Pagos con >1 pivot | 347 | **0** |
| Pivots huérfanos | - | **0** |

---

## 2. Dedup en `createPay()` - 2 niveles

**Archivo:** `app/Console/Commands/ImportCuotasPagosAgosto.php`

### NIVEL 1: Verificación por quota_id
- Busca `Pay::where('quota_id', $quota->id)->exists()` (quota_id directo)
- Busca `Pay::whereHas('quotas', ...)` (vía pivote `pay_quota`)
- Si existe → skip, incrementa `pays_duplicated`

### NIVEL 2: Verificación por igualdad exacta
- Busca `Pay::where('user_id', $userId)->where('amount', $abonoVal)->where('pay_date', $payDate)->where('type', 1)->exists()
- Detecta pagos duplicados aunque estén vinculados a cuotas diferentes

### Resultado de idempotencia
| Corrida | Pagos creados | Pagos duplicados |
|---------|---------------|------------------|
| 1ra | 345 | 43 |
| 2da | 0 | 388 |

---

## 3. Vínculo dual quota_id + pivot

Cada pago nuevo se vincula a su cuota de **dos formas**:
1. `pays.quota_id` = FK directa a `quotas.id`
2. `pay_quota` pivot = relación N:N

Esto permite que `Pay::consolidatedQuotaIds()` funcione con 3 niveles de fallback:
1. `consolidated_ids` JSON (snapshot)
2. Relación `quotas()` vía pivot
3. Campo `quota_id` legacy

---

## 4. Fix case-sensitive `dpt-` → `DPT-`

**Archivo:** `app/Console/Commands/ImportCuotasPagosAgosto.php`

- `resolveDepartaments()` y `parseSinglePredio()` usaban `dpt-` en minúsculas
- Corregido a `DPT-` (mayúsculas) para coincidir con el formato real de la BD

---

## 5. Fix year lookup en cuotas

**Archivo:** `app/Console/Commands/ImportCuotasPagosAgosto.php`

- Cambiado `->whereYear('due_date', $year)` → `->where('year', $year)`
- La columna `year` fue agregada por la migración `2026_09_09` y es más directa que calcular el año desde `due_date`

---

## 6. Separación en 2 pasos

**Archivo:** `app/Console/Commands/ImportCuotasPagosAgosto.php`

El `handle()` original hacía todo en un solo loop. Ahora ejecuta 3 pasos separados:

1. **Paso 1:** `processRowQuotas()` — crea cuotas (843 filas)
2. **Paso 2:** `processRowPays()` — crea pagos solo para filas con abono > 0 (392 filas)
3. **Paso 3:** Actualiza status de cuotas pagadas a `3` (Pagada)

---

## Estado final de la BD

| Tabla | Registros |
|-------|-----------|
| pays | 428 |
| pay_quota | 388 |
| quotas (status=3) | 387 |
| quotas (con pago y status!=3) | 0 |

### Departamentos sin usuario (4)
DPT-204, EST-003, EST-008, EST-011

---

## Archivos modificados

| Archivo | Cambio |
|---------|--------|
| `app/Console/Commands/ImportCuotasPagosAgosto.php` | Fix case-sensitive DPT-, year lookup, 2 pasos, dedup 2 niveles, quota_id + pivot + status=3 |
