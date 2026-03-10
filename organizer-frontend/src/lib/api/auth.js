import { http } from '@/lib/http'

export const AuthApi = {
  changePassword(data) {
    return http.post('/api/me/change-password', data).then((r) => r.data)
  },
}