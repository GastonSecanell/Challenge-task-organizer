<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toasts'
import { useCardEventsStore } from '@/stores/cardEvents'

import ColumnList from '../components/ColumnList.vue'
import CardModal from '../components/CardModal.vue'
import BoardLabelsModal from '../components/BoardLabelsModal.vue'
import BoardMembersModal from '../components/BoardMembersModal.vue'

// APIS (axios)
import { BoardsApi } from '@/lib/api/boards'
import { CardsApi } from '@/lib/api/cards'
import { ColumnsApi } from '@/lib/api/columns'
import { AttachmentsApi } from '@/lib/api/attachments'

const route = useRoute()
const auth = useAuthStore()
const toasts = useToastStore()
const cardEvents = useCardEventsStore()
const isLoading = ref(false)
const error = ref(null)

const isCreatingColumn = ref(false)
const savingByColumnId = ref({})
const savingByCardId = reactive({})
const deletingByCardId = reactive({})

const coverObjectUrlsByAttachmentId = ref({})
const hasLoadedOnce = ref(false)

const board = ref(null)
const columns = ref([])
const isArchived = computed(() => !!board.value?.archived_at)
const cardEventsVersion = computed(() => cardEvents.version)

const modal = reactive({
  isOpen: false,
  cardId: null,
})

const labelsModalOpen = ref(false)
const membersModalOpen = ref(false)

function openLabelsManager() {
  if (!auth.isAdmin) return
  labelsModalOpen.value = true
}

function openMembersManager() {
  if (!auth.canManageBoards) return
  membersModalOpen.value = true
}

const dragState = reactive({
  cardId: null,
  fromColumnId: null,
})

const moveTimers = new Map()
const cardRefreshTimers = new Map()
const boardId = computed(() => Number(route.params.id))

function sortByPositionAsc(a, b) {
  return (a.position ?? 0) - (b.position ?? 0)
}

// ---------------------------
// Covers
// ---------------------------
function resolveCoverUrl(cardLike) {
  return (
    cardLike?.cover_attachment?.thumb_url ||
    cardLike?.cover_attachment?.preview_url ||
    cardLike?.thumb_url ||
    cardLike?.preview_url ||
    null
  )
}

async function normalizeColumns(cols) {
  const normalized = []

  for (const c of cols ?? []) {
    const cards = []

    for (const card of c.cards ?? []) {
      const cover_image_url = await ensureCoverUrlForCard(card)

      cards.push({
        ...card,
        cover_image_url,
      })
    }

    normalized.push({
      ...c,
      cards: cards.slice().sort(sortByPositionAsc),
    })
  }

  return normalized.slice().sort(sortByPositionAsc)
}

async function fetchProtectedImageObjectUrl(url) {
  const blob = await AttachmentsApi.downloadBlobByUrl(url)
  return URL.createObjectURL(blob)
}

async function ensureCoverUrlForCard(cardLike) {
  const attachmentId =
    cardLike?.cover_attachment?.id
      ? Number(cardLike.cover_attachment.id)
      : cardLike?.cover_attachment_id
        ? Number(cardLike.cover_attachment_id)
        : null

  if (!attachmentId) return null

  if (coverObjectUrlsByAttachmentId.value[attachmentId]) {
    return coverObjectUrlsByAttachmentId.value[attachmentId]
  }

  const rawUrl =
    cardLike?.cover_attachment?.thumb_url ||
    cardLike?.cover_attachment?.preview_url ||
    null

  if (!rawUrl) return null

  try {
    const objectUrl = await fetchProtectedImageObjectUrl(rawUrl)

    coverObjectUrlsByAttachmentId.value = {
      ...coverObjectUrlsByAttachmentId.value,
      [attachmentId]: objectUrl,
    }

    return objectUrl
  } catch (e) {
    console.error('No se pudo cargar portada protegida', attachmentId, rawUrl, e)
    return null
  }
}

// ---------------------------
// Load board
// ---------------------------
async function loadBoard() {
  isLoading.value = true
  error.value = null

  try {
    const json = await BoardsApi.get(boardId.value)
    board.value = json.data
    columns.value = await normalizeColumns(json.data?.columns)
    hasLoadedOnce.value = true
  } catch (e) {
    error.value = e?.message ?? String(e)
  } finally {
    isLoading.value = false
  }
}

function findColumn(colId) {
  return columns.value.find((c) => Number(c.id) === Number(colId))
}

function findCardEntry(cardId) {
  const id = Number(cardId)

  for (const col of columns.value) {
    const idx = (col.cards ?? []).findIndex((c) => Number(c.id) === id)
    if (idx >= 0) {
      return {
        column: col,
        index: idx,
        card: col.cards[idx],
      }
    }
  }

  return null
}

function patchCardLocal(cardId, patch = {}) {
  const entry = findCardEntry(cardId)
  if (!entry) return

  entry.column.cards[entry.index] = {
    ...entry.card,
    ...patch,
  }
}

function computeInsertPosition(destCards, insertIndex) {
  if (!destCards.length) return 100
  if (insertIndex <= 0) return (destCards[0].position ?? 0) - 100
  if (insertIndex >= destCards.length) return (destCards[destCards.length - 1].position ?? 0) + 100
  const prev = destCards[insertIndex - 1].position ?? 0
  const next = destCards[insertIndex].position ?? 0
  return (prev + next) / 2
}

// ---------------------------
// Move card (optimistic + debounce persist)
// ---------------------------
function scheduleMovePersist(cardId, columnId, position) {
  if (isArchived.value) {
    toasts.push({ type: 'info', message: 'Proyecto archivado (solo lectura).', timeoutMs: 2200 })
    return
  }

  if (moveTimers.has(cardId)) clearTimeout(moveTimers.get(cardId))

  savingByCardId[cardId] = true
  moveTimers.set(
    cardId,
    setTimeout(async () => {
      try {
        await CardsApi.move(cardId, { column_id: columnId, position })
        toasts.push({ type: 'success', message: 'Movimiento guardado.', timeoutMs: 1800 })
      } catch {
        toasts.push({ type: 'error', message: 'No se pudo guardar el movimiento.' })
        await loadBoard()
      } finally {
        savingByCardId[cardId] = false
        moveTimers.delete(cardId)
      }
    }, 400),
  )
}

function moveDraggedCardInternal(toColumnId, toIndex, { persist } = { persist: true }) {
  if (isArchived.value) return

  const cardId = dragState.cardId
  const fromColumnId = dragState.fromColumnId
  if (!cardId || !fromColumnId) return

  const fromCol = findColumn(fromColumnId)
  const toCol = findColumn(toColumnId)
  if (!fromCol || !toCol) return

  const fromCards = (fromCol.cards ?? []).slice().sort(sortByPositionAsc)
  const card = fromCards.find((c) => Number(c.id) === Number(cardId))
  if (!card) return

  const fromCardsWithout = fromCards.filter((c) => Number(c.id) !== Number(cardId))

  const baseDestCards =
    Number(fromColumnId) === Number(toColumnId)
      ? fromCardsWithout.slice()
      : (toCol.cards ?? []).slice().filter((c) => Number(c.id) !== Number(cardId)).sort(sortByPositionAsc)

  const safeIndex = Math.max(0, Math.min(toIndex, baseDestCards.length))
  const newPos = computeInsertPosition(baseDestCards, safeIndex)

  const updated = { ...card, column_id: Number(toColumnId), position: newPos }
  baseDestCards.splice(safeIndex, 0, updated)

  fromCol.cards = fromCardsWithout
  toCol.cards = baseDestCards

  if (persist) scheduleMovePersist(updated.id, updated.column_id, updated.position)
}

function moveDraggedCard(toColumnId, toIndex) {
  moveDraggedCardInternal(toColumnId, toIndex, { persist: true })
}

function onCardDragStart({ cardId, columnId }) {
  if (isArchived.value) return
  dragState.cardId = Number(cardId)
  dragState.fromColumnId = Number(columnId)
}

function onCardDragEnd() {
  dragState.cardId = null
  dragState.fromColumnId = null
}

// ---------------------------
// Cards
// ---------------------------
async function createCard({ columnId, title }) {
  if (isArchived.value) {
    toasts.push({ type: 'info', message: 'Proyecto archivado (solo lectura).', timeoutMs: 2200 })
    return
  }

  if (!auth.canWriteCards) {
    toasts.push({ type: 'error', message: 'No tenés permisos para crear tarjetas.' })
    return
  }

  const col = findColumn(columnId)
  const lastPos = col?.cards?.length ? (col.cards[col.cards.length - 1].position ?? 0) : 0

  try {
    const json = await CardsApi.create({
      column_id: Number(columnId),
      title: String(title),
      position: lastPos + 100,
    })

    const created = {
      ...json.data,
      cover_image_url: resolveCoverUrl(json.data),
    }

    if (col) col.cards = [...(col.cards ?? []), created].slice().sort(sortByPositionAsc)
    else await loadBoard()

    toasts.push({ type: 'success', message: 'Tarjeta creada.', timeoutMs: 2000 })
  } catch (e) {
    error.value = e?.message ?? String(e)
    toasts.push({ type: 'error', message: error.value })
  }
}

async function deleteCard({ cardId }) {
  if (isArchived.value) {
    toasts.push({ type: 'info', message: 'Proyecto archivado (solo lectura).', timeoutMs: 2200 })
    return
  }

  if (!auth.canDeleteCards) {
    toasts.push({ type: 'error', message: 'No tenés permisos para eliminar tarjetas.' })
    return
  }

  const id = Number(cardId)
  if (!id) return

  deletingByCardId[id] = true
  try {
    await CardsApi.destroy(id)

    for (const col of columns.value) {
      const before = (col.cards ?? []).length
      col.cards = (col.cards ?? []).filter((c) => Number(c.id) !== id)
      if ((col.cards ?? []).length !== before) break
    }

    if (modal.isOpen && Number(modal.cardId) === id) closeCard()
    toasts.push({ type: 'info', message: 'Tarjeta eliminada.', timeoutMs: 1600 })
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo eliminar la tarjeta.' })
    await loadBoard()
  } finally {
    deletingByCardId[id] = false
  }
}

// ---------------------------
// Columns
// ---------------------------
async function createColumn({ name }) {
  if (isArchived.value) {
    toasts.push({ type: 'info', message: 'Proyecto archivado (solo lectura).', timeoutMs: 2200 })
    return
  }

  if (!auth.canManageColumns) {
    toasts.push({ type: 'error', message: 'No tenés permisos para crear listas.' })
    return
  }

  const trimmed = String(name || '').trim()
  if (!trimmed) return

  isCreatingColumn.value = true
  try {
    const lastPos = columns.value.length ? (columns.value[columns.value.length - 1].position ?? 0) : 0
    const json = await ColumnsApi.create({
      board_id: Number(boardId.value),
      name: trimmed,
      position: lastPos + 100,
    })

    const created = { ...json.data, cards: [] }
    columns.value = [...columns.value, created].slice().sort(sortByPositionAsc)
    toasts.push({ type: 'success', message: 'Lista creada.', timeoutMs: 1800 })
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo crear la lista.' })
  } finally {
    isCreatingColumn.value = false
  }
}

async function renameColumn({ columnId, name }) {
  if (isArchived.value) {
    toasts.push({ type: 'info', message: 'Proyecto archivado (solo lectura).', timeoutMs: 2200 })
    return
  }

  if (!auth.canManageColumns) {
    toasts.push({ type: 'error', message: 'No tenés permisos para editar listas.' })
    return
  }

  const id = Number(columnId)
  const trimmed = String(name || '').trim()
  if (!id || !trimmed) return

  savingByColumnId.value[id] = true
  try {
    const json = await ColumnsApi.update(id, { name: trimmed })
    columns.value = columns.value.map((c) => (Number(c.id) === id ? { ...c, name: json.data.name } : c))
    toasts.push({ type: 'success', message: 'Lista renombrada.', timeoutMs: 1600 })
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo renombrar la lista.' })
    await loadBoard()
  } finally {
    savingByColumnId.value[id] = false
  }
}

async function moveColumn({ columnId, position }) {
  if (isArchived.value) {
    toasts.push({ type: 'info', message: 'Proyecto archivado (solo lectura).', timeoutMs: 2200 })
    return
  }

  if (!auth.canManageColumns) {
    toasts.push({ type: 'error', message: 'No tenés permisos para mover listas.' })
    return
  }

  const id = Number(columnId)
  const pos = Number(position)
  if (!id || !Number.isFinite(pos)) return

  const prev = columns.value.slice()
  columns.value = columns.value
    .map((c) => (Number(c.id) === id ? { ...c, position: pos } : c))
    .slice()
    .sort(sortByPositionAsc)

  savingByColumnId.value[id] = true
  try {
    await ColumnsApi.update(id, { position: pos })
    toasts.push({ type: 'info', message: 'Lista movida.', timeoutMs: 1200 })
  } catch (e) {
    columns.value = prev
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo mover la lista.' })
  } finally {
    savingByColumnId.value[id] = false
  }
}

async function deleteColumn({ columnId }) {
  if (isArchived.value) {
    toasts.push({ type: 'info', message: 'Proyecto archivado (solo lectura).', timeoutMs: 2200 })
    return
  }

  if (!auth.canManageColumns) {
    toasts.push({ type: 'error', message: 'No tenés permisos para eliminar listas.' })
    return
  }

  const id = Number(columnId)
  if (!id) return

  savingByColumnId.value[id] = true
  try {
    await ColumnsApi.destroy(id)
    columns.value = columns.value.filter((c) => Number(c.id) !== id)
    toasts.push({ type: 'info', message: 'Lista eliminada.', timeoutMs: 1600 })
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo eliminar la lista.' })
  } finally {
    savingByColumnId.value[id] = false
  }
}

// ---------------------------
// Modal
// ---------------------------
function openCard({ cardId }) {
  modal.cardId = Number(cardId)
  modal.isOpen = true
}

function closeCard() {
  modal.isOpen = false
  modal.cardId = null
}

function scheduleCardRefresh(cardId) {
  const id = Number(cardId)
  if (!id) return
  if (cardRefreshTimers.has(id)) clearTimeout(cardRefreshTimers.get(id))

  const timer = setTimeout(async () => {
    try {
      const json = await CardsApi.get(id)
      const fresh = json?.data ?? json
      if (!fresh || Number(fresh.id) !== id) return

      const cover_image_url = await ensureCoverUrlForCard(fresh)

      for (const col of columns.value) {
        const idx = (col.cards ?? []).findIndex((c) => Number(c.id) === id)
        if (idx >= 0) {
          const merged = { ...col.cards[idx], ...fresh, cover_image_url }

          if (!fresh?.cover_attachment_id && !fresh?.cover_attachment?.id) {
            merged.cover_image_url = null
          }

          col.cards[idx] = merged
          break
        }
      }
    } catch {
      // ignore refresh errors
    } finally {
      cardRefreshTimers.delete(id)
    }
  }, 280)

  cardRefreshTimers.set(id, timer)
}

async function onCardSaved(updated) {
  const id = Number(updated?.id)
  if (!id) return

  const entry = findCardEntry(id)
  if (entry) {
    const merged = {
      ...entry.card,
      ...updated,
    }

    if (updated?.cover_attachment_id === null) {
      merged.cover_image_url = null
    } else if (updated?.cover_attachment || updated?.cover_attachment_id) {
      merged.cover_image_url = await ensureCoverUrlForCard(merged)
    }

    entry.column.cards[entry.index] = merged
  }

  scheduleCardRefresh(id)
}

onMounted(loadBoard)

watch(
  () => cardEventsVersion.value,
  () => {
    const event = cardEvents.lastEvent
    if (!event) return

    const cardId = Number(event.cardId)
    if (!cardId) return

    if (event.type === 'comment-created') {
      const entry = findCardEntry(cardId)
      if (!entry) return

      const current = Number(entry.card.comments_count ?? 0)
      patchCardLocal(cardId, {
        comments_count: current + 1,
      })
      return
    }

    if (event.type === 'comment-deleted') {
      const entry = findCardEntry(cardId)
      if (!entry) return

      const current = Number(entry.card.comments_count ?? 0)
      patchCardLocal(cardId, {
        comments_count: Math.max(0, current - 1),
      })
      return
    }

    if (event.type === 'attachment-created') {
      const entry = findCardEntry(cardId)
      if (!entry) return

      const current = Number(entry.card.attachments_count ?? 0)
      patchCardLocal(cardId, {
        attachments_count: current + 1,
      })
      return
    }

    if (event.type === 'attachment-deleted') {
      const entry = findCardEntry(cardId)
      if (!entry) return

      const current = Number(entry.card.attachments_count ?? 0)
      patchCardLocal(cardId, {
        attachments_count: Math.max(0, current - 1),
      })
      return
    }

    if (event.type === 'checklist-created') {
      patchCardLocal(cardId, {
        checklist_items_count: Number(event.totalCount ?? 0),
        checklist_done_count: Number(event.doneCount ?? 0),
      })
      return
    }

    if (event.type === 'checklist-deleted') {
      patchCardLocal(cardId, {
        checklist_items_count: Number(event.totalCount ?? 0),
        checklist_done_count: Number(event.doneCount ?? 0),
      })
      return
    }

    if (event.type === 'checklist-toggled') {
      patchCardLocal(cardId, {
        checklist_done_count: Number(event.doneCount ?? 0),
        checklist_items_count: Number(event.totalCount ?? 0),
      })
      return
    }

    if (event.type === 'card-done-changed') {
      patchCardLocal(cardId, {
        is_done: Boolean(event.isDone),
      })
      return
    }

    if (event.type === 'card-refresh') {
      scheduleCardRefresh(cardId)
    }
  },
)

watch(boardId, loadBoard)

onBeforeUnmount(() => {
  for (const timer of cardRefreshTimers.values()) clearTimeout(timer)
  cardRefreshTimers.clear()

  Object.values(coverObjectUrlsByAttachmentId.value).forEach((url) => {
    try {
      URL.revokeObjectURL(url)
    } catch {}
  })
})
</script>

<template>
  <div class="board-page">
    <div class="board-topbar">
      <div class="board-title">
        <div class="board-title__name">{{ board?.name ?? 'Board' }}</div>
        <div class="board-title__meta">Proyecto #{{ boardId }}</div>
      </div>

      <div class="board-status">
        <md-icon-button
          v-if="auth.canManageBoards"
          type="button"
          title="Gestionar miembros del proyecto"
          :disabled="isArchived"
          @click="openMembersManager"
        >
          <i class="mdi mdi-account-multiple-outline" aria-hidden="true"></i>
        </md-icon-button>

        <md-icon-button
          v-if="auth.isAdmin"
          type="button"
          title="Gestionar etiquetas"
          :disabled="isArchived"
          @click="openLabelsManager"
        >
          <i class="mdi mdi-tag-multiple-outline" aria-hidden="true"></i>
        </md-icon-button>

        <span v-if="isLoading" class="pill pill--loading">
          <md-circular-progress
            indeterminate
            class="md-spinner-inline-md"
          ></md-circular-progress>
        </span>
        <span v-else-if="error" class="pill pill--danger">Error: {{ error }}</span>
        <span v-else-if="isArchived" class="pill pill--archived">Archivado · Solo lectura</span>
        <span v-else class="pill pill--ok">Autosave listo</span>
      </div>
    </div>

    <div v-if="isLoading && !hasLoadedOnce" class="board-loading">
      <md-circular-progress
        indeterminate
        class="board-loading__spinner"
      ></md-circular-progress>
      <!-- <div class="board-loading__text">Cargando tablero…</div> -->
    </div>

    <ColumnList
      v-if="hasLoadedOnce"
      :columns="columns"
      :savingByCardId="savingByCardId"
      :deletingByCardId="deletingByCardId"
      :canCreateCard="auth.canWriteCards"
      :canManageCards="auth.canDeleteCards"
      :canCreateColumn="auth.canManageColumns"
      :isCreatingColumn="isCreatingColumn"
      :canManageColumns="auth.canManageColumns"
      :savingByColumnId="savingByColumnId"
      :draggingCardId="dragState.cardId"
      :isArchived="isArchived"
      @card-drag-start="onCardDragStart"
      @card-drag-end="onCardDragEnd"
      @drop-at="({ columnId, index }) => moveDraggedCard(columnId, index)"
      @create-card="createCard"
      @delete-card="deleteCard"
      @create-column="createColumn"
      @rename-column="renameColumn"
      @move-column="moveColumn"
      @delete-column="deleteColumn"
      @open-card="openCard"
    />

    <div v-if="isLoading && hasLoadedOnce" class="board-loading-overlay">
    <md-circular-progress
      indeterminate
      class="board-loading-overlay__spinner"
    ></md-circular-progress>
  </div>

    <CardModal
      v-if="modal.isOpen && modal.cardId"
      :isOpen="modal.isOpen"
      :cardId="modal.cardId"
      :isArchived="isArchived"
      @close="closeCard"
      @saved="onCardSaved"
    />

    <BoardLabelsModal
      v-if="labelsModalOpen"
      :isOpen="labelsModalOpen"
      :boardId="boardId"
      :isArchived="isArchived"
      @close="labelsModalOpen = false"
      @changed="loadBoard"
    />

    <BoardMembersModal
      v-if="membersModalOpen"
      :isOpen="membersModalOpen"
      :boardId="boardId"
      :isArchived="isArchived"
      @close="membersModalOpen = false"
      @changed="loadBoard"
    />
  </div>
</template>