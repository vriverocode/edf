# TASK_004 — Client: Visits, Incidents, Familiar, Reserves, Claims

## Objetivo
Agregar v-slot:loading a los botones de formulario del dominio Cliente: Visitas, Incidentes, Residentes/Familiares, Reservas y Libro de Reclamaciones.

## Archivos a modificar

### 1. view/client/Visits/createVisit.vue
- **q-btn línea 287** — botón "Registrar visita"
- Ya tiene `:loading="loading"` y type="submit"

```vue
<q-btn ... type="submit" :loading="loading">
  Registrar visita
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Registrando...
  </template>
</q-btn>
```

### 2. view/client/Incidents/createIncident.vue
- **q-btn línea 237** — botón "Enviar"
- Ya tiene `:loading="loading"` y type="submit"

```vue
<q-btn ... type="submit" :loading="loading">
  Enviar
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Enviando...
  </template>
</q-btn>
```

### 3. view/client/Familiar/createFamiliar.vue
- **q-btn línea 428** — botón "Siguiente"
- **q-btn línea 502** — botón "Registrar / Siguiente"
- **q-btn línea 581** — botón "Siguiente"
- **q-btn línea 634** — botón "Registrar"
- Ya tienen `:loading="loading"` y type="submit"
- Línea 674 es botón "Finalizar" sin @click async, no modificarlo

Aplicar a los 4 botones:

```vue
<q-btn ... type="submit" :loading="loading">
  Siguiente
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Guardando...
  </template>
</q-btn>
```

### 4. view/client/Familiar/editFamiliar.vue
- **q-btn línea 175** — botón "Guardar cambios"
- Ya tiene `:loading="loading"` y type="submit"

```vue
<q-btn ... type="submit" :loading="loading">
  Guardar cambios
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Guardando...
  </template>
</q-btn>
```

### 5. view/client/Reserves/createReserve.vue
- **q-btn línea 980** — botón "Realizar reserva / Pagar ahora"
- **q-btn línea 998** — botón "Elegir horario"
- **q-btn línea 1136** — botón "Aceptar y continuar"
- **q-btn línea 1229** — botón "Continuar" (NO TIENE :loading!)
- Ya tienen `:loading="loading"` (las primeras 3)
- En línea 1229 falta :loading, agregarlo

```vue
<q-btn ... type="submit" :loading="loading">
  Continuar
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Continuando...
  </template>
</q-btn>
```

### 6. view/client/Reserves/extendReserve.vue
- **q-btn línea 217** — botón "Confirmar extensión"
- Ya tiene `:loading="submitting"` y @click

```vue
<q-btn ... :loading="submitting" @click="confirmExtension">
  Confirmar extensión
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Confirmando...
  </template>
</q-btn>
```

### 7. view/client/Claims/claimsCreate.vue
- **q-btn línea 588** — botón "Enviar Reclamo"
- Ya tiene `:loading="loading"` и type="submit"

```vue
<q-btn ... type="submit" :loading="loading">
  Enviar Reclamo
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Enviando...
  </template>
</q-btn>
```

## Instrucciones

1. Agregar v-slot:loading a todos los botones listados.
2. En createReserve.vue línea 1229, asegurarse de que el q-btn tenga :loading.
3. Mantener props originales.
4. Ejecutar npm run build.
