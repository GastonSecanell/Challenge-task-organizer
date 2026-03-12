import { http } from '@/lib/http'

function normalizeAuthPayload(payload) {
  const p = payload?.data?.data ?? payload?.data ?? payload ?? {}

  return {
    token: p?.token ?? '',
    user: p?.user ?? null,
  }
}

export const AuthApi = {
  async login({ email, password, device_name = 'web' }) {
    const res = await http.post('/api/login', {
      email,
      password,
      device_name,
    })

    return normalizeAuthPayload(res)
  },

  async me() {
    const res = await http.get('/api/me')
    return res?.data?.user ?? res?.data?.data?.user ?? res?.data?.data ?? null
  },

  async logout() {
    return http.post('/api/logout')
  },
}