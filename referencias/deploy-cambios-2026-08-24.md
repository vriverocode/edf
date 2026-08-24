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
- **Causa raíz:** `public/.env` tenía `MAIL_MAILER=log`,_Bluehost leía ese archivo en vez del `.env` raíz

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

### Paso 4: Ejecutar migraciones (si hay nuevas)
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
  - Panel →>Email →>Email Routing → debe estar en "Local Mail Exchanger"
  - Panel →Email →MX Entry → debe tener el registro MX correcto

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
```
