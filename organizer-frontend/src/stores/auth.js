import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { AuthApi } from '@/lib/api/auth'

const LS_TOKEN = 'tareas.token'
const LS_USER = 'tareas.user'

function safeParseJSON(raw, fallback = null) {
  try {
    return raw ? JSON.parse(raw) : fallback
  } catch {
    return fallback
  }
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref(localStorage.getItem(LS_TOKEN) || '')
  const user = ref(safeParseJSON(localStorage.getItem(LS_USER), null))

  const isAuthenticated = computed(() => Boolean(token.value))

  const role = computed(() => {
    const fromRoleId = Number(user.value?.role_id || 0)
    if (fromRoleId) return fromRoleId

    const fromRoleObject = Number(user.value?.role?.id || 0)
    if (fromRoleObject) return fromRoleObject

    const fromLegacy = Number(user.value?.role || 0)
    if (fromLegacy) return fromLegacy

    const fromRolesArray = Number(user.value?.roles?.[0]?.id || 0)
    if (fromRolesArray) return fromRolesArray

    return 0
  })

  const isAdmin = computed(() => role.value === 1)
  const isConsulta = computed(() => role.value === 3)

  const canViewUsers = computed(() => isAdmin.value)
  const canManageUsers = computed(() => isAdmin.value)

  const canViewTasks = computed(() => isAdmin.value || isConsulta.value)
  const canEditTasks = computed(() => isAdmin.value)

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

  async function login({ email, password }) {
    const { token: newToken, user: newUser } = await AuthApi.login({
      email,
      password,
    })

    setSession({ token: newToken, user: newUser })
    return newUser
  }

  async function fetchMe() {
    if (!token.value) return null

    const me = await AuthApi.me()
    user.value = me
    localStorage.setItem(LS_USER, JSON.stringify(user.value))

    return user.value
  }

  async function logout() {
    if (!token.value) return

    try {
      await AuthApi.logout()
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
    isConsulta,
    canViewUsers,
    canManageUsers,
    canViewTasks,
    canEditTasks,
    setSession,
    clearSession,
    login,
    logout,
    fetchMe,
  }
})