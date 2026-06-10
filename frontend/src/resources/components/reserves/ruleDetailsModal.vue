<script setup>
import { defineProps, defineEmits } from 'vue';

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  rule: {
    type: Object,
    default: () => ({})
  }
});

const emit = defineEmits(['update:modelValue']);

const close = () => {
  emit('update:modelValue', false);
};
</script>

<template>
  <q-dialog :model-value="modelValue" @update:model-value="close" position="bottom">
    <q-card style="border-radius: 1.5rem 1.5rem 0 0; width: 100%; max-width: 500px;">
      <q-card-section class="row items-center justify-between pb-2">
        <div class="text-h6 font-bold text-primary">Detalle de la Norma</div>
        <q-btn icon="eva-close-outline" flat round dense v-close-popup />
      </q-card-section>

      <q-card-section class="pt-0" v-if="rule">
        <div class="flex items-center mb-3">
          <q-chip v-if="rule.code" color="grey-3" text-color="grey-9" size="sm" class="font-bold mr-2">
            Art. {{ rule.code }}
          </q-chip>
          <q-chip 
            :color="rule.severity == 1 ? 'tealedf' : (rule.severity == 2 ? 'warning' : 'negative')" 
            text-color="white" 
            size="sm" 
            class="font-bold"
          >
            {{ rule.severity == 1 ? 'Leve' : (rule.severity == 2 ? 'Grave' : 'Muy grave') }}
          </q-chip>
        </div>

        <div class="text-subtitle1 font-bold text-black mb-1">{{ rule.title }}</div>
        <div class="text-body2 text-grey-8 mb-4" style="white-space: pre-line;">
          {{ rule.description || 'Sin descripción detallada.' }}
        </div>

        <div v-if="rule.suggest_amount" class="bg-red-1 q-pa-md rounded-borders mb-2" style="border: 1px solid #ffcdd2;">
          <div class="row items-center text-negative font-bold mb-1">
            <q-icon name="eva-alert-triangle-outline" size="1.2rem" class="mr-1" />
            Sanción por incumplimiento
          </div>
          <div class="text-body2 text-red-9">
            Esta infracción conlleva una multa o amonestación monetaria de:
          </div>
          <div class="text-h6 font-bold text-negative mt-1">
            S/ {{ Number(rule.suggest_amount).toFixed(2) }}
          </div>
        </div>
      </q-card-section>

      <q-card-actions align="center" class="pb-4 pt-2">
        <q-btn outline color="primary" label="Entendido" rounded style="width: 80%" v-close-popup />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>