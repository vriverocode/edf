<script setup>
import { onMounted, ref } from 'vue';
import { useVisitStore } from '@/services/store/visits.store';
import moment from 'moment';
import { Notify, Dialog } from 'quasar';
import filterModal from '@/components/visits/filterModal.vue';

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})

const visitStore = useVisitStore()
const ready = ref(false)
const visits = ref([])
const loadingId = ref(null)
const filters = ref({
  search: '',
  status: [],
  departament_id: '',
})
const modal = ref('')
const activeFilterSearch = ref('')
const statusOptions = ref([])
const apartmentOptions = ref([])

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}

const formatDate = (date) => {
  if (!date) return ''
  return moment(date).format('DD MMM YYYY')
}

const getVisits = () => {
  ready.value = false
  visitStore.getVisitsForSecurity(filters.value)
    .then((response) => {
      if (response.code !== 200) throw response
      visits.value = response.data || []
      setTimeout(() => {
        ready.value = true
      }, 600)
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
  visitStore.getVisitFilterOptionsForSecurity()
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

const confirmArrived = (visit) => {
  Dialog.create({
    title: 'Confirmar llegada',
    message: `¿Marcar a ${visit.fullname} como llegada confirmada?`,
    cancel: true,
    persistent: true
  }).onOk(() => {
    loadingId.value = visit.id
    visitStore.markVisitArrived(visit.id)
      .then((response) => {
        if (response.code !== 200) throw response
        showNotify('primary', response.message || 'Llegada confirmada')
        getVisits()
      })
      .catch((err) => {
        const msg = err?.error || err?.message || 'No se pudo actualizar la visita'
        showNotify('negative', msg)
      })
      .finally(() => {
        loadingId.value = null
      })
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
      <div class="h-full" style="overflow: auto;">
        <div class="flex justify-end md:mx-24 md:px-12 px-4 pt-4">
          <q-btn outline color="primary" :class="activeFilterSearch" icon="eva-funnel-outline"
            @click="modal = 'filter'" />
        </div>
        <template v-if="visits.length > 0">
          <div class="mt-4 md:mt-8" style="height: 92%; overflow: scroll">
            <div class="px-4 md:mx-24 md:px-12">
              <div v-for="visit in visits" :key="visit.id" class="my-3 md:my-5 listVisit-container"
                style="border-radius: 12px!important; position: relative;">
                <div class="md:py-3 pt-1 visitListContainer flex items-center justify-between">
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
                        <div class="text-body2 text-grey-6" v-if="visit.airbnb_rent_id"
                          style="text-decoration:underline">
                          Reserva: #000{{ visit.airbnb.id }}
                        </div>
                        <div class="text-caption text-grey-6">
                          #{{ visit.departament?.number || 'Apt. N/A' }} - {{ formatDate(visit.date) }}
                          <template v-if="visit.hour">· {{ visit.hour }}</template>
                        </div>
                      </div>

                    </div>
                  </div>

                  <div class="flex justify-between items-center py-2 md:py-0 md:pt-2 pr-2 md:pr-5 w-full"
                    style="border-top: 1px solid #e0e0e0;">
                    <div class="badgeStatus flex flex-center px-2" :class="'bg-' + visit.status_color">
                      <div class="text-caption text-white text-bold">
                        {{ visit.status_label }}
                      </div>
                    </div>

                    <div class="flex items-center">
                      <q-btn color="primary" unelevated size="0.85rem" style="border-radius: 0.5rem;" no-caps
                        :loading="loadingId === visit.id" :disable="loadingId !== null" @click="confirmArrived(visit)">
                        Marcar llegado
                      </q-btn>
                    </div>
                  </div>

                  <div class="badgeType">
                    <div class="text-caption text-white text-bold">
                      {{ visit.type_label }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>

        <template v-else>
          <div class="flex flex-center column empty-results px-4" style="min-height: 60vh;">
            <q-icon name="eva-checkmark-circle-2-outline" size="4rem" color="grey-5" class="q-mb-md" />
            <div style="font-size: 1.2rem; font-weight: 600;" class="text-grey-7 text-center q-mb-sm">
              No hay visitas pendientes
            </div>
            <div class="text-grey-6 text-center q-mb-lg">
              Cuando haya visitas registradas como “Pendiente de llegada”, aparecerán aquí.
            </div>
            <q-btn color="primary" outline style="border-radius: 0.5rem;" @click="getVisits()">
              Actualizar
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

    <filterModal :dialog="modal === 'filter'" :current-filters="filters" :status-options="statusOptions"
      :apartment-options="apartmentOptions" title="Filtrar visitas de seguridad"
      search-label="Buscar por nombre, DNI o departamento" @closeModal="modal = ''" @updateList="getVisitsWithFilter" />
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
  border-bottom-right-radius: 45px;
  height: 30px;
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
  border-radius: 12px;
  box-shadow: 0px 2px 6px 0px rgb(199, 199, 199);
}
</style>
