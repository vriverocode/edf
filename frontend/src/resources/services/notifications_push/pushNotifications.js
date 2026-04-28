import { FirebaseMessaging } from '@capacitor-firebase/messaging'
import { Capacitor } from '@capacitor/core'
import axios from 'axios'
import storage from '@/services/storage'

// Ajusta esta URL a tu API real de Laravel
const API_URL = 'https://website-c67adca2.sfr.hrf.mybluehost.me/api/token-movile'
export const PushNotificationsService = {
  async init() {
    // Solo ejecutar en el celular, no en el navegador web
    if (!Capacitor.isNativePlatform()) {
      console.log('No estamos en nativo, saltando push notifications.')
      return
    }

    console.log('Inicializando Push Notifications...')

    // 1. Pedir Permiso (Crítico para Android 13+)
    const result = await FirebaseMessaging.requestPermissions()

    if (result.receive === 'granted') {
      // 2. Registrar listeners para cuando llegue la notificación
      await this.addListeners()

      // 3. Obtener el Token FCM
      const { token } = await FirebaseMessaging.getToken()
      console.log('Mi Token FCM es:', token)

      // 4. Enviar al Backend Laravel
      await this.sendTokenToBackend(token)
    } else {
      console.log('Permiso de notificaciones denegado :(')
    }
  },

  async sendTokenToBackend(token) {
    try {
      // Asegúrate de enviar los headers de autenticación si usas Sanctum
      axios.defaults.headers.common['Authorization'] = `Bearer ${localStorage.getItem(
        'access_token'
      )}`
      console.log(localStorage.getItem('access_token'))
      await axios.post(API_URL, {
        token: token,
      })
      console.log('Token enviado a Laravel exitosamente')
    } catch (error) {
      console.error('Error enviando token a Laravel:', error)
    }
  },

  async addListeners() {
    // Cuando llega la notificación y la app está ABIERTA
    await FirebaseMessaging.addListener('notificationReceived', (event) => {
      console.log('Notificación recibida en primer plano:', event.notification)
    })

    // Cuando el usuario TOCA la notificación
    await FirebaseMessaging.addListener('notificationActionPerformed', (event) => {
      console.log('Click en notificación:', event.notification)

      // Lógica de redirección (Deep Linking)
      const data = event.notification.data
      if (data && data.url) {
        window.location.href = data.url // O usa router.push(data.url)
      }
    })
  },
}
