# TASK_002 — Admin: Property, Users, Bank, Water, Financial, Monthly

## Objetivo
Agregar v-slot:loading a los botones de formulario del dominio Admin (Propiedades, Usuarios, Cuentas bancarias, lecturas de agua, cuentas financieras, presupuestos mensuales).

## Archivos a modificar

### 1. `view/admin/Department/createDepartment.vue`
- **q-btn línea 111** — botón "Siguiente"
- Ya tiene `:loading="loading"` y type="submit"

```vue
<q-btn color="primary" style="border-radius: 0.5rem;" type="submit" :loading="loading">
  Siguiente
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Creando...
  </template>
</q-btn>
```

### 2. `view/admin/Department/createUnit.vue`
- **q-btn línea 121** — botón "Siguiente"
- Ya tiene `:loading="loading"` y type="submit"

```vue
<q-btn color="primary" style="border-radius: 0.5rem;" type="submit" :loading="loading">
  Siguiente
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Creando...
  </template>
</q-btn>
```

### 3. `view/admin/Department/updateDepartment.vue`
- **q-btn línea 122** — botón "Guardar cambios"
- Ya tiene `:loading="loading"` y type="submit"

```vue
<q-btn color="primary" style="border-radius: 0.5rem;" type="submit" :loading="loading">
  Guardar cambios
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Guardando...
  </template>
</q-btn>
```

### 4. view/admin/Department/departmentList.vue
- **q-btn línea 223** — botón "Guardar cambio" en modal
- Ya tiene `:loading="modalLoading"`

```vue
<q-btn color="primary" no-caps :loading="modalLoading" @click="assignNewOwner">
  Guardar cambio
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Guardando...
  </template>
</q-btn>
```

### 5. view/admin/Users/createUser.vue
- **q-btn línea 177** — botón "Siguiente"
- **q-btn línea 232** — botón "Siguiente"
- Ya tienen `:loading="loading"` y type="submit"

```vue
<q-btn ... type="submit" :loading="loading">
  Siguiente
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Creando...
  </template>
</q-btn>
```

### 6. view/admin/Users/assingApartment.vue
- **q-btn línea 122** — botón "Asignar"
- Ya tiene `:loading="loading"` y type="submit"

```vue
<q-btn ... type="submit" :loading="loading">
  Asignar
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Asignando...
  </template>
</q-btn>
```

### 7. view/admin/BankAccount/createAccountBank.vue
- **q-btn línea 155** — botón "Siguiente"
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

### 8. view/admin/BankAccount/updateAccountBank.vue
- **q-btn línea 206** — botón "Actualizar"
- Ya tiene `:loading="loading"` y type="submit"

```vue
<q-btn ... type="submit" :loading="loading">
  Actualizar
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Actualizando...
  </template>
</q-btn>
```

### 9. view/admin/WaterReadings/waterReadingForm.vue
- **q-btn línea 219** — botón "Guardar"
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

### 10. view/admin/WaterReadings/waterReadingEditForm.vue
- **q-btn línea 256** — botón "Guardar cambios"
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

### 11. view/admin/FinancialAccounts/createFinancialAccount.vue
- **q-btn línea 127** — botón "Guardar"
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

### 12. view/admin/FinancialAccounts/updateFinancialAccount.vue
- **q-btn línea 144** — botón "Guardar"
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

### 13. view/admin/MonthlyBills/monthlyBillsForm.vue
- **q-btn línea 219** — botón "Guardar"
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

### 14. view/admin/MonthlyBills/monthlyBillsEditForm.vue
- **q-btn línea 194** — botón "Guardar cambios"
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

### 15. components/admin/deleteUserModal.vue
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

### 16. components/admin/initialWaterReadingModal.vue
- **q-btn línea 291** — botón "Guardar"
- Ya tiene `:loading="loading"` y @click

```vue
<q-btn ... :loading="loading" @click="submit">
  Guardar
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Guardando...
  </template>
</q-btn>
```

## Instrucciones

1. Aplicar los cambios de v-slot:loading en todos los botones listados.
2. Mantener las props originales del q-btn.
3. Ejecutar `npm run build` en frontend/.
4. Verificar que compile sin errores.
