import { http } from '@/lib/http'

export const UsersApi = {
  list(params = {}) {
    return http.get('/api/users', { params }).then((r) => r.data)
  },

  get(id) {
    return http.get(`/api/users/${id}`).then((r) => r.data)
  },

  create(payload) {
    return http.post('/api/users', payload).then((r) => r.data)
  },

  update(id, payload) {
    return http.put(`/api/users/${id}`, payload).then((r) => r.data)
  },

  remove(id) {
    return http.delete(`/api/users/${id}`).then((r) => r.data)
  },
}