<script setup>
import { useQuasar } from 'quasar';
import { onMounted, onUnmounted } from 'vue';
import { SplashScreen } from '@capacitor/splash-screen';
import { App } from '@capacitor/app';
import { ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { StatusBar, Style } from '@capacitor/status-bar';
import { Capacitor } from '@capacitor/core';
const showSplash = async () => {
  await SplashScreen.show({
    autoHide: true,
    showDuration: 2000,
  })
}
const $q = useQuasar()
const router = useRouter()
const route = useRoute();
const transitionName = ref('slide-up'); // Transición por defecto
const setupStatusBar2 = async () => {
  // Check if the app is running on iOS or Android
  if (Capacitor.isNativePlatform()) {
    try {
      // Your existing StatusBar code goes here
      // Example: await StatusBar.setStyle({ style: 'DARK' });
    } catch (error) {
      console.error('Error configuring StatusBar:', error);
    }
  } else {
    // Optional: Log that it's running on the web
    console.log('Running on web: StatusBar plugin ignored.');
  }
};
const setupStatusBar = async () => {
    await StatusBar.setStyle({ style: Style.Light }); // O Dark
    await StatusBar.setBackgroundColor({ color: '#0e344c' }); // Color de tu app
  };
onMounted(async () => {
  $q.addressbarColor.set('#0e344c');
  
  showSplash();
  setupStatusBar()
  setupStatusBar2
  await App.addListener('backButton', ({ canGoBack }) => {
    if (canGoBack) {
      // Si hay historial en el stack de navegación, retrocedemos
      router.go(-1);
    } else {
      // Si no hay a dónde ir atrás, cerramos la app
      App.exitApp();
    }
  });
});

onUnmounted(() => {
  // Es buena práctica eliminar todos los listeners al destruir el componente
  App.removeAllListeners();
});




watch(
  () => route.meta.depth,
  (toDepth, fromDepth) => {
    // Si vamos a una ruta más profunda (adelante), la hoja sube.
    // Si vamos a una ruta menos profunda (atrás), la hoja cae.
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
  </q-layout>

</template>
<style>
.appMobile {
  position: relative;
  width: 100%;
  height: 100vh;
  /* Mejor que 100% para cubrir toda la pantalla del móvil */
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