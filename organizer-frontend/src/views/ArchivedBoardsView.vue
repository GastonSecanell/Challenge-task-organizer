<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toasts'
import { BoardsApi } from '@/lib/api/boards'

const auth = useAuthStore()
const toasts = useToastStore()
const router = useRouter()

const boards = ref([])
const isLoading = ref(false)
const error = ref(null)
const savingById = ref({})

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const json = await BoardsApi.listArchived()
    boards.value = json.data ?? []
  } catch (e) {
    error.value = e?.message ?? String(e)
  } finally {
    isLoading.value = false
  }
}

function openBoard(b) {
  router.push(`/boards/${b.id}`)
}

async function unarchiveBoard(b, ev) {
  ev?.stopPropagation?.()
  if (!auth.canManageBoards) return

  const id = Number(b.id)
  savingById.value = { ...savingById.value, [id]: true }

  try {
    await BoardsApi.unarchive(id)
    boards.value = boards.value.filter((x) => Number(x.id) !== id)
    toasts.push({ type: 'success', message: 'Proyecto restaurado.' })
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo restaurar.' })
  } finally {
    const { [id]: _, ...rest } = savingById.value
    savingById.value = rest
  }
}

async function deleteBoardForever(b, ev) {
  ev?.stopPropagation?.()
  if (!auth.canManageBoards) return

  const ok = confirm(`Eliminar definitivamente "${b.name}"? Esta acción no se puede deshacer.`)
  if (!ok) return

  const id = Number(b.id)
  savingById.value = { ...savingById.value, [id]: true }

  try {
    await BoardsApi.destroy(id)
    boards.value = boards.value.filter((x) => Number(x.id) !== id)
    toasts.push({ type: 'info', message: 'Proyecto eliminado.' })
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo eliminar.' })
  } finally {
    const { [id]: _, ...rest } = savingById.value
    savingById.value = rest
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <div class="page__header">
      <div>
        <div class="page__title">Archivados</div>
        <div class="page__subtitle">Proyectos ocultos</div>
      </div>
      <md-text-button type="button" :disabled="isLoading" @click="load">
        <i class="mdi mdi-refresh" aria-hidden="true"></i>
        <span>Actualizar</span>
        <md-circular-progress v-if="isLoading" indeterminate class="md-spinner-inline"></md-circular-progress>
      </md-text-button>
    </div>

    <div v-if="error" class="alert alert--danger">{{ error }}</div>
    <div v-if="!isLoading && boards.length === 0" class="empty-state">No hay proyectos archivados.</div>

    <div class="project-grid">
      <div v-for="b in boards" :key="b.id" class="project-card project-card--disabled" aria-disabled="true">
        <div class="project-card__top">
          <div class="project-card__name">{{ b.name }}</div>
          <div class="project-card__actions">
            <md-icon-button
              v-if="auth.canManageBoards"
              type="button"
              title="Restaurar"
              :disabled="!!savingById[b.id]"
              @click="(ev) => unarchiveBoard(b, ev)"
            >
              <i class="mdi mdi-archive-arrow-up-outline" aria-hidden="true"></i>
            </md-icon-button>
            <md-icon-button
              v-if="auth.canManageBoards"
              type="button"
              title="Eliminar definitivamente"
              :disabled="!!savingById[b.id]"
              @click="(ev) => deleteBoardForever(b, ev)"
            >
              <i class="mdi mdi-delete-outline" aria-hidden="true"></i>
            </md-icon-button>
          </div>
        </div>
        <div class="project-card__hint">Archivado · Restaurá para abrir</div>
      </div>
    </div>
  </div>
</template>

