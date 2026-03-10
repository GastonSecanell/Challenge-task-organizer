<script setup>
import { computed, onBeforeUnmount, reactive, ref } from 'vue'
import CardItem from './CardItem.vue'
import ConfirmActionModal from './ConfirmActionModal.vue'

const props = defineProps({
  columns: { type: Array, required: true },
  savingByCardId: { type: Object, required: true },
  deletingByCardId: { type: Object, default: () => ({}) },
  canCreateCard: { type: Boolean, default: false },
  canManageCards: { type: Boolean, default: false },
  canCreateColumn: { type: Boolean, default: false },
  isCreatingColumn: { type: Boolean, default: false },
  canManageColumns: { type: Boolean, default: false },
  savingByColumnId: { type: Object, default: () => ({}) },
  draggingCardId: { type: [Number, null], default: null },
  isArchived: { type: Boolean, default: false },
})

const emit = defineEmits([
  'card-drag-start',
  'card-drag-end',
  'drop-at',
  'create-card',
  'create-column',
  'rename-column',
  'move-column',
  'delete-column',
  'delete-card',
  'open-card',
])

const sortedColumns = computed(() => {
  return (props.columns ?? []).slice().sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
})

function emitDrop(columnId, index) {
  if (props.isArchived) return
  emit('drop-at', { columnId, index })
}

function computeInsertIndexFromClientY(containerEl, clientY) {
  if (!containerEl) return 0
  const wraps = Array.from(containerEl.querySelectorAll('.card-wrap'))
  if (!wraps.length) return 0

  for (let i = 0; i < wraps.length; i++) {
    const r = wraps[i].getBoundingClientRect()
    const mid = r.top + r.height / 2
    if (clientY < mid) return i
  }
  return wraps.length
}

function dropInList(ev, colId) {
  if (props.isArchived) return
  const containerEl = ev?.currentTarget
  const clientY = Number(ev?.clientY ?? 0)
  const idx = computeInsertIndexFromClientY(containerEl, clientY)
  emitDrop(colId, idx)
}

function dropOnCard(ev, colId, cardIndex) {
  if (props.isArchived) return
  const el = ev?.currentTarget
  const rect = el?.getBoundingClientRect?.()
  if (!rect) return emitDrop(colId, cardIndex)
  const y = Number(ev?.clientY ?? 0) - rect.top
  const after = rect.height ? y / rect.height > 0.5 : false
  emitDrop(colId, after ? cardIndex + 1 : cardIndex)
}

const composer = reactive({
  openForColumnId: null,
  title: '',
})

function openComposer(columnId) {
  if (props.isArchived) return
  composer.openForColumnId = Number(columnId)
  composer.title = ''
}

function closeComposer() {
  composer.openForColumnId = null
  composer.title = ''
}

function submitComposer(columnId) {
  if (props.isArchived) return
  const title = composer.title.trim()
  if (!title) return
  emit('create-card', { columnId, title })
  closeComposer()
}

function askDeleteCard(cardId, columnId) {
  if (!props.canManageCards || props.isArchived) return
  confirmDelete.open = true
  confirmDelete.kind = 'card'
  confirmDelete.cardId = Number(cardId)
  confirmDelete.columnId = Number(columnId)
}

const columnEdit = reactive({
  id: null,
  name: '',
})

function startRename(col) {
  if (!props.canManageColumns || props.isArchived) return
  columnEdit.id = Number(col.id)
  columnEdit.name = String(col.name || '')
}

function cancelRename() {
  columnEdit.id = null
  columnEdit.name = ''
}

function commitRename(colId) {
  if (props.isArchived) return cancelRename()
  const id = Number(colId)
  const name = columnEdit.name.trim()
  if (!id || !name) return cancelRename()
  emit('rename-column', { columnId: id, name })
  cancelRename()
}

const confirmDelete = reactive({
  open: false,
  kind: null,
  columnId: null,
  cardId: null,
})
const menu = reactive({ openForColumnId: null })

function askDelete(colId) {
  if (!props.canManageColumns || props.isArchived) return
  confirmDelete.open = true
  confirmDelete.kind = 'column'
  confirmDelete.columnId = Number(colId)
}

function cancelDelete() {
  confirmDelete.open = false
  confirmDelete.kind = null
  confirmDelete.columnId = null
  confirmDelete.cardId = null
}

function doDelete() {
  if (props.isArchived) return
  if (confirmDelete.kind === 'column') {
    const id = Number(confirmDelete.columnId)
    if (!id) return cancelDelete()
    emit('delete-column', { columnId: id })
  } else if (confirmDelete.kind === 'card') {
    const cardId = Number(confirmDelete.cardId)
    const columnId = Number(confirmDelete.columnId)
    if (!cardId || !columnId) return cancelDelete()
    emit('delete-card', { cardId, columnId })
  }
  cancelDelete()
}

const deleteTitle = computed(() =>
  confirmDelete.kind === 'card' ? 'Eliminar tarjeta' : 'Eliminar lista',
)
const deleteMessage = computed(() =>
  confirmDelete.kind === 'card'
    ? 'Esta acción eliminará la tarjeta y su contenido relacionado de forma permanente.'
    : 'Esta acción eliminará la lista y sus tarjetas de forma permanente.',
)

function toggleMenu(colId) {
  if (props.isArchived) return
  const id = Number(colId)
  menu.openForColumnId = menu.openForColumnId === id ? null : id
}

function closeMenu() {
  menu.openForColumnId = null
}

const draggingColumnId = reactive({ id: null })

function onColumnDragStart(ev, colId) {
  if (!props.canManageColumns || props.isArchived) return
  draggingColumnId.id = Number(colId)
  try {
    ev.dataTransfer?.setData('text/column-id', String(colId))
    ev.dataTransfer?.setData('text/plain', String(colId))
    ev.dataTransfer.effectAllowed = 'move'
  } catch {
    // ignore
  }
}

function onColumnDragEnd() {
  draggingColumnId.id = null
}

function computeNewPosition(sorted, draggedId, targetIndex) {
  const list = sorted.filter((c) => Number(c.id) !== Number(draggedId))
  const idx = Math.max(0, Math.min(Number(targetIndex), list.length))
  const prev = idx > 0 ? Number(list[idx - 1]?.position ?? 0) : null
  const next = idx < list.length ? Number(list[idx]?.position ?? 0) : null
  if (prev === null && next === null) return 100
  if (prev === null) return next - 100
  if (next === null) return prev + 100
  if (prev === next) return prev + 0.0001
  return (prev + next) / 2
}

function onDropColumn(targetIndex) {
  if (props.isArchived) return
  const draggedId = draggingColumnId.id
  if (!draggedId) return
  const pos = computeNewPosition(sortedColumns.value, draggedId, targetIndex)
  emit('move-column', { columnId: draggedId, position: pos })
  draggingColumnId.id = null
}

function onColumnDrop(ev, idx) {
  if (props.isArchived) return
  if (!draggingColumnId.id) return
  const el = ev?.currentTarget
  const rect = el?.getBoundingClientRect?.()
  if (!rect) return onDropColumn(idx)
  const x = Number(ev?.clientX ?? 0) - rect.left
  const ratio = rect.width ? x / rect.width : 0
  const after = ratio > 0.5
  onDropColumn(after ? idx + 1 : idx)
}

const listComposer = reactive({
  open: false,
  name: '',
})

const columnsEl = ref(null)
const panState = reactive({
  isActive: false,
  startX: 0,
  startScrollLeft: 0,
})

function getColumnsElement() {
  const el = columnsEl.value
  if (!el) return null
  if (el instanceof HTMLElement) return el
  if (el?.$el instanceof HTMLElement) return el.$el
  return null
}

function isInteractivePanTarget(target) {
  if (!(target instanceof Element)) return false
  return Boolean(
    target.closest(
      'button, input, textarea, select, a, [contenteditable="true"], [draggable="true"], .card, .card-wrap, .cards, .col-menu, .column__actions',
    ),
  )
}

function onColumnsMouseDown(ev) {
  if (ev.button !== 0) return
  const el = getColumnsElement()
  if (!el) return
  if (isInteractivePanTarget(ev.target)) return

  panState.isActive = true
  panState.startX = ev.clientX
  panState.startScrollLeft = el.scrollLeft

  window.addEventListener('mousemove', onWindowMouseMove)
  window.addEventListener('mouseup', onWindowMouseUp, { once: true })
}

function onWindowMouseMove(ev) {
  if (!panState.isActive) return
  const el = getColumnsElement()
  if (!el) return
  const deltaX = ev.clientX - panState.startX
  el.scrollLeft = panState.startScrollLeft - deltaX
  ev.preventDefault()
}

function stopPanning() {
  panState.isActive = false
  window.removeEventListener('mousemove', onWindowMouseMove)
}

function onWindowMouseUp() {
  stopPanning()
}

onBeforeUnmount(() => {
  stopPanning()
})

function openListComposer() {
  if (props.isArchived) return
  listComposer.open = true
  listComposer.name = ''
}

function closeListComposer() {
  listComposer.open = false
  listComposer.name = ''
}

function submitListComposer() {
  if (props.isArchived) return
  const name = listComposer.name.trim()
  if (!name) return
  emit('create-column', { name })
  closeListComposer()
}
</script>

<template>
  <TransitionGroup
    ref="columnsEl"
    name="flip-col"
    tag="div"
    class="columns"
    :class="{ 'columns--panning': panState.isActive }"
    @mousedown="onColumnsMouseDown"
  >
    <div
      v-for="(col, idx) in sortedColumns"
      :key="`col-${col.id}`"
      class="column"
      @dragover.prevent
      @drop.prevent="onColumnDrop($event, idx)"
    >
        <!-- click fuera para cerrar menú -->
        <div v-if="menu.openForColumnId === Number(col.id)" class="col-menu__backdrop" @click="closeMenu" />
        <div
          class="column__header"
          :draggable="props.canManageColumns && !props.isArchived"
          :class="{ 'column__header--draggable': props.canManageColumns && !props.isArchived }"
          @dragstart="props.canManageColumns && !props.isArchived ? onColumnDragStart($event, col.id) : null"
          @dragend="onColumnDragEnd"
        >
          <div class="column__title px-2 py-1" :style="{ background: col.color }" :class="{ 'column__title--editable': columnEdit.id === Number(col.id) }">
            <span v-if="columnEdit.id !== Number(col.id)" @dblclick="startRename(col)">{{ col.name }}</span>
            <input
              v-else
              v-model="columnEdit.name"
              class="input input--sm"
              :disabled="Boolean(props.savingByColumnId?.[Number(col.id)])"
              @keydown.enter.prevent="commitRename(col.id)"
              @keydown.escape.prevent="cancelRename"
              @blur="commitRename(col.id)"
            />
            <md-circular-progress
              v-if="Boolean(props.savingByColumnId?.[Number(col.id)])"
              indeterminate
              class="md-spinner-inline"
            ></md-circular-progress>
          </div>

          <div class="column__actions">
            <md-icon-button
              v-if="(props.canCreateCard || props.canManageColumns) && !props.isArchived"
              type="button"
              title="Acciones"
              @click.stop="toggleMenu(col.id)"
            >
              <i class="mdi mdi-dots-horizontal" aria-hidden="true"></i>
            </md-icon-button>

            <div v-if="menu.openForColumnId === Number(col.id)" class="col-menu" @click.stop>
              <button
                v-if="props.canCreateCard && !props.isArchived"
                type="button"
                class="col-menu__item"
                @click="
                  () => {
                    closeMenu()
                    openComposer(col.id)
                  }
                "
              >
                <i class="mdi mdi-plus" aria-hidden="true"></i>
                <span>Añadir tarjeta</span>
              </button>
              <button
                v-if="props.canManageColumns && !props.isArchived"
                type="button"
                class="col-menu__item"
                @click="
                  () => {
                    closeMenu()
                    startRename(col)
                  }
                "
              >
                <i class="mdi mdi-pencil-outline" aria-hidden="true"></i>
                <span>Renombrar lista</span>
              </button>
              <button
                v-if="props.canManageColumns && !props.isArchived"
                type="button"
                class="col-menu__item col-menu__item--danger"
                @click="
                  () => {
                    closeMenu()
                    askDelete(col.id)
                  }
                "
              >
                <i class="mdi mdi-trash-can-outline" aria-hidden="true"></i>
                <span>Eliminar lista</span>
              </button>
            </div>
          </div>
        </div>

        <div
          class="cards"
          @dragover.prevent
          @drop.prevent="dropInList($event, col.id)"
        >
          <div
            v-if="(col.cards?.length ?? 0) === 0"
            class="cards__empty"
            @dragover.prevent
            @drop.prevent="emitDrop(col.id, 0)"
          >
            Soltá acá
          </div>

          <TransitionGroup name="flip" tag="div" class="cards__list" @dragover.prevent @drop.prevent="dropInList($event, col.id)">
            <div
              v-for="(card, cIdx) in (col.cards ?? [])"
              :key="`card-${card.id}`"
              class="card-wrap"
              @dragover.prevent
              @drop.prevent="dropOnCard($event, col.id, cIdx)"
            >
              <CardItem
                :card="card"
                :columnId="col.id"
                :isSaving="Boolean(props.savingByCardId?.[card.id])"
                :isDragging="Number(props.draggingCardId) === Number(card.id)"
                :canDelete="props.canManageCards && !props.isArchived"
                :isDeleting="Boolean(props.deletingByCardId?.[Number(card.id)])"
                :draggable="!props.isArchived"
                @dragstart="props.isArchived ? null : emit('card-drag-start', { cardId: card.id, columnId: col.id })"
                @dragend="emit('card-drag-end')"
                @delete="askDeleteCard(card.id, col.id)"
                @open="emit('open-card', { cardId: card.id })"
              />
            </div>
          </TransitionGroup>

          <div
            v-if="props.canCreateCard && !props.isArchived && composer.openForColumnId !== Number(col.id)"
            class="cards__add"
          >
            <md-text-button type="button" class="cards__add-btn" @click="openComposer(col.id)">
              <i class="mdi mdi-plus" aria-hidden="true"></i>
              <span>Añade una tarjeta</span>
            </md-text-button>
            <md-icon-button type="button" title="Plantillas (próximamente)" disabled>
              <i class="mdi mdi-note-multiple-outline" aria-hidden="true"></i>
            </md-icon-button>
          </div>

          <div
            v-if="props.canCreateCard && !props.isArchived && composer.openForColumnId === Number(col.id)"
            class="composer"
          >
            <textarea
              v-model="composer.title"
              class="composer__textarea"
              rows="2"
              placeholder="Ingrese un título para esta tarjeta…"
              @keydown.escape.prevent="closeComposer"
            />
            <div class="composer__actions">
              <md-filled-button type="button" @click="submitComposer(col.id)">
                <i class="mdi mdi-content-save-outline" aria-hidden="true"></i>
                <span>Guardar</span>
              </md-filled-button>
              <md-text-button type="button" @click="closeComposer">Cancelar</md-text-button>
            </div>
          </div>
        </div>
    </div>

    <div v-if="props.canCreateColumn && !props.isArchived" :key="'add-col'" class="column column--add">
      <div v-if="!listComposer.open" class="column--add__closed">
        <md-text-button type="button" @click="openListComposer">
          <i class="mdi mdi-plus" aria-hidden="true"></i>
          <span>Añade otra lista</span>
        </md-text-button>
      </div>

      <div v-else class="column--add__composer">
        <textarea
          v-model="listComposer.name"
          class="composer__textarea"
          rows="2"
          placeholder="Ingrese un título para esta lista…"
          @keydown.escape.prevent="closeListComposer"
        />
        <div class="composer__actions">
          <md-filled-button type="button" :disabled="props.isCreatingColumn" @click="submitListComposer">
            <i class="mdi mdi-content-save-outline" aria-hidden="true"></i>
            <span>Guardar</span>
            <md-circular-progress v-if="props.isCreatingColumn" indeterminate class="md-spinner-inline"></md-circular-progress>
          </md-filled-button>
          <md-text-button type="button" :disabled="props.isCreatingColumn" @click="closeListComposer">Cancelar</md-text-button>
        </div>
      </div>
    </div>
  </TransitionGroup>
  <ConfirmActionModal
    :isOpen="confirmDelete.open"
    :title="deleteTitle"
    :message="deleteMessage"
    confirmText="Eliminar"
    cancelText="Cancelar"
    @close="cancelDelete"
    @confirm="doDelete"
  />
</template>

