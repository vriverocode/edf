<script setup>
import { onMounted, ref, watch } from 'vue';
import { useRouter, useRoute } from 'vue-router';
import { Notify } from 'quasar';
import iconsApp from '@/assets/icons/index'
import { useApartmentStore } from '@/services/store/apartment.store';
import { useWaterReadingsStore } from '@/services/store/waterReadings.store';
import initialWaterReadingModal from '@/components/admin/initialWaterReadingModal.vue'

const apartmentStore = useApartmentStore()
const waterReadingsStore = useWaterReadingsStore()

const page = ref(1)
const route = useRoute()
const search = ref(route.query.search || '')
const number = ref(route.query.number || '')
const filter = ref(0)
const lastPage = ref(1)
const ready = ref(false)
const router = useRouter()
const highlightId = ref(null)

const goTo = (url) => {
  router.push(url)
}

const apartments = ref([])
const ownersWithoutApartment = ref([])
const changeOwnerModal = ref(false)
const selectedApartment = ref(null)
const selectedOwner = ref(null)
const modalLoading = ref(false)
const filteredOwners = ref([])

const filterOwners = (val, update) => {
  const q = val.toLowerCase()
  update(() => {
    filteredOwners.value = ownersWithoutApartment.value.filter(o =>
      o.name?.toLowerCase().includes(q)
    )
  })
}
const unitType = ref(Number(route.query.type) || 1)
const unitTypesOptions = [
  { label: 'Departamentos', value: 1 },
  { label: 'Estacionamientos', value: 2 },
  { label: 'Depósitos', value: 3 }
]

const initialReadingDialog = ref(false)
const selectedApartmentForReading = ref(null)
const departmentsWithReading = ref([])

const getDepartmentsWithReading = async () => {
  const now = new Date()
  try {
    const response = await waterReadingsStore.getWaterReadings({
      month: now.getMonth() + 1,
      year: now.getFullYear(),
      per_page: 999
    })
    if (response?.code === 200) {
      const list = response.data?.pagination?.data || response.data?.data || []
      departmentsWithReading.value = list.map(r => r.apartment_id || r.departament_id)
    }
  } catch {
    departmentsWithReading.value = []
  }
}

const hasReading = (apartmentId) => departmentsWithReading.value.includes(apartmentId)

const openInitialReadingModal = (apartment) => {
  selectedApartmentForReading.value = apartment
  initialReadingDialog.value = true
}

const getApartment = () => {
  ready.value = false;
  const data = {
    page: page.value,
    search: search.value,
    number: number.value,
    filter: filter.value,
    type: unitType.value // Pasamos el tipo al store
  }
  apartmentStore.getPaginationApartment(data)
    .then((response) => {
      if (response.code !== 200) throw response
      apartments.value = response.data.data;
      lastPage.value = response.data.last_page;
      setTimeout(() => { ready.value = true; }, 1000);
    })
}

const openChangeOwnerModal = async (apartment) => {
  selectedApartment.value = apartment
  selectedOwner.value = null
  filteredOwners.value = []
  changeOwnerModal.value = true
  modalLoading.value = true
  try {
    const response = await apartmentStore.getOwnersWithoutApartment()
    if (response.code !== 200) throw response
    ownersWithoutApartment.value = response.data
    filteredOwners.value = response.data
  } catch (error) {
    Notify.create({
      type: 'negative',
      message: 'No se pudo cargar propietarios disponibles'
    })
    changeOwnerModal.value = false
  } finally {
    modalLoading.value = false
  }
}

const assignNewOwner = async () => {
  if (!selectedApartment.value || !selectedOwner.value) {
    Notify.create({
      type: 'warning',
      message: 'Selecciona un propietario para continuar'
    })
    return
  }
  modalLoading.value = true
  try {
    const payload = {
      idApartament: selectedApartment.value.id,
      user: selectedOwner.value
    }
    const response = await apartmentStore.assignProperty(payload)
    if (response.code !== 200) throw response
    Notify.create({
      type: 'positive',
      message: 'Propietario asignado correctamente'
    })
    changeOwnerModal.value = false
    getApartment()
  } catch (error) {
    console.error(error)
    Notify.create({
      type: 'negative',
      message: typeof error === 'string' ? error : 'No se pudo asignar el propietario'
    })
  } finally {
    modalLoading.value = false
  }
}

const goToOwnerInfo = (apartmentId) => {
  router.push('/admin/department/owner-info/' + apartmentId)
}

const editApartment = (apartment) => {
  const params = [
    `page=${page.value}`,
    `type=${unitType.value}`,
    search.value ? `search=${encodeURIComponent(search.value)}` : '',
    number.value ? `number=${encodeURIComponent(number.value)}` : '',
    'from=list'
  ].filter(Boolean).join('&')
  router.push(`/admin/apartments/edit/${apartment.id}?${params}`)
}

const removeHighlight = () => {
  setTimeout(() => { highlightId.value = null }, 3000)
}

watch(unitType, (value) => {
  page.value = 1;
  router.replace({ query: { ...route.query, type: value, page: 1 } })
  getApartment();
})

const onPageChange = () => {
  router.replace({ query: { ...route.query, page: page.value } })
  getApartment()
}

let searchTimer = null
watch([search, number], () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    router.replace({
      query: {
        ...route.query,
        page: 1,
        search: search.value || undefined,
        number: number.value || undefined
      }
    })
    getApartment()
  }, 500)
})

const refreshData = () => {
  getApartment()
  getDepartmentsWithReading()
}

onMounted(() => {
  if (route.query.page) page.value = Number(route.query.page)
  if (route.query.highlight) highlightId.value = Number(route.query.highlight)
  refreshData()
  if (highlightId.value) removeHighlight()
})
</script>

<template>
  <div class="h-full" style="overflow: auto;">
    <div class="px-2 row pt-2  md:px-28">
      <div class="col-12 px-2 pb-2">
        <q-select dense borderless v-model="unitType" :options="unitTypesOptions" emit-value map-options
          class="form__inputsTypeDepart" />
      </div>
      <div class="col-6 px-2">
        <q-input dense borderless v-model="search" placeholder="Buscar por propietario..." clearable
          class="form__inputsTypeDepart">
          <template v-slot:prepend>
            <q-icon name="eva-search-outline" />
          </template>
        </q-input>
      </div>
      <div class="col-6 px-2">
        <q-input dense borderless v-model="number" placeholder="Buscar por número de unidad..." clearable
          class="form__inputsTypeDepart">
          <template v-slot:prepend>
            <q-icon name="eva-search-outline" />
          </template>
        </q-input>
      </div>
    </div>
    <div class="px-4 md:px-0 md:flex md:justify-between items-center w-full md:w-6/6 mt-3 md:px-28 ">
      <div class="w-full md:w-auto">
        <q-btn color="primary" unelevated class="w-full createButton" style="border-radius: 0.5rem;"
          @click="goTo('/admin/department/form/add')">
          <div class="flex items-center py-1 px-4">
            <q-icon name="eva-plus-outline" />
            <div class="pl-2">Crear nueva unidad</div>
          </div>
        </q-btn>
      </div>
    </div>
   
    <div class="mt-5 md:mt-8 px-2 md:px-28  pb-5" style="overflow: auto;" v-if="ready">
      <template v-if="apartments.length > 0">
        <div class="px-2 pt-3 mt-4  apartamentContainer relative"
          :class="{ 'highlight-animate': highlightId === apartment.id }" v-for="apartment in apartments"
          :key="apartment.id">
          <div class="flex items-center w-full pb-3">
            <div class="imgItem__container w">
              <div v-html="iconsApp.apartment" class="flex flex-center px-0 h-full" />
            </div>
            <div class="px-2 infoItem">
              <div class=" text-bold  text-black"
                style="font-weight: bold; font-size: 1.3rem; text-transform: uppercase;">
                #{{ apartment.number }}
                <q-icon v-if="hasReading(apartment.id)" name="eva-checkmark-circle-2" color="positive" size="sm"
                  class="q-ml-xs">
                  <q-tooltip>Lectura de agua registrada este período</q-tooltip>
                </q-icon>
              </div>
              <div class="mt-1 ellipsis w-full" style="font-weight: 500; font-size: 0.89rem;">
                Ubicación: {{ apartment.address }}
              </div>
              <div class="mt-1" style="font-weight: 500; font-size: 0.89rem; color: #555;">
                Propietario: {{ apartment.owner?.name || apartment.user?.name || 'Sin asignar' }}
              </div>
              <template v-if="apartment.type == 1">
                <div class="mt-1" style="font-weight: 500; font-size: 0.89rem;">
                  Area: {{ apartment.area }} mt²
                </div>
              </template>
              <div class="mt-1" style="font-weight: 500; font-size: 0.89rem;">
                % Participación: {{ apartment.participation_percentage }}%
              </div>
            </div>
          </div>
          <div class="flex w-full justify-end py-1" style="border-top: 1px solid lightgrey;">
            <div>
              <q-btn icon="eva-person-outline" class="mx-1" flat color="primary" round size="0.85rem"
                @click="goToOwnerInfo(apartment.id)" />
            </div>
            <div>
              <q-btn icon="eva-swap-outline" class="mx-1" flat color="warning" round size="0.85rem"
                @click="openChangeOwnerModal(apartment)" />
            </div>
            <div>
              <q-btn icon="eva-droplet-outline" class="mx-1" flat color="info" round size="0.85rem"
                @click="openInitialReadingModal(apartment)" />
            </div>
            <div>
              <q-btn icon="eva-settings-outline" class="mx-1" flat color="primary" round size="0.85rem"
                @click="editApartment(apartment)" />
            </div>
            <div>
              <q-btn icon="eva-trash-2-outline" class="mx-1" flat color="negative" round size="0.85rem" />
            </div>

          </div>
          <div class="itemBadge px-8 py-1" :class="{ 'bg-positive': !apartment.owner, 'bg-negative': apartment.owner }">
            {{ !apartment.owner ? 'Disponible' : 'Ocupado' }}
          </div>
        </div>
        <div class="flex justify-center mt-4">
          <q-pagination v-model="page" color="primary" :max="lastPage" :max-pages="4" :boundary-numbers="false"
            @update:model-value="onPageChange()" />
        </div>
      </template>
      <template v-else>
        <div class="h-full w-full font-medium text-center pt-20 text-h6">
          No tienes {{unitTypesOptions.find(el => el.value == unitType).label}} creados 😕😕
        </div>
      </template>
    </div>
    <div v-else class="flex flex-center py-24">
      <q-spinner-dots color="primary" size="7rem" />
    </div>
    <q-dialog v-model="changeOwnerModal" persistent>
      <q-card style="min-width: 320px; width: 95%; max-width: 480px;">
        <q-card-section class="row items-center q-pb-none">
          <div class="text-h6">Asignar/Cambiar propietario</div>
        </q-card-section>
        <q-card-section>
          <div class="text-subtitle2 q-mb-sm" v-if="selectedApartment" style="text-transform: uppercase;">
            Unidad #{{ selectedApartment.number }}
          </div>
          <q-select dense borderless v-model="selectedOwner" :options="filteredOwners" option-label="name"
            option-value="id" emit-value map-options class="form__inputsTypeDepart" :loading="modalLoading"
            :disable="modalLoading" use-input input-debounce="300" @filter="filterOwners"
            placeholder="Buscar propietario..."
            no-option-label="No hay propietarios sin departamento" />
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat no-caps label="Cancelar" color="grey-7" v-close-popup :disable="modalLoading" />
          <q-btn color="primary" no-caps label="Guardar cambio" :loading="modalLoading" @click="assignNewOwner" />
        </q-card-actions>
      </q-card>
    </q-dialog>

    <initialWaterReadingModal
      :dialog="initialReadingDialog"
      :apartment="selectedApartmentForReading"
      @close-modal="initialReadingDialog = false"
      @created="getApartment()"
    />
  </div>
</template>
<style lang="scss">
.form__inputsTypeDepart {
  & .q-field__inner {
    border: 1px solid lightgray;
    background: white;
    border-radius: 0.5rem;
    padding: 0 1rem;
    height: 45px;
  }
}

.itemBadge {
  color: white;
  font-size: 0.9rem;
  top: 0;
  right: 0;
  position: absolute;
  border-bottom-left-radius: 0.8rem;
  // transform: rotate(45deg);
}

.imgItem__container {
  width: 4.2rem;
  height: 4.2rem;
  border-radius: 0.8rem;
  background: $primary;
}

.infoItem {
  width: calc(100% - 4.2rem);
}

.apartamentContainer {
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

@keyframes highlightPulse {
  0% { box-shadow: 0 0 0 0 rgba(25, 118, 210, 0.7); }
  70% { box-shadow: 0 0 0 10px rgba(25, 118, 210, 0); }
  100% { box-shadow: 0 0 0 0 rgba(25, 118, 210, 0); }
}

.highlight-animate {
  animation: highlightPulse 1.5s ease-out;
  border: 2px solid var(--q-primary) !important;
}

@media (max-width: 780px) {
  .createButton {
    width: 100%;
  }
}
</style>
