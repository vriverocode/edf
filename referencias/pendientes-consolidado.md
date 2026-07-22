# Pendientes — Pacifik App

> Consolidado de items remanentes luego de completar bugs, mejoras UX y CRUDs base.
> Reemplaza a `pacifik-bugs-usabilidad-admin.md` y `pendientes-backend.md`.

---

## 🔴 Alta prioridad

### #4 — Turnos horarios en áreas comunes
- **Backend:** Agregar columna `time_slots` (JSON) a tabla `comun_areas`; modificar `ComunAreaController@store` y `@update` para aceptar/retornar bloques (ej. `{"day":1,"start":"06:00","end":"08:00"}`)
- **Frontend:** Componente de bloques horarios en `createComunArea.vue` y `updateComunArea.vue`

### #5 — Mantenimiento debe bloquear reservas
- **Backend:** Al crear/modificar un mantenimiento, buscar reservas existentes en misma área/fecha/horario, cancelarlas automáticamente, notificar a usuarios afectados
- **Relacionado con:** #4 (depende de tener turnos horarios)

### #6 — Flujo diferenciado para reservas de pago
- **Backend/Frontend:** Vista que dé énfasis a reservas que requieren conciliación de pago pendiente; flujo de confirmación de pago admin
- **Referencia:** `02-reservas.md`

### #33 — Comprobante adjunto no visible en listado de gastos
- **Backend:** Incluir campo `attachment_url` en respuesta de `GET /api/expenses`
- **Archivo:** `app/Http/Controllers/Api/ExpenseController.php`

### #34 — Filtros rápidos en listado de gastos
- **Backend:** `GET /api/expenses` debe aceptar `provider_id`, `category_id`, `date_from`, `date_to` como query params adicionales
- **Frontend:** Agregar filtros correspondientes en `expensesList.vue`

---

## 🟠 Media prioridad

### #41 — Inconsistencia visual (Volver / modal vs página nueva)
- **Frontend:** Unificar patrón de navegación: a veces hay botón "Volver", a veces no; a veces modal, a veces página nueva
- **Referencia:** `10-general.md`

### #42 — Fricción general lleva al admin a preferir Excel
- **Frontend:** Riesgo de adopción. Identificar y reducir pasos/click innecesarios en los flujos más usados
- **Referencia:** `10-general.md`

### #30 — Cuotas de mantenimiento
- **Estado:** Pendiente de validar con data real. No se pudo evaluar por falta de registros en BD
- **Referencia:** `09-finanzas-cuotas.md`

---

## ⚪ Obviado / Postergado

### #9 — Noticias segmentadas
- **Descripción:** Segmentar noticias por departamento, torre o piso
- **Motivo:** Postergado por decisión del usuario

---

## ✅ Resumen de lo completado (referencia)

| Ítems | Módulo |
|-------|--------|
| #1, #2, #3 | Áreas comunes |
| #7, #8 | Reservas |
| #10 | Reportes |
| #11, #12, #13, #14, #15, #16 | Usuarios |
| #17, #18, #19, #20, #21, #22, #23 | Departamentos / Unidades |
| #24, #25, #26, #27, #28, #29, #35, #36, #37, #38, #39, #40 | Finanzas |
| CRUD Proveedores | Backend + Frontend |
| CRUD Categorías de servicio | Backend + Frontend |
| POST /api/pays/refund | Devolución de pagos |
| include_cancelled en reportes | Backend |
