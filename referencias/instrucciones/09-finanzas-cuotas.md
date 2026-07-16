# Instrucciones — Finanzas: Cuotas (#30)

**Archivos principales involucrados:**
- `frontend/src/resources/view/admin/Quotas/quotasMenu.vue`
- `frontend/src/resources/view/admin/Quotas/quotasMaintenaceList.vue`
- `frontend/src/resources/view/admin/Quotas/quotasMaintenanceMonthDetail.vue`
- `frontend/src/resources/services/store/quota.store.js`

---

### #30 — Cuotas de mantenimiento: sin data cargada para validar

**Prioridad:** 🟡 Baja
**Tipo:** No verificado

**Problema:**
No se pudo evaluar la funcionalidad de "Cuotas de mantenimiento" porque no había datos cargados en el momento de la prueba.

**Causa raíz:**
El reporte menciona que no hay datos en el entorno de prueba. Esto puede deberse a:
1. No se ha generado ninguna cuota de mantenimiento aún (sistema nuevo)
2. Las cuotas se generan automáticamente al crear un presupuesto mensual y puede que no se haya creado ninguno
3. Las cuotas existen pero no se muestran por un bug

**Solución:**

**Paso 1:** Verificar el flujo de creación de cuotas:
   - Revisar `monthlyBillsForm.vue` → al crear presupuesto mensual, ¿se generan cuotas automáticamente?
   - Revisar si hay un botón "Generar cuotas" en algún lado
   - Revisar `quotasMaintenaceList.vue` para entender cómo carga los datos

**Paso 2:** Si las cuotas no se generan automáticamente, crear acción para generarlas:
   - Botón "Generar cuotas del mes" en `monthlyBillsList.vue` o `monthlyBillsDetails.vue`
   - Llamar a `POST /api/quotas/generate?month=X&year=Y`

**Paso 3:** Si el problema es solo de datos de prueba, precargar data:
   - Crear seeders en Laravel para generar cuotas de mantenimiento de prueba
   - O crear un presupuesto mensual manualmente y verificar que las cuotas aparecen

**Archivos a modificar:**
- (Depende del diagnóstico — puede no requerir cambios si solo faltan datos de prueba)
- Opcional: `frontend/src/resources/view/admin/MonthlyBills/monthlyBillsDetails.vue` — botón "Generar cuotas"
- Opcional: `frontend/src/resources/services/store/quota.store.js` — acción `generateMonthlyQuotas`

**Validación:**
1. Tener al menos un presupuesto mensual creado
2. Ir a Finanzas → Cuotas → Cuotas de mantenimiento
3. Si no hay datos, usar el botón "Generar cuotas del mes"
4. Verificar que aparecen las cuotas generadas con montos correctos

---

**Nota:** Este hallazgo (#30) es prioritario revisar **después** de tener data real o de prueba en el sistema. Sin datos, no se puede determinar si hay bugs reales en el flujo de cuotas.

**Posibles bugs a investigar cuando haya data:**
- Los montos de las cuotas se calculan correctamente según el % de participación (#19)
- Las cuotas se generan para todos los propietarios con unidades
- El estado de pago se actualiza correctamente al registrar un pago
- La vista de detalle por mes funciona correctamente

---

**Siguiente archivo:** `10-general.md` — Hallazgos #41, #42
