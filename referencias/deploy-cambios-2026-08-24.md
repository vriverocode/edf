# Deploy - Cambios 24 Agosto 2026

## Resumen de cambios

### 1. Cuota consolidada por mes (frontend client)
- **Archivo:** `app/Models/Quota.php` — nuevo método `groupConsolidatedByMonth()`
- **Archivo:** `app/Http/Controllers/Api/QuotaController.php` — `index()` usa el nuevo método
- **Archivo:** `frontend/.../quotasByUserList.vue` — fix `goTo()` para cuotas pagadas
- **Resultado:** El cliente ve 1 sola cuota por mes (no 1 por departamento)

### 2. Asignación de cuotas a inquilinos
- **Archivo:** `app/Services/MonthlyQuotaService.php` — nuevo método estático `findActiveTenantPivotId()`
- **Archivo:** `app/Http/Controllers/Api/QuotaController.php` — `generate()` ahora busca inquilino activo
- **Archivo:** `app/Http/Controllers/Api/PayController.php` — `storePay()` bloquea pago del propietario sobre cuota del inquilino
- **Archivos:** 4 commands de importación Excel ahora setean `peoples_x_departments_id`

### 3. Envío de correo post-commit
- **Archivo:** `app/Http/Controllers/Api/PayController.php` — `sendBillInvoicesForPay()` movido después de `DB::commit()`

### 4. Limpieza de archivos .env
- Eliminados: `public/.env`, `referencias/.env`, `frontend/.env.old2`
- `public/.env` agregado a `.gitignore`
- **Causa raíz:** `public/.env` tenía `MAIL_MAILER=log`, Bluehost leía ese archivo en vez del `.env` raíz

### 5. Reporte de morosos (reconstrucción completa)
- **Archivo:** `app/Http/Controllers/Api/ReportController.php` — métodos `delinquents()`, `getDelinquentsData()`, `delinquentsMetrics()`, `exportDelinquents()`, `sendReminderDelinquents()`
- **Archivo:** `app/Exports/DelinquentsExport.php` — clase de exportación Excel (nuevo)
- **Archivo:** `routes/api.php` — rutas `GET /delinquents`, `GET /delinquents/export`, `GET /delinquents/metrics`, `POST /delinquents/send-reminder`
- **Archivo:** `frontend/.../reportDelinquents.vue` — reconstruido con paginación server-side, búsqueda, métricas, exportación Excel, campanita de recordatorio
- **Archivo:** `frontend/.../report.store.js` — métodos `getDelinquents`, `getDelinquentsMetrics`, `exportDelinquents`
- **Resultado:** Tabla con paginación, búsqueda por nombre/DNI/departamento, 3 tarjetas de métricas, exportación Excel, envío de recordatorios

### 6. Flag `tenant_pays_quota` (propietario decide quién paga)
- **Archivo:** `database/migrations/2026_08_24_203955_add_tenant_pays_quota_to_departaments_table.php` — nueva columna boolean (default `false`)
- **Archivo:** `app/Models/Departament.php` — agregado `tenant_pays_quota` a `$fillable`
- **Archivo:** `app/Http/Controllers/Api/DepartamentController.php` — incluido en `updateApartment()`
- **Archivo:** `app/Services/MonthlyQuotaService.php` — `sendNotifications()` respeta el flag: si `true` notifica al inquilino, si `false` notifica al propietario
- **Archivo:** `frontend/.../myUnit.vue` — switch Vant por departamento para configurar quién paga
- **Resultado:** El propietario configura desde su panel si él o el inquilino paga la cuota

### 7. Filtros y mejoras en reporte de morosos
- **Archivo:** `app/Http/Controllers/Api/ReportController.php` — excluye usuarios admin/super-admin/trabajador/parcial, excluye cuotas con `amount = 0`
- **Archivo:** `app/Models/Quota.php` — agregado `$casts` para `amount`, `maintenance_amount`, `water_amount` como `float`
- **Archivo:** `app/Models/Quota.php` — `due_date` usa último día del mes (Carbon `endOfMonth()`)

### 8. Tipo de departamento: Lavandería (TYPE_LAV = 4)
- **Archivo:** `app/Models/Departament.php` — nuevo `TYPE_LAV = 4` + `Lavandería` en `getTypeLabelAttribute()`
- **Archivo:** `app/Http/Controllers/Api/DepartamentController.php` — validación `type` actualizada a `in:1,2,3,4`
- **Archivo:** `frontend/.../Department/createUnit.vue` — opción `🧺 Lavandería` (value: 4)
- **Archivo:** `frontend/.../Department/departmentList.vue` — filtro `Lavanderías` (value: 4)
- **Archivo:** `frontend/.../Payments/viewPayOfQuotas.vue` — `getUnitInfo()` maneja type 4 → `Lavandería`
- **Resultado:** Se pueden crear y filtrar departamentos tipo Lavandería (ej: LAV-001)

### 9. Filtro por departamento en lecturas de agua
- **Archivo:** `app/Http/Controllers/Api/WaterReadingController.php` — `index()` acepta `departament_id` (validación + query constraint)
- **Archivo:** `frontend/.../store/waterReadings.store.js` — `filterQuery()` maneja `departament_id`
- **Archivo:** `frontend/.../WaterReadings/waterReadingsList.vue` — dropdown "Departamento" con opciones cargadas desde `getApartmentsByFind('allWithUser')`, default "Todos"
- **Resultado:** Filtrar lecturas de agua por departamento específico o ver todas

---

## Pasos para deploy en Bluehost

### Paso 1: Pull del código
```bash
cd /home/bluelata/public_html  # o la ruta de tu proyecto en Bluehost
git pull origin master
```

### Paso 2: Eliminar `public/.env` del servidor (si aún existe)
```bash
rm -f public/.env
```

### Paso 3: Limpiar caché de configuración
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### Paso 4: Ejecutar migraciones (incluye la nueva `tenant_pays_quota`)
```bash
php artisan migrate --force
```

### Paso 5: Verificar que el `.env` raíz tiene SMTP correcto
```bash
grep MAIL_MAILER .env
# Debe decir: MAIL_MAILER=smtp
```

### Paso 6: Probar el SMTP
```bash
php artisan tinker
```
```php
Mail::raw('Test de correo desde Pacifik', function ($m) {
    $m->to('frovic.ve@gmail.com')
      ->subject('Test SMTP - ' . now()->format('d/m/Y H:i'));
});
exit
```

### Paso 7: Verificar que el correo llegó
- Revisar bandeja de entrada de `frovic.ve@gmail.com`
- Si no llega, revisar spam
- Si no llega en ningún lado, verificar en Bluehost:
  - Panel → Email → Email Routing → debe estar en "Local Mail Exchanger"
  - Panel → Email → MX Entry → debe tener el registro MX correcto

### Paso 8: Probar flujo completo de cuota
1. Login como admin
2. Ir a `/admin/quotas/pays` → "Cuotas de mantenimiento"
3. Generar cuotas para un mes (si no existen)
4. Login como propietario → `/client/balance/list`
5. Verificar que muestra 1 sola cuota por mes
6. Realizar pago y que admin lo valide
7. Verificar que el correo de recibo llega al destinatario

### Paso 9: Probar que propietario NO puede pagar cuota de inquilino
1. Login como propietario que tenga un inquilino asignado
2. Intentar pagar una cuota que tiene inquilino
3. Debe recibir error 403

### Paso 10: Probar flag `tenant_pays_quota`
1. Login como propietario → `/client/department/my-unit`
2. Verificar que el switch "¿Quién paga la cuota?" aparece por cada departamento
3. Hacer toggle y verificar que se guarda (toast de confirmación)
4. Verificar que la cuota generada notifica al destinatario correcto

### Paso 11: Probar reporte de morosos
1. Login como admin → `/admin/reports/delinquents`
2. Verificar que las 3 tarjetas de métricas muestran datos correctos
3. Buscar por nombre, DNI o departamento
4. Verificar que la columna "Responsable Pago" muestra Inquilino/Propietario
5. Expandir una fila y verificar que el detalle muestra responsable por cuota
6. Probar exportación Excel
7. Probar envío de recordatorio (campanita)

### Paso 12: Probar tipo Lavandería
1. Login como admin → `/admin/departments/list`
2. Filtrar por "Lavanderías" → verificar que muestra LAV-001 (si existe)
3. Crear nuevo departamento tipo Lavandería → verificar que se guarda con type=4
4. Verificar que `type_label` muestra "Lavandería"

### Paso 13: Probar filtro por departamento en lecturas de agua
1. Login como admin → `/admin/water_readings`
2. Verificar que aparece el dropdown "Departamento" con opción "Todos" selected
3. Seleccionar un departamento específico → verificar que solo muestra lecturas de ese dept
4. Volver a "Todos" → verificar que muestra todas las lecturas
5. Cambiar mes/año + departamento → verificar que ambos filtros funcionan juntos

---

## Comandos útiles de verificación

```bash
# Ver logs de correo en tiempo real
tail -f storage/logs/laravel.log | grep -i "BillInvoice\|mail\|smtp"

# Verificar configuración de mail cargada
php artisan tinker --execute="dd(config('mail.default'))"
# Debe decir: "smtp"

# Verificar que no hay .env en public/
ls -la public/.env 2>/dev/null && echo "PELIGRO: public/.env existe" || echo "OK: no existe"

# Verificar destinatarios de correos recientes
grep "BillInvoiceService" storage/logs/laravel.log | tail -5

# Verificar que la columna tenant_pays_quota existe
php artisan tinker --execute="dd(DB::getSchemaBuilder()->hasColumn('departaments', 'tenant_pays_quota'))"
# Debe decir: true

# Verificar que la ruta del reporte de morosos existe
php artisan route:list --path=delinquents
```
