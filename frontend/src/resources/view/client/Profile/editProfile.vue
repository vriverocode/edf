<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/services/store/auth.services'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'

const router = useRouter()
const $q = useQuasar()
const authStore = useAuthStore()

const form = ref({
  email: authStore.user.email || '',
  phone: authStore.user.phone || '',
  phone: authStore.user.dni || '',
  password: '',
  password_confirmation: '',
})

const showPassword = ref(false)
const showConfirmPassword = ref(false)
const loading = ref(false)

const onSubmit = () => {
  loading.value = true

  const payload = {
    email: form.value.email,
    phone: form.value.phone,
    dni: form.value.dni,
  }

  if (form.value.password) {
    payload.password = form.value.password
    payload.password_confirmation = form.value.password_confirmation
  }

  authStore
    .updateProfile(payload)
    .then(() => {
      $q.notify({
        type: 'positive',
        message: 'Datos actualizados correctamente',
      })
      router.go(-1)
    })
    .catch((error) => {
      $q.notify({
        type: 'negative',
        message: error?.data?.error || error?.data?.message || 'Error al actualizar los datos',
      })
    })
    .finally(() => {
      loading.value = false
    })
}
</script>

<template>
  <div class="md:px-20 px-2 pt-5">
    <q-form @submit="onSubmit">
      <div class="row w-full">
        <div class="col-md-6 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Correo electrónico
          </div>
          <q-input
            v-model="form.email"
            borderless
            dense
            placeholder="Ej. usuario@gmail.com"
            clearable
            class="form__inputsCR mt-2"
            color="primary"
            lazy-rules
            :rules="[val => val && val.length > 0 || 'Por favor ingresa un correo']"
          >
            <template v-slot:prepend>
              <q-icon name="eva-email-outline" color="grey-7" />
            </template>
          </q-input>
        </div>
        <div class="col-md-6 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            DNI
          </div>
          <q-input
            v-model="form.dni"
            borderless
            dense
            placeholder="Ej. 45288179"
            clearable
            class="form__inputsCR mt-2"
            color="primary"
            lazy-rules
            :rules="[val => val && val.length >= 8 || 'Por favor ingresa un DNI valido']"
          >
            <template v-slot:prepend>
              <q-icon name="eva-person-outline" color="grey-7" />
            </template>
          </q-input>
        </div>
        <div class="col-md-6 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Número de teléfono
          </div>
          <q-input
            v-model="form.phone"
            borderless
            dense
            placeholder="Ej. 999888777"
            clearable
            class="form__inputsCR mt-2"
            color="primary"
            lazy-rules
            :rules="[val => val && val.length >= 7 || 'Por favor ingresa un teléfono válido']"
          >
            <template v-slot:prepend>
              <q-icon name="eva-phone-outline" color="grey-7" />
            </template>
          </q-input>
        </div>

        <div class="col-md-6 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Nueva contraseña <span class="text-weight-regular text-grey-7">(Opcional)</span>
          </div>
          <q-input
            v-model="form.password"
            borderless
            dense
            :type="showPassword ? 'text' : 'password'"
            placeholder="Mínimo 8 caracteres"
            clearable
            class="form__inputsCR mt-2"
            color="primary"
            lazy-rules
            :rules="[val => !val || val.length >= 8 || 'Mínimo 8 caracteres']"
          >
            <template v-slot:prepend>
              <q-icon name="eva-lock-outline" color="grey-7" />
            </template>
            <template v-slot:append>
              <q-icon
                :name="showPassword ? 'eva-eye-outline' : 'eva-eye-off-outline'"
                class="cursor-pointer"
                @click="showPassword = !showPassword"
                color="grey-7"
              />
            </template>
          </q-input>
        </div>

        <div class="col-md-6 col-12 my-1 px-2 md:px-12">
          <div class="text-subtitle2 text-bold text-black">
            Confirmar contraseña
          </div>
          <q-input
            v-model="form.password_confirmation"
            borderless
            dense
            :type="showConfirmPassword ? 'text' : 'password'"
            placeholder="Repite tu contraseña"
            clearable
            class="form__inputsCR mt-2"
            color="primary"
            lazy-rules
            :rules="[val => !form.password || val === form.password || 'Las contraseñas no coinciden']"
          >
            <template v-slot:prepend>
              <q-icon name="eva-lock-outline" color="grey-7" />
            </template>
            <template v-slot:append>
              <q-icon
                :name="showConfirmPassword ? 'eva-eye-outline' : 'eva-eye-off-outline'"
                class="cursor-pointer"
                @click="showConfirmPassword = !showConfirmPassword"
                color="grey-7"
              />
            </template>
          </q-input>
        </div>

        <div class="col-12 pb-8 mt-6 px-2 md:px-12 flex items-center justify-between">
          <!-- <div class="flex items-center" style="width: 50%; box-sizing: border-box;">
            <q-btn color="grey-9" style="border-radius: 0.5rem;" @click="router.go(-1)">
              <div class="px-8 py-1">
                Volver
              </div>
            </q-btn>
          </div> -->
          <div class="flex items-center justify-end" style="width: 100%; box-sizing: border-box;">
            <q-btn color="primary" style="border-radius: 0.5rem;" type="submit" :loading="loading">
              <div class="px-8 py-1">
                Guardar
              </div>
            </q-btn>
          </div>
        </div>
      </div>
    </q-form>
  </div>
</template>

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
