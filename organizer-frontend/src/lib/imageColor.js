/**
 * imageColor.js
 * Extrae el color dominante (promedio ponderado) de una imagen
 * usando Canvas API. Compatible con blob: URLs generadas con auth.
 *
 * Devuelve una string CSS `rgb(r, g, b)` ligeramente oscurecida
 * para usarla como fondo sólido detrás de la imagen.
 */

const _cache = new Map()

/**
 * Extrae el color dominante de una URL de imagen (incluye blob:).
 * @param {string|null} url
 * @param {object} [opts]
 * @param {number} [opts.sampleSize=48]   Tamaño del canvas de muestreo
 * @param {number} [opts.darkenFactor=0.7] Factor de oscurecimiento (0–1)
 * @returns {Promise<string|null>}  Ej: "rgb(24, 56, 112)"
 */
export async function extractDominantColor(url, opts = {}) {
  if (!url) return null
  if (_cache.has(url)) return _cache.get(url)

  try {
    const color = await _sample(url, opts)
    if (color) _cache.set(url, color)
    return color
  } catch {
    return null
  }
}

/**
 * Limpia una entrada del caché (útil cuando se revoca un blob:URL).
 */
export function invalidateImageColor(url) {
  if (url) _cache.delete(url)
}

// ─── Internals ────────────────────────────────────────────────────────────────

function _sample(url, { sampleSize = 100, darkenFactor = 0.95 } = {}) {
  return new Promise((resolve, reject) => {
    const img = new Image()

    img.onload = () => {
      try {
        const canvas = document.createElement('canvas')
        canvas.width = sampleSize
        canvas.height = sampleSize
        const ctx = canvas.getContext('2d')
        if (!ctx) return resolve(null)

        ctx.drawImage(img, 0, 0, sampleSize, sampleSize)
        const { data } = ctx.getImageData(0, 0, sampleSize, sampleSize)

        let r = 0, g = 0, b = 0, n = 0
        for (let i = 0; i < data.length; i += 4) {
          const alpha = data[i + 3]
          if (alpha < 64) continue // ignorar casi-transparentes
          // peso por alpha para no distorsionar bordes
          const w = alpha / 255
          r += data[i] * w
          g += data[i + 1] * w
          b += data[i + 2] * w
          n += w
        }

        if (n < 1) return resolve(null)

        // Promedio + oscurecer para contraste
        const R = Math.round((r / n) * darkenFactor)
        const G = Math.round((g / n) * darkenFactor)
        const B = Math.round((b / n) * darkenFactor)

        resolve(`rgb(${R},${G},${B})`)
      } catch (e) {
        reject(e)
      }
    }

    img.onerror = () => reject(new Error('imageColor: no se pudo cargar la imagen'))
    img.src = url
  })
}
