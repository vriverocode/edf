# Análisis: Recibo PDF tipo 407-1

## Objetivo
Replicar el diseño del recibo 407-1.pdf (recibo de mantenimiento real del edificio) como PDF generado desde el sistema, con datos de MonthlyBills, WaterReadings, Quotas y Expenses. Enviar por correo como adjunto.

---

## Infraestructura existente

| Componente | Archivo | Estado |
|------------|---------|--------|
| DomPDF | `composer.json` → `barryvdh/laravel-dompdf ^3.1` | ✅ Instalado |
| Template PDF | `resources/views/bills/invoice.blade.php` (469 líneas) | ✅ Existente |
| Template email | `resources/views/mails/bills/invoice.blade.php` (207 líneas) | ✅ Existente |
| Service | `app/Services/BillInvoiceService.php` | ✅ `buildInvoiceData()` + `sendBillInvoiceForQuota()` |
| Controller | `app/Http/Controllers/Api/BillInvoiceController.php` | ✅ `downloadPdf()`, `sendEmail()`, `sendBulkEmails()` |
| Rutas | `routes/api.php` líneas 339-349 | ✅ 7 endpoints |

### Rutas existentes

```
GET  /api/bill-invoices/by-quota/{quotaId}        → show
GET  /api/bill-invoices/download/{quotaId}         → downloadPdf
GET  /api/bill-invoices/preview/{quotaId}          → previewHtml
POST /api/bill-invoices/send-email/{quotaId}       → sendEmail
POST /api/bill-invoices/send-bulk/{monthlyBillId}  → sendBulkEmails
GET  /api/bill-invoices/client-download/{quotaId}  → clientDownloadPdf
```

---

## Estructura del recibo 407-1.pdf (referencia)

### Sección 1: Header
- Dirección: "Av. Bertolotto 850, San Miguel"
- Nombre edificio: "JUNTA DE PROPIETARIOS DEL EDIFICIO MULTIFAMILIAR PACIFIK OCEAN TOWER"
- Título: "RECIBO DE MANTENIMIENTO"

### Sección 2: Datos del propietario
| Campo | Ejemplo | Fuente sistema |
|-------|---------|----------------|
| Propietario | CASTRO BARRERA ANITA MARITZA | `$owner->name` |
| DNI | 47608787 | `$owner->document` |
| Email | Anny.26cb@gmail.com | `$owner->email` |
| Código servicio | PAC407 | hardcoded o calc |
| Predio | 407 | `$departament->number` |

### Sección 3: Datos del recibo
| Campo | Ejemplo | Fuente sistema |
|-------|---------|----------------|
| Total cuota mes | S/ 351.00 | `$quota->amount` |
| Deuda anterior | S/ 376.76 | `$initialBalance` |
| Fecha emisión | 20/08/2026 | `$emissionDate` |
| Fecha vencimiento | 31/08/2026 | `$dueDate` |
| % Cobro aplicado | 0.577% | hardcoded o de config |
| % Participación | 0.58% | `$departament->participation_percentage * 100` |

### Sección 4: Áreas del propietario
| Tipo | Número | m² | Fuente |
|------|--------|-----|--------|
| Departamento | 407 | 66.45 | `$units` (foreach) |
| Estacionamiento | ESTAC-121 | 0.00 | `$units` (foreach) |
| Depósito | DEP-xxx | 0.00 | `$units` (foreach) |

### Sección 5: Detalle de conceptos (DESGLLOSE)
| Concepto | Importe | Fuente |
|----------|---------|--------|
| Servicios de Administración (BIOSEGURITEC) | 0.00 | Expense cat. admin |
| Agua Común {MES} {AÑO} | 0.00 | `$commonWaterCost` |
| Depósito Servicios Eléctricos S.G {nro} {MES} {AÑO} | 80.53 | Expense cat. electricidad |
| Servicios Eléctricos B.C.I. {nro} {MES} {AÑO} | 0.07 | Expense cat. electricidad |
| Cuota de Mantenimiento de Equipos y Personal | 218.44 | `$maintenanceAmount` |
| **Total** | **299.04** | `$totalAmount` |

**Nota**: Los gastos (Expenses) se desglosan por categoría. El sistema tiene `Expense` con `service_category_id` y `expense_type` (1=ordinario, 2=extraordinario).

### Sección 6: Lectura de agua
| Campo | Ejemplo | Fuente |
|-------|---------|--------|
| Lectura Anterior (A) | 395.057 | `$waterReading->previous_reading` |
| Lectura Actual (B) | 410.142 | `$waterReading->current_reading` |
| Consumo m³ (B-A) | 15.085 | `$waterConsumption` |
| Factor del mes | 3.44 | calc o hardcoded |
| Monto consumo agua | S/ 51.96 | `$waterAmount` |

### Sección 7: Sub-totales
- Sub-total Otros Cargos del Mes
- Sub-total Agua
- Sub-total Mantenimiento del Mes
- OTROS CARGOS
- **TOTAL**

### Sección 8: Cuentas recaudadoras (hardcoded)
```
JUNTA DE PROPIETARIOS DEL EDIFICIO MULTIFAMILIAR "PACIFIK OCEAN TOWER"
BCP CUENTA CORRIENTE EN SOLES: 193-7236791-0-97
BCP CCI: 00219300723679109712
```

### Sección 9: Footer
- Correo: adm.pacifikot@gmail.com
- Administrador: Jefry Vera Portillo
- Móvil: 924 039 563

---

## Template actual vs 407-1.pdf

| Sección | 407-1.pdf | Template actual (`invoice.blade.php`) |
|---------|-----------|--------------------------------------|
| Header | Dirección + nombre + "RECIBO DE MANTENIMIENTO" | Solo nombre + "RECIBO DE CUOTAS DE GASTOS COMUNES" |
| Datos propietario | Nombre, DNI, email, código servicio, predio | Nombre, DNI |
| Fechas | Emisión, vencimiento, % cobro, % participación | Emisión, vencimiento, ref interna |
| **Conceptos** | **Desglosa**: Admin, Agua Común, Electricidad (2), Mantenimiento | Simplificado: solo Cuota + Agua |
| **Lectura agua** | Tabla con lectura anterior, actual, consumo, factor, monto | Similar pero sin "factor" |
| **Gráfico** | "Historial de Consumo de Agua mt3" (barras) | No tiene |
| **Cuentas recaudadoras** | BCP Cuenta Corriente + CCI | No tiene |
| Sub-totales | Sub-total Otros Cargos, Sub-total Agua, Sub-total Mantenimiento | No tiene |
| Info pago | Cuentas bancarias | 7 boxes (saldo inicial, abonos, etc.) |

---

## Datos del sistema disponibles

### MonthlyBills (presupuesto mensual)
```php
$monthlyBill->monthly_budget           // Presupuesto base
$monthlyBill->total_maintenance_budget // Total a distribuir
$monthlyBill->water_price_per_m3       // Precio m³ agua
$monthlyBill->common_water_consumption_m3 // Consumo áreas comunes
$monthlyBill->expenses()               // Relación: gastos del mes
```

### Expenses (gastos)
```php
$expense->provider_id                  // Proveedor
$expense->service_category_id          // Categoría (eléctrico, admin, etc.)
$expense->invoice_number               // Nro factura
$expense->amount                       // Monto
$expense->expense_type                 // 1=ordinario, 2=extraordinario
$expense->description                  // Descripción
```

### WaterReading (lectura de agua)
```php
$waterReading->previous_reading        // Lectura anterior
$waterReading->current_reading         // Lectura actual
$waterReading->m3_price                // Precio por m³
$waterReading->amount                  // Monto total
$waterReading->is_common               // ¿Es área común?
```

### Quota (cuota por departamento)
```php
$quota->maintenance_amount             // Cuota mantenimiento
$quota->water_amount                   // Cuota agua
$quota->amount                         // Total
$quota->month                          // Mes
$quota->due_date                       // Fecha vencimiento
```

### Departament
```php
$departament->number                   // Número (ej: 407)
$departament->area                     // Área m²
$departament->participation_percentage // % participación
$departament->type_label               // "Departamento", "Estacionamiento", etc.
```

---

## Preguntas pendientes

1. **Datos bancarios**: ¿Hardcoded en el template o en `config/app.php`?
2. **Categorías de gastos**: ¿Cómo se distingue "Servicios Eléctricos" de "Servicios de Administración"? Verificar `service_category_id` o ` Expense::CATEGORIES`.
3. **Email con PDF adjunto**: ¿Reemplazar el email actual (solo HTML + link) o crear nueva ruta/método?
4. **Gráfico de consumo**: ¿Implementar con CSS puro (barras) o omitir?

---

## Próximos pasos

1. Verificar estructura de `service_categories` y `expenses` en la BD
2. Crear template `resources/views/bills/receipt.blade.php` estilo 407-1
3. Agregar método `buildReceiptData()` en `BillInvoiceService`
4. Agregar endpoint `downloadReceipt` en `BillInvoiceController`
5. Modificar `BillInvoiceMail` para adjuntar PDF
6. Probar con datos reales del depto 407
