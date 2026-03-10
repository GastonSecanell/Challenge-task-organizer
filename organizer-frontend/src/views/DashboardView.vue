<script setup>
import { computed, onMounted, ref } from 'vue'
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

const favorites = computed(() => boards.value.filter((b) => !!b.is_favorite))

const ownedNotFav = computed(() =>
  boards.value.filter((b) => !!b.is_owner && !b.is_favorite)
)

const invitedNotFav = computed(() =>
  boards.value.filter((b) => !b.is_owner && !b.is_favorite)
)

function isSaving(id) {
  return !!savingById.value[Number(id)]
}

async function load() {
  isLoading.value = true
  error.value = null
  try {
    const json = await BoardsApi.list()
    boards.value = json?.data ?? []
  } catch (e) {
    error.value = e?.message ?? String(e)
  } finally {
    isLoading.value = false
  }
}

function openBoard(b) {
  if (isSaving(b?.id)) return
  router.push(`/boards/${b.id}`)
}

async function toggleFavorite(b, ev) {
  ev?.stopPropagation?.()

  const id = Number(b?.id)
  if (!id || isSaving(id)) return

  const next = !Boolean(b.is_favorite)
  savingById.value = { ...savingById.value, [id]: true }

  boards.value = boards.value.map((x) =>
    Number(x.id) === id ? { ...x, is_favorite: next } : x
  )

  try {
    await BoardsApi.setFavorite(id, next)
  } catch (e) {
    boards.value = boards.value.map((x) =>
      Number(x.id) === id ? { ...x, is_favorite: !next } : x
    )
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo actualizar favorito.' })
  } finally {
    const { [id]: _ignored, ...rest } = savingById.value
    savingById.value = rest
  }
}

async function archiveBoard(b, ev) {
  ev?.stopPropagation?.()
  if (!auth.canManageBoards) return

  const id = Number(b?.id)
  if (!id || isSaving(id)) return

  savingById.value = { ...savingById.value, [id]: true }

  try {
    await BoardsApi.archive(id)
    boards.value = boards.value.filter((x) => Number(x.id) !== id)
    toasts.push({ type: 'info', message: 'Proyecto archivado.' })
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo archivar.' })
  } finally {
    const { [id]: _ignored, ...rest } = savingById.value
    savingById.value = rest
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <div class="page__header">
      <div>
        <div class="page__title">Dashboard</div>
        <div class="page__subtitle">Tus proyectos</div>
      </div>
    </div>

    <div v-if="error" class="alert alert--danger">
      {{ error }}
    </div>

    <div v-if="isLoading" class="dashboard-loading">
      <md-circular-progress
        indeterminate
        class="dashboard-loading__spinner"
      ></md-circular-progress>
      <!-- <div class="dashboard-loading__text">Cargando proyectos…</div> -->
    </div>

    <div
      v-else-if="boards.length === 0"
      class="empty-state"
    >
      No tenés proyectos asignados.
    </div>

    <!-- FAVORITOS -->
    <template v-if="!isLoading && favorites.length">
      <div class="section-title">Favoritos</div>

      <div class="project-grid">
        <div
          v-for="b in favorites"
          :key="b.id"
          class="project-card"
          :class="{ 'project-card--saving': isSaving(b.id) }"
          role="button"
          tabindex="0"
          @click="openBoard(b)"
        >
          <div class="project-card__overlay" v-if="isSaving(b.id)">
            <md-circular-progress
              indeterminate
              class="md-spinner-inline-md"
            ></md-circular-progress>
          </div>

          <div class="project-card__top">
            <div class="project-card__name">
              {{ b.name }}
            </div>

            <div class="project-card__actions">
              <md-icon-button
                type="button"
                title="Quitar de favoritos"
                :disabled="isSaving(b.id)"
                @click="(ev) => toggleFavorite(b, ev)"
              >
                <i class="mdi mdi-star"></i>
              </md-icon-button>

              <md-icon-button
                v-if="auth.canManageBoards"
                type="button"
                title="Archivar"
                :disabled="isSaving(b.id)"
                @click="(ev) => archiveBoard(b, ev)"
              >
                <i class="mdi mdi-archive-outline"></i>
              </md-icon-button>
            </div>
          </div>

          <div class="project-card__hint">
            Abrir proyecto
          </div>
        </div>
      </div>
    </template>

    <!-- PROPIOS -->
    <template v-if="!isLoading && ownedNotFav.length">
      <div class="section-title">Propios</div>

      <div class="project-grid">
        <div
          v-for="b in ownedNotFav"
          :key="b.id"
          class="project-card"
          :class="{ 'project-card--saving': isSaving(b.id) }"
          role="button"
          tabindex="0"
          @click="openBoard(b)"
        >
          <div class="project-card__overlay" v-if="isSaving(b.id)">
            <md-circular-progress
              indeterminate
              class="md-spinner-inline-md"
            ></md-circular-progress>
          </div>

          <div class="project-card__top">
            <div class="project-card__name">
              {{ b.name }}
            </div>

            <div class="project-card__actions">
              <md-icon-button
                type="button"
                title="Marcar favorito"
                :disabled="isSaving(b.id)"
                @click="(ev) => toggleFavorite(b, ev)"
              >
                <i class="mdi mdi-star-outline"></i>
              </md-icon-button>

              <md-icon-button
                v-if="auth.canManageBoards"
                type="button"
                title="Archivar"
                :disabled="isSaving(b.id)"
                @click="(ev) => archiveBoard(b, ev)"
              >
                <i class="mdi mdi-archive-outline"></i>
              </md-icon-button>
            </div>
          </div>

          <div class="project-card__hint">
            Abrir proyecto
          </div>
        </div>
      </div>
    </template>

    <!-- INVITADOS -->
    <template v-if="!isLoading && invitedNotFav.length">
      <div class="section-title">Invitados</div>

      <div class="project-grid">
        <div
          v-for="b in invitedNotFav"
          :key="b.id"
          class="project-card"
          :class="{ 'project-card--saving': isSaving(b.id) }"
          role="button"
          tabindex="0"
          @click="openBoard(b)"
        >
          <div class="project-card__overlay" v-if="isSaving(b.id)">
            <md-circular-progress
              indeterminate
              class="md-spinner-inline-md"
            ></md-circular-progress>
          </div>

          <div class="project-card__top">
            <div class="project-card__name">
              {{ b.name }}
            </div>

            <div class="project-card__actions">
              <md-icon-button
                type="button"
                title="Marcar favorito"
                :disabled="isSaving(b.id)"
                @click="(ev) => toggleFavorite(b, ev)"
              >
                <i class="mdi mdi-star-outline"></i>
              </md-icon-button>
            </div>
          </div>

          <div class="project-card__hint">
            Abrir proyecto
          </div>
        </div>
      </div>
    </template>
  </div>
</template>