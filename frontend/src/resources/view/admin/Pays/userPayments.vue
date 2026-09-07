<script setup>
import { onMounted, ref } from 'vue'
import { Notify } from 'quasar'
import { useRoute, useRouter } from 'vue-router'
import { usePayStore } from '@/services/store/pay.store'
import { useUserStore } from '@/services/store/users.store'
import moment from 'moment'

const route = useRoute()
const router = useRouter()
const payStore = usePayStore()
const userStore = useUserStore()

const loading = ref(false)
const payments = ref([])
const userName = ref('')

const fetchData = async () => {
  loading.value = true
  try {
    const userRes = await userStore.getUserById(route.params.id)
    if (userRes?.code === 200) {
      userName.value = userRes.data.name
    }
    const payRes = await payStore.getPaysByUser({ user_id: route.params.id, per_page: 999 })
    if (payRes?.code === 200) {
      payments.value = payRes.data?.data || []
    }
  } catch (e) {
    Notify.create({ color: 'negative', message: 'Error al cargar datos' })
  } finally {
    loading.value = false
  }
}

const formatMoney = (v) => `S/. ${(Number(v) || 0).toFixed(2)}`

onMounted(fetchData)
</script>
<template>
  <div class="md:px-20 px-2 pb-10 h-full" style="overflow: auto;">
    <div class="text-center text-black text-h5 text-bold my-2">
      Pagos de {{ userName }}
    </div>
    <div v-if="loading" class="flex justify-center py-10">
      <q-spinner-dots color="primary" size="3rem" />
    </div>
    <div v-else>
      <div v-if="payments.length === 0" class="text-center text-grey-6 q-py-xl">
        <q-icon name="eva-credit-card-outline" size="4rem" color="grey-4" />
        <div class="text-h6 q-mt-sm">No hay pagos registrados</div>
      </div>
      <q-table v-else flat bordered :rows="payments" :columns="[
        { name: 'id', label: '#', field: 'id', align: 'left' },
        { name: 'amount', label: 'Monto', field: 'amount', format: v => formatMoney(v), align: 'right' },
        { name: 'pay_method_name', label: 'Método', field: 'pay_method_name', align: 'center' },
        { name: 'voucher_number', label: 'Voucher', field: 'voucher_number', align: 'center' },
        { name: 'status_name', label: 'Estado', field: 'status_name', align: 'center' },
        { name: 'created_at', label: 'Fecha', field: 'created_at', format: v => v ? moment(v).format('DD/MM/YYYY') : '—', align: 'center' },
      ]" row-key="id" hide-pagination virtual-scroll />

    </div>
  </div>
</template>
