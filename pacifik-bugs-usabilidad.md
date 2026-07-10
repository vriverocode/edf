# Bugs y mejoras de usabilidad — Frontend Pacifik

**Fecha:** 2026-07-09
**Alcance:** `https://web.edificiopacifik.com/` (app cliente/residente)
**Fuente:** pruebas manuales del usuario navegando la app con una cuenta con más de un departamento.
**Nota:** este documento es de UX/funcionalidad, no de seguridad — para hallazgos de seguridad ver `pacifik-vulnerabilidades.md`.

---

## Índice

| # | Módulo | Tipo | Prioridad | Resumen |
|---|--------|------|-----------|---------|
| 1 | Residentes | Regla de negocio | 🟠 Media | Un "inquilino" puede tener más de un departamento asignado |
| 2 | Reservas | Bug funcional | 🔴 Alta | No permite doble reserva del mismo área — **falta la validación** |
| 3 | Reservas | Bug funcional | 🟠 Media | Reservas pasadas y canceladas no se archivan — se acumulan y entierran las nuevas al final del listado |
| 4 | Reservas | Bug de datos | 🟠 Media | Fecha de la reserva sale un día antes (timezone) |
| 5 | Reservas | Mejora UX | 🟡 Baja | No se muestra a qué departamento pertenece la reserva |
| 6 | Reservas | Mejora UX | 🟡 Baja | Paso 3: falta botón "volver" explícito |
| 7 | Reservas | Mejora UX | 🟡 Baja | Paso 3: no preselecciona el bloque horario actual |
| 8 | Reservas | Mejora UX | 🟠 Media | Pantalla de confirmación pierde todo el contexto de la app |
| 9 | Eventos | Bug funcional | 🔴 Alta | Crash (`TypeError`) al ver detalle de un evento |
| 10 | Eventos | Mejora UX | 🟡 Baja | Hay que entrar a un submenú para ver detalle, no se puede click directo |
| 11 | Mi Unidad | Bug funcional | 🟠 Media | El piso (número de piso) del departamento sale vacío |
| 12 | Incidencias | Bug funcional | 🟠 Media | Permite reportar incidencias con fecha futura |
| 13 | Incidencias | Mejora UX | 🟡 Baja | Listado no muestra fecha de registro, solo fecha del incidente |
| 14 | Incidencias | Mejora UX | 🟡 Baja | Detalle muestra fecha de registro sin hora |
| 15 | Incidencias | Bug funcional | 🟠 Media | Las imágenes adjuntas no se visualizan |
| 16 | Residentes | Bug funcional | 🟠 Media | Selector de unidad al registrar familiar incluye estacionamientos |
| 17 | Residentes | Bug funcional | 🟠 Media | Permite fecha fin de alquiler menor a fecha inicio; error tardío |
| 18 | Residentes | Bug funcional | 🟡 Baja | Al eliminar un residente, el menú de acciones queda huérfano |
| 19 | Residentes | Mejora UX | 🟡 Baja | Listado ordenado ascendente — los nuevos quedan al final |
| 20 | Visitas | Bug funcional | 🟠 Media | Botón de editar visita no funciona ("no disponible") |
| 21 | Visitas | Bug funcional | 🟠 Media | Eliminar una visita la borra en vez de archivarla |
| 22 | Visitas | Bug de datos | 🟠 Media | Fecha de visita sale un día menos (mismo bug de timezone) |
| 23 | Visitas | Bug funcional | 🟠 Media | Permite registrar visita el mismo día con hora ya pasada |
| 24 | Finanzas | Feature faltante | 🟡 Baja | "Saldos pendientes" y "Gastos comunes" no implementados |
| 25 | Navegación | Mejora UX | 🟡 Baja | Footer (Inicio/Finanzas/Salir): el indicador de sección activa existe pero es demasiado sutil |

---

## Reservas

### 1. Un usuario "Inquilino" puede tener más de un departamento asignado
**Tipo:** regla de negocio a revisar · **Prioridad:** 🟠 Media
Detectado al explorar el sistema: tiene sentido que un "Propietario" tenga varias unidades, pero un rol de "Inquilino" normalmente debería estar limitado a la unidad que alquila. Vale la pena confirmar con el negocio si esto es intencional o si falta una restricción al asignar unidades según el rol.

### 2. No hay validación para evitar reservas duplicadas del mismo área
**Tipo:** bug funcional · **Prioridad:** 🔴 Alta
El sistema permite crear más de una reserva activa de la misma área común, tanto el mismo día como en días distintos, cuando ya existe una reserva activa. La regla esperada: si ya tienes una reserva activa (no cancelada, no completada) para un área, no deberías poder crear otra hasta que esa termine o la canceles.
**Nota:** esto se conecta con el hallazgo 11 del reporte de seguridad (el backend no valida bien la creación de reservas) — probablemente el mismo endpoint necesita esta regla agregada.

### 3. Las reservas pasadas no cambian de estado ni se archivan
**Tipo:** bug funcional · **Prioridad:** 🟠 Media
Reservas cuya fecha/hora ya pasó siguen apareciendo en el listado sin distinguirse de las activas. Deberían pasar automáticamente a un estado tipo "Completada" (vía job programado o al consultarlas) para no confundir al usuario sobre cuáles reservas siguen vigentes.

**Consecuencia adicional detectada al probar paginación (2026-07-09):** el listado se ordena por fecha de reserva de forma ascendente y global, sin separar pasadas de vigentes. Al crear varias reservas nuevas con fechas futuras, quedan **al final de una lista cada vez más larga de reservas históricas** — para ver la reserva recién creada hay que bajar hasta el fondo del listado. Esto empeora con el tiempo: cuantas más reservas pasadas se acumulen (porque nunca se archivan), más difícil es encontrar las próximas.

**Las reservas canceladas tampoco se ocultan ni se limpian del listado.** Se probó creando y cancelando 20 reservas de prueba (usadas para probar la paginación): las 20 quedaron con estado "Cancelada" pero siguen apareciendo en el listado igual que las demás — el usuario confirma que una reserva cancelada el día anterior seguía visible al día siguiente. Es el mismo problema de fondo que las reservas vencidas: nada se archiva, todo se acumula indefinidamente en una sola lista.

**Recomendación combinada:** además de marcar como "Completada" las reservas vencidas, separar la vista en pestañas o secciones — "Próximas"/"Activas" primero (idealmente ordenadas por fecha más cercana primero), y "Historial" aparte para lo que ya pasó **o** fue cancelado, en vez de una sola lista cronológica mezclada que crece sin límite.

### 4. La fecha de la reserva se muestra un día antes de la real
**Tipo:** bug de datos (timezone) · **Prioridad:** 🟠 Media
En el detalle de una reserva creada, la fecha mostrada es un día anterior a la que realmente se seleccionó. Muy probablemente la fecha se guarda/formatea en UTC y se muestra sin convertir a la zona horaria local (o viceversa). Revisar cómo se serializa `date` en el backend y cómo se formatea en el frontend (librería de fechas usada, ej. `dayjs`/`moment`, y su configuración de timezone).

### 5. No se muestra a qué departamento pertenece la reserva
**Tipo:** mejora UX · **Prioridad:** 🟡 Baja
Para un usuario con más de un departamento, ni la pantalla de confirmación ni el listado de "mis reservas" indican para cuál unidad se hizo la reserva. Debería mostrarse el número de departamento en ambos lugares.

### 6. Paso 3 (elegir hora): falta un botón "volver" explícito
**Tipo:** mejora UX · **Prioridad:** 🟡 Baja
Solo existe un botón "cambiar fecha" que, como efecto secundario, también actúa como "volver" al paso anterior. Es confuso — debería haber un botón de volver explícito, separado de la acción de cambiar la fecha.

### 7. Paso 3: el bloque horario no se preselecciona según la hora actual
**Tipo:** mejora UX · **Prioridad:** 🟡 Baja
Los 3 bloques (Mañana / Tarde / Noche) siempre muestran "Mañana" por defecto. Si ya es de tarde, "Mañana" ya no tiene horarios disponibles — debería preseleccionar el bloque correspondiente a la hora actual (o el primer bloque que todavía tenga horarios disponibles).

### 8. La pantalla de confirmación (`/client/reserves/confirm-reserve`) es pantalla completa sin contexto de la app
**Tipo:** mejora UX · **Prioridad:** 🟠 Media
Al llegar a la confirmación se pierde todo el contexto de la app (logo, menú de navegación); el único botón es "Volver al inicio". Debería mantenerse el layout normal de la app (o al menos un header simplificado con logo + botón atrás), no una pantalla aislada.

---

## Eventos

### 9. Crash al abrir el detalle de un evento
**Tipo:** bug funcional (crítico de estabilidad) · **Prioridad:** 🔴 Alta
Al hacer click para ver el detalle de un evento, la app muestra: `Ups! Algo salió mal — TypeError: Cannot read properties of null (reading 'includes')`. Es un error de JS sin manejar (probablemente un campo que llega `null` desde la API y el frontend llama `.includes()` sobre él sin verificar). Revisar el componente de detalle de evento y agregar un chequeo de nulidad antes de usar `.includes()` (o el equivalente) sobre ese campo.

### 10. Para ver el detalle de un evento hay que entrar a un submenú (3 puntos)
**Tipo:** mejora UX · **Prioridad:** 🟡 Baja
El listado de eventos no permite hacer click directo sobre el evento para ver su detalle — hay que abrir un menú de "3 puntos" y elegir la opción ahí. Debería poder hacerse click en la tarjeta/fila del evento directamente; dejar el menú de 3 puntos solo para acciones secundarias (editar, eliminar, etc., si aplica).

---

## Mi Unidad

### 11. El piso (número de piso) del departamento sale vacío
**Tipo:** bug de datos (no de frontend) · **Prioridad:** 🟠 Media
En "Mi unidad → Mi unidad", el campo que debería mostrar el número de piso del departamento aparece vacío. **Confirmado que no es un bug de renderizado**: la unidad `dpt-304` (una de las revisadas durante las pruebas) devuelve `"floor": null` directamente en la respuesta de la API — el dato simplemente no está cargado en la base de datos. Revisar el proceso de importación/carga de unidades para completar este campo faltante (es probable que afecte a más unidades, no solo a esta).

---

## Atención → Reportar incidencias

### 12. Permite reportar incidencias con fecha futura
**Tipo:** bug funcional (validación) · **Prioridad:** 🟠 Media
Al crear una incidencia, el selector de fecha permite elegir fechas futuras — no tiene sentido reportar un incidente que "va a pasar". Debería limitar el selector a fecha actual o anterior (`max = hoy`).

### 13. El listado de incidencias no muestra cuándo se reportó
**Tipo:** mejora UX · **Prioridad:** 🟡 Baja
Solo se muestra la fecha del incidente, no la fecha en la que se registró el reporte. Hay que entrar al detalle para verlo. Sería útil mostrar ambas fechas en el listado (o al menos la de registro, que es la que indica el orden real de atención).

### 14. El detalle de la incidencia muestra la fecha de registro sin hora
**Tipo:** mejora UX · **Prioridad:** 🟡 Baja
Solo se ve la fecha (día/mes/año), sin la hora en que se registró. Agregar la hora ayuda a rastrear mejor los tiempos de atención.

### 15. Las imágenes adjuntas a una incidencia no se visualizan
**Tipo:** bug funcional · **Prioridad:** 🟠 Media
Al adjuntar imágenes al reportar una incidencia, luego no se pueden ver (ni en el listado ni en el detalle). Revisar si el problema es de subida (no se guardan), de la URL que se genera para mostrarlas, o de permisos de acceso al archivo.

---

## Residentes

### 16. El selector de unidad al registrar un familiar/residente incluye estacionamientos
**Tipo:** bug funcional · **Prioridad:** 🟠 Media
Al agregar un familiar o residente, el selector de "departamento" muestra también los estacionamientos, como si fueran una unidad habitable donde se puede alojar a alguien. Debería filtrarse a solo unidades tipo "Departamento" — el mismo filtro que ya existe (y funciona bien) en el flujo de reservas (`type==1`), aplicado aquí también.

### 17. Permite fecha fin de alquiler anterior a la fecha de inicio; el error aparece tarde
**Tipo:** bug funcional (validación) · **Prioridad:** 🟠 Media
El formulario deja avanzar con `fecha_fin < fecha_inicio` sin avisar en el paso donde se eligen las fechas. El error solo aparece en la página siguiente, al hacer click en "Registrar" — obligando a retroceder y corregir después de haber llenado más datos. Debería validarse en el mismo paso donde se seleccionan las fechas (validación inline, deshabilitando "Siguiente" o mostrando el error ahí mismo).

### 18. Al eliminar un residente, el menú de acciones queda huérfano
**Tipo:** bug funcional (UI stale) · **Prioridad:** 🟡 Baja
Después de eliminar un residente, solo desaparece el nombre/título en el listado, pero el menú de acciones (editar, etc.) de esa fila se mantiene visible y ya no lleva a ningún lado porque el usuario fue borrado. La fila completa debería quitarse del listado (o mostrarse claramente como "eliminado", sin acciones disponibles).

### 19. El listado de residentes se ordena de más antiguo a más nuevo
**Tipo:** mejora UX · **Prioridad:** 🟡 Baja
Los residentes agregados más recientemente aparecen al final de la lista, obligando a hacer scroll cada vez que agregas uno nuevo. Sería más natural ordenar del más reciente al más antiguo (o dejar elegir el orden).

---

## Visitas

### 20. El botón de editar una visita no funciona
**Tipo:** bug funcional · **Prioridad:** 🟠 Media
Al presionar el ícono de lápiz (editar) en una visita, aparece un mensaje de "no disponible". O se implementa la edición, o se oculta/deshabilita visualmente el botón para no sugerir una función que no existe.

### 21. Eliminar una visita la borra por completo en vez de archivarla
**Tipo:** bug funcional (integridad de datos) · **Prioridad:** 🟠 Media
Al eliminar una visita, desaparece del listado sin dejar rastro — como si nunca se hubiera registrado. Para un registro de control de acceso/seguridad, esto no debería ser un borrado físico: debería quedar archivada (soft delete) para mantener el historial, aunque ya no se muestre en el listado activo.

### 22. La fecha de la visita sale un día menos que la registrada
**Tipo:** bug de datos (timezone) · **Prioridad:** 🟠 Media
Mismo síntoma que el hallazgo 4 de Reservas — muy probablemente la misma causa raíz (manejo de timezone al guardar/mostrar fechas). Vale la pena revisar y corregir ambos casos juntos, ya que seguramente comparten el mismo bug de fondo en cómo se formatean fechas en la app.

### 23. Permite registrar una visita el mismo día con hora ya pasada
**Tipo:** bug funcional (validación) · **Prioridad:** 🟠 Media
Si la visita es para el día de hoy, el formulario debería impedir elegir una hora anterior a la hora actual. Actualmente lo permite sin avisar.

---

## Finanzas

### 24. "Saldos pendientes" y "Gastos comunes" no están implementados
**Tipo:** feature faltante · **Prioridad:** 🟡 Baja
En Finanzas → Balance, las secciones de "Saldos pendientes" y "Gastos comunes" no muestran funcionalidad real. Si todavía no están priorizadas para este lanzamiento, considera ocultarlas del menú en vez de dejarlas visibles y sin implementar (genera confusión/desconfianza en el residente).

---

## Navegación general

### 25. El indicador de sección activa en el footer (Inicio / Finanzas / Salir) es demasiado sutil
**Tipo:** mejora UX · **Prioridad:** 🟡 Baja
Al navegar entre "Inicio" y "Finanzas" desde el footer, el ícono activo sí cambia ligeramente, pero el contraste es tan bajo que en la práctica pasa desapercibido — el usuario no lo nota al ver la pantalla normalmente. No es que falte el indicador, es que es imperceptible. Reforzar el estado activo con un cambio más notorio (color de marca más saturado, fondo/pastilla detrás del ícono, o ícono relleno vs. outline) para que se note de un vistazo, como es estándar en navegación tipo tab-bar.

---

## Resumen para priorización

**Bugs que rompen una regla de negocio o generan datos inconsistentes (arreglar primero):**
- #2 (doble reserva del mismo área permitida)
- #9 (crash en detalle de evento)
- #4 y #22 (mismo bug de timezone en fechas, en 2 módulos distintos — buscar causa común)
- #12, #17, #23 (validaciones de fecha/hora faltantes en 3 formularios distintos)
- #15 (imágenes de incidencias no se ven)
- #21 (borrado físico de visitas en vez de archivado)

**Mejoras de experiencia (agrupar en un sprint de UX/pulido):**
- #5, #6, #7, #8, #10, #13, #14, #18, #19, #25

**Decisión de producto pendiente:**
- #1 (¿inquilino con varios departamentos es válido?)
- #24 (¿ocultar o priorizar Finanzas → Balance?)
