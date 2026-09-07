<script setup>
import { onMounted, ref, inject, watch, onBeforeUnmount, computed } from 'vue';
import { Notify } from 'quasar'
import { useRouter, useRoute } from 'vue-router';
import { useComunAreaStore } from '@/services/store/comunArea.store';
import { useReserveStore } from '@/services/store/reserve.store';
import iconsApp from '@/assets/icons/index'
import moment from 'moment';
import { usePayMethodStore } from '@/services/store/payMethod.store';
import culqiCheckout from '@/components/layout/culqiCheckout.vue';
import conditionPayLaterModal from '@/components/reserves/conditionPayLaterModal.vue';
import ruleDetailsModal from '@/components/reserves/ruleDetailsModal.vue';
import { useAuthStore } from '@/services/store/auth.services';
import { useMaintenanceStore } from '@/services/store/maintenance.store';


moment.locale('es', {
  weekdays: 'Domingo_Lunes_Martes_Miércoles_Jueves_Viernes_Sábado'.split('_'),
  weekdaysShort: 'Dom_Lun_Mar_Mie_Jue_Vie_Sab'.split('_'),
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  months: 'enero_febrero_marzo_abril_mayo_junio_julio_agosto_septiembre_octubre_noviembre_diciembre'.split('_'),
})
const myLocale = {
  days: 'Domingo_Lunes_Martes_Miércoles_Jueves_Viernes_Sábado'.split('_'),
  daysShort: 'DO_LU_MA_MI_JU_VI_SA'.split('_'),
  months: 'Enero_Febrero_Marzo_Abril_Mayo_Junio_Julio_Agosto_Septiembre_Octubre_Noviembre_Diciembre'.split('_'),
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  format24h: true,
  pluralDay: 'dias'
}
const showBankAccountModal = ref(false)
const typeModalShow = ref(false)
const movieModalShow = ref(false)
const rulesModal = ref(false)
const ruleDetailsModalVisible = ref(false);
const selectedRule = ref(null);
const comunAreaStore = useComunAreaStore()
const reserveStore = useReserveStore()
const paymentMethodStore = usePayMethodStore()
const authStore = useAuthStore()
const maintenanceStore = useMaintenanceStore()
const emitter = inject('emitter')
const comunAreas = ref([])
const blockedAreaIds = ref([])
const maintenanceDates = ref([])
const userApartments = computed(() => {
  if (authStore.user.role_id == 3) {
    return (authStore.user.departments_inquilino || [])
      .filter(p => p.departament?.type == 1)
      .map(p => ({
        id: p.departament_id,
        number: p.departament?.number ?? `Dpto ${p.departament_id}`,
      }))
  }
  if (!authStore.user?.units) return []
  return authStore.user.units.filter(u => u.type == 1)
})
const selectedComunArea = ref({})
const router = useRouter()
const disabledTime = ref(true)
const ready = ref(false)
const loading = ref(false)
const step = ref(1)
const selectedInterval = ref({})
const transitionName = ref('slide-next');
const isPayLaterModalOpen = ref(false)
const formData = ref({
  date: '',
  time_from: null,
  time_to: null,
  typeOfReserve: 0,
  note: '',
  is_exclusive: false,
  terms_accept: false,
  multa_accept: false,
  pay_later: false,
  departament_id: null,
})
const toPayId = ref(null)
const tapActive = ref(
  moment().format('D') !== moment(formData.value.date, 'YYYY/MM/DD').format('D')
    ? 'ma'
    : moment().format('H') < 12
      ? 'ma' : moment().format('H') >= 12 && moment().format('H') < 18
        ? 'ta' : 'no')
const intervalHorarys = ref({
  ma: [],
  ta: [],
  no: []
})
const selectedPayData = ref({})
const selectArea = (id) => {
  selectedComunArea.value = comunAreas.value.find((area) => area.id == id)
  formData.value.typeOfReserve = 0
  typeModalShow.value = true;
  visibleBackButton(false)
  fetchMaintenanceDates(id)
}
const openRuleDetails = (rule) => {
  selectedRule.value = rule;
  ruleDetailsModalVisible.value = true;
};
const backButton = () => {
  if (step.value == 2) {
    visibleBackButton(true)
    formData.value.date = ''
    emitter.emit('isReserve',
      {
        visible: false,
        data: {
          step: 1,
          icon: '',
          name: ''
        }
      })
  }
  if (step.value == 3) {
    tapActive.value =
      moment().format('D') !== moment(formData.value.date, 'YYYY/MM/DD').format('D')
        ? 'ma'
        : moment().format('H') < 12
          ? 'ma' : moment().format('H') >= 12 && moment().format('H') < 18
            ? 'ta' : 'no'
    selectedInterval.value = {};
    formData.value.time_from = null
    formData.value.to = null


  }
  if (step.value >= 4) {
    cleanFormPay()
  }
  step.value--

  emitter.emit('pagTitle', 'Selecciona area común')
}

const nextStep = () => {
  if (!validateStepForm()) {
    return
  }
  if (step.value == 1) {
      typeModalShow.value = false;
      const necesitaPago = formData.value.typeOfReserve > 1
        || (formData.value.typeOfReserve > 1 && selectedComunArea.value.price > 0)
        || (formData.value.typeOfReserve == 1 && selectedComunArea.value.warranty_price > 0)

      if (necesitaPago && authStore.user.bank_accounts?.length == 0) {
        showBankAccountModal.value = true
        return
      }
  }

  if (step.value == 3 && formData.value.typeOfReserve == 1 && !selectedComunArea.value.warranty_price) {
    createReserve()
    return
  } else {
    rulesModal.value = false
  }
  if (step.value == 4 && selectedPayData.value.id == 3) {
    prepareCulqiData()
  }
  if (step.value == 5) {

    !toPayId.value
      ? createReserve()
      : createPay(toPayId.value)

    return
  }

  emitter.emit('pagTitle', '')
  step.value++
  emitter.emit('isReserve',
    {
      visible: true,
      data: {
        step: step.value,
        icon: selectedComunArea.value.icon,
        name: selectedComunArea.value.name
      }
    })
  visibleBackButton(false)
}
const cleanForm = () => {
  formData.value = {
    date: '',
    time_from: '',
    time_to: '',
    note: '',
    is_exclusive: false,
  }
  disabledTime.value = true
}
const cleanFormPay = () => {
  if (step.value == 5) {
    payFormData.value.vaucher = null
    payFormData.value.date = ''
    payFormData.value.reference = ''
  }
  else {
    payFormData.value.pay_method = 0;
  }

}
const getComunsArea = () => {
  emitter.emit('pagTitle', 'Selecciona area común')
  comunAreaStore.getAllComunAreas()
    .then((response) => {
      if (response.code !== 200) throw response
      const allowedIds = (authStore.user.available_comun_areas || []).map(a => a.id)
      if (allowedIds.length > 0) {
        comunAreas.value = response.data.filter(area => allowedIds.includes(area.id) && area.status !== false)
      } else {
        comunAreas.value = response.data.filter(area => area.status !== false)
      }
      setTimeout(() => {
        ready.value = true
      }, 100)
    })
    .catch((response) => {
      console.error(response)
      showNotify('negative', 'Error al obtener areas comunes')
    })
}
const fetchBlockedAreas = () => {
  const departamentId = formData.value.departament_id || userApartments.value[0]?.id
  if (!departamentId) {
    blockedAreaIds.value = []
    return
  }
  reserveStore.getBookingsByDepartment(departamentId)
    .then((response) => {
      if (response.code !== 200) throw response
      const today = moment().format('YYYY-MM-DD')
      const activeBookings = response.data.filter(b => (b.date || '').slice(0, 10) >= today)
      blockedAreaIds.value = [...new Set(activeBookings.map(b => b.comun_area_id))]
    })
    .catch(() => {})
}
const getPayMethod = () => {
  paymentMethodStore.getPayMethod()
    .then((response) => {
      payMethods.value = response.data
    })
    .catch((response) => {
      console.error(response)
    })
}
const validateStepForm = () => {
  if (step.value == 1) {
    if (userApartments.value.length > 1 && !formData.value.departament_id) {
      showNotify('negative', 'Debes seleccionar el departamento')
      return false
    }
    !formData.value.typeOfReserve || formData.value.typeOfReserve == 0 ? showNotify('negative', 'Debes seleccionar el tipo de reserva') : ''
    return formData.value.typeOfReserve && formData.value.typeOfReserve != 0 ? true : false
  }
  if (step.value == 2) {
    !formData.value.date ? showNotify('negative', 'Debes seleccionar la fecha de la reserva') : ''
    return formData.value.date ? true : false
  }
  if (step.value == 3) {
    !formData.value.terms_accept || !formData.value.multa_accept ? showNotify('negative', 'Debes aceptar los terminos y pagos de multas') : ''
    return formData.value.terms_accept && formData.value.multa_accept ? true : false
  }
  if (step.value == 4) {
    !payFormData.value.pay_method || payFormData.value.pay_method == 0
      ? showNotify('negative', 'Debes seleccionar el metodo de pago') : ''
    return payFormData.value.pay_method && payFormData.value.pay_method != 0 ? true : false
  }
  if (step.value == 5) {
    !payFormData.value.date || !payFormData.value.reference || !payFormData.value.vaucher
      ? showNotify('negative', 'Debes seleccionar completar los datos y subir el comprobante') : ''
    return payFormData.value.date && payFormData.value.reference && payFormData.value.vaucher ? true : false
  }
  return true
}
const getAvaibleBookingByDay = () => {
  console.log('formData.value.date', formData.value.date);
  
  const alreadyBlocked = blockedAreaIds.value.includes(selectedComunArea.value.id)

  // if (alreadyBlocked) {
  //   showNotify('negative', 'Ya tienes una reserva activa para esta área. Solo podrás reservarla nuevamente cuando finalice el día de tu reserva.')
  //   formData.value.date = ''
  //   return
  // }

  const data = {
    idArea: selectedComunArea.value.id,
    date: formData.value.date,
    reserveType: formData.value.typeOfReserve
  }

  reserveStore.getAvailableReserveInDayByArea(data)
    .then((response) => {
      disabledTime.value = false
      intervalHorarys.value = response.data.blocks
    })
}

const fetchMaintenanceDates = (areaId) => {
  maintenanceDates.value = []
  maintenanceStore.getMaintenanceByArea(areaId, '')
    .then((response) => {
      if (selectedComunArea.value.id !== areaId) return
      maintenanceDates.value = response.map(m => ({
        date: moment(m.date, 'YYYY-MM-DD').format('YYYY/MM/DD'),
        time_from: m.time_from,
        time_to: m.time_to,
      }))
    })
    .catch((err) => {
      console.error('Error al obtener fechas de mantenimiento:', err)
    })
}

const daysAvailableForBook = (date) => {
  const isFutureOrToday = date >= moment().format('YYYY/MM/DD');

  if (!isFutureOrToday) return false;

  // Para el área "Lounge", máximo 30 días de anticipación
  const isLounge = selectedComunArea.value?.name?.toLowerCase().includes('lounge');
  if (isLounge) {
    const maxDate = moment().add(45, 'days').format('YYYY/MM/DD');
    if (date > maxDate) return false;
  }

  const maintenance = maintenanceDates.value.find(m => m.date === date)
  if (maintenance && (!maintenance.time_from || !maintenance.time_to)) {
    return false
  }

  if (!selectedComunArea.value || !selectedComunArea.value.schedules || selectedComunArea.value.schedules.length === 0) {
    return true;
  }
  const dayOfWeek = moment(date, 'YYYY/MM/DD').day();
  const hasSchedule = selectedComunArea.value.schedules.some(schedule => schedule.day === dayOfWeek);

  return hasSchedule;
}

const dateEvents = (date) => {
  return true;
}

const dateEventColor = (date) => {
  if (!daysAvailableForBook(date)) {
    return 'grey';
  }
  let cuposDisponibles = 5;
  if (cuposDisponibles === 0) {
    return 'negative';
  }
  if (cuposDisponibles > 0 && cuposDisponibles < 3) {
    return 'warning';
  }

  return 'positive'; 
}
const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}


const mediaUrl = import.meta.env.VITE_LARAVEL_MEDIA_URL

const visibleBackButton = (visible) => {
  const element = document.querySelector('.backButton');
  if (element) element.style.display = visible ? 'flex' : 'none'
}
const changeTap = (tab) => {
  tapActive.value = tab;
}
const setColor = (status) => {
  return status == 'Disponible'
    ? 'primary'
    : status == 'Últimos'
      ? 'warning'
      : 'negative'
}
const setSelectHour = (index) => {
  if (intervalHorarys.value[tapActive.value][index].status == 'Ocupado') {
    showNotify('negative', 'Selecciona un intervalo de tiempo disponible')
    return
  }
  selectedInterval.value = intervalHorarys.value[tapActive.value][index];
  formData.value.time_to = selectedInterval.value.time_to
  formData.value.time_from = selectedInterval.value.time_from
  selectedInterval.value.id = tapActive.value + '-' + index;
}
const openRuleModal = (status) => {
  if (!formData.value.time_from) {
    showNotify('negative', 'Selecciona un intervalo de tiempo para tu reserva')
    return
  }
  const isCine = selectedComunArea.value.name && selectedComunArea.value.name.toLowerCase().includes('cine');
  if(status){
    formData.value.multa_accept = false
    formData.value.terms_accept = false

  }
  if (status && isCine && formData.value.typeOfReserve == 1) {
    movieModalShow.value = true;
  } else {
    rulesModal.value = status
  }
}
const continueToRulesFromMovie = () => {
  if (!formData.value.note || formData.value.note.trim() === '') {
    showNotify('negative', 'Debes ingresar el nombre de la película para invitar a los demás');
    return;
  }
  movieModalShow.value = false;
  rulesModal.value = true;
}

const showPayLaterModal = (status) => {
  isPayLaterModalOpen.value = status
}
const handlePayLaterConfirm = async () => {
  isPayLaterModalOpen.value = false;
  formData.value.pay_later = true
  createReserve();
};

const payFormData = ref({
  pay_method: 0,
  amount: '',
  vaucher: null,
  reference: '',
  date: '',
  booking_id: null,
  type: 2
})


const payMethods = ref([])

const setPayData = (e) => {
  selectedPayData.value = payMethods.value.find(method => method.id == e)
}
const prepareCulqiData = () => {

  formData.value.comun_area = selectedComunArea.value.id
  formData.value.amount = formData.value.typeOfReserve > 1
    ? (selectedComunArea.value.price + selectedComunArea.value.warranty_price)
    : selectedComunArea.value.warranty_price || 0

  formData.value.exclusive = formData.value.is_exclusive ? 1 : 0;
}
const setTypeData = (e) => {
  if (e == 2) formData.value.is_exclusive = true
  else {
    formData.value.is_exclusive = false
  }
}



const formatCopy = (texto) => {
  copyData(texto.replaceAll(' ', '').trim())
}
const copyData = (texto) => {
  const element = document.getElementById('textToPasteData')
  const textArea = document.createElement('textarea');
  textArea.value = texto
  textArea.style.opacity = 0;

  element.appendChild(textArea);
  textArea.select();

  try {
    const success = document.execCommand('copy');
    showNotify('positive', 'Datos copiados con exito')
  } catch (err) {
  }
  finally {
    element.removeChild(textArea);
  }
}
const availableBeforeDaysToTodayOptions = (date) => {
  return date <= moment().format('YYYY/MM/DD');
}
const pegarTexto = async () => {
  if (!navigator.clipboard) {
    console.warn('La API del portapapeles no está disponible. Asegúrate de usar HTTPS o localhost.')
    return
  }
  try {
    const textoDelPortapapeles = await navigator.clipboard.readText()
    payFormData.value.reference = textoDelPortapapeles
  } catch (err) {
    console.error('Error al intentar pegar: ', err)
  }
}
const handleUpload = (event) => {
  const file = event.target.files[0];

  if (file) {
    if (!file.type.startsWith('image/')) {
      showNotify('negative', 'Por favor, selecciona solo un archivo de imagen.');
      return;
    }
    payFormData.value.vaucher = file;

  }
};
const fileSizeInMB = computed(() => {
  if (!payFormData.value.vaucher) return 0;
  const size = payFormData.value.vaucher.size / (1024 * 1024);

  return size.toFixed(2);
});
const createReserve = () => {
  formData.value.comun_area = selectedComunArea.value.id
  formData.value.amount = formData.value.typeOfReserve > 1
    ? (selectedComunArea.value.price + selectedComunArea.value.warranty_price)
    : selectedComunArea.value.warranty_price || 0

  formData.value.exclusive = formData.value.is_exclusive ? 1 : 0;

  loading.value = true
  reserveStore.createReserve(formData.value)
    .then((response) => {
      !response.data.toPay
        ? showNotify('positive', 'Reserva realizada con exito')
        : ''

      if (!response.data.toPay) {
        setTimeout(() => {
          loading.value = false
          router.push('/client/reserves/confirm-reserve/' + response.data.id)
          // rulesModal.value = false
        }, 1000);
        return
      }
      toPayId.value = response.data.id
      createPay(response.data.id)
    })
    .catch((response) => {
      console.error(response)
      setTimeout(() => {
        loading.value = false
        showNotify('negative', response)

      }, 2000);

    })
}

const createPay = (id) => {
  const dataForm = dataToForm(id)
  loading.value = true

  reserveStore.createReservePay(dataForm)
    .then((response) => {
      showNotify('positive', 'Reserva creada y pagada con exito')
      setTimeout(() => {
        loading.value = false
        router.push('/client/pay/details/' + response.data.idPay)
      }, 1000);
    })
    .catch((response) => {
      loading.value = false
      showNotify('negative', 'Error al crear el pago')

    })
}
const dataToForm = (id) => {
  const dataForm = new FormData()
  dataForm.append('amount', formData.value.amount)
  dataForm.append('vaucher', payFormData.value.vaucher)
  dataForm.append('reference', payFormData.value.reference)
  dataForm.append('pay_date', payFormData.value.date)
  dataForm.append('pay_method', payFormData.value.pay_method)
  dataForm.append('to_pay_id', id)
  dataForm.append('type', payFormData.value.type)
  return { data: dataForm }
}
const sanciones = computed(() => {
  let saciones = [];
  let reglasSancionables = selectedComunArea.value.rules_area.filter(area => area.suggest_amount)
  reglasSancionables.forEach(element => {
    saciones.push(
      {
        title: element.title,
        amount: element.suggest_amount
      }
    )
  });
  return saciones
});

const typeOfReserve = [
  {
    id: 1,
    title: 'Reserva Compartida (Sin costo)',
    description: ' ✔ Comparte el uso del espacio con otros residentes durante el horario seleccionado, según la capacidad disponible.',
    types: [1, 2]
  },
  {
    id: 2,
    title: 'Reserva Exclusiva',
    description: '🔒Disfruta del uso exclusivo de toda el área durante el horario reservado.',
    types: [2]
  },
  {
    id: 3,
    title: '🔒Reserva exclusiva',
    description: 'Tendrás el área completa reservada únicamente para ti durante el horario seleccionado.',
    types: [3, 4]
  }
]
const reservesByType = computed(() => {
  const type = selectedComunArea.value.type
  return typeOfReserve.filter(item => !item.types || item.types.includes(type))
})
const calculateDiffHour = computed(() => {
  const ahora = moment();
  const fechaReserva = moment(formData.value.date, 'YYYY/MM/DD');
  const diferenciaHoras = fechaReserva.diff(ahora, 'hours');

  return diferenciaHoras >= 24;
})
onMounted(() => {
  getComunsArea()
  getPayMethod()
  if (userApartments.value.length === 1) {
    formData.value.departament_id = userApartments.value[0].id
  }
  fetchBlockedAreas()
})

onBeforeUnmount(() => {
  emitter.emit('isReserve',
    {
      visible: false,
      data: {
        step: 1,
        icon: '',
        name: ''
      }
    })
});
const hrsFormat = (hr) => {
 return hr == 1
  ? moment.duration(hr, 'hours').asMinutes() + ' min'
  : hr + ' hrs'
}
const culqiSuccess = (data) => {
  alert('Bien ahi prepago')
};

watch(step,
  (toDepth, fromDepth) => {
    transitionName.value = toDepth > fromDepth ? 'slide-next' : 'slide-prev';
  }
);
</script>
<template>
  <div class=" h-full " style="overflow: hidden; position: relative;">
    <div class="h-full md:px-20 md:mx-16 " v-if="ready">
      <q-form @submit="nextStep()" class="h-full ">
        <Transition :name="transitionName">
          <div class="px-2 form-step row" v-if="step == 1">
            <div class="col-md-3 col-4 px-2 md:px-5 my-3" v-for="comunArea in comunAreas" :key="comunArea.id">
              <div class="boxContentV2 " :class="{ '': blockedAreaIds.includes(comunArea.id) }">
                <!-- <div class="boxItem_v2  md:px-6 " 
                @click="blockedAreaIds.includes(comunArea.id) 
                ? showNotify('negative', 'Ya tienes una reserva activa para esta área. Solo podrás reservarla nuevamente cuando finalice el día de tu reserva') 
                : selectArea(comunArea.id)"> -->
                <div class="boxItem_v2  md:px-6 " 
                @click=" selectArea(comunArea.id)">
                  <div class="flex justify-center items-center h-full w-full ">
                    <img :src="mediaUrl + '/images/icons/' + comunArea.icon " alt=""  
                    :class="{'h-full': !comunArea.name.includes('Sauna'), 'h-5/6': comunArea.name.includes('Sauna')}">
                  </div>
                  <div v-if="blockedAreaIds.includes(comunArea.id)" class="areaBlockedBadge">
                    Reservado
                  </div>
                </div>
                <div class="text-center mt-0 pt-1 px-2 text-title-squadV2 text-white text-ellipsis ellipsis "
                  style="background: #2d5eaa; height: 27%;">
                  {{ comunArea.name }}
                </div>
                <div v-if="comunArea.type > 1" class="pintype"
                  :class="{ 'bg-warning': comunArea.type !== 1 }">
                  S/.
                </div>
              </div>
            </div>
          </div>
        </Transition>
        <Transition :name="transitionName">
          <div class="h-full form-step" style="overflow: hidden;" v-if="step > 1">
            <div class=" w-full h-full ">
              <div class="w-full  pt-2 pb-2 px-4" style="height:15%">
                <div class="text-center text-[#5571b7] font-bold text-lg md:text-xl mb-2 mt-0 " v-if="step > 3">
                  <div class="font-bold text-2xl">PAGAR Y CONFIRMAR</div>
                </div>
                <div class="relative flex justify-between items-center px-3">
                  <div class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5/6 h-1.5 bg-[#d9dee8] z-0"></div>
                  <div v-for="n in 4" :key="n"
                    class="relative z-10 w-9 h-9 md:w-10 md:h-10 rounded-full flex items-center justify-center font-bold text-sm md:text-base transition-colors duration-300"
                    :class="{
                      'bg-[#79b5a8] text-white': n < step,      /* Completado: Verde/Teal */
                      'bg-[#5571b7] text-white': n === step,    /* Actual: Azul oscuro */
                      'bg-[#d9dee8] text-[#202c44]': n > step   /* Futuro: Gris claro */
                    }">
                    {{ n }}
                  </div>
                </div>

                <div class="text-center text-[#5571b7] font-bold text-lg md:text-xl mt-3 mb-4" v-if="step < 4">
                  <div class="font-bold text-2xl" v-if="step === 1">Selecciona área común</div>
                  <div class="font-bold text-2xl" v-else-if="step === 2">Fecha de reserva</div>
                  <div class="font-bold text-2xl" v-else-if="step === 3">Selecciona la hora</div>

                </div>
              </div>
              <div :style="{ height: step != 3 ? '75%' : '65%' }" style=" overflow: auto;" class="pb-5">
                <div class="row w-full pt-2">
                  <template v-if="step == 2">
                    <div class="flex flex-center w-full q-px-md">
                      <q-date color="tealedf" v-model="formData.date" minimal mask="YYYY/MM/DD" class="w-full calendarReserve custom-pins"
                        :options="daysAvailableForBook" @update:model-value="getAvaibleBookingByDay"
                        text-color="primary" :navigation-min-year-month="moment().format('YYYY/MM')" :locale="myLocale"
                        :events="dateEvents" :event-color="dateEventColor">
                      </q-date>
                      
                      <!-- Leyenda de disponibilidad -->
                      <div class="flex justify-center items-center mt-3 w-full legend-pins">
                        <div class="flex items-center"><span class="pin-dot bg-positive"></span>Disponible</div>
                        <div class="flex items-center"><span class="pin-dot bg-warning"></span>Pocos cupos</div>
                        <div class="flex items-center"><span class="pin-dot bg-negative"></span>Completo</div>
                        <div class="flex items-center"><span class="pin-dot bg-grey"></span>Bloqueado</div>
                      </div>
                      <div class="w-full px-2">
                        <div class="bg-primary mt-4 py-2 w-full textInfoContainer">
                          <div class="text-white dateInfoTitle text-center">Fecha seleccionada:</div>
                          <div class="text-white dateInfoContent text-center">
                            {{ formData.date ? moment(formData.date, 'YYYY/MM/DD').format('dddd DD [de] MMMM [del] YYYY') : '-----' }}
                          </div>
                        </div>
                      </div>
                    </div>
                  </template>
                  <template v-if="step == 3">
                    <div class="col-12  row md:px-32 px-4">
                      <div class="selectedDateBlock flex  items-center justify-between px-4 w-full py-2">
                        <div>
                          <div class="text-dateBlockTitle">Fecha elegida</div>
                          <div class="text-primary text-bold text-dateBlock">
                            {{ formData.date ? moment(formData.date, 'YYYY/MM/DD').format('dddd DD') : '-----' }}
                             {{ formData.date ? moment(formData.date, 'YYYY/MM/DD').format('DD/mm/yyyy') : '-----' }}
                            - 
                            {{  hrsFormat(formData.typeOfReserve == 2 
                              ? selectedComunArea.max_time_reserve_exclusive 
                              : selectedComunArea.max_time_reserve) }}
                          </div>
                        </div>
                        <div class="flex gap-2 justify-end w-full mt-1">
                          <q-btn outline color="tealedf" rounded no-caps class="backFecha" @click="backButton()">
                            <div class="text-bold text-sm">
                              Cambiar fecha
                            </div>
                          </q-btn>
                        </div>
                      </div>
                      <div class="w-full">
                        <div class="tabBloque flex flex-center w-full pt-5">
                          <div class="tabBloque__item flex flex-center mx-1 cursor-pointer"
                            :class="{ 'active': tapActive == 'ma' }" @click="changeTap('ma')">Mañana</div>
                          <div class="tabBloque__item flex flex-center mx-1 cursor-pointer"
                            :class="{ 'active': tapActive == 'ta' }" @click="changeTap('ta')">Tarde</div>
                          <div class="tabBloque__item flex flex-center mx-1 cursor-pointer"
                            :class="{ 'active': tapActive == 'no' }" @click="changeTap('no')">Noche</div>
                        </div>
                        <div class="mt-2 blockHoursContent  py-2 px-3">
                          <template v-if="intervalHorarys[tapActive].length > 0">
                            <div class="flex items-center justify-between blockHoursContent__item my-2 px-4 py-2"
                              v-for="(interval, index) in intervalHorarys[tapActive]"
                              :class="{ 'activeHour': selectedInterval?.id == (tapActive + '-' + index) }" :key="index"
                              @click="setSelectHour(index)">
                              <div class="text_interval pt-1">{{ interval.time_from }} - {{
                                interval.time_to }}</div>
                              <div>
                                <q-chip :color="selectedInterval?.id !== index ? (interval.maintenance ? 'warning' : setColor(interval.status)) : 'tealedf'"
                                  text-color="white" size="0.8rem">
                                  <div style="font-size: 0.7rem;" class="">
                                    <template v-if="selectedInterval?.id === index">Seleccionado</template>
                                    <template v-else-if="interval.maintenance">Mantenimiento</template>
                                    <template v-else>
                                      {{ interval.status }}
                                      <span v-if="selectedComunArea?.max_cupo" class="q-ml-xs">({{ interval.occupancy }}/{{ selectedComunArea.max_cupo }})</span>
                                    </template>
                                  </div>
                                </q-chip>
                              </div>
                            </div>
                          </template>
                          <template v-else>
                            <div class="flex flex-center column my-2 px-4 py-1">
                              <q-icon name="eva-clock-outline" color="grey-6" size="3rem" />
                              <div class="text-bold mt-1 text-grey-6">
                                No hay horarios disponibles para la reserva
                              </div>
                            </div>
                          </template>
                        </div>
                      </div>
                    </div>
                  </template>
                  <template v-if="step == 4">
                    <div class="col-12 col-md-6 row md:px-5 px-4 mt-1">
                      <div class="w-full">
                        <div class="text-xl font-bold pl-2">
                          Resumen de reserva y pago
                        </div>
                        <div class="mt-2 blockHoursContent  py-2 px-3">
                          <div class="text-confirmSubtitleArea">
                            Servicio
                          </div>
                          <div class="text-confirmTitleArea ">
                            {{ selectedComunArea.name }} - Pacifik
                          </div>
                          <div class="text-confirmSubtitleArea mt-3">
                            Fecha y hora
                          </div>
                          <div class="text-grey-10 text-confirmDate">
                            {{ formData.date ? moment(formData.date, 'YYYY/MM/DD').format('ddd DD') : '-----' }}
                            - {{ formData.time_from ? moment(formData.date + ' ' + formData.time_from, 'YYYY/MM/DD HH:mm').format('hh:mm A')
                              : '**:** **' }} - {{ selectedComunArea.max_time_reserve == 1
                              ? moment.duration(selectedComunArea.max_time_reserve, 'hours').asMinutes() + ' min'
                              : selectedComunArea.max_time_reserve + ' hrs'
                            }}
                          </div>
                        </div>
                        <div class="rulesContainer mt-4 px-2 pt-2 pb-1">
                          <div class="px-2 rulesContainer__Title">
                            Método de pago
                          </div>
                          <div>
                            <div v-for="method in payMethods" :key="method.id"
                              :class="{ 'activeMethodContainer': payFormData.pay_method == method.id }"
                              class="flex items-center justify-between selectMethodItem mb-2 mt-3 py-2 px-3">
                              <q-radio color="tealedf" :class="{ 'activeMethod': payFormData.pay_method == method.id }"
                                class="item_method-radio" v-model="payFormData.pay_method"
                                checked-icon="eva-checkmark-circle-outline" :val="method.id" :label="method.name"
                                @update:model-value="setPayData" />
                            </div>
                          </div>
                        </div>
                        <div class="selectedDateBlock mt-4 px-3 w-full py-2">
                          <div class="text-lg font-bold ">
                            Resumen de pago
                          </div>
                          <div class="flex items-center justify-between w-full" v-if="selectedComunArea.price > 0">
                            <div class="text__amountItem">Reserva ({{ selectedComunArea.max_time_reserve == 1
                              ? moment.duration(selectedComunArea.max_time_reserve, 'hours').asMinutes() + ' min'
                              : selectedComunArea.max_time_reserve + ' hrs' }})</div>
                            <div class="text__amountItem">S/ {{ selectedComunArea.price.toFixed(2) }}</div>
                          </div>
                          <div class="flex items-center justify-between w-full">
                            <div class="text__amountItem">Garantia</div>
                            <div class="text__amountItem">S/ {{ selectedComunArea.warranty_price.toFixed(2) }}</div>
                          </div>
                          <div class="flex items-center justify-between w-full mt-1 "
                            style="border-top: 2px solid #8b8e9446;">
                            <div class="text__amountTotal">Total</div>
                            <div class="text__amountTotal">
                              S/ {{ formData.typeOfReserve > 1
                                ? (selectedComunArea.price + selectedComunArea.warranty_price).toFixed(2)
                                : selectedComunArea.warranty_price.toFixed(2) }}
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </template>
                  <template v-if="step == 5">
                    <div class="col-12  row md:px-5 px-4 mt-1">
                      <div class="w-full">
                        <div class="text-lg font-bold pl-2">
                          Pago
                        </div>
                        <div class="text-caption font-medium pl-2 text-grey-7">
                          Método seleccionado:
                          {{ selectedPayData.name }}
                        </div>
                        <template v-if="selectedPayData.id !== 3">
                          <div class="font-medium pl-2 mt-3 " style="font-size:0.9rem">
                            Datos de cuenta a pagar
                          </div>
                          <div class="selectedDateBlock mt-1 px-3 w-full py-2">
                            <div v-for="(line, index) in selectedPayData.data" :key="index"
                              class=" my-1 flex items-center justify-between">
                              <div class="flex items-center " :class="{ 'w-full': line.title == 'QR' }">
                                <div class="text-md text-grey-10 ">{{ line.title }}: </div>
                                <img style="width: 8rem;" :src="line.data" alt="" v-if="line.type.value == 2"
                                  class="mx-auto">
                                <div v-else class="text-md text-grey-10 ml-1">{{ line.data }}</div>
                              </div>
                              <div v-html="iconsApp.copyIcon" class="cursor-pointer" v-if="line.title == 'CCI' || line.title == 'N\u00b0 de cuenta'"
                                @click="line.title.includes('Titular') ? copyData(line.data) : formatCopy(line.data)" />
                            </div>
                          </div>
                          <div class=" row mt-2 md:px-12">
                            <div class="col-12 mt-0">
                              <div class=" md:pr-4">
                                <q-input color="tealedf" label="Fecha de pago" v-model="payFormData.date"
                                  :rules="[val => !(!val) || 'Fecha es requerida']" dense borderless clearable
                                  class="form__inputsReverse mt-1">
                                  <template v-slot:append>
                                    <q-icon name="eva-calendar-outline" class="cursor-pointer">
                                      <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                                        <q-date mask="DD-MM-YYYY" v-model="payFormData.date"
                                          :options="availableBeforeDaysToTodayOptions"
                                          :navigation-min-year-month="moment().format('YYYY/MM')" :locale="myLocale">
                                          <div class="row items-center justify-end">
                                            <q-btn v-close-popup label="Aceptar" color="primary" flat />
                                          </div>
                                        </q-date>
                                      </q-popup-proxy>
                                    </q-icon>
                                  </template>
                                </q-input>

                              </div>
                            </div>
                            <div class="col-12 mt-0 ">
                              <div class=" md:pr-4">
                                <q-input color="tealedf" label="Referencia de pago" dense borderless clearable
                                  v-model="payFormData.reference" class="form__inputsReverse mt-0" :maxlength="12"
                                  placeholder="N° de referencia de la operacion" hint="Solo caracteres numéricos"
                                  mask="############"
                                  :rules="[val => !(!val) || 'La referencia de pago es obligatoria']">

                                  <template v-slot:append>
                                    <q-btn color="tealedf" size="0.1rem" outline style="padding:3px 6px" no-caps
                                      @click="pegarTexto()">
                                      <div class="text-xs">
                                        Pegar
                                      </div>
                                    </q-btn>
                                  </template>
                                </q-input>
                              </div>
                            </div>
                          </div>
                          <div class=" rulesContainer mt-0 px-3 w-full py-2">
                            <label for="vaucherPay">
                              <template v-if="!payFormData.vaucher">
                                <div class=" flex flex-center column">

                                  <q-icon name="eva-image-outline" size="3rem" color="grey-5" />
                                  <div class="text-center">
                                    <div class="text-grey-7 font-medium">
                                      Sube tu comprobante de pago
                                    </div>
                                    <div class="text-grey-6 font-medium">
                                      Pulsa o haz click aqui para carga tu archivo
                                    </div>
                                  </div>
                                </div>
                              </template>
                              <template v-else>
                                <div class="flex items-center justify-between">
                                  <div class="flex items-center">
                                    <q-icon color="tealedf" name="eva-checkmark-circle-2" />
                                    <div class="ml-1">
                                      <div class="text-xsImage text-tealedf">Voucher adjuntado correctamente</div>
                                      <div class="text-xsImage text-black"> {{ payFormData.vaucher.name.slice(0, 10)
                                        }}***{{
                                          payFormData.vaucher.name.slice(-5) }} - {{ fileSizeInMB }} MB</div>
                                    </div>
                                  </div>
                                </div>
                              </template>
                            </label>
                            <input type="file" id="vaucherPay" style="display: none;" accept="image/*"
                              @change="handleUpload">
                            <div></div>
                          </div>

                          <div class="selectedDateBlock mt-4 px-1 w-full py-2">
                            <q-chip color="tealedf" text-color="white" size="0.8rem">
                              <div style="font-size: 0.7rem;" class="">
                                Pendiente de validación
                              </div>
                            </q-chip>
                            <div class="text-xsImage  px-2">Tu comprobante será revisado por administración</div>
                            <div class="text-xsImage text-grey-7  px-2">
                              Te notificaremos cuando el pago sea validado
                            </div>
                          </div>
                        </template>
                        <template v-else>
                          <div class="text-md text-grey-10  w-full flex flex-center pt-10">
                            <culqiCheckout :externalData="formData" @success="culqiSuccess" />
                          </div>
                        </template>
                      </div>
                    </div>
                  </template>
                </div>
              </div>
              <div :style="{ height: step != 3 ? '10%' : '20%' }" class="buttonSection">
                <div class="row  ">
                  <template v-if="step >= 4">
                    <div class="col-4 flex flex-center ">
                      <q-btn outline color="grey-8" unelevated no-caps class="" style="width: 90%; border-radius: 3rem;"
                        @click="backButton()">
                        <div class="py-0 md:py-0">
                          Volver
                        </div>
                      </q-btn>
                    </div>
                    <div class="col-4 flex flex-center" v-if="calculateDiffHour && step ==4">
                      <q-btn outline color="warning" unelevated no-caps class=""
                        style="width: 95%; border-radius: 3rem;" @click="showPayLaterModal(true)">
                        <div class="py-0 md:py-0" style="font-weight: 500;">
                          Pagar luego
                        </div>
                      </q-btn>
                    </div>
                    <div class="flex flex-center" :class="{ 'col-4': calculateDiffHour && step == 4, 'col-8': !calculateDiffHour || step != 4 }">
                      <q-btn outline color="primary" unelevated no-caps class=""
                        style="width: 95%; border-radius: 3rem;" type="submit" :loading="loading">
                        <div class="py-0 md:py-0" style="font-weight: 500;">
                          {{ step == 4 ? 'Pagar ahora' : 'Realizar reserva' }}
                        </div>
                      </q-btn>
                    </div>
                  </template>
                  <template v-if="step == 2">
                    <div class="col-3 p-2 flex flex-center ">
                      <q-btn outline color="grey-8" unelevated no-caps class=""
                        style="width: 100%; border-radius: 3rem;" @click="backButton()">
                        <div class="py-0 md:py-0">
                          Volver
                        </div>
                      </q-btn>
                    </div>
                    <div class="col-9 pr-2 flex flex-center">
                      <q-btn color="tealedf" unelevated="" class="" style="width: 100%; border-radius: 2rem;"
                        type="submit" :loading="loading">
                        <div class="flex w-full flex-center">
                          <div class="py-1 md:py-1 font-bold mr-2" style="font-size:0.95rem">
                            Elegir horario
                          </div>
                          <!-- <div class="flex flex-center"
                            style="height:1.7rem; width:1.7rem; border:2px solid white; border-radius:50%">
                            <q-icon name="eva-arrow-forward-outline" size="1rem" />
                          </div> -->
                        </div>
                      </q-btn>
                    </div>
                  </template>
                  <template v-if="step == 3">
                    <div class="col-12 flex flex-center px-5">
                      <div class="selectedDateBlock row  px-4 w-full py-2">
                        <div class="col-12">
                          <div class="text-dateBlockTitle">Reserva</div>
                          <div class=" text-bold text-dateBlockBottom">
                            {{ formData.date ? moment(formData.date, 'YYYY/MM/DD').format('ddd DD') : '-----' }}
                            - {{ formData.time_from ? moment(formData.date + ' ' + formData.time_from, 'YYYY/MM/DD HH:mm').format('hh:mm A')
                              : '**:** **' }}
                          </div>
                        </div>
                        <div class="col-12 flex justify-between w-full mt-1">
                          <q-btn outline color="grey-7" rounded no-caps class="backFecha" @click="backButton()">
                            <div class="text-bold text-sm">
                              Volver
                            </div>
                          </q-btn>
                          <q-btn color="primary" unelevated rounded no-caps class="" @click="openRuleModal(true)">
                            <div class="text-bold text-sm">
                              Confirmar
                            </div>
                          </q-btn>
                        </div>
                      </div>
                    </div>
                  </template>
                </div>
              </div>
            </div>
          </div>

        </Transition>
        <div>
          <Transition :name="transitionName">
            <div class="h-full rulesModal px-3" style="overflow: hidden;" v-if="rulesModal">
              <div class="pb-4" style="overflow:auto; height:91%">
                <div class="text-center my-2 font-bold text-2xl text-primary">INSTRUCCIONES DE USO</div>
                <div class="importantInfo__reserve py-2 pl-3 pr-1">
                  <div class="text-importantInfo text-grey-7">⚠️ Importante</div>
                  <div class="text-importantInfo">
                    Antes de utilizar esta área, lee y acepta las normas de uso. El incumplimiento del reglamento puede generar sanciones, multas o la suspensión temporal del acceso al área.
                  </div>
                </div>
                <div class="rulesContainer mt-2 px-2 pt-2 pb-1">
                  <div class="px-2 rulesContainer__Title">
                    Reglas de uso - {{ selectedComunArea.name }}
                  </div>
                  <div class="rulesContainer__Subtitle text-grey-7 px-2">
                    Seguridad - higiene y convivencia
                  </div>
                  <div class="flex items-center justify-between ruleDetailContainer my-2 py-2 px-3 cursor-pointer"
                    v-for="(rule, index) in selectedComunArea.rules_area" :key="rule.id" @click="openRuleDetails(rule)">
                    <div class="flex items-center" style="width: 90%;">
                      <div class="ruleDetailContainer__index flex flex-center"
                        :class="{ 'bg-warning': rule.severity == 2, 'bg-negative': rule.severity == 3, 'bg-tealedf': rule.severity == 1 }">
                        {{ index + 1 }}
                      </div>
                      <div class="ml-2 "
                        style="font-size:0.82rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ rule.title }}
                      </div>
                    </div>
                    <q-icon name="eva-info-outline" color="grey-6" size="1.2rem" />
                  </div>
                </div>
                <div class="rulesContainer mt-2 px-2 pt-2 pb-1">
                  <div class="px-2 rulesContainer__Title">
                    ⛔ Sanciones
                  </div>
                  <div class="rulesContainer__Subtitle text-grey-7 px-2">
                    Multas por incumplimiento. Las sanciones se aplican de acuerdo con el Reglamento Interno del edificio.
                  </div>
                  <div class="flex items-center justify-between ruleDetailContainer my-1 py-1 px-3">
                    <div class="ml-1 font-bold" style="font-size:0.78rem">
                      Infracción
                    </div>
                    <div class="mr-1 font-bold" style="font-size:0.78rem">
                      Multa
                    </div>
                  </div>
                  <div class="flex items-center justify-between ruleDetailContainer my-2 py-1 px-3"
                    v-for="(infraccion, index) in sanciones" :key="index">
                    <div class="ml-1 font-bold text-negative" style="font-size:0.78rem">
                      {{ infraccion.title }}
                    </div>
                    <div class="mr-1 font-bold text-negative" style="font-size:0.78rem">
                      S/ {{ infraccion.amount.toFixed(2) }}
                    </div>
                  </div>
                </div>
                <div class="importantInfo__reserve py-2 pl-1  pr-1 mt-2">
                  <div class="px-3 rulesContainer__Title">
                    Aceptación de normas y condiciones
                  </div>
                  <div class="px-2">
                    <div class="mt-1">
                      <q-checkbox v-model="formData.terms_accept" class="check__accept"
                        checked-icon="eva-checkmark-circle-outline">
                        <div class="ml-2 pt-1 accept_text">
                          {{ 'He leído y acepto las normas de uso del area ' + selectedComunArea.name }}
                        </div>
                      </q-checkbox>

                    </div>
                    <div class="mt-1">
                      <q-checkbox v-model="formData.multa_accept" class="check__accept"
                        checked-icon="eva-checkmark-circle-outline">
                        <div class="ml-2 accept_text pt-1 ">
                          Acepto las sanciones y multas aplicables en caso de incumplimiento.
                        </div>
                      </q-checkbox>

                    </div>
                    <div class="accept_text text-grey-7 px-2 mt-1">
                      * ℹ️ Debes marcar ambas casillas para continuar con la reserva.
                    </div>
                  </div>
                </div>
              </div>
              <div class="row py-0 " style="height: 9%;">
                <div class="col-4 flex flex-center ">
                  <q-btn outline color="grey-8" unelevated no-caps class="" style="width: 90%; border-radius: 3rem;"
                    @click="rulesModal = false; openRuleModal(false)">
                    <div class="py-0 md:py-0">
                      Volver
                    </div>
                  </q-btn>
                </div>
                <div class="col-8 flex flex-center">
                  <q-btn outline color="primary" unelevated no-caps class="" style="width: 90%; border-radius: 3rem;"
                    type="submit" :loading="loading">
                    <div class="py-0 md:py-0" style="font-weight: 500;">
                      Aceptar y continuar
                    </div>
                  </q-btn>
                </div>
              </div>
            </div>
          </Transition>
          <Transition :name="transitionName">
            <div class="h-full rulesModal px-3" style="overflow: hidden;" v-if="typeModalShow">
              <div class="pb-4" style="overflow:auto; height:91%">
                <div class="text-center py-2 font-bold text-2xl text-primary">Información del área</div>
                <div class="pt-2 px-2 text-grey-9" style="font-weight:400; font-size:0.99rem; white-space: pre-line">
                  {{ selectedComunArea.description }}
                </div>
                <div class=" px-2 font-bold text-lg text-grey-9 pt-2 mt-2 border-t">
                  Cupo por horarios: {{ selectedComunArea.max_cupo }} cupo(s)
                </div>
                <div class="pt-2 px-0 text-grey-9" style="font-weight:400; font-size:0.99rem">
                  <q-chip :color="selectedComunArea.type_color">
                    <div class="text-white" style="font-weight:600">
                      {{ selectedComunArea.type_label_large }}
                    </div>
                  </q-chip>
                </div>
                <div class="text-center pt-4 font-bold text-2xl text-primary">Opciones de reserva</div>
                <div v-if="userApartments.length > 1" class="mt-4 px-2">
                  <div style="font-weight: 500; font-size: 0.95rem; margin-bottom: 8px;">Selecciona tu departamento:
                  </div>
                  <q-select v-model="formData.departament_id" :options="userApartments" option-value="id"
                    option-label="number" emit-value map-options outlined dense color="tealedf" label="Departamento"
                    @update:model-value="fetchBlockedAreas" />
                </div>
                <div>
                  <div v-for="type in reservesByType" :key="type.id"
                    :class="{ 'activeMethodContainer': formData.typeOfReserve == type.id }"
                    class="flex items-center justify-between selectTypeItem mb-2 mt-3 py-2 px-3">
                    <q-radio color="tealedf" :class="{ 'activeMethod': formData.typeOfReserve == type.id }"
                      class="item_method-radio" v-model="formData.typeOfReserve"
                      checked-icon="eva-checkmark-circle-outline" :val="type.id" @update:model-value="setTypeData">
                      <div class="flex justify-between">
                        <div style="width:80%">
                          <div style="font-weight:500;">
                            {{ type.title }}
                          </div>
                          <div class=" text-xs pt-1 text-grey-9">
                            {{ type.description }}
                          </div>
                        </div>
                        <div v-if="type.id > 1" class="text-negative" style="font-size:0.82rem; font-weight:500;">
                          S/ {{ selectedComunArea.price.toFixed(2) }}
                        </div>
                      </div>
                    </q-radio>

                  </div>
                </div>

                <div class="mt-4 px-1 mb-2">
                  <div class="rounded-xl p-0" style="">
                    <div class="bg-white rounded-lg p-3" style="border: 2px solid #dce8f4; " v-if="selectedComunArea.warranty_price > 0"  >
                      <div class="flex justify-between items-center mb-1">
                        <div class="flex items-center text-[#2d5eaa] font-bold" style="font-size: 1.05rem;">
                          🛡️
                          Depósito en garantía
                        </div>
                        <div class="text-[#2d5eaa] font-bold" style="font-size: 1.05rem;">
                          S/ {{ selectedComunArea.warranty_price.toFixed(2) }}
                        </div>
                      </div>
                      <div class="text-grey-8 mt-1" style="font-size: 0.8rem; line-height: 1.4;">
                       Se requiere para todas las reservas y se devuelve una vez finalizada la actividad, previa inspección del área.
                      </div>
                    </div>
                    
                    <div class="mt-3 text-grey-8 flex px-2" v-if="selectedComunArea.warranty_price > 0 || selectedComunArea.price > 0" style="font-size: 0.85rem; line-height: 1.3;">
                      <span class="mr-1">*💳 El pago puede realizarse mediante transferencia bancaria o con tarjeta de crédito o débito.</span>
                    </div>
                  </div>
                </div>

              </div>
              <div class="row py-0 " style="height: 9%;">
                <div class="col-4 flex flex-center">
                  <q-btn outline color="grey-8" unelevated no-caps class="" style="width: 90%; border-radius: 3rem;"
                    @click="typeModalShow = false; visibleBackButton(true); formData.typeOfReserve = 0">
                    <div class="py-0 md:py-0">
                      Volver
                    </div>
                  </q-btn>
                </div>
                <div class="col-8 flex flex-center">
                  <q-btn outline color="primary" unelevated no-caps class="" style="width: 90%; border-radius: 3rem;"
                    type="submit">
                    <div class="py-0 md:py-0" style="font-weight: 500;">
                      Continuar
                    </div>
                  </q-btn>
                </div>
              </div>
            </div>
          </Transition>
          <Transition :name="transitionName">
            <div class="h-full rulesModal px-3" style="overflow: hidden;" v-if="movieModalShow">
              <div class="pb-4 flex flex-col justify-center" style="overflow:auto; height:91%">
                
                <div class="flex flex-center mt-0">
                   <q-icon name="eva-film-outline" size="4rem" color="primary" />
                </div>
                
                <div class="text-center py-2 font-bold text-2xl text-primary">Cartelera de Cine</div>
                
                <div class="pt-2 px-3 text-grey-9 text-center" style="font-weight:400; font-size:0.95rem;">
                  Al ser una reserva compartida, se creará un evento en la comunidad invitando a los residentes a asistir.
                  <br>
                  <b>Coloca el nombre de la pelicula</b>
                </div>
                
                <div class="mt-6 px-4">
                  <q-input color="tealedf" label="¿Qué película proyectarás?" dense borderless clearable
                    v-model="formData.note" class="form__inputsReverse mt-0" :maxlength="12"
                    :rules="[val => !(!val) || 'Nombre de la pelicula es obligatorio']"  placeholder="Ej. El Padrino, Avengers, etc.">
                  </q-input>
                </div>
              </div>

              <div class="row py-0 " style="height: 9%;">
                <div class="col-4 flex flex-center">
                  <q-btn outline color="grey-8" unelevated no-caps class="" style="width: 90%; border-radius: 3rem;"
                    @click="movieModalShow = false; formData.note = ''">
                    <div class="py-0 md:py-0">
                      Volver
                    </div>
                  </q-btn>
                </div>
                <div class="col-8 flex flex-center">
                  <q-btn outline color="primary" unelevated no-caps class="" style="width: 90%; border-radius: 3rem;"
                    @click="continueToRulesFromMovie()">
                    <div class="py-0 md:py-0" style="font-weight: 500;">
                      Continuar
                    </div>
                  </q-btn>
                </div>
              </div>
            </div>
          </Transition>
        </div>
      </q-form>
      <div id="textToPasteData" />
      <conditionPayLaterModal :dialog="isPayLaterModalOpen" @closeModal="showPayLaterModal(false)"
        @confirmPayLater="handlePayLaterConfirm" />

      <q-dialog v-model="showBankAccountModal" persistent>
        <q-card style="min-width: 340px">
          <q-card-section class="text-center q-pt-lg">
            <q-icon name="eva-credit-card-outline" size="48px" color="warning" />
            <h4 class="text-h6 text-bold q-mt-md q-mb-sm">Cuenta bancaria requerida</h4>
            <p class="text-body2 text-grey-8 q-px-md">
              Para realizar reservas con costo o garantía necesitas tener una
              cuenta bancaria registrada para recibir reembolsos.
            </p>
          </q-card-section>
          <q-card-actions align="center" class="q-pb-lg q-gutter-md">
            <q-btn flat  no-caps label="Cancelar" color="grey" v-close-popup />
            <q-btn unelevated no-caps label="Crear cuenta" color="primary" class="px-5"
              @click="router.push('/client/account-bank/add')" />
          </q-card-actions>
        </q-card>
      </q-dialog>

      <ruleDetailsModal v-model="ruleDetailsModalVisible" :rule="selectedRule" />
    </div>
    <div v-else class="flex flex-center py-24 w-full">
      <q-spinner-dots color="primary" size="7rem" />
    </div>
  </div>
</template>
<style lang="scss">
.text__amountItem {
  font-size: 1rem;
  font-weight: 500;
  margin-bottom: 0.1rem;
}

.text-xsImage {
  font-size: 0.76rem;
  font-weight: 600;
}

.text__amountTotal {
  font-size: 1.16rem;
  font-weight: 500;
  color: #4d6bb4;
  margin-bottom: 0.1rem;
}

.item_method-radio {
  width: 100%;

  & .q-radio__inner {
    width: 1rem;
    min-width: 1rem;
    height: 1rem;
    margin-right: 0.5rem;
  }

  & .q-radio__label {
    width: 100%;
  }

  & .q-radio__bg {
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
  }

}

.selectMethodItem {
  border: 2px solid lightgrey;
  border-radius: 8rem;
  transition: all 0.2s ease-in;
}

.selectTypeItem {
  border: 2px solid lightgrey;
  border-radius: 0.8rem;
  transition: all 0.2s ease-in;
}

.activeMethodContainer {
  border: 2px solid #72b9af;
  background: #f0f1f6;
}

.text-confirmTitleArea {
  font-size: 1.4rem;
  color: #4d6bb4;
  font-weight: bolder;
}

.text-confirmDate {
  font-size: 1.2rem;
  font-weight: 500;
}

.text-confirmSubtitleArea {
  font-size: 0.97rem;
  color: rgb(139, 139, 139);
  font-weight: 500;
}

.accept_text {
  font-size: 0.8rem;
  font-weight: 500;
}

.check__accept {
  & .q-checkbox__inner {
    min-width: 1.2rem;
    width: 1.2rem;
    height: 1.2rem;
  }

  & .q-checkbox__bg {
    top: 0;
    left: 0;
    height: 100%;
    width: 100%;
  }
}

.text-importantInfo {
  font-size: 0.75rem;
  font-weight: 500;
}

.ruleDetailContainer {
  background: #f0f1f6;
  border: 2px solid lightgrey;
  border-radius: 8rem;

  &__index {
    height: 1.4rem;
    width: 1.4rem;
    border-radius: 50%;
    color: white;
    font-size: 0.8rem;
  }
}

.importantInfo__reserve {
  background: #f0f1f6;
  border: 2px solid lightgrey;

  border-radius: 0.8rem;
}

.rulesContainer {
  border: 2px solid lightgrey;
  border-radius: 0.8rem;

  &__Title {
    font-size: 1.1rem;
    font-weight: bold;
  }

  &__Subtitle {
    font-size: 0.76rem;
    font-weight: 500;
  }

}

.rulesModal {
  position: absolute;
  top: 0;
  bottom: 0;
  left: 0;
  right: 0;
  background: white;
}

.text-title-squadV2 {
  color: #1e5f96;
  font-weight: 500;
  font-size: 0.9rem;
}

.text_interval {
  font-size: 1rem;
  font-weight: 500;
  transition: all 0.2s ease-in;
}

.blockHoursContent {
  border: 2px solid lightgrey;
  border-radius: 0.7rem;

  &__item {
    border: 2px solid lightgray;
    border-radius: 0.8rem;
    transition: all 0.2s ease-in;

    &.activeHour {
      border: 2px solid #72b9af;
      background: #f0f1f6;

      & .text_interval {
        color: #006396;
      }
    }
  }

}

.tabBloque {
  &__item {
    padding: 0.3rem 0.8rem;
    background: white;
    border: 2px solid lightgray;
    border-radius: 1.5rem;
    font-size: 0.8rem;
    transition: all 0.2s ease-in-out;

    &.active {
      color: white;
      background: $primary;
      border: 2px solid $primary;
    }
  }
}

.backFecha {
  background: white !important;
}

.selectedDateBlock {
  border: 2px solid lightgray;
  border-radius: 0.5rem;
  background: #f0f1f6;

  & .text-dateBlockTitle {
    font-size: 0.8rem;
  }

  & .text-dateBlock {
    font-size: 1.2rem;
  }
}

.text-dateBlockBottom {
  font-size: 1rem;
}

.dateInfoContent {
  font-weight: 600;
  font-size: 1.3rem;
}

.legend-pins {
  font-size: 0.8rem;
  color: #555;
  flex-wrap: wrap;
  gap: 0.8rem;
}
.pin-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
  margin-right: 5px;
}

.textInfoContainer {
  border-radius: 0.7rem;
}

.dateInfoTitle {
  font-weight: 500;
  font-size: 0.96rem;
}


.form-step {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
}

.calendarReserve {
  border: 2px solid lightgray;
  border-radius: 0.5rem;
  background: #f0f1f6;

  &.q-date {
    box-shadow: none;
  }
  & .q-date__calendar-item--out div{
    border-radius: 5px;
    border: 0px!important;
    background: rgba(14, 197, 14, 0.075);
    margin-bottom: 0.4rem;
  }
  & .q-date__today{
    border: none;
    box-shadow: 0px 0px 0px 1px rgb(105, 105, 105);
  }
  
  /* Posicionar los pines en la esquina superior derecha */
  &.custom-pins .q-date__event {
    position: absolute;
    top: 4px;
    right: 4px;
    bottom: auto;
    left: auto;
    transform: none;
    width: 6px;
    height: 6px;
    border-radius: 50%;
  }
  &.q-date__calendar-item > div, .q-date__calendar-item button{
    border-radius: 5px;
    border: 0px!important;
    background: rgba(34, 231, 34, 0.144);
    margin-bottom: 0.4rem!important;;
  }
  & .q-date__calendar-weekdays .q-date__calendar-item {
    opacity: 1 !important;
    margin-top: 0.7rem;
    margin-bottom: 0.7rem;
    

    & div {
      
      background: #5571b7;
      color: white;
      border-radius: 50rem;
      width: 2rem !important;
      height: 2rem !important;
      font-size: 0.9rem;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;

    }

    &:last-child div,
    &:first-child div {

      color: white;
      background-color: $primary;
    }
  }

  & .q-date__calendar-days .q-date__calendar-item {

    opacity: 0.5;

    & button {
      width: 40px;
      height: 40px;
    }

    &.q-date__calendar-item--out div {
      color: $primary;
      font-size: 1.1rem;
      font-weight: 600 !important;
    }

    &.q-date__calendar-item--in {
      opacity: 1 !important;

      & .block {
        color: $primary;
        font-size: 1.1rem;
        font-weight: 600;
      }

      &.q-date__calendar-item--in:nth-child(7n + 1) .block,
      &.q-date__calendar-item--in:nth-child(7n) .block {
        color: $primary;
        /* Tu color dorado */
      }

      & .q-btn--unelevated .block {
        color: white !important;
      }
      & .q-btn--unelevated {
        background:  rgb(28, 67, 177)
      }
    }
  }

}

.q-date__navigation,
.q-time__clock-position {
  color: black;
}

.q-time__clock-position--active {
  color: white;
}

.q-date__calendar-item {
  color: black;
}



.md\:order-3 {
  order: 3
}

.text-body2x {
  font-size: 0.9rem;
}

.headerSection {
  background: #0282d259;
  font-weight: 500;
  color: #006396;
}



.selectAreaItem {
  border-radius: 0.6rem;
  position: relative;
  box-shadow: 0px 2px 5px 0px #bfbfbf65;
  border: 1px solid #bfbfbfa3;
  transition: all 0.2s ease-out;
  cursor: pointer;

  &:hover {
    transform: scale(1.01);
  }
}

.form__inputsReverse {
  & .q-field__inner {
    border-radius: 2rem;
    border: 2px solid #76b7af;
    padding: 0px 1rem;
  }

  &.q-field--dense.q-field--float .q-field__label {
    display: none;
  }

  &.q-field--labeled.q-field--dense .q-field__native {
    padding-top: 5px;
  }
}



.boxContentV2 {
  background: $primary;
  overflow: hidden;
  position: relative;
  height: 8.1rem;

  &.areaBlocked {
    opacity: 0.5;
    //pointer-events: none;
  }
}

.areaBlockedBadge {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: rgba(0, 0, 0, 0.7);
  color: white;
  padding: 4px 12px;
  border-radius: 1rem;
  font-size: 0.7rem;
  font-weight: 600;
  white-space: nowrap;
  z-index: 10;
}

.pintype {
  height: 1.35rem;
  width: 1.35rem;
  display: flex;
  justify-content: center;
  align-items: center;
  border-radius: 50%;
  position: absolute;
  left: 0.4rem;
  top: 0.4rem;
  color: white;
  font-size: 0.68rem;

}

.boxItem_v2 {
  border-radius: 0.8rem;
  overflow: visible;
  position: relative;
  width: 100%;
  background-repeat: no-repeat;
  background-size: cover;
  transition: all 0.7s ease-in-out;
  cursor: pointer;
  height: 73%;

  &:hover {
    transform: scale(1.03);
  }
}

@media (max-width: 780px) {

  .boxContentV2 {
    height: 6.5rem;
  }

  .form__inputsReverse {
    & .q-field__inner {

      padding: 0rem 1rem;
    }
  }
}
</style>