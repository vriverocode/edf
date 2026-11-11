<script setup>
// import bg from '@/assest/'
</script>
<template>
  <section class="h-full authContainer">
    
    <router-view v-slot="{ Component }">
      <component :is="Component" />
    </router-view>
  </section>
</template>
<style>
.authContainer {
  height: 100%;
  overflow: hidden;
  background: #f7f1e4;
}
</style>