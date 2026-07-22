# TASK_006 — Components: Notices, Finance

## Objetivo
Agregar v-slot:loading a los botones de componentes de anuncios/noticias, eventos y finanzas.

## Archivos a modificar

### 1. components/notices/createNoticeModal.vue
- **q-btn línea 170** — botón "Publicar"
- Ya tiene `:loading="loading"` y type="submit"

```vue
<q-btn ... type="submit" :loading="loading">
  Publicar
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Publicando...
  </template>
</q-btn>
```

### 2. components/notices/createAnnouncesModal.vue
- **q-btn línea 218** — botón "Publicar"
- Ya tiene `:loading="loading"` y type="submit"

```vue
<q-btn ... type="submit" :loading="loading">
  Publicar
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Publicando...
  </template>
</q-btn>
```

### 3. components/notices/updateNoticeModal.vue
- **q-btn línea 199** — botón "Publicar"
- Ya tiene `:loading="loading"` y type="submit"

```vue
<q-btn ... type="submit" :loading="loading">
  Publicar
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Actualizando...
  </template>
</q-btn>
```

### 4. components/notices/updateAnnounceModal.vue
- **q-btn línea 213** — botón "Publicar"
- Ya tiene `:loading="loading"` y type="submit"

```vue
<q-btn ... type="submit" :loading="loading">
  Publicar
  <template v-slot:loading>
    <q-spinner-gears type="on-left" />
    Actualizando...
  </template>
</q-btn>
```

### 5. components/notices/deleteNoticeModal.vue
- **q-btn línea 63** — botón "Borrar"
- Ya tiene `:loading="loading"` y @click

```vue
<q-btn ... :loading="loading" @click="deleteAnnounce">
  Borrar
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Eliminando...
  </template>
</q-btn>
```

### 6. components/notices/deleteAnnounceModal.vue
- **q-btn línea 63** — botón "Borrar"
- Ya tiene `:loading="loading"` y @click

```vue
<q-btn ... :loading="loading" @click="deleteAnnounce">
  Borrar
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Eliminando...
  </template>
</q-btn>
```

### 7. components/events/deleteEventModal.vue
- **q-btn línea 63** — botón "Borrar"
- Ya tiene `:loading="loading"` y @click

```vue
<q-btn ... :loading="loading" @click="deleteAnnounce">
  Borrar
  <template>
    <q-spinner-gears class="on-left" />
    Eliminando...
  </template>
</q-btn>
```

### 8. components/finance/createServiceCategoryModal.vue
- **q-btn línea 107** — botón "Crear"
- Ya tiene `:loading="loading"` y @click

```vue
<q-btn ... :loading="loading" @click="submit">
  Crear
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Creando...
  </template>
</q-btn>
```

### 9. components/finance/createFinancialAccountModal.vue
- **q-btn línea 147** — botón "Crear"
- Ya tiene `:loading="loading"` y @click

```vue
<q-btn ... :loading="loading" @click="submit">
  Crear
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Creando...
  </template>
</q-btn>
```

### 10. components/finance/createProviderModal.vue
- **q-btn línea 222** — botón "Crear"
- Ya tiene `:loading="loading"` y @click

```vue
<q-btn ... :loading="loading" @click="submit">
  Crear
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Creando...
  </template>
</q-btn>
```

### 11. components/finance/createTransactionCategoryModal.vue
- **q-btn línea 119** — botón "Crear"
- Ya tiene `:loading="loading"` y @click

```vue
<q-btn ... :loading="loading" @click="submit">
  Crear
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Creando...
  </template>
</q-btn>
```

### 12. components/reserves/addGuestModal.vue
- **q-btn línea 84** — botón "Agregar"
- Ya tiene `:loading="loading"` y @click

```vue
<q-btn ... :loading="loading" @click="addGuest()">
  Agregar
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Agregando...
  </template>
</q-btn>
```

### 13. components/reserves/editGuestModal.vue
- **q-btn línea 84** — botón "Actualizar"
- Ya tiene `:loading="loading"` y @click

```vue
<q-btn ... :loading="loading" @click="updateGuest()">
  Actualizar
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Actualizando...
  </template>
</q-btn>
```

### 14. components/reserves/deleteGuestModal.vue
- **q-btn línea 66** — botón "Eliminar"
- Ya tiene `:loading="loading"` y @click

```vue
<q-btn ... :loading="loading" @click="deleteGuest()">
  Eliminar
  <template class="on-left">
    <q-spinner-gears class="on-left" />
    Eliminando...
  </template>
</q-btn>
```

## Instrucciones

1. Agregar v-slot:loading a cada botón listado.
2. Cuidar de mantener los @click handlers originales.
3. Ejecutar npm run build.
