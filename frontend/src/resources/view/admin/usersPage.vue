<script setup>
import { storeToRefs } from 'pinia';
import { useAuthStore } from '@/services/store/auth.services';
import bg from '@/assets/img/util/bg3.webp'
import iconsApp from '@/assets/icons/index'
import { useRouter } from 'vue-router';
import departamentos from '@/assets/img/menu/departamentos.png'
import usuarios from '@/assets/img/menu/usuarios.png'
import visitas from '@/assets/img/menu/visitas.png'


const { user } = storeToRefs(useAuthStore())
const router = useRouter()
const menu = [
  {
    title: 'Usuarios',
    icon: usuarios,
    subtitle: 'Informacion de usuarios',
    link: '/admin/users/list',
  },
  {
    title: 'Departamentos',
    icon: departamentos,
    subtitle: 'Gestión de departamentos',
    link: '/admin/department/list',
  },
  // {
  //   title: 'Visitas',
  //   icon: visitas,
  //   subtitle: 'Lista de visitas',
  //   link: '/users',
  // },

];

const goTo = (url) => {
  router.push(url)
}
</script>
<template>
  <div class="h-full w-full px-2">
    <div class="row md:pt-10 pt-5  md:px-16">
      <div class="col-md-3 md:px- col-6 px-10 my-3" v-for="(item, key) in menu" :key="key" @click="goTo(item.link)">
        <div class="boxItem ">
          <div class="flex justify-center items-center h-full w-full p-1">
            <!-- <img :src="items.icon" class="w-full md:w-auto h-full" /> -->
            <!-- <div v-html="items.icon" class="flex justify-center mt-0" /> -->
            <img :src="item.icon" class="md:w-auto "
            :class="{ 'h-3/5': item.icon.includes('default-dash'), 'h-3/5': !item.icon.includes('default-dash') }" />
        
          </div>
        </div>
        <div class="text-center mt-2  text-title-squad text-ellipsis ellipsis ">
          {{ item.title }}
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped></style>