<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { Notify } from 'quasar'
import { Capacitor } from '@capacitor/core'
import { BarcodeScanner, BarcodeFormat } from '@capacitor-mlkit/barcode-scanning'
import { useAuthStore } from '@/services/store/auth.services'
import iconsApp from '@/assets/icons/index'

const router = useRouter()
const authServices = useAuthStore()


const isNative = Capacitor.isNativePlatform()

const form = reactive({
  codigo_invitacion: '',
})

const loading = ref(false)
const scanning = ref(false)

const rules = {
  code: [(val) => (val && val.trim().length > 0) || 'Ingresa el código de tu comunidad'],
}

const extractCode = (raw) => {
  const value = String(raw || '').trim()

  if (!value) return ''

  try {
    const url = new URL(value)
    const code = url.searchParams.get('codigo') || url.searchParams.get('code')

    if (code) return code.trim().toUpperCase()
  } catch {
    // El QR contiene el código en texto plano.
  }

  return value.toUpperCase()
}

const scanQr = async () => {
  if (scanning.value) return

  scanning.value = true

  try {
    const { barcodes } = await BarcodeScanner.scan({
      formats: [BarcodeFormat.QrCode],
    })

    const rawValue = barcodes?.[0]?.rawValue || barcodes?.[0]?.displayValue

    if (!rawValue) {
      Notify.create({ color: 'warning', message: 'No se detectó ningún código QR', timeout: 2200 })
      return
    }

    form.codigo_invitacion = extractCode(rawValue)
    Notify.create({ color: 'positive', message: 'Código detectado', timeout: 1500 })
  } catch (error) {
    console.error('No se pudo escanear el QR:', error)
    Notify.create({ color: 'negative', message: 'No se pudo abrir el escáner', timeout: 2200 })
  } finally {
    scanning.value = false
  }
}

const onSubmit = async () => {
  if (loading.value) return

  const registrationToken = sessionStorage.getItem('registration_token')

  if (!registrationToken) {
    Notify.create({
      color: 'warning',
      message: 'Tu registro expiró. Vuelve a crear tu cuenta.',
      timeout: 2500,
    })
    router.replace('/register')
    return
  }

  loading.value = true

  try {
    const response = await authServices.joinCommunity({
      registration_token: registrationToken,
      codigo_invitacion: form.codigo_invitacion,
    })

    if (response?.code !== 200) {
      Notify.create({
        color: 'negative',
        message: response?.error || 'No se pudo validar el código',
        timeout: 3200,
      })
      return
    }

    sessionStorage.removeItem('registration_token')

    Notify.create({
      color: 'positive',
      message: response?.data?.message || 'Te uniste a la comunidad',
      timeout: 3500,
    })

    setTimeout(() => router.push('/login'), 1400)
  } catch (error) {
    const message =
      error?.data?.error || error?.data?.message || 'No se pudo validar el código'

    Notify.create({ color: 'negative', message, timeout: 3200 })
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  if (!sessionStorage.getItem('registration_token')) {
    router.replace('/register')
  }
})
</script>

<template>
  <div>
    <div class="text-center">
      <h2 class="text-xl font-extrabold text-brand-ink">Únete a tu comunidad</h2>
      <p class="mt-1 text-[0.8rem] leading-snug text-brand-muted">
        Ingresa el código de invitación o escanea el QR que te compartió la administración.
      </p>
    </div>

    <q-form class="mt-5 flex flex-col" greedy @submit="onSubmit">
      <div class="mb-3">
        <label class="mb-[0.45rem] block text-[0.86rem] font-bold text-brand-ink" for="community-code">
          Código de invitación
        </label>
        <q-input
          id="community-code"
          v-model="form.codigo_invitacion"
          class="auth-field"
          borderless
          color="primary"
          placeholder="Ej: AB12CD34"
          autocomplete="off"
          autocapitalize="characters"
          input-class="text-center text-[1.05rem] font-bold tracking-[0.16em] text-brand-ink uppercase placeholder:font-normal placeholder:tracking-normal placeholder:text-[#A7B0A6]"
          :rules="rules.code"
          :disable="loading"
        >
          <template v-slot:prepend>
            <q-icon name="eva-keypad-outline" class="text-[1.15rem] text-brand-muted" />
          </template>
          <template v-slot:append>
            <div v-html="iconsApp.scan" class="h-5 w-5" @click="scanQr" />
          </template>
        </q-input>
      </div>

      <q-btn
        v-if="isNative"
        type="button"
        unelevated
        no-caps
        class="mb-4 min-h-[50px] w-full rounded-[0.9rem] border-[1.5px] border-solid border-brand-border bg-white font-bold text-brand-ink hover:bg-[#FDFAF3]"
        :loading="scanning"
        :disable="loading || scanning"
        @click="scanQr"
      >
        <span class="inline-flex items-center gap-2 text-[0.9rem]">
          <q-icon name="eva-camera-outline" class="text-[1.2rem]" />
          <span>Escanear QR</span>
        </span>
      </q-btn>

      <q-btn
        type="submit"
        unelevated
        no-caps
        class="min-h-[54px] w-full rounded-[0.9rem] bg-brand-amber text-white shadow-[0_12px_24px_rgba(227,163,60,0.28)] hover:bg-brand-amber-deep"
        :loading="loading"
        :disable="loading || scanning"
      >
        <span class="text-base font-extrabold text-white">Unirme a la comunidad</span>
      </q-btn>
    </q-form>

    <p class="mt-4 text-center text-[0.78rem] text-brand-muted">
      <button
        type="button"
        class="rounded font-bold text-brand-green underline underline-offset-2 hover:text-brand-terracotta"
        @click="router.push('/login')"
      >
        Volver al inicio de sesión
      </button>
    </p>

    <p class="mt-2 text-center text-[0.72rem] leading-relaxed text-brand-muted">
      Si ya creaste tu cuenta pero cerraste la app, vuelve a completar el
      <button
        type="button"
        class="font-bold text-brand-green underline underline-offset-2 hover:text-brand-terracotta"
        @click="router.push('/register')"
      >
        paso 1
      </button>
      con el mismo correo y contraseña para continuar.
    </p>
  </div>
</template>
