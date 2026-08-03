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
const hasSingleApartment = ref(false)
const deptosWithInquilino = ref(new Set())
const tipoResidentOptions = ref([
    {
        id: '',
        title: 'Selecciona el tipo de usuario'
    },
    {
        id: 'familiar',
        title: '👨‍👩‍👧 Familiar'
    },
    {
        id: 'inquilino',
        title: '🏠 Inquilino'
    },
    {
        id: 'airbnb',
        title: '🧳 Huésped temporal (Airbnb)'
    },
])

const getApartmentsByUser = () => {
    apartmentStore.getApartmentByUser()
        .then((response) => {
            const apartments = (response.data || []).filter(a => a.type == 1)
            hasNoApartments.value = apartments.length === 0
            hasSingleApartment.value = apartments.length === 1

            if (apartments.length === 0) {
                apartmentsOptions.value = []
                return
            }

            if (apartments.length === 1) {
                formData.value.apartment = apartments[0]
                return
            }

            apartmentsOptions.value = [
                { id: 0, number: 'Selecciona un departamento', area: null },
                ...apartments
            ]
        })
        .catch(() => {
            hasNoApartments.value = true
            apartmentsOptions.value = []
        })
}

const getInquilinosByUser = () => {
    userStore.getResidents()
        .then((response) => {
            const residents = response.data || []
            const inquilinos = residents.filter(r => r.type === 3)
            deptosWithInquilino.value = new Set(inquilinos.map(r => r.departament?.id))
        })
        .catch(() => {})
}


const isPwd = ref('true')
const showPassword = ref(false)

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
        title: 'Selecciona el tipo de usuario'
    },
    apartment: {
        id: 0,
        number: 'Selecciona un departamento'
    },
    idApartament: 0,
    active_time: null,
    parentesco: ''
})
const airbnbFormData = ref({
    nameTo: '',
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
    'Datos de Airbnb',
    'Credenciales de acceso'
]
const isAirbnb = () => formData.value.type?.id === 'airbnb'
const isFamiliar = () => formData.value.type?.id === 'familiar'
const isInquilino = () => formData.value.type?.id === 'inquilino'

const formatDateForApi = (date) => {
    if (!date) return null
    if (typeof date === 'string') {
        return date.replace(/\//g, '-')
    }
    return date
}
const nextStep = () => {
    if (!validatorStep()) {
        return false
    }
    if (step.value == 1 && (isFamiliar() || isInquilino())) {
        createUser()
        return
    }
    if (step.value == 3) {
        createUser()
        return
    }
    step.value++
}
const createUser = () => {
    loading.value = true
    formData.value.idApartament = formData.value.apartment?.id

    // Como estamos enviando imágenes, usamos FormData en lugar de un objeto JSON
    const payloadForm = new FormData();

    // Datos base del residente / cuenta
    payloadForm.append('type', formData.value.type?.id);
    payloadForm.append('name', formData.value.name);
    if (formData.value.email) payloadForm.append('email', formData.value.email);
    payloadForm.append('idApartament', formData.value.idApartament);

    if (formData.value.phone) payloadForm.append('phone', formData.value.phone);
    if (formData.value.username) payloadForm.append('username', formData.value.username);
    if (formData.value.password) payloadForm.append('password', formData.value.password);

    if (isAirbnb()) {
        payloadForm.append('active_time', formatDateForApi(airbnbFormData.value.init_time));
        payloadForm.append('end_time', formatDateForApi(airbnbFormData.value.end_time));
        payloadForm.append('airbnb[nameTo]', airbnbFormData.value.nameTo);
        payloadForm.append('airbnb[quantity]', airbnbFormData.value.quantity);
        payloadForm.append('airbnb[init_time]', formatDateForApi(airbnbFormData.value.init_time));
        payloadForm.append('airbnb[end_time]', formatDateForApi(airbnbFormData.value.end_time));
        airbnbGuests.value.forEach((guest, index) => {
            payloadForm.append(`airbnb[guests][${index}][name]`, guest.name);
            payloadForm.append(`airbnb[guests][${index}][document]`, guest.document);
            if (guest.photo) {
                payloadForm.append(`airbnb[guests][${index}][photo]`, guest.photo);
            }
        });
    }

    // if (isFamiliar()) {
    //     payloadForm.append('parentesco', formData.value.parentesco);
    // }

    userStore.createResident(payloadForm)
        .then((response) => {
            showNotify('positive', response?.data?.message || 'Residente registrado con éxito')
            loading.value = false
            step.value = 4
        })
        .catch((response) => {
            console.error(response)
            loading.value = false
            showNotify('negative', response?.message || response)
        })
}


const validatorStep = () => {
    if (step.value == 0) {
        const apartmentId = formData.value.apartment?.id
        if (!apartmentId || apartmentId === 0) {
            showNotify('negative', 'Selecciona un departamento')
            return false
        }
        if (!formData.value.type?.id) {
            showNotify('negative', 'Selecciona el tipo de residente')
            return false
        }

        // if (isFamiliar() && !formData.value.parentesco) {
        //     showNotify('negative', 'Indica el parentesco')
        //     return false
        // }
        if (isInquilino() && deptosWithInquilino.value.has(apartmentId)) {
            showNotify('negative', 'Este departamento ya tiene un inquilino asignado')
            return false
        }
    }
    if (step.value == 1) {
        if (!formData.value.name) {
            showNotify('negative', 'Nombre es requerido')
            return false
        }
        if (!formData.value.username) {
            showNotify('negative', 'Nombre de usuario es requerido')
            return false
        }
        if (!formData.value.email) {
            showNotify('negative', 'Correo electrónico es requerido')
            return false
        }
        if (!isAirbnb()) {
            if (!formData.value.password && formData.value.password.length < 8) {
                showNotify('negative', 'Contraseña es necesaria y debe tener una longitud de 8 caracteres')
                return false
            }
            if (isInquilino() && !formData.value.phone) {
                showNotify('negative', 'Teléfono es requerido para inquilinos')
                return false
            }
        }
    }
    if (step.value == 2) {
        if (!airbnbFormData.value.quantity || airbnbFormData.value.quantity <= 0) {
            showNotify('negative', 'Cantidad de personas es requerido')
            return false
        }
        if (!airbnbFormData.value.nameTo) {
            showNotify('negative', 'Nombre de reservador es requerido')
            return false
        }
        if (!airbnbFormData.value.init_time) {
            showNotify('negative', 'Fecha de inicio es requerido')
            return false
        }
        if (!airbnbFormData.value.end_time) {
            showNotify('negative', 'Fecha de finalización es requerido')
            return false
        }
        if (airbnbFormData.value.end_time && airbnbFormData.value.init_time) {
            const initParts = airbnbFormData.value.init_time.split('/')
            const endParts = airbnbFormData.value.end_time.split('/')
            const initDate = new Date(initParts[2], initParts[1] - 1, initParts[0])
            const endDate = new Date(endParts[2], endParts[1] - 1, endParts[0])
            if (endDate < initDate) {
                showNotify('negative', 'La fecha de finalización no puede ser anterior a la fecha de inicio')
                return false
            }
        }
    }
    if (step.value == 3) {
        // Validamos que el array no esté vacío si se puso una cantidad
        if (airbnbGuests.value.length === 0 && airbnbFormData.value.quantity > 0) {
            showNotify('negative', 'Debe completar los datos de los huéspedes');
            return false;
        }

        // Validación manual adicional por si acaso
        for (let i = 0; i < airbnbGuests.value.length; i++) {
            const guest = airbnbGuests.value[i];
            if (!guest.name || !guest.document || !guest.photo) {
                showNotify('negative', `Faltan datos en la Persona ${i + 1}`);
                return false;
            }
        }
    }
    return true
}
const autogenerateNameAirbnb = (e) => {

    if (e.id == 'airbnb') {
        formData.value.username = 'Airbnb' + formData.value.apartment.number.slice(-3) + Math.floor(Math.random() * 10000) + 1;
    }
}
const generateRandomPassword = () => {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()'
    let password = ''
    for (let i = 0; i < 12; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length))
    }
    return password
}

const generateRandomUsername = () => {
    const name = formData.value.name || 'user'
    const sanitized = name.toLowerCase().replace(/\s+/g, '').replace(/[^a-z0-9]/g, '')
    const suffix = Math.floor(Math.random() * 10000)
    return sanitized + suffix
}

const setGeneratedPassword = () => {
    formData.value.password = generateRandomPassword()
}

const setGeneratedUsername = () => {
    formData.value.username = generateRandomUsername()
}

const copyToClipboard = (text) => {
    navigator.clipboard.writeText(text)
    showNotify('positive', 'Copiado al portapapeles')
}

const finish = () => {
    router.go(-1)
}

const showNotify = (type, text) => {
    Notify.create({
        color: type,
        message: text,
        timeout: 2000
    })
}
const myLocale = {
    /* starting with Sunday */
    days: 'Domingo_Lunes_Martes_Miércoles_Jueves_Viernes_Sábado'.split('_'),
    daysShort: 'Dom_Lun_Mar_Mié_Jue_Vie_Sáb'.split('_'),
    months:
        'Enero_Febrero_Marzo_Abril_Mayo_Junio_Julio_Agosto_Septiembre_Octubre_Noviembre_Diciembre'.split(
            '_'
        ),
    monthsShort: 'Ene_Feb_Mar_Abr_May_Jun_Jul_Ago_Sep_Oct_Nov_Dic'.split(
        '_'
    ),
    firstDayOfWeek: 1, // 0-6, 0 - Sunday, 1 Monday, ...
    format24h: true,
    pluralDay: 'dias'
}

onMounted(() => {
    getApartmentsByUser()
    getInquilinosByUser()
})

</script>
<template>
    <div class="md:px-20 px-2">
        <div class="text-center text-black text-h5 text-bold md:mt-4 mt-5 mb-3">
            {{ titleOfSection[step] }}
        </div>
        <div class="text-center text-gray-600 mb-4">
            Completa la información de la persona que tendrá acceso a la aplicación.
        </div>
        <q-form @submit="nextStep()">
            <Transition name="horizontal">
                <div class="row w-full" v-if="step == 0">
                    <div class="col-md-6 md:my-0 col-12 my-1 mb-4 px-2 md:px-12">
                        <div class="text-subtitle2 text-bold text-black">
                            Tipo de residente
                        </div>
                        <q-select borderless dense class="form__inputsCR mt-2" v-model="formData.type" option-value="id"
                            option-label="title" :options="tipoResidentOptions" behavior="menu"
                            @update:model-value="autogenerateNameAirbnb">
                        </q-select>
                    </div>
                    <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
                        <div class="text-subtitle2 text-bold text-black">
                            Departamento
                        </div>
                        <q-banner v-if="hasNoApartments" class="rounded-borders q-mt-2 bg-warning">
                            <template v-slot:avatar>
                                <q-icon name="eva-home-outline" color="warning" />
                            </template>
                            No tienes departamentos asignados. Contacta al administrador.
                        </q-banner>
                        <!-- <div v-else-if="hasSingleApartment" class="form__inputsCR mt-2 flex items-center justify-between"
                            style="padding: 0.5rem 2rem; min-height: 36px;">
                            <div class="text-subtitle1" style="font-weight: 500;">
                                # {{ formData.apartment.number }}
                            </div>
                            <div class="text-positive text-subtitle2">
                                Tu departamento
                            </div>
                        </div> -->
                        <q-select v-else borderless dense class="form__inputsCR mt-2"
                            v-model="formData.apartment" option-value="id" option-label="number"
                            :options="apartmentsOptions" behavior="menu" :disable="hasSingleApartment" :readonly="hasSingleApartment">
                            <template v-slot:option="scope">
                                <q-item v-bind="scope.itemProps">
                                    <div class="w-full">
                                        <div class="flex items-center justify-between w-full">
                                            <div class="text-subtitle1" style="font-weight: 500;">
                                                {{ scope.opt.id != 0 ? '#' : '' }} {{ scope.opt.number }}
                                            </div>
                                            <div v-if="scope.opt.id != 0" class="text-positive text-subtitle2 pl-2">
                                                Tu departamento
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
                    </div>


                    <!-- Parentesco (solo Familiar) -->
                    <!-- <div v-if="formData.type?.id === 'familiar'" class="col-12 md:my-0 my-1 px-2 md:px-12 mt-5">
                        <div class="text-subtitle2 text-bold text-black">
                            Parentesco
                        </div>
                        <q-select borderless dense class="form__inputsCR mt-2" v-model="formData.parentesco"
                            :options="['Padre', 'Madre', 'Hijo', 'Hija', 'Cónyuge', 'Hermano', 'Hermana', 'Abuelo', 'Abuela', 'Nieto', 'Nieta', 'Otro']"
                            placeholder="Ej: Hijo, Madre, Cónyuge...">
                        </q-select>
                    </div> -->

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
                            Nombre de usuario
                        </div>
                        <q-input borderless clearable v-model="formData.username" dense class="form__inputsCR mt-2"
                            color="primary" :rules="[val => !!val || 'Nombre de usuario es requerido']" />
                        <div class="text-right q-mt-xs">
                            <span class="cursor-pointer text-primary" style="text-decoration: underline; font-size: 0.85rem;" @click="setGeneratedUsername">
                                generar
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12 mt-4">
                        <div class="text-subtitle2 text-bold text-black">
                            Correo electrónico
                        </div>
                        <q-input borderless clearable v-model="formData.email" dense class="form__inputsCR mt-2"
                            color="primary"
                            :rules="[val => !!val || 'Correo electrónico es requerido', val => !val || /^\S+@\S+\.\S+$/.test(val) || 'Ingresa un correo válido']" />
                    </div>
                    <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
                        <div class="text-subtitle2 text-bold text-black">
                            Contraseña
                        </div>
                        <q-input borderless clearable v-model="formData.password" dense class="form__inputsCR mt-2"
                            color="primary" :type="isPwd ? 'password' : 'text'"
                            :rules="[val => val && val.length > 0 || 'Correo electrónico es requerido', val => val.length >= 8 || 'Contraseña debe tener 8 caracteres']">
                            <template v-slot:append>
                                <q-icon :name="isPwd ? 'eva-eye-off-outline' : 'eva-eye-outline'" class="cursor-pointer"
                                    @click="isPwd = !isPwd" />
                            </template>
                        </q-input>
                        <div class="text-right q-mt-xs">
                            <span class="cursor-pointer text-primary" style="text-decoration: underline; font-size: 0.85rem;" @click="setGeneratedPassword">
                                generar
                            </span>
                        </div>
                    </div>
                    <div v-if="isFamiliar() || isInquilino()" class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
                        <div class="text-subtitle2 text-bold text-black">
                            Teléfono{{ isInquilino() ? '' : ' (opcional)' }}
                        </div>
                        <phoneNumberInput v-model="formData.phone" label="Teléfono" placeholder="930 539 580"
                            class="phoneUser"
                            :rules="isInquilino() ? [val => !!val || 'Teléfono es requerido'] : []" />
                    </div>

                    <div class="col-12 pb-8 mt-2 md:mt-7 px-2 md:px-12 flex items-center justify-between">
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
                                    {{ (isFamiliar() || isInquilino()) ? 'Registrar' : 'Siguiente' }}
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
                        <q-input borderless clearable type="number" v-model="airbnbFormData.quantity" dense
                            class="form__inputsCR mt-1" color="primary" autocomplete="off"
                            :rules="[val => val && val > 0 || 'Cantidad de personas es requerido']" />
                    </div>
                    <div class="col-md-6 md:my-0 col-12 my-1 px-2 md:px-12">
                        <div class="text-subtitle2 text-bold text-black">
                            A nombre de
                        </div>
                        <q-input borderless clearable v-model="airbnbFormData.nameTo" dense class="form__inputsCR mt-1"
                            color="primary" :rules="[val => val && val.length > 0 || 'Este nombre es requerido']" />
                    </div>
                    <!-- Fecha hasta (solo Airbnb) -->
                    <div class="col-md-6 col-12 md:my-0 my-1 px-2 md:px-12 md:pt-8">
                        <div class="text-subtitle2 text-bold text-black">
                            Fecha desde (Inicio del alquiler)
                        </div>
                        <q-input borderless dense class="form__inputsCR mt-1" v-model="airbnbFormData.init_time"
                            :rules="[val => !(!val) || 'Fecha de fin de alquiler es requerida']">
                            <template v-slot:append>
                                <q-icon name="eva-calendar-outline" class="cursor-pointer">
                                    <q-popup-proxy cover transition-show="scale" transition-hide="scale">
                                        <q-date v-model="airbnbFormData.init_time" minimal mask="DD/MM/YYYY"
                                            :options="(date) => date.replace(/\//g, '-') >= new Date().toISOString().split('T')[0]">
                                            <div class="row items-center justify-end" :locale="myLocale">
                                                <q-btn v-close-popup label="OK" color="primary" flat />
                                            </div>
                                        </q-date>
                                    </q-popup-proxy>
                                </q-icon>
                            </template>
                        </q-input>
                    </div>

                    <div class="col-md-6 col-12 md:my-0 my-1 px-2 md:px-12 md:pt-8">
                        <div class="text-subtitle2 text-bold text-black">
                            Fecha hasta (fin del alquiler)
                        </div>
                        <q-input borderless dense class="form__inputsCR mt-1" v-model="airbnbFormData.end_time"
                            :rules="[val => !(!val) || 'Fecha de fin de alquiler es requerida']">
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
                                <q-input borderless v-model="guest.name" dense class="form__inputsCR mt-2"
                                    color="primary" lazy-rules :rules="[val => !!val || 'El nombre es obligatorio']" />
                            </div>

                            <div class="col-md-6 col-12 my-1 px-2 md:px-12">
                                <div class="text-subtitle2 text-bold text-black">Documento de identidad</div>
                                <q-input borderless v-model="guest.document" dense class="form__inputsCR mt-2"
                                    color="primary" lazy-rules
                                    :rules="[val => !!val || 'El documento es obligatorio']" />
                            </div>

                            <div class="col-12 my-1 px-2 md:px-12">
                                <div class="text-subtitle2 text-bold text-black">Foto de la persona</div>
                                <q-file borderless v-model="guest.photo" dense class="form__inputsCR mt-2"
                                    color="primary" accept="image/*" label="Cargar foto" lazy-rules
                                    :rules="[val => !!val || 'La foto es obligatoria']">
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
                                <div class="px-8 py-1">Registrar</div>
                            </q-btn>
                        </div>
                    </div>
                </div>
            </Transition>
            <Transition name="horizontal">
                <div class="row w-full" v-if="step == 4">
                    <div class="col-12 px-2 md:px-12">
                        <div class="text-center q-my-lg">
                            <q-icon name="eva-checkmark-circle-2-outline" size="64px" color="positive" />
                        </div>
                        <div class="text-h6 text-center text-bold q-mb-md">
                            Registro exitoso
                        </div>
                        <div class="text-center text-grey-7 q-mb-lg">
                            Guarda las siguientes credenciales para que el usuario pueda iniciar sesión.
                        </div>
                        <div class="q-pa-md bg-grey-2 rounded-borders q-mb-md">
                            <div class="row items-center justify-between q-py-sm">
                                <div>
                                    <div class="text-caption text-grey-7">Usuario</div>
                                    <div class="text-body1 text-bold">{{ formData.username }}</div>
                                </div>
                                <q-btn flat dense icon="eva-copy-outline" color="primary" @click="copyToClipboard(formData.username)" />
                            </div>
                            <q-separator />
                            <div class="row items-center justify-between q-py-sm">
                                <div>
                                    <div class="text-caption text-grey-7">Contraseña</div>
                                    <div class="text-body1 text-bold">{{ showPassword ? formData.password : '••••••••' }}</div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <q-btn flat dense :icon="showPassword ? 'eva-eye-off-outline' : 'eva-eye-outline'" color="grey-7" @click="showPassword = !showPassword" />
                                    <q-btn flat dense icon="eva-copy-outline" color="primary" @click="copyToClipboard(formData.password)" />
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-center q-mt-lg">
                            <q-btn color="primary" style="border-radius: 0.5rem;" @click="finish">
                                <div class="px-10 py-1">
                                    Finalizar
                                </div>
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