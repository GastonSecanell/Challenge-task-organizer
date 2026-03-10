import { http } from '@/lib/http'

export const LabelsApi = {
  listByBoard(boardId) {
    return http.get(`/api/boards/${boardId}/labels`).then((r) => r.data)
  },

  create(boardId, payload) {
    return http.post(`/api/boards/${boardId}/labels`, payload).then((r) => r.data)
  },

  update(labelId, payload) {
    return http.patch(`/api/labels/${labelId}`, payload).then((r) => r.data)
  },

  destroy(labelId) {
    return http.delete(`/api/labels/${labelId}`).then((r) => r.data)
  },
}