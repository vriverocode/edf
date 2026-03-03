// src/router/middlewares/role.js

// 1. Importas tu store de Pinia
import { useAuthStore } from '@/services/store/auth.services' // Ajusta la ruta a donde tengas tu store

export default function role(to, from, next) {
  // 2. IMPORTANTE: Instancias el store DENTRO de la función del middleware
  const authStore = useAuthStore()

  // 3. Obtienes el rol directamente de tu estado en Pinia
  // (Ajusta 'userRole' al nombre exacto de la variable en tu state o getter)
  const userRole = authStore.user.rol_id
  console.log(authStore.user.rol.name)

  // 4. Verificas si la ruta tiene restricciones de roles
  if (to.meta.roles && !to.meta.roles.includes(userRole)) {
    console.warn(`Acceso denegado: El rol ${userRole} no tiene permisos.`)

    // Rediriges a una ruta segura (puedes mandarlo al home, a un 403, etc.)
    return next({ name: 'dashboardAdmin' })
  }

  // 5. Si tiene el rol correcto, permite el paso
  return next()
}
