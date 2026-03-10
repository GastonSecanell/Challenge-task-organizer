import { computed, reactive, ref } from 'vue'
import DOMPurify from 'dompurify'
import { escapeHtml, openLinkInNewTab } from '@/lib/text/html'

export function useCardChecklist({
  canEditRef,
  cardIdRef,
  checklistRef,
  ChecklistApi,
  toasts,
  errorRef,
  reloadFn, // opcional: si querés recargar en error (ej: loadBoard/loadCard)
} = {}) {
  const CHECKLIST_TEXT_MAX = 5000
  const newItemText = ref('')
  const hideCompleted = ref(false)
  const savingChecklistById = ref({})
  const draggingChecklistId = reactive({ id: null })
  const editingChecklistId = ref(null)
  const editingChecklistText = ref('')

  function formatChecklistText(text) {
    const escaped = escapeHtml(text)
    const codes = []
    const withCodePlaceholders = escaped.replaceAll(/`([^`]+)`/g, (_m, code) => {
      const key = `__CODE_${codes.length}__`
      codes.push(`<code class="check__code">${code}</code>`)
      return key
    })

    const withLinks = withCodePlaceholders.replaceAll(
      /(https?:\/\/[^\s<]+)/g,
      (m) => `<a href="${m}" target="_blank" rel="noopener noreferrer">${m}</a>`,
    )

    let html = withLinks
    for (let i = 0; i < codes.length; i += 1) html = html.replaceAll(`__CODE_${i}__`, codes[i])

    return DOMPurify.sanitize(html, { USE_PROFILES: { html: true }, ADD_ATTR: ['target', 'rel', 'class'] })
  }

  const visibleChecklist = computed(() => {
    const all = checklistRef.value ?? []
    const sorted = all.slice().sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
    return hideCompleted.value ? sorted.filter((i) => !i.is_done) : sorted
  })

  async function addChecklistItem() {
    if (!canEditRef.value) return
    const text = newItemText.value.trim()
    if (!text) return
    if (text.length > CHECKLIST_TEXT_MAX) {
      toasts.push({ type: 'warning', message: `Máximo ${CHECKLIST_TEXT_MAX} caracteres.` })
      return
    }
    newItemText.value = ''

    try {
      const list = checklistRef.value ?? []
      const lastPos = list.length ? (list[list.length - 1].position ?? 0) : 0
      const body = await ChecklistApi.create(cardIdRef.value, { text, position: lastPos + 100 })
      const created = body?.data ?? body ?? null
      if (created) checklistRef.value = [...list, created].slice().sort((a, b) => (a.position ?? 0) - (b.position ?? 0))
      toasts.push({ type: 'success', message: 'Tarea agregada.', timeoutMs: 1600 })
    } catch (e) {
      if (errorRef) errorRef.value = e?.message ?? String(e)
      toasts.push({ type: 'error', message: e?.message ?? 'No se pudo agregar la tarea.' })
    }
  }

  async function toggleItem(item) {
    if (!canEditRef.value) return
    try {
      const body = await ChecklistApi.update(item.id, { is_done: !item.is_done })
      const updated = (body?.data ?? body) || { ...item, is_done: !item.is_done }
      checklistRef.value = (checklistRef.value ?? []).map((x) => (Number(x.id) === Number(item.id) ? updated : x))
      toasts.push({ type: 'success', message: 'Checklist actualizado.', timeoutMs: 1400 })
    } catch (e) {
      if (errorRef) errorRef.value = e?.message ?? String(e)
      toasts.push({ type: 'error', message: e?.message ?? 'No se pudo actualizar el checklist.' })
    }
  }

  function startEditChecklistItem(item) {
    if (!canEditRef.value) return
    editingChecklistId.value = Number(item?.id)
    editingChecklistText.value = String(item?.text ?? '').slice(0, CHECKLIST_TEXT_MAX)
  }

  function cancelEditChecklistItem() {
    editingChecklistId.value = null
    editingChecklistText.value = ''
  }

  async function saveChecklistItemText(item) {
    if (!canEditRef.value) return
    const id = Number(item?.id)
    if (!id) return

    const text = editingChecklistText.value.trim()
    if (!text) {
      toasts.push({ type: 'warning', message: 'El texto no puede estar vacío.' })
      return
    }
    if (text.length > CHECKLIST_TEXT_MAX) {
      toasts.push({ type: 'warning', message: `Máximo ${CHECKLIST_TEXT_MAX} caracteres.` })
      return
    }
    if (text === String(item?.text ?? '').trim()) {
      cancelEditChecklistItem()
      return
    }

    savingChecklistById.value[id] = true
    try {
      const body = await ChecklistApi.update(id, { text })
      const updated = (body?.data ?? body) || { ...item, text }
      checklistRef.value = (checklistRef.value ?? []).map((x) => (Number(x.id) === id ? updated : x))
      toasts.push({ type: 'success', message: 'Texto de tarea actualizado.', timeoutMs: 1400 })
      cancelEditChecklistItem()
    } catch (e) {
      toasts.push({ type: 'error', message: e?.message ?? 'No se pudo actualizar la tarea.' })
    } finally {
      savingChecklistById.value[id] = false
    }
  }

  function onChecklistTextClick(item, ev) {
    if (openLinkInNewTab(ev)) return
    startEditChecklistItem(item)
  }

  function onChecklistDragStart(ev, itemId) {
    if (!canEditRef.value) return
    draggingChecklistId.id = Number(itemId)
    try {
      ev.dataTransfer?.setData('text/checklist-id', String(itemId))
      ev.dataTransfer.effectAllowed = 'move'
    } catch {
      // ignore
    }
  }

  function onChecklistDragEnd() {
    draggingChecklistId.id = null
  }

  function computeChecklistPos(list, draggedId, targetIndex) {
    const items = (list ?? []).filter((i) => Number(i.id) !== Number(draggedId))
    const idx = Math.max(0, Math.min(Number(targetIndex), items.length))
    const prev = idx > 0 ? Number(items[idx - 1]?.position ?? 0) : null
    const next = idx < items.length ? Number(items[idx]?.position ?? 0) : null
    if (prev === null && next === null) return 100
    if (prev === null) return next - 100
    if (next === null) return prev + 100
    if (prev === next) return prev + 0.0001
    return (prev + next) / 2
  }

  async function moveChecklistItemTo(targetIndex) {
    const draggedId = draggingChecklistId.id
    if (!canEditRef.value || !draggedId) return

    const pos = computeChecklistPos(visibleChecklist.value, draggedId, targetIndex)
    checklistRef.value = (checklistRef.value ?? []).map((i) =>
      Number(i.id) === Number(draggedId) ? { ...i, position: pos } : i,
    )

    savingChecklistById.value[draggedId] = true
    try {
      await ChecklistApi.update(draggedId, { position: pos })
      toasts.push({ type: 'info', message: 'Checklist reordenado.', timeoutMs: 1200 })
    } catch (e) {
      toasts.push({ type: 'error', message: e?.message ?? 'No se pudo reordenar el checklist.' })
      if (typeof reloadFn === 'function') await reloadFn()
    } finally {
      savingChecklistById.value[draggedId] = false
      draggingChecklistId.id = null
    }
  }

  async function deleteItem(item) {
    if (!canEditRef.value) return
    try {
      await ChecklistApi.destroy(item.id)
      checklistRef.value = (checklistRef.value ?? []).filter((x) => Number(x.id) !== Number(item.id))
      toasts.push({ type: 'info', message: 'Tarea eliminada.', timeoutMs: 1400 })
    } catch (e) {
      if (errorRef) errorRef.value = e?.message ?? String(e)
      toasts.push({ type: 'error', message: e?.message ?? 'No se pudo eliminar la tarea.' })
    }
  }

  return {
    newItemText,
    hideCompleted,
    savingChecklistById,
    draggingChecklistId,
    editingChecklistId,
    editingChecklistText,
    checklistTextMax: CHECKLIST_TEXT_MAX,

    visibleChecklist,

    formatChecklistText,
    onChecklistTextClick,

    addChecklistItem,
    toggleItem,
    startEditChecklistItem,
    cancelEditChecklistItem,
    saveChecklistItemText,

    onChecklistDragStart,
    onChecklistDragEnd,
    moveChecklistItemTo,
    deleteItem,
  }
}