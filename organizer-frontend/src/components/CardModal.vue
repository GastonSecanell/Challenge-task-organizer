<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { QuillEditor } from '@vueup/vue-quill'

import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toasts'
import { useCardEventsStore } from '@/stores/cardEvents'
import { getAvatarObjectUrl } from '@/lib/avatars'
import { extractDominantColor } from '@/lib/imageColor'

import BoardLabelsModal from './BoardLabelsModal.vue'
import ImageViewerModal from './ImageViewerModal.vue'
import ConfirmActionModal from './ui/ConfirmActionModal.vue'

// APIS
import { CardsApi } from '@/lib/api/cards'
import { BoardsApi } from '@/lib/api/boards'
import { LabelsApi } from '@/lib/api/labels'
import { ChecklistApi } from '@/lib/api/checklist'
import { AttachmentsApi } from '@/lib/api/attachments'

import { coverColors, quillToolbar } from '@/lib/options/cardOptions'
import { decorateLinksAndLinkifyText, sanitizeHtml } from '@/lib/text/html'

import { useCardAttachments } from '@/composables/card/useCardAttachments'
import { usePopoverPlacement } from '@/composables/ui/usePopoverPlacement'
import { useCardModalLoader } from '@/composables/card/useCardModalLoader'
import { useCardAutoSave } from '@/composables/card/useCardAutoSave'
import { useCardChecklist } from '@/composables/card/useCardChecklist'

// ------------------------------------------------------------
// Props / emits
// ------------------------------------------------------------
const props = defineProps({
  cardId: { type: Number, required: true },
  isOpen: { type: Boolean, required: true },
  isArchived: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'saved'])

const auth = useAuthStore()
const toasts = useToastStore()
const cardEvents = useCardEventsStore()

// ------------------------------------------------------------
// Helpers mínimos
// ------------------------------------------------------------
function unwrapList(body) {
  if (Array.isArray(body?.data)) return body.data
  if (Array.isArray(body)) return body
  return []
}
function unwrapItem(body) {
  return body?.data ?? body ?? null
}
function sortByPosition(arr) {
  return (arr || []).slice().sort((a, b) => (a?.position ?? 0) - (b?.position ?? 0))
}

// ------------------------------------------------------------
// Permisos / refs
// ------------------------------------------------------------
const canEdit = computed(() => auth.canWriteCards && !props.isArchived)
const canDelete = computed(() => auth.canDeleteCards && !props.isArchived)


const cardIdRef = computed(() => props.cardId)
const isOpenRef = computed(() => props.isOpen)

// ------------------------------------------------------------
// Popovers
// ------------------------------------------------------------
const labelsTriggerEl = ref(null)
const membersTriggerEl = ref(null)
const isCoverPopoverOpen = ref(false)

const labelsPopover = usePopoverPlacement({
  triggerElRef: labelsTriggerEl,
  preferredHeight: 320,
  minHeight: 180,
  maxHeightCap: 420,
})

const membersPopover = usePopoverPlacement({
  triggerElRef: membersTriggerEl,
  preferredHeight: 340,
  minHeight: 200,
  maxHeightCap: 460,
})

function requestClose() {
  labelsPopover.close()
  membersPopover.close()
  isCoverPopoverOpen.value = false
  revokeLocalCoverPreview()
  revokeAllLocalAttachmentPreviews()
  emit('close')
}

// ------------------------------------------------------------
// Attachments
// ------------------------------------------------------------
const {
  attachments,
  uploadInput,
  isUploading,
  isDragOverUpload,
  uploadDragDepth,
  uploadProgress,
  previewUrlByAttachmentId,

  setFromCard,
  cleanupPreviews,
  formatBytes,
  isImageAttachment,
  ensureAttachmentPreview,

  openFilePicker,
  onUploadDragEnter,
  onUploadDragLeave,
  onUploadDragOver,
  onDropUpload,
  uploadAttachment,

  downloadAttachment,
  viewAttachment,
  deleteAttachment,
} = useCardAttachments({
  toasts,
  canEditRef: canEdit,
  AttachmentsApi,
})

// ------------------------------------------------------------
// Avatares
// ------------------------------------------------------------
const avatarUrlsByUserId = ref({})
async function ensureAvatar(u) {
  const uid = Number(u?.id)
  if (!uid || !u?.has_avatar) return
  if (avatarUrlsByUserId.value[uid]) return
  avatarUrlsByUserId.value[uid] = await getAvatarObjectUrl(u)
}

// ------------------------------------------------------------
// Members: estado saving local
// ------------------------------------------------------------
const savingMemberByUserId = reactive({})
let pendingMemberSaves = new Set()
function markMemberSaving(uid) {
  const id = Number(uid)
  if (!id) return
  savingMemberByUserId[id] = true
  pendingMemberSaves.add(id)
}
function clearPendingMemberSaves() {
  for (const uid of pendingMemberSaves) savingMemberByUserId[uid] = false
  pendingMemberSaves = new Set()
}

// ------------------------------------------------------------
// Loader principal
// ------------------------------------------------------------
const {
  isLoading,
  error,
  card,
  boardId,
  form,
  checklist,

  boardLabels,
  selectedLabelIds,
  isLoadingLabels,

  members,
  selectedMemberIds,
  isLoadingMembers,

  activity,
  isLoadingActivity,

  loadBoard,
  loadActivity,
} = useCardModalLoader({
  cardIdRef,
  isOpenRef,

  CardsApi,
  LabelsApi,
  BoardsApi,

  unwrapList,
  unwrapItem,
  sortByPosition,

  setFromCard,
  cleanupPreviews,
  uploadDragDepth,
  isDragOverUpload,

  labelsPopover,
  membersPopover,
  isCoverPopoverOpen,

  ensureAvatar,
  clearPendingMemberSaves,
})

// ------------------------------------------------------------
// Autosave Title + Description
// ------------------------------------------------------------
const {
  titleEl,
  isTitleEditing,
  isSavingTitle,
  lastSavedTitle,
  isTitleDirty,
  titleCounterText,
  titleCounterClass,
  onTitleInput,
  onTitleFocus,
  onTitleBlur,
  autosizeTitle,

  quillWrapEl,
  isEditingDescription,
  isSavingDescription,
  lastSavedDescription,
  isDescriptionDirty,
  safeDescriptionHtml,
  saveDescriptionNow,

  openLinkInNewTab,
} = useCardAutoSave({
  propsCardIdRef: cardIdRef,
  canEditRef: canEdit,
  formRef: computed(() => form), // para mantenerlo reactivo
  CardsApi,
  toasts,
  TITLE_MAX: 80,
  titleDebounceMs: 500,
  descOutsideSave: true,
  onTitleSaved: (title) => emitInlineUpdate({ title }),
  onDescriptionSaved: (description) => emitInlineUpdate({ description }),
})

watch(
  () => [props.isOpen, form.title],
  async () => {
    if (!props.isOpen) return
    await nextTick()
    autosizeTitle()
  },
  { immediate: true },
)

// ------------------------------------------------------------
// Labels
// ------------------------------------------------------------
const labelsQuery = ref('')
const isUpdatingLabels = ref(false)
const labelsModalOpen = ref(false)

const filteredBoardLabels = computed(() => {
  const q = labelsQuery.value.trim().toLowerCase()
  if (!q) return boardLabels.value
  return boardLabels.value.filter((l) => String(l.name || '').toLowerCase().includes(q))
})

const selectedLabels = computed(() => {
  const ids = new Set(selectedLabelIds.value.map((x) => Number(x)))
  return boardLabels.value.filter((l) => ids.has(Number(l.id)))
})

let labelsSaveTimer = null
function toggleLabelId(id) {
  const n = Number(id)
  if (!n) return
  if (selectedLabelIds.value.includes(n)) selectedLabelIds.value = selectedLabelIds.value.filter((x) => x !== n)
  else selectedLabelIds.value = [...selectedLabelIds.value, n]
}

function onToggleLabel(id) {
  toggleLabelId(id)
  queueSaveLabels()
}

function queueSaveLabels() {
  if (!canEdit.value) return
  if (labelsSaveTimer) clearTimeout(labelsSaveTimer)

  labelsSaveTimer = setTimeout(async () => {
    isUpdatingLabels.value = true
    try {
      await CardsApi.setLabels(props.cardId, selectedLabelIds.value)
      emitInlineUpdate()
      refreshActivitySoon()
      emitCardEvent('card-refresh')
    } catch (e) {
      toasts.push({ type: 'error', message: e?.message ?? 'No se pudieron guardar las etiquetas.' })
    } finally {
      isUpdatingLabels.value = false
    }
  }, 350)
}

async function createDefaultLabels() {
  if (!auth.isAdmin || !boardId.value) return

  const defaults = [
    { name: 'Frontend', color: '#60a5fa' },
    { name: 'Backend', color: '#fb7185' },
    { name: 'Base de Datos', color: '#fbbf24' },
    { name: 'Requerimiento', color: '#a78bfa' },
    { name: 'Mejora', color: '#34d399' },
  ]

  isLoadingLabels.value = true
  try {
    await Promise.all(defaults.map((d) => LabelsApi.create(boardId.value, d)))
    const body = await LabelsApi.listByBoard(boardId.value)
    boardLabels.value = sortByPosition(unwrapList(body))
    toasts.push({ type: 'success', message: 'Etiquetas creadas.', timeoutMs: 1800 })
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudieron crear las etiquetas.' })
  } finally {
    isLoadingLabels.value = false
  }
}

// ------------------------------------------------------------
// Members
// ------------------------------------------------------------
const membersQuery = ref('')
const isUpdatingMembers = ref(false)

const memberMap = computed(() => {
  const m = new Map()
  for (const u of members.value ?? []) m.set(Number(u.id), u)
  for (const u of card.value?.members ?? []) m.set(Number(u.id), u)
  return m
})

const selectedMembers = computed(() =>
  selectedMemberIds.value.map((id) => memberMap.value.get(Number(id))).filter(Boolean),
)

const availableBoardMembers = computed(() => {
  const list = [...members.value]
  const byId = new Map()
  for (const u of list) {
    const id = Number(u?.id)
    if (!id) continue
    if (!byId.has(id)) byId.set(id, u)
  }
  return Array.from(byId.values()).sort((a, b) => String(a?.name || '').localeCompare(String(b?.name || '')))
})

const filteredBoardMembers = computed(() => {
  const q = membersQuery.value.trim().toLowerCase()
  if (!q) return availableBoardMembers.value
  return availableBoardMembers.value.filter((u) => {
    const n = String(u.name || '').toLowerCase()
    const e = String(u.email || '').toLowerCase()
    return n.includes(q) || e.includes(q)
  })
})

function userInitial(u) {
  return String(u?.name || '?').slice(0, 1).toUpperCase()
}

function toggleMemberId(id) {
  const n = Number(id)
  if (!n) return
  if (selectedMemberIds.value.includes(n)) selectedMemberIds.value = selectedMemberIds.value.filter((x) => x !== n)
  else selectedMemberIds.value = [...selectedMemberIds.value, n]
}

let membersSaveTimer = null

function queueSaveMembers() {
  if (!canEdit.value) return
  if (membersSaveTimer) clearTimeout(membersSaveTimer)

  membersSaveTimer = setTimeout(async () => {
    isUpdatingMembers.value = true
    try {
      await CardsApi.setMembers(props.cardId, selectedMemberIds.value)
      emitInlineUpdate()
      clearPendingMemberSaves()
      refreshActivitySoon()
      emitCardEvent('card-refresh')
    } catch (e) {
      toasts.push({ type: 'error', message: e?.message ?? 'No se pudieron guardar los miembros.' })
      clearPendingMemberSaves()
    } finally {
      isUpdatingMembers.value = false
    }
  }, 350)
}

async function toggleMemberFromPopover(user) {
  if (!canEdit.value) return
  const uid = Number(user?.id)
  if (!uid) return
  if (savingMemberByUserId[uid]) return

  markMemberSaving(uid)

  toggleMemberId(uid)
  queueSaveMembers()
}

// ------------------------------------------------------------
// Checklist
// ------------------------------------------------------------
const isAdding = ref(false)
const newChecklistTextareaEl = ref(null)
const editingChecklistTextareaEl = ref(null)
const {
  newItemText,
  hideCompleted,
  savingChecklistById,
  draggingChecklistId,
  editingChecklistId,
  editingChecklistText,
  checklistTextMax,
  visibleChecklist,
  formatChecklistText,
  startEditChecklistItem,
  addChecklistItem,
  toggleItem,
  cancelEditChecklistItem,
  saveChecklistItemText,
  onChecklistDragStart,
  onChecklistDragEnd,
  moveChecklistItemTo,
  deleteItem,
} = useCardChecklist({
  canEditRef: canEdit,
  cardIdRef,
  checklistRef: checklist,
  ChecklistApi,
  toasts,
  errorRef: error,
  reloadFn: loadBoard,
})

async function toggleChecklistItemWrapped(item) {
  const itemId = Number(item?.id)
  const nextIsDone = !Boolean(item?.is_done)

  await toggleItem(item)
  refreshActivitySoon()

  const list = Array.isArray(checklist.value) ? checklist.value : []
  const totalCount = list.length

  const doneCount = list.reduce((acc, x) => {
    if (Number(x?.id) === itemId) {
      return acc + (nextIsDone ? 1 : 0)
    }
    return acc + (Boolean(x?.is_done) ? 1 : 0)
  }, 0)

  emitCardEvent('checklist-toggled', { totalCount, doneCount })
  emit('saved', { id: props.cardId })
}

async function saveChecklistItemTextWrapped(item) {
  await saveChecklistItemText(item)
  refreshActivitySoon()
  emitCardEvent('card-refresh')
  emit('saved', { id: props.cardId })
}

async function deleteChecklistItemWrapped(item) {
  await deleteItem(item)
  refreshActivitySoon()

  const list = Array.isArray(checklist.value) ? checklist.value : []
  const totalCount = list.length
  const doneCount = list.filter((x) => Boolean(x?.is_done)).length

  emitCardEvent('checklist-deleted', { totalCount, doneCount })
  emit('saved', {
    id: props.cardId,
    checklist_items_count: totalCount,
    checklist_done_count: doneCount,
  })
}

const checklistMaxToastShown = ref(false)
const newChecklistMaxToastShown = ref(false)
const checklistEditingLength = computed(() => String(editingChecklistText.value ?? '').length)
const newChecklistLength = computed(() => String(newItemText.value ?? '').length)
const checklistCounterClass = computed(() =>
  checklistEditingLength.value >= checklistTextMax ? 'checklist__counter--limit' : '',
)
const newChecklistCounterClass = computed(() =>
  newChecklistLength.value >= checklistTextMax ? 'checklist__counter--limit' : '',
)

function onChecklistEditInput() {
  if (checklistEditingLength.value >= checklistTextMax && !checklistMaxToastShown.value) {
    toasts.push({ type: 'warning', message: `Máximo ${checklistTextMax} caracteres.` })
    checklistMaxToastShown.value = true
  }
}

function autoResizeTextarea(el) {
  const target = Array.isArray(el) ? el[0] : el
  const textarea =
    target?.tagName === 'TEXTAREA'
      ? target
      : target?.querySelector?.('textarea') ?? null
  if (!textarea) return
  textarea.style.height = 'auto'
  textarea.style.height = `${textarea.scrollHeight}px`
}

function onChecklistTextareaInput(ev) {
  onChecklistEditInput()
  autoResizeTextarea(ev?.target)
}

function onNewChecklistTextareaInput(ev) {
  if (newChecklistLength.value >= checklistTextMax && !newChecklistMaxToastShown.value) {
    toasts.push({ type: 'warning', message: `Máximo ${checklistTextMax} caracteres.` })
    newChecklistMaxToastShown.value = true
  }
  autoResizeTextarea(ev?.target)
}

watch(
  () => editingChecklistId.value,
  () => {
    checklistMaxToastShown.value = false
    nextTick(() => autoResizeTextarea(editingChecklistTextareaEl.value))
  },
)

watch(
  () => newItemText.value,
  (val) => {
    if (String(val ?? '').length < checklistTextMax) newChecklistMaxToastShown.value = false
  },
)

watch(
  () => editingChecklistText.value,
  () =>
    nextTick(() => {
      if (editingChecklistId.value === null) return
      autoResizeTextarea(editingChecklistTextareaEl.value)
    }),
)

watch(
  () => newItemText.value,
  () => nextTick(() => autoResizeTextarea(newChecklistTextareaEl.value)),
)

// Para mantener la UX del “spinner isAdding”
async function addChecklistItemWrapped() {
  if (!canEdit.value) return
  if (!newItemText.value.trim()) return

  isAdding.value = true
  try {
    await addChecklistItem()
    refreshActivitySoon()

    const list = Array.isArray(checklist.value) ? checklist.value : []
    const totalCount = list.length
    const doneCount = list.filter((x) => Boolean(x?.is_done)).length

    emitCardEvent('checklist-created', { totalCount, doneCount })
    emit('saved', {
      id: props.cardId,
      checklist_items_count: totalCount,
      checklist_done_count: doneCount,
    })
  } finally {
    isAdding.value = false
  }
}

const localCoverPreviewUrl = ref(null)

function revokeLocalCoverPreview() {
  if (localCoverPreviewUrl.value?.startsWith?.('blob:')) {
    URL.revokeObjectURL(localCoverPreviewUrl.value)
  }
  localCoverPreviewUrl.value = null
}

const localAttachmentPreviewById = ref({})

function setLocalAttachmentPreview(id, url) {
  const nid = Number(id)
  if (!nid || !url) return
  localAttachmentPreviewById.value = {
    ...localAttachmentPreviewById.value,
    [nid]: url,
  }
}

function getLocalAttachmentPreview(id) {
  return localAttachmentPreviewById.value[Number(id)] || null
}

function revokeLocalAttachmentPreview(id) {
  const nid = Number(id)
  const url = localAttachmentPreviewById.value[nid]
  if (url?.startsWith?.('blob:')) {
    URL.revokeObjectURL(url)
  }

  const next = { ...localAttachmentPreviewById.value }
  delete next[nid]
  localAttachmentPreviewById.value = next
}

function revokeAllLocalAttachmentPreviews() {
  for (const key of Object.keys(localAttachmentPreviewById.value)) {
    const url = localAttachmentPreviewById.value[key]
    if (url?.startsWith?.('blob:')) {
      URL.revokeObjectURL(url)
    }
  }
  localAttachmentPreviewById.value = {}
}

function isImageFile(file) {
  return String(file?.type || '').toLowerCase().startsWith('image/')
}

// ------------------------------------------------------------
// Done
// ------------------------------------------------------------
async function toggleDoneNow() {
  if (!canEdit.value) return
  const next = !Boolean(form.is_done)
  form.is_done = next

  try {
    await CardsApi.update(props.cardId, { is_done: next })
    emit('saved', { id: props.cardId, is_done: next })

    refreshActivitySoon()
    emitCardEvent('card-done-changed', { isDone: next })

    toasts.push({
      type: 'success',
      message: next ? 'Marcada como finalizada.' : 'Marcada como incompleta.',
      timeoutMs: 1600,
    })
  } catch (e) {
    form.is_done = !next
    toasts.push({
      type: 'error',
      message: e?.message ?? 'No se pudo actualizar el estado.',
    })
  }
}

// ------------------------------------------------------------
// Cover
// ------------------------------------------------------------
const coverAttachment = computed(() => {
  const id = form.cover_attachment_id ? Number(form.cover_attachment_id) : null
  if (!id) return null
  return (attachments.value ?? []).find((a) => Number(a.id) === id) ?? null
})

const coverImageUrl = computed(() => {
  const id = form.cover_attachment_id ? Number(form.cover_attachment_id) : null

  if (id) {
    const a = (attachments.value ?? []).find((x) => Number(x.id) === id)
    const localUrl = getLocalAttachmentPreview(id)

    if (localUrl) return localUrl
    if (a?.thumb_url) return a.thumb_url
    if (previewUrlByAttachmentId.value[id]) return previewUrlByAttachmentId.value[id]
    if (a?.preview_url) return a.preview_url
  }

  return localCoverPreviewUrl.value || null
})

const extractedModalCoverBg = ref(null)

watch(
  () => form.cover_attachment_id,
  async (id) => {
    if (!id) return
    const a = coverAttachment.value
    if (!a) return

    if (!a.thumb_url && !a.preview_url) {
      await ensureAttachmentPreview(a)
    }
  },
)

watch(
  coverImageUrl,
  async (url) => {
    extractedModalCoverBg.value = url ? await extractDominantColor(url) : null
  },
  { immediate: true },
)

const modalCoverBg = computed(() => form.cover_color || extractedModalCoverBg.value || '#1e3a5f')

const coverUploadInput = ref(null)
const isCoverDragOver = ref(false)
const coverDragDepth = ref(0)
const isCoverUploading = ref(false)
const coverUploadProgress = ref(0)
const COVER_UPLOAD_MAX_BYTES = 5 * 1024 * 1024

async function setCoverColor(color) {
  if (!canEdit.value) return
  revokeLocalCoverPreview()
  form.cover_color = color
  form.cover_attachment_id = null
  try {
    await CardsApi.update(props.cardId, {
      cover_color: color,
      cover_attachment_id: null,
      cover_size: form.cover_size,
    })
    emit('saved', {
      id: props.cardId,
      cover_color: color,
      cover_attachment_id: null,
      cover_size: form.cover_size,
    })
    emitCardEvent('card-refresh')
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo actualizar la portada.' })
  }
}

async function setCoverFromAttachment(a) {
  if (!canEdit.value) return
  const id = Number(a?.id)
  if (!id) return

  form.cover_attachment_id = id
  form.cover_color = null

  const localUrl = getLocalAttachmentPreview(id)

  revokeLocalCoverPreview()

  if (localUrl) {
    localCoverPreviewUrl.value = localUrl
  } else if (a?.thumb_url) {
    localCoverPreviewUrl.value = a.thumb_url
  } else if (a?.preview_url) {
    localCoverPreviewUrl.value = a.preview_url
  } else {
    await ensureAttachmentPreview(a)
    localCoverPreviewUrl.value = previewUrlByAttachmentId.value[id] || null
  }

  try {
    await CardsApi.update(props.cardId, {
      cover_attachment_id: id,
      cover_color: null,
      cover_size: form.cover_size,
    })

    emit('saved', {
      id: props.cardId,
      cover_attachment_id: id,
      cover_color: null,
      cover_size: form.cover_size,
      cover_attachment: a,
    })

    emitCardEvent('card-refresh')
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo actualizar la portada.' })
  }
}

function isValidCoverImage(file) {
  if (!file) return 'No hay imagen para subir.'
  if (Number(file.size || 0) > COVER_UPLOAD_MAX_BYTES) return 'La imagen supera 5MB.'
  const mime = String(file.type || '').toLowerCase()
  const name = String(file.name || '').toLowerCase()
  const byMime = mime === 'image/jpeg' || mime === 'image/png'
  const byExt = name.endsWith('.jpg') || name.endsWith('.jpeg') || name.endsWith('.png')
  if (!byMime && !byExt) return 'Solo se permiten imágenes JPG o PNG.'
  return null
}

function openCoverImagePicker() {
  if (!canEdit.value || isUploading.value || isCoverUploading.value) return
  coverUploadInput.value?.click?.()
}

async function uploadCoverImageFile(file) {
  const msg = isValidCoverImage(file)
  if (msg) {
    toasts.push({ type: 'error', message: msg })
    return
  }

  revokeLocalCoverPreview()
  localCoverPreviewUrl.value = URL.createObjectURL(file)

  isCoverUploading.value = true
  try {
    const body = await AttachmentsApi.uploadToCard(props.cardId, file, (pct) => {
      coverUploadProgress.value = Number(pct || 0)
    })

    const created = unwrapItem(body)
    if (!created) throw new Error('No se pudo crear el adjunto.')

    attachments.value = [created, ...attachments.value]
    await nextTick()
    // importante: el attachment recién subido también necesita preview local
    if (localCoverPreviewUrl.value) {
      setLocalAttachmentPreview(created.id, localCoverPreviewUrl.value)
    }

    if (isImageAttachment(created) && !created.thumb_url && !created.preview_url) {
      await ensureAttachmentPreview(created)
    }

    form.cover_attachment_id = Number(created.id)
    form.cover_color = null

    await CardsApi.update(props.cardId, {
      cover_attachment_id: Number(created.id),
      cover_color: null,
      cover_size: form.cover_size,
    })

    emit('saved', {
      id: props.cardId,
      cover_attachment_id: Number(created.id),
      cover_color: null,
      cover_size: form.cover_size,
      cover_attachment: created,
    })

    emitCardEvent('card-refresh')

    toasts.push({
      type: 'success',
      message: 'Imagen subida y portada aplicada.',
      timeoutMs: 1800,
    })
  } catch (e) {
    revokeLocalCoverPreview()
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo subir la portada.' })
  } finally {
    isCoverUploading.value = false
    coverUploadProgress.value = 0
  }
}

async function onCoverImageInputChange(ev) {
  const file = ev?.target?.files?.[0]
  if (ev?.target) ev.target.value = ''
  if (!file) return
  await uploadCoverImageFile(file)
}

function onCoverDragEnter() {
  if (!canEdit.value || isUploading.value || isCoverUploading.value) return
  coverDragDepth.value += 1
  isCoverDragOver.value = true
}

function onCoverDragLeave() {
  if (!canEdit.value || isUploading.value || isCoverUploading.value) return
  coverDragDepth.value = Math.max(0, coverDragDepth.value - 1)
  if (coverDragDepth.value === 0) isCoverDragOver.value = false
}

function onCoverDragOver() {
  if (!canEdit.value || isUploading.value || isCoverUploading.value) return
  isCoverDragOver.value = true
}

async function onCoverDrop(ev) {
  if (!canEdit.value || isUploading.value || isCoverUploading.value) return
  const files = Array.from(ev?.dataTransfer?.files ?? [])
  coverDragDepth.value = 0
  isCoverDragOver.value = false
  if (!files.length) return
  if (files.length > 1) toasts.push({ type: 'info', message: 'Se tomará solo la primera imagen.' })
  await uploadCoverImageFile(files[0])
}

async function setCoverSize(size) {
  if (!canEdit.value) return
  const s = size === 'large' ? 'large' : 'small'
  form.cover_size = s
  try {
    await CardsApi.update(props.cardId, { cover_size: s })
    emit('saved', { id: props.cardId, cover_size: s })
    emitCardEvent('card-refresh')
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo actualizar el tamaño.' })
  }
}

async function removeCover() {
  if (!canEdit.value) return
  revokeLocalCoverPreview()
  form.cover_color = null
  form.cover_attachment_id = null
  try {
    await CardsApi.update(props.cardId, { cover_color: null, cover_attachment_id: null })
    toasts.push({ type: 'info', message: 'Portada quitada.', timeoutMs: 1600 })
    emit('saved', { id: props.cardId, cover_color: null, cover_attachment_id: null })
    emitCardEvent('card-refresh')
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo quitar la portada.' })
  }
}

// ------------------------------------------------------------
// Activity / comments
// ------------------------------------------------------------

let activityRefreshTimer = null

function refreshActivitySoon(delay = 250) {
  if (!props.isOpen || !props.cardId) return

  if (activityRefreshTimer) clearTimeout(activityRefreshTimer)

  activityRefreshTimer = setTimeout(async () => {
    try {
      await loadActivity()
    } catch {
      // ignore
    } finally {
      activityRefreshTimer = null
    }
  }, delay)
}

function emitCardEvent(type, extra = {}) {
  cardEvents.emitEvent({
    type,
    cardId: props.cardId,
    ...extra,
  })
}

const activityVersion = computed(() => cardEvents.getActivityVersion(props.cardId))

watch(
  () => activityVersion.value,
  () => {
    if (!props.isOpen || !props.cardId) return
    refreshActivitySoon(100)
  }
)

const commentBody = ref('<p><br></p>')
const isPostingComment = ref(false)
const isCommentComposerExpanded = ref(false)
const replyingToComment = ref(null)
const deletingCommentById = reactive({})

const AUDIT_ACTIONS = new Set([
  'card_moved','card_completed','card_marked_incomplete',
  'checklist_item_created','checklist_item_updated','checklist_item_deleted',
  'attachment_uploaded','attachment_deleted','attachment_created','attachment_updated',
])

const visibleActivity = computed(() =>
  (activity.value ?? []).filter((it) =>
    it?.type === 'comment' || (it?.type === 'audit' && AUDIT_ACTIONS.has(it?.action))
  )
)

let cleanupDescriptionEditorListeners = null
let cleanupCommentEditorListeners = null
const inlineImageBlockedToastMs = 2600
const deletingAttachmentById = reactive({})
const confirmDelete = reactive({
  open: false,
  kind: null,
  payload: null,
})
const isConfirmingDelete = ref(false)
const imageViewer = reactive({
  isOpen: false,
  src: '',
  title: 'Imagen',
})

function openImageViewer(src, title = 'Imagen') {
  const url = String(src || '').trim()
  if (!url) return
  imageViewer.src = url
  imageViewer.title = String(title || 'Imagen')
  imageViewer.isOpen = true
}

function closeImageViewer() {
  imageViewer.isOpen = false
  imageViewer.src = ''
  imageViewer.title = 'Imagen'
}

function extractFirstImageFileFromDataTransfer(dt) {
  const files = Array.from(dt?.files ?? [])
  const byFile = files.find((f) => String(f?.type || '').toLowerCase().startsWith('image/'))
  if (byFile) return byFile

  const items = Array.from(dt?.items ?? [])
  for (const it of items) {
    const t = String(it?.type || '').toLowerCase()
    if (!t.startsWith('image/')) continue
    const f = it.getAsFile?.()
    if (f) return f
  }
  return null
}

function notifyInlineImageNotAllowed() {
  toasts.push({
    type: 'info',
    message: 'Pegar o arrastrar imágenes en el editor está deshabilitado. Usa la sección Adjuntos.',
    timeoutMs: inlineImageBlockedToastMs,
  })
}

async function uploadAttachmentWrapped(ev) {
  const file = ev?.target?.files?.[0] ?? null
  const created = await uploadAttachment(props.cardId, ev)

  if (file && created?.id && isImageFile(file)) {
    const id = Number(created.id)

    const alreadyHasPreview =
      created.thumb_url ||
      created.preview_url ||
      previewUrlByAttachmentId.value[id]

    if (!alreadyHasPreview && !getLocalAttachmentPreview(id)) {
      setLocalAttachmentPreview(id, URL.createObjectURL(file))
    }
  }

  refreshActivitySoon()
  emitCardEvent('attachment-created')
  emit('saved', { id: props.cardId })
}

async function onDropUploadWrapped(ev) {
  const file = Array.from(ev?.dataTransfer?.files ?? [])[0] ?? null
  const created = await onDropUpload(props.cardId, ev)

  if (file && created?.id && isImageFile(file)) {
    const id = Number(created.id)

    const alreadyHasPreview =
      created.thumb_url ||
      created.preview_url ||
      previewUrlByAttachmentId.value[id]

    if (!alreadyHasPreview && !getLocalAttachmentPreview(id)) {
      setLocalAttachmentPreview(id, URL.createObjectURL(file))
    }
  }

  refreshActivitySoon()
  emitCardEvent('attachment-created')
  emit('saved', { id: props.cardId })
}

function extFromName(name) {
  const s = String(name || '')
  const i = s.lastIndexOf('.')
  return i >= 0 ? s.slice(i + 1).toLowerCase() : ''
}

function attachmentIconClass(a) {
  const mime = String(a?.mime_type || '').toLowerCase()
  const ext = extFromName(a?.original_name)
  if (mime.includes('pdf') || ext === 'pdf') return 'mdi-file-pdf-box'
  if (mime.includes('word') || ['doc', 'docx'].includes(ext)) return 'mdi-file-word-box'
  if (mime.includes('excel') || ['xls', 'xlsx'].includes(ext)) return 'mdi-file-excel-box'
  return 'mdi-file-document-outline'
}

async function onAttachmentView(a) {
  if (!a) return

  if (isImageAttachment(a)) {
    const id = Number(a.id)

    let src =
      getLocalAttachmentPreview(id) ||
      previewUrlByAttachmentId.value[id] ||
      null

    if (!src) {
      src = await ensureAttachmentPreview(a)
    }

    if (!src) {
      src = await AttachmentsApi.downloadBlobUrl(id)
    }

    if (!src) {
      toasts.push({ type: 'error', message: 'No se pudo abrir la imagen.' })
      return
    }

    openImageViewer(src, a.original_name || 'Imagen adjunta')
    return
  }

  await viewAttachment(a)
}

async function deleteAttachmentWithLoading(a) {
  const id = Number(a?.id)
  if (!id || deletingAttachmentById[id]) return

  deletingAttachmentById[id] = true

  try {
    await deleteAttachment(a)
    revokeLocalAttachmentPreview(id)

    refreshActivitySoon()
    emitCardEvent('attachment-deleted')
    emit('saved', { id: props.cardId })
  } finally {
    deletingAttachmentById[id] = false
  }
}

function askDeleteAttachment(a) {
  if (!canDelete.value) return
  confirmDelete.open = true
  confirmDelete.kind = 'attachment'
  confirmDelete.payload = a
}

function bindInlineImageUploadListeners(quill) {
  const root = quill?.root
  if (!root) return () => {}

  const onPaste = (ev) => {
    const f = extractFirstImageFileFromDataTransfer(ev?.clipboardData)
    if (!f) return
    ev.preventDefault()
    notifyInlineImageNotAllowed()
  }

  const onDrop = (ev) => {
    const f = extractFirstImageFileFromDataTransfer(ev?.dataTransfer)
    if (!f) return
    ev.preventDefault()
    notifyInlineImageNotAllowed()
  }

  root.addEventListener('paste', onPaste)
  root.addEventListener('drop', onDrop)
  return () => {
    root.removeEventListener('paste', onPaste)
    root.removeEventListener('drop', onDrop)
  }
}

function onDescriptionEditorReady(quill) {
  cleanupDescriptionEditorListeners?.()
  cleanupDescriptionEditorListeners = bindInlineImageUploadListeners(quill)
}

function onCommentEditorReady(quill) {
  cleanupCommentEditorListeners?.()
  cleanupCommentEditorListeners = bindInlineImageUploadListeners(quill)
}

function htmlToPlainText(html) {
  try {
    const doc = new DOMParser().parseFromString(String(html || ''), 'text/html')
    return String(doc.body?.textContent || '').replace(/\u00A0/g, ' ').trim()
  } catch {
    return String(html || '').trim()
  }
}

function normalizeCommentHtml(html) {
  const safe = sanitizeHtml(String(html || ''))
  return decorateLinksAndLinkifyText(safe)
}

function formatWhen(dt) {
  if (!dt) return ''
  try {
    return new Date(dt).toLocaleString()
  } catch {
    return String(dt)
  }
}

function auditMessage(a) {
  const action = a?.action
  const p = a?.payload ?? {}

  if (action === 'card_moved') {
    const from =
      p?.from_column_name ||
      p?.from_column?.name ||
      (p?.from_column_id ? `col #${p.from_column_id}` : 'otra lista')

    const to =
      p?.to_column_name ||
      p?.to_column?.name ||
      (p?.to_column_id ? `col #${p.to_column_id}` : 'otra lista')

    return `movió la tarjeta de "${from}" a "${to}"`
  }

  if (action === 'card_completed') {
    return 'marcó la tarjeta como finalizada'
  }

  if (action === 'card_marked_incomplete') {
    return 'marcó la tarjeta como incompleta'
  }

  if (action === 'checklist_item_created') {
    const preview = p?.text_preview || p?.text || 'una tarea'
    return `añadió "${preview}" en esta tarjeta`
  }

  if (action === 'checklist_item_updated') {
    const preview = p?.text_preview || p?.text || 'una tarea'
    const changes = Array.isArray(p?.changes) ? p.changes : []

    if (changes.includes('is_done')) {
      const done = Boolean(p?.is_done)
      return done
        ? `ha completado "${preview}" en esta tarjeta`
        : `ha marcado como incompleta "${preview}" en esta tarjeta`
    }

    if (changes.includes('text')) {
      return `ha editado "${preview}" en esta tarjeta`
    }

    return `ha actualizado "${preview}" en esta tarjeta`
  }

  if (action === 'checklist_item_deleted') {
    const preview = p?.text_preview || p?.text || 'una tarea'
    return `eliminó "${preview}" de esta tarjeta`
  }

  if (action === 'attachment_uploaded') {
    const name = p?.name || p?.original_name || 'un archivo'
    return `adjuntó "${name}" a esta tarjeta`
  }

  if (action === 'attachment_deleted') {
    const name = p?.name || p?.original_name || 'un archivo'
    return `eliminó "${name}" de esta tarjeta`
  }

  return 'Actividad'
}

function canDeleteComment(it) {
  if (it?.type !== 'comment') return false
  const currentUserId = Number(auth.user?.id || 0)
  const ownerId = Number(it?.user?.id || 0)
  return Boolean(canDelete.value && (auth.isAdmin || (currentUserId && currentUserId === ownerId)))
}

function shortTextFromHtml(html) {
  try {
    const doc = new DOMParser().parseFromString(String(html || ''), 'text/html')
    const t = String(doc.body?.textContent || '').trim()
    if (t.length <= 70) return t
    return `${t.slice(0, 70)}...`
  } catch {
    const t = String(html || '').trim()
    if (t.length <= 70) return t
    return `${t.slice(0, 70)}...`
  }
}

function startReplyToComment(it) {
  if (!canEdit.value || it?.type !== 'comment') return
  replyingToComment.value = {
    id: Number(it.id),
    userName: String(it?.user?.name || 'Usuario'),
    body: shortTextFromHtml(it?.body),
  }
  isCommentComposerExpanded.value = true
}

function cancelReply() {
  replyingToComment.value = null
}

async function deleteCommentItem(it) {
  const id = Number(it?.id)
  if (!id || deletingCommentById[id]) return

  deletingCommentById[id] = true
  try {
    await CardsApi.deleteComment(props.cardId, id)
    refreshActivitySoon()
    emitCardEvent('comment-deleted')
    emit('saved', { id: props.cardId })
    toasts.push({ type: 'info', message: 'Comentario eliminado.', timeoutMs: 1500 })
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo eliminar el comentario.' })
  } finally {
    deletingCommentById[id] = false
  }
}

function askDeleteCommentItem(it) {
  confirmDelete.open = true
  confirmDelete.kind = 'comment'
  confirmDelete.payload = it
}

function askDeleteChecklistItem(item) {
  if (!canDelete.value) return
  confirmDelete.open = true
  confirmDelete.kind = 'checklist'
  confirmDelete.payload = item
}

function cancelConfirmDelete() {
  if (isConfirmingDelete.value) return
  forceCloseConfirmDelete()
}

function forceCloseConfirmDelete() {
  confirmDelete.open = false
  confirmDelete.kind = null
  confirmDelete.payload = null
}

async function confirmDeleteNow() {
  if (!confirmDelete.open || !confirmDelete.kind || isConfirmingDelete.value) return

  isConfirmingDelete.value = true

  try {
    if (confirmDelete.kind === 'attachment') {
      await deleteAttachmentWithLoading(confirmDelete.payload)
    } else if (confirmDelete.kind === 'comment') {
      await deleteCommentItem(confirmDelete.payload)
    } else if (confirmDelete.kind === 'checklist') {
      await deleteChecklistItemWrapped(confirmDelete.payload)
    }

    forceCloseConfirmDelete()
  } finally {
    isConfirmingDelete.value = false
  }
}

const confirmDeleteTitle = computed(() => {
  if (confirmDelete.kind === 'attachment') return 'Eliminar adjunto'
  if (confirmDelete.kind === 'comment') return 'Eliminar comentario'
  if (confirmDelete.kind === 'checklist') return 'Eliminar tarea'
  return 'Eliminar'
})
const confirmDeleteMessage = computed(() => {
  if (confirmDelete.kind === 'attachment') return '¿Deseas eliminar este adjunto?'
  if (confirmDelete.kind === 'comment') return '¿Deseas eliminar este comentario?'
  if (confirmDelete.kind === 'checklist') return '¿Deseas eliminar esta tarea?'
  return '¿Confirmas esta eliminación?'
})

async function postComment() {
  if (!canEdit.value) return
  const body = normalizeCommentHtml(commentBody.value)
  const plain = htmlToPlainText(body)
  if (!plain) return
  if (plain.length > 5000) {
    toasts.push({ type: 'error', message: 'El comentario es demasiado largo (máx. 5000 caracteres).' })
    return
  }
  if (!body) return

  isPostingComment.value = true
  try {
    const parentId = Number(replyingToComment.value?.id || 0) || null
    await CardsApi.comment(props.cardId, body, { parent_id: parentId })
    commentBody.value = '<p><br></p>'
    isCommentComposerExpanded.value = false
    replyingToComment.value = null

    refreshActivitySoon()
    emitCardEvent('comment-created')
    emit('saved', { id: props.cardId })
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo comentar.' })
  } finally {
    isPostingComment.value = false
  }
}

// ------------------------------------------------------------
// Inline update al board
// ------------------------------------------------------------
function emitInlineUpdate(extra = {}) {
  const labelsLite = selectedLabels.value.map((l) => ({ id: l.id, name: l.name, color: l.color }))
  const membersLite = selectedMembers.value
    .map((u) => (u ? { id: u.id, name: u.name, email: u.email, avatar_url: u.avatar_url, has_avatar: u.has_avatar } : null))
    .filter(Boolean)

  emit('saved', {
    id: props.cardId,
    label_ids: selectedLabelIds.value.slice(),
    labels: labelsLite,
    member_ids: selectedMemberIds.value.slice(),
    members: membersLite,
    ...extra,
  })
}

function getCoverAttachmentImageSrc(a) {
  const id = Number(a?.id)
  if (!id) return null

  const isCurrentCover = Number(form.cover_attachment_id) === id

  return (
    getLocalAttachmentPreview(id) ||
    (isCurrentCover ? localCoverPreviewUrl.value : null) ||
    previewUrlByAttachmentId.value[id] ||
    null
  )
}

function getAttachmentImageSrc(a) {
  const id = Number(a?.id)
  if (!id) return null

  return (
    getLocalAttachmentPreview(id) ||
    previewUrlByAttachmentId.value[id] ||
    null
  )
}

// ------------------------------------------------------------
// Close popovers outside
// ------------------------------------------------------------
function closePopoversOnOutside(ev) {
  const t = ev.target

  if (labelsTriggerEl.value?.contains?.(t)) return
  if (t?.closest?.('.mini-picker__popover')) return

  if (membersTriggerEl.value?.contains?.(t)) return
  if (t?.closest?.('.members-popover')) return

  labelsPopover.close()
  membersPopover.close()
}

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      isCommentComposerExpanded.value = false
      replyingToComment.value = null
    }
    if (open) window.addEventListener('pointerdown', closePopoversOnOutside, true)
    else window.removeEventListener('pointerdown', closePopoversOnOutside, true)
  },
  { immediate: true },
)

watch(
  () => [attachments.value, previewUrlByAttachmentId.value],
  () => {
    for (const a of attachments.value ?? []) {
      const id = Number(a?.id)
      if (!id) continue

      const hasRemotePreview =
        a?.thumb_url ||
        a?.preview_url ||
        previewUrlByAttachmentId.value[id]

      if (hasRemotePreview && getLocalAttachmentPreview(id)) {
        revokeLocalAttachmentPreview(id)
      }
    }
  },
  { deep: true },
)

onBeforeUnmount(() => {
  cleanupDescriptionEditorListeners?.()
  cleanupCommentEditorListeners?.()
  window.removeEventListener('pointerdown', closePopoversOnOutside, true)

  if (activityRefreshTimer) clearTimeout(activityRefreshTimer)
  if (labelsSaveTimer) clearTimeout(labelsSaveTimer)
  if (membersSaveTimer) clearTimeout(membersSaveTimer)
  revokeLocalCoverPreview()
  revokeAllLocalAttachmentPreviews()
})


// Popover refresh
watch(() => labelsQuery.value, () => labelsPopover.refresh())
watch(() => membersQuery.value, () => membersPopover.refresh())
watch(() => filteredBoardLabels.value.length, () => labelsPopover.refresh())
watch(() => filteredBoardMembers.value.length, () => membersPopover.refresh())
</script>

<template>
    <div v-if="isOpen" class="modal-backdrop" @click.self="requestClose">
    <div class="modal modal--card">
      <div
        class="modal-cover"
        :class="[
          form.cover_color ? 'modal-cover--has' : '',
          coverImageUrl ? 'modal-cover--img' : '',
          form.cover_size === 'large' ? 'modal-cover--lg' : '',
        ]"
        :style="
          coverImageUrl
            ? {
                backgroundImage: `url(${coverImageUrl})`,
                backgroundSize: 'contain',
                backgroundPosition: 'center',
                backgroundRepeat: 'no-repeat',
                backgroundColor: modalCoverBg,
              }
            : form.cover_color
              ? { background: form.cover_color }
              : undefined
        "
      >
        <div class="modal-cover__controls">
          <md-text-button
            v-if="canEdit"
            type="button"
            @click="isCoverPopoverOpen = !isCoverPopoverOpen"
          >
            <i class="mdi mdi-image-outline" aria-hidden="true"></i>
          </md-text-button>
          <md-icon-button type="button" title="Cerrar" @click="requestClose">
            <i class="mdi mdi-close" aria-hidden="true"></i>
          </md-icon-button>
        </div>

        <div v-if="isCoverPopoverOpen && canEdit" class="cover-popover" @click.stop>
          <div class="cover-popover__top">
            <div class="cover-popover__title">Portada</div>
            <md-icon-button type="button" title="Cerrar" @click="isCoverPopoverOpen = false">
              <i class="mdi mdi-close" aria-hidden="true"></i>
            </md-icon-button>
          </div>

          <div class="cover-popover__section-title">Tamaño</div>
          <div class="cover-size-row">
            <button
              type="button"
              class="cover-size"
              :class="form.cover_size === 'small' ? 'cover-size--active' : ''"
              @click="setCoverSize('small')"
            >
              <span class="cover-size__thumb" :style="form.cover_color ? { background: form.cover_color } : undefined" />
              <span>Chico</span>
            </button>
            <button
              type="button"
              class="cover-size"
              :class="form.cover_size === 'large' ? 'cover-size--active' : ''"
              @click="setCoverSize('large')"
            >
              <span class="cover-size__thumb cover-size__thumb--lg" :style="form.cover_color ? { background: form.cover_color } : undefined" />
              <span>Grande</span>
            </button>
          </div>

          <md-text-button v-if="form.cover_color || form.cover_attachment_id" type="button" class="cover-popover__btn" @click="removeCover">
            <span>Quitar portada</span>
          </md-text-button>

          <div class="cover-popover__section-title">Colores</div>
          <div class="cover-colors">
            <button
              v-for="c in coverColors"
              :key="c.value"
              type="button"
              class="cover-swatch"
              :class="form.cover_color === c.value ? 'cover-swatch--active' : ''"
              :style="{ background: c.value }"
              :title="c.name"
              @click="setCoverColor(c.value)"
            />
          </div>

          <div class="cover-popover__section-title">Adjuntos (imágenes)</div>
          <div
            class="cover-upload"
            :class="isCoverDragOver ? 'cover-upload--over' : ''"
            role="button"
            tabindex="0"
            @click="openCoverImagePicker"
            @keydown.enter.prevent="openCoverImagePicker"
            @dragenter.prevent="onCoverDragEnter"
            @dragover.prevent="onCoverDragOver"
            @dragleave.prevent="onCoverDragLeave"
            @drop.prevent="onCoverDrop"
          >
            <div class="cover-upload__title">
              <i class="mdi mdi-image-plus" aria-hidden="true"></i>
              <span>Arrastrá una imagen o hacé click para elegir</span>
            </div>
            <div class="cover-upload__hint">JPG / PNG · Máx 5MB · Se aplicará como portada automáticamente</div>
            <div v-if="isCoverUploading" class="cover-upload__progress">
              <md-linear-progress :value="coverUploadProgress / 100"></md-linear-progress>
              <span class="muted">{{ coverUploadProgress }}%</span>
            </div>
          </div>
          <input
            ref="coverUploadInput"
            type="file"
            class="attachments__file"
            accept=".jpg,.jpeg,.png,image/jpeg,image/png"
            :disabled="isCoverUploading || isUploading"
            @change="onCoverImageInputChange"
          />
          <div class="cover-attachments">
            <button
              v-for="a in attachments.filter((x) => String(x?.mime_type || '').startsWith('image/'))"
              :key="a.id"
              type="button"
              class="cover-att"
              :class="Number(form.cover_attachment_id) === Number(a.id) ? 'cover-att--active' : ''"
              @mouseenter="!a.thumb_url ? ensureAttachmentPreview(a) : null"
              @focus="!a.thumb_url ? ensureAttachmentPreview(a) : null"
              @click="setCoverFromAttachment(a)"
            >
            <img
              v-if="getAttachmentImageSrc(a)"
              :src="getAttachmentImageSrc(a)"
              alt=""
            />
              <span v-else class="cover-att__ph">IMG</span>
            </button>
            <div
              v-if="attachments.filter((x) => String(x?.mime_type || '').startsWith('image/')).length === 0"
              class="muted cover-attachments__empty"
            >
              Aun no hay imagenes subidas.
            </div>
          </div>
        </div>
      </div>

      <div v-if="isLoading" class="modal__body modal__body--center">
        <md-circular-progress indeterminate class="my-4 md-spinner-inline-big"></md-circular-progress>
      </div>
      <div v-else-if="!isLoading" class="modal__body">
       
        <div class="modal__layout mx-3">
          <div class="modal__main">
            <div v-if="error" class="alert alert--danger">{{ error }}</div>

            <div class="grid">
              <div class="modal-title-row" :class="isTitleEditing ? 'modal-title-row--editing' : ''">
                <md-checkbox
                  v-if="canEdit"
                  touch-target="wrapper"
                  :checked="form.is_done"
                  @change="toggleDoneNow"
                  class="done-check done-check--edit"
                />
                <span v-else class="done-check done-check--ro" :class="form.is_done ? 'done-check--on' : ''" title="Finalizada"></span>

                <div class="modal-title-wrap">
                  <textarea
                    ref="titleEl"
                    v-model="form.title"
                    class="modal-title-input"
                    :class="[
                      canEdit ? 'modal-title-input--editable' : 'modal-title-input--ro',
                      isTitleEditing ? 'modal-title-input--editing' : '',
                    ]"
                    :disabled="!canEdit"
                    placeholder="Título…"
                    :rows="2"
                    @focus="onTitleFocus"
                    @blur="onTitleBlur"
                    @input="onTitleInput"
                    @keydown.enter.exact.prevent
                  ></textarea>

                  <div class="modal-title-meta" v-if="canEdit">
                    <span class="title-counter" :class="titleCounterClass">
                    <!-- contador -->
                      {{ titleCounterText }}
                    </span>
                    <span v-if="isSavingTitle" class="modal-title-saving">
                      <md-circular-progress indeterminate class="mx-1 md-spinner-inline-md"></md-circular-progress>
                    </span>
                    <span v-else-if="isTitleDirty" class="modal-title-dirty">Sin guardar</span>
                  </div>
                </div>
              </div>
            </div>
            <hr class="mt-2">

            <div class="card-meta-row mt-2">
              <div class="card-meta-group">
                <div class="m-1 card-ui__title">Etiquetas</div>
                <div v-if="isLoadingLabels" class="muted">Cargando etiquetas…</div>
                <div v-else-if="boardLabels.length === 0" class="muted">
                  No hay etiquetas para este proyecto.
                  <md-text-button v-if="auth.isAdmin" type="button" @click="createDefaultLabels">Crear etiquetas base</md-text-button>
                </div>
                <div v-else class="mini-picker">
                  <div class="mini-picker__row mini-picker__row--inline">
                    <div class="mini-picker__selected">
                      <span v-for="l in selectedLabels" :key="l.id" class="label-chip" :style="{ background: l.color || '#091e42' }">
                        {{ l.name }}
                      </span>
                      <span v-if="selectedLabels.length === 0" class="muted">Sin etiquetas.</span>
                    </div>
                  <md-icon-button ref="labelsTriggerEl" type="button" title="Etiquetas" :disabled="!canEdit" @click="labelsPopover.toggle()">
                      <i class="mdi mdi-plus" aria-hidden="true"></i>
                    </md-icon-button>
                  </div>

                  <div v-if="labelsPopover.isOpen.value" class="mini-picker__popover"
                    :class="labelsPopover.openUp.value ? 'mini-picker__popover--up' : ''"
                    :style="{ maxHeight: `${labelsPopover.maxHeight.value}px` }">
                    <input v-model="labelsQuery" class="input input--sm" placeholder="Buscar etiquetas…" />
                    <div class="mini-picker__list">
                      <label v-for="l in filteredBoardLabels" :key="l.id" class="mini-picker__item">
                        <input
                          type="checkbox"
                          :checked="selectedLabelIds.includes(Number(l.id))"
                          :disabled="!canEdit"
                          @change="onToggleLabel(l.id)"
                        />
                        <span class="mini-picker__swatch" :style="{ background: l.color || '#091e42' }" />
                        <span class="mini-picker__name">{{ l.name }}</span>
                      </label>
                    </div>
                    <div class="mini-picker__footer">
                      <md-filled-button v-if="auth.isAdmin" type="button" class="my-3" @click="labelsModalOpen = true" title="Gestionar etiquetas">
                        <i class="mdi mdi-plus" aria-hidden="true"></i>
                        <span class="mx-1">Gestionar etiquetas</span>
                      </md-filled-button>

                      <span v-if="isUpdatingLabels" class="muted">Guardando…</span>
                      <md-text-button type="button" @click="labelsPopover.close()">Cerrar</md-text-button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="card-meta-group">
                <div class="m-1 card-ui__title">Miembros</div>
                <div v-if="isLoadingMembers" class="muted">Cargando miembros…</div>
                <div v-else class="mini-picker">
                  <div class="mini-picker__row mini-picker__row--inline">
                    <div class="mini-picker__selected">
                      <span v-for="u in selectedMembers" :key="u.id" class="mini-avatar" :title="u.name">
                        <img v-if="avatarUrlsByUserId[Number(u.id)]" :src="avatarUrlsByUserId[Number(u.id)]" alt="" />
                        <span v-else>{{ userInitial(u) }}</span>
                      </span>
                      <span v-if="selectedMembers.length === 0" class="muted">Sin miembros.</span>
                    </div>
                    <md-icon-button ref="membersTriggerEl" type="button" title="Miembros" :disabled="!canEdit" @click="membersPopover.toggle()">
                      <i class="mdi mdi-plus" aria-hidden="true"></i>
                    </md-icon-button>
                  </div>

                  <div v-if="membersPopover.isOpen.value"
                    class="mini-picker__popover members-popover"
                    :class="membersPopover.openUp.value ? 'mini-picker__popover--up' : ''"
                    :style="{ maxHeight: `${membersPopover.maxHeight.value}px` }">
                    <input v-model="membersQuery" class="input input--sm" placeholder="Buscar miembros" />

                    <div class="members-popover__section-title">Miembros de la tarjeta</div>
                    <div class="members-popover__list">
                      <button
                        v-for="u in selectedMembers"
                        :key="`selected-${u.id}`"
                        type="button"
                        class="members-popover__selected-item"
                        :disabled="!canEdit || savingMemberByUserId[Number(u.id)]"
                        @click="toggleMemberFromPopover(u)"
                      >
                        <span class="mini-avatar mini-avatar--sm">
                          <img v-if="avatarUrlsByUserId[Number(u.id)]" :src="avatarUrlsByUserId[Number(u.id)]" alt="" />
                          <span v-else>{{ userInitial(u) }}</span>
                        </span>
                        <span class="mini-picker__name">{{ u.name }}</span>
                        <span class="members-popover__right">
                          <md-circular-progress
                            v-if="savingMemberByUserId[Number(u.id)]"
                            indeterminate
                            class="md-spinner-inline-md"
                          ></md-circular-progress>

                          <i v-else class="mdi mdi-close members-popover__remove-icon" aria-hidden="true"></i>
                        </span>
                      </button>
                      <div v-if="selectedMembers.length === 0" class="muted">Sin miembros en la tarjeta.</div>
                    </div>

                    <div class="members-popover__section-title">Miembros del tablero</div>
                    <div class="members-popover__list">
                      <button
                        v-for="u in filteredBoardMembers"
                        :key="`board-${u.id}`"
                        type="button"
                        class="members-popover__member"
                        :class="selectedMemberIds.includes(Number(u.id)) ? 'members-popover__member--active' : ''"
                        :disabled="!canEdit"
                        @click="toggleMemberFromPopover(u)"
                      >
                        <span class="mini-avatar mini-avatar--sm">
                          <img v-if="avatarUrlsByUserId[Number(u.id)]" :src="avatarUrlsByUserId[Number(u.id)]" alt="" />
                          <span v-else>{{ userInitial(u) }}</span>
                        </span>
                        <span class="mini-picker__name">{{ u.name }}</span>
                        <span class="members-popover__right">
                          <md-circular-progress
                            v-if="savingMemberByUserId[Number(u.id)]"
                            indeterminate
                            class="md-spinner-inline-md"
                          ></md-circular-progress>

                          <i
                            v-else-if="selectedMemberIds.includes(Number(u.id))"
                            class="mdi mdi-check members-popover__check"
                            aria-hidden="true"
                          ></i>
                        </span>
                      </button>
                      <div v-if="filteredBoardMembers.length === 0" class="muted">No hay resultados.</div>
                    </div>

                    <div class="mini-picker__footer">
                    <md-text-button type="button" @click="membersPopover.close()">Cerrar</md-text-button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <hr class="mt-4">
            <div class="grid mt-4">
              <div class="field">
                <div class="desc__top">
                  <span class="m-1 card-ui__title">Descripción</span>
                  <md-text-button
                    v-if="canEdit"
                    type="button"
                    @click="isEditingDescription = !isEditingDescription"
                  >
                    <i class="mdi" :class="isEditingDescription ? 'mdi-check' : 'mdi-pencil-outline'" aria-hidden="true"></i>
                    <span>{{ isEditingDescription ? 'Listo' : 'Editar' }}</span>
                  </md-text-button>
                </div>

                <div
                  v-if="!isEditingDescription"
                  class="desc-preview"
                  :class="canEdit ? 'desc-preview--editable' : ''"
                  @click="
                    (ev) => {
                      if (openLinkInNewTab(ev)) return
                      if (canEdit) isEditingDescription = true
                    }
                  "
                >
                  <div v-if="safeDescriptionHtml" class="desc-preview__html" v-html="safeDescriptionHtml"></div>
                  <div v-else class="muted">Sin descripción. {{ canEdit ? 'Hacé click para escribir…' : '' }}</div>
                </div>

                <div v-else class="quill-wrap" ref="quillWrapEl">
                  <QuillEditor
                    v-model:content="form.description"
                    contentType="html"
                    theme="snow"
                    :readOnly="!canEdit"
                    :toolbar="quillToolbar"
                    placeholder="Escribí la descripción…"
                    @ready="onDescriptionEditorReady"
                  />
                </div>
              </div>
            </div>

            <div class="card-ui mt-4">
              <div class="checklist__header">
                <div class="card-ui__title">Tareas</div>
                <div class="checklist__header-actions">
                  <md-text-button type="button" @click="hideCompleted = !hideCompleted">
                    <span>{{ hideCompleted ? 'Mostrar completadas' : 'Ocultar completadas' }}</span>
                  </md-text-button>
                </div>
              </div>

              <div v-if="checklist.length > 0" class="checklist__progress">
                <span class="checklist__progress-pct">
                  {{ Math.round((checklist.filter((i) => i.is_done).length / checklist.length) * 100) }}%
                </span>
                <md-linear-progress
                  :value="checklist.filter((i) => i.is_done).length / checklist.length"
                  style="flex:1"
                ></md-linear-progress>
              </div>

              <div class="checklist">

                <template v-for="(item, idx) in visibleChecklist" :key="item.id">
                  <div
                    v-if="canEdit"
                    class="checklist-drop"
                    @dragover.prevent
                    @drop.prevent="moveChecklistItemTo(idx)"
                  />
                  <div
                    :class="['checklist__item', editingChecklistId === Number(item.id) ? 'checklist__item--editing' : '']"
                    :draggable="canEdit && editingChecklistId !== Number(item.id)"
                    @dragstart="(ev) => onChecklistDragStart(ev, item.id)"
                    @dragend="onChecklistDragEnd"
                  >
                    <div class="check">
                      <input type="checkbox" :checked="item.is_done" :disabled="!canEdit" @change="toggleChecklistItemWrapped(item)" />
                      <div class="check__content">
                        <textarea
                          v-if="editingChecklistId === Number(item.id)"
                          ref="editingChecklistTextareaEl"
                          v-model="editingChecklistText"
                          class="input check__edit"
                          rows="4"
                          :maxlength="checklistTextMax"
                          @keydown.enter.exact.prevent="saveChecklistItemTextWrapped(item)"
                          @keydown.esc.prevent="cancelEditChecklistItem"
                          @input="onChecklistTextareaInput"
                          @click.stop
                        ></textarea>
                        <span
                          v-else
                          :class="['check__text', item.is_done ? 'check__text--done' : '']"
                          v-html="formatChecklistText(item.text)"
                        ></span>
                      </div>
                    </div>
                    <div class="checklist__actions">
                      <div class="checklist__action-row">
                        <md-circular-progress
                          v-if="Boolean(savingChecklistById?.[Number(item.id)])"
                          indeterminate
                          class="md-spinner-inline"
                        ></md-circular-progress>
                        <md-icon-button
                          v-if="canEdit && editingChecklistId === Number(item.id)"
                          type="button"
                          title="Guardar texto"
                          @click="saveChecklistItemTextWrapped(item)"
                        >
                          <i class="mdi mdi-check" aria-hidden="true"></i>
                        </md-icon-button>
                        <md-icon-button
                          v-if="canEdit && editingChecklistId === Number(item.id)"
                          type="button"
                          title="Cancelar edición"
                          @click="cancelEditChecklistItem"
                        >
                          <i class="mdi mdi-close" aria-hidden="true"></i>
                        </md-icon-button>
                        <md-icon-button
                          v-if="canEdit && editingChecklistId !== Number(item.id)"
                          type="button"
                          title="Editar texto"
                          @click="startEditChecklistItem(item)"
                        >
                          <i class="mdi mdi-pencil-outline" aria-hidden="true"></i>
                        </md-icon-button>
                        <md-icon-button
                          v-if="canDelete && editingChecklistId !== Number(item.id)"
                          type="button"
                          title="Eliminar"
                          @click="askDeleteChecklistItem(item)"
                        >
                          <i class="mdi mdi-trash-can-outline" aria-hidden="true"></i>
                        </md-icon-button>
                      </div>
                      <div
                        v-if="editingChecklistId === Number(item.id)"
                        class="checklist__counter"
                        :class="checklistCounterClass"
                      >
                        {{ checklistEditingLength }}/{{ checklistTextMax }}
                      </div>
                    </div>
                  </div>
                </template>

                <div v-if="canEdit" class="checklist-drop checklist-drop--end" @dragover.prevent @drop.prevent="moveChecklistItemTo(visibleChecklist.length)" />
              </div>

              <div v-if="canEdit" class="checklist__add">
                <textarea
                  ref="newChecklistTextareaEl"
                  v-model="newItemText"
                  class="input check__edit checklist__new-text"
                  rows="3"
                  :maxlength="checklistTextMax"
                  placeholder="Nueva tarea…"
                  @keydown.enter.exact.prevent="addChecklistItemWrapped"
                  @input="onNewChecklistTextareaInput"
                ></textarea>
                <div class="checklist__add-actions">
                  <md-filled-button type="button" :disabled="isAdding" @click="addChecklistItemWrapped">
                    <i class="mdi mdi-plus" aria-hidden="true"></i>
                    <span>Agregar</span>
                    <md-circular-progress v-if="isAdding" indeterminate class="md-spinner-inline"></md-circular-progress>
                  </md-filled-button>
                  <div class="checklist__counter" :class="newChecklistCounterClass">
                    {{ newChecklistLength }}/{{ checklistTextMax }}
                  </div>
                </div>
              </div>
            </div>

            <div class="card-ui">
              <div class="card-ui__title">Adjuntos</div>

              <div v-if="attachments.length === 0" class="muted">Sin adjuntos.</div>

              <div class="attachments">
                <div v-for="a in attachments" :key="a.id" class="attachment">
                  <div class="attachment__main">
                    <div class="attachment__name">
                      <span
                        v-if="isImageAttachment(a)"
                        class="attachment__thumb"
                        @mouseenter="!a.thumb_url ? ensureAttachmentPreview(a) : null"
                        @focus="!a.thumb_url ? ensureAttachmentPreview(a) : null"
                      >
                        <img
                          v-if="getAttachmentImageSrc(a)"
                          :src="getAttachmentImageSrc(a)"
                          alt=""
                        />
                        <span v-else class="attachment__thumb-ph">IMG</span>
                      </span>
                      <span v-else class="attachment__file-icon" :title="a.mime_type || 'Archivo'">
                        <i class="mdi" :class="attachmentIconClass(a)" aria-hidden="true"></i>
                      </span>
                      <span>{{ a.original_name }}</span>
                    </div>
                    <div class="attachment__meta">{{ formatBytes(a.size) }} · {{ a.mime_type }}</div>
                  </div>
                  <div class="attachment__actions">
                    <md-text-button type="button" @click="onAttachmentView(a)">
                      <i class="mdi mdi-eye-outline" aria-hidden="true"></i>
                      <span>Ver</span>
                    </md-text-button>
                    <md-text-button type="button" @click="downloadAttachment(a)">
                      <i class="mdi mdi-download-outline" aria-hidden="true"></i>
                      <span>Descargar</span>
                    </md-text-button>
                    <md-circular-progress
                      v-if="canDelete && deletingAttachmentById[Number(a.id)]"
                      indeterminate
                      class="md-spinner-inline-md"
                    ></md-circular-progress>
                    <md-icon-button
                      v-else-if="canDelete"
                      type="button"
                      title="Eliminar"
                      :disabled="deletingAttachmentById[Number(a.id)]"
                      @click="askDeleteAttachment(a)"
                    >
                      <i class="mdi mdi-trash-can-outline" aria-hidden="true"></i>
                    </md-icon-button>
                  </div>
                </div>
              </div>

              <div v-if="canEdit" class="attachments__upload">
                <div
                  class="attachments__dropzone"
                  :class="isDragOverUpload ? 'attachments__dropzone--over' : ''"
                  role="button"
                  tabindex="0"
                  @click="openFilePicker"
                  @keydown.enter.prevent="openFilePicker"
                  @dragenter.prevent="onUploadDragEnter"
                  @dragover.prevent="onUploadDragOver"
                  @dragleave.prevent="onUploadDragLeave"
                  @drop.prevent="onDropUploadWrapped"
                >
                  <div class="attachments__dz-title">
                    <i class="mdi mdi-paperclip" aria-hidden="true"></i>
                    <span>Arrastrá un archivo acá o hacé click para elegir</span>
                  </div>
                  <div class="attachments__dz-hint">PDF / Word / Excel / JPG / PNG · Máx 5MB · 1 archivo</div>
                  <div v-if="isUploading" class="attachments__progress">
                    <md-linear-progress :value="uploadProgress / 100"></md-linear-progress>
                    <div class="muted">{{ uploadProgress }}%</div>
                  </div>
                </div>

                <input
                  ref="uploadInput"
                  type="file"
                  class="attachments__file"
                  accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,image/jpeg,image/png"
                  :disabled="isUploading"
                  @change="uploadAttachmentWrapped"
                />
              </div>
            </div>
          </div>

          <aside class="modal__side">
            <div class="card-ui">
              <div class="card-ui__title">Comentarios y actividad</div>

              <div v-if="canEdit" class="comment-composer">
                <button
                  v-if="!isCommentComposerExpanded"
                  type="button"
                  class="comment-composer__compact"
                  @click="isCommentComposerExpanded = true"
                >
                  Escribe un comentario…
                </button>
                <div
                  v-else
                  class="quill-wrap comment-composer__editor"
                >
                  <QuillEditor
                    v-model:content="commentBody"
                    contentType="html"
                    theme="snow"
                    :readOnly="!canEdit"
                    :toolbar="quillToolbar"
                    placeholder="Escribe un comentario…"
                    @ready="onCommentEditorReady"
                  />
                </div>
                <div v-if="isCommentComposerExpanded && replyingToComment" class="replying-chip">
                  <span class="replying-chip__label">Respondiendo a {{ replyingToComment.userName }}:</span>
                  <span class="replying-chip__text">{{ replyingToComment.body }}</span>
                  <md-text-button type="button" @click="cancelReply">Quitar</md-text-button>
                </div>
                <div v-if="isCommentComposerExpanded" class="composer__actions">
                  <md-filled-button type="button" :disabled="isPostingComment" @click="postComment">
                    <i class="mdi mdi-send-outline" aria-hidden="true"></i>
                    <span class="mx-1">Enviar</span>
                    <md-circular-progress v-if="isPostingComment" indeterminate class="md-spinner-inline-md"></md-circular-progress>
                  </md-filled-button>
                  <md-text-button type="button" @click="isCommentComposerExpanded = false">
                    <span class="mx-1">Cancelar</span>
                  </md-text-button>
                  <md-text-button type="button" @click="loadActivity">
                    <i class="mdi mdi-refresh" aria-hidden="true"></i>
                    <span class="mx-1">Refrescar</span>
                    <md-circular-progress v-if="isLoadingActivity" indeterminate class="md-spinner-inline-md"></md-circular-progress>
                  </md-text-button>
                </div>
              </div>

              <div v-if="visibleActivity.length === 0" class="muted">Sin movimientos ni comentarios todavía.</div>

              <div v-else class="activity">
                <div v-for="it in visibleActivity" :key="`${it.type}-${it.id}`" class="activity__item">
                  <span class="mini-avatar mini-avatar--sm" :title="it.user?.name || ''">
                    <img v-if="avatarUrlsByUserId[Number(it.user?.id)]" :src="avatarUrlsByUserId[Number(it.user?.id)]" alt="" />
                    <span v-else>{{ userInitial(it.user) }}</span>
                  </span>
                  <div class="activity__main">
                    <div class="activity__line">
                      <span class="activity__who">{{ it.user?.name || 'Sistema' }}</span>
                      <span class="activity__did">
                        <template v-if="it.type === 'comment'">comentó</template>
                        <template v-else>{{ auditMessage(it) }}</template>
                      </span>
                    </div>
                    <div v-if="it.type === 'comment' && it.parent_comment" class="activity__replyto">
                      Respuesta a {{ it.parent_comment?.user?.name || 'comentario' }}:
                      "{{ shortTextFromHtml(it.parent_comment?.body) }}"
                    </div>
                    <div
                      v-if="it.type === 'comment'"
                      class="activity__body ql-editor"
                      v-html="normalizeCommentHtml(it.body)"
                      @click="openLinkInNewTab"
                    />
                    <div v-if="it.type === 'comment'" class="activity__actions">
                      <md-text-button type="button" @click="startReplyToComment(it)">
                        <span>Responder</span>
                      </md-text-button>
                      <md-circular-progress
                        v-if="deletingCommentById[Number(it.id)]"
                        indeterminate
                        class="md-spinner-inline-md"
                      ></md-circular-progress>
                      <md-text-button
                        v-else-if="canDeleteComment(it)"
                        type="button"
                        @click="askDeleteCommentItem(it)"
                      >
                        <span>Eliminar</span>
                      </md-text-button>
                    </div>
                    <div class="activity__when">{{ formatWhen(it.created_at) }}</div>
                  </div>
                </div>
              </div>
            </div>
          </aside>
        </div>
      </div>
    </div>
  </div>
  <BoardLabelsModal
    v-if="labelsModalOpen"
    :isOpen="labelsModalOpen"
    :boardId="boardId"
    :isArchived="isArchived"
    @close="labelsModalOpen = false"
    @changed="loadBoard"
  />
  <ImageViewerModal
    :isOpen="imageViewer.isOpen"
    :src="imageViewer.src"
    :title="imageViewer.title"
    @close="closeImageViewer"
  />
  <ConfirmActionModal
    :isOpen="confirmDelete.open"
    :title="confirmDeleteTitle"
    :message="confirmDeleteMessage"
    confirmText="Eliminar"
    cancelText="Cancelar"
    :isLoading="isConfirmingDelete"
    @close="cancelConfirmDelete"
    @confirm="confirmDeleteNow"
  />
</template>

