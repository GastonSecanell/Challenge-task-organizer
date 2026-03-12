import axios from 'axios'

export class ApiError extends Error {
  constructor(message, { status, body, code } = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.body = body
    this.code = code
  }
}

function pickFirstValidationError(body) {
  const errs = body?.errors
  if (!errs || typeof errs !== 'object') return null
  for (const k of Object.keys(errs)) {
    const v = errs[k]
    if (Array.isArray(v) && v.length) return String(v[0])
  }
  return null
}

function getFriendlyErrorMessage(status, body, err) {
  const validationMsg = pickFirstValidationError(body)

  if (status === 422) {
    return validationMsg || body?.message || 'Hay errores de validación.'
  }

  if (status === 401 || status === 419) {
    return 'Tu sesión venció o no es válida. Iniciá sesión nuevamente.'
  }

  if (status === 403) {
    return 'No tenés permisos para realizar esta acción.'
  }

  if (status === 404) {
    return 'No se encontró el recurso solicitado.'
  }

  if (status === 429) {
    return 'Hay demasiadas solicitudes. Esperá un momento e intentá otra vez.'
  }

  if ([500, 502, 503, 504].includes(status)) {
    return 'Hay un problema con el servidor. Intentá nuevamente en unos minutos.'
  }

  if (!status) {
    if (err?.code === 'ECONNABORTED') {
      return 'La solicitud tardó demasiado. Intentá nuevamente.'
    }

    return 'No se pudo conectar con el servidor. Verificá tu conexión o intentá nuevamente.'
  }

  return body?.message || validationMsg || `Error inesperado (${status}).`
}

let _getToken = () => null

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  timeout: 15000,
  headers: { Accept: 'application/json' },
})

export function initHttp({ getToken, baseURL } = {}) {
  if (typeof getToken === 'function') _getToken = getToken

  if (typeof baseURL === 'string' && baseURL.trim()) {
    http.defaults.baseURL = baseURL
  }
}

http.interceptors.request.use((config) => {
  const token = _getToken?.()
  if (token) {
    config.headers = config.headers || {}
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

http.interceptors.response.use(
  (res) => res,
  (err) => {
    const status = err?.response?.status
    const body = err?.response?.data ?? null

    if (status === 401 || status === 419) {
      window.dispatchEvent(new CustomEvent('auth:expired'))
    }

    const msg = getFriendlyErrorMessage(status, body, err)

    throw new ApiError(msg, {
      status,
      body,
      code: err?.code,
    })
  },
)