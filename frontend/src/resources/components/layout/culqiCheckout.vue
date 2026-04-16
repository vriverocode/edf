<template>
  <q-btn 
    label="Pagar y Reservar Ahora" 
    color="primary" 
    no-caps=""
    :loading="loading"
    @click="abrirCheckout" 
  />
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { usePayStore } from '@/services/store/pay.store';
import { Notify } from 'quasar';
import { useAuthStore } from '@/services/store/auth.services';
import { storeToRefs } from 'pinia';

const props = defineProps({
  externalData: { type: Object, required: true }
});
const { user } = storeToRefs(useAuthStore())
const emit = defineEmits(['success']);
const payStore = usePayStore();
const loading = ref(false);

const abrirCheckout = () => {
  if (!window.Culqi) return;

  window.Culqi.settings({
    title: 'Reserva de Área',
    currency: 'PEN',
    description: 'Pago de uso de áreas comunes',
    amount: props.externalData.amount * 100
  });
  window.Culqi.open();
};

onMounted(() => {
  window.culqi = async () => {
    if (window.Culqi.token) {
      const token = window.Culqi.token.id;
      await procesarTodo(token);
    } else {
      Notify.create({ message: window.Culqi.error.user_message, color: 'negative' });
    }
  };
});

const procesarTodo = async (token) => {
  loading.value = true;
  try {
    // Combinamos el token de Culqi con los datos del formulario
    const payload = {
      token: token,
      email: user.value.email,
      ...props.externalData // amount, comun_area_id, date, etc.
    };

    const res = await payStore.createCulqiPay(payload);
    emit('success', res.data);
  } catch (err) {
    Notify.create({ message: err, color: 'negative' });
  } finally {
    loading.value = false;
  }
};
</script>