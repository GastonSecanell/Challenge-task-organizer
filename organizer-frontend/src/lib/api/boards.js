import { http } from '@/lib/http'

export const BoardsApi = {
  list() {
    return http.get('/api/boards').then((r) => r.data)
  },

  listArchived() {
    return http.get('/api/boards', { params: { archived: 1 } }).then((r) => r.data)
  },

  get(boardId) {
    const id = Number(boardId)
    return http.get(`/api/boards/${id}`).then((r) => r.data)
  },

  create(name) {
    return http.post('/api/boards', { name }).then((r) => r.data)
  },

  rename(boardId, name) {
    return http.patch(`/api/boards/${boardId}`, { name }).then((r) => r.data)
  },

  setFavorite(boardId, is_favorite) {
    return http.put(`/api/boards/${boardId}/favorite`, { is_favorite }).then((r) => r.data)
  },

  archive(boardId) {
    return http.patch(`/api/boards/${boardId}/archive`).then((r) => r.data)
  },

  unarchive(boardId) {
    return http.patch(`/api/boards/${boardId}/unarchive`).then((r) => r.data)
  },

  destroy(boardId) {
    return http.delete(`/api/boards/${boardId}`).then((r) => r.data)
  },

  members(boardId) {
    return http.get(`/api/boards/${boardId}/members`).then((r) => r.data)
  },

  memberOptions(boardId) {
    return http.get(`/api/boards/${boardId}/member-options`).then((r) => r.data)
  },

  addMember(boardId, userId) {
    return http.post(`/api/boards/${boardId}/members`, { user_id: userId }).then((r) => r.data)
  },

  removeMember(boardId, userId) {
    return http.delete(`/api/boards/${boardId}/members/${userId}`).then((r) => r.data)
  },
  
  transferOwner(boardId, userId) {
    return http.post(`/api/boards/${boardId}/transfer-owner`, { user_id: userId }).then((r) => r.data)
  },
}