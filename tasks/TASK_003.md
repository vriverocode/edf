# TASK_003 — Admin: Expenses, ComunAreas, Events, Pays

## Objetivo
Agregar v-slot:loading a los botones de formulario de Gastos, Áreas comunes, Eventos y Pagos del Admin.

## Archivos a modificar

### 1. view/admin/Expenses/expenseForm.vue
- **q-btn línea 456** — botón "Siguiente" / "Guardar"
- Ya tiene `:loading="loading && step === 2"` y type="submit"

```vue
<q-btn ... type="submit" :loading="loading">
  Siguiente
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Guardando...
  </template>
</q-btn>
```

> Nota: si `loading` depende de step, ajustar el v-slot en consecuencia. Se sugiere mantener `:loading="loading && step === 2"`.

### 2. view/admin/ComunAreas/createComunArea.vue
- **q-btn línea 423** — botón "Siguiente"
- Ya tiene `:loading="loading"` y type="submit"

```vue
<q-btn ... type="submit" :loading="loading">
  Siguiente
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Creando...
  </template>
</q-btn>
```

### 3. view/admin/ComunAreas/updateComunArea.vue
- **q-btn línea 443** — botón "Siguiente" / "Guardar"
- Ya tiene `:loading="loading"` y type="submit"

```vue
<q-btn ... type="submit" :loading="loading">
  Guardar
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Guardando...
  </template>
</q-btn>
```

### 4. view/admin/ComunAreas/createMaintenance.vue
- **q-btn línea 255** — botón "Programar"
- Ya tiene `:loading="loading"` y type="submit"

```vue
<q-btn ... type="submit" :loading="loading">
  Programar
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Programando...
  </template>
</q-btn>
```

### 5. view/admin/Events/createEvent.vue
- **q-btn línea 336** — botón "Siguiente" / "Guardar reserva"
- Ya tiene `:loading="loading"` y type="submit"

```vue
<q-btn ... type="submit" :loading="loading">
  Siguiente
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Guardando...
  </template>
</q-btn>
```

### 6. view/admin/Events/updateEvent.vue
- **q-btn línea 344** — botón "Siguiente" / "Guardar reserva"
- Ya tiene `:loading="loading"` y type="submit"

```vue
<q-btn ... type="submit" :loading="loading">
  Siguiente
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Guardando...
  </template>
</q-btn>
```

### 7. view/admin/Pays/registerPay.vue
- **q-btn línea 414** — botón "Registrar pago"
- Ya tiene `:loading="submitting"` (variable se llama submitting)

```vue
<q-btn ... :loading="submitting" @click="submitPay">
  Registrar pago
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Registrando...
  </template>
</q-btn>
```

### 8. components/comunAreas/deleteAreaModal.vue
- **q-btn línea 71** — botón "Si" (confirmar borrado)
- Ya tiene `:loading="loading"` y @click

```vue
<q-btn ... :loading="loading" @click="deleteComunArea()">
  Si
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Eliminando...
  </template>
</q-btn>
```

## Instrucciones

1. Aplicar v-slot:loading en cada botón listado.
2. Mantener las props y lógica original del q-btn.
3. Ejecutar `npm run build`.
