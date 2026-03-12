import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

import AppLayout from '@/layouts/AppLayout.vue'
import LoginView from '@/views/LoginView.vue'
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
        { path: '', redirect: '/tareas' },
        {
          path: 'tareas',
          name: 'tareas.index',
          component: TareasView,
        },
        {
          path: 'usuarios',
          name: 'usuarios.index',
          component: UsersIndexView,
          meta: { requiresAdmin: true },
        },
      ],
    },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.public) {
    if (auth.isAuthenticated && to.name === 'login') {
      return { path: '/tareas' }
    }
    return true
  }

  const requiresAuth = to.matched.some((record) => record.meta.requiresAuth)
  const requiresAdmin = to.matched.some((record) => record.meta.requiresAdmin)

  if (requiresAuth && !auth.isAuthenticated) {
    return { path: '/login', query: { redirect: to.fullPath } }
  }

  if (requiresAuth && !auth.user) {
    try {
      await auth.fetchMe()
    } catch {
      auth.clearSession()
      return { path: '/login' }
    }
  }

  if (requiresAdmin && !auth.canViewUsers) {
    return { path: '/tareas' }
  }

  return true
})

export default router