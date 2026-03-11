import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

import AppLayout from '@/layouts/AppLayout.vue'
import LoginView from '@/views/LoginView.vue'
import DashboardView from '@/views/DashboardView.vue'
import TareasView from '@/views/TareasIndexView.vue'
import UsersIndexView from '@/views/UsersIndexView.vue'

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
        {
          path: 'dashboard',
          name: 'dashboard',
          component: DashboardView,
        },
        {
          path: 'tareas',
          name: 'tareas.index',
          component: TareasView,
        },
        {
          path: 'usuarios',
          name: 'usuarios.index',
          component: UsersIndexView,
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.public) {
    if (auth.isAuthenticated && to.name === 'login') {
      return { path: '/dashboard' }
    }
    return true
  }

  if (to.meta.requiresAuth) {
    if (!auth.isAuthenticated) {
      return { path: '/login', query: { redirect: to.fullPath } }
    }

    if (!auth.user) {
      try {
        await auth.fetchMe()
      } catch {
        auth.clearSession()
        return { path: '/login' }
      }
    }
  }

  return true
})

export default router