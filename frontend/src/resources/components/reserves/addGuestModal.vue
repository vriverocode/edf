<script setup>
import { ref, watch } from 'vue'
import { useGuestListStore } from '@/services/store/guestList.store'
import { Notify } from 'quasar'

const emit = defineEmits(['closeModal', 'guestAdded'])
const guestListStore = useGuestListStore()

const props = defineProps({
  dialog: Boolean,
  bookingId: Number,
  currentCount: Number,
})

const dialog = ref(props.dialog)
const loading = ref(false)
const form = ref({
  name: '',
  dni: '',
  age: null,
})

const hideModal = () => {
  emit('closeModal')
  resetForm()
}

const resetForm = () => {
  form.value = { name: '', dni: '', age: null }
}

const addGuest = () => {
  loading.value = true
  guestListStore.addGuest(props.bookingId, form.value)
    .then(() => {
      loading.value = false
      emit('guestAdded')
      emit('closeModal')
      resetForm()
    })
    .catch((error) => {
      loading.value = false
      Notify.create({ color: 'negative', message: error || 'Error al agregar invitado', timeout: 2000 })
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
            Agregar invitado
          </div>
        </q-card-section>
        <section class="content__modalSectionRifa mt-3">
          <q-card-section class="q-pt-none q-px-sm">
            <div class="px-2">
              <div class="q-mb-sm">
                <div class="text-body2 text-grey-7 q-mb-xs">Nombre *</div>
                <q-input v-model="form.name" outlined dense placeholder="Nombre completo" />
              </div>
              <div class="q-mb-sm">
                <div class="text-body2 text-grey-7 q-mb-xs">DNI</div>
                <q-input v-model="form.dni" outlined dense placeholder="DNI (opcional)" />
              </div>
              <div class="q-mb-sm">
                <div class="text-body2 text-grey-7 q-mb-xs">Edad</div>
                <q-input v-model.number="form.age" outlined dense type="number" placeholder="Edad (opcional)" />
              </div>
            </div>
          </q-card-section>
        </section>
      </div>
      <section class="pb-5" style="border-top: 1px solid lightgray">
        <div class="flex justify-evenly mt-4">
          <q-btn label="Cancelar" unelevated class="q-mx-sm" color="primary" outline
            style="border-radius: 0.8rem; padding: 0 2rem !important; font-size: 0.9rem" @click="hideModal()" />
          <q-btn label="Agregar" unelevated class="q-mx-sm" color="positive" outline
            style="border-radius: 0.8rem; padding: 0 2rem !important; font-size: 0.9rem" :loading="loading"
            @click="addGuest()" :disable="!form.name" />
        </div>
      </section>
    </q-card>
  </q-dialog>
</template>
