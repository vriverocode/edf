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
</style>