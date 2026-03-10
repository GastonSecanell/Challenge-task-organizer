import { http } from '@/lib/http'

export const ChecklistApi = {
  create(cardId, payload) {
    return http.post(`/api/cards/${cardId}/checklist-items`, payload).then((r) => r.data)
  },

  update(itemId, payload) {
    return http.patch(`/api/checklist-items/${itemId}`, payload).then((r) => r.data)
  },

  destroy(itemId) {
    return http.delete(`/api/checklist-items/${itemId}`).then((r) => r.data)
  },
}