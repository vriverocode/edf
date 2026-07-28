# Pendientes — Pacifik App v2

> Consolidado actualizado con todas las tareas pendientes, renumeradas y priorizadas.
> Incluye: pendientes del proyecto original + hallazgos de auditoría del código.
> **Fecha:** 2026-07-28

---

## Leyenda de progreso

| Estado | Significado |
|--------|-------------|
| 🔧 Ya hay código | Existe parte del trabajo, solo falta completar o ajustar |
| 🔨 Iniciado parcial | Hay base pero falta lógica backend/frontend |
| 🔨 Todo por hacer | No hay nada implementado, hay que crear desde cero |

---

## 🔴 Prioridad Alta (7 ítems)

### #1 — Fix ruta duplicada en routes/index.js
- **Estado:** ✅ Completado
- **Archivo:** `frontend/src/resources/routes/index.js:1121-1136`
- **Problema:** Objeto con `path` y `component` repetidos — `/security/airbnb/list` queda pisado por la segunda definición
- **Acción:** Separar en dos objetos de ruta independientes

### #2 — Comprobante adjunto en listado de gastos
- **Estado:** ✅ Completado
- **Backend:** Incluir campo `attachment_url` en respuesta de `GET /api/expenses`
- **Archivo:** `app/Http/Controllers/Api/ExpenseController.php`
- **Nota:** El campo probablemente ya existe en el modelo, solo falta incluirlo en el map de la respuesta

### #3 — Filtros rápidos en listado de gastos
- **Estado:** ✅ Completado
- **Backend:** `GET /api/expenses` debe aceptar `provider_id`, `category_id`, `date_from`, `date_to` como query params
- **Frontend:** El componente `expenseFilterModal.vue` ya fue creado
- **Nota:** Verificar si el backend ya soporta estos filtros (el store y componente frontend ya existen)

### #4 — UserController.php: método vacío con TODO
- **Estado:** 🔨 Todo por hacer
- **Archivo:** `app/Http/Controllers/Api/UserController.php:474-477`
- **Método:** `setAvailableComunaAreaToReserve()` — completamente vacío
- **Acción:** Implementar lógica para habilitar áreas comunes a reservar para el usuario

### #5 — QuotaController.php: métodos vacíos
- **Estado:** ✅ Completado (absorbido por #8)
- **Archivo:** `app/Http/Controllers/Api/QuotaController.php`
- **Métodos vacíos:** `store()` (línea 181), `update()` (línea 279), `destroy()` (línea 287)
- **Acción:** Implementar CRUD completo de cuotas de mantenimiento

### #6 — Doble reserva de área común sin validación ++
- **Estado:** ✅ Completado
- **Bug documentado:** `pacifik-bugs-usabilidad.md`
- **Problema:** No valida si ya existe una reserva en el mismo área/fecha/horario
- **Acción:** Agregar validación en backend antes de crear reserva, y mensaje de error claro en frontend

### #7 — console.log en producción (stores + vistas)
- **Estado:** ✅ Completado
- **Alcance:** 25 stores + 30+ vistas con `console.log(response)` en catch blocks
- **Problema:** Filtra datos de API sensibles en consola del navegador en producción
- **Acción:** Reemplazar `console.log(response)` por `console.error` o silenciar en catch; eliminar logs de debug como `console.log('por aqui no son los pagados')` en `createReserve.vue:339`

---

## 🟠 Prioridad Media (6 ítems)

### #8 — Cuotas de mantenimiento
- **Estado:** ✅ Completado
- **Problema:** No se pudo validar por falta de registros reales en BD
- **Acción:** Implementados store(), update(), destroy() y generate() en QuotaController con rutas POST/PUT/DELETE

### #9 — Flujo diferenciado reservas de pago
- **Estado:** ✅ Completado
- **Módulo:** Reservas
- **Acción:** Validación de cuenta bancaria al crear reserva con pago (modal si no tiene cuentas registradas)

### #10 — Turnos horarios en áreas comunes
- **Estado:** 🔨 Todo por hacer
- **Backend:** Nuevo campo `time_slots` (JSON array) en tabla `comun_areas` + modificar `ComunAreaController`
- **Frontend:** Componente de bloques horarios en `createComunArea.vue` y `updateComunArea.vue`
- **Nota:** Requiere migración de BD

### #11 — Mantenimiento debe bloquear reservas
- **Estado:** ✅ Ya estaba implementado
- **Backend:** Al crear/modificar mantenimiento, busca reservas existentes en misma área/fecha/horario, las cancela automáticamente y notifica (MaintenanceController.php:88-105)
- **Dependencia:** No requiere #10 (ya funciona con time_from/time_to existentes)

### #12 — Inconsistencia visual Volver / modal
- **Estado:** 🔨 Todo por hacer
- **Módulo:** General UX
- **Problema:** A veces botón "Volver", a veces no; a veces modal, a veces página nueva
- **Acción:** Unificar patrón de navegación en toda la app

### #13 — Fricción general reduce adopción
- **Estado:** 🔨 Todo por hacer
- **Módulo:** General UX
- **Problema:** El admin prefiere Excel por exceso de clicks/pasos innecesarios
- **Acción:** Identificar y reducir fricción en flujos más usados

---

## ⚪ Prioridad Baja (4 ítems)

### #14 — Dead code: función noDisponible()
- **Estado:** ✅ Completado
- **Archivo:** `frontend/src/resources/view/client/Visits/visitsList.vue:124-126`
- **Problema:** Función nunca llamada, remanente de botón de editar eliminado
- **Acción:** Eliminar función + comentario `<!-- Edit removed -->`

### #15 — Menú "Descarga pase" comentado
- **Estado:** ✅ Completado
- **Archivo:** `frontend/src/resources/view/client/Reserves/reserveList.vue:304-306`
- **Problema:** Item de menú comentado, posiblemente para implementar después
- **Acción:** Decidir si implementar o eliminar completamente

### #16 — downloadReceipt() stubs sin implementar
- **Estado:** ✅ Completado
- **Archivos:** `confirmReserve.vue:34`, `viewReserve.vue:37`, `viewQuota.vue:37`, `payFinish.vue:36`
- **Problema:** 4 funciones con solo `console.log`, botones comentados en templates
- **Acción:** Implementar lógica de descarga de recibo o eliminar la funcionalidad

### #17 — Noticias segmentadas
- **Estado:** Postergado
- **Módulo:** Noticias
- **Problema:** Segmentar noticias por departamento, torre o piso
- **Motivo:** Postergado por decisión del usuario

---

## Resumen por estado

| Estado | Cantidad | Ítems |
|--------|----------|-------|
| ✅ Completado | 11 | #1, #2, #3, #5, #6, #7, #8, #9, #14, #15, #16 |
| ✅ Ya estaba implementado | 1 | #11 |
| 🔨 Todo por hacer | 4 | #4, #10, #12, #13 |
| Postergado | 1 | #17 |

## Sugerencia de orden de ejecución

1. ~~**Sprint 1 (quick wins):** #1, #2, #3 — Bugs críticos y mejoras con código existente~~ ✅
2. ~~**Sprint 2 (limpieza):** #7, #14, #15, #16 — Reducir deuda técnica~~ ✅
3. ~~**Sprint 3 (funcionalidad):** #4, #5, #6 — Backend completo de cuotas y validación~~ ✅ (parcial: #4 pendiente)
4. ~~**Sprint 4 (áreas comunes):** #10, #11 — Turnos horarios + bloqueo mantenimiento~~ ✅ (#11 ya implementado)
5. ~~**Sprint 5 (UX):** #8, #9, #12, #13 — Validación data real, reservas pago, flujo Volver~~ ✅ (parcial: #12-#13 pendientes)
6. **Pendientes:** #4, #10, #12, #13, #17
