# TASK_000 — Crear composable useButtonLoader

## Objetivo
Crear un composable reutilizable que maneje el estado de carga de cualquier botón asíncrono en la app.

## Archivo a crear

**Ruta:** `frontend/src/resources/composables/useButtonLoader.js`

### Contenido

```js
import { ref } from 'vue'

export function useButtonLoader() {
  const loading = ref(false)

  async function withLoading(asyncFn) {
    loading.value = true
    try {
      return await asyncFn()
    } finally {
      loading.value = false
    }
  }

  return { loading, withLoading }
}
```

### Uso (integrado en la tarea correspondiente)

```js
import { useButtonLoader } from '@/composables/useButtonLoader'
const { loading, withLoading } = useButtonLoader()
```

```vue
<q-btn :loading="loading" @click="withLoading(handlerAsync)" />

<q-btn :loading="loading" type="submit">
  Texto
  <template v-slot:loading>
    <q-spinner-gears class="on-left" />
    Enviando...
  </template>
</q-btn>
```
