<script setup>
import { onMounted, ref } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import TareasTable from '@/components/tareas/TareasTable.vue'
import BasePagination from '@/components/ui/BasePagination.vue'
import TareaFormModal from '@/components/tareas/TareaFormModal.vue'
import ConfirmActionModal from '@/components/ui/ConfirmActionModal.vue'
import { useToastStore } from '@/stores/toasts'
import { useAuthStore } from '@/stores/auth'
import { TareasApi } from '@/lib/api/tareas'
import { PrioridadesApi } from '@/lib/api/prioridades'
import { EtiquetasApi } from '@/lib/api/etiquetas'

const auth = useAuthStore()
const toasts = useToastStore()

const items = ref([])
const prioridades = ref([])
const etiquetas = ref([])

const loading = ref(true)

const busqueda = ref('')

const filtros = ref({
  estado: '',
  prioridad_id: '',
  etiqueta_id: '',
  fecha_vencimiento: '',
  ordenar_por: 'id',
  direccion: 'desc',
})

const paginacion = ref({
  pagina_actual: 1,
  por_pagina: 5,
  total: 0,
  ultima_pagina: 1,
  desde: 0,
  hasta: 0,
})

const showFilters = ref(false)

const modalOpen = ref(false)
const selectedId = ref(null)
const viewMode = ref(false)

const confirmDeleteOpen = ref(false)
const deleting = ref(false)
const selectedItemToDelete = ref(null)

async function initCatalogos() {
  try {
    const [resPrioridades, resEtiquetas] = await Promise.all([
      PrioridadesApi.list(),
      EtiquetasApi.list(),
    ])

    prioridades.value = resPrioridades?.data ?? []
    etiquetas.value = resEtiquetas?.data ?? []
  } catch {
    prioridades.value = []
    etiquetas.value = []
  }
}

async function fetchTareas() {
  loading.value = true

  try {
    const params = {
      pagina: paginacion.value.pagina_actual,
      por_pagina: paginacion.value.por_pagina,
      busqueda: busqueda.value || undefined,
      estado: filtros.value.estado || undefined,
      etiqueta_id: filtros.value.etiqueta_id || undefined,
      prioridad_id: filtros.value.prioridad_id || undefined,
      fecha_vencimiento: filtros.value.fecha_vencimiento || undefined,
      ordenar_por: filtros.value.ordenar_por,
      direccion: filtros.value.direccion,
    }

    const res = await TareasApi.list(params)

    items.value = res?.data ?? []

    paginacion.value = {
      pagina_actual: Number(res?.paginacion?.pagina_actual ?? 1),
      por_pagina: Number(res?.paginacion?.por_pagina ?? 10),
      total: Number(res?.paginacion?.total ?? 0),
      ultima_pagina: Number(res?.paginacion?.ultima_pagina ?? 1),
      desde: Number(res?.paginacion?.desde ?? 0),
      hasta: Number(res?.paginacion?.hasta ?? 0),
    }
  } catch (error) {
    toasts.error(error?.message || 'No se pudieron cargar las tareas.')
  } finally {
    loading.value = false
  }
}

function updateSearch(value) {
  busqueda.value = value
  paginacion.value.pagina_actual = 1
  fetchTareas()
}

function updateFilters(value) {
  filtros.value = value
  paginacion.value.pagina_actual = 1
  fetchTareas()
}

function updateShowFilters(value) {
  showFilters.value = value
}

function resetFilters() {
  busqueda.value = ''
  filtros.value = {
    estado: '',
    prioridad_id: '',
    etiqueta_id: '',
    fecha_vencimiento: '',
    ordenar_por: 'id',
    direccion: 'desc',
  }
  paginacion.value.pagina_actual = 1
  fetchTareas()
}

function updateSort(column) {
  if (filtros.value.ordenar_por === column) {
    filtros.value.direccion =
      filtros.value.direccion === 'asc' ? 'desc' : 'asc'
  } else {
    filtros.value.ordenar_por = column
    filtros.value.direccion = 'asc'
  }

  paginacion.value.pagina_actual = 1
  fetchTareas()
}

async function updatePage(value) {
  if (value < 1 || value > paginacion.value.ultima_pagina) return
  paginacion.value.pagina_actual = value
  await fetchTareas()
}

async function updatePerPage(value) {
  paginacion.value.por_pagina = value
  paginacion.value.pagina_actual = 1
  await fetchTareas()
}

function openCreate() {
  if (!auth.canEditTasks) return
  selectedId.value = null
  viewMode.value = false
  modalOpen.value = true
}

function openView(id) {
  selectedId.value = id
  viewMode.value = true
  modalOpen.value = true
}

function openEdit(id) {
  if (!auth.canEditTasks) return
  selectedId.value = id
  viewMode.value = false
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  selectedId.value = null
  viewMode.value = false
}

async function handleSaved() {
  closeModal()
  await fetchTareas()
}

function handleDelete(item) {
  if (!auth.canEditTasks) return
  selectedItemToDelete.value = item
  confirmDeleteOpen.value = true
}

function closeDeleteModal() {
  if (deleting.value) return
  confirmDeleteOpen.value = false
  selectedItemToDelete.value = null
}

async function confirmDelete() {
  if (!auth.canEditTasks) return
  if (!selectedItemToDelete.value?.id) return

  deleting.value = true

  try {
    const res = await TareasApi.remove(selectedItemToDelete.value.id)

    confirmDeleteOpen.value = false
    selectedItemToDelete.value = null

    toasts.success(res?.message || 'Tarea eliminada correctamente')
    await fetchTareas()
  } catch (error) {
    toasts.error(error?.message || 'No se pudo eliminar la tarea')
  } finally {
    deleting.value = false
  }
}

async function handleChangePriority({ id, prioridad_id }) {
  if (!auth.canEditTasks) return

  try {
    const res = await TareasApi.changePriority(id, { prioridad_id })
    toasts.success(res?.message || 'Prioridad actualizada')
    await fetchTareas()
  } catch (error) {
    toasts.error(error?.message || 'No se pudo actualizar la prioridad')
  }
}

async function handleChangeStatus({ id, estado }) {
  if (!auth.canEditTasks) return

  try {
    const res = await TareasApi.changeStatus(id, { estado })
    toasts.success(res?.message || 'Estado actualizado')
    await fetchTareas()
  } catch (error) {
    toasts.error(error?.message || 'No se pudo actualizar el estado')
  }
}

onMounted(async () => {
  await initCatalogos()
  await fetchTareas()
})
</script>

<template>
  <section class="space-y-4">
    <div class="flex items-center justify-end">
      <BaseButton v-if="auth.canEditTasks" @click="openCreate">
        Nueva tarea
      </BaseButton>
    </div>

    <TareasTable
      :items="items"
      :prioridades="prioridades"
      :etiquetas="etiquetas"
      :loading="loading"
      :page="paginacion.pagina_actual"
      :per-page="paginacion.por_pagina"
      :total="paginacion.total"
      :search="busqueda"
      :filters="filtros"
      :show-filters="showFilters"
      :can-edit="auth.canEditTasks"
      @update:search="updateSearch"
      @update:filters="updateFilters"
      @update:showFilters="updateShowFilters"
      @resetFilters="resetFilters"
      @sort="updateSort"
      @view="openView"
      @edit="openEdit"
      @delete="handleDelete"
      @change-priority="handleChangePriority"
      @change-status="handleChangeStatus"
      @labels-updated="fetchTareas"
    />

    <BasePagination
      :page="paginacion.pagina_actual"
      :per-page="paginacion.por_pagina"
      :total="paginacion.total"
      @update:page="updatePage"
      @update:perPage="updatePerPage"
    />

    <TareaFormModal
      :open="modalOpen"
      :tarea-id="selectedId"
      :view-mode="viewMode"
      :prioridades="prioridades"
      :etiquetas="etiquetas"
      @close="closeModal"
      @saved="handleSaved"
    />

    <ConfirmActionModal
      :open="confirmDeleteOpen"
      :is-loading="deleting"
      title="Eliminar tarea"
      :message="`Se eliminará la tarea '${selectedItemToDelete?.titulo || ''}'. Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      cancel-text="Cancelar"
      @close="closeDeleteModal"
      @confirm="confirmDelete"
    />
  </section>
</template>