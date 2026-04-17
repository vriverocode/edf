<script setup>
import { inject, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { useUserStore } from '@/services/store/users.store';
import iconsApp from '@/assets/icons/index'

const userStore = useUserStore()
const page = ref(1)
const search = ref('')
const ready = ref(false)
const materialIcons = inject('materialIcons')

const router = useRouter()

const goTo = (url) => {
    router.push(url)
}

const residents = ref([])

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

onMounted(() => {
    getUsers()
})
</script>

<template>
    <div class="h-full">
        <template v-if="ready">
            <div class="h-full" style="overflow: auto;">
                <template v-if="residents.length > 0">
                    <div class="mt-4 md:mt-8" style="height:85%">
                        <div class="px-4 md:mx-24 md:pr-12">
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
                                            size="0.9rem">
                                            <q-tooltip transition-show="flip-right" transition-hide="flip-left"
                                                :class="'bg-black text-body2 px-2'">
                                                Editar usuario
                                            </q-tooltip>
                                        </q-btn>
                                    </div>
                                    <div>
                                        <q-btn :icon="materialIcons.outlinedEvent" class="mx-1" color="light-green-9"
                                            flat size="0.9rem">
                                            <q-tooltip transition-show="flip-right" transition-hide="flip-left"
                                                class="bg-black text-body2 px-2">
                                                Ver reservas
                                            </q-tooltip>
                                        </q-btn>
                                    </div>
                                    <div>
                                        <q-btn icon="eva-trash-2-outline" class="mx-1" color="negative" flat
                                            size="0.9rem">
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
                        <q-btn color="primary" unelevated class="w-full mt-5 md:mx-5 createButton "
                            style="border-radius: 0.5rem;" @click="goTo('/client/familiar/add')">
                            <div class="flex items-center py-1">
                                <q-icon name="eva-plus-outline" />
                                <div class="q-pt-xs text-bold pl-1">
                                    Registrar familiar/residente
                                </div>
                            </div>
                        </q-btn>
                    </div>
                </template>
                <template v-else>
                    <div class="flex flex-center column empty-results px-4" style="min-height: 60vh;">
                        <q-icon name="eva-people-outline" size="4rem" color="grey-5" class="q-mb-md" />
                        <div style="font-size: 1.2rem; font-weight: 600;" class=" text-grey-7 text-center q-mb-sm">No
                            hay
                            familiares ni residentes
                            Airbnb</div>
                        <div class=" text-grey-6 text-center q-mb-lg">Registra familiares o usuarios temporales de
                            alquiler.</div>
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
