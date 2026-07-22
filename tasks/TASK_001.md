# TASK_001 — Auth + Layout

## Objetivo
Agregar v-slot:loading a todos los botones de formulario de Auth y Layout.

## Archivos a modificar

### 1. `frontend/src/resources/view/auth/login.vue`
- **q-btn línea 105** — botón "Ingresar"
- Ya tiene `:loading="loading"`, solo agregar v-slot:loading:

```vue
<q-btn ... :loading="loading" size="lg">
  Ingresar
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Ingresando...
  </template>
</q-btn>
```

### 2. `frontend/src/resources/view/auth/forgotPassword.vue`
- **q-btn línea 85** — botón "Enviar"
- Ya tiene `:loading="loading"` y type="submit"

```vue
<q-btn ... :loading="loading" type="submit">
  Enviar
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Enviando...
  </template>
</q-btn>
```

> Saltar el botón de "Volver al login" (línea 113) — es navegación, no submit.

### 3. `frontend/src/resources/view/auth/resetPassword.vue`
- **q-btn línea 233** — botón "Guardar"
- Ya tiene `:loading="loading"` y type="submit"

```vue
<q-btn ... :loading="loading">
  Guardar
  <template v-slot:loading>
    <q-spinner-spinner-gears class="on-left" />
    Guardando...
  </template>
</q-btn>
```

> Saltar los botones de "Volver al login" (líneas 130, 153).

### 4. `frontend/src/resources/components/layout/logoutModal.vue`
- **q-btn línea 61** — botón "Si" dentro del diálogo de cerrar sesión
- Ya tiene `:loading="loading"` y @click

```vue
<q-btn ... :loading="loading" @click="logout()">
  Si
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Cerrando sesión...
  </template>
</q-btn>
```

### 5. `frontend/src/resources/components/layout/firstTimeSetupModal.vue`
- **q-btn línea 181** — botón "Guardar y continuar"
- Ya tiene `:loading="loading"` y v-slot:loading. Verificar que está completo.
- No requiere cambios.

## Instrucciones

1. Aplicar los cambios de v-slot:loading en todos los q-btn listados.
2. Ejecutar `npm run build` en frontend/
3. Verificar que compile sin errores.

## Resumen de cambios
| Archivo | q-btn línea | Botón a modificar |
|---------|-------------|-------------------|
| login.vue | 105 | Ingresar |
| forgotPassword.vue | 85 | Enviar |
| resetPassword.vue | 233 | Guardar |
| logoutModal.vue | 61 | Si |
