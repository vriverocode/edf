<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { Notify } from 'quasar'
import { useApartmentStore } from '@/services/store/apartment.store'
import { usePayStore } from '@/services/store/pay.store'
import { useReserveStore } from '@/services/store/reserve.store'
import moment from 'moment'
import esMx from 'moment/locale/es-mx'
moment.locale('es-mx', {
  relativeTime: {
            future: 'en %s',
            past: 'hace %s',
            s: 'unos segundos',
            ss: '%d segundos',
            m: 'un minuto',
            mm: '%d minutos',
            h: 'una hora',
            hh: '%d horas',
            d: 'un día',
            dd: '%d días',
            w: 'una semana',
            ww: '%d semanas',
            M: 'un mes',
            MM: '%d meses',
            y: 'un año',
            yy: '%d años',
        },
});
const route = useRoute()
const apartmentStore = useApartmentStore()
const payStore = usePayStore()
const reserveStore = useReserveStore()

const loading = ref(true)
const apartment = ref(null)
const owner = ref(null)
const ownerPays = ref([])
const ownerReserves = ref([])

const ownerName = computed(() => owner.value?.name || 'Sin propietario asignado')

const loadOwnerInfo = async () => {
  loading.value = true
  try {
    const apartmentResponse = await apartmentStore.getApartmentById(route.params.id)
    if (apartmentResponse.code !== 200) throw apartmentResponse
    apartment.value = apartmentResponse.data
    owner.value = apartmentResponse.data.owner || null

    if (!owner.value) {
      ownerPays.value = []
      ownerReserves.value = []
      return
    }

    const [paysResponse, reservesResponse] = await Promise.all([
      payStore.getPaysByUser({}),
      reserveStore.getReservesByUser({})
    ])

    ownerPays.value = (paysResponse.data || []).filter((pay) => pay.user_id === owner.value.id)
    ownerReserves.value = (reservesResponse.data || []).filter((booking) => booking.user_id === owner.value.id)
  } catch (error) {
    Notify.create({
      type: 'negative',
      message: 'No se pudo cargar la información del propietario'
    })
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadOwnerInfo()
})
</script>

<template>
  <div class="md:px-32 px-2 pb-12 h-full" style="overflow: auto; ">
    <q-card flat bordered class="q-mb-md">
      <q-card-section>
        <div class="text-h6 text-weight-bold">Propietario del {{apartment?.type_label}}</div>
        <div class="text-subtitle2 q-mt-xs" v-if="apartment">
          Unidad #{{ apartment.number }}  <template v-if="apartment.type == 1">
            - {{ apartment.address }}
          </template>
        </div>
      </q-card-section>
      <q-separator />
      <q-card-section v-if="!loading">
        <div class="row q-col-gutter-md">
          <div class="col-12 col-md-6">
            <div class="text-caption text-grey-7">Nombre</div>
            <div class="text-subtitle1 text-weight-medium">{{ ownerName }}</div>
          </div>
          <div class="col-12 col-md-6" v-if="owner">
            <div class="text-caption text-grey-7">Correo</div>
            <div class="text-subtitle1 text-weight-medium">{{ owner.email }}</div>
          </div>
          <div class="col-12 col-md-6" v-if="owner">
            <div class="text-caption text-grey-7">Teléfono</div>
            <div class="text-subtitle1 text-weight-medium">{{ owner.phone || 'Sin teléfono' }}</div>
          </div>
          <div class="col-12 col-md-6" v-if="owner">
            <div class="text-caption text-grey-7">Creado:</div>
            <div class="text-subtitle1 text-weight-medium">{{ moment(owner.created_at).from(moment()) || '-----'}}</div>
          </div>
          <div class="col-12 col-md-6" v-if="owner">
            <div class="text-caption text-grey-7">Estado</div>
            <q-badge :color="owner.status === 2 ? 'negative' : 'positive'" class="q-mt-xs" >
                <div class="px-2 py-1 text-weight-medium" style="font-size:0.9rem">
                  {{ owner.status_label || '---' }}
                </div>
            </q-badge>
          </div>
        </div>
      </q-card-section>
      <q-card-section v-else class="flex flex-center">
        <q-spinner-dots color="primary" size="3rem" />
      </q-card-section>
    </q-card>

    <q-card flat bordered class="q-mb-md">
      <q-card-section class="row items-center justify-between">
        <div class="text-subtitle1 text-weight-bold">Historial de pagos</div>
        <q-badge color="primary">{{ ownerPays.length }}</q-badge>
      </q-card-section>
      <q-separator />
      <q-card-section v-if="!loading && ownerPays.length === 0" class="text-grey-7">
        Sin pagos registrados para este propietario.
      </q-card-section>
      <q-card-section v-else class="q-pa-none">
        <q-markup-table flat dense class="desktopTable">
          <thead>
            <tr>
              <th class="text-left " style="padding:0.8rem">Código</th>
              <th class="text-left " style="padding:0.8rem">Fecha</th>
              <th class="text-left " style="padding:0.8rem">Monto</th>
              <th class="text-left " style="padding:0.8rem">Tipo</th>
              <th class="text-left " style="padding:0.8rem">Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="pay in ownerPays" :key="pay.id">
              <td class="" style="padding:0.8rem">{{ pay.pay_id }}</td>
              <td class="" style="padding:0.8rem">{{ pay.pay_date }}</td>
              <td class="" style="padding:0.8rem">S/ {{ pay.amount }}</td>
              <td class="" style="padding:0.8rem">{{ pay.title_pay }}</td>
              <td class="" style="padding:0.8rem">
                <q-badge :color="pay.status_color">{{ pay.status_label }}</q-badge>
              </td>
            </tr>
          </tbody>
        </q-markup-table>
        <div class="mobileCards q-pa-sm">
          <q-card v-for="pay in ownerPays" :key="'m-pay-' + pay.id" flat bordered class="q-mb-sm">
            <q-card-section>
              <div class="text-body2 text-weight-medium">{{ pay.pay_id }}</div>
              <div class="text-caption text-grey-7">{{ pay.pay_date }}</div>
              <div class="q-mt-sm">S/ {{ pay.amount }} - {{ pay.title_pay }}</div>
              <q-badge class="q-mt-sm" :color="pay.status_color">{{ pay.status_label }}</q-badge>
            </q-card-section>
          </q-card>
        </div>
      </q-card-section>
    </q-card>

    <q-card flat bordered>
      <q-card-section class="row items-center justify-between">
        <div class="text-subtitle1 text-weight-bold">Historial de reservas</div>
        <q-badge color="deep-purple">{{ ownerReserves.length }}</q-badge>
      </q-card-section>
      <q-separator />
      <q-card-section v-if="!loading && ownerReserves.length === 0" class="text-grey-7">
        Sin reservas registradas para este propietario.
      </q-card-section>
      <q-card-section v-else class="q-pa-none">
        <q-markup-table flat dense class="desktopTable">
          <thead>
            <tr>
              <th class="text-left" style="padding:0.8rem" >Reserva</th>
              <th class="text-left" style="padding:0.8rem" >Área común</th>
              <th class="text-left" style="padding:0.8rem" >Fecha</th>
              <th class="text-left" style="padding:0.8rem" >Horario</th>
              <th class="text-left" style="padding:0.8rem" >Estado</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="booking in ownerReserves" :key="booking.id">
              <td style="padding:0.8rem">#{{ booking.booking_number }}</td>
              <td style="padding:0.8rem">{{ booking.comun_area?.name || '-' }}</td>
              <td style="padding:0.8rem">{{ booking.date }}</td>
              <td style="padding:0.8rem">{{ booking.time_from }} - {{ booking.time_to }}</td>
              <td style="padding:0.8rem">
                <q-badge :color="booking.status_color">{{ booking.status_label }}</q-badge>
              </td>
            </tr>
          </tbody>
        </q-markup-table>
        <div class="mobileCards q-pa-sm">
          <q-card v-for="booking in ownerReserves" :key="'m-res-' + booking.id" flat bordered class="q-mb-sm">
            <q-card-section>
              <div class="text-body2 text-weight-medium">#{{ booking.booking_number }}</div>
              <div class="text-caption text-grey-7">{{ booking.date }} | {{ booking.time_from }} - {{ booking.time_to }}</div>
              <div class="q-mt-sm">{{ booking.comun_area?.name || 'Sin área común' }}</div>
              <q-badge class="q-mt-sm" :color="booking.status_color">{{ booking.status_label }}</q-badge>
            </q-card-section>
          </q-card>
        </div>
      </q-card-section>
    </q-card>
  </div>
</template>

<style scoped>
.ownerInfoPage {
  max-width: 1100px;
  margin: 0 auto;
}

.mobileCards {
  display: none;
}

@media (max-width: 768px) {
  .desktopTable {
    display: none;
  }

  .mobileCards {
    display: block;
  }
}
</style>
