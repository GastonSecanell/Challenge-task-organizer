import { http } from '@/lib/http'

const cache = new Map()

export async function getAvatarObjectUrl(user) {
  const userId = Number(user?.id)
  const url = user?.avatar_url
  const hasAvatar = Boolean(user?.has_avatar) && Boolean(url)

  if (!userId || !hasAvatar) return null

  const existing = cache.get(userId)
  if (existing?.objectUrl) return existing.objectUrl
  if (existing?.promise) return await existing.promise

  const p = (async () => {
    // axios: responseType 'blob' devuelve Blob en r.data
    const blob = await http
      .get(url, { responseType: 'blob', headers: { Accept: 'image/*' } })
      .then((r) => r.data)

    const objectUrl = URL.createObjectURL(blob)
    cache.set(userId, { objectUrl })
    return objectUrl
  })()

  cache.set(userId, { promise: p })

  try {
    return await p
  } catch {
    cache.delete(userId)
    return null
  }
}

export function revokeAvatarObjectUrl(userId) {
  const id = Number(userId)
  const existing = cache.get(id)
  if (existing?.objectUrl) URL.revokeObjectURL(existing.objectUrl)
  cache.delete(id)
}

export function clearAvatarCache() {
  // copiamos keys primero para no mutar mientras iteramos el Map
  const ids = Array.from(cache.keys())
  for (const id of ids) {
    const entry = cache.get(id)
    if (entry?.objectUrl) URL.revokeObjectURL(entry.objectUrl)
    cache.delete(id)
  }
}