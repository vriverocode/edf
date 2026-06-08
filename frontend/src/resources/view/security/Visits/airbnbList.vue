<script setup>
import { onMounted, ref } from 'vue'
import { useVisitStore } from '@/services/store/visits.store'
import moment from 'moment'
import AirbnbGuestsModal from './components/AirbnbGuestsModal.vue'
import filterModal from '@/components/visits/filterModal.vue'

moment.locale('es', {
    monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
    months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})

const visitStore = useVisitStore()
const ready = ref(false)
const rents = ref([])
const selectedRent = ref(null)
const isGuestsModalOpen = ref(false)
const filters = ref({
    search: '',
    status: [],
    departament_id: '',
})
const modal = ref('')
const activeFilterSearch = ref('')
const statusOptions = ref([])
const apartmentOptions = ref([])

const formatDate = (date) => {
    if (!date) return ''
    return moment(date).format('DD MMM YYYY')
}

const getAirbnbRents = () => {
    ready.value = false
    visitStore
        .getAirbnbReserve(filters.value)
        .then((response) => {
            if (response.code !== 200) throw response
            rents.value = response.data || []
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

const getRentsWithFilter = (newFilter) => {
    filters.value = { ...filters.value, ...newFilter }
    isUsingFilter()
    getAirbnbRents()
}

const loadFilterOptions = () => {
    visitStore.getAirbnbFilterOptionsForSecurity()
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

const openGuestsModal = (rent) => {
    selectedRent.value = rent
    isGuestsModalOpen.value = true
}

onMounted(() => {
    loadFilterOptions()
    getAirbnbRents()
})
</script>

<template>
    <div class="h-full">
        <template v-if="ready">
            <div class="h-full" style="overflow: hidden;">
                <div class="flex justify-end md:mx-24 md:px-12 px-4 pt-4">
                    <q-btn outline color="primary" :class="activeFilterSearch" icon="eva-funnel-outline"
                        @click="modal = 'filter'" />
                </div>
                <template v-if="rents.length > 0">
                    <div class="mt-4 md:mt-8 pb-20" style="height: 92%; overflow: auto">
                        <div class="px-4 md:mx-24 md:px-12">
                            <div v-for="rent in rents" :key="rent.id" class="my-3 listVisit-container"
                                style="border-radius: 12px !important; position: relative;">
                                <div class="md:pt-3 md:pb-2 pt-1 visitListContainer">
                                    <div class="flex items-center justify-between px-2 md:px-5 pb-2">
                                        <div class="flex items-center">
                                            <div class="avatar-box">{{ rent.name_to?.charAt(0)?.toUpperCase() || 'A' }}
                                            </div>
                                            <div class="ml-2">
                                                <div class="flex items-center text-sm text-gray-700">
                                                    <div class="text-subtitle1 text-bold text-black"
                                                        style="line-height: 1.7;">
                                                        {{ rent.name_to || `Reserva #${rent.id}` }}
                                                    </div>
                                                </div>

                                                <div class="flex items-center text-xs mt-1 text-gray-500">
                                                    <svg class="w-4 h-4 mr-2 text-gray-500" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                                                        </path>
                                                    </svg>
                                                    <div>
                                                        {{ formatDate(rent.init_day) }}
                                                        <template v-if="rent.end_date">al {{ formatDate(rent.end_date)
                                                            }}</template>
                                                    </div>
                                                </div>
                                                <div class="flex items-center text-xs mt-1 text-gray-500">

                                                    <svg style="transform: translateX(-3px);" width="1.6rem"
                                                        height="1.6rem" viewBox="0 0 64 64"
                                                        xmlns="http://www.w3.org/2000/svg" stroke-width="2"
                                                        stroke="#374151" fill="none">
                                                        <g id="SVGRepo_bgCarrier" stroke-width="0"></g>
                                                        <g id="SVGRepo_tracerCarrier" stroke-linecap="round"
                                                            stroke-linejoin="round"></g>
                                                        <g id="SVGRepo_iconCarrier">
                                                            <path
                                                                d="M34.82,52.73H14.69V22.18a1,1,0,0,1,.52-.87L33.34,11.4a1,1,0,0,1,1.48.88Z"
                                                                stroke-linecap="round">
                                                            </path>
                                                            <path
                                                                d="M48.87,52.73H34.92V21.59L48.4,29.3a1,1,0,0,1,.47.85Z"
                                                                stroke-linecap="round">
                                                            </path>
                                                            <line x1="28.1" y1="24.86" x2="21.06" y2="24.86"
                                                                stroke-linecap="round"></line>
                                                            <line x1="43.66" y1="32.41" x2="40.14" y2="32.41"
                                                                stroke-linecap="round"></line>
                                                            <line x1="43.66" y1="36.9" x2="40.14" y2="36.9"
                                                                stroke-linecap="round"></line>
                                                            <line x1="43.66" y1="41.71" x2="40.14" y2="41.71"
                                                                stroke-linecap="round"></line>
                                                            <line x1="43.66" y1="46.19" x2="40.14" y2="46.19"
                                                                stroke-linecap="round"></line>
                                                            <line x1="28.1" y1="30.44" x2="21.06" y2="30.44"
                                                                stroke-linecap="round"></line>
                                                            <line x1="28.1" y1="35.94" x2="21.06" y2="35.94"
                                                                stroke-linecap="round"></line>
                                                            <line x1="28.1" y1="41.44" x2="21.06" y2="41.44"
                                                                stroke-linecap="round"></line>
                                                            <line x1="28.1" y1="46.94" x2="21.06" y2="46.94"
                                                                stroke-linecap="round"></line>
                                                            <line x1="9.46" y1="52.73" x2="54.54" y2="52.73"
                                                                stroke-linecap="round"></line>
                                                        </g>
                                                    </svg>
                                                    <div class="q-pt-xs">
                                                        Apt. #{{ rent.departament?.number || 'N/A' }}
                                                        <template v-if="rent.quantity">· {{ rent.quantity }}
                                                            huesped(es)</template>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="flex justify-end items-center py-2 pr-2 md:pt-3 md:pb-1 md:pr-2 border-top-light">
                                        <q-btn color="primary" unelevated size="0.85rem" style="border-radius: 0.5rem;"
                                            no-caps @click="openGuestsModal(rent)">
                                            Ver huespedes
                                        </q-btn>
                                    </div>

                                    <div class="badgeType" :class="'bg-' + rent.status_color">
                                        <div class="text-caption text-white text-bold">
                                            {{ rent.status_label }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <div class="flex flex-center column empty-results px-4" style="min-height: 60vh;">
                        <q-icon name="eva-home-outline" size="4rem" color="grey-5" class="q-mb-md" />
                        <div style="font-size: 1.2rem; font-weight: 600;" class="text-grey-7 text-center q-mb-sm">
                            No hay reservas Airbnb pendientes
                        </div>
                        <div class="text-grey-6 text-center q-mb-lg">
                            Cuando existan reservas activas, apareceran aqui.
                        </div>
                        <q-btn color="primary" outline style="border-radius: 0.5rem;" @click="getAirbnbRents()">
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
            :apartment-options="apartmentOptions" title="Filtrar reservas Airbnb"
            search-label="Buscar por titular, huesped, DNI o departamento" @closeModal="modal = ''"
            @updateList="getRentsWithFilter" />

        <AirbnbGuestsModal v-model="isGuestsModalOpen" :rent="selectedRent" @updated="getAirbnbRents" />
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

.avatar-box {
    height: 2.8rem;
    width: 2.8rem;
    background: #1976d2;
    border-radius: 0.5rem;
    font-size: 1.4rem;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
}

.border-top-light {
    border-top: 1px solid #e0e0e0;
}
</style>

<style lang="scss" scoped>
.visitListContainer {
    overflow: hidden;
    border-radius: 12px;
    box-shadow: 0px 2px 6px 0px rgb(199, 199, 199);
}
</style>
