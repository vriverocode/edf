<script setup>
import { ref, watch, onMounted } from 'vue'
import ApiService from '@/services/axios'

const emit = defineEmits(['update:modelValue'])

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => []
  },
  userId: {
    type: [Number, String],
    default: null
  }
})

const areas = ref([])
const selectedAreas = ref([...props.modelValue])
const loading = ref(false)

const loadAreas = async () => {
  loading.value = true
  try {
    const { data } = await ApiService.get('/api/comun-area/all')
    if (data.code === 200) {
      areas.value = data.data || []
    }
    if (props.userId) {
      const { data: userData } = await ApiService.get('/api/users/byId/' + props.userId)
      const current = userData?.data?.available_comun_areas || []
      selectedAreas.value = current.map((a) => a.id)
      emit('update:modelValue', [...selectedAreas.value])
    }
  } catch {
    areas.value = []
  } finally {
    loading.value = false
  }
}

const toggleArea = (areaId) => {
  const index = selectedAreas.value.indexOf(areaId)
  if (index === -1) {
    selectedAreas.value.push(areaId)
  } else {
    selectedAreas.value.splice(index, 1)
  }
  emit('update:modelValue', [...selectedAreas.value])
}

const isSelected = (areaId) => selectedAreas.value.includes(areaId)

watch(
  () => props.modelValue,
  (val) => {
    selectedAreas.value = [...val]
  }
)

onMounted(() => {
  loadAreas()
})
</script>

<template>
  <div class="row w-full">
    <div class="col-12 px-2 md:px-12">
      <div class="text-subtitle1 text-bold text-primary mb-3">
        Selecciona las áreas comunes que podrá reservar
      </div>
      <div class="text-body2 text-grey-7 mb-4">
        Si no seleccionas ninguna área, el usuario podrá reservar todas las disponibles.
      </div>

      <div v-if="loading" class="flex justify-center py-6">
        <q-spinner-dots color="primary" size="3rem" />
      </div>

      <div v-else-if="areas.length === 0" class="text-center py-6">
        <q-icon name="eva-alert-circle-outline" size="3rem" color="grey-5" />
        <div class="text-grey-6 q-mt-sm">No hay áreas comunes registradas</div>
      </div>

      <div v-else class="q-gutter-sm">
        <q-item
          v-for="area in areas"
          :key="area.id"
          clickable
          v-ripple
          :active="isSelected(area.id)"
          active-class="bg-blue-1 text-primary"
          class="rounded-borders q-mb-sm"
          style="border: 1px solid #e0e0e0;"
          @click="toggleArea(area.id)"
        >
          <q-item-section side>
            <q-checkbox
              :model-value="isSelected(area.id)"
              @click.stop="toggleArea(area.id)"
              color="primary"
            />
          </q-item-section>
          <q-item-section>
            <q-item-label class="text-bold">{{ area.name }}</q-item-label>
            <q-item-label caption class="moreLineHeigth">
              {{ area.type_label || 'Tipo no definido' }}
              <span v-if="area.capacity"> · Capacidad: {{ area.capacity }}</span>
              <span v-if="area.price > 0"> · S/. {{ area.price }}</span>
              <span v-else-if="area.warranty_price > 0"> · Garantía: S/. {{ area.warranty_price }}</span>
            </q-item-label>
          </q-item-section>
        </q-item>
      </div>
    </div>
  </div>
</template>

<style lang="scss">
.moreLineHeigth {
  line-height: 1.5rem !important;
}
</style>
