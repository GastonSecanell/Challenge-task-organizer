import { http } from '@/lib/http'

export const TareasApi = {

  list(params = {}) {
    return http.get('/api/tareas', { params }).then(r => r.data)
  },

  get(id) {
    return http.get(`/api/tareas/${id}`).then(r => r.data)
  },

  create(payload) {
    return http.post('/api/tareas', payload).then(r => r.data)
  },

  update(id, payload) {
    return http.patch(`/api/tareas/${id}`, payload).then(r => r.data)
  },

  remove(id) {
    return http.delete(`/api/tareas/${id}`).then(r => r.data)
  },

}