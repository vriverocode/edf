<script setup>
import { ref, watch } from 'vue';
import { useApartmentStore } from '@/services/store/apartment.store';
import { useUserStore } from '@/services/store/users.store';
import { Notify } from 'quasar';

const props = defineProps({
    userId: { type: [String, Number], required: true }
});

const emit = defineEmits(['success']);

const apartmentStore = useApartmentStore();
const userStore = useUserStore();

const isOpen = ref(false);
const loading = ref(false);
const availableProperties = ref([]);

const unitTypes = [
    { label: 'Departamento', value: 1 },
    { label: 'Estacionamiento', value: 2 },
    { label: 'Depósito', value: 3 }
];

const formData = ref({
    type: null,
    apartment: { id: 0, number: 'Selecciona una unidad' }
});

// Filtro dinámico al cambiar el tipo
watch(() => formData.value.type, (newType) => {
    formData.value.apartment = { id: 0, number: 'Selecciona una unidad' };
    if (newType) {
        apartmentStore.getApartmentsByFind('available', newType.value)
            .then(res => {
                availableProperties.value = [
                    { id: 0, number: 'Selecciona una unidad' },
                    ...res.data
                ];
            });
    }
});

const handleAssign = () => {
    if (!formData.value.apartment.id) {
        Notify.create({ color: 'negative', message: 'Selecciona una unidad válida' });
        return;
    }

    loading.value = true;
    const data = {
        user: props.userId,
        idApartament: formData.value.apartment.id
    };

    userStore.assingApartment(data)
        .then(() => {
            Notify.create({ color: 'positive', message: 'Unidad asignada con éxito' });
            emit('success');
            closeModal();
        })
        .catch(() => {
            Notify.create({ color: 'negative', message: 'No se pudo realizar la asignación' });
        })
        .finally(() => {
            loading.value = false;
        });
};

const openModal = () => { isOpen.value = true; };
const closeModal = () => {
    formData.value = { type: null, apartment: { id: 0, number: 'Selecciona una unidad' } };
    isOpen.value = false;
};

defineExpose({ openModal });
</script>

<template>
    <q-dialog v-model="isOpen" position="bottom">
        <q-card style="width: 500px; border-radius: 1.5rem 1.5rem 0 0;" class="q-pa-md">
            <q-card-section class="row items-center q-pb-none">
                <div class="text-h6 text-bold text-black">Asignar nueva propiedad</div>
                <q-space />
                <q-btn icon="eva-close-outline" flat round dense @click="closeModal" />
            </q-card-section>

            <q-card-section>
                <div class="text-subtitle2 text-black q-mb-xs">Tipo de Mobiliario</div>
                <q-select dense borderless v-model="formData.type" :options="unitTypes"
                    class="form__inputsRs q-mb-md" />

                <div class="text-subtitle2 text-black q-mb-xs">Unidades Disponibles</div>
                <q-select dense borderless v-model="formData.apartment" :options="availableProperties"
                    option-label="number" :disable="!formData.type" class="form__inputsRs">
                    <template v-slot:option="scope">
                        <q-item v-bind="scope.itemProps">
                            <div class="w-full">
                                <div class="flex items-center justify-between w-full">
                                    <div class="text-subtitle1" style="font-weight: 500;">
                                        {{ scope.opt.id != 0 ? '#' : '' }} {{ scope.opt.number }}
                                    </div>
                                    <div v-if="scope.opt.id != 0" class="text-positive text-subtitle2 pl-2">
                                        Disponible
                                    </div>
                                </div>
                                <div class="text-caption text-grey-6" v-if="scope.opt.id != 0">
                                    {{ scope.opt.area }} mt²
                                </div>
                            </div>
                        </q-item>
                    </template>
                </q-select>
            </q-card-section>

            <q-card-actions align="center" class="q-pb-lg">
                <q-btn color="primary" unelevated class="full-width" style="border-radius: 0.5rem;" :loading="loading"
                    @click="handleAssign">
                    <div class="py-1">Confirmar Asignación</div>
                </q-btn>
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>

<style lang="scss">
// Estilo exacto de tu archivo assingApartment.vue
.form__inputsRs {
    & .q-field__inner {
        box-shadow: 0px 3px 5px 0px rgb(218, 218, 218);
        background: white;
        border-radius: 0.5rem;
        padding: 0 1rem;
        height: 45px;
    }
}
</style>