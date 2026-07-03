<script setup>
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/services/store/auth.services';
import iconsApp from '@/assets/icons/index'
import { useRouter } from 'vue-router';
import { computed } from 'vue';

const { user } = storeToRefs(useAuthStore())
const router = useRouter()
const menu = [
  {
    title: 'Terminos y condiciones',
    link: 'https://www.edificiopacifik.com/terminos_y_condiciones',
    icon: iconsApp.terms,
    roles: [],
    external: true
  },
  {
    title: 'Reclamos',
    icon: iconsApp.claims,
    link: '/client/claims/add',
    roles: [],
    external: false
  },


];
const menuByRol = computed(() => {
  const rol = user.value?.rol_id
  return menu.filter(item => {
    if (!item.roles || item.roles.length === 0) {
      return true
    }
    return item.roles.includes(rol)
  })
})
const goTo = (url, external = false) => {
  if (external) {
    var link = document.createElement('a');
    link.href = url;
    link.target = '_blank';

    link.rel = 'noopener noreferrer';
    link.click();
    return
  }
  router.push(url)
}
</script>
<template>
  <div class="h-full w-full px-2">
    <div class="row md:pt-10 pt-2  md:px-28">
      <div class="col-md-3   col-6 px-7 my-3" v-for="(items, key) in menuByRol" :key="key">
        <div class="boxItem" @click="goTo(items.link, items.external)">
          <div class="flex justify-center items-center h-full w-full p-1">
            <div v-html="items.icon" class="flex justify-center mt-0" />
          </div>
        </div>
        <div class="text-center mt-2  text-title-squad text-ellipsis ellipsis ">
          {{ items.title }}
        </div>
      </div>

    </div>
  </div>
</template>

<style lang="scss" scoped></style>