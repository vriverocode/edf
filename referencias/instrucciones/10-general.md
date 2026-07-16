# Instrucciones — General (#41, #42)

**Archivos principales involucrados:**
- `frontend/src/resources/layouts/panelLayout.vue`
- `frontend/src/resources/view/admin/Users/usersList.vue`
- `frontend/src/resources/view/admin/Department/departmentList.vue`
- Múltiples archivos de vista (transversal)

---

### #41 — Inconsistencia visual entre pantallas

**Prioridad:** 🟠 Media
**Tipo:** Mejora UX

**Problema:**
No hay un patrón consistente de navegación:
- Algunas pantallas tienen botón "Volver" y otras no
- Algunas acciones abren un modal y otras navegan a una página completa nueva
- El usuario tiene que re-aprender la navegación en cada sección

**Causa raíz:**
El desarrollo se ha hecho sin un patrón de navegación definido. Cada vista implementa su propio comportamiento:
- `panelLayout.vue` tiene un botón "Volver" automático (flecha) que se muestra cuando `depth > 0`, pero muchas vistas también agregan su propio botón Volver manual
- Algunas acciones usan `router.push()` (navegación completa), otras usan modales
- Las rutas tienen diferentes `depth` que controlan la visibilidad del botón Volver del layout

**Solución:**

**Esto es un refactor transversal. Se recomienda:**

**Paso 1:** Definir un estándar de navegación:
   - **Acciones de edición/creación → Modal** (cuando el formulario es simple, < 5 campos)
   - **Acciones de edición/creación → Página completa** (cuando el formulario es complejo, con múltiples pasos o carga de datos externos)
   - **Acciones de vista/detalle → Página completa**

**Paso 2:** Unificar el botón "Volver":
   - El botón Volver del `panelLayout.vue` debe ser suficiente. No agregar botones Volver manuales en las vistas hijas.
   - Si una vista necesita su propio botón Volver, usar siempre `router.back()` o `router.push()` con la ruta padre.

**Paso 3:** Casos específicos a corregir:
   - `updateDepartment.vue` → convertir a modal (desde `departmentList.vue`) o mantener como página pero mejorar el retorno (ver #23)
   - `createUser.vue` → mantener como página (formulario complejo de 2 pasos)
   - `expenseForm.vue` → mantener como página (formulario complejo)
   - `waterReadingForm.vue` → mantener como página (necesita navegación secuencial)
   - `bookingsList.vue` → los botones de acción (validar pago, cancelar) deberían abrir modales, no navegar a nuevas páginas

**Paso 4:** Verificar `depth` en todas las rutas del router:
   - Asegurar que `depth` es correcto para que el botón Volver del layout se muestre/oculte adecuadamente
   - El layout muestra el botón Volver cuando `depth > 0` y la ruta actual no está en `['dashboardAdmin', 'financePage', 'usersAdmin', 'payConfirm', 'reserveConfirm']`

**Archivos a modificar (transversal):**
- Múltiples archivos de vista (sin lista exhaustiva — aplicar estándar progresivamente)
- `frontend/src/resources/routes/index.js` — verificar depths

**Validación:**
1. Navegar por toda la app de admin
2. Verificar que el comportamiento de Volver es predecible
3. Verificar que acciones similares usan el mismo patrón (modal vs página)
4. No debe haber botones Volver duplicados

---

### #42 — La fricción general empuja al admin hacia Excel

**Prioridad:** 🔴 Alta
**Tipo:** Observación / Riesgo de adopción

**Problema:**
La suma de todos los problemas anteriores hace que en la práctica sea más rápido y confiable usar Excel que el sistema para gestionar un edificio real.

**Esto NO es un bug individual** sino la consecuencia de todos los demás hallazgos. Se resuelve implementando las correcciones de los hallazgos #1 al #41.

**Factores contribuyentes (cada uno tiene su propia instrucción):**
1. Formularios sin indicación de campos obligatorios → #13
2. Listas largas sin buscador ni paginación → #11, #12, #20, #21, #22, #29
3. Falta de reportes financieros → #24, #36, #37, #38, #39, #40
4. Navegación inconsistente → #23, #41
5. Bugs que bloquean funciones centrales → #16, #35, #25
6. Flujos financieros incompletos → #6, #7, #27

**Solución:**

**No hay una solución única.** La solución es ejecutar el plan completo de correcciones de todos los hallazgos en este documento.

**Recomendaciones adicionales:**

**Paso 1:** Después de implementar todas las correcciones, hacer una sesión de pruebas con un admin real (no el desarrollador) para identificar puntos de fricción restantes.

**Paso 2:** Considerar un rediseño de la sección de Finanzas como proyecto separado:
   - Dashboard financiero con indicadores clave (#40)
   - Reportes exportables a Excel/PDF (#37, #38)
   - Alertas automáticas (#39)
   - Presupuesto anual (#36)

**Paso 3:** Priorizar la usabilidad sobre la cantidad de features:
   - Es mejor tener pocas funciones que funcionan bien que muchas funciones que frustran
   - Considerar eliminar funcionalidades placeholder (#24) hasta que estén realmente implementadas

**Validación:**
1. Después de implementar todas las correcciones, un admin debería poder:
   - Gestionar usuarios (crear, editar, buscar) sin fricción
   - Registrar lecturas de agua de 180 unidades en menos de 30 minutos
   - Ver el estado financiero del edificio en una sola pantalla
   - Generar reportes de gastos y morosos
   - Conciliar pagos y devoluciones
2. El admin no debería necesitar abrir Excel para ninguna tarea de gestión diaria

---

## Fin del plan de instrucciones

Este es el último archivo de instrucciones. Una vez implementados todos los hallazgos (#1 al #42), el sistema debería estar en condiciones de ser usado por un administrador real sin recurrir a herramientas externas.

**Próximo paso:** Empezar con la implementación siguiendo el orden establecido en `00-plan-general.md`, comenzando por los bugs críticos de la Fase 1.
