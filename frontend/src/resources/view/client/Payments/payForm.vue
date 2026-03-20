<script setup>
import { ref, inject, onMounted, computed } from 'vue';
import transfer from '@/assets/img/util/transfer.webp'
import yape from '@/assets/img/util/yape.webp'
import cash from '@/assets/img/util/cash.webp'
import { useRoute, useRouter } from 'vue-router';
import { useReserveStore } from '@/services/store/reserve.store'
import { useQuotaStore } from '@/services/store/quota.store'
import { useNotificationsStore } from '@/services/store/notifications.store'
import iconsApp from '@/assets/icons/index'
import logo from '@/assets/img/logo/logo-white.webp'
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

const route = useRoute()
const router = useRouter()
const reserveStore = useReserveStore()
const quotaStore = useQuotaStore()
const emitter = inject('emitter', null)
const openLogout = () => {
  if (emitter) emitter.emit('logoutModal')
}

const ready = ref(false)
const step = ref(1)
const loading = ref(false)
const disable = ref(true)
const materialIcons = inject('materialIcons')
const toPay = ref({})
const transitionName = ref('horizontal');
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

const payMethods = [
  { title: 'Tarjeta de crédito o débito', value: 1, img: null },
  { title: 'Transferencia Bancaria', value: 2, img: transfer },
  { title: 'Otros', value: 3, img: cash }
]

const paymentSubtitle = computed(() => {
  if (!toPay.value || !Object.keys(toPay.value).length) return ''
  return typePay() === 'quota' && toPay.value.month_label
    ? `MANTENIMIENTO ${String(toPay.value.month_label).toUpperCase()}`
    : toPay.value.comun_area?.name
      ? `RESERVA ${String(toPay.value.comun_area.name).toUpperCase()}`
      : 'PAGO'
})

// pay_method 1=Tarjeta, 2=Transferencia, 3=Otros
const payData = [
  [],
  [], // 1 Tarjeta: sin datos para copiar (pasarela u otro flujo)
  [
    { title: 'N° de cuenta', value: '0000000000000' },
    { title: 'Banco', value: 'BCP' },
    { title: 'Titular de la cuenta', value: 'Juan Perez' },
  ],
  [
    { title: 'Dirección de entrega', value: 'Lobby edificio central, Horario de 8:00 - 15:00' },
  ],
]

const nextStep = () => {
  if (step.value == 3 || (step.value == 2 && payFormData.value.pay_method == 3)) {
    createPay()
    return
  }

  if (step.value == 2) {
    disable.value = true
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


const showNotify = (type, text) => {
  Notify.create({
    color: type,
    message: text,
    timeout: 2000
  })
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
      showNotify('positive', 'Error al crear el pago')

    })
}

const formatAllToCopy = () => {
  let dataFormatted = ''
  try {
    payData[payFormData.value.pay_method].forEach(data => {
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
  dataForm.append('to_pay_id', toPay.value.id)
  dataForm.append('type', payFormData.value.type)
  console.log(payFormData.value)
  return { data: dataForm }
}
onMounted(() => {
  getToPay()
})


</script>
<template>
  <div class="pay-form-page h-full md:px-28">
    <q-form @submit="nextStep()" class="h-full">
      <template v-if="ready">
        <div class="pay-form-body">
          <Transition :name="transitionName">
            <div v-if="step === 1" class="pay-form-step1">
              <div class="pay-form-back" @click="stepBack()">
                <div class="pay-form-back__circle">
                  <q-icon name="eva-arrow-back-outline" size="1.8rem" />
                </div>
                <span class="pay-form-back__text">REGRESAR</span>
              </div>

              <div class="text-center mt-4">
                <h1 class="pay-form-title">PAGAR</h1>
                <p class="pay-form-subtitle">{{ paymentSubtitle }}</p>
              </div>

              <div class="pay-form-amount-box">
                <span class="pay-form-amount-prefix">S/</span>
                <span class="pay-form-amount-value">{{ toPay.amount != null ? Number(toPay.amount).toFixed(2) : '0.00'
                }}</span>
              </div>

              <p class="pay-form-select-label">Seleccionar medio</p>
              <div class="pay-form-methods">
                <button v-for="method in payMethods" :key="method.value" type="button" class="pay-form-method-card"
                  :class="{ 'pay-form-method-card--active': payFormData.pay_method === method.value }"
                  @click="selectPayMethod(method.value)">
                  <span class="pay-form-method-card__text">{{ method.title }}</span>
                </button>
              </div>

              <q-btn class="pay-form-btn-submit" type="submit" :loading="loading" :disable="disable" unelevated no-caps>
                PAGAR
              </q-btn>
            </div>
          </Transition>
          <Transition :name="transitionName">
            <div v-if="step === 2" class="pay-form-step2 h-full md:pt-5" style="overflow: auto;">
              <div class="pay-form-back mb-4" @click="stepBack()">
                <div class="pay-form-back__circle">
                  <q-icon :name="materialIcons.outlinedArrowBack" color="white" size="1.2rem" />
                </div>
                <span class="pay-form-back__text">REGRESAR</span>
              </div>
              <div v-if="payFormData.pay_method !== 3">
                <div class="dataPayCard pt-6 pb-3 px-3 md:px-8 md:py-8" style="transform: translateY(-0.4rem);">
                  <div class="pb-5 text-h6 text-bold text-black">
                    Paga tu reserva
                    <div class="text-grey-7 mt-1" style="font-size: 0.85rem;line-height: 1.3;">
                      <template v-if="payFormData.pay_method === 1">Completa el formulario en el siguiente paso para
                        registrar tu pago con tarjeta.</template>
                      <template v-else>Asegúrate de pagar correctamente, utiliza los datos que te aparecen
                        acá.</template>
                    </div>
                  </div>
                  <template v-if="payData[payFormData.pay_method]?.length">
                    <div v-for="(data, key) in payData[payFormData.pay_method]" :key="key"
                      class="mb-5 flex items-center justify-between">
                      <div :class="{ 'w-full': data.title == 'QR' }">
                        <div class="text-body2 mb-1 text-grey-7">{{ data.title }}</div>
                        <img style="width: 8rem;" :src="data.value" alt="" v-if="data.title == 'QR'" class="mx-auto">
                        <div v-else style="font-size: 1.05rem;" class="text-black text-bold">{{ data.value }}</div>
                      </div>
                      <div v-html="iconsApp.copyIcon" class="cursor-pointer" v-if="data.title != 'QR'"
                        @click="data.title.includes('Titular') ? copyData(data.value) : formatCopy(data.value)" />
                    </div>
                    <div class="flex flex-center mt-6 cursor-pointer">
                      <div v-html="iconsApp.copyIcon" />
                      <div class="ml-1 text-primary" @click="formatAllToCopy()"
                        style="font-size: 1.02rem; font-weight: medium;">Copiar datos</div>
                    </div>
                  </template>
                </div>
              </div>
              <div v-else>
                <div class="dataPayCard pt-6 pb-3 px-3 md:px-8 md:py-8" style="transform: translateY(-0.4rem);">
                  <div class="pb-7 text-h6 text-bold text-black">
                    Paga tu reserva
                    <div class=" text-grey-7 mt-1" style="font-size: 0.85rem;line-height: 1.3;">
                      Dirigete a nuestra oficina, para finalizar tu reserva
                    </div>
                  </div>
                  <div class="text-center text-black text-moneyEfectivo">
                    Dirigete a la siguiente ubicación de nuestra oficina para realizar el abono en efectivo:
                  </div>
                  <div class="md:px-8">
                    <div class="my-4 text-center text-grey-8 text-subtitle1 px-4 py-4 box-data">
                      Av. Alfredo Benavides 430, Miraflores 15074.
                    </div>
                  </div>
                  <div class="mt-7 mb-4 text-center" v-if="toPay.booking_number">
                    <div class="text-moneyEfectivo text-black">El codigo de tu reservación es:</div>
                    <div class="flex flex-center">
                      <div class="text-primary text-h5  mt-4 box-data pl-4 pr-3 py-3 flex items-center">
                        #00{{ toPay.booking_number }}
                        <div class="ml-2">
                          <div v-html="iconsApp.copyIcon" />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </Transition>
          <Transition :name="transitionName">
            <div v-if="step === 3" class="pay-form-step3 h-full md:pt-5">
              <div class="pay-form-back mb-4" @click="stepBack()">
                <div class="pay-form-back__circle">
                  <q-icon :name="materialIcons.outlinedArrowBack" color="white" size="1.2rem" />
                </div>
                <span class="pay-form-back__text">REGRESAR</span>
              </div>
              <div class="dataPayCard p-5 pb-3 px-3 md:px-8 md:py-8" style="transform: translateY(-0.4rem);">
                <div class="pb-7 text-h6 text-bold text-black">
                  Confirma tu pago
                  <div class=" text-grey-7 mt-1" style="font-size: 0.85rem;line-height: 1.3;">
                    Completa el formulario
                  </div>
                </div>
                <div class=" row mt-1 px-1 md:px-12">
                  <div class="col-12 mt-0">
                    <div class="text-subtitle2 text-black">
                      Fecha de pago:
                    </div>
                    <div class="pr-2 md:pr-4">
                      <q-input v-model="payFormData.date" :rules="[val => !(!val) || 'Fecha es requerida']" dense
                        borderless clearable class="form__inputsPay mt-1" color="primary" accept=".jpg, image/*">
                        <template v-slot:append>
                          <q-icon name="eva-calendar-outline" class="cursor-pointer">
                            <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                              <q-date mask="DD-MM-YYYY" v-model="payFormData.date"
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
                  <div class="col-12 mt-2 ">
                    <div class="text-subtitle2 text-black ">
                      Referencia de pago
                    </div>
                    <div class="pr-2 md:pr-4">
                      <q-input dense borderless clearable v-model="payFormData.reference" class="form__inputsPay mt-1"
                        :maxlength="12" color="primary"
                        :rules="[val => !(!val) || 'La refrencia de pago es obligatoria']" />
                    </div>
                  </div>
                  <div class="col-12 mt-2 mb-4">
                    <div class="text-subtitle2 text-black ">
                      Vaucher de pago
                    </div>
                    <div class="pr-2 md:pr-4">
                      <q-file v-model="payFormData.vaucher" dense borderless clearable class="form__inputsPay mt-1"
                        color="primary" @update:model-value="onFileChange">
                        <template v-slot:append>
                          <q-icon name="eva-folder-add-outline" class="cursor-pointer">
                          </q-icon>
                        </template>
                        <template v-slot:selected>
                          <div class="row items-center q-gutter-x-sm">
                            <q-icon name="eva-checkmark-circle-2-outline" color="positive" size="sm" />
                            <div>Archivo subido</div>
                          </div>
                        </template>
                      </q-file>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </Transition>
        </div>

        <div v-if="step === 2 || step === 3" class="pay-form-summary md:px-20 md:mx-20" style="height: 22%;">
          <div class="py-3 summarySection">
            <div class="mb-3 md:px-16 px-5">
              <div class="flex justify-between items-center py-2" style="border-bottom: 1px solid lightgrey; "
                v-if="toPay.comun_area">
                <div class="text-subtitle2 text-grey-8">Reserva</div>
                <div class="text-subtitle1 text-bold text-black">{{ toPay.comun_area.name }}</div>
              </div>
              <div class="flex justify-between items-center py-2" style="border-bottom: 1px solid lightgrey; "
                v-if="toPay.month">
                <div class="text-subtitle2 text-grey-8">Mes</div>
                <div class="text-subtitle1 text-bold text-black">{{ toPay.month_label }}</div>
              </div>
              <div class="flex justify-between items-center py-2" style="border-bottom: 1px solid lightgrey;">
                <div class="text-subtitle2 text-grey-8">Total</div>
                <div class="text-subtitle1 text-bold text-black">S/. {{ toPay.amount }}</div>
              </div>
            </div>
            <div class="flex flex-center w-full">
              <q-btn color="secondary" class="pay-form-btn-summary" style="width: 90%; border-radius: 0.5rem;"
                type="submit" :loading="loading" :disable="disable" unelevated no-caps>
                {{ step === 3 ? 'Finalizar' : payFormData.pay_method === 3 ? 'Confirmar pago' : 'Ya hice el pago' }}
              </q-btn>
            </div>
          </div>
        </div>

        <footer v-if="step === 1" class="pay-form-footer">
          <router-link to="/client/department/options" class="pay-form-footer__item">
            <q-icon name="eva-home" size="2rem" class="pay-form-footer__icon" />
            <span>Home</span>
          </router-link>
          <router-link to="/client/pays/list" class="pay-form-footer__item">
            <q-icon name="eva-trending-up" size="2rem" class="pay-form-footer__icon" />
            <span>Finanzas</span>
          </router-link>
          <a href="#" class="pay-form-footer__item pay-form-footer__item--link" @click.prevent="openLogout">
            <q-icon name="eva-log-out" size="2rem" class="pay-form-footer__icon" />
            <span>Salir</span>
          </a>
        </footer>
      </template>
      <div v-else class="flex flex-center  h-full">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

    </q-form>
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
  padding: 1rem 1.5rem 1.5rem;
}

.pay-form-step1 {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
  max-width: 28rem;
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

.pay-form-amount-box {
  background: #eef2f5;
  border-radius: 3rem;
  padding: 1rem 1.5rem;
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
  font-size: 3.5rem;
  font-weight: 800;
  color: #1763a6;
  line-height: 1;
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

.pay-form-footer {
  background: #f4f5f7;
  padding: 1rem;
  display: flex;
  justify-content: space-around;
  align-items: center;
}

.pay-form-footer__item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 0.25rem;
  color: #1763a6;
  font-size: 0.9rem;
  font-weight: 600;
  text-decoration: none;
  cursor: pointer;

  &.pay-form-footer__item--link {
    background: none;
    border: none;
  }
}

.pay-form-footer__icon {
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 0;
  color: #1763a6;
}

.pay-form-btn-summary {
  border-radius: 0.5rem !important;
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