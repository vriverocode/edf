<script setup>
import { ref, onMounted } from 'vue'
import { useConfigStore } from '@/services/store/config.store'
import { storeToRefs } from 'pinia'
import { useRoute } from 'vue-router'
import iconsApp from '@/assets/icons/index'

const DOWNLOAD_URL = 'https://github.com/vriverocode/edf/releases/download/apk/pacifik.apk'

const route = useRoute()
const configStore = useConfigStore()
const { isDownloading, downloadProgress } = storeToRefs(configStore)

// Permite sobrescribir la URL desde query param ?url=... si en el futuro se pasa dinámicamente
const apkUrl = ref(route.query.url || DOWNLOAD_URL)

const handleDownload = async () => {
    await configStore.downloadFromUrl(apkUrl.value)
}
</script>

<template>
    <q-page class="app-update-page column flex-center q-pa-lg">
        <div class="update-card column flex-center text-center">
            <!-- Icono animado -->
            <div class="icon-wrapper q-mb-lg">
                <div v-html="iconsApp.updateSys" class="update-icon" />
            </div>

            <!-- Texto principal -->
            <h1 class="text-h5 text-weight-bold q-mb-sm update-title">
                ¡Nueva versión disponible!
            </h1>
            <p class="text-body1 text-grey-7 q-mb-xl update-desc">
                Descarga e instala la última versión de <strong>PACIFIK</strong> para disfrutar de
                las últimas mejoras y correcciones.
            </p>

            <!-- Progreso de descarga -->
            <div v-if="isDownloading" class="full-width q-px-md q-mb-lg">
                <p class="text-body2 text-grey-6 q-mb-sm">
                    Descargando... {{ Math.round(downloadProgress) }}%
                </p>
                <q-linear-progress
                    :value="downloadProgress / 100"
                    color="primary"
                    track-color="grey-3"
                    rounded
                    size="10px"
                    class="q-mt-xs"
                />
            </div>

            <!-- Botón de descarga -->
            <q-btn
                v-if="!isDownloading"
                color="primary"
                text-color="white"
                no-caps
                rounded
                size="lg"
                unelevated
                class="download-btn q-px-xl"
                @click="handleDownload"
            >
                <q-icon name="eva-download-outline" class="q-mr-sm" size="22px" />
                Descargar e Instalar
            </q-btn>

            <p class="text-caption text-grey-5 q-mt-lg">
                La app se reiniciará automáticamente una vez instalada.
            </p>
        </div>
    </q-page>
</template>

<style scoped>
.app-update-page {
    min-height: 100vh;
    background: #f9f9f9;
}

.update-card {
    max-width: 380px;
    width: 100%;
    background: #ffffff;
    border-radius: 24px;
    padding: 40px 28px;
    box-shadow: 0 8px 40px rgba(0, 0, 0, 0.08);
}

.icon-wrapper {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    background: linear-gradient(135deg, #e8f4ff, #d6eaff);
    display: flex;
    align-items: center;
    justify-content: center;
}

.update-icon {
    width: 52px;
    height: 52px;
}

.update-title {
    color: #1a1a2e;
    line-height: 1.3;
}

.update-desc {
    line-height: 1.6;
    max-width: 300px;
}

.download-btn {
    font-size: 16px;
    font-weight: 600;
    letter-spacing: 0.3px;
    min-width: 220px;
}
</style>
