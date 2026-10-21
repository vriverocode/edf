# Plan: Saldo a favor en viewQuota + contador en quotasMaintenanceMonthDetail

## Contexto (investigación)

### viewQuota.vue (`view/client/Quotas/viewQuota.vue`, 1144 líneas)
- Carga datos con `quotaStore.getQuotaById(id)` → `GET /api/quotas/byId/{id}` → `QuotaController::show` (`QuotaController.php:572-770`).
- El backend **ya expone** `data.pay` (Pay seleccionado: status 2 → 1 → cualquiera) y `data.pays[]` (relación `pay_quota`), todos con columna `credit_applied`.
- El frontend **ya muestra** "Saldo a favor aplicado" en "Datos del pago" (líneas 855-858, `payData.credit_applied`), pero **no lo resta** del total.
- Bloque de totales: líneas **813-835** — `Sub-total otros/agua/mantenimiento/extras` y **`TOTAL DEL MES` = `money(grandTotal)`** (líneas 831-834). `grandTotal` es un computed (líneas 211-213).
- **No hay cambios backend necesarios.**

### quotasMaintenanceMonthDetail.vue (645 líneas)
- `quotas = ref([])` (línea 16) recibe `response.data` (array de **grupos por propietario**, un grupo = una card) de `GET /api/quotas/admin/by-month/{month}`; se recarga con cada filtro.
- Filtros: líneas **263-285**. Contenedor de cards: línea **292** (`v-if="quotas.length > 0"`). Spinner: 286-288. Vacío: 615-621.
- Patrón de contador existente en el repo: `{{ expenses.length }} registros` (`reportExpenses.vue:99`, `reportUserQuotas.vue:102`, `creditsList.vue:69`).

---

## Cambios

### 1. `frontend/src/resources/view/client/Quotas/viewQuota.vue`

**a) Script — nuevo computed junto a los existentes (~línea 211):**
```js
const creditApplied = computed(() => Number(payData.value?.credit_applied || 0))
const finalTotal = computed(() => round2(grandTotal.value - creditApplied.value))
```
(`payData` ya existe — mapeado en `fetchQuotaById` línea 344-349; usar `pay.credit_applied` del pago canónico ya mostrado en "Datos del pago". Si no hay pago o credit_applied = 0 → no se muestra la línea.)

**b) Template — bloque Totales (líneas 813-835):**
- Insertar fila antes de `TOTAL DEL MES`, solo si `creditApplied > 0`:
  - Label: "Saldo a favor aplicado"
  - Valor: `- S/. {{ money(creditApplied) }}` (estilo similar a los otros sub-totales, color verde/negativo)
- Cambiar el valor de `TOTAL DEL MES` de `money(grandTotal)` → `money(finalTotal)`.
- Mismo ajuste en la celda "Total mes (todas las unidades)" de la info general (líneas 470-472) si muestra `grandTotal`, para mantener coherencia.

Verificación visual: cuando `credit_applied = 0` o no hay pay, el bloque queda idéntico al actual.

### 2. `frontend/src/resources/view/admin/Quotas/quotasMaintenanceMonthDetail.vue`

**Contador de registros** — insertar después de la fila de filtros (línea 285) y antes del spinner (286), visible cuando no carga:
```html
<div v-if="!loading" class="q-px-4 md:px-32 q-mb-sm text-caption text-grey-7">
  {{ recordCount }} registro{{ recordCount === 1 ? '' : 's' }}
</div>
```
- Es pequeño (text-caption), siempre visible (con y sin filtros), y se actualiza solo porque `quotas` se recarga en cada filtro.

**Regla de conteo (confirmada):** no es `quotas.length` simple — por cada card (grupo):
- Contar los `details` cuyo `departament.type === 1` (un card con 2 dpt type 1 = **2**)
- Si el card no tiene ningún dpt type 1 (ej. usuario con rol propietario parcial / otras unidades) = **1**

```js
const recordCount = computed(() =>
  quotas.value.reduce((sum, group) => {
    const details = Array.isArray(group.details) ? group.details : []
    const typeOneIds = new Set(
      details
        .filter((d) => Number(d?.departament?.type) === 1)
        .map((d) => d?.departament?.id)
    )
    return sum + (typeOneIds.size > 0 ? typeOneIds.size : 1)
  }, 0)
)
```
- IDs únicos por seguridad (evita doble conteo si hubiera 2 cuotas del mismo depto en un grupo).
- Estructura confirmada en `Quota::groupConsolidatedByOwner` (`Quota.php:384-484`): cada card incluye `details[]` con `departament.type` e `id`.

---

## Archivos a tocar

| Archivo | Acción |
|---|---|
| `frontend/src/resources/view/client/Quotas/viewQuota.vue` | computed `creditApplied`/`finalTotal` + fila de descuento en bloque Totales (y celda info general) |
| `frontend/src/resources/view/admin/Quotas/quotasMaintenanceMonthDetail.vue` | contador "N registros" bajo los filtros |

Sin cambios backend.

## Verificación

1. Abrir una cuota pagada con `credit_applied > 0` → ver fila "Saldo a favor aplicado" y TOTAL DEL MES reducido; cuota sin crédito → vista idéntica a la actual.
2. `quotasMaintenanceMonthDetail` → contador visible con/sin filtros y cambia al filtrar.
3. Prettier en los dos archivos (sin punto y coma, single quotes).
