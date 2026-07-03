<script setup>
import { inject, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useUserStore } from '@/services/store/users.store';
import iconsApp from '@/assets/icons/index'
import deleteUserModal from '@//components/admin/deleteUserModal.vue';
const userStore = useUserStore()
const filterRol = ref(2)
const page = ref(1)
const search = ref('')
const ready = ref(false)
const materialIcons = inject('materialIcons')
const modal = ref('')

const router = useRouter()

const goTo = (url) => {
  router.push(url)
}

const users = ref([])
const selectedUser = ref({})

const openModal = (user, type) => {
  selectedUser.value = user
  setTimeout(() => {
    modal.value = type
  }, 50);
}

const getUsers = () => {

  ready.value = false;

  const data = {
    page: page.value,
    search: search.value,
    rol: filterRol.value
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
const optionsFilterRol = [
  {
    name: 'Admin',
    value: 1
  },
  {
    name: 'Propietarios',
    value: 2
  },
  {
    name: 'Inquilinos',
    value: 3
  },
  {
    name: 'Familiar',
    value: 4
  },
  {
    name: 'Airbnb',
    value: 5
  },
  {
    name: 'Trabajadores',
    value: 6
  },
  {
    name: 'Propietarios parciales',
    value: 7
  },
]
onMounted(() => {
  getUsers()
})
</script>

<template>
  <div class="h-full" style="overflow: auto;">
    <div class="w-full px-4">
      <q-select
        v-model="filterRol"
        :options="optionsFilterRol"
        option-label="name"
        option-value="value"
        emit-value
        map-options dense borderless color="primary" 
        class="form_userOptionSelect"
        @update:model-value="getUsers"
      />
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
          class="md:py-4 py-3 mb-5 userListContainer row items-center">
          <div class="flex items-center pb-3 pt-2 pl-2 md:pl-5 col-12 no-wrap">
            <div
              style="height: 2.8rem; width: 2.8rem; background: #b5b5b5; border-radius: 0.5rem; font-size: 2rem; font-weight: bold;"
              class="flex flex-center text-white">
              {{ user.name.charAt(0).toUpperCase() }}
            </div>
            <div class="ml-2">
              <div class="text-subtitle1  text-bold text-black" style="line-height:1.7;">
                {{ user.name }}
              </div>
              <div class="flex items-center wrap " v-if="user.rol_id == 2 || user.rol_id == 7">
                <div class="text-body2 text-grey-6 text-uppercase">
                  #{{ user.units.length > 0 
                  ? user.formatted_units 
                  : 'Apt. no asignado' }}
                </div>
                <div class="text-caption text-grey-6 ml-1">
                  ({{ user.rol.name }})
                </div>
              </div>
            </div>
          </div>
          <template v-if="user.status == 2">
            <div style="position: absolute; top: 0.2rem; right: 1rem;">
              <div class="pt-1" v-html="iconsApp.cancelHouse" />
              <q-tooltip transition-show="flip-right" transition-hide="flip-left" class="bg-black text-body2 px-2">
                Moroso
              </q-tooltip>
            </div>
          </template>
          <div class="flex justify-end px-2 w-full pt-3 col-12" style="border-top: 1px solid lightgrey;">
            <div v-if="user.rol_id == 2 || user.rol_id == 7">
              <q-btn :icon="materialIcons.outlinedAddHomeWork" class="mx-1" flat color="yellow-9" size="0.9rem"
                @click="goTo('/admin/users/assign-property/' + user.id)">
                <q-tooltip transition-show="flip-right" transition-hide="flip-left" :class="'bg-black text-body2 px-2'">
                  Agregar unidad
                </q-tooltip>
              </q-btn>
            </div>
            <div>
              <q-btn icon="eva-settings-outline" class="mx-1" color="primary" flat size="0.9rem">
                <q-tooltip transition-show="flip-right" transition-hide="flip-left" :class="'bg-black text-body2 px-2'">
                  Editar usuario
                </q-tooltip>
              </q-btn>
            </div>
            <div>
              <q-btn :icon="materialIcons.outlinedPaid" class="mx-1" color="amber-6" flat size="0.9rem" v-if="user.rol_id != 1 && user.rol_id != 7 && user.rol_id != 6">
                <q-tooltip transition-show="flip-right" transition-hide="flip-left" class="bg-black text-body2 px-2">
                  Ver pagos
                </q-tooltip>
              </q-btn>
            </div>
            <div>
              <q-btn icon="eva-trash-2-outline" class="mx-1" color="negative" flat size="0.9rem" @click="openModal(user, 'delete')">
                <q-tooltip transition-show="flip-right" transition-hide="flip-left" class="bg-black text-body2 px-2">
                  Borrar usuario
                </q-tooltip>
              </q-btn>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div v-if="Object.values(selectedUser).length > 0">
      <deleteUserModal :dialog="(modal == 'delete')" :user="selectedUser" @close-modal="modal = ''" @update-list="getUsers()" />
    </div>
    
  </div>
</template>
<style lang="scss">
.userListContainer {
  overflow: hidden;
  position: relative;
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
.form_userOptionSelect{
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
}
@media (max-width: 780px) {
  .createButton {
    width: 100%;
  }
}



@media (max-width: 780px) {
  .form_userOptionSelect{
    & .q-field__inner {
      padding: 0.1rem 1rem;
    }
  }
}

</style>
