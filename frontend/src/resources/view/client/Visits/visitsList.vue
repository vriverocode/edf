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
  date_from: '',
  date_to: '',
})
const modal = ref('')
const confirmDeleteModal = ref(false)
const selectedVisit = ref(null)
const loadingDelete = ref(false)
const activeFilterSearch = ref('')
const statusOptions = ref([])
const apartmentOptions = ref([])
const page = ref(1)
const lastPage = ref(1)
const total = ref(0)
const perPage = ref(15)

const goTo = (url) => {
  router.push(url)
}

const getVisits = (newPage = 1) => {
  ready.value = false
  page.value = newPage
  const params = {
    ...filters.value,
    page: page.value,
    per_page: perPage.value,
  }
  visitStore.getVisitsByUser(params)
    .then((response) => {
      if (response.code !== 200) throw response
      visits.value = response.data.data
      lastPage.value = response.data.last_page
      total.value = response.data.total
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
  const hasDateFrom = !!filters.value.date_from
  const hasDateTo = !!filters.value.date_to
  activeFilterSearch.value = filters.value.search || hasStatus || hasApartment || hasDateFrom || hasDateTo ? 'active-filter' : ''
}

const getVisitsWithFilter = (newFilter) => {
  filters.value = { ...filters.value, ...newFilter }
  isUsingFilter()
  getVisits(1)
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
  selectedVisit.value = item
  confirmDeleteModal.value = true
}

const confirmDelete = () => {
  if (!selectedVisit.value) return
  loadingDelete.value = true
  visitStore.deleteVisit(selectedVisit.value.id)
    .then(() => {
      showNotify('positive', 'Visita eliminada')
      confirmDeleteModal.value = false
      selectedVisit.value = null
      loadingDelete.value = false
      getVisits()
    })
    .catch((error) => {
      loadingDelete.value = false
      showNotify('negative', error || 'Error al eliminar visita')
    })
}
const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
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
        <div class="reserve-list-footer row px-4 pt-2 flex md:justify-center items-center md:w-full md:px-28"
        style="height: 13%; overflow:hidden" >
        <div class="flex items-center col-10 col-md-11 pr-2 md:px-4">
          <q-btn color="primary" unelevated class="w-full mt-0 md:mt-0 createButton"
              style="border-radius: 0.5rem;" @click="goTo('/client/visit/add')">
              <div class="flex items-center  py-1">
                <q-icon name="eva-plus-outline" />
                <div class="q-pt-xs text-bold pl-1">
                  Registrar visita
                </div>
              </div>
            </q-btn>
        </div>
        <div class="w-full flex justify-end col-2 col-md-1 md:px-12">
          <q-btn outline color="primary" :class="activeFilterSearch" icon="eva-funnel-outline" @click="modal = 'filter'" />
        </div>
      </div>
        <template v-if="visits.length > 0">
          <div class="pt-0 md:pt-4 pb-8"  style="height:87%; overflow:auto">
            <div class="px-4 md:px-32">
              <q-slide-item v-for="visit in visits" :key="visit.id" @right="() => deleteItem(visit)"
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
                          @click="goTo('/client/visits/view/'+visit.id)">
                          <q-tooltip transition-show="flip-right" transition-hide="flip-left"
                            class="bg-black text-body2 px-2">
                            Ver detalles
                          </q-tooltip>
                        </q-btn>
                        <!-- Edit removed -- not yet implemented -->
                        <q-btn icon="eva-trash-2-outline" class="mx-1" color="negative" flat size="0.9rem"
                          @click="deleteItem(visit)">
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
            <div v-if="lastPage > 1" class="flex justify-center py-4 px-4">
              <q-pagination v-model="page" :max="lastPage" :max-pages="4" :boundary-numbers="false"
                color="primary" @update:model-value="(p) => getVisits(p)" />
            </div>
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

    <q-dialog v-model="confirmDeleteModal" persistent backdrop-filter="blur(0.5px)">
      <q-card class="dialog_document" style="border-radius: 1rem; max-width: 90%;">
        <div>
          <q-card-section class="q-px-none">
            <div class="text-h6 text-center text-black pb-2" style="border-bottom: 1px solid lightgray">
              Eliminar visita
            </div>
          </q-card-section>
          <section class="mt-3">
            <q-card-section class="q-pt-none q-px-sm">
              <div class="px-2 text-center">
                <div class="text-body1 text-black">
                  ¿Deseas eliminar la visita de <b class="text-primary">{{ selectedVisit?.fullname }}</b>?
                </div>
                <div class="text-grey-7 q-mt-sm">
                  Esta accion no se puede deshacer.
                </div>
              </div>
            </q-card-section>
          </section>
        </div>
        <section class="pb-5" style="border-top: 1px solid lightgray">
          <div class="flex justify-evenly mt-4">
            <q-btn label="Cancelar" unelevated class="q-mx-sm" color="primary" outline
              style="border-radius: 0.8rem; padding: 0 2rem !important; font-size: 0.9rem"
              @click="confirmDeleteModal = false; selectedVisit = null" />
            <q-btn label="Eliminar" unelevated class="q-mx-sm" color="negative" outline
              style="border-radius: 0.8rem; padding: 0 2rem !important; font-size: 0.9rem"
              :loading="loadingDelete" @click="confirmDelete()" />
          </div>
        </section>
      </q-card>
    </q-dialog>
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
  //width: auto;
  height: 50px;
}

@media (max-width: 780px) {
  .createButton {
    width: 100%;
    height: auto;
  }
}
</style>
