import { http } from '@/lib/http'

export const AuditApi = {
  getLogs(params = {}) {
    return http.get('/api/audit', { params }).then((r) => r.data)
  },
}