<script setup>
import { ref } from 'vue'
import { useAuthStore } from '@/services/store/auth.services'
import { useRouter } from 'vue-router'
import { Notify } from 'quasar'
import bg from '@/assets/img/backgrounds/bg.webp'
import logoWite from '@/assets/img/logo/logo-white.webp'

const authServices = useAuthStore()
const router = useRouter()

const email = ref('')
const loading = ref(false)
const sent = ref(false)

const emailRules = [
  val => val && val.length > 0 || 'El correo no puede quedar vacío',
  val => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val) || 'Ingrese un correo válido',
]

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 3000,
  })
}

const submitEmail = async () => {
  loading.value = true
  try {
    await authServices.forgotPassword(email.value)
    sent.value = true
    showNotify('positive', 'Correo enviado. Revise su bandeja de entrada.')
  } catch (error) {
    showNotify('negative', error?.data?.error || 'Error al enviar el correo')
  } finally {
    loading.value = false
  }
}

const goToLogin = () => {
  router.push('/login')
}
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
                    RECUPERAR CONTRASEÑA
                  </div>

                  <div v-if="!sent" class="w-full mt-6 md:mt-8">
                    <p class="text-white text-subtitle2 text-center mb-4" style="font-size: 0.9rem">
                      Ingrese su correo electrónico y le enviaremos las instrucciones para restablecer su contraseña.
                    </p>

                    <q-form @submit="submitEmail">
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

                      <div class="md:px-16 px-6 mt-5 flex justify-center">
                        <q-btn
                          flat
                          class="btn__login w-auto md:w-1/2"
                          no-caps
                          :loading="loading"
                          size="lg"
                          type="submit"
                        >
                          <div class="text-h6 text-bold md:px-2 px-12 text-white">Enviar</div>
                        </q-btn>
                      </div>
                    </q-form>

                    <div class="text-white text-center mt-10 text-bold cursor-pointer" @click="router.go(-1)" style="text-decoration: underline;">
                      Volver al inicio
                    </div>
                  </div>

                  <div v-else class="w-full mt-6 md:mt-8 text-center">
                    <div class="text-white mb-4" style="font-size: 4rem">✉️</div>
                    <p class="text-white text-subtitle1 mb-2" style="font-size: 1rem">
                      Correo enviado correctamente
                    </p>
                    <p class="text-white text-subtitle2" style="font-size: 0.85rem; color: #a1a1aa">
                      Revise la bandeja de entrada de <strong>{{ email }}</strong> y siga las instrucciones para restablecer su contraseña.
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
