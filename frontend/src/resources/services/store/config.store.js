import { defineStore } from 'pinia'
import ApiService from '@/services/axios'
import { CapacitorDownloader } from '@capgo/capacitor-downloader'
import { FileOpener } from '@capawesome-team/capacitor-file-opener'
import { Capacitor } from '@capacitor/core'

export const useConfigStore = defineStore('config', {
  state: () => ({
    updateAvailable: false,
    versionInfo: null,
    downloadProgress: 0,
    isDownloading: false,
    currentAppVersionCode: 1, // Aquí colocas el versionCode actual de tu app compilada
  }),
  actions: {
    // 1. Consultar a tu API (Laravel) si hay una nueva versión
    async checkForUpdates() {
      return await new Promise((resolve, reject) => {
        // Descomenta la siguiente línea si esta ruta requiere token
        // if (!ApiService.getToken()) throw ''

        // ApiService.setHeader() // Descomenta si necesitas enviar el token en la cabecera
        ApiService.get('/api/app-version') // Ajusta la URL según tu backend
          .then(({ data }) => {
            // Asumiendo que tu backend devuelve { code: 200, data: {...} } como en los otros stores
            // Ajusta esta condición si la respuesta es directa
            const versionData = data.data || data

            if (versionData && versionData.version_code > this.currentAppVersionCode) {
              this.versionInfo = versionData
              this.updateAvailable = true
            }
            resolve(data)
          })
          .catch(({ response }) => {
            console.log(response)
            reject(response?.data?.error || 'Error comprobando actualizaciones')
          })
      })
    },

    // 2. Ejecutar la descarga y lanzar el instalador
    async downloadAndInstall() {
      if (!this.isNative()) return // Solo ejecutar en Android

      this.isDownloading = true
      this.downloadProgress = 0

      try {
        // Escuchar el progreso para la barra de carga en el frontend
        CapacitorDownloader.addListener('downloadProgress', ({ progress }) => {
          this.downloadProgress = progress // Va de 0 a 100
        })

        // Descargar el archivo directamente a la carpeta pública del dispositivo
        const downloadResult = await CapacitorDownloader.download({
          url: this.versionInfo.download_url,
          name: 'actualizacion.apk',
        })

        // Una vez descargado, decirle al Sistema Operativo que lo abra
        await FileOpener.openFile({
          path: downloadResult.path,
          mimeType: 'application/vnd.android.package-archive',
        })
      } catch (error) {
        console.error('Error en la descarga/instalación', error)
      } finally {
        this.isDownloading = false
      }
    },

    isNative() {
      return Capacitor.isNativePlatform()
    },
  },
})
