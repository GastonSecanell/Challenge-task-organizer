import { onBeforeUnmount, reactive, ref, watch } from 'vue'

/**
 * useCardModalLoader
 * - Maneja load() completo del modal Card
 * - Evita carreras (invalidateLoads)
 * - Limpieza al cerrar
 */
export function useCardModalLoader({
  // refs de entrada
  cardIdRef,
  isOpenRef,

  // APIs
  CardsApi,
  LabelsApi,
  BoardsApi,

  // helpers
  unwrapList,
  unwrapItem,
  sortByPosition,

  // attachments hook (para setear adjuntos / limpiar previews)
  setFromCard,
  cleanupPreviews,
  uploadDragDepth,
  isDragOverUpload,

  // popovers UI (para cerrar al cerrar modal)
  labelsPopover,
  membersPopover,
  isCoverPopoverOpen,

  // extras
  ensureAvatar, // async (user) => void
  clearPendingMemberSaves, // () => void
} = {}) {
  // ------------------------------------------------------------
  // State base (antes estaba en el componente)
  // ------------------------------------------------------------
  const isLoading = ref(false)
  const error = ref(null)

  const card = ref(null)
  const boardId = ref(null)

  const form = reactive({
    title: '',
    description: '',
    due_date: '', // yyyy-mm-dd
    cover_color: null,
    cover_attachment_id: null,
    cover_size: 'small',
    is_done: false,
  })

  const checklist = ref([])

  // labels / members data (los devolvemos para que el componente lo use)
  const boardLabels = ref([])
  const selectedLabelIds = ref([])
  const isLoadingLabels = ref(false)

  const members = ref([])
  const allUsers = ref([])
  const selectedMemberIds = ref([])
  const isLoadingMembers = ref(false)

  // activity
  const activity = ref([])
  const isLoadingActivity = ref(false)

  function isoToDateInput(iso) {
    if (!iso) return ''
    return String(iso).slice(0, 10)
  }

  // ------------------------------------------------------------
  // Evita que respuestas viejas pisen estado (cierre/abrir rápido)
  // ------------------------------------------------------------
  let loadReq = 0
  function invalidateLoads() {
    loadReq += 1
    return loadReq
  }

  // ------------------------------------------------------------
  // Activity
  // ------------------------------------------------------------
  async function loadActivity() {
    if (!isOpenRef?.value) return
    isLoadingActivity.value = true
    try {
      const body = await CardsApi.activity(Number(cardIdRef.value))
      activity.value = unwrapList(body).slice()
      // Prefetch avatars en actividad
      if (ensureAvatar) {
        for (const it of activity.value) {
          if (it?.user) await ensureAvatar(it.user)
        }
      }
    } catch {
      activity.value = []
    } finally {
      isLoadingActivity.value = false
    }
  }

  // ------------------------------------------------------------
  // Load Board (labels embedded en board.get, si lo usás)
  // ------------------------------------------------------------
  async function loadBoard() {
    error.value = null
    const bid = Number(boardId.value)
    if (!bid) return
    try {
      const body = await BoardsApi.get(bid)
      const b = unwrapItem(body) || {}
      boardLabels.value = sortByPosition(b.labels ?? [])
    } catch (e) {
      error.value = e?.message ?? String(e)
    }
  }

  // ------------------------------------------------------------
  // LOAD principal
  // ------------------------------------------------------------
  async function load() {
    if (!isOpenRef?.value) return

    const reqId = invalidateLoads()
    isLoading.value = true
    error.value = null

    try {
      // 1) Card
      const body = await CardsApi.get(Number(cardIdRef.value))
      if (reqId !== loadReq) return

      const c = unwrapItem(body)
      card.value = c
      boardId.value = c?.board_id ?? null

      form.title = c?.title ?? ''
      form.description = c?.description ?? ''
      form.due_date = isoToDateInput(c?.due_at)
      form.cover_color = c?.cover_color ?? null
      form.cover_attachment_id = c?.cover_attachment_id ?? null
      form.cover_size = c?.cover_size ?? 'small'
      form.is_done = Boolean(c?.is_done)

      checklist.value = sortByPosition(c?.checklist_items ?? [])

      // adjuntos/preview state viene de useCardAttachments
      if (setFromCard) setFromCard(c)

      selectedLabelIds.value = (c?.label_ids ?? []).map((x) => Number(x))
      selectedMemberIds.value = (c?.member_ids ?? []).map((x) => Number(x))

      // fallback (por si api no trae member_ids)
      if (
        selectedMemberIds.value.length === 0 &&
        Array.isArray(c?.members) &&
        c.members.length > 0
      ) {
        selectedMemberIds.value = c.members.map((m) => Number(m.id))
      }
      if (selectedMemberIds.value.length === 0 && c?.assigned_user_id) {
        selectedMemberIds.value = [Number(c.assigned_user_id)]
      }

      // 2) Board labels + members
      if (boardId.value) {
        isLoadingLabels.value = true
        try {
          const lb = await LabelsApi.listByBoard(Number(boardId.value))
          if (reqId !== loadReq) return
          boardLabels.value = sortByPosition(unwrapList(lb))
        } finally {
          isLoadingLabels.value = false
        }

        isLoadingMembers.value = true
        try {
          const mb = await BoardsApi.members(Number(boardId.value))
          if (reqId !== loadReq) return
          members.value = unwrapList(mb)
            .slice()
            .sort((a, b) => String(a?.name || '').localeCompare(String(b?.name || '')))
          allUsers.value = []
        } finally {
          isLoadingMembers.value = false
        }
      } else {
        boardLabels.value = []
        members.value = []
        allUsers.value = []
      }

      // 3) Activity
      await loadActivity()

      // 4) Prefetch avatars (members + selected)
      if (ensureAvatar) {
        for (const u of members.value ?? []) await ensureAvatar(u)
        // selectedMemberIds → buscamos user en members/allUsers/card.members si existe
        const byId = new Map()
        for (const u of members.value ?? []) byId.set(Number(u.id), u)
        for (const u of allUsers.value ?? []) byId.set(Number(u.id), u)
        for (const u of card.value?.members ?? []) byId.set(Number(u.id), u)

        for (const uid of selectedMemberIds.value ?? []) {
          const u = byId.get(Number(uid))
          if (u) await ensureAvatar(u)
        }
      }
    } catch (e) {
      if (reqId !== loadReq) return
      error.value = e?.message ?? String(e)
    } finally {
      if (reqId !== loadReq) return
      isLoading.value = false
    }
  }

  // ------------------------------------------------------------
  // Cleanup al cerrar
  // ------------------------------------------------------------
  function cleanupOnClose() {
    invalidateLoads()

    // cleanup previews (attachments)
    if (cleanupPreviews) cleanupPreviews()

    // reset drag upload
    if (uploadDragDepth) uploadDragDepth.value = 0
    if (isDragOverUpload) isDragOverUpload.value = false

    // limpiar flags de saving members
    if (clearPendingMemberSaves) clearPendingMemberSaves()

    // cerrar popovers
    if (labelsPopover) labelsPopover.close()
    if (membersPopover) membersPopover.close()
    if (isCoverPopoverOpen) isCoverPopoverOpen.value = false
  }

  // watch apertura/cierre
  const stop = watch(
    () => [isOpenRef?.value, cardIdRef?.value],
    ([open]) => {
      if (open) {
        load()
        return
      }
      cleanupOnClose()
    },
    { immediate: true },
  )

  onBeforeUnmount(() => {
    stop?.()
  })

  return {
    // state
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
    allUsers,
    selectedMemberIds,
    isLoadingMembers,

    activity,
    isLoadingActivity,

    // fns
    load,
    loadBoard,
    loadActivity,
    cleanupOnClose,
    invalidateLoads,
  }
}