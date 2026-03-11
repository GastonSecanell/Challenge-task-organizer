import { http } from '@/lib/http'

export const PrioridadesApi = {
  list() {
    return http.get('/api/prioridades').then((r) => r.data)
  },
}