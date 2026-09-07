<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useUserStore } from '@/services/store/users.store';
import { useQuasar } from 'quasar';
import phoneNumberInput from '@/components/layout/phoneNumberInput.vue';
import userAvailableAreasStep from '@/components/admin/userAvailableAreasStep.vue';

const $q = useQuasar();
const route = useRoute();
const router = useRouter();
const userStore = useUserStore();

const loading = ref(false);
const initialLoading = ref(true);
const isPwd = ref(true);
const selectedAreas = ref([])
const areaLoading = ref(false)

const formData = ref({
    name: '',
    username: '',
    email: '',
    phone: '',
    password: '',
    parentesco: '',
    typeLabel: '' // Para mostrar de solo lectura
});

const isFamiliar = ref(false);

const loadData = () => {
    initialLoading.value = true;
    userStore.getUserById(route.params.id)
        .then((response) => {
            const user = response.data;
            formData.value.name = user.name || '';
            formData.value.username = user.username || '';
            formData.value.email = user.email || '';
            formData.value.phone = user.phone || '';
            formData.value.parentesco = user.parentesco || '';
            
            // Determinar tipo basado en rol_id o type
            isFamiliar.value = user.rol_id === 4; // 4 = FAMILIAR
            
            if (user.rol_id === 4) formData.value.typeLabel = 'Familiar';
            else if (user.rol_id === 5) formData.value.typeLabel = 'Huésped temporal (Airbnb)';
            else if (user.rol_id === 3) formData.value.typeLabel = 'Inquilino';
            else formData.value.typeLabel = 'Usuario';
        })
        .catch((error) => {
            $q.notify({
                color: 'negative',
                message: error || 'Error al cargar datos del residente',
                timeout: 2000
            });
            router.go(-1);
        })
        .finally(() => {
            initialLoading.value = false;
        });
};

const onSubmit = () => {
    loading.value = true;
    
    // Solo enviamos parentesco si es familiar
    const payload = {
        name: formData.value.name,
        username: formData.value.username,
        email: formData.value.email,
        phone: formData.value.phone,
    };
    
    if (isFamiliar.value) {
        payload.parentesco = formData.value.parentesco;
    }
    
    if (formData.value.password) {
        payload.password = formData.value.password;
    }

    userStore.updateResident(route.params.id, payload)
        .then(() => {
            $q.notify({
                color: 'positive',
                message: 'Datos actualizados correctamente',
                timeout: 2000
            });
            setTimeout(() => {
                router.go(-1);
            }, 800);
        })
        .catch((error) => {
            $q.notify({
                color: 'negative',
                message: error || 'Error al actualizar datos',
                timeout: 2000
            });
        })
        .finally(() => {
            loading.value = false;
        });
};

const saveAreas = () => {
    areaLoading.value = true
    userStore.setAvailableComunaAreas(route.params.id, selectedAreas.value)
        .then(() => {
            $q.notify({
                color: 'positive',
                message: 'Áreas comunes actualizadas correctamente',
                timeout: 2000
            });
        })
        .catch((error) => {
            $q.notify({
                color: 'negative',
                message: error || 'Error al actualizar las áreas comunes',
                timeout: 2000
            });
        })
        .finally(() => {
            areaLoading.value = false
        });
};

onMounted(() => {
    loadData();
});
</script>

<template>
    <div class="md:px-20 px-2 h-full">
        <div class="text-center text-black text-h5 text-bold md:mt-4 mt-5 mb-3">
            Editar residente
        </div>
        
        <div v-if="initialLoading" class="flex flex-center" style="height: 60vh;">
            <q-spinner-dots color="primary" size="4rem" />
        </div>

        <q-form v-else @submit="onSubmit">
            <div class="row w-full mt-5">
                <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
                    <div class="text-subtitle2 text-bold text-black pt-2">
                        Tipo de residente (Solo lectura)
                    </div>
                    <q-input borderless dense class="form__inputsCR mt-2 bg-grey-2" v-model="formData.typeLabel" readonly />
                </div>
                
                <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
                    <div class="text-subtitle2 text-bold text-black pt-2">
                        Nombre del residente
                    </div>
                    <q-input borderless clearable dense class="form__inputsCR mt-2" color="primary" v-model="formData.name"
                        :rules="[val => !!val || 'El nombre es requerido']" />
                </div>

                <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
                    <div class="text-subtitle2 text-bold text-black pt-2">
                        Nombre de usuario
                    </div>
                    <q-input borderless clearable dense class="form__inputsCR mt-2" color="primary" v-model="formData.username"
                        :rules="[val => !!val || 'El nombre de usuario es requerido']" />
                </div>
                
                <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
                    <div class="text-subtitle2 text-bold text-black pt-2">
                        Correo electrónico
                    </div>
                    <q-input borderless clearable dense class="form__inputsCR mt-2" color="primary" v-model="formData.email"
                        :rules="[val => !!val || 'El correo electrónico es requerido']" />
                </div>
                
                <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
                    <div class="text-subtitle2 text-bold text-black">
                        Teléfono
                    </div>
                    <phoneNumberInput v-model="formData.phone" label="Teléfono" placeholder="412-1234567"
                        class="phoneUser" />
                </div>
                
                <div v-if="isFamiliar" class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
                    <div class="text-subtitle2 text-bold text-black pt-2">
                        Parentesco
                    </div>
                    <q-input borderless clearable dense class="form__inputsCR mt-2" color="primary" v-model="formData.parentesco"
                        :rules="[val => !!val || 'Indica el parentesco']" />
                </div>
                
                <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
                    <div class="text-subtitle2 text-bold text-black pt-2">
                        Nueva contraseña (Opcional)
                    </div>
                    <q-input borderless clearable dense class="form__inputsCR mt-2" color="primary"
                        v-model="formData.password" :type="isPwd ? 'password' : 'text'"
                        :rules="[val => !val || val.length >= 8 || 'Debe tener al menos 8 caracteres']"
                        hint="Déjalo en blanco para mantener la contraseña actual">
                        <template v-slot:append>
                            <q-icon :name="isPwd ? 'eva-eye-off-outline' : 'eva-eye-outline'"
                                class="cursor-pointer" @click="isPwd = !isPwd" />
                        </template>
                    </q-input>
                </div>
                
                <div class="col-12 my-1 px-2 md:px-12">
                    <q-separator class="q-my-md" />
                    <div class="text-subtitle2 text-bold text-black pt-2">
                        Áreas que puede reservar
                    </div>
                    <div class="text-body2 text-grey-7">
                        Si no seleccionas ninguna área, el usuario podrá reservar todas las disponibles.
                    </div>
                    <userAvailableAreasStep v-model="selectedAreas" :userId="route.params.id" class="q-mt-sm" />
                    <div class="flex justify-end q-mt-sm">
                        <q-btn color="teal" no-caps style="border-radius: 0.5rem" :loading="areaLoading" @click="saveAreas">
                            <div class="px-8 py-1">Guardar áreas</div>
                        </q-btn>
                    </div>
                </div>
                
                <div class="col-12 my-4 px-2 md:px-12 flex items-center justify-between">
                    <q-btn flat color="grey-9" class="q-mr-sm" @click="router.go(-1)">
                        Volver
                    </q-btn>
                    <q-btn color="primary" style="border-radius: 0.5rem" no-caps type="submit" :loading="loading">
                        <div class="px-10 py-1">
                            Guardar cambios
                        </div>
                    </q-btn>
                </div>
            </div>
        </q-form>
    </div>
</template>

<style lang="scss">
.form__inputsCR {
    & .q-field__inner {
        box-shadow: 0px 3px 5px 0px #bfbfbfa3;
        border-radius: 0.8rem;
        border: 1px solid rgb(223, 223, 223);
        padding: 0px 2rem;
    }
}

@media (max-width: 780px) {
    .form__inputsCR {
        & .q-field__inner {
            padding: 0px 1rem;
        }
    }
}
</style>
