# Deploy - Cambios 6-7 Septiembre 2026

## Resumen de cambios

### 1. Reporte de Pagos — Vista y Backend completos
- **Archivo:** `app/Http/Controllers/Api/ReportController.php` — `paymentsReport()` con paginación, filtros (search, status, date range), métricas y sort dinámico. `exportPayments()` usa Excel export con Maatwebsite
- **Archivo:** `app/Exports/PaymentsExport.php` — **NUEVO** exporta Excel con header azul `#2D6FB5`, texto blanco, column widths, row heights, bordes sutiles, sort activo
- **Archivo:** `frontend/.../reportPayments.vue` — **NUEVO** tabla con búsqueda, filtro estado, rango fechas (Hoy/Este mes/Limpiar), 5 cards métricas, 8 columnas ordenables (Fecha, Usuario, Depto., Cuota, Monto, Método, Referencia, Estado), paginación, exportar Excel
- **Archivo:** `frontend/.../report.store.js` — `getPaymentsReport()` y `exportPaymentsReport()` con blob download
- **Archivo:** `frontend/.../routes/index.js` — ruta `/admin/reports/payments`
- **Archivo:** `frontend/.../reportsMenu.vue` — card "Reporte de pagos" agregada
- **Ruta API:** `GET /api/reports/payments` + `GET /api/reports/payments/export`

### 2. Sorting en reporte de pagos (Depto., Cuota, Fecha, Monto, Estado)
- **Backend:** sort dinámico con JOINs para campos relacionados:
  - `dept_number` → JOIN `pay_quota` → `quotas` → `departaments`, ordena por `departaments.number`
  - `month` → JOIN `pay_quota` → `quotas`, ordena por `quotas.month`
  - `pay_date`, `amount`, `status` → orden directo en tabla `pays`
- **Frontend:** 5 columnas ordenables con ícono de dirección y subrayado activo
- **Export Excel** también respeta el sort activo

### 3. Filtros en página de pagos de mantenimiento
- **Archivo:** `frontend/.../payMaintenanceList.vue` — búsqueda por nombre de usuario o número de departamento, rango de fecha con `q-date` popup (DD/MM/YYYY), botón "Limpiar filtros"
- **Backend:** `PayController::applyPaysFilter()` — search con `whereHas` sobre user y departament, date range con conversión `Carbon::createFromFormat('d/m/Y')`

### 4. Pagos mensuales clickeables
- **Archivo:** `frontend/.../reportMonthlyPays.vue` — celdas clickeables → `/client/quota/view/:id`, fix `quotaId` → `quota_id`, `@click` en `<td>` (no en `<template>`), CSS hover underline

### 5. Vista de cuota (viewQuota) mejorada
- **Archivo:** `frontend/.../viewQuota.vue` — optional chaining en `quota.departament?.owner?.name`, desglose detallado (Mantenimiento %, Agua consumo, Total) con `Promise.allSettled`, variable renames descriptivos (`quotaData`, `isLoading`, `fetchQuotaById`, etc.), q-chip de estado con `status_label`/`status_color`, "Responsable de pago" con `responsible_pivot?.user?.name`
- **Backend:** `QuotaController::show()` carga `departament.owner`, `pays.payMethod`, `responsiblePivot.user`, `waterReading`

### 6. Notificaciones push asíncronas
- **Archivo:** `app/Jobs/SendNotificationJob.php` — **NUEVO** job genérico `ShouldQueue` (3 retries, 30s timeout) para enviar notificaciones Push/Firebase
- **Backend:** `PayController` usa `SendNotificationJob::dispatch()` en todos los métodos de notificación. Store del pago movido fuera de la transacción DB

### 7. Monto pendiente en header para inquilinos
- **Backend:** `GET /api/user` retorna `tenant_pending_amount` y `tenant_pending_count`
- **Archivo:** `frontend/.../headerLayout.vue` — bloque de mantenido pendiente para rol 3

### 8. Fix de referencias numéricas
- **Archivo:** `frontend/.../createReserve.vue` — input de referencia con máscara numérica

### 9. Fix de q-select duplicado
- **Archivo:** `frontend/.../departmentList.vue` — corregido nombre duplicado en `q-select`

### 10. Notificación de actualización de app
- **Backend:** `AppUpdateController::notifyUpdateApp()` — endpoint público `POST /api/notify-update-app`
- **Archivo:** `referencias/deploy-cambios-2026-08-31.md` — doc previo de referencia

## Archivos modificados (27)
```
app/Exports/PaymentsExport.php              (NUEVO — 174 líneas)
app/Jobs/SendNotificationJob.php            (NUEVO — 49 líneas)
app/Http/Controllers/Api/ReportController.php    (+108 líneas — paymentsReport, exportPayments, dept/month sort)
app/Http/Controllers/Api/PayController.php       (+15 — async notifications)
app/Http/Controllers/Api/QuotaController.php     (+7 — eager loading en show)
app/Http/Controllers/Api/NoticeController.php    (fix)
routes/api.php                                  (+10 — reportes pagos)
frontend/.../reportPayments.vue                  (NUEVO — 457 líneas)
frontend/.../report.store.js                     (+46 — getPaymentsReport, exportPaymentsReport)
frontend/.../routes/index.js                     (+12 — /admin/reports/payments)
frontend/.../reportsMenu.vue                     (+6 — card reporte pagos)
frontend/.../payMaintenanceList.vue              (+65 — search, date range)
frontend/.../reportMonthlyPays.vue               (+20 — clickable cells)
frontend/.../viewQuota.vue                       (+190 — breakdown detallado, null safety, renames)
frontend/.../headerLayout.vue                    (+9 — tenant pending block)
frontend/.../createReserve.vue                   (fix máscara numérica)
frontend/.../departmentList.vue                  (fix q-select duplicado)
frontend/.../usersList.vue                       (fixes)
frontend/.../assignPropertyPage.vue             (fixes)
frontend/.../userPayments.vue                   (fix)
frontend/.../validatePay.vue                    (fix)
frontend/.../quotasMaintenanceMonthDetail.vue   (fix)
frontend/.../createUnit.vue                     (fix)
frontend/.../optionList.vue                     (fix)
frontend/.../editFamiliar.vue                   (+11)
frontend/.../dashboard.vue                      (+6 — cuotas menu role 3)
```
