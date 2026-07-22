# TASK_005 — Client: Quotas, Payments, Notices

## Objetivo
Agregar v-slot:loading a los botones de formulario de Cuotas, Pagos y Notificaciones del cliente.

## Archivos a modificar

### 1. view/client/Quotas/payQuota.vue
- **q-btn línea 401** — botón "Siguiente / Ya hice el pago / Confirmar pago / Pagar reserva"
- Ya tiene `:loading="loading"` y type="submit"

```vue
<q-btn ... type="submit" :loading="loading">
  Pagar
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Procesando...
  </template>
</q-btn>
```

### 2. view/client/Payments/payForm.vue
- **q-btn línea 451** — botón "PAGAR"
- **q-btn línea 536** — botón "Confirmar pago / Ya hice el pago"
- **q-btn línea 658** — botón "Finalizar"
- Ya tienen `:loading="loading"` y type="submit"

Aplicar a los 3 botones:

```vue
<q-btn ... type="submit" :loading="loading">
  Pagar
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Procesando...
  </template>
</q-btn>
```

### 3. view/client/Notices/noticesView.vue
- **q-btn línea 114** — botón "Aprobar"
- Ya tiene `:loading="loading"` y @click

```vue
<q-btn ... :loading="loading" @click="setStatusAnnounce(2)">
  Aprobar
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Actualizando...
  </template>
</q-btn>
```

> Nota: hay un botón "Rechazar" en la línea 111, decidir según criterio si agregar loading.

## Instrucciones

1. Agregar v-slot:loading a todos los botones listados.
2. Mantener props y lógica original.
3. Ejecutar npm run build.
