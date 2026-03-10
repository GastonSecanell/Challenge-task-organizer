export const AVATAR_MAX_BYTES = 3 * 1024 * 1024
export const AVATAR_ALLOWED_TYPES = new Set([
  'image/jpeg',
  'image/png',
  'image/webp',
])

export function validateAvatarFile(file) {
  if (!file) return 'No hay archivo seleccionado.'
  if (Number(file.size || 0) > AVATAR_MAX_BYTES) return 'La imagen supera 3MB.'

  const mime = String(file.type || '').toLowerCase()
  if (!AVATAR_ALLOWED_TYPES.has(mime)) {
    return 'Formato no permitido. Usa JPG, PNG o WEBP.'
  }

  return null
}