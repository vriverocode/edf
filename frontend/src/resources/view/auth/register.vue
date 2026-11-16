<script setup>
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Notify } from 'quasar'
import { useAuthStore } from '@/services/store/auth.services'

const router = useRouter()
const authServices = useAuthStore()

const form = reactive({
  name: '',
  email: '',
  password: '',
})

const isPwd = ref(true)
const loading = ref(false)

const passwordChecks = computed(() => [
  { label: 'Mínimo 8 caracteres', valid: form.password.length >= 8 },
  { label: 'Una letra mayúscula', valid: /[A-Z]/.test(form.password) },
  { label: 'Un número', valid: /\d/.test(form.password) },
  { label: 'Un carácter especial (! @ # $)', valid: /[^A-Za-z0-9\s]/.test(form.password) },
])

const passwordIsStrong = computed(() => passwordChecks.value.every((check) => check.valid))

const isEmail = (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)

const rules = {
  name: [(val) => (val && val.trim().length > 0) || 'Ingresa tu nombre completo'],
  email: [
    (val) => (val && val.length > 0) || 'Ingresa tu correo',
    (val) => isEmail(val) || 'Ingresa un correo válido',
  ],
  password: [
    (val) => (val && val.length > 0) || 'Ingresa una contraseña',
    () => passwordIsStrong.value || 'La contraseña no cumple los requisitos',
  ],
}

const togglePwd = () => {
  isPwd.value = !isPwd.value
}

const onSubmit = async () => {
  if (loading.value) return

  loading.value = true

  try {
    const response = await authServices.registerResident({
      nombre: form.name,
      email: form.email,
      password: form.password,
    })
    const status = response?.code

    if (status !== 201) {
      const message = response?.error || response?.message || 'No se pudo crear la cuenta'
      Notify.create({ color: 'negative', message, timeout: 3000 })
      return
    }

    const registrationToken = response?.data?.registration_token

    if (!registrationToken) {
      Notify.create({ color: 'negative', message: 'No se pudo iniciar el registro', timeout: 3000 })
      return
    }

    sessionStorage.setItem('registration_token', registrationToken)
    router.push('/register/comunidad')
  } catch (error) {
    const message =
      error?.data?.error ||
      error?.data?.errors?.email?.[0] ||
      error?.data?.errors?.nombre?.[0] ||
      error?.data?.message ||
      'No se pudo crear la cuenta'

    Notify.create({ color: 'negative', message, timeout: 3000 })
  } finally {
    loading.value = false
  }
}
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

    <q-form class="flex flex-col" greedy @submit="onSubmit">
      <div class="mb-0">
        <label class="mb-[0.45rem] block text-[0.86rem] font-bold text-brand-ink" for="register-name">
          Nombre completo
        </label>
        <q-input
          id="register-name"
          v-model="form.name"
          class="auth-field"
          borderless
          color="primary"
          placeholder="Ej: Juan Pérez"
          autocomplete="name"
          input-class="text-[0.95rem] text-brand-ink placeholder:text-[#A7B0A6]"
          :rules="rules.name"
          :disable="loading"
        >
          <template v-slot:prepend>
            <q-icon name="eva-person-outline" class="text-[1.15rem] text-brand-muted" />
          </template>
        </q-input>
      </div>
      <div class="mb-0">
        <label class="mb-[0.45rem] block text-[0.86rem] font-bold text-brand-ink" for="register-email">
          Correo electrónico
        </label>
        <q-input
          id="register-email"
          v-model="form.email"
          class="auth-field"
          borderless
          color="primary"
          placeholder="tu@correo.com"
          autocomplete="email"
          autocapitalize="none"
          input-class="text-[0.95rem] text-brand-ink placeholder:text-[#A7B0A6]"
          :rules="rules.email"
          :disable="loading"
        >
          <template v-slot:prepend>
            <q-icon name="eva-email-outline" class="text-[1.15rem] text-brand-muted" />
          </template>
        </q-input>
      </div>

      <div class="mb-0">
        <label class="mb-[0.45rem] block text-[0.86rem] font-bold text-brand-ink" for="register-password">
          Contraseña
        </label>
        <q-input
          id="register-password"
          v-model="form.password"
          class="auth-field"
          borderless
          color="primary"
          placeholder="••••••••"
          :type="isPwd ? 'password' : 'text'"
          autocomplete="new-password"
          input-class="text-[0.95rem] text-brand-ink placeholder:text-[#A7B0A6]"
          :rules="rules.password"
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

        <ul class="mb-2 ">
          <li
            v-for="check in passwordChecks"
            :key="check.label"
            class="flex items-center gap-1.5 text-[0.72rem] leading-tight"
            :class="check.valid ? 'text-brand-green' : 'text-brand-muted'"
          >
            <q-icon
              :name="check.valid ? 'eva-checkmark-circle-2-outline' : 'eva-radio-button-off-outline'"
              class="text-[0.95rem]"
            />
            <span>{{ check.label }}</span>
          </li>
        </ul>
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
          <span>Registrarme</span>
        </span>
      </q-btn>
    </q-form>

    <p class="mt-4 text-center text-[0.72rem] leading-relaxed text-brand-muted">
      Al registrarte aceptas los
      <a
        href="https://www.edificiopacifik.com/terminos.html"
        target="_blank"
        rel="noopener noreferrer"
        class="font-bold text-brand-green underline underline-offset-2 hover:text-brand-terracotta"
      >Términos</a>
      y la
      <a
        href="https://www.edificiopacifik.com/terminos.html"
        target="_blank"
        rel="noopener noreferrer"
        class="font-bold text-brand-green underline underline-offset-2 hover:text-brand-terracotta"
      >Política de privacidad</a>
    </p>
  </div>
</template>
