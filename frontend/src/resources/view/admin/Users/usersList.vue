<script setup>
import { inject, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useUserStore } from '@/services/store/users.store';
import iconsApp from '@/assets/icons/index'

const userStore = useUserStore()
const tabActive = ref('users')
const page = ref(1)
const search = ref('')
const ready = ref(false)
const materialIcons = inject('materialIcons')



const changeTab = (tab) => {
  tabActive.value = tab
  getUsers()
}
const router = useRouter()

const goTo = (url) => {
  router.push(url)
}

const users = ref([])

const getUsers = () => {

  ready.value = false;

  const data = {
    page: page.value,
    search: search.value,
    rol: tabActive.value == 'users' ? 1 : 2
  }
  userStore.getUsers(data)
    .then((response) => {
      if (response.code !== 200) throw response
      users.value = response.data;
      setTimeout(() => {
        ready.value = true;
      }, 1000);
    })
    .catch(() => {
    })
}

onMounted(() => {
  getUsers()
})
</script>

<template>
  <div class="h-full" style="overflow: auto;">
    <div class="flex mt-5 justify-between  md:mx-auto items-center md:w-2/6 bg-primary mx-4 "
      style=" height: 2.8rem; overflow: hidden;border-radius: 0.7rem; ">
      <div class="text-subtitle1 text-bold text-white text-center bg-primary tabItem leftItem"
        @click="changeTab('users')" :class="{ 'active': tabActive == 'users' }" style="width: calc(50% - 1px);">Usuarios
      </div>
      <div style="height: 100%; width: 2px; background: lightcyan; width: 2px;" />
      <div class="text-subtitle1 text-bold text-white text-center bg-primary tabItem rightItem"
        @click="changeTab('admin')" :class="{ 'active': tabActive == 'admin' }" style="width: calc(50% - 1px);">
        Administradores</div>
    </div>
    <div class="px-4 md:px-0 md:flex md:mx-auto md:justify-end md:w-5/6">
      <q-btn color="primary" unelevated class="w-full mt-5 md:mx-5 createButton " style="border-radius: 0.5rem;"
        @click="goTo('/admin/users/form/add')">
        <div class="flex items-center py-1">
          <q-icon name="eva-plus-outline" />
          <div class="q-pt-xs text-bold pl-1">
            Crear nuevo usuario
          </div>
        </div>
      </q-btn>
    </div>
    <div class="mt-4 md:mt-8">
      <div class="px-4 md:mx-24 md:pr-12">
        <div v-for="user in users" :key="user.id"
          class="md:py-4 py-3 mb-5 userListContainer flex items-center justify-between">
          <div class="flex items-center pb-3 pl-2 md:pl-5 ">
            <div
              style="height: 2.8rem; width: 2.8rem; background: #b5b5b5; border-radius: 0.5rem; font-size: 2rem; font-weight: bold;"
              class="flex flex-center text-white">
              {{ user.name.charAt(0).toUpperCase() }}
            </div>
            <div class="ml-2">
              <div class="text-subtitle1  text-bold text-black" style="line-height:1.7;">
                {{ user.name }}
              </div>
              <div class="flex items-center">
                <div class="text-body2 text-grey-6 ">#{{ user.apartaments.length > 0 ? user.apartaments[0].number :
                  'Apt. no asignado' }}</div>
                <div class="text-caption text-grey-6 ml-1">
                  ({{ user.rol.name }})
                </div>
              </div>
            </div>
          </div>
          <div class="flex justify-end  items-center pb-3 pt-1 pr-2 md:pr-5 ">
            <div>
              <q-btn :icon="materialIcons.outlinedAddHomeWork" class="mx-1" flat color="yellow-9" round size="0.9rem"
                @click="goTo('/admin/users/assign-property/' + user.id)">

              </q-btn>
            </div>
            <template v-if="user.status == 2">

              <div>
                <div class="pt-1" v-html="iconsApp.cancelHouse" />
                <q-tooltip transition-show="flip-right" transition-hide="flip-left" class="bg-black text-body2 px-2">
                  Moroso
                </q-tooltip>
              </div>

            </template>
          </div>
          <div class="flex justify-end px-2 w-full pt-3 " style="border-top: 1px solid lightgrey;">
            <div>
              <q-btn icon="eva-settings-outline" class="mx-1" color="primary" flat size="0.9rem">
                <q-tooltip transition-show="flip-right" transition-hide="flip-left" :class="'bg-black text-body2 px-2'">
                  Editar usuario

                </q-tooltip>
              </q-btn>
            </div>
            <div>
              <q-btn :icon="materialIcons.outlinedEvent" class="mx-1" color="light-green-9" flat size="0.9rem">
                <q-tooltip transition-show="flip-right" transition-hide="flip-left" class="bg-black text-body2 px-2">
                  Ver reservas
                </q-tooltip>
              </q-btn>
            </div>
            <div>
              <q-btn :icon="materialIcons.outlinedPaid" class="mx-1" color="amber-6" flat size="0.9rem">
                <q-tooltip transition-show="flip-right" transition-hide="flip-left" class="bg-black text-body2 px-2">
                  Ver pagos
                </q-tooltip>
              </q-btn>
            </div>
            <div>
              <q-btn icon="eva-trash-2-outline" class="mx-1" color="negative" flat size="0.9rem">
                <q-tooltip transition-show="flip-right" transition-hide="flip-left" class="bg-black text-body2 px-2">
                  Borrar usuario
                </q-tooltip>
              </q-btn>

            </div>

          </div>
        </div>
      </div>
    </div>
  </div>
</template>
<style lang="scss">
.userListContainer {
  overflow: hidden;
  border-radius: .5rem;
  box-shadow: 0px 2px 6px 0px rgb(199, 199, 199);
}

.createButton {
  width: auto;
}

.tabItem {
  opacity: 0.5;
  cursor: pointer;
  height: 100%;
  display: flex;
  justify-content: center;
  align-items: center;
  transition: all 0.2s ease-out;

  &:hover {
    background: #279edb !important;
  }

  &.active {
    opacity: 1;
  }

  &.leftItem {
    border-top-left-radius: 0.7rem;
    border-bottom-left-radius: 0.7rem;

  }

  &.rightItem {
    border-top-right-radius: 0.7rem;
    border-bottom-right-radius: 0.7rem;

  }
}

@media (max-width: 780px) {
  .createButton {
    width: 100%;
  }
}
</style>
