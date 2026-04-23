<script setup>

import { onMounted, ref, watch } from 'vue';
import { Notify } from 'quasar'
import { useUserStore } from '@/services/store/users.store';
import { useApartmentStore } from '@/services/store/apartment.store';
import phoneNumberInput from '@/components/layout/phoneNumberInput.vue';
import { useRouter } from 'vue-router';
const router = useRouter()
const userStore = useUserStore()
const apartmentStore = useApartmentStore()
const apartmentsOptions = ref([])
const hasNoApartments = ref(false)
const tipoResidentOptions = ref([
    {
        id: '',
        title: 'Selecciona el tipo de residente'
    },
    {
        id: 'airbnb',
        title: 'Airbnb (Alquiler temporal)'
    },
    {
        id: 'familiar',
        title: 'Familiar / Habitante'
    },
])

const getApartmentsByUser = () => {
    apartmentStore.getApartmentByUser()
        .then((response) => {
            const apartments = response.data || []
            hasNoApartments.value = apartments.length === 0

            if (apartments.length === 0) {
                apartmentsOptions.value = []
                return
            }

            apartmentsOptions.value = [
                { id: 0, number: 'Selecciona un apartamento', area: null },
                ...apartments
            ]

            if (apartments.length === 1) {
                formData.value.apartment = apartments[0]
            }
        })
        .catch(() => {
            hasNoApartments.value = true
            apartmentsOptions.value = []
        })
}
const props = defineProps({
    dialog: Boolean,
    rifa: Object,
})

const isPwd = ref('true')

const step = ref(0)
const loading = ref(false)
const formData = ref({
    name: '',
    username: '',
    email: '',
    phone: '',
    password: '',
    type: {
        id: '',
        title: 'Selecciona el tipo de residente'
    },
    apartment: {
        id: 0,
        number: 'Selecciona un apartamento'
    },
    idApartament: 0,
    active_time: null,
    parentesco: ''
})
const airbnbFormData = ref({
    nameTo:'',
    quantity: 0,
    init_time: null,
    end_time: null

})

const airbnbGuests = ref([])
watch(() => airbnbFormData.value.quantity, (newVal) => {
    const count = parseInt(newVal) || 0
    if (count > airbnbGuests.value.length) {
        const diff = count - airbnbGuests.value.length
        for (let i = 0; i < diff; i++) {
            airbnbGuests.value.push({ name: '', document: '', photo: null })
        }
    } else if (count < airbnbGuests.value.length) {
        airbnbGuests.value = airbnbGuests.value.slice(0, count)
    }
})

const titleOfSection = [
    'Tipo y asignación',
    'Datos del residente',
    'Registro de Airbnb',
    'Datos de Airbnb'   
]
const isAirbnb = () => formData.value.type?.id === 'airbnb'
const isFamiliar = () => formData.value.type?.id === 'familiar'

const formatDateForApi = (date) => {
    if (!date) return null
    if (typeof date === 'string') {
        return date.replace(/\//g, '-')
    }
    return date
}
const nextStep = () => {
   if(!validatorStep()){
    return false
   }
   if(step.value == 1 && isFamiliar()){
    createUser()
    return
   }
   step.value++
}
const createUser = () => {
    loading.value = true
    formData.value.idApartament = formData.value.apartment?.id

    const payload = {
        type: formData.value.type?.id,
        name: formData.value.name,
        email: formData.value.email,
        phone: formData.value.phone || null,
        username: formData.value.username || null,
        password: formData.value.password || null,
        idApartament: formData.value.idApartament,
    }

    if (isAirbnb()) {
        payload.active_time = formatDateForApi(formData.value.active_time)
    }
    if (isFamiliar()) {
        payload.parentesco = formData.value.parentesco
    }

    userStore.createResident(payload)
    .then((response) => {
        showNotify('positive', response?.data?.message || 'Residente registrado con éxito')
        setTimeout(() => {
            loading.value = false
            router.go(-1)
        }, 1000);
    })
    .catch((response) => {
        console.log(response)
        loading.value = false
        showNotify('negative', response?.message || response)
    })
}

const validatorStep = () => { 
    if (step.value == 0) {
        const apartmentId = formData.value.apartment?.id
        if (!apartmentId || apartmentId === 0) {
            showNotify('negative', 'Selecciona un apartamento')
            return false
        }
        if (!formData.value.type?.id) {
            showNotify('negative', 'Selecciona el tipo de residente')
            return false
        }
        // if (isAirbnb() && !formData.value.active_time) {
        //     showNotify('negative', 'Indica la fecha de fin del alquiler')
        //     return false
        // }
        
        if (isFamiliar() && !formData.value.parentesco) {
            showNotify('negative', 'Indica el parentesco')
            return false
        }
    }
    if (step.value == 1) {
        if ( !formData.value.name) {
            showNotify('negative', 'Nombre es requerido')
            return false
        }
        if ( !formData.value.email) {
            showNotify('negative', 'correo electronico es requerido')
            return false
        }
        if ( !formData.value.phone) {
            showNotify('negative', 'Telefono es requerido')
            return false
        }
        if ( !formData.value.password && formData.value.password.length < 8) {
            showNotify('negative', 'Contraseña es necesaria y debe tener una longitud de 8 caracteres')
            return false
        }
    }
    if (step.value == 2) {
        if ( !airbnbFormData.value.quantity || airbnbFormData.value.quantity <= 0) {
            showNotify('negative', 'Cantidad de personas es requerido')
            return false
        }
        if ( !airbnbFormData.value.nameTo) {
            showNotify('negative', 'Nombre de reservador es requerido')
            return false
        }
        if ( !airbnbFormData.value.init_time) {
            showNotify('negative', 'Fecha de inicio es requerido')
            return false
        }
        if ( !airbnbFormData.value.end_time) {
            showNotify('negative', 'Fecha de finalización es requerido')
            return false
        }
    }
    return true
}
const autogenerateNameAirbnb = (e) => {

    if(e.id ==  'airbnb'){
        formData.value.username = 'Airbnb' + formData.value.apartment.number + '-'+ Math.floor(Math.random() * 10000) + 1;
    }
}
const showNotify = (type, text) => {
    Notify.create({
        color: type,
        message: text,
        timeout: 2000
    })
}


onMounted(() => {
    getApartmentsByUser()
})

</script>
<template>
    <div class="md:px-20 md:mx-16 px-2">
        <div class="text-center text-black text-h5 text-bold md:mt-4 mt-5 mb-3">
            {{ titleOfSection[step] }}
        </div>
        <q-form @submit="nextStep()">
            <Transition name="horizontal">
                <div class="row w-full" v-if="step == 0">
                    <div class="col-md-6 md:my-0 col-12 my-1 mb-4 px-2 md:px-12">
                        <div class="text-subtitle2 text-bold text-black">
                            Tipo de residente
                        </div>
                        <q-select borderless dense class="form__inputsCR mt-2" v-model="formData.type" option-value="id"
                            option-label="title" :options="tipoResidentOptions" behavior="menu" @update:model-value="autogenerateNameAirbnb">
                        </q-select>
                    </div>
                    <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
                        <div class="text-subtitle2 text-bold text-black">
                            Apartamento
                        </div>
                        <q-select v-if="!hasNoApartments" borderless dense class="form__inputsCR mt-2"
                            v-model="formData.apartment" option-value="id" option-label="number"
                            :options="apartmentsOptions" behavior="menu" >

                            <template v-slot:option="scope">
                                <q-item v-bind="scope.itemProps">
                                    <div class="w-full">
                                        <div class="flex items-center justify-between w-full">
                                            <div class="text-subtitle1 " style="font-weight: 500;">
                                                {{ scope.opt.id != 0 ? '#' : '' }} {{ scope.opt.number }}
                                            </div>
                                            <div v-if="scope.opt.id != 0" class="text-positive text-subtitle2 pl-2">
                                                Tu apartamento
                                            </div>
                                        </div>
                                        <div class="text-caption text-grey-6"
                                            v-if="scope.opt.id != 0 && scope.opt.area">
                                            {{ scope.opt.area }} mt²
                                        </div>
                                    </div>
                                </q-item>
                            </template>
                        </q-select>
                        <q-banner v-else class="rounded-borders q-mt-2 bg-warning">
                            <template v-slot:avatar>
                                <q-icon name="eva-home-outline" color="warning" />
                            </template>
                            No tienes apartamentos asignados. Contacta al administrador.
                        </q-banner>
                    </div>

                   
                    <!-- Parentesco (solo Familiar) -->
                    <div v-if="formData.type?.id === 'familiar'" class="col-12 md:my-0 my-1 px-2 md:px-12">
                        <div class="text-subtitle2 text-bold text-black">
                            Parentesco
                        </div>
                        <q-select borderless dense class="form__inputsCR mt-2" v-model="formData.parentesco"
                            :options="['Padre', 'Madre', 'Hijo', 'Hija', 'Cónyuge', 'Hermano', 'Hermana', 'Abuelo', 'Abuela', 'Nieto', 'Nieta', 'Otro']"
                            placeholder="Ej: Hijo, Madre, Cónyuge...">
                        </q-select>
                    </div>

                    <div class="col-12 my-2 mt-5 px-2 md:px-12 flex justify-end">
                        <q-btn color="primary " style="border-radius: 0.5rem;" type="submit" :loading="loading"
                            :disable="hasNoApartments">
                            <div class="px-10 py-1">
                                Siguiente
                            </div>
                        </q-btn>
                    </div>

                </div>
            </Transition>
            <Transition name="horizontal">
                <div class="row w-full" v-if="step == 1">
                    <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
                        <div class="text-subtitle2 text-bold text-black">
                            Nombre completo
                        </div>
                        <q-input borderless clearable v-model="formData.name" dense class="form__inputsCR mt-2"
                            color="primary" :rules="[val => val && val.length > 0 || 'Nombre es requerido']" />
                    </div>
                    <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
                        <div class="text-subtitle2 text-bold text-black">
                            Nombre de usuario (opcional)
                        </div>
                        <q-input borderless clearable v-model="formData.username" dense class="form__inputsCR mt-2"
                            color="primary" />
                    </div>
                    <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
                        <div class="text-subtitle2 text-bold text-black">
                            Correo electrónico
                        </div>
                        <q-input borderless clearable v-model="formData.email" dense class="form__inputsCR mt-2"
                            color="primary"
                            :rules="[val => val && val.length > 0 || 'Correo electrónico es requerido']" />
                    </div>
                    <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
                        <div class="text-subtitle2 text-bold text-black">
                            Contraseña
                        </div>
                        <q-input borderless clearable v-model="formData.password" dense class="form__inputsCR mt-2"
                            color="primary" :type="isPwd ? 'password' : 'text'">
                            <template v-slot:append>
                                <q-icon :name="isPwd ? 'eva-eye-off-outline' : 'eva-eye-outline'" class="cursor-pointer"
                                    @click="isPwd = !isPwd" />
                            </template>
                        </q-input>
                    </div>
                    <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
                        <div class="text-subtitle2 text-bold text-black">
                            Teléfono
                        </div>
                        <phoneNumberInput v-model="formData.phone" label="Teléfono" placeholder="412-1234567"
                            class="phoneUser" :rules="[val => !!val || 'El teléfono es requerido']" />
                    </div>

                    <div class="col-12 pb-8 mt-2 px-2 md:px-12 flex items-center justify-between">
                        <div class="flex items-center" style="width: 50%; box-sizing: border-box;">
                            <q-btn color="grey-9 " style="border-radius: 0.5rem;" @click="step--">
                                <div class="px-8 py-1 ">
                                    Volver
                                </div>
                            </q-btn>
                        </div>
                        <div class="flex items-center justify-end" style="width: 50%; box-sizing: border-box;">
                            <q-btn color="primary " style="border-radius: 0.5rem;" type="submit" :loading="loading">
                                <div class="px-8 py-1 ">
                                    {{isFamiliar() ? 'Registrar' : 'Siguiente'}}
                                </div>
                            </q-btn>
                        </div>
                    </div>

                </div>
            </Transition>
            <Transition name="horizontal">
                <div class="row w-full" v-if="step == 2">
                    <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
                        <div class="text-subtitle2 text-bold text-black">
                            Números de personas
                        </div>
                        <q-input borderless clearable  type="number" v-model="airbnbFormData.quantity" dense class="form__inputsCR mt-1" 
                            color="primary" :rules="[val => val && val > 0 || 'Cantidad de personas es requerido']" />
                    </div>
                    <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
                        <div class="text-subtitle2 text-bold text-black">
                            A nombre de
                        </div>
                        <q-input borderless clearable  v-model="airbnbFormData.nameTo" dense class="form__inputsCR mt-1" 
                            color="primary" :rules="[val => val && val.length > 0 || 'Este nombre es requerido']" />
                    </div>
                     <!-- Fecha hasta (solo Airbnb) -->
                    <div  class="col-12 md:my-0 my-1 px-2 md:px-12 md:pt-8">
                        <div class="text-subtitle2 text-bold text-black">
                            Fecha desde (Inicio del alquiler)
                        </div>
                        <q-input borderless dense class="form__inputsCR mt-1" v-model="airbnbFormData.init_time" 
                            :rules="[val => !(!val)  || 'Fecha de fin de alquiler es requerida']">
                            <template v-slot:append>
                                <q-icon name="eva-calendar-outline" class="cursor-pointer">
                                    <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                                        <q-date v-model="airbnbFormData.init_time" minimal mask="DD/MM/YYYY"
                                            :options="(date) => date.replace(/\//g, '-') >= new Date().toISOString().split('T')[0]">
                                            <div class="row items-center justify-end">
                                                <q-btn v-close-popup label="OK" color="primary" flat />
                                            </div>
                                        </q-date>
                                    </q-popup-proxy>
                                </q-icon>
                            </template>
                        </q-input>
                    </div>

                    <div  class="col-12 md:my-0 my-1 px-2 md:px-12 md:pt-8">
                        <div class="text-subtitle2 text-bold text-black">
                            Fecha hasta (fin del alquiler)
                        </div>
                        <q-input borderless dense class="form__inputsCR mt-1" v-model="airbnbFormData.end_time" 
                            :rules="[val => !(!val)  || 'Fecha de fin de alquiler es requerida']">
                            <template v-slot:append>
                                <q-icon name="eva-calendar-outline" class="cursor-pointer">
                                    <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                                        <q-date v-model="airbnbFormData.end_time" minimal mask="DD/MM/YYYY"
                                            :options="(date) => date.replace(/\//g, '-') >= new Date().toISOString().split('T')[0]">
                                            <div class="row items-center justify-end">
                                                <q-btn v-close-popup label="OK" color="primary" flat />
                                            </div>
                                        </q-date>
                                    </q-popup-proxy>
                                </q-icon>
                            </template>
                        </q-input>
                    </div>

                    <div class="col-12 pb-8 mt-2 px-2 md:px-12 flex items-center justify-between">
                        <div class="flex items-center" style="width: 50%; box-sizing: border-box;">
                            <q-btn color="grey-9 " style="border-radius: 0.5rem;" @click="step--">
                                <div class="px-8 py-1 ">
                                    Volver
                                </div>
                            </q-btn>
                        </div>
                        <div class="flex items-center justify-end" style="width: 50%; box-sizing: border-box;">
                            <q-btn color="primary " style="border-radius: 0.5rem;" type="submit" :loading="loading">
                                <div class="px-8 py-1 ">
                                    Siguiente
                                </div>
                            </q-btn>
                        </div>
                    </div>

                </div>
            </Transition>
            <Transition name="horizontal">
                <div class="row w-full" v-if="step == 3">
                    
                    <div class="col-12" v-for="(guest, index) in airbnbGuests" :key="index">
                        <div class="text-subtitle1 text-bold text-primary mt-4 mb-2 px-2 md:px-12">
                            Persona {{ index + 1 }}
                        </div>
                        
                        <div class="row w-full">
                            <div class="col-md-6 col-12 my-1 px-2 md:px-12">
                                <div class="text-subtitle2 text-bold text-black">Nombre completo</div>
                                <q-input borderless clearable v-model="guest.name" dense class="form__inputsCR mt-2" color="primary" />
                            </div>
                            
                            <div class="col-md-6 col-12 my-1 px-2 md:px-12">
                                <div class="text-subtitle2 text-bold text-black">Documento de identidad</div>
                                <q-input borderless clearable v-model="guest.document" dense class="form__inputsCR mt-2" color="primary" />
                            </div>
                            
                            <div class="col-12 my-1 px-2 md:px-12">
                                <div class="text-subtitle2 text-bold text-black">Foto de la persona</div>
                                <q-file borderless clearable v-model="guest.photo" dense class="form__inputsCR mt-2" color="primary" accept="image/*" label="Cargar foto">
                                    <template v-slot:prepend>
                                        <q-icon name="eva-cloud-upload-outline" />
                                    </template>
                                </q-file>
                            </div>
                        </div>
                        <q-separator class="mt-4 q-mx-md" v-if="index < airbnbGuests.length - 1" />
                    </div>

                    <div class="col-12 pb-8 mt-6 px-2 md:px-12 flex items-center justify-between">
                        <div class="flex items-center" style="width: 50%; box-sizing: border-box;">
                            <q-btn color="grey-9" style="border-radius: 0.5rem;" @click="step--">
                                <div class="px-8 py-1">Volver</div>
                            </q-btn>
                        </div>
                        <div class="flex items-center justify-end" style="width: 50%; box-sizing: border-box;">
                            <q-btn color="primary" style="border-radius: 0.5rem;" type="submit" :loading="loading">
                                <div class="px-8 py-1">Registrar Airbnb</div>
                            </q-btn>
                        </div>
                    </div>
                </div>
            </Transition>
        </q-form>
    </div>
</template>
<style lang="scss">
.phoneUser.form__inputsSelect .prefixInput .q-field__inner {
    border: 0px solid rgb(223, 223, 223);
}

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