import { defineStore } from 'pinia';
import { ref } from 'vue';
import { CapacitorDownloader } from '@capgo/capacitor-downloader';
import { FileOpener } from '@capawesome-team/capacitor-file-opener';
import { Capacitor } from '@capacitor/core';
import axios from 'axios';

export const useConfigStore = defineStore('config', () => {
    const updateAvailable = ref(false);
    const versionInfo = ref(null);
    const downloadProgress = ref(0);
    const isDownloading = ref(false);
    const currentAppVersionCode = 1; // Aquí colocas el versionCode actual de tu app compilada

    // 1. Consultar a tu API (Laravel) si hay una nueva versión
    const checkForUpdates = async () => {
        try {
            // Ajusta la URL a la de tu API
            const { data } = await axios.get('https://tudominio.com/api/app-version');

            if (data && data.version_code > currentAppVersionCode) {
                versionInfo.value = data;
                updateAvailable.value = true;
            }
        } catch (error) {
            console.error("Error comprobando actualizaciones:", error);
        }
    };

    // 2. Ejecutar la descarga y lanzar el instalador
    const downloadAndInstall = async () => {
        if (!isNative()) return; // Solo ejecutar en Android

        isDownloading.value = true;
        downloadProgress.value = 0;

        try {
            // Escuchar el progreso para la barra de carga en el frontend
            CapacitorDownloader.addListener('downloadProgress', ({ progress }) => {
                downloadProgress.value = progress; // Va de 0 a 100
            });

            // Descargar el archivo directamente a la carpeta pública del dispositivo
            const downloadResult = await CapacitorDownloader.download({
                url: versionInfo.value.download_url,
                name: 'actualizacion.apk',
            });

            // Una vez descargado, decirle al Sistema Operativo que lo abra
            await FileOpener.openFile({
                path: downloadResult.path,
                mimeType: 'application/vnd.android.package-archive'
            });

        } catch (error) {
            console.error("Error en la descarga/instalación", error);
        } finally {
            isDownloading.value = false;
        }
    };

    const isNative = () => Capacitor.isNativePlatform();

    return {
        updateAvailable,
        versionInfo,
        downloadProgress,
        isDownloading,
        checkForUpdates,
        downloadAndInstall
    };
});