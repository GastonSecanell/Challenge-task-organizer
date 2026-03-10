<script setup>
import { RouterLink, RouterView } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from '@/stores/theme'

const auth = useAuthStore()
const theme = useThemeStore()

function logout() {
  auth.logout()
}
</script>

<template>
  <div class="min-h-screen bg-[var(--bg-page)] text-[var(--text-primary)]">

    <header class="border-b border-[var(--border-default)] bg-[var(--bg-surface)]">
      <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4">

        <h1 class="text-lg font-semibold">
          Gestor de Tareas
        </h1>

        <nav class="flex items-center gap-3">

          <RouterLink
            to="/dashboard"
            class="px-3 py-2 text-sm rounded hover:bg-[var(--bg-hover)]"
          >
            Dashboard
          </RouterLink>

          <RouterLink
            to="/tareas"
            class="px-3 py-2 text-sm rounded hover:bg-[var(--bg-hover)]"
          >
            Tareas
          </RouterLink>

          <button
            class="px-3 py-2 text-sm rounded hover:bg-[var(--bg-hover)]"
            @click="theme.toggle()"
          >
            Theme
          </button>

          <button
            class="px-3 py-2 text-sm rounded text-red-400 hover:bg-[var(--bg-hover)]"
            @click="logout"
          >
            Logout
          </button>

        </nav>
      </div>
    </header>

    <main class="mx-auto max-w-7xl p-6">
      <RouterView />
    </main>

  </div>
</template>

