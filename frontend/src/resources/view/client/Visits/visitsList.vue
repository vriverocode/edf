<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useVisitStore } from '@/services/store/visits.store';
import moment from 'moment';

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})

const visitStore = useVisitStore()
const ready = ref(false)
const visits = ref([])
const router = useRouter()

const goTo = (url) => {
  router.push(url)
}

const getVisits = () => {
  ready.value = false
  visitStore.getVisitsByUser()
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

const formatDate = (date) => {
  if (!date) return '-'
  return moment(date).format('DD MMM YYYY, HH:mm')
}

onMounted(() => {
  getVisits()
})
</script>

<template>
  <div class="h-full">
    <template v-if="ready">
      <div class="h-full" style="overflow: auto;">
        <template v-if="visits.length > 0">
          <div class="mt-4 md:mt-8" style="height:85%">
            <div class="px-4 md:mx-24 md:pr-12">
              <div v-for="visit in visits" :key="visit.id"
                class="md:py-4 py-3 mb-5 visitListContainer flex items-center justify-between">
                <div class="flex items-center pb-3 pl-2 md:pl-5">
                  <div
                    style="height: 2.8rem; width: 2.8rem; background: #1976d2; border-radius: 0.5rem; font-size: 1.5rem; font-weight: bold;"
                    class="flex flex-center text-white">
                    {{ visit.fullname?.charAt(0)?.toUpperCase() || '?' }}
                  </div>
                  <div class="ml-2">
                    <div class="text-subtitle1 text-bold text-black" style="line-height:1.7;">
                      {{ visit.fullname }}
                    </div>
                    <div class="flex items-center flex-wrap gap-x-2">
                      <div class="text-body2 text-grey-6">
                        DNI: {{ visit.dni }}
                      </div>
                      <div class="text-caption text-grey-6">
                        #{{ visit.departament?.number || 'Apt. N/A' }}
                      </div>
                      <div class="text-caption text-primary">
                        {{ visit.type_label }}
                      </div>
                    </div>
                    <div class="text-caption text-grey-6 mt-1">
                      {{ formatDate(visit.created_at) }}
                    </div>
                    <div v-if="visit.description" class="text-caption text-grey-7 mt-1" style="max-width: 300px;">
                      {{ visit.description }}
                    </div>
                  </div>
                </div>
                <div class="flex justify-end px-2 pb-3 pt-1 pr-2 md:pr-5">
                  <q-btn icon="eva-eye-outline" class="mx-1" color="primary" flat size="0.9rem">
                    <q-tooltip transition-show="flip-right" transition-hide="flip-left" class="bg-black text-body2 px-2">
                      Ver detalles
                    </q-tooltip>
                  </q-btn>
                  <q-btn icon="eva-edit-2-outline" class="mx-1" color="grey-8" flat size="0.9rem">
                    <q-tooltip transition-show="flip-right" transition-hide="flip-left" class="bg-black text-body2 px-2">
                      Editar
                    </q-tooltip>
                  </q-btn>
                  <q-btn icon="eva-trash-2-outline" class="mx-1" color="negative" flat size="0.9rem">
                    <q-tooltip transition-show="flip-right" transition-hide="flip-left" class="bg-black text-body2 px-2">
                      Eliminar
                    </q-tooltip>
                  </q-btn>
                </div>
              </div>
            </div>
          </div>
          <div class="px-4 md:px-0 md:flex md:mx-auto md:justify-end md:w-5/6" style="height:10%">
            <q-btn color="primary" unelevated class="w-full mt-5 md:mx-5 createButton"
              style="border-radius: 0.5rem;" @click="goTo('/client/visit/add')">
              <div class="flex items-center py-1">
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
  </div>
</template>

<style lang="scss" scoped>
.visitListContainer {
  overflow: hidden;
  border-radius: 0.5rem;
  box-shadow: 0px 2px 6px 0px rgb(199, 199, 199);
}

.createButton {
  width: auto;
}

@media (max-width: 780px) {
  .createButton {
    width: 100%;
  }
}
</style>
