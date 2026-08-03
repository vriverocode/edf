// src/router/middlewares/role.js
import { useAuthStore } from '@/services/store/auth.services'

export default async function role(to, from, next) {
  const authStore = useAuthStore()

  // 1. Si la ruta no requiere validación de roles, la dejamos pasar inmediatamente
  if (!to.meta.roles) {
    return next()
  }

  // 2. Verificamos si el rol del usuario aún NO está en el state de Pinia
  if (!authStore.user?.rol?.name) {
    // Como no está, mandamos a llamar a la API utilizando tu acción existente
    // El 'await' pausa la navegación de la ruta hasta que la promesa se resuelva
    await authStore.currentUser()
  }

  // 3. Una vez que la API responde (o si ya lo teníamos de antes), obtenemos el rol
  // Nota: Mantuve tu fallback a 'Propietario' en caso de que la API falle o devuelva algo inesperado
  const userRole = authStore.user?.rol?.name || 'Propietario'

  // 4. Comprobamos si el rol tiene permiso para entrar a la ruta
  if (to.meta.roles && !to.meta.roles.includes(userRole.toLowerCase())) {
    console.error(`Rol detectado: ${userRole}`)
    console.warn(`Acceso denegado: El rol ${userRole} no tiene permisos.`)

    // Redirigimos según el rol: el trabajador va a su módulo de seguridad
    const fallback = userRole.toLowerCase() === 'trabajador' ? '/security/reserves/list' : '/dashboard'

    // Previene bucle infinito si el fallback vuelve a caer en este middleware
    if (to.fullPath === fallback) {
      return next()
    }

    return next(fallback)
  }

  // 5. Si todo está en orden, permitimos la navegación
  return next()
}