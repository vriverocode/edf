<script setup>
import { onMounted, ref } from 'vue'
import { Notify, Dialog } from 'quasar'
import { useProviderStore } from '@/services/store/provider.store'
import { useServiceCategoryStore } from '@/services/store/serviceCategory.store'
import createProviderModal from '@/components/finance/createProviderModal.vue'

const providerStore = useProviderStore()
const serviceCategoryStore = useServiceCategoryStore()

const loading = ref(false)
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

const notify = (type, msg) => Notify.create({ color: type, message: msg, timeout: 2200 })

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
  }
}

const onSearchInput = (val) => {
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
      notify('negative', err?.error || err?.message || 'Error al eliminar')
    }
  })
}

const onProviderCreated = (created) => {
  if (created?.id) loadProviders()
}

onMounted(() => {
  loadProviders()
})
</script>

<template>
  <div class="q-pa-md">
    <div class="text-h5 text-bold q-mb-md">Proveedores</div>

    <div class="row items-center q-mb-md q-gutter-x-sm">
      <q-input
        v-model="search"
        dense outlined placeholder="Buscar proveedor..."
        class="col"
        style="max-width: 360px"
        @update:model-value="onSearchInput"
      >
        <template #prepend><q-icon name="eva-search-outline" /></template>
      </q-input>
      <q-space />
      <q-btn color="primary" unelevated icon="eva-plus-outline" label="Nuevo proveedor" @click="createDialog = true" />
    </div>

    <q-table
      :rows="providers"
      :columns="[
        { name: 'name', label: 'Nombre', field: 'name', align: 'left' },
        { name: 'tax_id', label: 'RUC', field: 'tax_id', align: 'left' },
        { name: 'category', label: 'Categoría', field: r => r.service_category?.name || '—', align: 'left' },
        { name: 'phone', label: 'Teléfono', field: r => r.phone || '—', align: 'left' },
        { name: 'email', label: 'Email', field: r => r.email || '—', align: 'left' },
        { name: 'actions', label: 'Acciones', field: '', align: 'center' }
      ]"
      row-key="id"
      :loading="loading"
      :pagination="{ rowsPerPage: 0 }"
      flat bordered
      hide-pagination
    >
      <template #body-cell-actions="{ row }">
        <q-td>
          <q-btn flat dense round color="primary" icon="eva-edit-outline" @click="openEdit(row)">
            <q-tooltip>Editar</q-tooltip>
          </q-btn>
          <q-btn flat dense round color="negative" icon="eva-trash-2-outline" @click="confirmDelete(row)">
            <q-tooltip>Eliminar</q-tooltip>
          </q-btn>
        </q-td>
      </template>
    </q-table>

    <div v-if="lastPage > 1" class="flex justify-center q-mt-md">
      <q-pagination v-model="page" color="primary" :max="lastPage" :max-pages="5"
        @update:model-value="loadProviders" />
    </div>

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
      :service-categories="[]"
      @close-modal="createDialog = false"
      @created="onProviderCreated"
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
