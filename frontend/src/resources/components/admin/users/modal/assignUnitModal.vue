<script setup>
import { ref, watch } from 'vue';
import { useApartmentStore } from '@/services/store/apartment.store';
import { useUserStore } from '@/services/store/users.store';
import { Notify } from 'quasar';

const props = defineProps({
    userId: { type: [String, Number], required: true },
    userName: { type: String, default: '' }
});

const emit = defineEmits(['success']);

const apartmentStore = useApartmentStore();
const userStore = useUserStore();

const isOpen = ref(false);
const loading = ref(false);
const loadingUnits = ref(false);
const confirmOpen = ref(false);
const removeResidents = ref(true);
const availableProperties = ref([]);

const unitTypes = [
    { label: 'Departamento', value: 1 },
    { label: 'Estacionamiento', value: 2 },
    { label: 'Depósito', value: 3 }
];

const formData = ref({
    type: null,
    apartment: { id: 0, number: 'Selecciona una unidad', residents_count: 0 }
});

const hasResidents = (unit) => Number(unit?.residents_count || 0) > 0;

const loadUnits = (type) => {
    loadingUnits.value = true;
    apartmentStore.getApartmentsByFind('all', type.value)
        .then(res => {
            availableProperties.value = [
                { id: 0, number: 'Selecciona una unidad', residents_count: 0 },
                ...res.data.filter(unit => Number(unit.user_id) !== Number(props.userId))
            ];
        })
        .catch(() => {
            availableProperties.value = [
                { id: 0, number: 'Selecciona una unidad', residents_count: 0 }
            ];
        })
        .finally(() => {
            loadingUnits.value = false;
        });
};

// Filtro dinámico al cambiar el tipo
watch(() => formData.value.type, (newType) => {
    formData.value.apartment = { id: 0, number: 'Selecciona una unidad', residents_count: 0 };
    if (newType) {
        loadUnits(newType);
    }
});

watch(() => formData.value.apartment, (unit) => {
    removeResidents.value = hasResidents(unit);
});

const handleAssign = () => {
    if (!formData.value.apartment.id) {
        Notify.create({ color: 'negative', message: 'Selecciona una unidad válida' });
        return;
    }

    const unit = formData.value.apartment;
    if (unit.user_id || hasResidents(unit)) {
        removeResidents.value = hasResidents(unit);
        confirmOpen.value = true;
        return;
    }

    assignUnit(false);
};

const assignUnit = (shouldRemoveResidents) => {
    const unit = formData.value.apartment;
    loading.value = true;

    const data = {
        user: props.userId,
        idApartament: unit.id,
        remove_residents: shouldRemoveResidents
    };

    userStore.assingApartment(data)
        .then(() => {
            Notify.create({ color: 'positive', message: 'Unidad asignada con éxito' });
            emit('success');
            closeModal();
        })
        .catch((error) => {
            Notify.create({
                color: 'negative',
                message: error || 'No se pudo realizar la asignación'
            });
        })
        .finally(() => {
            loading.value = false;
        });
};

const confirmAssignment = () => {
    const unit = formData.value.apartment;
    assignUnit(hasResidents(unit) ? removeResidents.value : false);
};

const openModal = () => {
    confirmOpen.value = false;
    isOpen.value = true;
};

const closeModal = () => {
    formData.value = { type: null, apartment: { id: 0, number: 'Selecciona una unidad', residents_count: 0 } };
    availableProperties.value = [];
    confirmOpen.value = false;
    removeResidents.value = true;
    isOpen.value = false;
};

defineExpose({ openModal });
</script>

<template>
    <q-dialog v-model="isOpen" position="bottom" @hide="closeModal">
        <q-card style="width: 500px; border-radius: 1.5rem 1.5rem 0 0;" class="q-pa-md">
            <template v-if="!confirmOpen">
                <q-card-section class="row items-center q-pb-none">
                    <div class="text-h6 text-bold text-black">Asignar nueva propiedad</div>
                    <q-space />
                    <q-btn icon="eva-close-outline" flat round dense @click="closeModal" />
                </q-card-section>

                <q-card-section>
                    <div class="text-subtitle2 text-black q-mb-xs">Tipo de Mobiliario</div>
                    <q-select dense borderless v-model="formData.type" :options="unitTypes"
                        class="form__inputsRs q-mb-md" />

                    <div class="text-subtitle2 text-black q-mb-xs">Unidades</div>
                    <q-select dense borderless v-model="formData.apartment" :options="availableProperties"
                        option-label="number" :disable="!formData.type" :loading="loadingUnits"
                        class="form__inputsRs">
                        <template v-slot:option="scope">
                            <q-item v-bind="scope.itemProps">
                                <div class="w-full">
                                    <div class="flex items-center justify-between w-full">
                                        <div class="text-subtitle1" style="font-weight: 500;">
                                            {{ scope.opt.id != 0 ? '#' : '' }} {{ scope.opt.number }}
                                        </div>
                                        <div v-if="scope.opt.id != 0"
                                            :class="scope.opt.user_id ? 'text-orange-8' : 'text-positive'"
                                            class="text-subtitle2 pl-2">
                                            {{ scope.opt.user_id ? 'Asignada' : 'Disponible' }}
                                        </div>
                                    </div>
                                    <div class="text-caption text-grey-6" v-if="scope.opt.id != 0">
                                        {{ scope.opt.area }} mt²
                                    </div>
                                    <div class="text-caption text-orange-8" v-if="scope.opt.user_id">
                                        Dueño actual: {{ scope.opt.owner?.name || 'Sin nombre' }}
                                    </div>
                                    <div class="text-caption text-blue-8" v-if="hasResidents(scope.opt)">
                                        {{ scope.opt.residents_count }} inquilino(s)/familiar(es) asociado(s)
                                    </div>
                                </div>
                            </q-item>
                        </template>
                    </q-select>
                </q-card-section>

                <q-card-actions align="center" class="q-pb-lg">
                    <q-btn color="primary" unelevated class="full-width" style="border-radius: 0.5rem;"
                        :loading="loading" @click="handleAssign">
                        <div class="py-1">Confirmar Asignación</div>
                    </q-btn>
                </q-card-actions>
            </template>

            <template v-else>
                <q-card-section class="row items-center q-pb-none">
                    <div class="text-h6 text-bold text-black">
                        {{ formData.apartment.user_id ? 'Confirmar transferencia' : 'Confirmar asignación' }}
                    </div>
                    <q-space />
                    <q-btn icon="eva-close-outline" flat round dense @click="closeModal" />
                </q-card-section>

                <q-card-section class="text-black">
                    <div class="text-subtitle1 q-mb-sm">
                        Unidad #{{ formData.apartment.number }}
                    </div>

                    <div v-if="formData.apartment.user_id" class="q-mb-sm">
                        Actualmente pertenece a
                        <span class="text-bold">{{ formData.apartment.owner?.name || 'otro usuario' }}</span>.
                        <span v-if="userName">Se transferirá a <span class="text-bold">{{ userName }}</span>.</span>
                    </div>

                    <div v-if="hasResidents(formData.apartment)" class="q-mb-sm">
                        Esta unidad tiene
                        <span class="text-bold">{{ formData.apartment.residents_count }}</span>
                        inquilino(s)/familiar(es) asociado(s).
                    </div>

                    <q-checkbox v-if="hasResidents(formData.apartment)" v-model="removeResidents"
                        class="q-mt-sm" color="primary" label="Quitar inquilinos y familiares asociados" />
                </q-card-section>

                <q-card-actions class="q-pb-lg q-px-md">
                    <q-btn flat color="grey-8" class="col" label="Cancelar" @click="confirmOpen = false" />
                    <q-btn color="primary" unelevated class="col" style="border-radius: 0.5rem;"
                        :loading="loading" @click="confirmAssignment">
                        Confirmar
                    </q-btn>
                </q-card-actions>
            </template>
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
