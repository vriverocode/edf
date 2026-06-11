import { defineStore } from 'pinia'
import ApiService from '@/services/axios'
import { CapacitorDownloader } from '@capgo/capacitor-downloader'
import { FileOpener } from '@capawesome-team/capacitor-file-opener'
import { Capacitor } from '@capacitor/core'
import { Filesystem, Directory } from '@capacitor/filesystem'

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
        const fileName = `pacifik_update_${this.versionInfo.version_code}.apk`

        // NUEVO: Le pedimos a Capacitor la ruta ABSOLUTA de la carpeta Data de la app
        const { uri: apkAbsolutePath } = await Filesystem.getUri({
          path: fileName,
          directory: Directory.Data,
        })
        await new Promise(async (resolve, reject) => {

          let progressListener = await CapacitorDownloader.addListener(
            'downloadProgress',
            ({ progress }) => {
              this.downloadProgress = progress // Va de 0 a 100
            }
          )
          let failListener = await CapacitorDownloader.addListener('downloadFailed', (error) => {
            reject(error)
          })
          
          let completedListener = await CapacitorDownloader.addListener(
            'downloadCompleted',
            async (result) => {
              if (result.id === 'update-download') {
                try {
                  // Ahora sí, el archivo existe y está completo. Le decimos al SO que lo abra.
                  await FileOpener.openFile({
                    path: apkAbsolutePath,
                    mimeType: 'application/vnd.android.package-archive',
                  })
                  resolve()
                } catch (e) {
                  reject(e)
                } finally {
                  // Limpiar eventos en memoria
                  progressListener.remove()
                  failListener.remove()
                  completedListener.remove()
                }
              }
            }
          )
          await CapacitorDownloader.download({
            url: this.versionInfo.download_url,
            id: 'update-download',
            destination: apkAbsolutePath,
          })
        })
        // Una vez descargado, decirle al Sistema Operativo que lo abra
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
