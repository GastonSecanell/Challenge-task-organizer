import { http } from '@/lib/http'

export const RolesApi = {
  list() {
    return http.get('/api/roles').then((r) => r.data)
  },
}