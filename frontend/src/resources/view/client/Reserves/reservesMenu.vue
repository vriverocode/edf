<script setup>
import { storeToRefs } from 'pinia'
import { useAuthStore } from '@/services/store/auth.services'
import { useRouter } from 'vue-router'
import { computed } from 'vue'
import booking from '@/assets/img/menu/reservas.png'
import mis_reservas from '@/assets/img/menu/mis_reservas.svg'

const { user } = storeToRefs(useAuthStore())
const router = useRouter()

const menu = [
  {
    title: 'Mis reservas',
    link: '/client/reserves/list',
    icon: mis_reservas,
    roles: [2, 3, 4, 5]
  },
  {
    title: 'Historial de reservas',
    link: '/client/reserves/history',
    icon: booking,
    roles: [2, 3, 4, 5]
  }
]

const menuByRol = computed(() => {
  const rol = user.value?.rol_id
  return menu.filter(item => !item.roles || item.roles.includes(rol))
})

const goTo = (url) => {
  router.push(url)
}
</script>

<template>
  <div class="h-full w-full px-2">
    <div class="row md:pt-10 pt-2 md:px-28">
       <div class="col-md-3 col-6 px-7 my-3" v-for="(item, key) in menuByRol" :key="key">
        <div class="px-3">
          <div class="boxItem " @click="goTo(item.link)">
            <div class="flex justify-center items-center h-full w-full p-1">
              <img :src="item.icon" class="w-auto md:w-auto " 
                :class="{ 'h-3/5': item.title.includes('Historial'), 'h-full': !item.title.includes('Historial') }" />
            </div>
          </div>
        </div>
        <div class="text-center mt-2  text-title-squad">
          {{ item.title }}
        </div>
      </div>
    </div>
  </div>
</template>
