<script setup>
import { onMounted, ref } from 'vue'
import { Notify, Dialog } from 'quasar'
import { useProviderStore } from '@/services/store/provider.store'
import { useServiceCategoryStore } from '@/services/store/serviceCategory.store'
import createProviderModal from '@/components/finance/createProviderModal.vue'

const providerStore = useProviderStore()
const serviceCategoryStore = useServiceCategoryStore()

const loading = ref(false)
const ready = ref(false)
const providers = ref([])
const page = ref(1)
const lastPage = ref(1)
const search = ref('')
const searchTimeout = ref(null)

const editDialog = ref(false)
const editingProvider = ref(null)
const editForm = ref({ name: '', tax_id: '', service_category_id: null, contact_name: '', phone: '', email: '' })
const editLoading = ref(false)
const categoryOptions = ref([])

const createDialog = ref(false)
const serviceCategoryList = ref([])

const notify = (type, msg) => Notify.create({ color: type, message: msg, timeout: 2200 })

const loadServiceCategories = async () => {
  try {
    const res = await serviceCategoryStore.getServiceCategories()
    if (res?.code === 200 && Array.isArray(res.data)) {
      serviceCategoryList.value = res.data
    }
  } catch {
    serviceCategoryList.value = []
  }
}

const loadProviders = async () => {
  loading.value = true
  try {
    const res = await providerStore.getProviders({ page: page.value, per_page: 15, search: search.value || undefined })
    if (res?.code !== 200) throw res
    const pag = res.data?.pagination || {}
    providers.value = pag.data || []
    lastPage.value = pag.last_page || 1
  } catch (err) {
    notify('negative', err?.error || err?.message || 'Error al cargar proveedores')
  } finally {
    loading.value = false
    setTimeout(() => { ready.value = true }, 100)
  }
}

const onSearchInput = () => {
  clearTimeout(searchTimeout.value)
  searchTimeout.value = setTimeout(() => {
    page.value = 1
    loadProviders()
  }, 400)
}

const openEdit = (provider) => {
  editingProvider.value = provider
  editForm.value = {
    name: provider.name || '',
    tax_id: provider.tax_id || '',
    service_category_id: provider.service_category_id || null,
    contact_name: provider.contact_name || '',
    phone: provider.phone || '',
    email: provider.email || ''
  }
  loadCategoryOptions()
  editDialog.value = true
}

const loadCategoryOptions = async () => {
  try {
    const res = await serviceCategoryStore.getServiceCategories()
    if (res?.code === 200 && Array.isArray(res.data)) {
      categoryOptions.value = res.data.map(c => ({ label: c.name, value: c.id }))
    }
  } catch {
    categoryOptions.value = []
  }
}

const saveEdit = async () => {
  if (!editForm.value.name?.trim()) { notify('warning', 'El nombre es requerido'); return }
  if (!editForm.value.tax_id?.trim()) { notify('warning', 'El RUC es requerido'); return }
  editLoading.value = true
  try {
    const res = await providerStore.updateProvider(editingProvider.value.id, editForm.value)
    if (res?.code !== 200) throw res
    notify('positive', 'Proveedor actualizado')
    editDialog.value = false
    editingProvider.value = null
    loadProviders()
  } catch (err) {
    notify('negative', err?.error || err?.message || 'Error al actualizar')
  } finally {
    editLoading.value = false
  }
}

const confirmDelete = (provider) => {
  Dialog.create({
    title: 'Eliminar proveedor',
    message: `¿Eliminar a <b>${provider.name}</b>?`,
    html: true,
    cancel: true,
    ok: { color: 'negative', label: 'Eliminar' }
  }).onOk(async () => {
    try {
      const res = await providerStore.deleteProvider(provider.id)
      if (res?.code !== 200) throw res
      notify('positive', 'Proveedor eliminado')
      loadProviders()
    } catch (err) {
      console.log(err)
      notify('negative', err || err?.error || err?.message || 'Error al eliminar')
    }
  })
}

const onProviderCreated = (created) => {
  if (created?.id) loadProviders()
}

onMounted(() => {
  loadProviders()
  loadServiceCategories()
})
</script>

<template>
  <div class="h-full" style="overflow: hidden;">
    <!-- Header: 15% -->
    <div class="px-4 md:px-28 flex items-center" style="height: 25%;">
      <div class="row items-center w-full q-col-gutter-sm">
        <div class="col-12 col-md-6">
          <q-input v-model="search" dense borderless clearable placeholder="Buscar proveedor..."
            class="form__inputsR" @update:model-value="onSearchInput">
            <template v-slot:prepend>
              <q-icon name="eva-search-outline" />
            </template>
          </q-input>
        </div>
        <div class="col-12 col-md-6">
          <q-btn color="primary" class="w-full" unelevated icon="eva-plus-outline" @click="createDialog = true">
            <div class="flex items-center py-1 q-ml-xs">
              <span class="text-bold">Nuevo proveedor</span>
            </div>
          </q-btn>
        </div>
      </div>
    </div>

    <!-- Content: 85% with scroll -->
    <div style="height: 75%; overflow: auto;">

      <!-- Loading -->
      <div v-if="!ready" class="flex justify-center items-center py-20">
        <q-spinner-dots color="primary" size="7rem" />
      </div>

      <!-- Content -->
      <div v-else class="px-4 md:px-28 pt-2">
        <div v-if="providers.length > 0" class="space-y-3 md:px-5">
          <div v-for="provider in providers" :key="provider.id"
            class="bg-white rounded-xl shadow-md border border-gray-100 overflow-hidden md:mb-5"
            style="position: relative;">

            <div class="pb-4 pt-2 border-b border-dashed border-gray-300">
              <div class="flex justify-between items-start mb-2 px-4">
                <div class="flex-1">
                  <h3 class="text-lg font-bold text-gray-900 mb-0">
                    {{ provider.name }}
                  </h3>
                  <div class="text-caption text-grey-7 mt-1">
                    RUC: {{ provider.tax_id }}
                  </div>
                </div>
                <span class="inline-block px-3 py-2 text-xs font-bold text-white"
                  :class="provider.status == 1 ? 'bg-positive' : 'bg-grey-5'"
                  style="border-bottom-left-radius: 0.5rem;">
                  {{ provider.status == 1 ? 'Activo' : 'Inactivo' }}
                </span>
              </div>

              <div class="px-4 text-sm text-gray-700">
                <div class="mb-1">
                  <strong>Categoría:</strong> {{ provider.category?.name || '—' }}
                </div>
                <div class="mb-1">
                  <strong>Contacto:</strong> {{ provider.contact_name || '—' }}
                </div>
                <div class="flex gap-4">
                  <div v-if="provider.phone"><strong>Tel:</strong> {{ provider.phone }}</div>
                  <div v-if="provider.email"><strong>Email:</strong> {{ provider.email }}</div>
                </div>
              </div>
            </div>

            <div class="py-2 px-4 bg-gray-50">
              <div class="flex justify-end items-center gap-1">
                <q-btn flat dense round color="primary" icon="eva-edit-outline" size="sm"
                  @click="openEdit(provider)">
                  <q-tooltip>Editar</q-tooltip>
                </q-btn>
                <q-btn flat dense round color="negative" icon="eva-trash-2-outline" size="sm"
                  @click="confirmDelete(provider)">
                  <q-tooltip>Eliminar</q-tooltip>
                </q-btn>
              </div>
            </div>
          </div>

          <!-- Pagination -->
          <div v-if="lastPage > 1" class="flex justify-center q-mt-md q-mb-lg">
            <q-pagination v-model="page" color="primary" :max="lastPage" :max-pages="5"
              @update:model-value="loadProviders" />
          </div>
        </div>

        <!-- Empty state -->
        <div v-else class="flex flex-col items-center justify-center py-20">
          <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mb-6">
            <q-icon name="eva-people-outline" color="primary" size="42px" />
          </div>
          <h3 class="text-lg font-semibold text-gray-900 mb-2">No hay proveedores registrados</h3>
          <p class="text-gray-600 text-center mb-6">Agrega un proveedor para comenzar a registrar gastos.</p>
        </div>
      </div>
    </div>

    <!-- Edit Dialog -->
    <q-dialog v-model="editDialog" @hide="editingProvider = null">
      <q-card style="min-width: min(400px, 92vw);" class="q-pa-md">
        <div class="text-h6 q-mb-sm">Editar proveedor</div>

        <div class="text-subtitle2 text-black">Nombre o razón social</div>
        <q-input v-model="editForm.name" dense borderless clearable class="form__inputsR mt-1" autofocus />

        <div class="text-subtitle2 text-black q-mt-md">RUC / documento tributario</div>
        <q-input v-model="editForm.tax_id" dense borderless clearable class="form__inputsR mt-1" />

        <div class="text-subtitle2 text-black q-mt-md">Categoría de servicio</div>
        <q-select v-model="editForm.service_category_id" :options="categoryOptions"
          option-label="label" option-value="value" emit-value map-options
          dense borderless class="form__inputsR mt-1" />

        <div class="text-subtitle2 text-black q-mt-md">Contacto</div>
        <q-input v-model="editForm.contact_name" dense borderless clearable class="form__inputsR mt-1" />

        <div class="row q-col-gutter-sm q-mt-sm">
          <div class="col-6">
            <div class="text-subtitle2 text-black">Teléfono</div>
            <q-input v-model="editForm.phone" dense borderless clearable class="form__inputsR mt-1" />
          </div>
          <div class="col-6">
            <div class="text-subtitle2 text-black">Correo</div>
            <q-input v-model="editForm.email" dense borderless clearable class="form__inputsR mt-1" />
          </div>
        </div>

        <div class="row justify-end q-gutter-sm q-mt-lg">
          <q-btn flat label="Cancelar" color="grey" no-caps @click="editDialog = false" />
          <q-btn color="primary" label="Guardar" no-caps :loading="editLoading" @click="saveEdit" />
        </div>
      </q-card>
    </q-dialog>

    <createProviderModal
      :dialog="createDialog"
      :service-categories="serviceCategoryList"
      @close-modal="createDialog = false"
      @created="onProviderCreated"
      @category-created="loadServiceCategories"
    />
  </div>
</template>

<style lang="scss" scoped>
.form__inputsR {
  & :deep(.q-field__inner) {
    box-shadow: 0px 3px 4px 0px #bfbfbf48;
    border-radius: 0.5rem;
    border: 1px solid rgb(223, 223, 223);
    padding: 0px 1rem;
  }
}
</style>
