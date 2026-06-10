<script setup>
import { useQuasar } from 'quasar';
import { onMounted, onUnmounted } from 'vue';
import { SplashScreen } from '@capacitor/splash-screen';
import { App } from '@capacitor/app';
import { ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { StatusBar, Style } from '@capacitor/status-bar';
import { Capacitor } from '@capacitor/core';
import { useConfigStore } from './services/store/config.store';
import { storeToRefs } from 'pinia';
import iconsApp from '@/assets/icons/index'

const showSplash = async () => {
  await SplashScreen.show({
    autoHide: true,
    showDuration: 2000,
  })
}

const updateStore = useConfigStore();
const { updateAvailable, isDownloading, downloadProgress, versionInfo } = storeToRefs(updateStore);
const $q = useQuasar()
const router = useRouter()
const route = useRoute();
const transitionName = ref('slide-up');
const setupStatusBar2 = async () => {
  if (Capacitor.isNativePlatform()) {
    try {
      await StatusBar.setStyle({ style: Style.Light });
      await StatusBar.setBackgroundColor({ color: '#0e344c' });
    } catch (error) {
      console.error('Error configuring StatusBar:', error);
    }
  } else {
    console.log('Running on web: StatusBar plugin ignored.');
  }
};
const setupStatusBar = async () => {
  await StatusBar.setStyle({ style: Style.Light });
  await StatusBar.setBackgroundColor({ color: '#0e344c' });
};
onMounted(async () => {
  $q.addressbarColor.set('#0e344c');

  showSplash();
  setupStatusBar2
  await updateStore.checkForUpdates();
  await App.addListener('backButton', ({ canGoBack }) => {
    if (canGoBack) {
      router.go(-1);
    } else {
      App.exitApp();
    }
  });
});

onUnmounted(() => {
  App.removeAllListeners();
});




watch(
  () => route.meta.depth,
  (toDepth, fromDepth) => {
    transitionName.value = toDepth > fromDepth ? 'slide-up' : 'slide-down';
  }
);
</script>
<template>
  <q-layout view="hHh lpR fFf" class="app-container">
    <router-view class="appMobile " v-slot="{ Component }">
      <transition :name="transitionName">
        <component :is="Component" class="pageComponent" />
      </transition>
    </router-view>
    <q-dialog v-model="updateAvailable" persistent maximized transition-show="slide-up" transition-hide="slide-down">
      <q-card class="bg-white  column flex-center">
        <q-card-section class="text-center">
          <div v-html="iconsApp.updateSys"/>
          <h4 class="q-mt-md text-subtitle1">¡Nueva Versión Disponible!</h4>
          <p class="text-subtitle1">Versión {{ versionInfo?.version }}</p>
          <p class="q-px-lg">{{ versionInfo?.release_notes }}</p>
        </q-card-section>

        <q-card-actions align="center" class="q-mt-xl column">
          <div v-if="isDownloading" class="full-width q-px-xl text-center">
            <p>Descargando... {{ Math.round(downloadProgress) }}%</p>
            <q-linear-progress :value="downloadProgress / 100" color="warning" class="q-mt-sm" />
          </div>

          <q-btn v-else color="primary" text-color="white" no-caps  size="lg" rounded
            @click="updateStore.downloadAndInstall()" >
            <div class="px-5">
              Descargar e Instalar
            </div>
          </q-btn>
        </q-card-actions>
      </q-card>
    </q-dialog>
  </q-layout>

</template>
<style>
.appMobile {
  position: relative;
  width: 100%;
  height: 100vh;
  overflow: hidden;
  margin: auto;
}


@media (max-width: 780px) {
  .appMobile {
    width: 100%;
    border-radius: 0px;

  }
}
</style>