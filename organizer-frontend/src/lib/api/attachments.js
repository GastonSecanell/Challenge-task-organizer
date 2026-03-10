import { http } from '@/lib/http'

const previewCache = new Map() // id -> objectUrl (para imágenes)
const ATTACHMENT_DOWNLOAD_RE = /\/api\/attachments\/(\d+)\/download(?:\?.*)?$/i

export const AttachmentsApi = {
  getDownloadUrl(attachmentOrId) {
    const id = Number(
      typeof attachmentOrId === 'object'
        ? (attachmentOrId?.id ?? attachmentOrId?.attachment_id)
        : attachmentOrId,
    )
    if (!id) return null
    return `/api/attachments/${id}/download`
  },

  extractAttachmentIdFromDownloadUrl(url) {
    const raw = String(url || '').trim()
    if (!raw) return null

    try {
      const u = raw.startsWith('http://') || raw.startsWith('https://')
        ? new URL(raw)
        : new URL(raw, window.location.origin)
      const m = u.pathname.match(ATTACHMENT_DOWNLOAD_RE)
      const id = Number(m?.[1] ?? 0)
      return id || null
    } catch {
      const m = raw.match(ATTACHMENT_DOWNLOAD_RE)
      const id = Number(m?.[1] ?? 0)
      return id || null
    }
  },

  async resolveInlineAttachmentImages(html) {
    const source = String(html || '')
    if (!source) return ''

    try {
      const doc = new DOMParser().parseFromString(source, 'text/html')
      const imgs = Array.from(doc.querySelectorAll('img[src]'))
      if (!imgs.length) return doc.body.innerHTML

      const swaps = imgs.map(async (img) => {
        const currentSrc = String(img.getAttribute('src') || '')
        const aid = this.extractAttachmentIdFromDownloadUrl(currentSrc)
        if (!aid) return
        try {
          const blobUrl = await this.downloadBlobUrl(aid)
          if (blobUrl) img.setAttribute('src', blobUrl)
        } catch {
          // ignore single-image failures
        }
      })

      await Promise.allSettled(swaps)
      return doc.body.innerHTML
    } catch {
      return source
    }
  },

  async downloadBlobUrl(attachmentId) {
    const id = Number(attachmentId)
    if (!id) return null

    if (previewCache.has(id)) {
      return previewCache.get(id)
    }

    const res = await http.get(`/api/attachments/${id}/download`, {
      responseType: 'blob',
      headers: { Accept: 'image/*' },
    })

    const objUrl = URL.createObjectURL(res.data)
    previewCache.set(id, objUrl)

    return objUrl
  },

  async downloadBlob(attachmentId) {
    const id = Number(attachmentId)
    if (!id) return null

    const res = await http.get(`/api/attachments/${id}/download`, {
      responseType: 'blob',
      headers: { Accept: 'application/octet-stream' },
    })

    return res.data
  },

  downloadBlobByUrl(url) {
    return http.get(url, { responseType: 'blob' }).then((r) => r.data)
  },

  async uploadToCard(cardId, file, onProgress) {
    const id = Number(cardId)
    if (!id || !file) return null
  
    const fd = new FormData()
    fd.append('file', file)
  
    const res = await http.post(`/api/cards/${id}/attachments`, fd, {
      headers: {
        Accept: 'application/json',
        // NO seteamos Content-Type acá
      },
  
      // clave: si tu instancia http mete application/json por default,
      // esto lo elimina para este request
      transformRequest: [(data, headers) => {
        if (headers) {
          delete headers['Content-Type']
          delete headers['content-type']
        }
        return data
      }],
  
      onUploadProgress: (e) => {
        if (!e.total) return
        const pct = Math.max(0, Math.min(100, Math.round((e.loaded / e.total) * 100)))
        onProgress?.(pct)
      },
    })
  
    return res.data
  },

  destroy(attachmentId) {
    const id = Number(attachmentId)
    return http.delete(`/api/attachments/${id}`).then((r) => r.data)
  },

  revoke(attachmentId) {
    const id = Number(attachmentId)
    const url = previewCache.get(id)

    if (url) {
      URL.revokeObjectURL(url)
    }

    previewCache.delete(id)
  },

  revokeAll() {
    for (const url of previewCache.values()) {
      URL.revokeObjectURL(url)
    }
    previewCache.clear()
  },
}