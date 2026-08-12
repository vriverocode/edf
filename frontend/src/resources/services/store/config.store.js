import { defineStore } from 'pinia'
import ApiService from '@/services/axios'
import { FileOpener } from '@capawesome-team/capacitor-file-opener'
import { Capacitor } from '@capacitor/core'
import { Filesystem, Directory } from '@capacitor/filesystem'

export const useConfigStore = defineStore('config', {
  state: () => ({
    updateAvailable: false,
    versionInfo: null,
    downloadProgress: 0,
    isDownloading: false,
    currentAppVersionCode: 29, // Aquí colocas el versionCode actual de tu app compilada
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
            console.error(response)
            reject(response?.data?.error || 'Error comprobando actualizaciones')
          })
      })
    },

    // 2. Ejecutar la descarga y lanzar el instalador
    async downloadAndInstall() {
      if (!this.isNative()) return

      this.isDownloading = true
      this.downloadProgress = 50 // Progreso simulado/indeterminado

      try {
        const fileName = `pacifik_update_${this.versionInfo.version_code}.apk`

        // 1. Descargar en la carpeta CACHÉ (Carpeta pública temporal)
        const downloadResult = await Filesystem.downloadFile({
          url: this.versionInfo.download_url,
          path: fileName,
          directory: Directory.Cache, // <--- ESTE ES EL CAMBIO MÁGICO
        })

        this.downloadProgress = 100

        // 2. Abrir el archivo
        await FileOpener.openFile({
          path: downloadResult.path,
          mimeType: 'application/vnd.android.package-archive',
        })
      } catch (error) {
        console.error('Error en la descarga/instalación:', error)
      } finally {
        this.isDownloading = false
      }
    },

    isNative() {
      return Capacitor.isNativePlatform()
    },
  },
})
