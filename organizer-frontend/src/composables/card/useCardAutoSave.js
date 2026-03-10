import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { decorateLinksAndLinkifyText, sanitizeHtml, openLinkInNewTab } from '@/lib/text/html'

function normalizeHtml(s) {
  return String(s || '').trim()
}

export function useCardAutoSave({
  // required
  propsCardIdRef,
  canEditRef,
  formRef,
  CardsApi,
  toasts,

  // title config
  TITLE_MAX = 80,
  titleDebounceMs = 500,

  // desc config
  descOutsideSave = true,

  // callbacks
  onTitleSaved = null,
  onDescriptionSaved = null,
  mapDescriptionForSave = null,
} = {}) {
  // ---------------------------
  // Title
  // ---------------------------
  const titleEl = ref(null)
  const isTitleEditing = ref(false)
  const isSavingTitle = ref(false)
  const lastSavedTitle = ref('')
  let titleSaveTimer = null

  const titleLen = computed(() => String(formRef.value?.title || '').length)
  const titleCounterText = computed(() => `${titleLen.value}/${TITLE_MAX}`)
  const titleCounterClass = computed(() => {
    const n = titleLen.value
    if (n > TITLE_MAX) return 'title-counter--bad'
    if (n >= TITLE_MAX - 10) return 'title-counter--warn'
    return 'title-counter--ok'
  })
  const isTitleDirty = computed(() => String(formRef.value?.title || '') !== String(lastSavedTitle.value || ''))

  function autosizeTitle() {
    const el = titleEl.value
    if (!el) return
    el.style.height = 'auto'
    el.style.height = `${el.scrollHeight + 2}px`
  }

  function onTitleInput() {
    autosizeTitle()
  }

  function onTitleFocus() {
    if (!canEditRef.value) return
    isTitleEditing.value = true
    nextTick(() => autosizeTitle())
  }

  function onTitleBlur() {
    isTitleEditing.value = false
    nextTick(() => autosizeTitle())
  }

  function queueSaveTitle() {
    if (!canEditRef.value) return
    if (titleSaveTimer) clearTimeout(titleSaveTimer)

    const title = String(formRef.value?.title || '')
    const trimmed = title.trim()
    if (!trimmed) return

    if (title.length > TITLE_MAX) formRef.value.title = title.slice(0, TITLE_MAX)

    titleSaveTimer = setTimeout(async () => {
      if (String(formRef.value?.title || '') === String(lastSavedTitle.value || '')) return
      isSavingTitle.value = true
      try {
        await CardsApi.update(propsCardIdRef.value, { title: formRef.value.title })
        lastSavedTitle.value = formRef.value.title
        if (typeof onTitleSaved === 'function') onTitleSaved(formRef.value.title)
      } catch (e) {
        toasts.push({ type: 'error', message: e?.message ?? 'No se pudo guardar el título.' })
      } finally {
        isSavingTitle.value = false
      }
    }, titleDebounceMs)
  }

  watch(
    () => formRef.value?.title,
    () => queueSaveTitle(),
  )

  // ---------------------------
  // Description
  // ---------------------------
  const isEditingDescription = ref(false)
  const lastSavedDescription = ref('')
  const isSavingDescription = ref(false)
  const quillWrapEl = ref(null)

  const isDescriptionDirty = computed(() =>
    String(formRef.value?.description || '') !== String(lastSavedDescription.value || ''),
  )

  const safeDescriptionHtml = computed(() => {
    const html = formRef.value?.description || ''
    const sanitized = sanitizeHtml(html)
    return decorateLinksAndLinkifyText(sanitized)
  })

  async function saveDescriptionNow({ silentIfNoChanges = true } = {}) {
    if (!canEditRef.value) return
    if (isSavingDescription.value) return

    const descriptionRaw = formRef.value?.description
    const descriptionForSave = typeof mapDescriptionForSave === 'function'
      ? await mapDescriptionForSave(descriptionRaw)
      : descriptionRaw
    const previousRaw = lastSavedDescription.value
    const previousForCompare = typeof mapDescriptionForSave === 'function'
      ? await mapDescriptionForSave(previousRaw)
      : previousRaw

    const next = normalizeHtml(descriptionForSave)
    const prev = normalizeHtml(previousForCompare)

    if (next === prev) {
      if (!silentIfNoChanges) toasts.push({ type: 'info', message: 'No hay cambios.', timeoutMs: 1200 })
      return
    }

    isSavingDescription.value = true
    try {
      await CardsApi.update(propsCardIdRef.value, { description: descriptionForSave })
      // Keep dirty-state UX stable with what the user currently sees/edits.
      lastSavedDescription.value = formRef.value.description
      if (typeof onDescriptionSaved === 'function') onDescriptionSaved(descriptionForSave)
      toasts.push({ type: 'success', message: 'Descripción guardada.', timeoutMs: 1600 })
    } catch (e) {
      toasts.push({ type: 'error', message: e?.message ?? 'No se pudo guardar la descripción.' })
    } finally {
      isSavingDescription.value = false
    }
  }

  function isClickInsideDescriptionArea(ev) {
    const el = quillWrapEl.value
    if (!el) return false
    return el.contains(ev.target)
  }

  async function onDocPointerDown(ev) {
    if (!descOutsideSave) return
    if (!isEditingDescription.value) return
    if (isClickInsideDescriptionArea(ev)) return
    await saveDescriptionNow()
    isEditingDescription.value = false
  }

  watch(
    () => isEditingDescription.value,
    (open) => {
      if (!descOutsideSave) return
      if (open) window.addEventListener('pointerdown', onDocPointerDown, true)
      else window.removeEventListener('pointerdown', onDocPointerDown, true)
    },
  )

  onBeforeUnmount(() => {
    if (titleSaveTimer) clearTimeout(titleSaveTimer)
    window.removeEventListener('pointerdown', onDocPointerDown, true)
  })

  return {
    // title
    titleEl,
    TITLE_MAX,
    isTitleEditing,
    isSavingTitle,
    lastSavedTitle,
    isTitleDirty,
    titleLen,
    titleCounterText,
    titleCounterClass,
    autosizeTitle,
    onTitleInput,
    onTitleFocus,
    onTitleBlur,

    // description
    quillWrapEl,
    isEditingDescription,
    isSavingDescription,
    lastSavedDescription,
    isDescriptionDirty,
    safeDescriptionHtml,
    saveDescriptionNow,

    // shared
    openLinkInNewTab,
  }
}