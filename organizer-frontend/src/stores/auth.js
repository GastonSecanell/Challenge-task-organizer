import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { http } from '@/lib/http'

const LS_TOKEN = 'board.token'
const LS_USER = 'board.user'

function safeParseJSON(raw, fallback = null) {
  try {
    return raw ? JSON.parse(raw) : fallback
  } catch {
    return fallback
  }
}

function normalizeAuthPayload(payload) {
  // soporta:
  // 1) { token, user }
  // 2) { data: { token, user } }
  // 3) { data: { data: { token, user } } } (por si tenés wrappers)
  const p = payload?.data?.data ?? payload?.data ?? payload ?? {}
  return {
    token: p?.token ?? '',
    user: p?.user ?? null,
  }
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem(LS_TOKEN) || '')
  const user = ref(safeParseJSON(localStorage.getItem(LS_USER), null))

  const isAuthenticated = computed(() => Boolean(token.value))

  const role = computed(() => {
    const fromRoleId = Number(user.value?.role_id || 0)
    if (fromRoleId) return fromRoleId
    const fromLegacy = Number(user.value?.role || 0)
    if (fromLegacy) return fromLegacy
    return Number(user.value?.roles?.[0]?.id || 0)
  })
  const isAdmin = computed(() => role.value === 1)
  const canManageUsers = computed(() => role.value === 1)
  const canReadAudit = computed(() => role.value === 1 || role.value === 4)
  const canManageBoards = computed(() => role.value === 1 || role.value === 2)
  const canManageColumns = computed(() => role.value === 1 || role.value === 2)
  const canWriteCards = computed(() => role.value === 1 || role.value === 2 || role.value === 3)
  const canDeleteCards = computed(() => role.value === 1 || role.value === 2)
  const canManageCards = computed(() => canWriteCards.value) // compat

  function setSession({ token: newToken, user: newUser }) {
    token.value = newToken || ''
    user.value = newUser || null

    localStorage.setItem(LS_TOKEN, token.value)
    localStorage.setItem(LS_USER, JSON.stringify(user.value))
  }

  function clearSession() {
    token.value = ''
    user.value = null
    localStorage.removeItem(LS_TOKEN)
    localStorage.removeItem(LS_USER)
  }

  // LOGIN: no requiere token; usamos http igual (interceptor no agrega Authorization si token vacío)
  async function login({ email, password }) {
    const res = await http.post('/api/login', {
      email,
      password,
      device_name: 'web',
    })

    const { token: newToken, user: newUser } = normalizeAuthPayload(res)
    setSession({ token: newToken, user: newUser })
    return newUser
  }

  // ME: requiere token (interceptor lo agrega)
  async function fetchMe() {
    if (!token.value) return null

    const res = await http.get('/api/me')
    // soporta {user} o {data:{user}}
    const u = res?.data?.user ?? res?.data?.data?.user ?? res?.data?.data ?? null

    user.value = u
    localStorage.setItem(LS_USER, JSON.stringify(user.value))
    return user.value
  }

  async function logout() {
    if (!token.value) return
    try {
      await http.post('/api/logout')
    } finally {
      clearSession()
    }
  }

  return {
    token,
    user,
    role,
    isAuthenticated,
    isAdmin,
    canManageUsers,
    canReadAudit,
    canManageBoards,
    canManageColumns,
    canWriteCards,
    canDeleteCards,
    canManageCards,
    setSession,
    clearSession,
    login,
    logout,
    fetchMe,
  }
})