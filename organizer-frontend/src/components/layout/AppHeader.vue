<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { Moon, Sun, ChevronDown, User, LogOut, Shield } from 'lucide-vue-next'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'
import { useToastStore } from '@/stores/toasts'

const auth = useAuthStore()
const theme = useThemeStore()
const toasts = useToastStore()
const route = useRoute()
const router = useRouter()

const profileOpen = ref(false)
const triggerRef = ref(null)
const panelRef = ref(null)

const navItems = computed(() => [
  { label: 'Dashboard', to: '/dashboard', name: 'dashboard' },
  { label: 'Tareas', to: '/tareas', name: 'tareas.index' },
  { label: 'Usuarios', to: '/usuarios', name: 'usuarios.index' },
])

const currentUser = computed(() => {
  const fromStore = auth.user
  if (fromStore) return fromStore

  try {
    const raw =
      localStorage.getItem('user') ||
      sessionStorage.getItem('user') ||
      localStorage.getItem('auth_user') ||
      sessionStorage.getItem('auth_user')

    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
})

const userName = computed(() => currentUser.value?.name || 'Usuario')
const userEmail = computed(() => currentUser.value?.email || '-')
const userRole = computed(() => currentUser.value?.role?.name || 'Sin rol')

function isActive(item) {
  return route.name === item.name
}

function toggleProfile() {
  profileOpen.value = !profileOpen.value
}

function closeProfile() {
  profileOpen.value = false
}

function handleClickOutside(event) {
  if (!profileOpen.value) return

  const clickedTrigger = triggerRef.value?.contains(event.target)
  const clickedPanel = panelRef.value?.contains(event.target)

  if (!clickedTrigger && !clickedPanel) {
    closeProfile()
  }
}

async function logout() {
  try {
    closeProfile()
    const res = await auth.logout()
    toasts.success(res?.message || 'Sesión cerrada correctamente')
    router.push('/login')
  } catch (error) {
    toasts.error(error?.message || 'No se pudo cerrar la sesión')
  }
}

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside)
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleClickOutside)
})
</script>

<template>
  <header
    class="sticky top-0 z-40 border-b border-[var(--border-default)] bg-[var(--bg-surface)]/95 backdrop-blur"
  >
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-4">
      <div class="min-w-0">
        <h1 class="text-lg font-semibold text-[var(--text-primary)]">
          Gestor de Tareas
        </h1>
        <p class="text-xs text-[var(--text-muted)]">
          Challenge Laravel + Vue + Docker
        </p>
      </div>

      <div class="flex items-center gap-3">
        <nav
          class="flex items-center gap-1 rounded-xl border border-[var(--border-default)] bg-[var(--bg-page)] p-1"
        >
          <RouterLink
            v-for="item in navItems"
            :key="item.name"
            :to="item.to"
            class="rounded-lg px-3 py-2 text-sm transition-colors"
            :class="
              isActive(item)
                ? 'bg-[var(--accent)] text-white'
                : 'text-[var(--text-secondary)] hover:bg-[var(--bg-hover)] hover:text-[var(--text-primary)]'
            "
          >
            {{ item.label }}
          </RouterLink>
        </nav>

        <button
          type="button"
          class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[var(--border-default)] bg-[var(--bg-page)] text-[var(--text-secondary)] transition hover:border-[var(--accent)] hover:bg-[var(--bg-hover)] hover:text-[var(--text-primary)]"
          :title="theme.isLight ? 'Activar modo oscuro' : 'Activar modo claro'"
          @click="theme.toggle()"
        >
          <Moon v-if="theme.isLight" class="h-4 w-4" />
          <Sun v-else class="h-4 w-4" />
        </button>

        <div class="relative">
          <button
            ref="triggerRef"
            type="button"
            class="inline-flex items-center gap-3 rounded-xl border border-[var(--border-default)] bg-[var(--bg-page)] px-3 py-2 text-left transition hover:border-[var(--accent)] hover:bg-[var(--bg-hover)]"
            @click="toggleProfile"
          >
            <div
              class="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--accent-soft)] text-[var(--accent)]"
            >
              <User class="h-4 w-4" />
            </div>

            <div class="hidden min-w-0 sm:block">
              <p class="truncate text-sm font-semibold text-[var(--text-primary)]">
                {{ userName }}
              </p>
              <p class="truncate text-xs text-[var(--text-muted)]">
                {{ userRole }}
              </p>
            </div>

            <ChevronDown class="h-4 w-4 text-[var(--text-secondary)]" />
          </button>

          <transition name="fade">
            <div
              v-if="profileOpen"
              ref="panelRef"
              class="absolute right-0 top-[calc(100%+10px)] z-50 w-[320px] rounded-2xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-3 shadow-2xl"
            >
              <div class="mb-3 border-b border-[var(--border-default)] pb-3">
                <div class="flex items-start gap-3">
                  <div
                    class="flex h-11 w-11 items-center justify-center rounded-full bg-[var(--accent-soft)] text-[var(--accent)]"
                  >
                    <User class="h-5 w-5" />
                  </div>

                  <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-[var(--text-primary)]">
                      {{ userName }}
                    </p>
                    <p class="truncate text-xs text-[var(--text-secondary)]">
                      {{ userEmail }}
                    </p>

                    <div class="mt-2 inline-flex items-center gap-1 rounded-full border border-[var(--border-default)] px-2 py-1 text-[11px] text-[var(--text-secondary)]">
                      <Shield class="h-3.5 w-3.5" />
                      <span>{{ userRole }}</span>
                    </div>
                  </div>
                </div>
              </div>

              <button
                type="button"
                class="flex w-full items-center gap-2 rounded-xl px-3 py-2 text-sm text-red-400 transition hover:bg-[var(--bg-hover)] hover:text-red-300"
                @click="logout"
              >
                <LogOut class="h-4 w-4" />
                <span>Cerrar sesión</span>
              </button>
            </div>
          </transition>
        </div>
      </div>
    </div>
  </header>
</template>