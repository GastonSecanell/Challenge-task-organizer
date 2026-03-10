import { http } from '@/lib/http'

export const ColumnsApi = {
  create({ board_id, name, position }) {
    return http
      .post('/api/columns', { board_id, name, position })
      .then((r) => r.data)
  },

  update(columnId, payload) {
    const id = Number(columnId)
    return http.patch(`/api/columns/${id}`, payload).then((r) => r.data)
  },

  destroy(columnId) {
    const id = Number(columnId)
    return http.delete(`/api/columns/${id}`).then((r) => r.data)
  },
}