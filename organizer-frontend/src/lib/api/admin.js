import { http } from '@/lib/http'

export const AdminApi = {
  getRoles() {
    return http.get('/api/roles').then((r) => r.data)
  },

  list() {
    return http.get('/api/admin/users').then((r) => r.data)
  },

  // alias para mantener compatibilidad
  getUsers() {
    return this.list()
  },

  createUser(payload) {
    return http.post('/api/admin/users', payload).then((r) => r.data)
  },

  updateUser(userId, payload) {
    return http.put(`/api/admin/users/${userId}`, payload).then((r) => r.data)
  },

  uploadUserAvatar(userId, file) {
    const id = Number(userId)
    const fd = new FormData()
    fd.append('file', file)
    return http.post(`/api/users/${id}/avatar`, fd, {
      headers: { Accept: 'application/json' },
      transformRequest: [(data, headers) => {
        if (headers) {
          delete headers['Content-Type']
          delete headers['content-type']
        }
        return data
      }],
    }).then((r) => r.data)
  },

  deleteUserAvatar(userId) {
    const id = Number(userId)
    return http.delete(`/api/users/${id}/avatar`).then((r) => r.data)
  },

  deleteUser(userId) {
    return http.delete(`/api/admin/users/${userId}`).then((r) => r.data)
  },
}