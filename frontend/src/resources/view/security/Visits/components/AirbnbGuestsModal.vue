<script setup>
import { computed, ref } from 'vue'
import moment from 'moment'
import { Dialog, Notify } from 'quasar'
import { useVisitStore } from '@/services/store/visits.store'

const props = defineProps({
  modelValue: { type: Boolean, default: false },
  rent: { type: Object, default: () => null },
})

const emit = defineEmits(['update:modelValue', 'updated'])

const visitStore = useVisitStore()
const loadingGuestId = ref(null)
const failedPhotos = ref({})
const mediaUrl = import.meta.env.VITE_LARAVEL_MEDIA_URL

const guests = computed(() => props.rent?.guest || [])

const closeModal = () => {
  emit('update:modelValue', false)
}

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000,
  })
}

const formatDate = (date) => {
  if (!date) return ''
  return moment(date).format('DD MMM YYYY')
}

const getGuestPhotoUrl = (guest) => {
  const photo = guest?.photo
  if (!photo) return ''
  if (String(photo).startsWith('http')) return photo
  return `${mediaUrl}/storage/${photo}`
}

const hasGuestPhoto = (guest) => {
  return !!guest?.photo && !failedPhotos.value[guest.id]
}

const onPhotoError = (guestId) => {
  failedPhotos.value = {
    ...failedPhotos.value,
    [guestId]: true,
  }
}

const markGuestArrived = (guest) => {
  Dialog.create({
    title: 'Confirmar llegada',
    message: `¿Marcar a ${guest.fullname} como llegada confirmada?`,
    cancel: true,
    persistent: true,
  }).onOk(() => {
    loadingGuestId.value = guest.id
    visitStore
      .markVisitArrived(guest.id)
      .then((response) => {
        if (response.code !== 200) throw response
        showNotify('primary', response.message || 'Llegada confirmada')
        emit('updated')
        closeModal()
      })
      .catch((err) => {
        const msg = err?.error || err?.message || 'No se pudo actualizar la visita'
        showNotify('negative', msg)
      })
      .finally(() => {

        loadingGuestId.value = null
      })
  })
}
</script>

<template>
  <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)">
    <q-card style="width: 700px; max-width: 95vw; border-radius: 12px; position: relative;">
      <q-card-section class="row items-center justify-between">
        <div>
          <div class="text-h6 text-weight-bold">Huespedes de la reserva</div>
          <div class="text-caption text-grey-7">
            Apt. #{{ rent?.departament?.number || 'N/A' }}
            <template v-if="rent?.init_date">
              · {{ formatDate(rent?.init_date) }}
            </template>
          </div>
        </div>
        <q-btn icon="eva-close" flat round dense @click="closeModal" />
      </q-card-section>

      <q-separator />

      <q-card-section style="max-height: 65vh; overflow: auto;">
        <div v-if="guests.length" class="column q-gutter-sm">
          <div v-for="guest in guests" :key="guest.id" class="guest-row">
            <div class="row justify-between no-wrap">
              <div class="row items-center no-wrap">
                <div class="avatar-box">
                  <img v-if="hasGuestPhoto(guest)" :src="getGuestPhotoUrl(guest)" alt="Foto del huesped"
                    class="avatar-image" @error="onPhotoError(guest.id)" />
                  <span v-else>
                    {{ guest.fullname?.charAt(0)?.toUpperCase() || '?' }}
                  </span>
                </div>
                <div class="q-ml-sm">
                  <div class="text-subtitle2 text-weight-bold">{{ guest.fullname }}</div>
                  <div class="text-caption text-grey-7">DNI: {{ guest.dni || 'N/A' }}</div>
                  <div class="text-caption text-grey-7">
                    {{ formatDate(guest.date) }}
                    <template v-if="guest.hour">· {{ guest.hour }}</template>
                  </div>
                  <div class="text-caption text-grey-7" v-if="guest.arrived_date">
                    Llegada: {{ moment(guest.arrived_date).format('DD/MM/YYYY hh:mm A') }}
                  </div>
                </div>
              </div>

              <div class="row items-end">
                <q-btn color="primary" unelevated no-caps size="sm" :loading="loadingGuestId === guest.id"
                  :disable="loadingGuestId !== null || Number(guest.status) === 2" @click="markGuestArrived(guest)">
                  {{ Number(guest.status) === 2 ? 'Confirmado' : 'Marcar llegado' }}
                </q-btn>
              </div>
              <q-badge :color="guest.status_color" class="text-weight-medium badge__status_modal py-1 pl-8 pr-2">
                {{ guest.status_label }}
              </q-badge>
            </div>
          </div>
        </div>

        <div v-else class="text-center text-grey-7 q-py-xl">
          Esta reserva no tiene huespedes registrados.
        </div>
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<style scoped>
.badge__status_modal {
  position: absolute;
  top: 0;
  right: 0;
  border-radius: 0;
  border-bottom-left-radius: 10px;
}

.guest-row {
  border: 1px solid #e7e7e7;
  border-radius: 10px;
  padding: 10px;
  position: relative;
  overflow: hidden;
}

.avatar-box {
  width: 2.4rem;
  height: 2.4rem;
  border-radius: 0.5rem;
  background: #226fb5;
  color: #fff;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.avatar-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
</style>
