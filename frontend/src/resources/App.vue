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