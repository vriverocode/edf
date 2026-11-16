<script setup>
import { nextTick, onMounted, reactive, ref, watch } from 'vue';
import { useAuthStore } from '@/services/store/auth.services';
import { useRouter } from 'vue-router'
import { Notify } from 'quasar'
import { Capacitor } from '@capacitor/core';
import {
  deleteStoredBiometricToken,
  disableBiometric,
  enableBiometric,
  getBiometricEmail,
  getBiometricUsername,
  getStoredBiometricToken,
  hasBiometricEnabled,
  hasBiometricPreference,
  isBiometricAvailable,
  isBiometricCancel,
  promptBiometric,
  saveStoredBiometricToken,
  setBiometricPreference,
  setBiometricUsername
} from '@/composables/useBiometric';

const isMobile = Capacitor.isNativePlatform()
const authServices = useAuthStore()
const usernameInput = ref(null)

const login = reactive({
  username: getBiometricUsername(),
  password: '',
  isRememberUser: localStorage.getItem('isRememberUser') == 'true' ? true : false
})
const useBiometricLogin = ref(hasBiometricPreference())
const isPwd = ref(true)
const loading = ref(false)
const biometricLoading = ref(false)
const biometricCancelled = ref(false)
const biometricFailed = ref(false)
const router = useRouter();
const error = ref(false)
const errorMessage = ref('')

const isEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)

watch(useBiometricLogin, (enabled) => {
  setBiometricPreference(enabled)

  if (enabled) {
    setBiometricUsername(login.username)
  } else {
    setBiometricUsername('')
  }
})

watch(() => login.username, (username) => {
  if (useBiometricLogin.value) {
    setBiometricUsername(username)
  }
})
const rules = (id) => {
  if (id == 'user') return [
    val => val && val.length > 0 || 'Ingresa tu usuario o correo',
    val => isEmail(val) || (/[,$}#*. %"'()\-;&|<>]/.test(val) == false) || 'No debe contener "[](),%|&;\'" ',
  ]
  if (id == 'password') return [
    val => val && val.length > 0 || 'Contraseña no puede quedar vacio',
    val => val.length >= 8 || 'Debe tener 8 caracteres minimo'
  ]

}
const togglePwd = () => {
  isPwd.value = !isPwd.value
}
const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}
const activateBiometric = async ({ email, username }) => {
  biometricLoading.value = true
  biometricCancelled.value = false
  biometricFailed.value = false

  try {
    const available = await isBiometricAvailable()

    if (!available) {
      throw new Error('No hay una huella registrada en el dispositivo')
    }

    await promptBiometric()
    await saveStoredBiometricToken({ email, token: authServices.token })
    enableBiometric(email, username)
  } finally {
    biometricLoading.value = false
  }
}
const authLogin = async () => {
  if (loading.value || biometricLoading.value) return

  loading.value = true
  let completed = false
  let notification = { type: 'positive', text: 'Inicio de sesión correcto' }

  try {
    const wantsBiometric = useBiometricLogin.value
    const persistToken = !wantsBiometric
    const response = await authServices.login(login, { persistToken })
    const status = response?.status ?? response?.code

    if (status !== 200) {
      throw response
    }

    if (wantsBiometric) {
      try {
        const email = response?.data?.email || authServices.user?.email || login.username
        const username = response?.data?.username || login.username
        const rememberUser = login.isRememberUser

        await activateBiometric({ email, username, rememberUser })
        login.username = username
      } catch (biometricError) {
        console.error('No se pudo activar la autenticación biométrica:', biometricError)
        await deleteStoredBiometricToken()
        disableBiometric()
        useBiometricLogin.value = false
        authServices.saveToken(authServices.token, { persist: true })

        notification = {
          type: 'warning',
          text: isBiometricCancel(biometricError)
            ? 'Sesión iniciada. No se activó la huella.'
            : 'Sesión iniciada, pero no se pudo activar la huella. Verifica que tengas una huella registrada en Android.'
        }
      }
    } else {
      await deleteStoredBiometricToken()
      disableBiometric()
    }

    completed = true
    showNotify(notification.type, notification.text)
    setTimeout(() => {
      loading.value = false
      router.push('/dashboard')
    }, 1000)
  } catch (response) {
    const message = response?.data?.error || response?.error

    showNotify('negative', message || 'Error de conexión')
  } finally {
    if (!completed) {
      loading.value = false;
    }
  }
}
const tryBiometricLogin = async ({ silent = false } = {}) => {
  if (biometricLoading.value) return false

  const email = getBiometricEmail()

  if (!isMobile || !hasBiometricEnabled() || !email) {
    biometricFailed.value = true
    return false
  }

  biometricLoading.value = true
  biometricCancelled.value = false
  biometricFailed.value = false

  try {
    const available = await isBiometricAvailable()

    if (!available) {
      throw new Error('La autenticación biométrica no está disponible')
    }

    await promptBiometric()

    const credentials = await getStoredBiometricToken()

    if (credentials.email !== email) {
      await deleteStoredBiometricToken()
      disableBiometric()
      useBiometricLogin.value = false
      throw new Error('Las credenciales biométricas no coinciden con la cuenta guardada')
    }

    await authServices.setSession(credentials.token)

    showNotify('primary', 'Inicio de sesión correcto')
    router.push('/dashboard')
    return true
  } catch (biometricError) {
    const cancelled = isBiometricCancel(biometricError)
    biometricCancelled.value = cancelled
    biometricFailed.value = true
    useBiometricLogin.value = hasBiometricPreference()

    if (!silent && !cancelled) {
      showNotify('negative', 'No se pudo autenticar con huella')
    }

    return false
  } finally {
    biometricLoading.value = false
  }
}
const continueManually = async () => {
  try {
    biometricCancelled.value = false
    biometricFailed.value = false
    biometricLoading.value = false

    await nextTick()
    usernameInput.value?.focus?.()
  } catch (manualError) {
    console.error('No se pudo volver al formulario manual:', manualError)
    biometricFailed.value = false
  }
}

onMounted(async () => {
  if (!isMobile || !hasBiometricEnabled()) return

  await nextTick()
  await new Promise((resolve) => setTimeout(resolve, 300))
  await tryBiometricLogin({ silent: true })
})
</script>

<template>
  <div>
    <q-btn
      type="button"
      unelevated
      no-caps
      class="mt-4 min-h-[48px] w-full rounded-[0.9rem] border-[1.5px] border-solid border-brand-border bg-white font-bold text-brand-ink hover:bg-[#FDFAF3]"
    >
      <span class="inline-flex items-center gap-2.5 text-[0.92rem]">
        <svg width="20" height="20" viewBox="0 0 48 48" aria-hidden="true">
          <path fill="#FFC107" d="M43.611 20.083H42V20H24v8h11.303c-1.649 4.657-6.08 8-11.303 8-6.627 0-12-5.373-12-12s5.373-12 12-12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 12.955 4 4 12.955 4 24s8.955 20 20 20 20-8.955 20-20c0-1.341-.138-2.65-.389-3.917z"/>
          <path fill="#FF3D00" d="M6.306 14.691l6.571 4.819C14.655 15.108 18.961 12 24 12c3.059 0 5.842 1.154 7.961 3.039l5.657-5.657C34.046 6.053 29.268 4 24 4 16.318 4 9.656 8.337 6.306 14.691z"/>
          <path fill="#4CAF50" d="M24 44c5.166 0 9.86-1.977 13.409-5.192l-6.19-5.238A11.91 11.91 0 0 1 24 36c-5.202 0-9.619-3.317-11.283-7.946l-6.522 5.025C9.505 39.556 16.227 44 24 44z"/>
          <path fill="#1976D2" d="M43.611 20.083H42V20H24v8h11.303a12.04 12.04 0 0 1-4.087 5.571l.003-.002 6.19 5.238C36.971 39.205 44 34 44 24c0-1.341-.138-2.65-.389-3.917z"/>
        </svg>
        <span>Continuar con Google</span>
      </span>
    </q-btn>

    <div class="my-4 flex items-center gap-3 text-[0.78rem] text-brand-muted">
      <span class="h-px flex-1 bg-[#EDE5D6]"></span>
      <span>o con tu correo</span>
      <span class="h-px flex-1 bg-[#EDE5D6]"></span>
    </div>

    <q-form class="flex flex-col" greedy @submit="authLogin">
      <div class="mb-0">
        <label class="mb-[0.45rem] block text-[0.86rem] font-bold text-brand-ink" for="login-identifier">
          Correo electrónico
        </label>
        <q-input
          id="login-identifier"
          ref="usernameInput"
          v-model="login.username"
          class="auth-field"
          borderless
          color="primary"
          placeholder="tu@correo.com"
          autocomplete="username"
          autocapitalize="none"
          input-class="text-[0.95rem] text-brand-ink placeholder:text-[#A7B0A6]"
          :rules="rules('user')"
          :disable="loading || biometricLoading"
        >
          <template v-slot:prepend>
            <q-icon name="eva-email-outline" class="text-[1.15rem] text-brand-muted" />
          </template>
        </q-input>
      </div>

      <div class="mb-3">
        <label class="mb-[0.45rem] block text-[0.86rem] font-bold text-brand-ink" for="login-password">
          Contraseña
        </label>
        <q-input
          id="login-password"
          v-model="login.password"
          class="auth-field"
          borderless
          color="primary"
          placeholder="••••••••"
          :type="isPwd ? 'password' : 'text'"
          autocomplete="current-password"
          input-class="text-[0.95rem] text-brand-ink placeholder:text-[#A7B0A6]"
          :rules="rules('password')"
          :disable="loading || biometricLoading"
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
      <div class="mb-3 flex items-center">
        <q-checkbox v-model="login.isRememberUser" :val="true" size="2rem"/>
        <label class=" block text-[0.86rem] font-bold text-brand-ink" for="login-password">
          Recordar usuario
        </label>
      </div>
      <div class="-mt-1 mb-3 text-right">
        <button
          type="button"
          class="rounded text-sm font-bold text-brand-terracotta hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-amber"
          @click="router.push('/forgot-password')"
        >
          ¿Olvidaste tu contraseña?
        </button>
      </div>

      <div v-if="isMobile" class="mb-3 flex items-center gap-1">
        <q-checkbox
          v-model="useBiometricLogin"
          color="primary"
          :disable="loading || biometricLoading"
        />
        <span class="text-[0.84rem] font-semibold leading-tight text-brand-ink">
          Usar huella para futuros accesos
        </span>
      </div>

      <div v-if="isMobile && biometricLoading" class="mb-3 flex flex-wrap items-center gap-2 text-[0.8rem] text-brand-muted">
        <q-spinner color="primary" size="18px" />
        <span>Autenticando con huella...</span>
      </div>

      <div
        v-if="isMobile && biometricFailed && !biometricLoading"
        class="mb-3 flex flex-wrap items-center gap-2 text-[0.8rem] text-brand-terracotta"
      >
        <span>
          {{ biometricCancelled ? 'Cancelaste la autenticación biométrica.' : 'No se pudo autenticar con huella.' }}
        </span>
        <button
          type="button"
          class="rounded font-bold text-brand-terracotta hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-amber"
          @click="continueManually"
        >
          Usar contraseña
        </button>
      </div>

      <q-btn
        type="submit"
        unelevated
        no-caps
        class="mt-1 min-h-[54px] w-full rounded-[0.9rem] bg-brand-amber text-white shadow-[0_12px_24px_rgba(227,163,60,0.28)] hover:bg-brand-amber-deep"
        :loading="loading"
        :disable="loading || biometricLoading"
      >
        <span class="inline-flex items-center gap-1.5 text-base font-extrabold text-white">
          <span>Iniciar sesión</span>
        </span>
      </q-btn>
    </q-form>

    <p class="mt-4 text-center text-[0.72rem] leading-relaxed text-brand-muted">
      Al continuar aceptas los
      <a
        href="https://www.edificiopacifik.com/terminos.html"
        target="_blank"
        rel="noopener noreferrer"
        class="font-bold text-brand-green underline underline-offset-2 hover:text-brand-terracotta"
      >Términos</a>
      y la
      <!-- TODO: reemplazar por la URL definitiva de la política de privacidad -->
      <a
        href="https://www.edificiopacifik.com/terminos.html"
        target="_blank"
        rel="noopener noreferrer"
        class="font-bold text-brand-green underline underline-offset-2 hover:text-brand-terracotta"
      >Política de privacidad</a>
    </p>

    <q-dialog v-model="error">
      <q-card>
        <q-card-section>
          <div class="text-h6">errors</div>
        </q-card-section>
        <q-card-section class="q-pt-none text-red-500">
          {{ errorMessage }}
        </q-card-section>
        <q-card-actions align="right">
          <q-btn flat label="OK" color="primary" v-close-popup />
        </q-card-actions>
      </q-card>
    </q-dialog>
  </div>
</template>
