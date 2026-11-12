<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Notify } from 'quasar'
import { useAuthStore } from '@/services/store/auth.services'

const route = useRoute()
const router = useRouter()
const authServices = useAuthStore()

const token = ref(String(route.query.token || ''))
const email = ref(String(route.query.email || ''))

const validating = ref(true)
const valid = ref(false)
const completed = ref(false)
const loading = ref(false)
const errorMessage = ref('')

const user = reactive({
  name: '',
  email: '',
  unidad: '',
  urbanismo: '',
})

const password = ref('')
const passwordConfirmation = ref('')
const isPwd = ref(true)
const isPwdConfirm = ref(true)

const passwordRules = [
  (val) => (val && val.length > 0) || 'Ingresa una contraseña',
  (val) => (val && val.length >= 8) || 'Debe tener 8 caracteres mínimo',
]

const confirmRules = [
  (val) => (val && val.length > 0) || 'Confirma tu contraseña',
  (val) => val === password.value || 'Las contraseñas no coinciden',
]

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 3000,
  })
}

const firstError = (error, fallback) =>
  error?.data?.error ||
  error?.data?.errors?.password?.[0] ||
  error?.data?.errors?.token?.[0] ||
  error?.data?.message ||
  fallback

const togglePwd = () => {
  isPwd.value = !isPwd.value
}

const togglePwdConfirm = () => {
  isPwdConfirm.value = !isPwdConfirm.value
}

const validateLink = async () => {
  if (!token.value || !email.value) {
    validating.value = false
    valid.value = false
    errorMessage.value = 'El enlace está incompleto. Solicita uno nuevo a la administración.'
    return
  }

  try {
    const response = await authServices.validateActivation({
      token: token.value,
      email: email.value,
    })

    if (response?.code !== 200) {
      throw response
    }

    const data = response?.data ?? {}
    user.name = data.name || ''
    user.email = data.email || email.value
    user.unidad = data.unidad || ''
    user.urbanismo = data.urbanismo?.nombre || ''
    email.value = user.email
    valid.value = true
  } catch (error) {
    valid.value = false
    errorMessage.value = firstError(error, 'El enlace no es válido o ha expirado.')
  } finally {
    validating.value = false
  }
}

const submit = async () => {
  if (loading.value) return

  loading.value = true

  try {
    const response = await authServices.completeActivation({
      token: token.value,
      email: email.value,
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    })

    if (response?.code !== 200) {
      throw response
    }

    const sessionToken = response?.data?.token

    if (sessionToken) {
      authServices.saveToken(sessionToken, { persist: true })

      try {
        await authServices.currentUser()
      } catch (sessionError) {
        console.error('No se pudo cargar el perfil tras la activación:', sessionError)
      }
    }

    completed.value = true
    showNotify('positive', 'Cuenta activada correctamente')

    setTimeout(() => {
      router.push(sessionToken ? '/dashboard' : '/login')
    }, 1600)
  } catch (error) {
    showNotify('negative', firstError(error, 'No se pudo activar la cuenta'))
  } finally {
    loading.value = false
  }
}

onMounted(validateLink)
</script>

<template>
  <div
    class="auth-login relative flex h-full w-full justify-center overflow-x-hidden overflow-y-auto bg-brand-cream pb-[env(safe-area-inset-bottom,0px)] font-sans"
  >
    <span
      class="pointer-events-none fixed -right-[110px] -top-[120px] z-0 h-[300px] w-[300px] rounded-full bg-brand-green/[0.08] blur-[2px]"
      aria-hidden="true"
    ></span>
    <span
      class="pointer-events-none fixed -bottom-[140px] -left-[120px] z-0 h-[340px] w-[340px] rounded-full bg-brand-terracotta/[0.07] blur-[2px]"
      aria-hidden="true"
    ></span>

    <div class="relative z-[1] w-full max-w-[430px] md:my-auto">
      <header
        class="relative isolate overflow-hidden rounded-b-[2rem] bg-brand-green px-6 pb-[5.75rem] pt-[calc(1.75rem_+_env(safe-area-inset-top,0px))] text-white md:rounded-b-none md:rounded-t-[1.75rem] md:px-8 md:pb-[6.25rem] md:pt-9"
      >
        <span
          class="pointer-events-none absolute -right-[60px] -top-[80px] -z-10 h-[210px] w-[210px] rounded-full bg-brand-sage/30"
          aria-hidden="true"
        ></span>
        <span
          class="pointer-events-none absolute -bottom-[70px] -left-[80px] -z-10 h-[180px] w-[180px] rounded-full bg-white/5"
          aria-hidden="true"
        ></span>

        <div class="inline-flex items-center gap-[0.65rem]">
          <div class="grid h-11 w-11 place-items-center rounded-xl bg-white text-brand-green shadow-lg" aria-hidden="true">
            <q-icon name="eva-home-outline" size="20px" />
          </div>
          <span class="text-xl font-extrabold tracking-tight text-white">easycondo</span>
        </div>

        <h1 class="mt-[1.35rem] text-[clamp(1.6rem,5vw,2rem)] font-extrabold leading-[1.15] tracking-tight">
          Activa tu cuenta
        </h1>
        <p class="mt-2 max-w-[19rem] text-[0.95rem] leading-relaxed text-[#CBD8C8]">
          Crea tu contraseña definitiva para ingresar a la comunidad.
        </p>
      </header>

      <section
        class="relative z-[2] -mt-[4.5rem] mx-4 mb-4 rounded-3xl bg-white p-5 shadow-[0_24px_48px_rgba(34,56,41,0.14)] md:mx-0 md:mt-[-4.75rem] md:p-6"
      >
        <div v-if="validating" class="flex flex-col items-center justify-center gap-3 py-10 text-brand-muted">
          <q-spinner color="primary" size="32px" />
          <p class="text-sm font-semibold">Validando enlace...</p>
        </div>

        <div v-else-if="!valid" class="flex flex-col items-center gap-3 py-6 text-center">
          <q-icon name="eva-alert-circle-outline" class="text-[2.75rem] text-brand-terracotta" />
          <p class="max-w-[20rem] text-sm font-semibold leading-relaxed text-brand-ink">
            {{ errorMessage }}
          </p>
          <q-btn
            unelevated
            no-caps
            class="mt-2 min-h-[48px] w-full rounded-[0.9rem] bg-brand-amber text-white hover:bg-brand-amber-deep"
            @click="router.push('/login')"
          >
            <span class="text-sm font-extrabold text-white">Volver al inicio de sesión</span>
          </q-btn>
        </div>

        <div v-else-if="completed" class="flex flex-col items-center gap-3 py-6 text-center">
          <q-icon name="eva-checkmark-circle-2-outline" class="text-[3rem] text-brand-green" />
          <p class="text-base font-extrabold text-brand-ink">¡Cuenta activada!</p>
          <p class="max-w-[20rem] text-sm leading-relaxed text-brand-muted">
            Tu contraseña fue registrada correctamente. Te estamos redirigiendo...
          </p>
        </div>

        <div v-else>
          <div class="mb-4 space-y-1 rounded-2xl bg-brand-field p-3.5 text-[0.82rem] leading-relaxed text-brand-ink">
            <p>
              <span class="font-bold">Residente:</span>
              {{ user.name }}
            </p>
            <p>
              <span class="font-bold">Unidad:</span>
              {{ user.unidad }}
            </p>
            <p v-if="user.urbanismo">
              <span class="font-bold">Urbanismo:</span>
              {{ user.urbanismo }}
            </p>
            <p>
              <span class="font-bold">Correo:</span>
              {{ user.email }}
            </p>
          </div>

          <q-form class="flex flex-col" greedy @submit="submit">
            <div class="mb-3">
              <label class="mb-[0.45rem] block text-[0.86rem] font-bold text-brand-ink" for="activation-password">
                Nueva contraseña
              </label>
              <q-input
                id="activation-password"
                v-model="password"
                class="auth-field"
                borderless
                color="primary"
                placeholder="••••••••"
                :type="isPwd ? 'password' : 'text'"
                autocomplete="new-password"
                input-class="text-[0.95rem] text-brand-ink placeholder:text-[#A7B0A6]"
                :rules="passwordRules"
                :disable="loading"
              >
                <template v-slot:prepend>
                  <q-icon name="eva-lock-outline" class="text-[1.15rem] text-brand-muted" />
                </template>
                <template v-slot:append>
                  <q-icon
                    :name="isPwd ? 'eva-eye-off-outline' : 'eva-eye-outline'"
                    class="cursor-pointer rounded-full text-[1.15rem] text-brand-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-amber"
                    role="button"
                    tabindex="0"
                    :aria-label="isPwd ? 'Mostrar contraseña' : 'Ocultar contraseña'"
                    @click="togglePwd"
                    @keydown.enter.prevent="togglePwd"
                    @keydown.space.prevent="togglePwd"
                  />
                </template>
              </q-input>
            </div>

            <div class="mb-3">
              <label class="mb-[0.45rem] block text-[0.86rem] font-bold text-brand-ink" for="activation-confirm">
                Confirmar contraseña
              </label>
              <q-input
                id="activation-confirm"
                v-model="passwordConfirmation"
                class="auth-field"
                borderless
                color="primary"
                placeholder="••••••••"
                :type="isPwdConfirm ? 'password' : 'text'"
                autocomplete="new-password"
                input-class="text-[0.95rem] text-brand-ink placeholder:text-[#A7B0A6]"
                :rules="confirmRules"
                :disable="loading"
              >
                <template v-slot:prepend>
                  <q-icon name="eva-lock-outline" class="text-[1.15rem] text-brand-muted" />
                </template>
                <template v-slot:append>
                  <q-icon
                    :name="isPwdConfirm ? 'eva-eye-off-outline' : 'eva-eye-outline'"
                    class="cursor-pointer rounded-full text-[1.15rem] text-brand-muted focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-amber"
                    role="button"
                    tabindex="0"
                    :aria-label="isPwdConfirm ? 'Mostrar contraseña' : 'Ocultar contraseña'"
                    @click="togglePwdConfirm"
                    @keydown.enter.prevent="togglePwdConfirm"
                    @keydown.space.prevent="togglePwdConfirm"
                  />
                </template>
              </q-input>
            </div>

            <q-btn
              type="submit"
              unelevated
              no-caps
              class="mt-1 min-h-[54px] w-full rounded-[0.9rem] bg-brand-amber text-white shadow-[0_12px_24px_rgba(227,163,60,0.28)] hover:bg-brand-amber-deep"
              :loading="loading"
              :disable="loading"
            >
              <span class="inline-flex items-center gap-1.5 text-base font-extrabold text-white">
                <span>Activar mi cuenta</span>
                <q-icon name="eva-arrow-forward-outline" />
              </span>
            </q-btn>
          </q-form>
        </div>
      </section>
    </div>
  </div>
</template>
