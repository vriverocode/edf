<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useUserStore } from '@/services/store/users.store'
import moment from 'moment'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

const user = ref(null)
const loading = ref(false)
const error = ref(null)

const userId = route.params.id || route.query.id

const rolColors = {
  1: { bg: '#6c5ce7', label: 'Admin' },
  2: { bg: '#0984e3', label: 'Propietario' },
  3: { bg: '#00b894', label: 'Inquilino' },
  4: { bg: '#fdcb6e', label: 'Familiar' },
  5: { bg: '#e17055', label: 'Airbnb' },
  6: { bg: '#636e72', label: 'Trabajador' },
  7: { bg: '#74b9ff', label: 'Prop. Parcial' },
  8: { bg: '#a29bfe', label: 'Super-Admin' },
}

const rolColor = computed(() => rolColors[user.value?.rol_id]?.bg ?? '#b2bec3')
const rolLabel = computed(
  () => user.value?.rol?.name ?? rolColors[user.value?.rol_id]?.label ?? '—'
)

const avatarInitial = computed(() =>
  user.value?.name ? user.value.name.charAt(0).toUpperCase() : '?'
)

const statusInfo = computed(() => {
  const s = user.value?.status
  if (s === 2) return { label: 'Moroso', color: 'negative', icon: 'eva-alert-triangle-outline' }
  if (s === 3) return { label: 'Inactivo', color: 'grey-6', icon: 'eva-slash-outline' }
  return { label: 'Al día', color: 'positive', icon: 'eva-checkmark-circle-outline' }
})

const apartments = computed(() => (user.value?.units ?? []).filter((u) => u.type === 1))
const parkings = computed(() => (user.value?.units ?? []).filter((u) => u.type === 2))
const deposits = computed(() => (user.value?.units ?? []).filter((u) => u.type === 3))

const showUnits = computed(() => {
  const r = user.value?.rol_id
  return r === 2 || r === 3 || r === 4 || r === 5 || r === 7
})

const showPayments = computed(() => {
  const r = user.value?.rol_id
  return r !== 1 && r !== 7 && r !== 6
})

const getUserById = async (id) => {
  try {
    loading.value = true
    error.value = null

    const response = await userStore.getUserDetail(id)
    if (response.code !== 200) throw response
    user.value = response.data
  } catch (err) {
    console.error('Error al obtener el usuario:', err)
    error.value = err?.error || err?.message || 'Error al cargar el usuario'
  } finally {
    loading.value = false
  }
}

const reloadUser = () => {
  if (userId) {
    getUserById(userId)
  }
}

const goToUsersList = () => {
  router.push('/admin/users/list')
}

const formatDate = (date) => (date ? moment(date).format('DD/MM/YYYY') : '—')

onMounted(() => {
  if (userId) {
    getUserById(userId)
  } else {
    error.value = 'ID de usuario no proporcionado'
  }
})
</script>

<template>
  <div class="h-full relative overflow-hidden">
    <div class="relative pt-8 pb-8 md:px-6 px-3 h-full" style="overflow:auto">
      <!-- Loading -->
      <div v-if="loading" class="flex flex-col items-center justify-center py-20">
        <q-spinner-dots color="primary" size="4rem" />
        <p class="text-gray-600 font-medium mt-4">Cargando usuario...</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="flex flex-col items-center justify-center py-20">
        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mb-6">
          <svg class="w-10 h-10 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">¡Ups! Algo salió mal</h2>
        <p class="text-gray-600 text-center mb-6">{{ error }}</p>
        <button
          @click="reloadUser"
          class="px-6 py-3 bg-red-500 text-white rounded-full font-medium hover:bg-red-600 transition-colors"
        >
          Reintentar
        </button>
      </div>

      <!-- Usuario cargado -->
      <div v-else-if="user" class="flex flex-col items-center md:px-28 md:mx-28 pb-8">
        <div
          class="bg-white rounded-xl shadow-lg border border-gray-100 flex flex-col items-center w-full"
        >
          <!-- Header -->
          <div class="row w-full mb-3 items-start">
            <div class="col-12 text-right">
              <div class="flex justify-end md:pb-1">
                <div class="px-4 py-2 dateFact text-primary text-md font-bold">
                  <span class="text-grey-7 font-medium text-md">Registrado el:</span>
                  {{ formatDate(user.created_at) }}
                </div>
              </div>
            </div>
            <div class=" items-center col-12 px-2 pt-3 row">
              <div class="col-3">
                <div
                  class="bg-primary rounded-xl p-4 flex flex-center text-white font-bold"
                  style="min-width: 4rem; min-height: 4rem; font-size: 2rem"
                >
                  {{ avatarInitial }}
                </div>
              </div>
              <div class="col pl-2">
                <h1 class="text-xl font-bold text-gray-900">{{ user.name }}</h1>
                <div class="flex items-center gap-1 mt-2">
                  <q-chip
                    dense
                    class="text-white text-weight-bold px-2"
                    :style="{ background: rolColor }"
                  >
                    {{ rolLabel }}
                  </q-chip>
                  <q-chip
                    dense
                    :color="statusInfo.color"
                    text-color="white"
                    class="text-weight-bold px-2"
                  >
                    <q-icon :name="statusInfo.icon" size="0.9rem" class="mr-1 " />
                    {{ statusInfo.label }}
                  </q-chip>
                </div>
              </div>
              <div class="flex flex-col gap-2 pr-2">
                <q-btn
                  unelevated
                  icon="eva-edit-2-outline"
                  color="primary"
                  size="0.85rem"
                  style="border-radius: 0.5rem"
                  @click="router.push('/admin/users/form/update/' + user.id)"
                >
                  <span class="ml-1 hidden md:block">Editar</span>
                </q-btn>
                <q-btn
                  v-if="showPayments"
                  unelevated
                  icon="eva-credit-card-outline"
                  color="amber-7"
                  size="0.85rem"
                  style="border-radius: 0.5rem"
                  @click="router.push('/admin/pays/user/' + user.id)"
                >
                  <span class="ml-1 hidden md:block">Pagos</span>
                </q-btn>
              </div>
            </div>
          </div>

          <!-- Detalles -->
          <div class="w-full md:p-5 px-4 pt-5 mb-5" style="border-top: 1px solid lightgray">
            <div class="space-y-4">
              <!-- Datos personales -->
              <div class="text-sm font-bold text-gray-900 uppercase">Datos personales</div>

              <div
                class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534)"
              >
                <span class="text-gray-600 font-medium">Nombre completo</span>
                <span class="text-gray-900 font-semibold">{{ user.name || '—' }}</span>
              </div>
              <div
                class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534)"
              >
                <span class="text-gray-600 font-medium">Usuario</span>
                <span class="text-gray-900 font-semibold">{{ user.username || '—' }}</span>
              </div>
              <div
                class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534)"
              >
                <span class="text-gray-600 font-medium">Correo electrónico</span>
                <a
                  v-if="user.email"
                  :href="'mailto:' + user.email"
                  class="text-primary font-semibold"
                  style="text-decoration: none"
                >
                  {{ user.email }}
                </a>
                <span v-else class="text-gray-900 font-semibold">—</span>
              </div>
              <div
                class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534)"
              >
                <span class="text-gray-600 font-medium">Teléfono</span>
                <a
                  v-if="user.phone"
                  :href="'tel:' + user.phone"
                  class="text-primary font-semibold"
                  style="text-decoration: none"
                >
                  {{ user.phone }}
                </a>
                <span v-else class="text-gray-900 font-semibold">—</span>
              </div>
              <div
                class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534)"
              >
                <span class="text-gray-600 font-medium">DNI</span>
                <span class="text-gray-900 font-semibold">{{ user.dni || '—' }}</span>
              </div>
              <div
                class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534)"
              >
                <span class="text-gray-600 font-medium">Primer inicio</span>
                <span>
                  <q-badge
                    v-if="user.is_first_time"
                    color="warning"
                    text-color="dark"
                    label="Pendiente configuración"
                  />
                  <span v-else class="text-positive font-semibold">Completo</span>
                </span>
              </div>

              <!-- Unidades -->
              <template v-if="showUnits">
                <div class="text-sm font-bold text-gray-900 uppercase pt-2">Unidades asignadas</div>

                <div
                  v-if="apartments.length > 0"
                  class="flex justify-between items-center pb-2"
                  style="border-bottom: 1px solid rgba(211, 211, 211, 0.534)"
                >
                  <span class="text-gray-600 font-medium">Departamentos</span>
                  <div class="text-right">
                    <div
                      v-for="apt in apartments"
                      :key="apt.id"
                      class="text-gray-900 font-semibold flex items-center gap-1 justify-end"
                    >
                      <q-icon name="eva-home-outline" size="1rem" color="primary" />
                      N° {{ apt.number }}
                      <span v-if="apt.floor" class="text-gray-500 font-normal text-xs"
                        >· Piso {{ apt.floor }}</span
                      >
                    </div>
                  </div>
                </div>

                <div
                  v-if="parkings.length > 0"
                  class="flex justify-between items-center pb-2"
                  style="border-bottom: 1px solid rgba(211, 211, 211, 0.534)"
                >
                  <span class="text-gray-600 font-medium">Estacionamientos</span>
                  <div class="text-right">
                    <div
                      v-for="park in parkings"
                      :key="park.id"
                      class="text-gray-900 font-semibold flex items-center gap-1 justify-end"
                    >
                      <q-icon name="eva-car-outline" size="1rem" color="teal" />
                      N° {{ park.number }}
                    </div>
                  </div>
                </div>

                <div
                  v-if="deposits.length > 0"
                  class="flex justify-between items-center pb-2"
                  style="border-bottom: 1px solid rgba(211, 211, 211, 0.534)"
                >
                  <span class="text-gray-600 font-medium">Depósitos</span>
                  <div class="text-right">
                    <div
                      v-for="dep in deposits"
                      :key="dep.id"
                      class="text-gray-900 font-semibold flex items-center gap-1 justify-end"
                    >
                      <q-icon name="eva-archive-outline" size="1rem" color="orange-7" />
                      N° {{ dep.number }}
                    </div>
                  </div>
                </div>

                <div
                  v-if="apartments.length === 0 && parkings.length === 0 && deposits.length === 0"
                  class="flex justify-between items-center pb-2"
                  style="border-bottom: 1px solid rgba(211, 211, 211, 0.534)"
                >
                  <span class="text-gray-600 font-medium">Unidades asignadas</span>
                  <span class="text-gray-500 font-medium">Sin unidades asignadas</span>
                </div>
              </template>

              <!-- Período Airbnb -->
              <template v-if="user.rol_id === 5 && (user.active_time || user.end_time)">
                <div class="text-sm font-bold text-gray-900 uppercase pt-2">Período Airbnb</div>

                <div
                  class="flex justify-between items-center pb-2"
                  style="border-bottom: 1px solid rgba(211, 211, 211, 0.534)"
                >
                  <span class="text-gray-600 font-medium">Fecha inicio</span>
                  <span class="text-gray-900 font-semibold">{{
                    formatDate(user.active_time)
                  }}</span>
                </div>
                <div
                  class="flex justify-between items-center pb-2"
                  style="border-bottom: 1px solid rgba(211, 211, 211, 0.534)"
                >
                  <span class="text-gray-600 font-medium">Fecha fin</span>
                  <span class="text-gray-900 font-semibold">{{ formatDate(user.end_time) }}</span>
                </div>
              </template>

              <!-- Áreas permitidas -->
              <template v-if="user.available_comun_areas && user.available_comun_areas.length > 0">
                <div class="text-sm font-bold text-gray-900 uppercase pt-2">
                  Áreas permitidas para reservar
                </div>

                <div
                  class="flex justify-between items-center pb-2"
                  style="border-bottom: 1px solid rgba(211, 211, 211, 0.534)"
                >
                  <span class="text-gray-600 font-medium">Áreas</span>
                  <div class="flex flex-wrap gap-1 justify-end">
                    <q-chip
                      v-for="area in user.available_comun_areas"
                      :key="area.id"
                      dense
                      color="primary"
                      text-color="white"
                    >
                      {{ area.name }}
                    </q-chip>
                  </div>
                </div>
              </template>

              <!-- Información del sistema -->
              <div class="text-sm font-bold text-gray-900 uppercase pt-2">
                Información del sistema
              </div>

              <div
                class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534)"
              >
                <span class="text-gray-600 font-medium">ID de usuario</span>
                <span class="text-gray-900 font-semibold">#{{ user.id }}</span>
              </div>
              <div
                class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534)"
              >
                <span class="text-gray-600 font-medium">Estado de cuenta</span>
                <q-badge :color="statusInfo.color" :label="statusInfo.label" />
              </div>
              <div
                class="flex justify-between items-center pb-2"
                style="border-bottom: 1px solid rgba(211, 211, 211, 0.534)"
              >
                <span class="text-gray-600 font-medium">Fecha de registro</span>
                <span class="text-gray-900 font-semibold">{{ formatDate(user.created_at) }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- No encontrado -->
      <div v-else class="flex flex-col items-center justify-center py-20">
        <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mb-6">
          <svg
            class="w-10 h-10 text-gray-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
            />
          </svg>
        </div>
        <h2 class="text-xl font-bold text-gray-900 mb-2">Usuario no encontrado</h2>
        <p class="text-gray-600 text-center mb-6">
          El usuario solicitado no existe o no tienes permisos para verlo.
        </p>
        <button
          @click="goToUsersList"
          class="px-6 py-3 bg-gray-500 text-white rounded-full font-medium hover:bg-gray-600 transition-colors"
        >
          Volver al listado
        </button>
      </div>
    </div>
  </div>
</template>

<style lang="scss">
.dateFact {
  border-bottom: 1px solid $primary;
  border-left: 1px solid $primary;
  width: fit-content;
  border-bottom-left-radius: 1rem;
}
</style>
