import { http } from '@/lib/http'

export const CardsApi = {
  get(cardId) {
    return http.get(`/api/cards/${cardId}`).then((r) => r.data)
  },
  create(payload) {
    return http.post('/api/cards', payload).then((r) => r.data)
  },
  
  update(cardId, payload) {
    return http.patch(`/api/cards/${cardId}`, payload).then((r) => r.data)
  },
  
  move(cardId, payload) {
    const id = Number(cardId)
    return http.patch(`/api/cards/${id}/move`, payload).then((r) => r.data)
  },
  
  setLabels(cardId, labelIds) {
    return http.put(`/api/cards/${cardId}/labels`, {
      label_ids: labelIds,
    }).then((r) => r.data)
  },

  setMembers(cardId, userIds) {
    return http.put(`/api/cards/${cardId}/members`, {
      user_ids: userIds,
    }).then((r) => r.data)
  },

  activity(cardId) {
    return http.get(`/api/cards/${cardId}/activity`).then((r) => r.data)
  },

  comment(cardId, body, options = {}) {
    return http.post(`/api/cards/${cardId}/comments`, {
      body,
      parent_id: options?.parent_id ?? null,
    }).then((r) => r.data)
  },

  deleteComment(cardId, commentId) {
    return http.delete(`/api/cards/${cardId}/comments/${commentId}`).then((r) => r.data)
  },

  destroy(cardId) {
    return http.delete(`/api/cards/${cardId}`).then((r) => r.data)
  },
}