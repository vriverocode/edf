<script setup>
import { ref, watch } from 'vue'
import { useUserStore } from '@/services/store/users.store'
import { Notify } from 'quasar'

const emit = defineEmits(['closeModal', 'updateList'])
const props = defineProps({
  dialog: Boolean,
  user: Object
})
const userStore = useUserStore()
const loading = ref(false)
const dialog = ref(props.dialog)
const password = ref('')

const hideModal = () => {
  password.value = ''
  emit('closeModal')
}

const updateList = () => {
  hideModal()
  emit('updateList')
}

const resetUser = () => {
  loading.value = true
  userStore.resetUser({ id: props.user.id, password: password.value || '12345678' })
    .then(() => {
      showNotify('positive', 'Usuario reseteado correctamente')
      updateList()
    })
    .catch((error) => {
      console.error(error)
      showNotify('negative', error || 'Error al resetear usuario')
    })
    .finally(() => {
      loading.value = false
    })
}

const showNotify = (type, text) => {
  Notify.create({ color: type, message: text, timeout: 2000 })
}

watch(() => props.dialog, (newValue) => {
  dialog.value = newValue
})
</script>

<template>
  <q-dialog v-model="dialog" persistent backdrop-filter="blur(0.5px)">
    <q-card class="w-full" style="border-radius:1rem; max-width: 28rem;">
      <div class="px-5 py-4">
        <div class="text-h6 text-primary text-bold text-center">
          Reset de usuario
        </div>
        <div class="text-body2 text-grey-7 text-center q-mt-sm">
          Se reseteará el usuario <b>{{ user.name }}</b>
        </div>
        <div class="text-caption text-grey-6 text-center q-mt-xs">
          La contraseña será <b>12345678</b> si no se ingresa una nueva.
        </div>

        <q-input
          v-model="password"
          label="Nueva contraseña"
          placeholder="12345678"
          dense
          borderless
          class="form__inputsR q-mt-lg"
          hint="Dejar vacío para usar la contraseña por defecto"
        />

        <section class="mt-5 flex justify-between px-2">
          <q-btn color="primary" label="Cancelar" @click="hideModal" flat />
          <q-btn color="negative" label="Resetear" @click="resetUser" :loading="loading" />
        </section>
      </div>
    </q-card>
  </q-dialog>
</template>

<style lang="scss">
.form__inputsR {
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
}
</style>
