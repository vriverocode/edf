<script setup>
import { ref, watch } from 'vue'
import { useGuestListStore } from '@/services/store/guestList.store'
import { Notify } from 'quasar'

const emit = defineEmits(['closeModal', 'guestDeleted'])
const guestListStore = useGuestListStore()

const props = defineProps({
  dialog: Boolean,
  guest: Object,
})

const dialog = ref(props.dialog)
const loading = ref(false)

const hideModal = () => {
  emit('closeModal')
}

const deleteGuest = () => {
  loading.value = true
  guestListStore.deleteGuest(props.guest.id)
    .then(() => {
      loading.value = false
      emit('guestDeleted')
      emit('closeModal')
    })
    .catch((error) => {
      loading.value = false
      Notify.create({ color: 'negative', message: error || 'Error al eliminar invitado', timeout: 2000 })
    })
}

watch(() => props.dialog, (newValue) => {
  dialog.value = newValue
})
</script>

<template>
  <q-dialog v-model="dialog" class="createPayMethodDialog" persistent backdrop-filter="blur(0.5px)">
    <q-card class="dialog_document public" style="border-radius: 1rem">
      <div>
        <q-card-section class="q-px-none">
          <div class="text-h6 text-center text-black pb-2" style="border-bottom: 1px solid lightgray">
            Eliminar invitado
          </div>
        </q-card-section>
        <section class="content__modalSectionRifa mt-3">
          <q-card-section class="q-pt-none q-px-sm">
            <div class="px-2 text-center">
              <div class="text-h6 text-black">
                ¿Deseas eliminar al invitado <b class="text-primary">{{ guest?.name }}</b>?
              </div>
              <div class="text-grey-7 q-mt-sm">
                Esta acción no se puede deshacer.
              </div>
            </div>
          </q-card-section>
        </section>
      </div>
      <section class="pb-5" style="border-top: 1px solid lightgray">
        <div class="flex justify-evenly mt-4">
          <q-btn label="Cancelar" unelevated class="q-mx-sm" color="primary" outline
            style="border-radius: 0.8rem; padding: 0 2rem !important; font-size: 0.9rem" @click="hideModal()" />
          <q-btn label="Eliminar" unelevated class="q-mx-sm" color="negative" outline
            style="border-radius: 0.8rem; padding: 0 2rem !important; font-size: 0.9rem" :loading="loading"
            @click="deleteGuest()" />
        </div>
      </section>
    </q-card>
  </q-dialog>
</template>
