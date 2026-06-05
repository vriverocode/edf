<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useVisitStore } from '@/services/store/visits.store';
import moment from 'moment';
import { Notify } from 'quasar';
import filterModal from '@/components/visits/filterModal.vue';

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})

const visitStore = useVisitStore()
const ready = ref(false)
const visits = ref([])
const router = useRouter()
const filters = ref({
  search: '',
  status: [],
  departament_id: '',
})
const modal = ref('')
const activeFilterSearch = ref('')
const statusOptions = ref([])
const apartmentOptions = ref([])

const goTo = (url) => {
  router.push(url)
}

const getVisits = () => {
  ready.value = false
  visitStore.getVisitsByUser(filters.value)
    .then((response) => {
      if (response.code !== 200) throw response
      visits.value = response.data
      setTimeout(() => {
        ready.value = true
      }, 800)
    })
    .catch(() => {
      ready.value = true
    })
}

const isUsingFilter = () => {
  const hasStatus = Array.isArray(filters.value.status) && filters.value.status.length > 0
  const hasApartment = !!filters.value.departament_id
  activeFilterSearch.value = filters.value.search || hasStatus || hasApartment ? 'active-filter' : ''
}

const getVisitsWithFilter = (newFilter) => {
  filters.value = { ...filters.value, ...newFilter }
  isUsingFilter()
  getVisits()
}

const loadFilterOptions = () => {
  visitStore.getVisitFilterOptionsByUser()
    .then((response) => {
      if (response.code !== 200) throw response
      statusOptions.value = response.data?.statuses || []
      apartmentOptions.value = response.data?.apartments || []
    })
    .catch(() => {
      statusOptions.value = []
      apartmentOptions.value = []
    })
}

const formatDate = (date) => {
  if (!date) return ''
  return moment(date).format('DD MMM YYYY ')
}

const deleteItem = (item) => {
  console.log(item)
}
const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}
const noDisponible = () => {
  showNotify('terciary', 'No disponible')
}
onMounted(() => {
  loadFilterOptions()
  getVisits()
})
</script>

<template>
  <div class="h-full">
    <template v-if="ready">
      <div class="h-full" style="overflow: hidden;">
        <div class="flex justify-end column  items-end md:pr-36 px-4 pt-0" style="height:8%;overflow:hidden">
          <q-btn outline color="primary" :class="activeFilterSearch" icon="eva-funnel-outline" @click="modal = 'filter'" />
        </div>
        <template v-if="visits.length > 0">
          <div class="pt-4 md:pt-4 pb-8"  style="height:82%; overflow:auto">
            <div class="px-4 md:px-32">
              <q-slide-item v-for="visit in visits" :key="visit.id" @right="() => deleteItem(visit.id)"
                right-color="red-8" class="my-3 listVisit-container" style="border-radius: 12px!important;">
                <template v-slot:right>
                  <div class="row items-center" style="border-radius: 12px;">
                    <q-icon name="eva-trash-2-outline" />
                    <div class="ml-1 text-subtitle2">
                      Borrar Visita
                    </div>
                  </div>
                </template>
                <div class="md:py-4 pt-1 visitListContainer flex items-center justify-between">
                  <div class="flex items-center pb-2 pl-2 md:pl-5">
                    <div
                      style="height: 2.8rem; width: 2.8rem; background: #1976d2; border-radius: 0.5rem; font-size: 1.5rem; font-weight: bold;"
                      class="flex flex-center text-white">
                      {{ visit.fullname?.charAt(0)?.toUpperCase() || '?' }}
                    </div>
                    <div class="ml-2">
                      <div class="text-subtitle1 text-bold text-black" style="line-height:1.7;">
                        {{ visit.fullname }}
                      </div>
                      <div class="">
                        <div class="text-body2 text-grey-6">
                          DNI: {{ visit.dni }}
                        </div>
                        <div class="text-caption text-grey-6">
                          #{{ visit.departament?.number || 'Apt. N/A' }} - {{ formatDate(visit.date) }}
                        </div>

                      </div>
                      <!-- <div class="text-caption text-grey-6">
                        {{ formatDate(visit.created_at) }}
                      </div> -->
                      <div v-if="visit.description" class="text-caption text-grey-7 mt-1" style="max-width: 300px;">
                        {{ visit.description }}
                      </div>
                    </div>
                  </div>
                  <div class="flex justify-between items-center py-1 pr-2 md:pr-5 w-full"
                    style="border-top: 1px solid #e0e0e0;">
                      <div class="badgeStatus flex flex-center px-2" :class="'bg-'+visit.status_color">
                        <div class="text-caption text-white text-bold">
                          {{ visit.status_label }}
                        </div>
                      </div>
                      <div class="">
                        <q-btn icon="eva-eye-outline" class="mx-1" color="primary" flat size="0.9rem"
                          @click="noDisponible()">
                          <q-tooltip transition-show="flip-right" transition-hide="flip-left"
                            class="bg-black text-body2 px-2">
                            Ver detalles
                          </q-tooltip>
                        </q-btn>
                        <q-btn icon="eva-edit-2-outline" class="mx-1" color="grey-8" flat size="0.9rem"
                          @click="noDisponible()">
                          <q-tooltip transition-show="flip-right" transition-hide="flip-left"
                            class="bg-black text-body2 px-2">
                            Editar
                          </q-tooltip>
                        </q-btn>
                        <q-btn icon="eva-trash-2-outline" class="mx-1" color="negative" flat size="0.9rem"
                          @click="noDisponible()">
                          <q-tooltip transition-show="flip-right" transition-hide="flip-left"
                            class="bg-black text-body2 px-2">
                            Eliminar
                          </q-tooltip>
                        </q-btn>
                      </div>
                  </div>
                  <div class="badgeType">
                    <div class="text-caption text-white text-bold">
                      {{ visit.type_label }}
                    </div>
                  </div>
                  
                </div>
              </q-slide-item>
            </div>
          </div>
          <div class="px-4 md:px-0 md:flex md:mx-auto md:justify-end md:w-5/6" style="height:10%">
            <q-btn color="primary" unelevated class="w-full mt-4r md:mt-0 md:mx-5 createButton"
              style="border-radius: 0.5rem;" @click="goTo('/client/visit/add')">
              <div class="flex items-center ">
                <q-icon name="eva-plus-outline" />
                <div class="q-pt-xs text-bold pl-1">
                  Registrar visita
                </div>
              </div>
            </q-btn>
          </div>
        </template>
        <template v-else>
          <div class="flex flex-center column empty-results px-4" style="min-height: 60vh;">
            <q-icon name="eva-person-done-outline" size="4rem" color="grey-5" class="q-mb-md" />
            <div style="font-size: 1.2rem; font-weight: 600;" class="text-grey-7 text-center q-mb-sm">
              No hay visitas registradas
            </div>
            <div class="text-grey-6 text-center q-mb-lg">
              Registra las visitas que recibas en tu departamento.
            </div>
            <q-btn color="primary" unelevated style="border-radius: 0.5rem;" @click="goTo('/client/visit/add')">
              <q-icon name="eva-plus-outline" class="q-mr-sm" />
              Registrar visita
            </q-btn>
          </div>
        </template>
      </div>
    </template>
    <template v-else>
      <div class="h-full flex flex-center" style="overflow: auto;">
        <q-spinner-dots color="primary" size="7rem" />
      </div>
    </template>

    <filterModal
      :dialog="modal === 'filter'"
      :current-filters="filters"
      :status-options="statusOptions"
      :apartment-options="apartmentOptions"
      title="Filtrar mis visitas"
      search-label="Buscar por nombre, DNI o departamento"
      @closeModal="modal = ''"
      @updateList="getVisitsWithFilter"
    />
  </div>
</template>
<style lang="scss">
.q-btn.active-filter {
  background: $primary !important;
  color: white !important;
}

.badgeType {
  position: absolute;
  right: 0;
  top: 0;
  background: #226fb5;
  color: white;
  font-size: 0.8rem;
  padding: 0.2rem 0.9rem;
  border-bottom-left-radius: 12px;
}
.badgeStatus {
  color: white;
  font-size: 0.8rem;
  height: 30px;
  border-bottom-right-radius: 45px;
  border-top-right-radius: 45px;
}

.listVisit-container {
  box-shadow: 0px 5px 5px 0px rgba(54, 54, 54, 0.082) !important;
  border-radius: 12px;
  border: 1px solid #e0e0e0;

}
</style>
<style lang="scss" scoped>
.visitListContainer {
  overflow: hidden;
  box-shadow: 0px 2px 6px 0px rgb(199, 199, 199);
}

.createButton {
  width: auto;
  height: 50px;
}

@media (max-width: 780px) {
  .createButton {
    width: 100%;
    height: auto;
  }
}
</style>
