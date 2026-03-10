import { ref } from 'vue'

export function useCardAttachments({ toasts, canEditRef, AttachmentsApi }) {
  const attachments = ref([])
  const uploadInput = ref(null)
  const isUploading = ref(false)
  const isDragOverUpload = ref(false)
  const uploadDragDepth = ref(0)
  const uploadProgress = ref(0)
  const previewUrlByAttachmentId = ref({})

  const ALLOWED_EXTS = new Set(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'])
  const ALLOWED_MIMES = new Set([
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'image/jpeg',
    'image/png',
  ])
  const MAX_FILE_BYTES = 5 * 1024 * 1024

  function setFromCard(card) {
    attachments.value = (card?.attachments ?? []).slice().sort((a, b) => (b.id ?? 0) - (a.id ?? 0))
  }

  function revokePreview(id) {
    const url = previewUrlByAttachmentId.value[Number(id)]
    if (url) {
      try { URL.revokeObjectURL(url) } catch {}
      delete previewUrlByAttachmentId.value[Number(id)]
    }
  }

  function cleanupPreviews() {
    for (const k of Object.keys(previewUrlByAttachmentId.value)) {
      try { URL.revokeObjectURL(previewUrlByAttachmentId.value[k]) } catch {}
    }
    previewUrlByAttachmentId.value = {}
    AttachmentsApi.revokeAll?.()
  }

  function getExt(name) {
    const s = String(name || '')
    const i = s.lastIndexOf('.')
    return i < 0 ? '' : s.slice(i + 1).toLowerCase()
  }

  function validateAttachmentFile(file) {
    if (!file) return 'No hay archivo.'
    if (file.size > MAX_FILE_BYTES) return 'El archivo supera 5MB.'
    const ext = getExt(file.name)
    const mime = String(file.type || '').toLowerCase()
    const okByExt = ext && ALLOWED_EXTS.has(ext)
    const okByMime = mime && ALLOWED_MIMES.has(mime)
    if (!okByExt && !okByMime) return 'Tipo no permitido. Solo PDF, Word, Excel, JPG/JPEG o PNG (máx 5MB).'
    return null
  }

  function isImageAttachment(a) {
    const mime = String(a?.mime_type || '').toLowerCase()
    return mime.startsWith('image/')
  }

  async function ensureAttachmentPreview(a) {
    if (!isImageAttachment(a)) return null

    const id = Number(a?.id)
    if (!id) return null

    if (previewUrlByAttachmentId.value[id]) {
      return previewUrlByAttachmentId.value[id]
    }

    try {
      const url = await AttachmentsApi.downloadBlobUrl(id)
      if (url) {
        previewUrlByAttachmentId.value[id] = url
        return url
      }
      return null
    } catch {
      return null
    }
  }

  function openFilePicker() {
    if (!canEditRef.value || isUploading.value) return
    uploadInput.value?.click?.()
  }

  function onUploadDragEnter() {
    if (!canEditRef.value || isUploading.value) return
    uploadDragDepth.value += 1
    isDragOverUpload.value = true
  }

  function onUploadDragLeave() {
    if (!canEditRef.value || isUploading.value) return
    uploadDragDepth.value = Math.max(0, uploadDragDepth.value - 1)
    if (uploadDragDepth.value === 0) isDragOverUpload.value = false
  }

  function onUploadDragOver() {
    if (!canEditRef.value || isUploading.value) return
    isDragOverUpload.value = true
  }

  async function uploadAttachment(cardId, ev) {
    const file = ev?.target?.files?.[0]
    if (ev?.target) ev.target.value = ''
    if (!file) return null

    const msg = validateAttachmentFile(file)
    if (msg) {
      toasts.push({ type: 'error', message: msg })
      return null
    }

    isUploading.value = true

    try {
      const body = await AttachmentsApi.uploadToCard(
        cardId,
        file,
        (pct) => { uploadProgress.value = pct }
      )

      const created = body?.data ?? body ?? null

      if (created) {
        attachments.value = [created, ...attachments.value]

        if (isImageAttachment(created)) {
          await ensureAttachmentPreview(created)
        }
      }

      toasts.push({ type: 'success', message: 'Adjunto subido.', timeoutMs: 1800 })

      return created
    } catch (e) {
      toasts.push({ type: 'error', message: e?.message ?? 'No se pudo subir el adjunto.' })
      return null
    } finally {
      isUploading.value = false
      uploadProgress.value = 0
    }
  }
  
  async function onDropUpload(cardId, ev) {
    if (!canEditRef.value || isUploading.value) return null

    const files = Array.from(ev?.dataTransfer?.files ?? [])
    uploadDragDepth.value = 0
    isDragOverUpload.value = false

    if (!files.length) return null
    if (files.length > 1) {
      toasts.push({ type: 'info', message: 'Se subirá solo el primer archivo.' })
    }

    return await uploadAttachment(cardId, {
      target: { files: [files[0]], value: '' },
    })
  }

  async function downloadAttachment(a) {
    try {
      const id = Number(a?.id)
      if (!id) throw new Error('Adjunto inválido')
      const blob = await AttachmentsApi.downloadBlob(id)
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = a.original_name || 'archivo'
      document.body.appendChild(link)
      link.click()
      link.remove()
      URL.revokeObjectURL(url)
    } catch {
      toasts.push({ type: 'error', message: 'No se pudo descargar el adjunto.' })
    }
  }

  async function viewAttachment(a) {
    if (isImageAttachment(a)) {
      await ensureAttachmentPreview(a)
      const url = previewUrlByAttachmentId.value[Number(a.id)]
      if (url) window.open(url, '_blank', 'noopener,noreferrer')
      else await downloadAttachment(a)
      return
    }
    await downloadAttachment(a)
  }

  async function deleteAttachment(a) {
    if (!canEditRef.value) return
    try {
      await AttachmentsApi.destroy(a.id)
      attachments.value = attachments.value.filter((x) => Number(x.id) !== Number(a.id))
      revokePreview(a.id)
      AttachmentsApi.revoke?.(Number(a.id))
      toasts.push({ type: 'info', message: 'Adjunto eliminado.', timeoutMs: 1600 })
    } catch (e) {
      toasts.push({ type: 'error', message: e?.message ?? 'No se pudo eliminar el adjunto.' })
    }
  }

  function formatBytes(n) {
    const num = Number(n || 0)
    if (!num) return '0 B'
    const units = ['B', 'KB', 'MB', 'GB']
    const i = Math.min(Math.floor(Math.log(num) / Math.log(1024)), units.length - 1)
    return `${(num / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1)} ${units[i]}`
  }

  return {
    // state
    attachments,
    uploadInput,
    isUploading,
    isDragOverUpload,
    uploadDragDepth,
    uploadProgress,
    previewUrlByAttachmentId,

    // helpers
    setFromCard,
    cleanupPreviews,
    formatBytes,
    isImageAttachment,
    ensureAttachmentPreview,

    // ui handlers
    openFilePicker,
    onUploadDragEnter,
    onUploadDragLeave,
    onUploadDragOver,
    onDropUpload,
    uploadAttachment,

    // actions
    downloadAttachment,
    viewAttachment,
    deleteAttachment,
  }
}