# Bugs y mejoras de usabilidad — Frontend Pacifik (rol Admin)

**Fecha:** 2026-07-15
**Alcance:** `https://web.edificiopacifik.com/` (panel de administración — rol "admin")
**Fuente:** pruebas manuales del usuario navegando la app con una cuenta de tipo admin.
**Nota:** este documento es de UX/funcionalidad para el rol admin, no de seguridad — para hallazgos de seguridad ver `pacifik-vulnerabilidades.md`. Para los hallazgos de propietario/residente ver `pacifik-bugs-usabilidad.md`, y para el rol worker ver `pacifik-bugs-usabilidad-worker.md`.

**Contexto del rol:** el admin es quien gestiona la operación completa del edificio — áreas comunes, reservas, usuarios, unidades, finanzas. Varias fallas ya reportadas para el rol worker se repiten aquí (mismo componente de listado reutilizado), y a eso se suman módulos exclusivos del admin (finanzas, presupuesto, gestión de usuarios y unidades) que tienen huecos funcionales más profundos, porque de este rol depende la conciliación de pagos y la gestión económica del edificio.

---

## Índice

| # | Módulo | Tipo | Prioridad | Resumen |
|---|--------|------|-----------|---------|
| 1 | Áreas comunes | Bug funcional | 🟠 Media | "Ver reservas históricas" no es un histórico: muestra reservas futuras y canceladas, sin depto, sin filtros |
| 2 | Áreas comunes | Bug funcional | 🔴 Alta | El botón "Crear reserva" del admin permite crear una reserva sin departamento asociado |
| 3 | Áreas comunes | Mejora UX / producto | 🟠 Media | Terminología inconsistente en tipos de área ("gratuito"≈"compartido", "exclusivo"≈"de pago") y solapamiento entre "de pago" y "de pago con lista de invitados" |
| 4 | Áreas comunes | Feature faltante | 🟠 Media | No se pueden configurar turnos horarios (ej. 6–8am, 8–10am), solo un rango general y días fijos |
| 5 | Áreas comunes | Feature faltante | 🔴 Alta | No se puede poner un área en mantenimiento en una fecha/horario específico |
| 6 | Reservas (admin) | Feature faltante | 🔴 Alta | No hay flujo diferenciado para reservas de pago (conciliación del pago) |
| 7 | Reservas (admin) | Regla de negocio | 🔴 Alta | No existe flujo de devolución cuando un propietario pagó, fue aceptado, y luego cancela |
| 8 | Reservas (admin) | Mejora UX | 🟠 Media | Mismas fallas que en la vista worker: sin depto, se ven canceladas, sin paginado |
| 9 | Noticias | Feature faltante | 🟠 Media | Solo hay noticias masivas — no se puede segmentar por departamento, torre o piso |
| 10 | Reporte de reservas | Mejora UX | 🟠 Media | Vuelve a aparecer el listado de reservas canceladas |
| 11 | Usuarios | Bug funcional / performance | 🟠 Media | Filtro por defecto "Propietarios" pero carga todos sin paginar |
| 12 | Usuarios | Feature faltante | 🟠 Media | No hay filtros para buscar por departamento o nombre |
| 13 | Usuarios | Bug funcional | 🟠 Media | El formulario de 2 pasos solo avisa de campos obligatorios del paso 1 al llegar al paso final, y no marca cuáles son obligatorios |
| 14 | Usuarios | Bug funcional | 🔴 Alta | Permite crear propietarios sin asignar unidad, y luego no aparecen en el listado de propietarios |
| 15 | Usuarios | Mejora UX | 🟠 Media | Al filtrar por inquilino/familiar/airbnb solo se ve el nombre, no el departamento |
| 16 | Usuarios | Bug funcional | 🔴 Alta | Los botones "Editar usuario" y "Ver pagos" no funcionan |
| 17 | Departamentos (unidades) | Mejora UX / naming | 🟡 Baja | El menú se llama "Departamentos" pero gestiona todo tipo de unidades (deptos, estacionamientos, depósitos) |
| 18 | Departamentos (unidades) | Mejora UX | 🟠 Media | El listado de unidades no muestra el propietario |
| 19 | Departamentos (unidades) | Bug de datos (a confirmar) | 🔴 Alta | El % de participación solo se asigna al departamento; estacionamientos y depósitos quedan en 0%, en vez de sumar al total del propietario |
| 20 | Departamentos (unidades) | Mejora UX | 🟠 Media | El modal para reasignar una unidad muestra todos los usuarios solo con nombre, sin buscador |
| 21 | Departamentos (unidades) | Feature faltante | 🔴 Alta | Registrar el consumo de agua es impráctico: no existe un flujo secuencial de toma de lectura por unidad |
| 22 | Departamentos (unidades) | Mejora UX | 🔴 Alta | No hay indicador visual de qué unidades ya tienen la lectura de agua registrada en el periodo |
| 23 | Departamentos (unidades) | Mejora UX | 🟠 Media | Editar una unidad abre una página nueva (no modal) y al volver recarga el listado desde el inicio, sin indicar qué cambió |
| 24 | Finanzas → Balance | Feature faltante | 🟠 Media | "Saldos pendientes" y "Gastos comunes" no están implementados |
| 25 | Finanzas → Presupuesto | Bug funcional | 🔴 Alta | El campo "Consumo total de agua (m³)" solo acepta 3 dígitos; al ingresar el 4° se reinicia y borra lo escrito |
| 26 | Finanzas → Presupuesto | Mejora UX | 🟠 Media | No se muestra ni se valida el consumo del periodo anterior al registrar el actual |
| 27 | Finanzas → Presupuesto | Regla de negocio | 🟠 Media | "Incluir gastos" permite añadir al periodo actual gastos que pertenecen a periodos pasados |
| 28 | Finanzas → Presupuesto | Feature faltante | 🟠 Media | No hay indicador de cuánto se ha gastado del presupuesto total en el periodo |
| 29 | Finanzas → Presupuesto | Mejora UX | 🔴 Alta | Medición de agua tiene el mismo problema de lista larga sin indicador de qué unidad ya se registró |
| 30 | Finanzas → Cuotas | No verificado | 🟡 Baja | "Cuotas de mantenimiento" no tiene data cargada para poder validar la funcionalidad |
| 31 | Finanzas → Gastos | Feature faltante | 🟠 Media | El proveedor se elige de una lista solo con nombre; no hay gestión real de proveedores |
| 32 | Finanzas → Gastos | Feature faltante | 🟠 Media | Mismo problema con la categoría del servicio: se crea desde el formulario, sin gestión centralizada |
| 33 | Finanzas → Gastos | Bug funcional | 🟠 Media | El comprobante adjunto no se ve luego en el listado de gastos |
| 34 | Finanzas → Gastos | Mejora UX | 🟡 Baja | El listado de gastos no tiene filtros rápidos |
| 35 | Finanzas → Pagos | Bug funcional (crítico) | 🔴 Alta | La sección da "Página no encontrada" |
| 36 | Finanzas (general) | Feature faltante | 🔴 Alta | No se encuentra dónde ingresar el presupuesto anual |
| 37 | Finanzas (general) | Feature faltante | 🔴 Alta | No hay reportes de gastos |
| 38 | Finanzas (general) | Feature faltante | 🔴 Alta | No hay reporte de morosos |
| 39 | Finanzas (general) | Feature faltante | 🟠 Media | No hay alertas de gestión |
| 40 | Finanzas (general) | Feature faltante | 🟠 Media | No hay indicadores de gestión |
| 41 | General | Mejora UX | 🟠 Media | Inconsistencia visual: a veces hay botón "Volver", a veces no; a veces modal, a veces página nueva |
| 42 | General | Observación / riesgo de adopción | 🔴 Alta | La fricción de los formularios hace que, en la práctica, el administrador termine prefiriendo usar Excel en vez del sistema |

---

## Áreas comunes

### 1. "Ver reservas históricas" no muestra un histórico
**Tipo:** bug funcional · **Prioridad:** 🟠 Media
El primer botón de acción de cada área común dice "ver reservas históricas", pero en realidad muestra reservas **futuras**, incluyendo canceladas (que no deberían mostrarse, ver hallazgo #5 de `pacifik-bugs-usabilidad-worker.md`). Además, no se muestra el número de departamento que hizo cada reserva, y no hay ningún filtro disponible — son básicamente las mismas falencias ya reportadas en el listado de reservas del worker, repetidas aquí.

### 2. El botón "Crear reserva" del admin permite una reserva sin departamento
**Tipo:** bug funcional · **Prioridad:** 🔴 Alta
Desde el listado de áreas comunes hay un botón para crear una reserva directamente, pero el admin no tiene un departamento asociado a su cuenta — la reserva creada por esta vía queda "flotando", sin ningún propietario/unidad a la que asociarse. Esto puede romper reportes, conciliación de pagos y cualquier lógica que asuma que toda reserva tiene un departamento dueño.

### 3. Terminología inconsistente en los tipos de área
**Tipo:** mejora UX / decisión de producto · **Prioridad:** 🟠 Media
Al editar un área común existen 4 tipos: gratuito, mixto (compartido y exclusivo), de pago, y de pago con lista de invitados. El problema es que la nomenclatura mezcla conceptos que deberían ser independientes: se usa "gratuito" y "compartido" como si fueran sinónimos, y "exclusivo" como si fuera sinónimo de "de pago". Además, no hay ninguna diferencia funcional aparente entre "de pago" y "de pago con lista de invitados" que justifique tenerlos como dos tipos separados. Vale la pena revisar con negocio qué dimensiones son realmente independientes (¿gratis vs. de pago? ¿compartido vs. exclusivo? ¿requiere lista de invitados o no?) y modelarlas como atributos separados en vez de un único selector de "tipo".

### 4. No se pueden configurar turnos horarios al crear/editar un área
**Tipo:** feature faltante · **Prioridad:** 🟠 Media
La configuración de un área común solo permite definir un rango horario general y los días fijos disponibles. No existe la posibilidad de dividir la disponibilidad en turnos/bloques (por ejemplo 6:00–8:00am, 8:00–10:00am, etc.), lo cual limita mucho cómo se puede administrar la disponibilidad real de un área con alta demanda.

### 5. No se puede poner un área en mantenimiento en una fecha específica
**Tipo:** feature faltante · **Prioridad:** 🔴 Alta
No hay forma de bloquear un área común para una fecha y horario puntual por mantenimiento (por ejemplo: "25 de julio, piscina cerrada de 12pm a 6pm por mantenimiento"). Sin esto, el admin no tiene manera de evitar que se sigan creando reservas para un horario en el que el área no va a estar disponible.

---

## Reservas (admin)

### 6. No hay flujo diferenciado para reservas de pago
**Tipo:** feature faltante · **Prioridad:** 🔴 Alta
El listado general de "Reservas" mezcla todas las reservas por igual, pero las reservas de áreas de pago requieren que el admin concilie el pago (verificar que se pagó, aceptarlo, etc.). Actualmente no hay un flujo o vista que le dé énfasis especial a este subconjunto de reservas, cuando es justamente el que requiere más gestión activa por parte del admin.

### 7. No existe flujo de devolución tras una cancelación con pago aceptado
**Tipo:** regla de negocio · **Prioridad:** 🔴 Alta
Cuando un propietario paga una reserva de área de pago, el admin acepta ese pago, y luego el propietario cancela la reserva, no queda ningún registro ni indicador de que hay un monto pendiente de devolver. Esto es un hueco de proceso financiero: el sistema debería marcar automáticamente esas reservas como "pendiente de devolución" para que el admin no dependa de llevar ese seguimiento manualmente.

### 8. Mismas fallas que en la vista del worker
**Tipo:** mejora UX · **Prioridad:** 🟠 Media
El listado de reservas del admin no muestra el número de departamento, incluye reservas canceladas, y carga todas las reservas de golpe sin paginado — los mismos problemas ya documentados en los hallazgos #1, #5 y #8 de `pacifik-bugs-usabilidad-worker.md`. Como es el mismo componente de listado reutilizado, lo más eficiente sería corregirlo una sola vez y que el fix se propague a ambos roles.

---

## Noticias

### 9. Solo existen noticias masivas
**Tipo:** feature faltante · **Prioridad:** 🟠 Media
No hay forma de enviar un comunicado segmentado — solo se puede enviar a todo el edificio. Sería útil poder dirigir una noticia a un departamento específico, a una torre, o a un piso (por ejemplo, para avisos de mantenimiento localizados o comunicados que no aplican a todo el edificio).

---

## Reporte de reservas

### 10. Vuelve a aparecer el listado de canceladas
**Tipo:** mejora UX · **Prioridad:** 🟠 Media
El reporte de reservas también incluye las canceladas mezcladas con las demás — mismo patrón de fondo detectado en Áreas comunes (#1) y Reservas (#8): nada se separa/archiva, todo se acumula en una sola vista.

---

## Usuarios

### 11. El filtro por defecto es "Propietarios" pero carga todos sin paginar
**Tipo:** bug funcional / performance · **Prioridad:** 🟠 Media
El listado de usuarios arranca filtrado por "Propietarios", pero trae todos los registros de una sola vez sin paginación.

### 12. No hay filtros para buscar por departamento o nombre
**Tipo:** feature faltante · **Prioridad:** 🟠 Media
No existe una barra de búsqueda para ubicar un usuario por nombre o por el departamento al que pertenece — con un volumen grande de usuarios esto vuelve la búsqueda manual impracticable.

### 13. El formulario de creación (2 pasos) valida tarde y no marca los campos obligatorios
**Tipo:** bug funcional (UX de validación) · **Prioridad:** 🟠 Media
Crear un usuario nuevo tiene 2 pasos. Si se deja algún campo obligatorio vacío en el paso 1, el sistema no avisa en ese momento — recién avisa al llegar al paso final. Además, en ningún punto del formulario se indica visualmente cuáles campos son obligatorios, así que no hay manera de anticipar el error.

### 14. Permite crear propietarios sin asignar unidad, y luego desaparecen del listado
**Tipo:** bug funcional · **Prioridad:** 🔴 Alta
El formulario deja crear un propietario sin asignarle ningún departamento. El problema es que, una vez creado así, ese propietario ya no aparece al buscarlo en el listado de propietarios — queda un usuario "huérfano" en el sistema, creado pero inubicable desde la pantalla que normalmente se usaría para encontrarlo.

### 15. Los filtros por inquilino/familiar/airbnb no muestran el departamento
**Tipo:** mejora UX · **Prioridad:** 🟠 Media
Al filtrar el listado de usuarios por "inquilino", "familiar" o "airbnb", cada fila solo muestra el nombre de la persona, sin indicar a qué departamento está asociada — un dato imprescindible para que el admin pueda identificar de quién se trata.

### 16. "Editar usuario" y "Ver pagos" no funcionan
**Tipo:** bug funcional · **Prioridad:** 🔴 Alta
Ambos botones de acción del listado de usuarios no responden — son dos funciones centrales para la gestión de usuarios (corregir datos, revisar historial de pagos) que actualmente están rotas.

---

## Departamentos (gestión de unidades)

### 17. El menú "Departamentos" en realidad gestiona todo tipo de unidades
**Tipo:** mejora UX / naming · **Prioridad:** 🟡 Baja
El menú se llama "Departamentos", pero desde ahí se agregan también estacionamientos y depósitos. El nombre del menú no refleja su alcance real, lo cual puede generar confusión (por ejemplo, buscar "dónde se agregan los estacionamientos" y no pensar en este menú).

### 18. El listado de unidades no muestra el propietario
**Tipo:** mejora UX · **Prioridad:** 🟠 Media
Al ver la lista de departamentos/unidades no aparece quién es el propietario de cada una — hay que entrar al detalle para saberlo, cuando debería ser un dato visible directamente en el listado.

### 19. Posible error conceptual en el % de participación
**Tipo:** bug de datos (a confirmar con negocio) · **Prioridad:** 🔴 Alta
El porcentaje de participación (área en m² de la unidad sobre el total del edificio, que determina cuánto le corresponde pagar de cuotas a cada propietario) solo se está asignando al departamento. Los estacionamientos y/o depósitos del mismo propietario quedan con 0% de participación. Esto parece conceptualmente incorrecto: el % de participación de un propietario debería ser la **suma de todas sus unidades** (departamento + estacionamientos + depósitos), no solo la del departamento. Si esto no se corrige, los montos de cuotas de mantenimiento calculados a partir de este porcentaje estarían sub-cobrando a los propietarios con estacionamiento/depósito adicional.

### 20. El modal de reasignación de unidad no tiene buscador
**Tipo:** mejora UX · **Prioridad:** 🟠 Media
Al usar el botón para cambiar la asignación (propietario) de una unidad, se abre un modal con la lista completa de usuarios, mostrando solo el nombre y sin ningún campo de búsqueda — impráctico con una base de usuarios grande.

### 21. Registrar el consumo de agua es impráctico, unidad por unidad
**Tipo:** feature faltante · **Prioridad:** 🔴 Alta
El registro de agua se hace haciendo click en cada unidad individualmente desde el listado. Con un edificio de ~180 departamentos, esto es inmanejable. Debería existir un flujo dedicado de "registro de servicios comunes" que muestre la secuencia de unidades a las que hay que tomarles la lectura, y que al guardar una avance automáticamente a la siguiente — en vez de tener que volver al listado y buscar la próxima unidad cada vez.

### 22. No hay indicador de qué unidades ya tienen la lectura registrada
**Tipo:** mejora UX · **Prioridad:** 🔴 Alta
Una vez registrada el agua de una unidad en el periodo actual, el listado no muestra ningún indicador visual de que ya se hizo. Con ~180 departamentos, no hay forma de saber a cuáles ya se les tomó lectura y a cuáles falta, aparte de recordarlo manualmente o volver a abrir cada una para verificar.

### 23. Editar una unidad abre una página nueva y pierde el contexto al volver
**Tipo:** mejora UX · **Prioridad:** 🟠 Media
El botón de editar unidad no abre un modal (como en otras partes de la app) sino que navega a una página completa. Al guardar o volver, se recarga el listado desde el inicio, sin ningún indicador de qué se modificó — obligando a buscar de nuevo la unidad para confirmar que el cambio se aplicó.

---

## Finanzas

### 24. Balance: "Saldos pendientes" y "Gastos comunes" no implementados
**Tipo:** feature faltante · **Prioridad:** 🟠 Media
Mismo hallazgo que el #24 de `pacifik-bugs-usabilidad.md` (rol propietario), confirmado también desde la vista admin — ninguna de las dos secciones tiene funcionalidad real.

### 25. Presupuesto → Gastos mensuales: el campo de consumo de agua se rompe al 4° dígito
**Tipo:** bug funcional · **Prioridad:** 🔴 Alta
Al crear un presupuesto de gastos mensuales, el campo "Consumo total de agua (m³)" solo acepta 3 dígitos. Al intentar ingresar un cuarto dígito, el campo se reinicia y borra los 2 dígitos previamente escritos — imposibilitando ingresar consumos de 4 cifras o más.

### 26. No se muestra ni se valida el consumo del periodo anterior
**Tipo:** mejora UX / control · **Prioridad:** 🟠 Media
Al registrar el consumo de agua del periodo actual, no hay ninguna referencia al consumo del periodo anterior ni una validación que alerte si el nuevo valor es muy distinto (por ejemplo, para detectar errores de tipeo o consumos anómalos).

### 27. "Incluir gastos" permite mezclar periodos
**Tipo:** regla de negocio · **Prioridad:** 🟠 Media
El botón "Incluir gastos" del presupuesto mensual permite elegir gastos que pertenecen a periodos pasados y agregarlos al periodo actual, lo cual puede distorsionar el presupuesto de ambos periodos.

### 28. No hay indicador de avance del presupuesto
**Tipo:** feature faltante · **Prioridad:** 🟠 Media
No existe ningún indicador que muestre cuánto se ha gastado del presupuesto total durante el periodo — un dato básico de control presupuestario para un administrador.

### 29. Presupuesto → Medición de agua tiene el mismo problema de lista larga
**Tipo:** mejora UX · **Prioridad:** 🔴 Alta
Igual que en el registro de agua desde Departamentos (#21/#22), aquí también se ingresa la medición eligiendo cada unidad de una lista larga, sin indicador de cuáles ya fueron registradas en el periodo.

### 30. Cuotas de mantenimiento: sin data para validar
**Tipo:** no verificado · **Prioridad:** 🟡 Baja
No se pudo evaluar la funcionalidad de "Cuotas de mantenimiento" porque no había datos cargados en el momento de la prueba. Queda pendiente una revisión posterior cuando haya data real.

### 31. Gastos: no hay gestión real de proveedores
**Tipo:** feature faltante · **Prioridad:** 🟠 Media
Al registrar un gasto, el proveedor se elige de una lista que solo muestra el nombre. Si el proveedor no existe, se puede crear directamente desde ese mismo formulario, pero luego no hay ninguna pantalla donde verlo, editarlo o gestionarlo — los proveedores creados así quedan "sueltos" sin administración centralizada.

### 32. Gastos: mismo problema con la categoría del servicio
**Tipo:** feature faltante · **Prioridad:** 🟠 Media
La categoría del gasto tiene el mismo patrón que los proveedores (#31): se crea al vuelo desde el formulario de registro de gasto, sin ninguna pantalla de gestión donde revisar o editar las categorías existentes.

### 33. El comprobante adjunto no aparece en el listado de gastos
**Tipo:** bug funcional · **Prioridad:** 🟠 Media
Al registrar un gasto se puede adjuntar un comprobante, pero luego, al ver el listado de gastos, no hay forma de ver el/los adjuntos — mismo tipo de síntoma que el hallazgo #15 de `pacifik-bugs-usabilidad.md` (imágenes de incidencias que tampoco se visualizan).

### 34. El listado de gastos no tiene filtros rápidos
**Tipo:** mejora UX · **Prioridad:** 🟡 Baja
No hay filtros accesibles directamente desde el listado de gastos (por proveedor, categoría, rango de fechas, etc.).

### 35. Finanzas → Pagos da "Página no encontrada"
**Tipo:** bug funcional (crítico) · **Prioridad:** 🔴 Alta
La sección de Pagos dentro de Finanzas no carga — muestra un error de página no encontrada (404). Esto bloquea por completo una de las funciones más centrales del rol admin: gestionar los pagos de los propietarios.

### 36–40. Funcionalidad de gestión financiera ausente
**Tipo:** feature faltante · **Prioridad:** 🔴 Alta (presupuesto anual, reportes de gastos, reporte de morosos) / 🟠 Media (alertas, indicadores de gestión)
No se encontró en ningún menú:
- **#36** un lugar para ingresar el presupuesto anual del edificio,
- **#37** reportes de gastos,
- **#38** un reporte de morosos (propietarios con pagos pendientes),
- **#39** alertas de gestión (vencimientos, pagos atrasados, presupuesto excedido, etc.),
- **#40** indicadores de gestión en general (dashboard financiero).

Estas son funciones esperables en cualquier sistema de administración de edificios — su ausencia obliga al administrador a llevar esta gestión fuera del sistema (ver también hallazgo #42).

---

## General

### 41. Inconsistencia visual entre pantallas
**Tipo:** mejora UX · **Prioridad:** 🟠 Media
No hay un patrón consistente de navegación: algunas pantallas tienen botón "Volver" explícito y otras no; algunas acciones abren un modal y otras navegan a una página completa nueva (ver también hallazgo #23). Esto obliga al usuario a re-aprender la navegación en cada sección en vez de poder anticiparla.

### 42. La fricción general empuja al admin hacia Excel
**Tipo:** observación general / riesgo de adopción · **Prioridad:** 🔴 Alta
Sumando todo lo anterior — formularios que no indican campos obligatorios, listas larguísimas sin buscador ni indicador de progreso, falta de reportes y de un dashboard financiero, navegación inconsistente — el resultado práctico es que, para gestionar un edificio real, es más rápido y confiable llevar todo en una hoja de cálculo que usar el sistema. Esto no es un bug puntual, sino la señal de que el flujo de trabajo del admin (sobre todo en Finanzas y en el registro masivo de lecturas de agua) necesita rediseñarse pensando en el volumen real de unidades que maneja, no solo en que cada pantalla "funcione" de forma aislada.

---

## Resumen para priorización

**Bugs críticos / bloqueantes (arreglar primero):**
- #35 (Finanzas → Pagos da 404 — función central del rol completamente inaccesible)
- #25 (campo de consumo de agua se rompe al 4° dígito)
- #16 (Editar usuario y Ver pagos no funcionan)
- #14 (propietarios creados sin unidad quedan inubicables)
- #2 (reserva creada por el admin sin departamento asociado)
- #19 (% de participación no suma todas las unidades del propietario — a confirmar con negocio, pero si es un error, afecta directamente cuánto paga cada propietario)

**Huecos de proceso financiero (impactan directamente la gestión de dinero del edificio):**
- #6 y #7 (sin flujo de conciliación de pago ni de devolución en reservas de pago)
- #27 (mezcla de gastos entre periodos)
- #36, #37, #38 (sin presupuesto anual, sin reportes de gastos, sin reporte de morosos)

**Impráctico a escala (funciona en una demo, se rompe con datos reales — ~180 unidades):**
- #21, #22, #29 (registro de agua unidad por unidad, sin indicador de progreso)
- #11, #12, #20 (listados de usuarios y modales sin paginar ni buscar)
- #1, #8, #10 (reservas/reportes sin paginado ni filtros, canceladas mezcladas)

**Mejoras de experiencia (agrupar en un sprint de UX/pulido):**
- #3, #4, #9, #15, #17, #18, #23, #26, #28, #31, #32, #33, #34, #41

**Pendiente de validar / decisión de producto:**
- #19 (confirmar con negocio la regla de % de participación)
- #30 (revisar Cuotas de mantenimiento cuando haya data)
- #5 (mantenimiento programado de áreas — validar prioridad con negocio)

**Observación transversal:**
- #42 — no es un bug individual, es la conclusión de que el flujo completo de Finanzas (y el registro masivo de lecturas) necesita un rediseño pensado para el volumen real de unidades, no solo arreglos puntuales.
