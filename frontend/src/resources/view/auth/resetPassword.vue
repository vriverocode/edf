<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/services/store/auth.services'
import { Notify } from 'quasar'
import bg from '@/assets/img/backgrounds/bg.webp'
import logoWite from '@/assets/img/logo/logo-white.webp'

const route = useRoute()
const router = useRouter()
const authServices = useAuthStore()

const token = ref('')
const email = ref('')
const password = ref('')
const passwordConfirmation = ref('')
const loading = ref(false)
const validatingToken = ref(true)
const tokenValid = ref(false)
const resetSuccess = ref(false)
const isPwd = ref(true)
const isPwdConfirm = ref(true)

const emailRules = [
  val => val && val.length > 0 || 'El correo no puede quedar vacío',
  val => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val) || 'Ingrese un correo válido',
]

const passwordRules = [
  val => val && val.length > 0 || 'La contraseña no puede quedar vacía',
  val => val.length >= 8 || 'Debe tener 8 caracteres mínimo',
]

const passwordConfirmRules = [
  val => val && val.length > 0 || 'Confirme su contraseña',
  val => val === password.value || 'Las contraseñas no coinciden',
]

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 3000,
  })
}

const validateToken = async () => {
  validatingToken.value = true
  try {
    await authServices.validateResetToken(token.value)
    tokenValid.value = true
  } catch (error) {
    tokenValid.value = false
    showNotify('negative', error?.data?.error || 'Token no válido o expirado')
  } finally {
    validatingToken.value = false
  }
}

const submitNewPassword = async () => {
  loading.value = true
  try {
    await authServices.resetPassword({
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })
    resetSuccess.value = true
    showNotify('positive', 'Contraseña actualizada correctamente')
  } catch (error) {
    showNotify('negative', error?.data?.error || 'Error al actualizar la contraseña')
  } finally {
    loading.value = false
  }
}

const goToLogin = () => {
  router.push('/login')
}

onMounted(() => {
  token.value = route.query.token || ''
  if (token.value) {
    validateToken()
  } else {
    validatingToken.value = false
    tokenValid.value = false
    showNotify('negative', 'Token no proporcionado')
  }
})
</script>

<template>
  <div class="h-full bg-white login-root">
    <div class="h-full md:w-1/2 md:mx-auto relative">
      <div class="absolute inset-0 login__bg" :style="{ backgroundImage: 'url(' + bg + ')' }"></div>

      <div class="relative z-10 h-full md:w-full">
        <section class="mt-0 md:pt-12">
          <div class="w-full h-full">
            <div class="mx-auto form__cont md:px-8">
              <div class="w-full h-full">
                <div class="relative md:px-10 px-8 h-full w-full form pt-12 md:pt-0">
                  <img :src="logoWite" alt="logo" class="md:w-1/6 w-2/5 mx-auto mt-12" />

                  <div class="text-white mt-5 text-center" style="font-weight: 600; font-size: 1.4rem">
                    NUEVA CONTRASEÑA
                  </div>

                  <!-- Loading state -->
                  <div v-if="validatingToken" class="w-full mt-6 md:mt-8 text-center">
                    <q-spinner-dots size="40px" color="white" class="q-mt-xl" />
                    <p class="text-white text-subtitle2 mt-4" style="color: #a1a1aa">
                      Validando enlace...
                    </p>
                  </div>

                  <!-- Token invalid -->
                  <div v-else-if="!tokenValid && !resetSuccess" class="w-full mt-6 md:mt-8 text-center">
                    <div class="text-white mb-4" style="font-size: 4rem">⚠️</div>
                    <p class="text-white text-subtitle1 mb-2" style="font-size: 1rem">
                      El enlace no es válido o ha expirado
                    </p>
                    <p class="text-white text-subtitle2" style="font-size: 0.85rem; color: #a1a1aa">
                      Solicite un nuevo enlace de recuperación de contraseña.
                    </p>

                    <div class="md:px-16 px-6 mt-6 flex justify-center">
                      <q-btn
                        flat
                        class="btn__login w-auto md:w-1/2"
                        no-caps
                        size="lg"
                        @click="goToLogin"
                      >
                        <div class="text-h6 text-bold md:px-2 px-12 text-white">Volver al login</div>
                      </q-btn>
                    </div>
                  </div>

                  <!-- Success state -->
                  <div v-else-if="resetSuccess" class="w-full mt-6 md:mt-8 text-center">
                    <div class="text-white mb-4" style="font-size: 4rem">✅</div>
                    <p class="text-white text-subtitle1 mb-2" style="font-size: 1rem">
                      Contraseña actualizada correctamente
                    </p>
                    <p class="text-white text-subtitle2" style="font-size: 0.85rem; color: #a1a1aa">
                      Ya puede iniciar sesión con su nueva contraseña.
                    </p>

                    <div class="md:px-16 px-6 mt-6 flex justify-center">
                      <q-btn
                        flat
                        class="btn__login w-auto md:w-1/2"
                        no-caps
                        size="lg"
                        @click="goToLogin"
                      >
                        <div class="text-h6 text-bold md:px-2 px-12 text-white">Iniciar sesión</div>
                      </q-btn>
                    </div>
                  </div>

                  <!-- Reset form -->
                  <div v-else class="w-full mt-6 md:mt-8">
                    <p class="text-white text-subtitle2 text-center mb-4" style="font-size: 0.9rem">
                     Ingrese su correo electrónico y la nueva contraseña.
                    </p>

                    <q-form @submit="submitNewPassword">
                      <q-input
                        class="auth_input"
                        color="white"
                        autocapitalize="none"
                        v-model="email"
                        :rules="emailRules"
                        rounded
                        standout
                      >
                        <template v-slot:prepend>
                          <div class="pl-2" style="font-size: 1rem; font-weight: 500">Correo</div>
                        </template>
                      </q-input>

                      <q-input
                        class="q-pt-sm auth_input"
                        color="grey-1"
                        v-model="password"
                        :rules="passwordRules"
                        placeholder="••••••••••"
                        :type="isPwd ? 'password' : 'text'"
                        rounded
                        standout
                      >
                        <template v-slot:prepend>
                          <div class="pl-2" style="font-size: 1rem; font-weight: 500">Nueva contraseña</div>
                        </template>
                        <template v-slot:append>
                          <q-icon
                            :name="isPwd ? 'eva-eye-off-outline' : 'eva-eye-outline'"
                            class="cursor-pointer"
                            color="grey-1"
                            @click="isPwd = !isPwd"
                          />
                        </template>
                      </q-input>

                      <q-input
                        class="q-pt-sm auth_input"
                        color="grey-1"
                        v-model="passwordConfirmation"
                        :rules="passwordConfirmRules"
                        placeholder="••••••••••"
                        :type="isPwdConfirm ? 'password' : 'text'"
                        rounded
                        standout
                      >
                        <template v-slot:prepend>
                          <div class="pl-2" style="font-size: 1rem; font-weight: 500">Confirmar contraseña</div>
                        </template>
                        <template v-slot:append>
                          <q-icon
                            :name="isPwdConfirm ? 'eva-eye-off-outline' : 'eva-eye-outline'"
                            class="cursor-pointer"
                            color="grey-1"
                            @click="isPwdConfirm = !isPwdConfirm"
                          />
                        </template>
                      </q-input>

                      <div class="md:px-16 px-6 mt-5 flex justify-center">
                        <q-btn
                          flat
                          class="btn__login w-auto md:w-1/2"
                          no-caps
                          :loading="loading"
                          size="lg"
                          type="submit"
                        >
                          <div class="text-h6 text-bold md:px-2 px-12 text-white">Guardar</div>
                        </q-btn>
                      </div>
                    </q-form>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<style lang="scss">
.login-root {
  backface-visibility: hidden;
  transform: translateZ(0);
  will-change: transform;
}

.login__bg {
  background-size: 100% 100% !important;
  background-position: bottom;
  background-repeat: no-repeat;
  transform: translateZ(0);
  will-change: transform;
}

.auth_input {
  & .q-field__control {
    background: white;
  }

  &.q-field--standout.q-field--highlighted .q-field__control {
    background: white;
  }

  &.q-field--standout .q-field__native {
    color: rgb(0, 0, 0) !important;
    font-size: 1rem !important;

    &::placeholder {
      color: #9b9b9b !important;
    }
  }

  &.q-field--standout .q-icon:before {
    color: darkgray;
  }

  &.q-field--standout .q-field__prepend {
    color: rgb(0, 0, 0) !important;
    font-weight: 500;
    font-size: 0.95rem !important;
  }

  & .q-field__bottom--animated {
    padding-top: 6px;

    & > .col > div {
      background: white;
      width: max-content;
      padding: 0.6rem;
      border-radius: 0.3rem;
    }
  }
}

.btn__login {
  --tw-bg-opacity: 1;
  background-color: #c8a34b;
  box-shadow: 0px 0.2rem 1rem 0px rgba(0, 0, 0, 0.345);
  transition: all 0.5s ease;
  border-radius: 1rem;
  padding: 0.8rem;
  font-weight: 800;
  min-width: 2.5em;
  min-height: 2.6em;

  &:hover {
    --tw-bg-opacity: 1;
    background-color: white;
  }

  & .q-spinner {
    color: white !important;
  }
}

.form {
  display: flex;
  flex-direction: column;
  justify-content: center;
}

@media (max-width: 780px) {
  .btn__login {
    border-radius: 5rem;

    &:hover {
      --tw-bg-opacity: 1;
      color: white;
      --tw-bg-opacity: 1;
      background-color: rgb(2 132 199 / var(--tw-bg-opacity, 1));
    }
  }
}
</style>
