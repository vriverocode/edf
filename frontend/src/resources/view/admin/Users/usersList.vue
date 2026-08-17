<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Notify } from 'quasar';
import { useUserStore } from '@/services/store/users.store';
import iconsApp from '@/assets/icons/index'
import deleteUserModal from '@//components/admin/deleteUserModal.vue';
import userAvailableAreasStep from '@/components/admin/userAvailableAreasStep.vue';
import { usePaginationState } from '@/composables/usePaginationState';
const userStore = useUserStore()
const filterRol = ref(2)
const lastPage = ref(1)
const search = ref('')
const ready = ref(false)
const modal = ref('')

const router = useRouter()

const { page, restoreFromQuery, syncToUrl, onPageChange } = usePaginationState({
  filters: [
    { key: 'rol', ref: filterRol, parse: Number },
    { key: 'search', ref: search }
  ]
})

const goTo = (url) => {
  router.push(url)
}

const users = ref([])
const selectedUser = ref({})
const areasUser = ref({})
const areasDialog = ref(false)
const areasLoading = ref(false)
const selectedAreas = ref([])

const openAreas = (user) => {
  selectedAreas.value = []
  areasUser.value = user
  areasDialog.value = true
}

const saveAreas = () => {
  areasLoading.value = true
  userStore.setAvailableComunaAreas(areasUser.value.id, selectedAreas.value)
    .then(() => {
      showNotify('positive', 'Áreas comunes actualizadas correctamente')
      areasDialog.value = false
    })
    .catch((error) => {
      showNotify('negative', error || 'Error al actualizar las áreas comunes')
    })
    .finally(() => {
      areasLoading.value = false
    })
}

const showNotify = (type, text) => {
  Notify.create({ color: type, message: text, timeout: 2000 })
}

const openModal = (user, type) => {
  selectedUser.value = user
  setTimeout(() => {
    modal.value = type
  }, 50)
}

const getUsers = (resetPage = false) => {
  ready.value = false;
  if (resetPage) {
    page.value = 1
    syncToUrl()
  }
  const data = {
    page: page.value,
    per_page: 20,
    search: search.value,
    rol: filterRol.value
  }
  userStore.getUsers(data)
    .then((response) => {
      if (response.code !== 200) throw response
      users.value = response.data.data || response.data;
      lastPage.value = response.data.last_page || 1
      setTimeout(() => {
        ready.value = true
      }, 1000)
    })
    .catch(() => {})
}

const optionsFilterRol = [
  { name: 'Admin', value: 1 },
  { name: 'Propietarios', value: 2 },
  { name: 'Inquilinos', value: 3 },
  { name: 'Familiar', value: 4 },
  { name: 'Airbnb', value: 5 },
  { name: 'Trabajadores', value: 6 },
  { name: 'Propietarios parciales', value: 7 },
]

const showDepartment = (user) => {
  return user.rol_id == 2 || user.rol_id == 3 || user.rol_id == 4 || user.rol_id == 5 || user.rol_id == 7
}

const formatUnits = (user) => {
  if (user.units && user.units.length > 0) {
    return user.formatted_units || user.units.map(u => u.number || u.name).join(', ')
  }
  return 'Apt. no asignado'
}

onMounted(() => {
  restoreFromQuery()
  getUsers()
})
</script>
<template>
  <div class="h-full" style="overflow: auto;">
    <div class="w-full px-4 flex items-center q-col-gutter-sm md:px-32">
      <div class="col">
        <q-select v-model="filterRol" :options="optionsFilterRol" option-label="name" option-value="value"
          emit-value map-options dense borderless color="primary"
          class="form_userOptionSelect" @update:model-value="getUsers(true)" />
      </div>
      <div class="col">
        <q-input v-model="search" dense borderless clearable placeholder="Buscar por nombre o # depto..."
          class="form_userOptionSelect" @update:model-value="getUsers(true)" debounce="500">
          <template v-slot:prepend>
            <q-icon name="eva-search-outline" />
          </template>
        </q-input>
      </div>
    </div>
    <div class="px-4 md:px-0 md:flex md:mx-auto md:justify-end md:w-5/6">
      <q-btn color="primary" unelevated class="w-full mt-5 md:mx-5 createButton" style="border-radius: 0.5rem;"
        @click="goTo('/admin/users/form/add')">
        <div class="flex items-center py-1">
          <q-icon name="eva-plus-outline" />
          <div class="q-pt-xs text-bold pl-1">Crear nuevo usuario</div>
        </div>
      </q-btn>
    </div>
    <div class="mt-4 md:mt-8">
      <div class="px-4 md:mx-28">
        <div v-for="user in users" :key="user.id"
          class="md:py-4 py-3 mb-5 userListContainer row items-center">
          <div class="flex items-center pb-3 pt-2 pl-2 md:pl-5 col-12 no-wrap">
            <div style="height: 2.8rem; width: 2.8rem; background: #b5b5b5; border-radius: 0.5rem; font-size: 2rem; font-weight: bold;"
              class="flex flex-center text-white">
              {{ user.name.charAt(0).toUpperCase() }}
            </div>
            <div class="ml-2">
              <div class="text-subtitle1 text-bold text-black" style="line-height:1.7;">
                {{ user.name }}
              </div>
              <div class="flex items-center wrap" v-if="showDepartment(user)">
                <div class="text-body2 text-grey-6 text-uppercase">
                  #{{ formatUnits(user) }}
                </div>
                <div class="text-caption text-grey-6 ml-1">
                  ({{ user.rol.name }})
                </div>
              </div>
              <div class="text-body2 text-grey-6" v-else>
                {{ user.rol.name }}
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
            <div>
              <q-btn icon="eva-eye-outline" class="mx-1" flat color="indigo-5" size="0.9rem"
                @click="goTo('/admin/users/detail/' + user.id)">
                <q-tooltip transition-show="flip-right" transition-hide="flip-left" class="bg-black text-body2 px-2">
                  Ver detalle
                </q-tooltip>
              </q-btn>
            </div>
            <div v-if="user.rol_id == 2 || user.rol_id == 7">
              <q-btn icon="eva-home-outline" class="mx-1" flat color="yellow-9" size="0.9rem"
                @click="goTo('/admin/users/assign-property/' + user.id)">
                <q-tooltip transition-show="flip-right" transition-hide="flip-left" :class="'bg-black text-body2 px-2'">
                  Agregar unidad
                </q-tooltip>
              </q-btn>
            </div>
            <div>
              <q-btn icon="eva-settings-outline" class="mx-1" color="primary" flat size="0.9rem"
                @click="goTo('/admin/users/form/update/' + user.id)">
                <q-tooltip transition-show="flip-right" transition-hide="flip-left" :class="'bg-black text-body2 px-2'">
                  Editar usuario
                </q-tooltip>
              </q-btn>
            </div>
            <div>
              <q-btn icon="eva-credit-card-outline" class="mx-1" color="amber-6" flat size="0.9rem"
                v-if="user.rol_id != 1 && user.rol_id != 7 && user.rol_id != 6"
                @click="goTo('/admin/pays/user/' + user.id)">
                <q-tooltip transition-show="flip-right" transition-hide="flip-left" class="bg-black text-body2 px-2">
                  Ver pagos
                </q-tooltip>
              </q-btn>
            </div>
            <div>
              <q-btn icon="eva-grid-outline" class="mx-1" flat color="teal" size="0.9rem" @click="openAreas(user)">
                <q-tooltip transition-show="flip-right" transition-hide="flip-left" :class="'bg-black text-body2 px-2'">
                  Áreas que puede reservar
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
      <div v-if="lastPage > 1" class="flex justify-center q-py-md">
        <q-pagination v-model="page" :max="lastPage" :max-pages="7" boundary-numbers color="primary"
          @update:model-value="onPageChange(() => getUsers())" />
      </div>
    </div>
    <div v-if="Object.values(selectedUser).length > 0">
      <deleteUserModal :dialog="(modal == 'delete')" :user="selectedUser" @close-modal="modal = ''" @update-list="getUsers()" />
    </div>
    <q-dialog v-model="areasDialog">
      <q-card style="max-width: 40rem; width: 100%;" class="q-pa-md">
        <q-card-section>
          <div class="text-h6 text-primary text-bold">
            Áreas que puede reservar
          </div>
          <div class="text-body2 text-grey-7 q-mt-xs">
            {{ areasUser.name }} — Si no seleccionas ninguna área, el usuario podrá reservar todas las disponibles.
          </div>
        </q-card-section>
        <q-card-section class="scroll" style="max-height: 55vh;">
          <userAvailableAreasStep v-model="selectedAreas" :userId="areasUser.id" />
        </q-card-section>
        <q-card-actions align="right" class="q-px-md q-pb-md">
          <q-btn flat color="grey-7" @click="areasDialog = false">
            Cancelar
          </q-btn>
          <q-btn color="primary" :loading="areasLoading" @click="saveAreas">
            Guardar
          </q-btn>
        </q-card-actions>
      </q-card>
    </q-dialog>
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
  &:hover { background: #279edb !important; }
  &.active { opacity: 1; }
  &.leftItem { border-top-left-radius: 0.7rem; border-bottom-left-radius: 0.7rem; }
  &.rightItem { border-top-right-radius: 0.7rem; border-bottom-right-radius: 0.7rem; }
}
.form_userOptionSelect {
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
}
@media (max-width: 780px) {
  .createButton { width: 100%; }
  .form_userOptionSelect .q-field__inner { padding: 0.1rem 1rem; }
}
</style>
