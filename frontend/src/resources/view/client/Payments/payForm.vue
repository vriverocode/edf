<script setup>
import { ref, onMounted, computed, inject, watch } from 'vue';
import transfer from '@/assets/img/util/transfer.webp'
import cash from '@/assets/img/util/cash.webp'
import { useRoute, useRouter } from 'vue-router';
import { useReserveStore } from '@/services/store/reserve.store'
import { useQuotaStore } from '@/services/store/quota.store'
import { usePayMethodStore } from '@/services/store/payMethod.store'
import { useNotificationsStore } from '@/services/store/notifications.store'
import { useAuthStore } from '@/services/store/auth.services'
import { storeToRefs } from 'pinia';
import iconsApp from '@/assets/icons/index'
import moment from 'moment';
import { Notify } from 'quasar';

const myLocale = {
  /* starting with Sunday */
  days: 'Domingo_Lunes_Martes_Miércoles_Jueves_Viernes_Sábado'.split('_'),
  daysShort: 'Dom_Lun_Mar_Mié_Jue_Vie_Sáb'.split('_'),
  months: 'Enero_Febrero_Marzo_Abril_Mayo_Junio_Julio_Agosto_Septiembre_Octubre_Noviembre_Diciembre'.split('_'),
  monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split('_'),
  firstDayOfWeek: 1, // 0-6, 0 - Sunday, 1 Monday, ...
  format24h: true,
  pluralDay: 'dias'
}

const getUnitInfo = (type) => {
  if (type === 2) return { name: 'Estacionamiento', icon: '🚗' };
  if (type === 3) return { name: 'Depósito', icon: '📦' };
  return { name: 'Departamento', icon: '🏢' };
}
const route = useRoute()
const router = useRouter()
const reserveStore = useReserveStore()
const quotaStore = useQuotaStore()
const payMethodStore = usePayMethodStore()
const authStore = useAuthStore()
const { currencySymbol } = storeToRefs(authStore)

const ready = ref(false)
const step = ref(1)
const loading = ref(false)
const disable = ref(true)
const materialIcons = inject('materialIcons')
const toPay = ref({})
const transitionName = ref('slide-next');
const typePay = () => {

  return ['quotaPay'].includes(route.name) ? 'quota' : 'reserve'
}
const payFormData = ref({
  pay_method: 0,
  amount: '',
  vaucher: null,
  reference: '',
  date: '',
  booking_id: route.params.id || route.query.id,
  quota_id: route.params.id || route.query.id,
  type: typePay() == 'quota' ? 1 : 2
})

const notificationsStore = useNotificationsStore()

const payMethods = ref([])

const paymentSubtitle = computed(() => {
  if (!toPay.value || !Object.keys(toPay.value).length) return ''
  return typePay() === 'quota' && toPay.value.month_label
    ? `MANTENIMIENTO ${String(toPay.value.month_label).toUpperCase()}`
    : toPay.value.comun_area?.name
      ? `RESERVA ${String(toPay.value.comun_area.name).toUpperCase()}`
      : 'PAGO'
})
const isQuotaPayment = computed(() => typePay() === 'quota')
const safeAmount = (value) => Number(value ?? 0)
const maintenanceAmount = computed(() => safeAmount(toPay.value?.maintenance_amount))
const waterAmount = computed(() => safeAmount(toPay.value?.water_amount))
const waterConsumptionM3 = computed(() => safeAmount(toPay.value?.water_consumption_m3))
const waterPricePerM3 = computed(() => safeAmount(toPay.value?.water_price_per_m3))
const maintenanceParticipation = computed(() => safeAmount(
  toPay.value?.maintenance_participation_percentage ?? toPay.value?.departament?.participation_percentage
))
const maintenanceBudget = computed(() => safeAmount(toPay.value?.maintenance_budget_total))
const waterDetailsLink = computed(() => (
  toPay.value?.id ? `/client/quota/water-detail/${toPay.value.id}` : null
))
const maintenanceDetailsLink = computed(() => (
  toPay.value?.id ? `/client/quota/details/month/${toPay.value.month}` : null
))
const quotaBreakdown = computed(() => {
  const rows = Array.isArray(toPay.value?.breakdown) ? toPay.value.breakdown : []
  return rows.map((item) => {
    const info = getUnitInfo(Number(item?.unit_type ?? 1))
    return {
      quotaId: item?.id ?? '-',
      unitLabel: `${info.name} ${item?.unit_number ?? '-'}`,
      participation: safeAmount(item?.participation),
      waterM3: safeAmount(item?.water_consumption_m3),
      waterAmount: safeAmount(item?.water_amount),
      maintenanceAmount: safeAmount(item?.maintenance_amount),
      totalAmount: safeAmount(item?.amount),
    }
  })
})
const amountPrefix = computed(() => currencySymbol.value || 'S/')

// pay_method 1=Tarjeta, 2=Transferencia, 3=Otros
const payData = computed(() => {
  const defaultData = [
    [],
    [],
    [],
    [],
  ]
  payMethods.value.forEach((method) => {
    defaultData[method.value] = Array.isArray(method.data) ? method.data : []
  })
  return defaultData
})

const normalizeMethodValue = (method) => {
  if (method?.type !== undefined && method?.type !== null) return Number(method.type)
  if (method?.value !== undefined && method?.value !== null) return Number(method.value)
  if (method?.id !== undefined && method?.id !== null) return Number(method.id)
  return null
}

const normalizeMethodData = (method) => {
  if (Array.isArray(method?.data)) {
    return method.data.map((item) => ({
      title: item?.title ?? '',
      value: item?.data ?? item?.value ?? ''
    }))
  }
  return []
}

const getPayMethodsAvailables = () => {
  payMethodStore.getPayMethod()
    .then((response) => {
      if (response.code !== 200) throw response

      const methods = (response.data || [])
        .filter((method) => Number(method.status) === 1)
        .map((method) => {
          const value = normalizeMethodValue(method)
          return {
            title: method.name || `Método ${value}`,
            value,
            img: value === 2 ? transfer : (value === 3 ? cash : null),
            data: normalizeMethodData(method),
          }
        })
        .filter((method) => method.value !== null)

      payMethods.value = methods
    })
    .catch(() => {
      payMethods.value = []
      showNotify('negative', 'No se pudieron cargar los métodos de pago')
    })
}

const nextStep = () => {
  if (!validateForm()) return
  if (step.value == 3 || (step.value == 2 && payFormData.value.pay_method == 3)) {
    createPay()
    return
  }

  if (step.value == 2) {
    // disable.value = true
  }
  step.value++


}
const stepBack = () => {
  if (step.value == 1) {
    router.go(-1)
    return
  }
  step.value--
}
const getToPay = () => {
  typePay() == 'quota'
    ? getQuotaById()
    : getBookingById()
}
const getBookingById = () => {
  reserveStore.getReserveById(route.params.id || route.query.id)
    .then((response) => {
      toPay.value = response.data
      ready.value = true
    })
    .catch((response) => {
      console.log(response)
      ready.value = true
    })
}
const getQuotaById = () => {
  quotaStore.getQuotaById(route.params.id || route.query.id)
    .then((response) => {
      toPay.value = response.data
      ready.value = true
    })
    .catch((response) => {
      console.log(response)
      ready.value = true
    })
}

const selectPayMethod = (value) => {
  payFormData.value.pay_method = value
  disable.value = false
}
const onFileChange = () => {
  if (payFormData.value.reference) disable.value = false
}
const validateForm = () => {
  if (step.value == 3) {
    !payFormData.value.vaucher
      ? showNotify('negative', 'Debes subir el comprobante') : ''
    return payFormData.value.vaucher ? true : false
  }
  return true
}

const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
}

const goTo = (url) => {
  router.push(url)
}
const createPay = () => {
  const storeToUse = typePay() == 'quota'
    ? quotaStore.createQuotaPay
    : reserveStore.createReservePay



  const dataForm = dataToForm()
  loading.value = true

  storeToUse(dataForm)
    .then((response) => {
      showNotify('positive', 'Pago creado con exito')
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

const formatAllToCopy = () => {
  let dataFormatted = ''
  try {
    ; (payData.value[payFormData.value.pay_method] || []).forEach(data => {
      if (data.title != 'QR') {
        dataFormatted += (data.title != 'Titular de la cuenta' ? data.value.replaceAll(' ', '') : data.value) + ' '
      }
    });
  } catch (error) {
    console.log('Error al copiar la data')
  }
  copyData(dataFormatted.trim())
}
const formatCopy = (texto) => {
  copyData(texto.replaceAll(' ', '').trim())
}
const copyData = (texto) => {
  const element = document.getElementById('textToPaste')
  const textArea = document.createElement('textarea');
  textArea.value = texto
  textArea.style.opacity = 0;

  element.appendChild(textArea);
  textArea.select();

  try {
    const success = document.execCommand('copy');
    showNotify('positive', 'Datos copiados con exito')
  } catch (err) {
    console.error(err.name, err.message);
  }
  finally {
    element.removeChild(textArea);
  }
}
const dataToForm = () => {
  const dataForm = new FormData()
  dataForm.append('amount', toPay.value.amount)
  dataForm.append('vaucher', payFormData.value.vaucher)
  dataForm.append('reference', payFormData.value.reference)
  dataForm.append('pay_date', payFormData.value.date)
  dataForm.append('pay_method', payFormData.value.pay_method)
  if (payFormData.value.type == 1 && toPay.value.consolidated_ids) {
    toPay.value.consolidated_ids.forEach((id) => {
      dataForm.append('quota_ids[]', id)
    })
  } else {
    dataForm.append('to_pay_id', toPay.value.id)
  }
  dataForm.append('type', payFormData.value.type)
  if (
    payFormData.value.type === 1 &&
    Array.isArray(toPay.value.consolidated_ids) &&
    toPay.value.consolidated_ids.length
  ) {
    dataForm.append('consolidated_ids', JSON.stringify(toPay.value.consolidated_ids))
  }
  return { data: dataForm }
}
const availableBeforeDaysToTodayOptions = (date) => {
  return date <= moment().format('YYYY/MM/DD');
}
onMounted(() => {
  getToPay()
  getPayMethodsAvailables()
})

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

watch(step, (toStep, fromStep) => {
  transitionName.value = toStep > fromStep ? 'slide-next' : 'slide-prev'
})


</script>
<template>
  <div class=" h-full " style="overflow: hidden; position: relative;">
    <div class="h-full md:px-20 md:mx-16 " v-if="ready">
      <q-form @submit="nextStep()" class="h-full pay-form-wrapper">
        <template v-if="ready">
          <div class="pay-form-body">
            <Transition :name="transitionName">
              <div v-if="step === 1" class="pay-form-step1">
                <div class="text-center mt-0">
                  <div class="pay-form-title">PAGAR</div>
                  <p class="pay-form-subtitle">{{ paymentSubtitle }}</p>
                </div>

                <div class="pay-form-breakdown mt-3" v-if="isQuotaPayment">
                  <div class="pb-1">
                    <div class="pay-form-breakdown__detail">
                      <span>Consumo:</span>
                      <span>{{ waterConsumptionM3.toFixed(2) }} m3</span>
                    </div>
                    <div class="pay-form-breakdown__detail">
                      <span>Precio del m3:</span>
                      <span> {{ amountPrefix }} {{ waterPricePerM3.toFixed(2) }}</span>
                    </div>
                    <div class="pay-form-breakdown__row mt-2" @click="goTo(waterDetailsLink)"
                      style="text-decoration:underline">
                      <span>Agua</span>
                      <span>{{ amountPrefix }} {{ waterAmount.toFixed(2) }}</span>
                    </div>
                  </div>

                  <div class="pt-1" v-if="quotaBreakdown.length">
                    <div v-for="item in quotaBreakdown" :key="`quota-breakdown-inline-${item.quotaId}`"
                      class="py-2 mt-2" style="border-bottom: 1px solid #e5e7eb;">
                      <div class="flex justify-center w-full pb-1 ">
                        <div class="text-bold text-subtitle1">{{ item.unitLabel }}</div>
                      </div>
                      <div class="pay-form-breakdown__detail">
                        <span>% Participación:</span>
                        <span>{{ item.participation.toFixed(2) }} %</span>
                      </div>
                      <div class="pay-form-breakdown__detail mt-1">
                        <span>Monto</span>
                        <span>{{ amountPrefix }} {{ item.maintenanceAmount.toFixed(2) }}</span>
                      </div>
                    </div>
                  </div>

                  <div class="pt-4">
                    <div class="text-h6 text-bold pb-2">
                      Total:
                    </div>
                    <div class="pay-form-breakdown__detail">
                      <span>% Participación:</span>
                      <span>{{ maintenanceParticipation.toFixed(2) }} %</span>
                    </div>
                    <div class="pay-form-breakdown__detail">
                      <span>Total mantenimiento {{ toPay.month_label }}:</span>
                      <span>{{ amountPrefix }} {{ maintenanceBudget.toFixed(2) }}</span>
                    </div>
                    <div class="pay-form-breakdown__row mt-2" @click="goTo(maintenanceDetailsLink)"
                      style="text-decoration:underline">
                      <span>Mantenimiento</span>
                      <span>{{ amountPrefix }} {{ maintenanceAmount.toFixed(2) }}</span>
                    </div>
                  </div>
                </div>

                <div class="pay-form-amount-box mb-3 mt-4">
                  <span class="pay-form-amount-prefix">{{ amountPrefix }}</span>
                  <span class="pay-form-amount-value flex flex-center">{{ toPay.amount != null ?
                    Number(toPay.amount).toFixed(2) : '0.00'
                  }}</span>
                </div>

                <div class="pay-form-select-label">Seleccionar medio</div>
                <div class="pay-form-methods mt-2">
                  <button v-for="method in payMethods" :key="method.value" type="button" class="pay-form-method-card"
                    :class="{ 'pay-form-method-card--active': payFormData.pay_method === method.value }"
                    @click="selectPayMethod(method.value)">
                    <span class="pay-form-method-card__text">{{ method.title }}</span>
                  </button>
                </div>

                <q-btn class="pay-form-btn-submit" type="submit" :loading="loading" :disable="disable" unelevated
                  no-caps>
                  PAGAR
                </q-btn>
              </div>
            </Transition>
            <Transition :name="transitionName">
              <div v-if="step === 2" class="pay-form-step-wrapper">
                <div class="pay-form-step-content">
                  <div class="col-12 col-md-6 row md:px-5 px-0 mt-1">
                    <div class="w-full">
                      <div class="text-xl font-bold pl-2">
                        Resumen de pago
                      </div>
                      <div class="selectedDateBlock mt-2 px-3 w-full py-2">
                        <div class="flex justify-between items-center w-full py-1" v-if="toPay.comun_area">
                          <div class="text__amountItem">Servicio</div>
                          <div class="text__amountItem">{{ toPay.comun_area.name }}</div>
                        </div>
                        <div class="flex justify-between items-center w-full py-1" v-if="toPay.month_label">
                          <div class="text__amountItem">Mes</div>
                          <div class="text__amountItem">{{ toPay.month_label }}</div>
                        </div>
                        <div class="flex justify-between items-center w-full mt-1 pt-2"
                          style="border-top: 2px solid #8b8e9446;">
                          <div class="text__amountTotal">Total</div>
                          <div class="text__amountTotal">{{ amountPrefix }} {{ Number(toPay.amount || 0).toFixed(2) }}
                          </div>
                        </div>
                      </div>

                      <div class="rulesContainer mt-4 px-2 pt-2 pb-1">
                        <div class="px-2 rulesContainer__Title">
                          Datos para el pago
                        </div>
                        <template v-if="payFormData.pay_method !== 3">
                          <template v-if="payData[payFormData.pay_method]?.length">
                            <div v-for="(data, key) in payData[payFormData.pay_method]" :key="key"
                              class="my-2 py-1 px-3 flex items-center justify-between ruleDetailContainer">
                              <div :class="{ 'w-full': data.title == 'QR' }">
                                <div class="text-body2 mb-1 text-grey-7">{{ data.title }}</div>
                                <img style="width: 8rem;" :src="data.value" alt="" v-if="data.title == 'QR'"
                                  class="mx-auto">
                                <div v-else style="font-size: 1.05rem;" class="text-black text-bold">{{ data.value }}
                                </div>
                              </div>
                              <div v-html="iconsApp.copyIcon" class="cursor-pointer" v-if="data.title != 'QR'"
                                @click="data.title.includes('Titular') ? copyData(data.value) : formatCopy(data.value)" />
                            </div>
                            <div class="flex flex-center my-3 cursor-pointer">
                              <div v-html="iconsApp.copyIcon" />
                              <div class="ml-1 text-primary" @click="formatAllToCopy()"
                                style="font-size: 1.02rem; font-weight: medium;">Copiar datos</div>
                            </div>
                          </template>
                        </template>
                        <template v-else>
                          <div class="text-center text-black text-moneyEfectivo py-3 px-2">
                            Dirigete a nuestra oficina para finalizar tu pago en efectivo.
                          </div>
                          <div class="my-2 text-center text-grey-8 text-subtitle1 px-4 py-4 box-data">
                            Av. Alfredo Benavides 430, Miraflores 15074.
                          </div>
                          <div class="mt-4 mb-2 text-center" v-if="toPay.booking_number">
                            <div class="text-moneyEfectivo text-black">Codigo de reservacion:</div>
                            <div class="flex flex-center">
                              <div class="text-primary text-h5 mt-2 box-data pl-4 pr-3 py-3 flex items-center">
                                #00{{ toPay.booking_number }}
                              </div>
                            </div>
                          </div>
                        </template>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="buttonSection">
                  <div class="row py-4">
                    <div class="col-4 flex flex-center">
                      <q-btn outline color="grey-8" unelevated no-caps style="width: 90%; border-radius: 3rem;"
                        @click="stepBack()">
                        Volver
                      </q-btn>
                    </div>
                    <div class="col-8 flex flex-center">
                      <q-btn outline color="primary" unelevated no-caps style="width: 95%; border-radius: 3rem;"
                        type="submit" :loading="loading" :disable="disable">
                        {{ payFormData.pay_method === 3 ? 'Confirmar pago' : 'Ya hice el pago' }}
                      </q-btn>
                    </div>
                  </div>
                </div>
              </div>
            </Transition>
            <Transition :name="transitionName">
              <div v-if="step === 3" class="pay-form-step-wrapper">
                <div class="pay-form-step-content">
                  <div class="col-12 row md:px-5 px-4 mt-1">
                    <div class="w-full">
                      <div class="text-lg font-bold pl-2">
                        Pago
                      </div>
                      <div class="text-caption font-medium pl-2 text-grey-7">
                        Método seleccionado:
                        {{payMethods.find((m) => m.value === payFormData.pay_method)?.title || '-'}}
                      </div>
                      <div class="font-medium pl-2 mt-3" style="font-size:0.9rem">
                        Completa los datos del comprobante
                      </div>
                      <div class="selectedDateBlock mt-1 px-3 w-full py-2">
                        <div class="row mt-1">
                          <div class="col-12 mt-0">
                            <div class="md:pr-4">

                              <q-input color="tealedf" label="Fecha de pago" v-model="payFormData.date"
                                :rules="[val => !(!val) || 'Fecha es requerida']" dense borderless clearable
                                class="form__inputsPay mt-1">
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
                          <div class="col-12 mt-2">

                            <q-input color="tealedf" label="Referencia de pago" dense borderless clearable
                              v-model="payFormData.reference" class="form__inputsPay mt-1" :maxlength="12"
                              :rules="[val => !(!val) || 'La refrencia de pago es obligatoria']">

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
                      <div class="rulesContainer mt-2 px-3 w-full py-2">
                        <div class="text-subtitle2 text-black mb-2">
                          Vaucher de pago
                        </div>
                        <q-file v-model="payFormData.vaucher" dense borderless clearable class="form__inputsPay mt-1"
                          color="primary" @update:model-value="onFileChange">
                          <template v-slot:append>
                            <q-icon name="eva-folder-add-outline" class="cursor-pointer" />
                          </template>
                          <template v-slot:selected>
                            <div class="row items-center q-gutter-x-sm">
                              <q-icon name="eva-checkmark-circle-2-outline" color="positive" size="sm" />
                              <div>Archivo subido</div>
                            </div>
                          </template>
                        </q-file>
                      </div>
                      <div class="selectedDateBlock mt-4 px-1 w-full py-2">
                        <q-chip color="tealedf" text-color="white" size="0.8rem">
                          <div style="font-size: 0.7rem;">
                            Pendiente de validación
                          </div>
                        </q-chip>
                        <div class="text-xsImage px-2">Tu comprobante será revisado por administración</div>
                        <div class="text-xsImage text-grey-7 px-2">
                          Te notificaremos cuando el pago sea validado
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="buttonSection">
                  <div class="row py-4">
                    <div class="col-4 flex flex-center">
                      <q-btn outline color="grey-8" unelevated no-caps style="width: 90%; border-radius: 3rem;"
                        @click="stepBack()">
                        Volver
                      </q-btn>
                    </div>
                    <div class="col-8 flex flex-center">
                      <q-btn outline color="primary" unelevated no-caps style="width: 95%; border-radius: 3rem;"
                        type="submit" :loading="loading" :disable="disable">
                        Finalizar
                      </q-btn>
                    </div>
                  </div>
                </div>
              </div>
            </Transition>
          </div>

        </template>
        <div v-else class="flex flex-center  h-full">
          <q-spinner-dots color="primary" size="7rem" />
        </div>

      </q-form>
    </div>
    <div id="textToPaste" />
  </div>
</template>
<style lang="scss">
/* --- Diseño pantalla PAGAR (paso 1) --- */
.pay-form-page {
  display: flex;
  flex-direction: column;
  background: #fff;
}

.pay-form-wrapper {
  display: flex;
  flex-direction: column;
}

.pay-form-header {
  background: $primary;
  border-bottom-left-radius: 1.5rem;
  border-bottom-right-radius: 1.5rem;
  padding: 1rem 1.5rem 1.5rem;
  display: flex;
  flex-direction: column;
  position: relative;
  z-index: 2;
  min-height: 5rem;
}

.pay-form-header__top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.pay-form-header__bottom {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-top: 1rem;
}

.pay-form-header__title {
  color: white;
  font-weight: 400;
  font-size: 1.2rem;
}

.pay-form-header__logo {
  height: 4.5rem;
  object-fit: contain;
}

.pay-form-header__bell {
  position: relative;
  cursor: pointer;
}

.pay-form-header__badge {
  position: absolute;
  top: -0.25rem;
  right: -0.25rem;
  z-index: 1;
}

.pay-form-body {
  flex: 1;
  overflow: auto;
  padding: 0rem 1.5rem 1.5rem;
}

.pay-form-step-wrapper {
  height: 100%;
  overflow: hidden;
}

.pay-form-step-content {
  height: 91%;
  overflow: auto;
}

.pay-form-step1 {
  display: flex;
  flex-direction: column;
  margin: 0 auto;
}

.pay-form-back {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  width: max-content;
}

.pay-form-back__circle {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 50%;
  background: transparent;
  border: 2px solid #cba258;
  color: #cba258;
  display: flex;
  align-items: center;
  justify-content: center;
}

.pay-form-back__text {
  font-size: 1rem;
  font-weight: 400;
  color: #000;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.pay-form-title {
  font-size: 2rem;
  font-weight: 800;
  color: #1763a6;
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.02em;
}

.pay-form-subtitle {
  font-size: 1rem;
  color: #000;
  margin: 0.2rem 0 0;
  text-transform: uppercase;
  font-weight: 500;
}

.pay-form-breakdown {
  border: 1px solid #e3e6ea;
  border-radius: 1rem;
  background: #f8fafc;
  padding: 0.8rem 1rem;
}

.pay-form-breakdown__row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  font-size: 1rem;
  color: #1f2937;
  font-weight: 600;
}

.pay-form-breakdown__row+.pay-form-breakdown__row {
  margin-top: 0.6rem;
  padding-top: 0.6rem;
  border-top: 1px solid #e5e7eb;
}

.pay-form-breakdown__detail {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 0.35rem;
  color: #4b5563;
  font-size: 0.9rem;
  line-height: 1.3;
}

.pay-form-breakdown__link {
  margin-left: 0.5rem;
  color: #1763a6;
  font-weight: 700;
  text-decoration: underline;
}

.pay-form-amount-box {
  background: #eef2f5;
  border-radius: 3rem;
  padding: 0.2rem 1.5rem;
  text-align: center;
  border: none;
  display: flex;
  justify-content: center;
  align-items: baseline;
  gap: 0.2rem;
}

.pay-form-amount-prefix {
  font-size: 2.5rem;
  font-weight: 800;
  color: #1763a6;
}

.pay-form-amount-value {
  font-size: 2.8rem;
  font-weight: 800;
  color: #1763a6;
}

.pay-form-select-label {
  font-size: 1.2rem;
  color: #000;
  font-weight: 700;
  margin: 0.5rem 0 0.5rem;
  text-align: center;
}

.pay-form-methods {
  display: flex;
  gap: 0.8rem;
  justify-content: center;
}

.pay-form-method-card {
  background: #fff;
  border: 2px solid #eef2f5;
  border-radius: 1.5rem;
  padding: 1.5rem 0.5rem;
  cursor: pointer;
  transition: box-shadow 0.2s, border-color 0.2s;
  text-align: center;
  font-family: inherit;
  font-size: 0.8rem;
  color: #000;
  font-weight: 600;
  line-height: 1.2;
  box-shadow: none;
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;

  &:hover {
    border-color: #cba258;
  }

  &--active {
    border-color: #cba258;
    background: #fafafa;
  }
}

.pay-form-method-card__text {
  display: block;
}

.pay-form-btn-submit {
  margin-top: 1rem;
  background: #cba258 !important;
  color: white !important;
  font-weight: 700 !important;
  font-size: 1.1rem !important;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-radius: 2rem;
  padding: 0.8rem 1.5rem;
  width: 80%;
  margin-left: auto;
  margin-right: auto;
  display: block;
}

.pay-form-btn-summary {
  border-radius: 0.5rem !important;
}

.buttonSection {
  height: 9%;
}

.rulesContainer {
  border: 1px solid #d6dbe2;
  border-radius: 1rem;
  background: #fff;
}

.rulesContainer__Title {
  font-size: 1rem;
  font-weight: 700;
  color: #1f2937;
}

.ruleDetailContainer {
  border: 1px solid #e6e8eb;
  border-radius: 0.8rem;
}

.selectedDateBlock {
  border: 1px solid #d6dbe2;
  border-radius: 1rem;
  background: #fff;
}

.text__amountItem {
  font-size: 0.95rem;
  color: #1f2937;
  font-weight: 500;
}

.text__amountTotal {
  font-size: 1.05rem;
  color: #1f2937;
  font-weight: 700;
}

/* --- Estilos existentes (pasos 2 y 3) --- */
.summarySection {
  border: 2px solid lightgray;
  border-top-left-radius: 1rem;
  border-top-right-radius: 1rem;
}

.q-date__today {
  background: #0351824d;
  color: $primary;
}

.box-data {
  background: #03518221;
  border-radius: 1rem;
  width: auto;
  font-weight: 600;
}

.text-moneyEfectivo {
  font-weight: bold;
  font-size: 1rem;
}

.dataPayCard {
  background: white;
  border-radius: 1.2rem;
}

.form__inputsPay {
  & .q-field__inner {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
}

@media (max-width: 780px) {
  .pay-form-methods {
    grid-template-columns: 1fr;
  }

  .pay-form-method-card {
    font-size: 0.85rem;
    padding: 1rem;
  }

  .form__inputsPay .q-field__inner {
    padding: 0.1rem 1rem;
  }

  .dataPayCard {
    border-radius: 0;
    border-bottom-left-radius: 1.2rem;
    border-bottom-right-radius: 1.2rem;
  }

  .summarySection {
    border: 1px solid lightgray;
    border-top-left-radius: 1rem;
    border-top-right-radius: 1rem;
    box-shadow: 0px 0.2rem 1rem 0px rgb(155 155 155 / 53%);
  }
}
</style>