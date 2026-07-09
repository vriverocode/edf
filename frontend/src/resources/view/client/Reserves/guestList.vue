<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useGuestListStore } from '@/services/store/guestList.store'
import { Notify } from 'quasar'
import moment from 'moment'
import addGuestModal from '@/components/reserves/addGuestModal.vue'
import editGuestModal from '@/components/reserves/editGuestModal.vue'
import deleteGuestModal from '@/components/reserves/deleteGuestModal.vue'

moment.locale('es', {
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})

const route = useRoute()
const router = useRouter()
const guestListStore = useGuestListStore()

const bookingId = Number(route.params.id)
const booking = ref(null)
const guests = ref([])
const loading = ref(true)
const maxGuests = ref(40)
const isLocked = ref(false)
const now = ref(moment())

const dialog = ref('')
const selectedGuest = ref({})

const guestCount = computed(() => guests.value.length)
const canAddGuest = computed(() => !isLocked.value && guestCount.value < maxGuests.value)

const lockMinutesRemaining = computed(() => {
  if (!booking.value) return null
  const bookingDateTime = moment(moment(booking.value.date).format('YYYY-MM-DD') + ' ' + booking.value.time_from)
  const diff = bookingDateTime.diff(now.value, 'minutes')
  return diff > 0 ? diff : 0
})

const isLockedDisplay = computed(() => {
  return isLocked.value || lockMinutesRemaining.value <= 0
})

let timer = null

const startTimer = () => {
  timer = setInterval(() => {
    now.value = moment()
    if (booking.value) {
      const bookingDateTime = moment(moment(booking.value.date).format('YYYY-MM-DD') + ' ' + booking.value.time_from)
      isLocked.value = now.value.diff(bookingDateTime, 'minutes') <= 60
    }
  }, 30000)
}

const fetchGuests = () => {
  loading.value = true
  guestListStore.getGuestsByBooking(bookingId)
    .then((response) => {
      if (response.code !== 200) throw response
      booking.value = response.data.booking
      guests.value = response.data.guests
      maxGuests.value = response.data.max_guests
      isLocked.value = response.data.is_locked
      loading.value = false
    })
    .catch((error) => {
      loading.value = false
      Notify.create({ color: 'negative', message: error || 'Error al cargar datos', timeout: 2000 })
    })
}

const openAddModal = () => {
  dialog.value = 'add'
}

const openEditModal = (guest) => {
  selectedGuest.value = guest
  dialog.value = 'edit'
}

const openDeleteModal = (guest) => {
  selectedGuest.value = guest
  dialog.value = 'delete'
}

const goBack = () => {
  router.push('/client/reserves/list')
}

onMounted(() => {
  fetchGuests()
  startTimer()
})

onUnmounted(() => {
  if (timer) clearInterval(timer)
})
</script>

<template>
  <div class="h-full" style="overflow: hidden">
    <div style="height: 90%; overflow: auto">
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <!-- Content -->
      <div v-else class="px-4 py-6 md:px-28">
        <!-- Booking Info -->
        <div v-if="booking" class="bg-white rounded-xl shadow-md border border-gray-100 p-4 q-mb-md">
          <div class="text-subtitle1 font-bold text-grey-9 q-mb-sm">
            {{ booking.comun_area?.name || 'Area comun' }}
          </div>
          <div class="row items-center text-body2 text-grey-7 q-mb-xs">
            <q-icon name="eva-calendar-outline" size="1rem" class="q-mr-sm" />
            {{ moment(booking.date).format('DD MMM YYYY') }}
          </div>
          <div class="row items-center text-body2 text-grey-7">
            <q-icon name="eva-clock-outline" size="1rem" class="q-mr-sm" />
            {{ booking.time_from }} - {{ booking.time_to }}
          </div>
        </div>

        <!-- Lock Warning -->
        <div v-if="isLockedDisplay" class="bg-orange-1 rounded-xl border border-orange-3 p-3 q-mb-md">
          <div class="row items-center">
            <q-icon name="eva-lock-outline" color="orange-8" size="1.2rem" class="q-mr-sm" />
            <div class="text-body2 text-orange-9">
              <b>Bloqueado:</b> Ya falta 1 hora o menos para el inicio de la reserva.
              No se pueden agregar, editar ni eliminar invitados.
            </div>
          </div>
        </div>

        <!-- Counter -->
        <div class="row justify-between items-center q-mb-md">
          <div class="text-body2 text-grey-7">
            <b>{{ guestCount }}</b> / {{ maxGuests }} invitados
          </div>
          <q-btn color="positive" unelevated rounded size="sm" :disable="!canAddGuest"
            @click="openAddModal()">
            <q-icon name="eva-plus-outline" class="q-mr-xs" />
            Agregar invitado
          </q-btn>
        </div>

        <!-- Guests List -->
        <div v-if="guests.length > 0" class="space-y-3">
          <div v-for="guest in guests" :key="guest.id"
            class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
            <div class="row justify-between items-center">
              <div class="col">
                <div class="text-body1 font-bold text-grey-9">{{ guest.name }}</div>
                <div v-if="guest.dni" class="text-caption text-grey-6">DNI: {{ guest.dni }}</div>
                <div v-if="guest.age" class="text-caption text-grey-6">Edad: {{ guest.age }}</div>
              </div>
              <div class="row items-center q-gutter-xs" v-if="!isLockedDisplay">
                <q-btn flat round icon="eva-edit-2-outline" color="warning" size="sm" @click="openEditModal(guest)">
                  <q-tooltip>Editar</q-tooltip>
                </q-btn>
                <q-btn flat round icon="eva-trash-2-outline" color="negative" size="sm" @click="openDeleteModal(guest)">
                  <q-tooltip>Eliminar</q-tooltip>
                </q-btn>
              </div>
            </div>
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="flex flex-col items-center justify-center py-16">
          <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center q-mb-md">
            <q-icon name="eva-people-outline" size="2.5rem" class="text-blue-500" />
          </div>
          <h3 class="text-body1 font-semibold text-grey-9 q-mb-sm">No hay invitados</h3>
          <p class="text-grey-6 text-center">
            Agrega invitados para esta reserva.
          </p>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <addGuestModal :dialog="dialog === 'add'" :bookingId="bookingId" :currentCount="guestCount"
      @closeModal="dialog = ''" @guestAdded="fetchGuests()" />

    <editGuestModal :dialog="dialog === 'edit'" :guest="selectedGuest"
      @closeModal="dialog = ''" @guestUpdated="fetchGuests()" />

    <deleteGuestModal :dialog="dialog === 'delete'" :guest="selectedGuest"
      @closeModal="dialog = ''" @guestDeleted="fetchGuests()" />
  </div>
</template>
