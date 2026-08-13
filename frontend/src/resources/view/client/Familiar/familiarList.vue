<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useUserStore } from '@/services/store/users.store';
import { useQuasar, Notify } from 'quasar';
import iconsApp from '@/assets/icons/index'
import userAvailableAreasStep from '@/components/admin/userAvailableAreasStep.vue';

const $q = useQuasar()
const userStore = useUserStore()
const page = ref(1)
const search = ref('')
const ready = ref(false)

const router = useRouter()

const goTo = (url) => {
    router.push(url)
}

const residents = ref([])

const areasUser = ref({})
const areasDialog = ref(false)
const areasLoading = ref(false)
const selectedAreas = ref([])

const openAreas = (user) => {
    selectedAreas.value = []
    areasUser.value = user
    areasDialog.value = true
}

const saveAreas = () => {
    areasLoading.value = true
    userStore.setAvailableComunaAreas(areasUser.value.id, selectedAreas.value)
        .then(() => {
            Notify.create({ color: 'positive', message: 'Áreas comunes actualizadas correctamente', timeout: 2000 })
            areasDialog.value = false
        })
        .catch((error) => {
            Notify.create({ color: 'negative', message: error || 'Error al actualizar las áreas comunes', timeout: 2000 })
        })
        .finally(() => {
            areasLoading.value = false
        })
}

const getUsers = () => {

    ready.value = false;

    userStore.getResidents()
        .then((response) => {
            if (response.code !== 200) throw response
            residents.value = response.data;
            setTimeout(() => {
                ready.value = true;
            }, 1000);
        })
        .catch(() => {
        })
}

const confirmDelete = (item) => {
    $q.dialog({
        title: 'Eliminar usuario',
        message: `¿Estás seguro de eliminar a ${item.user?.name}? Sus reservas pendientes serán canceladas.`,
        cancel: {
            label: 'Cancelar',
            flat: true,
            color: 'grey-7'
        },
        ok: {
            label: 'Eliminar',
            color: 'negative',
            unelevated: true,
        },
        persistent: true,
    }).onOk(() => {
        userStore.deleteUser(item.user.id)
            .then(() => {
                $q.notify({
                    color: 'positive',
                    message: 'Usuario eliminado correctamente',
                    timeout: 2000,
                })
                getUsers()
            })
            .catch((error) => {
                $q.notify({
                    color: 'negative',
                    message: error || 'Error al eliminar usuario',
                    timeout: 2000,
                })
            })
    })
}

onMounted(() => {
    getUsers()
})
</script>

<template>
    <div class="h-full">
        <template v-if="ready">
            <div class="h-full" style="overflow: hidden;">
                <template v-if="residents.length > 0">
                    <div class="mt-4 md:mt-8" style="height:85%; overflow: auto;">
                        <div class="px-4 md:mx-24">
                            <div v-for="item in residents" :key="item.id"
                                class="md:py-4 py-3 mb-5 userListContainer flex items-center justify-between">
                                <div class="flex items-center pb-3 pl-2 md:pl-5 ">
                                    <div style="height: 2.8rem; width: 2.8rem; background: #b5b5b5; border-radius: 0.5rem; font-size: 2rem; font-weight: bold;"
                                        class="flex flex-center text-white">
                                        {{ item.user?.name?.charAt(0)?.toUpperCase() || '?' }}
                                    </div>
                                    <div class="ml-2">
                                        <div class="text-subtitle1  text-bold text-black" style="line-height:1.7;">
                                            {{ item.user?.name }}
                                        </div>
                                        <div class="flex items-center">
                                            <div class="text-body2 text-grey-6 ">#
                                                {{ item.departament?.number || 'Apt. no asignado' }}</div>
                                            <div class="text-caption text-grey-6 ml-1">
                                                ({{ item.type_label || item.user?.rol?.name }})
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex justify-end  items-center pb-3 pt-1 pr-2 md:pr-5 ">
                                    <template v-if="item.user?.status == 2">
                                        <div>
                                            <div class="pt-1" v-html="iconsApp.cancelHouse" />
                                            <q-tooltip transition-show="flip-right" transition-hide="flip-left"
                                                class="bg-black text-body2 px-2">
                                                Moroso
                                            </q-tooltip>
                                        </div>
                                    </template>
                                </div>
                                <div class="flex justify-end px-2 w-full pt-3 "
                                    style="border-top: 1px solid lightgrey;">
                                    <div>
                                        <q-btn icon="eva-settings-outline" class="mx-1" color="primary" flat
                                            size="0.9rem" @click="goTo('/client/familiar/edit/' + item.user?.id)">
                                            <q-tooltip transition-show="flip-right" transition-hide="flip-left"
                                                :class="'bg-black text-body2 px-2'">
                                                Editar usuario
                                            </q-tooltip>
                                        </q-btn>
                                    </div>
                                    <div>
                                        <q-btn icon="eva-calendar-outline" class="mx-1" color="light-green-9"
                                            flat size="0.9rem"
                                            @click="goTo('/client/familiar/' + item.user?.id + '/bookings')">
                                            <q-tooltip transition-show="flip-right" transition-hide="flip-left"
                                                class="bg-black text-body2 px-2">
                                                Ver reservas
                                            </q-tooltip>
                                        </q-btn>
                                    </div>
                                    <div>
                                        <q-btn icon="eva-grid-outline" class="mx-1" flat color="teal"
                                            size="0.9rem" @click="openAreas(item.user)">
                                            <q-tooltip transition-show="flip-right" transition-hide="flip-left"
                                                :class="'bg-black text-body2 px-2'">
                                                Áreas que puede reservar
                                            </q-tooltip>
                                        </q-btn>
                                    </div>
                                    <div>
                                        <q-btn icon="eva-trash-2-outline" class="mx-1" color="negative" flat
                                            size="0.9rem" @click="confirmDelete(item)">
                                            <q-tooltip transition-show="flip-right" transition-hide="flip-left"
                                                class="bg-black text-body2 px-2">
                                                Borrar usuario
                                            </q-tooltip>
                                        </q-btn>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="px-4 md:px-0 md:flex md:mx-auto md:justify-end md:w-5/6" style="height:10%">
                        <q-btn color="primary" unelevated class="w-full mt-5 md:mt-0 md:mb-5 md:mx-5 createButton "
                            style="border-radius: 0.5rem;" @click="goTo('/client/familiar/add')">
                            <div class="flex items-center py-1">
                                <q-icon name="eva-plus-outline" />
                                <div class=" text-bold pl-1">
                                    Registrar familiar/residente
                                </div>
                            </div>
                        </q-btn>
                    </div>
                </template>
                <template v-else>
                    <div class="flex flex-center column empty-results px-4" style="min-height: 60vh;">
                        <q-icon name="eva-people-outline" size="4rem" color="grey-5" class="q-mb-md" />
                        <div style="font-size: 1.2rem; font-weight: 600;" class=" text-grey-7 text-center q-mb-sm">
                            No hay usuarios registrados
                        </div>
                        <div class=" text-grey-6 text-center q-mb-lg">
                            Crea cuentas para familiares, inquilinos o huéspedes temporales y permíteles acceder a la aplicación para gestionar sus propias reservas.
                        </div>
                        <q-btn color="primary" unelevated style="border-radius: 0.5rem;"
                            @click="goTo('/client/familiar/add')">
                            <q-icon name="eva-plus-outline" class="q-mr-sm" />
                            Registrar nuevo
                        </q-btn>
                    </div>
                </template>
            </div>
        </template>
        <template v-else>
            <div class="h-full flex flex-center" style="overflow: auto;">
                <q-spinner-dots color="primary" size="7rem" />
            </div>
        </template>
    </div>
    <q-dialog v-model="areasDialog">
        <q-card style="max-width: 40rem; width: 100%;" class="q-pa-md">
            <q-card-section>
                <div class="text-h6 text-primary text-bold">
                    Áreas que puede reservar
                </div>
                <div class="text-body2 text-grey-7 q-mt-xs">
                    {{ areasUser.name }} — Si no seleccionas ninguna área, el usuario podrá reservar todas las disponibles.
                </div>
            </q-card-section>
            <q-card-section class="scroll" style="max-height: 55vh;">
                <userAvailableAreasStep v-model="selectedAreas" :userId="areasUser.id" />
            </q-card-section>
            <q-card-actions align="right" class="q-px-md q-pb-md">
                <q-btn flat color="grey-7" @click="areasDialog = false">
                    Cancelar
                </q-btn>
                <q-btn color="primary" :loading="areasLoading" @click="saveAreas">
                    Guardar
                </q-btn>
            </q-card-actions>
        </q-card>
    </q-dialog>
</template>
<style lang="scss">
.userListContainer {
    overflow: hidden;
    border-radius: .5rem;
    box-shadow: 0px 2px 6px 0px rgb(199, 199, 199);
}

.createButton {
    width: auto;
}

.tabItem {
    opacity: 0.5;
    cursor: pointer;
    height: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    transition: all 0.2s ease-out;

    &:hover {
        background: #279edb !important;
    }

    &.active {
        opacity: 1;
    }

    &.leftItem {
        border-top-left-radius: 0.7rem;
        border-bottom-left-radius: 0.7rem;

    }

    &.rightItem {
        border-top-right-radius: 0.7rem;
        border-bottom-right-radius: 0.7rem;

    }
}

@media (max-width: 780px) {
    .createButton {
        width: 100%;
    }
}
</style>
