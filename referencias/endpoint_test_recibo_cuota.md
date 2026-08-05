# Endpoint de prueba: envío de recibo de cuota (BillInvoiceMail)

**Fecha:** 2026-08-05
**Estado:** Ya existente, sin cambios requeridos.

## Rutas

**Enviar recibo de cuota:**
```
POST /api/bill-invoices/test/send/{quotaId}
```

- `quotaId` → en la URL (entero, id de la cuota). Sin body.
- Requiere header `Authorization: Bearer <TOKEN_ADMIN>` (rol admin o super-admin)
- Envía `BillInvoiceMail` al inquilino responsable o, si no hay, al propietario del departamento
- No exige que la cuota esté pagada (status 3) — solo que exista

**Listar cuotas pagadas (helper para conseguir IDs):**
```
GET /api/bill-invoices/test/list-paid
```

- Sin parámetros. Devuelve hasta 50 cuotas status 3 con: `id`, `department_number`, `owner_name`, `owner_email`, `month`, `month_label`

## Uso (curl)

```bash
# 1. Encontrar cuotas pagadas
curl -H "Authorization: Bearer <TOKEN>" \
  https://tu-dominio/api/bill-invoices/test/list-paid

# 2. Enviar el recibo de una cuota
curl -X POST -H "Authorization: Bearer <TOKEN>" \
  https://tu-dominio/api/bill-invoices/test/send/123
```

## Ubicación en el código

- `app/Http/Controllers/Api/BillInvoiceController.php:313` — `testSend()`
- `app/Http/Controllers/Api/BillInvoiceController.php:347` — `listPaidQuotas()`
- `routes/api.php:324-325` — rutas dentro del grupo `bill-invoices` con middleware `role:admin,super-admin` (y `auth:sanctum` del grupo padre)
- `app/Services/BillInvoiceService.php:34` — `sendBillInvoiceForQuota()` (arma datos con `buildInvoiceData` y envía `BillInvoiceMail`)

## Notas

- `testSend` NO verifica `status === 3`; envía a cualquier cuota existente.
- Destinatario: `quota->responsiblePivot?->user ?? departament->owner`.
- Los mismos archivos están sincronizados en `SERVIDOR/` (mirror de producción), listos para desplegar.
- Para probar localmente se necesita una cuota existente (la BD local puede estar vacía).
