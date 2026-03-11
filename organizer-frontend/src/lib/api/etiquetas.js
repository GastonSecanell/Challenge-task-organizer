import { http } from '@/lib/http'

export const EtiquetasApi = {
  list() {
    return http.get('/api/etiquetas').then(r => r.data)
  },
}