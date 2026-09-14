# Plan: Sistema de Saldo a Favor (Créditos por Departamento)

**Fecha:** 2026-09-14
**Estado:** Pendiente de implementación

---

## Objetivo

Implementar un sistema de saldos a favor para propietarios/inquilinos. Si un pago es mayor al monto de la cuota, la diferencia se guarda como crédito y se aplica automáticamente a la siguiente cuota.

---

## 1. Base de Datos — 2 tablas nuevas

### Tabla `credit_balances`
Saldo actual por departamento.

| Columna | Tipo | Notas |
|---------|------|-------|
| `id` | bigint PK | auto |
| `departament_id` | bigint FK → departaments | UNIQUE, un registro por depto |
| `balance` | decimal(15,2) | default 0, saldo actual |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

### Tabla `credit_transactions`
Historial de cada movimiento de crédito.

| Columna | Tipo | Notas |
|---------|------|-------|
| `id` | bigint PK | auto |
| `departament_id` | bigint FK → departaments | |
| `type` | enum('created','applied','expired') | Tipo de transacción |
| `amount` | decimal(15,2) | Monto de la transacción |
| `balance_after` | decimal(15,2) | Saldo después de la transacción |
| `pay_id` | bigint FK → pays | nullable, pago que generó/usó el crédito |
| `quota_id` | bigint FK → quotas | nullable, cuota donde se aplicó |
| `description` | text | Descripción legible |
| `created_at` | timestamp | |

---

## 2. Backend — Archivos a crear/modificar

### Archivos nuevos

| Archivo | Descripción |
|---------|-------------|
| `database/migrations/xxxx_create_credit_balances_table.php` | Migración tabla saldos |
| `database/migrations/xxxx_create_credit_transactions_table.php` | Migración tabla transacciones |
| `app/Models/CreditBalance.php` | Modelo saldo por departamento |
| `app/Models/CreditTransaction.php` | Modelo historial de transacciones |
| `app/Services/CreditService.php` | Servicio de lógica de créditos |

### Archivos a modificar

| Archivo | Cambios |
|---------|---------|
| `app/Http/Controllers/Api/PayController.php` | Detectar sobrepago, crear crédito, aplicar crédito |
| `app/Http/Controllers/Api/QuotaController.php` | Mostrar crédito aplicado en cuotas |
| `app/Models/Quota.php` | Atributos virtuales: `credit_applied`, `amount_after_credit` |
| `routes/api.php` | Nuevo endpoint `GET /api/departaments/{id}/credit-balance` |

### Método principal del servicio CreditService

```php
class CreditService
{
    // Obtener saldo actual de un departamento
    public function getBalance(Departament $dept): float

    // Crear crédito por sobrepago
    public function createCredit(Departament $dept, float $amount, Pay $pay): CreditBalance

    // Aplicar crédito a una cuota
    public function applyCredit(Departament $dept, Quota $quota, Pay $pay): float

    // Obtener historial de transacciones
    public function getHistory(Departament $dept): Collection
}
```

### Cambios en PayController::storePay

```php
// Después de crear el Pay y vincularlo a las cuotas:
$overpaymentAmount = $request->input('overpayment_amount', 0);

if ($overpaymentAmount > 0) {
    $totalQuotaAmount = $quotas->sum('amount');
    $creditAmount = $pay->amount - $totalQuotaAmount;

    if ($creditAmount > 0) {
        $creditService = new CreditService();
        $creditService->createCredit($departament, $creditAmount, $pay);
    }
}
```

### Cambios en PayController::validatePayment

```php
// Al aprobar pago (status=2):
// Si el pago tenía crédito aplicado, descontar del balance
if ($pay->credit_applied > 0) {
    $creditService = new CreditService();
    $creditService->applyCredit($departament, $quota, $pay);
}
```

---

## 3. Frontend — Archivos a modificar

| Archivo | Cambios |
|---------|---------|
| `frontend/src/resources/view/client/Payments/payForm.vue` | Checkbox "Transferí de más" + campo monto |
| `frontend/src/resources/view/client/Quotas/quotasByUserList.vue` | Badge saldo a favor + monto con descuento |
| `frontend/src/resources/view/admin/Pays/payMaintenanceList.vue` | Columna "Saldo a favor" |
| `frontend/src/resources/services/store/pay.store.js` | Enviar `overpayment_amount` |
| `frontend/src/resources/services/store/quota.store.js` | Recibir datos de crédito |

### Cambios en payForm.vue

- Nuevo checkbox: `v-model="transferMore"` → "Transferí de más / Tengo saldo a favor"
- Al marcar checkbox: mostrar campo `overpayment_amount` (monto total transferido)
- Calcular automáticamente: `creditAmount = overpaymentAmount - totalQuota`
- Mostrar saldo a favor actual del departamento (fetch al montar el componente)

### Cambios en quotasByUserList.vue

- Fetch saldo a favor del departamento al cargar
- Mostrar badge: "Saldo a favor: $200.00"
- Mostrar monto con descuento: "$300 → $100 (crédito: $200)"

### Cambios en payMaintenanceList.vue

- Nueva columna "Saldo a favor" que muestre el balance del departamento
- Filtro por departamentos con saldo > 0

---

## 4. Flujo completo

```
PASO 1: Pago con sobrepago
─────────────────────────────
- DPT-101 tiene cuota de $300 (status=1 Pendiente)
- Cliente abre payForm → ve "Saldo a favor: $0.00"
- Cliente marca "Transferí de más" → ingresa $500
- Backend crea Pay($500, status=1) → vincula a Quota($300)
- Diferencia $200 → CreditBalance(DPT-101, balance=200)
- CreditTransaction(DPT-101, type=created, amount=200)

PASO 2: Admin aprueba
─────────────────────────────
- Admin aprueba pago → Quota pasa a status=3 (Pagada)
- Se confirma el sobrepago de $200

PASO 3: Siguiente mes
─────────────────────────────
- Cuota de $350 se crea (status=1)
- Sistema detecta crédito de $200 en DPT-101
- Muestra: "Cuota $350 - Crédito $200 = Pagar $150"
- Cliente paga $150
- Backend crea Pay($150) → descuenta $200 del CreditBalance
- CreditBalance(DPT-101, balance=0)
- CreditTransaction(DPT-101, type=applied, amount=200)
```

---

## 5. Endpoints API

| Método | Ruta | Descripción |
|--------|------|-------------|
| GET | `/api/departaments/{id}/credit-balance` | Obtener saldo a favor |
| GET | `/api/departaments/{id}/credit-transactions` | Historial de transacciones |

---

## 6. Validaciones

- `overpayment_amount` debe ser >= monto total de cuotas
- No se pueden aplicar créditos si el balance es 0
- Un crédito solo se aplica una vez (idempotencia)
- Si el crédito es mayor a la cuota siguiente, el excedente queda como saldo
- Créditos no expiran (por ahora)

---

## 7. Enums

```php
// CreditTransaction types
const TYPE_CREATED = 'created';   // Sobrepago genera crédito
const TYPE_APPLIED = 'applied';   // Crédito aplicado a cuota
const TYPE_EXPIRED = 'expired';   // Crédito expirado (futuro)
```

---

## 8. Estimación de esfuerzo

| Tarea | Tiempo estimado |
|-------|----------------|
| Migraciones + modelos | 30 min |
| CreditService | 1 hora |
| Cambios en PayController | 1 hora |
| Cambios en Quota model | 30 min |
| payForm.vue (checkbox + lógica) | 1.5 horas |
| quotasByUserList.vue (mostrar crédito) | 1 hora |
| payMaintenanceList.vue (admin) | 30 min |
| pay.store.js + quota.store.js | 30 min |
| Testing | 1 hora |
| **Total** | **~7 horas** |
