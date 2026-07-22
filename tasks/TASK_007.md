# TASK_007 — Components: Cancel Reserve, Pay Methods, Admin, Layout

## Objetivo
Agregar v-slot:loading a los botones de componentes restantes: cancelar reserva, métodos de pago, modales de admin/layout.

## Archivos a modificar

### 1. components/reserves/cancelReserveModal.vue
- **q-btn línea 86** — botón "Cancelar reserva"
- Ya tiene `:loading="loading"` y @click

```vue
<q-btn ... :loading="loading" @click="cancelBooking()">
  Cancelar reserva
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Cancelando...
  </template>
</q-btn>
```

### 2. components/payMethods/disabledModal.vue
- **q-btn línea 74** — botón "Si"
- Ya tiene `:loading="loading"` y @click

```vue
<q-btn ... :loading="loading" @click="disablePayMethod()">
  Si
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Procesando...
  </template>
</q-btn>
```

### 3. components/admin/deleteUserModal.vue
- **q-btn línea 59** — botón "Borrar"
- Ya tiene `:loading="loading"` y @click

```vue
<q-btn ... :loading="loading" @click="deleteUser">
  Borrar
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Eliminando...
  </template>
</q-btn>
```

### 4. components/layout/logoutModal.vue
- **q-btn línea 61** -- botón "Si"
- [movido a TASK_001]

### 5. components/layout/firstTimeSetupModal.vue
- **q-btn línea 181** — [verificar en TASK_001, ya completado]

## Instrucciones

1. Agregar v-slot:loading a los botones listados.
2. Mantener props y lógica original.
3. Ejecutar npm run build.
