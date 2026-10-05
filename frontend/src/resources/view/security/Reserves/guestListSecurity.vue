<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import moment from 'moment'
import { Dialog, Notify } from 'quasar'
import { useGuestListStore } from '@/services/store/guestList.store'

const route = useRoute()
const router = useRouter()
const guestListStore = useGuestListStore()

const ready = ref(false)
const loadError = ref(false)
const booking = ref(null)
const guests = ref([])
const search = ref('')
const loadingGuestId = ref(null)

const bookingId = route.params.id

const arrivedCount = computed(() => guests.value.filter((guest) => Number(guest.status) === 2).length)

const filteredGuests = computed(() => {
  const needle = search.value.trim().toLowerCase()
  if (!needle) return guests.value
  return guests.value.filter(
    (guest) =>
      String(guest.name ?? '').toLowerCase().includes(needle) ||
      String(guest.dni ?? '').includes(needle)
  )
})

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000,
  })
}

const formatDate = (date) => (date ? moment(date).format('DD MMM YYYY') : '')
const formatArrival = (date) => (date ? moment(date).format('DD MMM YYYY HH:mm') : '')

const load = () => {
  ready.value = false
  loadError.value = false
  guestListStore
    .getGuestsForSecurity(bookingId)
    .then((response) => {
      if (response.code !== 200) throw response
      booking.value = response.data.booking
      guests.value = response.data.guests || []
      ready.value = true
    })
    .catch((err) => {
      loadError.value = true
      ready.value = true
      showNotify('negative', typeof err === 'string' ? err : 'No se pudo cargar la lista de invitados')
    })
}

const toggleArrived = (guest) => {
  const isArrived = Number(guest.status) === 2
  Dialog.create({
    title: isArrived ? 'Desmarcar llegada' : 'Confirmar llegada',
    message: isArrived
      ? `¿Desmarcar a ${guest.name}? El invitado volverá a estado pendiente.`
      : `¿Marcar a ${guest.name} como llegada confirmada?`,
    cancel: true,
    persistent: true,
    ok: { label: isArrived ? 'Desmarcar' : 'Marcar llegado', color: isArrived ? 'negative' : 'primary' },
  }).onOk(() => {
    loadingGuestId.value = guest.id
    guestListStore
      .toggleGuestArrived(guest.id)
      .then((response) => {
        if (response.code !== 200) throw response
        const updated = response.data.guest
        const index = guests.value.findIndex((item) => item.id === guest.id)
        if (index !== -1 && updated) guests.value.splice(index, 1, updated)
        showNotify('primary', response.data.message || 'Invitado actualizado')
      })
      .catch((err) => {
        showNotify('negative', typeof err === 'string' ? err : 'No se pudo actualizar la llegada')
      })
      .finally(() => {
        loadingGuestId.value = null
      })
  })
}

onMounted(() => {
  load()
})
</script>

<template>
  <div class="h-full">
    <template v-if="ready && booking">
      <div class="h-full" style="overflow: hidden;">
        <!-- Datos de la reserva -->
        <div class="px-4 md:mx-24 md:px-12 pt-3">
          <div class="listGuest-container q-pa-md" style="border-radius: 12px;">
            <div class="row items-center justify-between q-mb-sm">
              <div class="text-subtitle1 text-bold">
                #{{ booking.booking_number }} · {{ booking.comun_area?.name }}
              </div>
              <q-badge :color="booking.status_color" :label="booking.status_label" />
            </div>
            <div class="text-body2 text-grey-8">
              {{ formatDate(booking.date) }}
              <template v-if="booking.time_from"> · {{ booking.time_from }}<template v-if="booking.time_to"> -
                  {{ booking.time_to }}</template></template>
            </div>
            <div class="text-body2 text-grey-8" v-if="booking.user">
              Residente: {{ booking.user.name }} {{ booking.user.lastname }}
              <template v-if="booking.departament?.number"> · Apt. {{ booking.departament.number }}</template>
            </div>
            <div class="q-mt-sm flex justify-end w-full">
              <q-badge color="primary" class="q-pa-sm text-subtitle2">
                {{ arrivedCount }} / {{ guests.length }} llegaron
              </q-badge>
            </div>
          </div>
        </div>

        <!-- Buscador -->
        <div class="px-4 md:mx-24 md:px-12 q-mt-sm">
          <q-input v-model="search" borderless dense clearable color="primary"
            placeholder="Buscar por nombre o DNI" class="form__inputsCR">
            <template v-slot:prepend>
              <q-icon name="eva-search-outline" />
            </template>
          </q-input>
        </div>

        <!-- Lista de invitados -->
        <template v-if="filteredGuests.length > 0">
          <div class="mt-3 md:mt-4" style="height: 62%; overflow: auto">
            <div class="px-4 md:mx-24 md:px-12">
              <div v-for="guest in filteredGuests" :key="guest.id" class="my-2 listGuest-container"
                style="border-radius: 12px !important;">
                <div class="visitListContainer flex  justify-between q-pa-sm">
                  <div class="flex items-center">
                    <div class="guestAvatar flex flex-center text-white text-bold">
                      {{ guest.name?.charAt(0)?.toUpperCase() || '?' }}
                    </div>
                    <div class="ml-2">
                      <div class="text-subtitle1 text-bold text-black" style="line-height: 1.5;">
                        {{ guest.name }}
                      </div>
                      <div class="text-body2 text-grey-8" v-if="guest.dni">DNI: {{ guest.dni }}</div>
                      <div class="text-caption text-grey-6" v-if="guest.age">Edad: {{ guest.age }} años</div>
                      <div class="text-caption text-primary" v-if="Number(guest.status) === 2">
                        Llegó: {{ formatArrival(guest.updated_at) }}
                      </div>
                    </div>
                  </div>
                  <div class="flex items-start pt-1">
                    <q-badge :color="guest.status_color" :label="guest.status_label" class="q-pa-xs" />
                  </div>
                  <div class="w-full flex justify-end py-1">
                    <q-btn v-if="Number(guest.status) !== 2" color="primary" unelevated size="0.85rem"
                      style="border-radius: 0.5rem;" no-caps icon="eva-checkmark-outline"
                      :loading="loadingGuestId === guest.id" :disable="loadingGuestId !== null"
                      @click="toggleArrived(guest)">
                      Marcar llegado
                    </q-btn>
                    <q-btn v-else outline color="negative" size="0.85rem" style="border-radius: 0.5rem;" no-caps
                      icon="eva-close-outline" :loading="loadingGuestId === guest.id"
                      :disable="loadingGuestId !== null" @click="toggleArrived(guest)">
                      Desmarcar
                    </q-btn>
                  </div>
                  
                </div>
              </div>
            </div>
          </div>
        </template>

        <template v-else>
          <div class="flex flex-center column empty-results px-4" style="min-height: 40vh;">
            <q-icon name="eva-people-outline" size="4rem" color="grey-5" class="q-mb-md" />
            <div style="font-size: 1.2rem; font-weight: 600;" class="text-grey-7 text-center q-mb-sm">
              {{ search ? 'No se encontraron invitados' : 'No hay invitados registrados' }}
            </div>
            <div class="text-grey-6 text-center q-mb-lg">
              {{ search ? 'Prueba con otro nombre o DNI.' : 'Esta reserva aún no tiene invitados en su lista.' }}
            </div>
            <q-btn color="primary" outline style="border-radius: 0.5rem;" @click="load()">
              Actualizar
            </q-btn>
          </div>
        </template>
      </div>
    </template>

    <template v-else-if="ready && loadError">
      <div class="h-full flex flex-center column px-4">
        <q-icon name="eva-alert-circle-outline" size="4rem" color="negative" class="q-mb-md" />
        <div class="text-subtitle1 text-grey-8 q-mb-md">No se pudo cargar la lista de invitados</div>
        <div class="q-gutter-sm flex">
          <q-btn color="primary" unelevated label="Reintentar" no-caps @click="load()" />
          <q-btn outline color="primary" label="Volver" no-caps @click="router.back()" />
        </div>
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
.guestAvatar {
  height: 2.8rem;
  width: 2.8rem;
  background: #1976d2;
  border-radius: 0.5rem;
  font-size: 1.5rem;
}

.visitListContainer {
  display: flex;
  align-items: start;
  justify-content: space-between;
}

.listGuest-container {
  box-shadow: 0px 5px 5px 0px rgba(54, 54, 54, 0.082) !important;
  border: 1px solid #e0e0e0;
}
</style>

<style lang="scss">
.form__inputsCR {
  & .q-field__inner {
    box-shadow: 0px 3px 5px 0px #bfbfbfa3;
    border-radius: 0.8rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 2rem;
  }
}

@media (max-width: 780px) {
  .form__inputsCR {
    & .q-field__inner {
      padding: 0px 1rem;
    }
  }
}
</style>
