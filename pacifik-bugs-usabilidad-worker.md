# Bugs y mejoras de usabilidad — Frontend Pacifik (rol Worker)

**Fecha:** 2026-07-14
**Alcance:** `https://web.edificiopacifik.com/` (vista del personal supervisor del edificio — rol "worker")
**Fuente:** pruebas manuales del usuario navegando la app con una cuenta de tipo worker.
**Nota:** este documento es de UX/funcionalidad para el rol worker, no de seguridad — para hallazgos de seguridad ver `pacifik-vulnerabilidades.md`. Para los hallazgos del rol propietario/residente ver `pacifik-bugs-usabilidad.md`.

**Contexto del rol:** el worker es personal operativo del edificio (portería/administración), no el propietario. Necesita información operativa rápida (quién llega hoy, qué unidad, si puede autorizar algo) y no necesariamente los mismos datos o el mismo nivel de detalle que ve un propietario sobre su propia cuenta.

---

## Índice

| # | Módulo | Tipo | Prioridad | Resumen |
|---|--------|------|-----------|---------|
| 1 | Reservas | Mejora UX | 🟠 Media | El listado no muestra el número de departamento que hizo la reserva |
| 2 | Reservas | Bug funcional | 🔴 Alta | Crash al ver el detalle de una reserva: "Error al cargar la reserva" |
| 3 | Reservas | Mejora UX | 🟡 Baja | Ver detalle requiere entrar al menú de 3 puntos en vez de click directo |
| 4 | Reservas | Mejora UX | 🟠 Media | Filtros escondidos tras el ícono de embudo, no hay filtros rápidos visibles |
| 5 | Reservas | Mejora UX / dato interno | 🟠 Media | Aparecen reservas canceladas, que son un dato interno del propietario |
| 6 | Reservas | Mejora UX | 🟠 Media | No hay agrupación por área; el orden es por ID interno, sin lógica cronológica |
| 7 | Reservas | Mejora UX | 🟠 Media | No hay vista por defecto de "reservas de hoy"; se listan todas de una |
| 8 | Reservas | Bug funcional / performance | 🟠 Media | No hay paginación: se cargan todas las reservas de golpe |
| 9 | Reservas | Regla de negocio | 🔴 Alta | El worker puede "Cancelar por mantenimiento" una reserva manualmente — debería ser una regla automática del sistema |
| 10 | Departamentos | Mejora UX | 🟡 Baja | Listado de departamentos sin un orden claro |
| 11 | Departamentos | Mejora UX | 🟠 Media | Paginado sin indicar total de departamentos ni total de páginas |
| 12 | Departamentos | Feature faltante | 🟠 Media | No hay filtros para buscar por departamento, propietario, DNI o placa |
| 13 | Departamentos | Mejora UX | 🟡 Baja | Lista de residentes del departamento muestra muy poco dato (solo nombre, teléfono, DNI) |
| 14 | Departamentos | Recomendación (sin verificar) | 🟡 Baja | Ocupantes temporales (inquilino/airbnb) deberían filtrarse por vigencia de su estadía |
| 15 | Airbnb | Bug funcional | 🟠 Media | Error al cargar la foto del huésped en "Ver huéspedes" |
| 16 | Airbnb | Mejora UX | 🟠 Media | Filtros escondidos en el embudo, deberían ser visibles desde el listado |
| 17 | Airbnb | Mejora UX / regla de negocio | 🟠 Media | Filtro por "cancelado"/"completado" no es útil al worker; falta filtro por check-out hecho / en curso |
| 18 | Airbnb | Mejora UX | 🟠 Media | El listado muestra airbnbs futuros; debería priorizar los que están en curso o llegan hoy/mañana |
| 19 | Airbnb | Feature faltante | 🟠 Media | No hay forma de ver las visitas asociadas a un airbnb (solo se ven los huéspedes principales) |
| 20 | Visitas | Mejora UX | 🟠 Media | El listado muestra visitas futuras; el worker debería ver primero solo las de hoy |
| 21 | Visitas | Feature faltante | 🟠 Media | Al marcar una visita como "llegada" no se muestra la hora de llegada |
| 22 | Visitas | Bug funcional | 🟠 Media | El botón "Marcar llegado" sigue habilitado después de usarlo |
| 23 | Visitas | Bug funcional (validación) | 🔴 Alta | El sistema permite marcar como "llegado" una visita programada a futuro |
| 24 | Visitas | Mejora UX | 🟡 Baja | Aparecen visitas pasadas mezcladas en el listado |
| 25 | Visitas | Feature faltante | 🟠 Media | No se muestra el detalle registrado de la visita (tipo, motivo, etc.) en la vista del worker |
| 26 | Visitas | Mejora UX | 🟠 Media | Filtros escondidos en el embudo (mismo patrón repetido en Reservas y Airbnb) |

---

## Reservas

### 1. El listado no muestra el número de departamento que hizo la reserva
**Tipo:** mejora UX · **Prioridad:** 🟠 Media
En la lista de reservas de propietarios, no aparece a qué departamento pertenece cada reserva. Para el worker esto es un dato operativo básico (a diferencia del propietario, que ya sabe cuál es su unidad) — sin él, identificar de quién es la reserva requiere entrar al detalle uno por uno.

### 2. Crash al ver el detalle de una reserva
**Tipo:** bug funcional (crítico de estabilidad) · **Prioridad:** 🔴 Alta
Al hacer click en "ver detalles" de una reserva aparece: `¡Ups! Algo salió mal — Error al cargar la reserva`. Esto bloquea por completo la única forma que tiene el worker de ver la información de la reserva.

### 3. Ver el detalle requiere entrar al menú de 3 puntos
**Tipo:** mejora UX · **Prioridad:** 🟡 Baja
Actualmente hay que abrir el botón de acción (3 puntos) y luego elegir "Ver detalles". Debería bastar con hacer click directo sobre la fila/tarjeta de la reserva para ver el detalle; dejar el menú de 3 puntos solo para acciones secundarias. (Mismo patrón detectado en el módulo de Eventos del rol propietario, ver hallazgo #10 de `pacifik-bugs-usabilidad.md`.)

### 4. Los filtros están escondidos tras el ícono de embudo
**Tipo:** mejora UX · **Prioridad:** 🟠 Media
Hay que hacer click en el embudo cada vez para que aparezcan las opciones de filtro. Al ser una pantalla de uso operativo diario, debería tener filtros rápidos visibles directamente en el listado (por ejemplo, chips o pestañas), sin el paso extra de abrir el embudo.

### 5. Aparecen reservas canceladas, que son un dato interno del propietario
**Tipo:** mejora UX / alcance de información · **Prioridad:** 🟠 Media
El listado del worker incluye reservas canceladas. Esa información es interna del propietario (si canceló o no una reserva) y no aporta nada operativo al worker — debería excluirse del listado por defecto.

### 6. No hay agrupación por área; el orden no tiene lógica cronológica
**Tipo:** mejora UX · **Prioridad:** 🟠 Media
Las reservas se listan ordenadas por un identificador interno, no por área de reserva ni por fecha/hora. Debería poder agruparse por área común (o al menos ordenarse cronológicamente) para que el worker entienda de un vistazo qué áreas están reservadas y cuándo.

### 7. No hay una vista por defecto de "reservas de hoy"
**Tipo:** mejora UX · **Prioridad:** 🟠 Media
El listado carga todas las reservas de una vez, sin priorizar las del día actual. La vista inicial debería mostrar las reservas de hoy (que es lo que el worker necesita gestionar en el momento), dejando la posibilidad de expandir la búsqueda a otras fechas como una acción explícita.

### 8. No hay paginación: se cargan todas las reservas de golpe
**Tipo:** bug funcional / performance · **Prioridad:** 🟠 Media
El listado trae todas las reservas existentes sin límite ni paginación, lo cual va a degradarse a medida que se acumulen más reservas (ver también el hallazgo #3 de `pacifik-bugs-usabilidad.md`, sobre reservas que nunca se archivan).

### 9. El worker puede "Cancelar por mantenimiento" una reserva manualmente
**Tipo:** regla de negocio · **Prioridad:** 🔴 Alta
En el menú de 3 puntos de cada reserva existe la opción "Cancelar por mantenimiento". Esto no debería depender de una decisión manual del worker: si un propietario tiene meses de mantenimiento impagos, la regla de negocio debería **impedir automáticamente** que ese propietario cree una reserva desde el origen (bloqueo al momento de reservar), en vez de dejar que el worker decida caso por caso si cancela una reserva ya creada. Delegar esto a una acción manual introduce inconsistencia (depende de que el worker se acuerde de revisarlo) y le da al worker una responsabilidad que es del sistema.

---

## Departamentos

### 10. Listado de departamentos sin un orden claro
**Tipo:** mejora UX · **Prioridad:** 🟡 Baja
Los departamentos no aparecen ordenados de forma predecible (ni numérico, ni alfabético). Ordenar por número/torre facilitaría ubicar una unidad específica a simple vista.

### 11. Paginado sin indicar total de departamentos ni total de páginas
**Tipo:** mejora UX · **Prioridad:** 🟠 Media
El listado está paginado pero no muestra cuántos departamentos hay en total ni cuántas páginas existen, dificultando saber si se ha revisado todo el listado o cuánto falta.

### 12. No hay filtros para buscar por departamento, propietario, DNI o placa
**Tipo:** feature faltante · **Prioridad:** 🟠 Media
No existe una barra de búsqueda ni filtros para ubicar rápidamente un departamento específico, un propietario por nombre, un residente por DNI, o un vehículo por placa. Dado que este es un flujo de consulta frecuente para el worker (por ejemplo, para verificar un ingreso), la ausencia de búsqueda obliga a recorrer el listado completo.

### 13. La lista de residentes del departamento muestra muy poco dato
**Tipo:** mejora UX · **Prioridad:** 🟡 Baja
Al entrar al detalle de un departamento y ver "residentes", solo aparece nombre, teléfono y DNI. Dependiendo de qué necesite validar el worker en portería, podría faltar contexto adicional (p. ej. si es propietario, familiar o inquilino, o si está vigente).

### 14. Recomendación: filtrar ocupantes temporales por vigencia de su estadía
**Tipo:** recomendación (no verificada) · **Prioridad:** 🟡 Baja
No se pudo probar directamente, pero queda como recomendación: para departamentos con ocupante temporal (inquilino o huésped de Airbnb), el sistema debería dejar de mostrarlos automáticamente una vez que su periodo de ocupación/estadía terminó, para que el worker no vea como "residente actual" a alguien que ya se fue.

---

## Airbnb

### 15. Error al cargar la foto del huésped en "Ver huéspedes"
**Tipo:** bug funcional · **Prioridad:** 🟠 Media
El listado principal de Airbnb muestra el usuario principal de la reserva, su estadía, el número de departamento y la cantidad de huéspedes. Al hacer click en "Ver huéspedes" para ver el detalle, la foto del huésped falla al cargar (error). Revisar si el problema es de subida, de la URL generada, o de permisos de acceso al archivo (mismo tipo de síntoma que el hallazgo #15 de `pacifik-bugs-usabilidad.md`, sobre imágenes de incidencias que tampoco cargan).

### 16. Filtros escondidos en el embudo, deberían ser visibles desde el listado
**Tipo:** mejora UX · **Prioridad:** 🟠 Media
Mismo problema que en Reservas (hallazgo #4): hay que abrir el embudo para acceder a los filtros. Deberían estar visibles directamente en el listado.

### 17. El filtro por "cancelado"/"completado" no es útil para el worker
**Tipo:** mejora UX / regla de negocio · **Prioridad:** 🟠 Media
Los filtros actuales de estado (cancelado, completado) responden más a la lógica de gestión de la reserva de Airbnb en sí, no a lo que necesita operar el worker. Al worker le interesa saber qué departamentos **ya hicieron check-out** y cuáles **están en curso** — ese debería ser el filtro/estado principal expuesto, no el estado de la reserva de Airbnb.

### 18. El listado muestra airbnbs futuros mezclados con los actuales
**Tipo:** mejora UX · **Prioridad:** 🟠 Media
Aparecen reservas de Airbnb que todavía no empiezan, muy adelantadas en el tiempo. La vista principal del worker debería limitarse a las que están en curso o llegan hoy/mañana como máximo; las reservas más lejanas en el futuro deberían quedar disponibles solo a través de un filtro explícito, no en la vista por defecto.

### 19. No hay forma de ver las visitas asociadas a un airbnb
**Tipo:** feature faltante · **Prioridad:** 🟠 Media
Un Airbnb puede tener huéspedes principales y además visitas de esos huéspedes, pero actualmente no existe una vista donde el worker pueda consultar las visitas asociadas a una estadía de Airbnb — solo se ven los huéspedes principales.

---

## Visitas

### 20. El listado muestra visitas futuras; debería priorizar las de hoy
**Tipo:** mejora UX · **Prioridad:** 🟠 Media
El listado general de visitas muestra visitas programadas a futuro. El worker necesita, de primera mano, únicamente las visitas de hoy (que son las que tiene que gestionar en portería); las visitas de otras fechas deberían quedar detrás de un filtro o vista secundaria.

### 21. Al marcar una visita como "llegada" no se muestra la hora de llegada
**Tipo:** feature faltante · **Prioridad:** 🟠 Media
Después de marcar que un visitante llegó, el sistema no muestra en qué hora se registró esa llegada — un dato básico de control de acceso que debería quedar visible en el listado o en el detalle de la visita.

### 22. El botón "Marcar llegado" sigue habilitado después de usarlo
**Tipo:** bug funcional · **Prioridad:** 🟠 Media
Tras marcar una visita como llegada, el botón "Marcar llegado" continúa disponible y se puede volver a presionar. Debería deshabilitarse o cambiar de estado (por ejemplo a "Llegado ✓") una vez usado, para evitar registros duplicados o inconsistentes.

### 23. El sistema permite marcar como "llegado" una visita programada a futuro
**Tipo:** bug funcional (validación) · **Prioridad:** 🔴 Alta
No debería ser posible marcar como "llegado" a un visitante cuya visita está programada para una fecha futura — solo debería habilitarse esta acción para visitas del día de hoy. Permitirlo rompe la confiabilidad del registro de control de acceso.

### 24. Aparecen visitas pasadas mezcladas en el listado
**Tipo:** mejora UX · **Prioridad:** 🟡 Baja
El listado incluye visitas de fechas ya pasadas junto con las vigentes, sin separación. Debería archivarse/separarse el historial de visitas pasadas de la vista operativa activa (mismo patrón de fondo que el hallazgo #3 de `pacifik-bugs-usabilidad.md`, sobre reservas que nunca se archivan).

### 25. No se muestra el detalle registrado de la visita en la vista del worker
**Tipo:** feature faltante · **Prioridad:** 🟠 Media
Al registrar una visita se le pide al residente llenar tipo de visita, motivo, etc., pero esos datos no aparecen en ningún lado de la vista del worker. Sin ese contexto, el worker recibe al visitante sin saber a qué viene ni qué se esperaba — el detalle de la visita debería mostrarse en el listado o al menos en el detalle de cada visita.

### 26. Filtros escondidos en el embudo
**Tipo:** mejora UX · **Prioridad:** 🟠 Media
Mismo patrón repetido de Reservas (#4) y Airbnb (#16): los filtros de Visitas también están escondidos tras el ícono de embudo en vez de estar disponibles como filtros rápidos en el listado.

---

## Resumen para priorización

**Bugs/reglas de negocio que rompen algo (arreglar primero):**
- #2 (crash al ver detalle de una reserva)
- #9 (cancelación por mantenimiento debería ser regla automática, no decisión manual del worker)
- #23 (permite marcar como "llegado" una visita futura)
- #15 (foto del huésped de Airbnb no carga)
- #22 (botón "Marcar llegado" no se deshabilita tras usarse)

**Feature faltante (funcionalidad operativa que falta):**
- #12 (buscador/filtros en Departamentos por depto, propietario, DNI, placa)
- #19 (ver visitas asociadas a un Airbnb)
- #21 (hora de llegada no se registra/muestra)
- #25 (detalle de la visita — tipo, motivo — no visible para el worker)

**Mejoras de experiencia repetidas en varios módulos (agrupar en un mismo sprint, es el mismo patrón de fondo):**
- Filtros escondidos tras el embudo → #4, #16, #26
- Falta de vista "de hoy" por defecto / mezcla de pasado-presente-futuro → #7, #18, #20, #24
- Falta de paginación real / totales → #8, #11
- Click directo al detalle en vez de menú de 3 puntos → #3
- Datos operativos faltantes en el listado (departamento en reservas, orden de departamentos, detalle de residentes) → #1, #5, #6, #10, #13, #17

**Recomendación pendiente de validar con el negocio:**
- #14 (filtrar ocupantes temporales por vigencia de estadía — no se pudo probar directamente)
