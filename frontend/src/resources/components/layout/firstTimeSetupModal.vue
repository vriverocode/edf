<script setup>
import { ref, watch } from 'vue';
import { useAuthStore } from '@/services/store/auth.services';
import { useUserStore } from '@/services/store/users.store';
import { useQuasar } from 'quasar';

const emit = defineEmits(['completed'])
const props = defineProps({
  dialog: Boolean,
})

const $q = useQuasar()
const authStore = useAuthStore()
const userStore = useUserStore()
const dialog = ref(props.dialog)
const loading = ref(false)
const showPassword = ref(false)
const showConfirmPassword = ref(false)

const form = ref({
  phone: '',
  email:'',
  password: '',
  password_confirmation: '',
})

watch(() => props.dialog, (newValue) => {
  dialog.value = newValue
})

const isFormValid = () => {
  if (!form.value.phone || form.value.phone.length < 7) {
    $q.notify({ color: 'negative', message: 'Ingrese un número de teléfono válido', position: 'top' })
    return false
  }
  if (!form.value.password || form.value.password.length < 8) {
    $q.notify({ color: 'negative', message: 'La contraseña debe tener al menos 8 caracteres', position: 'top' })
    return false
  }
  if (form.value.password !== form.value.password_confirmation) {
    $q.notify({ color: 'negative', message: 'Las contraseñas no coinciden', position: 'top' })
    return false
  }
  return true
}

const submit = async () => {
  if (!isFormValid()) return

  loading.value = true
  try {
    await userStore.completeFirstTime({
      email: form.value.email,
      phone: form.value.phone,
      password: form.value.password,
      password_confirmation: form.value.password_confirmation,
    })

    $q.notify({ color: 'positive', message: '¡Datos actualizados correctamente!', position: 'bottom', icon: 'eva-checkmark-circle-outline' })
    // Refresh user data so is_first_time is now 0
    await authStore.currentUser()
    emit('completed')
  } catch (error) {
    console.log(error)
    const msg = typeof error === 'string' ? error : 'Ocurrió un error, intente nuevamente'
    $q.notify({ color: 'negative', message: msg, position: 'bottom' })
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <q-dialog v-model="dialog" persistent backdrop-filter="blur(6px)" class="firstTimeSetupDialog" no-esc-dismiss no-backdrop-dismiss>
    <q-card class="dialog_card">
      <!-- Header -->
      <q-card-section class="dialog_header">
        <div class="header_icon_wrapper">
          <q-icon name="eva-shield-outline" size="2.4rem" color="black" />
        </div>
        <div class="text-h5 text-center text-black header_title">
          ¡Bienvenido!
        </div>
        <p class="text-center text-black header_subtitle">
          Para continuar, configura tu contraseña y número de teléfono.
        </p>
      </q-card-section>

      <!-- Form -->
      <q-card-section class="dialog_body">
        <q-form @submit.prevent="submit" class="form_container">
          <!-- Phone -->
          <div class="field_group">
            <label class="field_label">Correo electrónico</label>
            <q-input
              v-model="form.email"
              outlined
              dense
              placeholder="Ej: usuario@gmail.com"
              class="field_input"
              :rules="[val => !!val || 'Campo requerido']"
              lazy-rules
            >
              <template v-slot:prepend>
                <q-icon name="eva-email-outline" color="grey-7" />
              </template>
            </q-input>
          </div>
          <div class="field_group">
            <label class="field_label">Número de teléfono</label>
            <q-input
              v-model="form.phone"
              outlined
              dense
              placeholder="Ej: 999888777"
              class="field_input"
              :rules="[val => !!val || 'Campo requerido']"
              lazy-rules
            >
              <template v-slot:prepend>
                <q-icon name="eva-phone-outline" color="grey-7" />
              </template>
            </q-input>
          </div>

          <!-- Password -->
          <div class="field_group">
            <label class="field_label">Nueva contraseña</label>
            <q-input
              v-model="form.password"
              outlined
              dense
              :type="showPassword ? 'text' : 'password'"
              placeholder="Mínimo 8 caracteres"
              class="field_input"
              :rules="[val => !!val && val.length >= 8 || 'Mínimo 8 caracteres']"
              lazy-rules
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

          <!-- Confirm Password -->
          <div class="field_group">
            <label class="field_label">Confirmar contraseña</label>
            <q-input
              v-model="form.password_confirmation"
              outlined
              dense
              :type="showConfirmPassword ? 'text' : 'password'"
              placeholder="Repita su contraseña"
              class="field_input"
              :rules="[val => val === form.password || 'Las contraseñas no coinciden']"
              lazy-rules
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

          <!-- Submit Button -->
          <q-btn
            type="submit"
            color="primary"
            label="Guardar y continuar"
            unelevated
            class="submit_btn full-width"
            :loading="loading"
            no-caps
          >
            <template v-slot:loading>
              <q-spinner-dots color="white" size="1.4em" />
            </template>
          </q-btn>
        </q-form>
      </q-card-section>
    </q-card>
  </q-dialog>
</template>

<style lang="scss">
.firstTimeSetupDialog {
  .q-dialog__inner--minimized {
    padding: 0px;
  }

  .dialog_card {
    width: 92%;
    max-width: 420px;
    border-radius: 1.4rem !important;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  }

  .dialog_header {
    //background: linear-gradient(135deg, #0e344c 0%, #1a5276 50%, #1e6f8e 100%);
    padding: 2rem 1.5rem 1.6rem;
    position: relative;

    &::after {
      content: '';
      position: absolute;
      bottom: -1px;
      left: 0;
      right: 0;
      height: 20px;
      background: white;
      border-radius: 1.4rem 1.4rem 0 0;
    }
  }

  .header_icon_wrapper {
    width: 60px;
    height: 60px;
    margin: 0 auto 0.8rem;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 50%;
    backdrop-filter: blur(8px);
  }

  .header_title {
    font-weight: 700;
    letter-spacing: -0.5px;
    margin-bottom: 0.3rem;
    font-size: 1.4rem;
  }

  .header_subtitle {
    opacity: 0.85;
    font-size: 0.88rem;
    margin: 0;
    line-height: 1.4;
  }

  .dialog_body {
    padding: 1.2rem 1.5rem 1.8rem;
  }

  .form_container {
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
  }

  .field_group {
    display: flex;
    flex-direction: column;
  }

  .field_label {
    font-size: 0.82rem;
    font-weight: 600;
    color: #374151;
    margin-bottom: 0.3rem;
  }

  .field_input {
    .q-field__control {
      border-radius: 0.7rem;
    }
  }

  .submit_btn {
    margin-top: 0.6rem;
    padding: 0.75rem;
    border-radius: 0.8rem;
    font-size: 1rem;
    font-weight: 600;
    //background: linear-gradient(135deg, #0e344c 0%, #1a5276 100%) !important;
    color: white;
    letter-spacing: 0.2px;
    transition: all 0.3s ease;

    &:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 20px rgba(14, 52, 76, 0.4);
    }

    &:active {
      transform: translateY(0);
    }
  }
}
</style>
