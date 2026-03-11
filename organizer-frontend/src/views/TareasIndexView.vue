<script setup>
import { onMounted, ref } from 'vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import TareasTable from '@/components/tareas/TareasTable.vue'
import UsersPagination from '@/components/users/UsersPagination.vue'
import TareaFormModal from '@/components/tareas/TareaFormModal.vue'
import { useToastStore } from '@/stores/toasts'
import { TareasApi } from '@/lib/api/tareas'
import { PrioridadesApi } from '@/lib/api/prioridades'

const toasts = useToastStore()

const items = ref([])
const prioridades = ref([])
const loading = ref(true)

const busqueda = ref('')

const filtros = ref({
  estado: '',
  prioridad_id: '',
  fecha_vencimiento: '',
  ordenar_por: 'id',
  direccion: 'desc',
})

const paginacion = ref({
  pagina_actual: 1,
  por_pagina: 10,
  total: 0,
  ultima_pagina: 1,
  desde: 0,
  hasta: 0,
})

const showFilters = ref(false)

const modalOpen = ref(false)
const selectedId = ref(null)

async function fetchPrioridades() {
  try {
    const res = await PrioridadesApi.list()
    prioridades.value = res?.data ?? []
  } catch {
    prioridades.value = []
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
    fecha_vencimiento: '',
    ordenar_por: 'id',
    direccion: 'desc',
  }
  paginacion.value.pagina_actual = 1
  fetchTareas()
}

function updateSort(column) {
  if (filtros.value.ordenar_por === column) {
    filtros.value.direccion = filtros.value.direccion === 'asc' ? 'desc' : 'asc'
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
  selectedId.value = null
  modalOpen.value = true
}

function openEdit(id) {
  selectedId.value = id
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  selectedId.value = null
}

async function handleSaved() {
  closeModal()
  await fetchTareas()
}

async function handleDelete(item) {
  const ok = window.confirm(`¿Eliminar la tarea "${item.titulo}"?`)
  if (!ok) return

  try {
    await TareasApi.remove(item.id)
    toasts.success('Tarea eliminada correctamente.')
    await fetchTareas()
  } catch (error) {
    toasts.error(error?.message || 'No se pudo eliminar la tarea.')
  }
}

onMounted(async () => {
  await fetchPrioridades()
  await fetchTareas()
})
</script>

<template>
  <section class="space-y-4">
    <div class="flex items-center justify-end">
      <BaseButton @click="openCreate">
        Nueva tarea
      </BaseButton>
    </div>

    <TareasTable
      :items="items"
      :prioridades="prioridades"
      :loading="loading"
      :page="paginacion.pagina_actual"
      :per-page="paginacion.por_pagina"
      :total="paginacion.total"
      :search="busqueda"
      :filters="filtros"
      :show-filters="showFilters"
      @update:search="updateSearch"
      @update:filters="updateFilters"
      @update:showFilters="updateShowFilters"
      @resetFilters="resetFilters"
      @sort="updateSort"
      @edit="openEdit"
      @delete="handleDelete"
    />

    <UsersPagination
      :page="paginacion.pagina_actual"
      :per-page="paginacion.por_pagina"
      :total="paginacion.total"
      @update:page="updatePage"
      @update:perPage="updatePerPage"
    />

    <TareaFormModal
      :open="modalOpen"
      :tarea-id="selectedId"
      @close="closeModal"
      @saved="handleSaved"
    />
  </section>
</template>