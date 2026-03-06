<script setup>
import iconsApp from '@/assets/icons/index';
import { useUserStore } from '@/services/store/users.store';
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

const pendindgsCount = ref([]);

const getPendingCount = () => {
  useUserStore().getAllPendingsForAdmin()
    .then((response) => {
      pendindgsCount.value = response.data
    })
    .catch((error) => {
      console.log(error)
    })
}
const router = useRouter()
const menu = [

  {
    title: 'Areas comunes',
    icon: iconsApp.atraction,
    subtitle: 'Gestiona las areas comunes',
    link: '/admin/comun-area/list',
  },
  {
    title: 'Reservas',
    icon: iconsApp.reserve,
    subtitle: 'Informacion de reservas',
    link: '/reserves',
  },
  {
    title: 'Noticias',
    icon: iconsApp.news,
    subtitle: 'Envia información sobre: eventos, servicio, etc',
    link: '/admin/notices',
  },
  {
    title: 'Eventos',
    icon: iconsApp.events,
    subtitle: 'Modulo de gestion de eventos',
    link: '/admin/events',
  },
];

const goTo = (url) => {
  router.push(url)
}

onMounted(() => {
  getPendingCount()
})
</script>nde
<template>
  <div class="h-full w-full px-2">
    <div class="row md:pt-10 pt-2  md:px-20">
      <div class="col-md-3 md:px-20 col-6 px-7 my-3" v-for="(items, key) in menu" :key="key">
        <div class="boxItem" @click="goTo(items.link)">
          <div class="flex justify-center items-center h-full w-full p-1">
            <!-- <img :src="items.icon" class="w-full md:w-auto h-full" /> -->
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

<style lang="scss">
.badgeCountReserve {
  height: 1.5rem;
  width: 1.5rem;
  border-radius: 0.4rem;
  background: red;
  position: absolute;
  color: white;
  font-size: 0.9rem;
  font-weight: 500;
  top: -10px;
  right: -5px;

}
</style>