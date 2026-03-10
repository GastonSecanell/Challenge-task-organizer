import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AppLayout from '@/layouts/AppLayout.vue'
import LoginView from '@/views/LoginView.vue'
import BoardView from '@/views/BoardView.vue'
import DashboardView from '@/views/DashboardView.vue'
import ArchivedBoardsView from '@/views/ArchivedBoardsView.vue'
import AdminUsersView from '@/views/AdminUsersView.vue'
import AuditView from '@/views/AuditView.vue'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/login',
      name: 'login',
      component: LoginView,
      meta: { public: true },
    },
    {
      path: '/',
      component: AppLayout,
      meta: { requiresAuth: true },
      children: [
        { path: '', redirect: '/dashboard' },
        { path: 'dashboard', name: 'dashboard', component: DashboardView },
        { path: 'archived', name: 'archived', component: ArchivedBoardsView },
        { path: 'boards/:id', name: 'board', component: BoardView },
        { path: 'admin/users', name: 'admin-users', component: AdminUsersView, meta: { requiresAdmin: true } },
        { path: 'admin/audit', name: 'audit', component: AuditView, meta: { requiresAudit: true } },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.public) {
    if (auth.isAuthenticated && to.name === 'login') return { path: '/dashboard' }
    return true
  }

  if (to.meta.requiresAuth) {
    if (!auth.isAuthenticated) return { path: '/login', query: { redirect: to.fullPath } }
    if (!auth.user) {
      try {
        await auth.fetchMe()
      } catch {
        auth.clearSession()
        return { path: '/login', query: { redirect: to.fullPath } }
      }
    }
  }

  if (to.meta.requiresAdmin && !auth.isAdmin) return { path: '/dashboard' }
  if (to.meta.requiresAudit && !auth.canReadAudit) return { path: '/dashboard' }

  return true
})

export default router
