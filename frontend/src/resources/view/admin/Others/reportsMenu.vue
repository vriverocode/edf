<script setup>
import iconsApp from '@/assets/icons/index';
import { useUserStore } from '@/services/store/users.store';
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import comunArea from '@/assets/img/menu/areas-comunes.png'
import booking from '@/assets/img/menu/reservas.png'


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
    title: 'Reporte de reservas',
    icon: comunArea,
    subtitle: 'Reservas con filtros y exportación',
    link: '/admin/reports/bookings',
  },
  // {
  //   title: 'Reportes de eventos',
  //   icon: booking,
  //   subtitle: 'Informacion de reservas',
  //   link: '/reserves',
  // },
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
    <div class="row md:pt-10 pt-2  md:px-28">
      <div class="col-md-3  col-6 px-7 my-3" v-for="(item, key) in menu" :key="key">
        <div class="boxItem" @click="goTo(item.link)">
          <div class="flex justify-center items-center h-full w-full p-1">
            <div class="flex justify-center items-center h-full w-full p-1">
              <!-- <div v-html="item.icon" class="flex justify-center mt-0" v-if="user.rol_id == 6 && (item.title == 'Airbnb' || item.title == 'Visitas')" /> -->
              <img :src="item.icon" class="md:w-auto "
                :class="{ 'h-3/5': item.icon.includes('default-dash'), 'h-3/5': !item.icon.includes('default-dash') }" />
            
            </div>
          </div>
        </div>
        <div class="text-center mt-2  text-title-squad text-ellipsis ellipsis ">
          {{ item.title }}
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