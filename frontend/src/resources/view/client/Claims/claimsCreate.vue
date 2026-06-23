<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import moment from 'moment'
import { useAuthStore } from '@/services/store/auth.services'
import { useClaimsStore } from '@/services/store/claims.store'

const router = useRouter()
const $q = useQuasar()
const authStore = useAuthStore()
const claimsStore = useClaimsStore()

// ─── Estado ───────────────────────────────────────────────────
const loading = ref(false)
const loadingSequence = ref(true)
const vaucherFile = ref(null)

const form = ref({
  sequence: null,
  claim_date: moment().format('DD/MM/YYYY'),

  // Identificación del consumidor
  nombres: '',
  apellidos: '',
  doctype: null,
  document: '',
  departamento: '',
  provincia: '',
  distrito: '',
  direccion: '',
  referencia: '',
  phone: '',
  email: '',
  is_minor: false,
  guardian_fullname: '',
  guardian_document: '',

  // Identificación del bien contratado
  service_type: null,
  service_number: '',
  service_date: moment().format('YYYY-MM-DD'),
  amount: '',

  // Detalle del reclamo
  claim_type: null,
  claim_description: '',
  pedido: '',
  observations: '',
})

// ─── Opciones ─────────────────────────────────────────────────
const doctypeOptions = [
  { label: 'DNI', value: 'DNI' },
  { label: 'RUC', value: 'RUC' },
  { label: 'Carnet de Extranjería', value: 'CE' },
  { label: 'Pasaporte', value: 'PASAPORTE' },
]

const serviceTypeOptions = [
  { label: 'Producto', value: 'producto' },
  { label: 'Servicio', value: 'servicio' },
]

const claimTypeOptions = [
  { label: 'Reclamo', value: 'reclamo' },
  { label: 'Queja', value: 'queja' },
]

// ─── Datos del usuario autenticado ────────────────────────────
const user = computed(() => authStore.user)

function prefillFromUser() {
  if (!user.value) return
  const u = user.value
  form.value.nombres = u.name?.split(' ').slice(0, 1).join(' ') || ''
  form.value.apellidos = u.name?.split(' ').slice(1).join(' ') || ''
  form.value.email = u.email || ''
  form.value.phone = u.phone || ''
  form.value.document = u.document || ''
  form.value.doctype = u.document_type || null
}

// ─── Carga inicial ────────────────────────────────────────────
onMounted(async () => {
  prefillFromUser()
  try {
    const seq = await claimsStore.getClaimSequence()
    form.value.sequence = seq
  } catch (e) {
    $q.notify({ type: 'warning', message: 'No se pudo cargar el número de reclamo' })
  } finally {
    loadingSequence.value = false
  }
})

// ─── Envío ────────────────────────────────────────────────────
const onSubmit = () => {
  loading.value = true

  const payload = new FormData()

  // Meta
  payload.append('sequence', form.value.sequence)
  payload.append('claim_date', moment().format('YYYY-MM-DD'))

  // Consumidor
  const fullname = `${form.value.nombres} ${form.value.apellidos}`.trim()
  payload.append('fullname', fullname)
  payload.append('doctype', form.value.doctype)
  payload.append('document', form.value.document)
  payload.append('departamento', form.value.departamento)
  payload.append('provincia', form.value.provincia)
  payload.append('distrito', form.value.distrito)
  payload.append('direccion', form.value.direccion)
  payload.append('referencia', form.value.referencia)
  payload.append('phone', form.value.phone)
  payload.append('email', form.value.email)
  payload.append('is_minor', form.value.is_minor ? 1 : 0)
  if (form.value.is_minor) {
    payload.append('guardian_fullname', form.value.guardian_fullname)
    payload.append('guardian_document', form.value.guardian_document)
  }

  // Bien contratado
  payload.append('service_type', form.value.service_type)
  payload.append('service_number', form.value.service_number)
  payload.append('service_date', form.value.service_date)
  payload.append('amount', form.value.amount)

  // Reclamo
  payload.append('claim_type', form.value.claim_type)
  payload.append('claim_description', form.value.claim_description)
  payload.append('pedido', form.value.pedido)
  payload.append('observations', form.value.observations)

  if (vaucherFile.value) {
    payload.append('vaucher', vaucherFile.value)
  }

  claimsStore.createClaim(payload)
    .then((res) => {
      $q.notify({
        type: 'positive',
        message: `Reclamo N° ${res.data?.sequence} registrado con éxito. Recibirás una respuesta en 15 días hábiles.`,
        timeout: 6000,
      })
      router.go(-1)
    })
    .catch((error) => {
      $q.notify({
        type: 'negative',
        message: typeof error === 'string' ? error : 'Error al registrar el reclamo',
      })
    })
    .finally(() => {
      loading.value = false
    })
}
</script>

<template>
  <div class="claims-page md:px-10 px-2 pb-16">

    <!-- Encabezado Libro de Reclamaciones -->
    <div class="claims-header">
      <div class="claims-header__logo">
        <q-icon name="eva-book-open-outline" size="2.2rem" color="white" />
      </div>
      <div>
        <div class="text-h6 text-bold">Libro de Reclamaciones Virtual</div>
        <div class="text-caption text-blue-grey-3">
          Conforme al Código de Protección y Defensa del Consumidor
        </div>
      </div>
    </div>

    <!-- Aviso legal -->
    <div class="claims-notice q-mb-md">
      <p class="q-mb-xs text-body2 text-blue-grey-8">
        Conforme a lo establecido en el Código de Protección y Defensa del Consumidor, este
        establecimiento cuenta con un Libro de Reclamaciones a tu disposición.
      </p>
      <ol class="text-caption text-blue-grey-7 q-pl-md q-mb-none">
        <li>Para registrar tu queja o reclamo, completa el siguiente formulario.</li>
        <li>Llena el tipo de reclamo o queja y los datos solicitados.</li>
        <li>Después de completar el formulario haz clic en el botón «Enviar».</li>
        <li>En las siguientes 48 horas recibirás confirmación de tu número de reclamo.</li>
        <li>
          De conformidad con el D.S.101-2022 PCM, el plazo de atención es de
          <strong>15 días hábiles</strong>, improrrogables, desde su presentación.
        </li>
      </ol>
    </div>

    <q-form @submit="onSubmit">

      <!-- Número de reclamo y fecha -->
      <div class="row q-col-gutter-md q-mb-md">
        <div class="col-md-6 col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">N° de Reclamo</div>
          <q-input
            v-model="form.sequence"
            borderless
            dense
            readonly
            class="form__input"
            :loading="loadingSequence"
            placeholder="Cargando..."
          />
        </div>
        <div class="col-md-6 col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Fecha</div>
          <q-input
            v-model="form.claim_date"
            borderless
            dense
            readonly
            class="form__input"
          />
        </div>
      </div>

      <!-- ─── Sección 1: Identificación del consumidor ─── -->
      <div class="claims-section-title">
        <q-icon name="eva-person-outline" size="1.2rem" class="q-mr-xs" />
        1.- Identificación del Consumidor Reclamante
      </div>
      <div class="text-caption text-blue-grey-6 q-mb-md">* Datos requeridos</div>

      <div class="row q-col-gutter-md">

        <!-- Nombres -->
        <div class="col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Nombres *</div>
          <q-input
            v-model="form.nombres"
            borderless dense clearable
            class="form__input"
            placeholder="José"
            lazy-rules
            :rules="[val => (val && val.length > 0) || 'Ingresa tus nombres']"
          />
        </div>

        <!-- Apellidos -->
        <div class="col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Apellidos *</div>
          <q-input
            v-model="form.apellidos"
            borderless dense clearable
            class="form__input"
            placeholder="Flores"
            lazy-rules
            :rules="[val => (val && val.length > 0) || 'Ingresa tus apellidos']"
          />
        </div>

        <!-- Tipo y número de documento -->
        <div class="col-md-6 col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Tipo de documentación *</div>
          <q-select
            v-model="form.doctype"
            :options="doctypeOptions"
            option-value="value"
            option-label="label"
            emit-value map-options
            borderless dense
            class="form__input"
            behavior="menu"
            placeholder="— Selecciona —"
            lazy-rules
            :rules="[val => !!val || 'Selecciona el tipo de documento']"
          />
        </div>
        <div class="col-md-6 col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Número de documentación *</div>
          <q-input
            v-model="form.document"
            borderless dense clearable
            class="form__input"
            placeholder="10087922"
            lazy-rules
            :rules="[val => (val && val.length > 0) || 'Ingresa el número de documento']"
          />
        </div>

        <!-- Departamento -->
        <div class="col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Departamento *</div>
          <q-input
            v-model="form.departamento"
            borderless dense clearable
            class="form__input"
            placeholder="Lima"
            lazy-rules
            :rules="[val => (val && val.length > 0) || 'Ingresa el departamento']"
          />
        </div>

        <!-- Provincia -->
        <div class="col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Provincia *</div>
          <q-input
            v-model="form.provincia"
            borderless dense clearable
            class="form__input"
            placeholder="Lima"
            lazy-rules
            :rules="[val => (val && val.length > 0) || 'Ingresa la provincia']"
          />
        </div>

        <!-- Distrito -->
        <div class="col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Distrito *</div>
          <q-input
            v-model="form.distrito"
            borderless dense clearable
            class="form__input"
            placeholder="Miraflores"
            lazy-rules
            :rules="[val => (val && val.length > 0) || 'Ingresa el distrito']"
          />
        </div>

        <!-- Dirección -->
        <div class="col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Dirección *</div>
          <q-input
            v-model="form.direccion"
            borderless dense clearable
            class="form__input"
            placeholder="Calle 17, n° 355 Urbanización Santa Rosa"
            lazy-rules
            :rules="[val => (val && val.length > 0) || 'Ingresa la dirección']"
          />
        </div>

        <!-- Referencia -->
        <div class="col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Referencia</div>
          <q-input
            v-model="form.referencia"
            borderless dense clearable
            class="form__input"
            placeholder="Alt. de la Cuadra 8 de Villa Sol"
          />
        </div>

        <!-- Teléfono -->
        <div class="col-md-6 col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Teléfono *</div>
          <q-input
            v-model="form.phone"
            borderless dense clearable
            class="form__input"
            placeholder="+51 952 458 236"
            lazy-rules
            :rules="[val => (val && val.length > 0) || 'Ingresa el teléfono']"
          />
        </div>

        <!-- Correo -->
        <div class="col-md-6 col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Correo electrónico *</div>
          <q-input
            v-model="form.email"
            type="email"
            borderless dense clearable
            class="form__input"
            placeholder="minombre@gmail.com"
            lazy-rules
            :rules="[val => (val && val.includes('@')) || 'Ingresa un correo válido']"
          />
        </div>

        <!-- Menor de edad -->
        <div class="col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">¿Eres menor de edad? *</div>
          <div class="row q-gutter-md">
            <q-radio v-model="form.is_minor" :val="false" label="No" color="primary" />
            <q-radio v-model="form.is_minor" :val="true" label="Sí" color="primary" />
          </div>
        </div>

        <!-- Datos del apoderado (si es menor) -->
        <template v-if="form.is_minor">
          <div class="col-12">
            <div class="claims-section-title claims-section-title--sub">
              <q-icon name="eva-people-outline" size="1rem" class="q-mr-xs" />
              Datos del apoderado / padre o madre
            </div>
          </div>
          <div class="col-12">
            <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Nombre completo del apoderado *</div>
            <q-input
              v-model="form.guardian_fullname"
              borderless dense clearable
              class="form__input"
              placeholder="Nombre completo"
              lazy-rules
              :rules="[val => (val && val.length > 0) || 'Ingresa el nombre del apoderado']"
            />
          </div>
          <div class="col-12">
            <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">N° de documento del apoderado *</div>
            <q-input
              v-model="form.guardian_document"
              borderless dense clearable
              class="form__input"
              placeholder="DNI del apoderado"
              lazy-rules
              :rules="[val => (val && val.length > 0) || 'Ingresa el documento del apoderado']"
            />
          </div>
        </template>

      </div>

      <!-- ─── Sección 2: Identificación del bien contratado ─── -->
      <div class="claims-section-title q-mt-lg">
        <q-icon name="eva-shopping-bag-outline" size="1.2rem" class="q-mr-xs" />
        2.- Identificación del Bien Contratado
      </div>

      <div class="row q-col-gutter-md">

        <!-- Tipo de bien -->
        <div class="col-md-6 col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Tipo *</div>
          <q-select
            v-model="form.service_type"
            :options="serviceTypeOptions"
            option-value="value"
            option-label="label"
            emit-value map-options
            borderless dense
            class="form__input"
            behavior="menu"
            placeholder="— Selecciona —"
            lazy-rules
            :rules="[val => !!val || 'Selecciona el tipo de bien']"
          />
        </div>

        <!-- Número / código de orden -->
        <div class="col-md-6 col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">N° de Orden / Código *</div>
          <q-input
            v-model="form.service_number"
            borderless dense clearable
            class="form__input"
            placeholder="ORD-0001"
            lazy-rules
            :rules="[val => (val && val.length > 0) || 'Ingresa el número de orden']"
          />
        </div>

        <!-- Fecha del servicio -->
        <div class="col-md-6 col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Fecha del servicio *</div>
          <q-input
            v-model="form.service_date"
            type="date"
            borderless dense
            class="form__input"
            lazy-rules
            :rules="[val => !!val || 'Selecciona la fecha']"
          />
        </div>

        <!-- Monto -->
        <div class="col-md-6 col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Monto (S/)</div>
          <q-input
            v-model="form.amount"
            type="number"
            borderless dense clearable
            class="form__input"
            placeholder="0.00"
            min="0"
          />
        </div>

      </div>

      <!-- ─── Sección 3: Detalle del reclamo ─── -->
      <div class="claims-section-title q-mt-lg">
        <q-icon name="eva-alert-circle-outline" size="1.2rem" class="q-mr-xs" />
        3.- Detalle del Reclamo o Queja
      </div>

      <div class="row q-col-gutter-md">

        <!-- Tipo de reclamo -->
        <div class="col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Tipo *</div>
          <div class="row q-gutter-md">
            <q-radio
              v-for="opt in claimTypeOptions"
              :key="opt.value"
              v-model="form.claim_type"
              :val="opt.value"
              :label="opt.label"
              color="primary"
            />
          </div>
          <div v-if="!form.claim_type" class="text-caption text-negative q-mt-xs">
            Selecciona el tipo de reclamo
          </div>
        </div>

        <!-- Descripción del reclamo -->
        <div class="col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Descripción detallada *</div>
          <q-input
            v-model="form.claim_description"
            type="textarea"
            borderless dense clearable
            class="form__input"
            placeholder="Describe con detalle tu reclamo o queja..."
            rows="4"
            lazy-rules
            :rules="[val => (val && val.length > 0) || 'Ingresa la descripción del reclamo']"
          />
        </div>

        <!-- Pedido del consumidor -->
        <div class="col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Pedido del consumidor</div>
          <q-input
            v-model="form.pedido"
            type="textarea"
            borderless dense clearable
            class="form__input"
            placeholder="¿Qué esperas como solución a tu reclamo?"
            rows="3"
          />
        </div>

        <!-- Observaciones -->
        <div class="col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Observaciones adicionales</div>
          <q-input
            v-model="form.observations"
            type="textarea"
            borderless dense clearable
            class="form__input"
            placeholder="Cualquier información adicional relevante..."
            rows="2"
          />
        </div>

        <!-- Adjunto / Voucher -->
        <div class="col-12">
          <div class="text-subtitle2 text-bold text-blue-grey-8 q-mb-xs">Adjuntar documento / imagen (Opcional)</div>
          <q-file
            v-model="vaucherFile"
            borderless dense clearable
            accept="image/*,.pdf"
            class="form__input"
            label="Selecciona un archivo"
          >
            <template v-slot:prepend>
              <q-icon name="eva-attach-outline" />
            </template>
          </q-file>
        </div>

      </div>

      <!-- Botones -->
      <div class="row q-mt-xl q-mb-sm q-col-gutter-md">
        <div class="col-6">
          <q-btn
            unelevated
            color="grey-4"
            text-color="grey-8"
            class="full-width claims-btn"
            @click="router.go(-1)"
          >
            Volver
          </q-btn>
        </div>
        <div class="col-6">
          <q-btn
            unelevated
            color="primary"
            type="submit"
            :loading="loading"
            class="full-width claims-btn"
          >
            <q-icon name="eva-checkmark-circle-2-outline" class="q-mr-sm" />
            Enviar Reclamo
          </q-btn>
        </div>
      </div>

    </q-form>
  </div>
</template>

<style lang="scss" scoped>
.claims-page {
  background: #f5f7fb;
  min-height: 100vh;
  padding-top: 1rem;
}

/* Header */
.claims-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  background: linear-gradient(135deg, #1976d2 0%, #0d47a1 100%);
  color: white;
  border-radius: 1rem;
  padding: 1.2rem 1.5rem;
  margin-bottom: 1rem;
  box-shadow: 0 4px 20px rgba(25, 118, 210, 0.3);

  &__logo {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 0.8rem;
    padding: 0.6rem;
    display: flex;
    align-items: center;
    justify-content: center;
  }
}

/* Aviso legal */
.claims-notice {
  background: #e3f2fd;
  border-left: 4px solid #1976d2;
  border-radius: 0.5rem;
  padding: 0.9rem 1.1rem;

  ol {
    margin-top: 0.4rem;
    li {
      margin-bottom: 0.15rem;
    }
  }
}

/* Títulos de sección */
.claims-section-title {
  display: flex;
  align-items: center;
  font-weight: 700;
  font-size: 0.95rem;
  color: #1565c0;
  background: linear-gradient(90deg, #e3f2fd 0%, transparent 100%);
  border-left: 4px solid #1976d2;
  border-radius: 0.3rem;
  padding: 0.5rem 0.9rem;
  margin-bottom: 1rem;
  margin-top: 0.5rem;

  &--sub {
    font-size: 0.85rem;
    color: #37474f;
    border-left-color: #78909c;
    background: linear-gradient(90deg, #eceff1 0%, transparent 100%);
  }
}

/* Inputs */
.form__input {
  .q-field__inner {
    background: white;
    box-shadow: 0px 2px 8px rgba(0, 0, 0, 0.07);
    border-radius: 0.75rem;
    border: 1.5px solid #e0e6f0;
    padding: 0 1rem;
    transition: border-color 0.2s ease;

    &:focus-within {
      border-color: #1976d2;
    }
  }
}

/* Botones */
.claims-btn {
  border-radius: 0.75rem;
  padding: 0.7rem 1rem;
  font-weight: 600;
  letter-spacing: 0.03em;
}

@media (max-width: 780px) {
  .claims-header {
    border-radius: 0.75rem;
    padding: 1rem;
  }
  .form__input {
    .q-field__inner {
      padding: 0 0.7rem;
    }
  }
}
</style>