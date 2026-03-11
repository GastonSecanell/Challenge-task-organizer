<script setup>
import { computed } from "vue";
import { RouterLink, RouterView, useRoute, useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { useThemeStore } from "@/stores/theme";
import { useToastStore } from "@/stores/toasts";

const auth = useAuthStore();
const theme = useThemeStore();
const toasts = useToastStore();
const route = useRoute();
const router = useRouter();

const navItems = computed(() => [
  { label: "Dashboard", to: "/dashboard", name: "dashboard" },
  { label: "Tareas", to: "/tareas", name: "tareas.index" },
  { label: "Usuarios", to: "/usuarios", name: "usuarios.index" },
]);

function isActive(item) {
  return route.name === item.name;
}

async function logout() {
  try {
    await auth.logout();
    toasts.success("Sesión cerrada correctamente.");
    router.push("/login");
  } catch (error) {
    toasts.error(error?.message || "No se pudo cerrar la sesión.");
  }
}
</script>

<template>
  <div class="min-h-screen bg-[var(--bg-page)] text-[var(--text-primary)]">
    <header
      class="sticky top-0 z-40 border-b border-[var(--border-default)] bg-[var(--bg-surface)]/95 backdrop-blur"
    >
      <div
        class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-4"
      >
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
            class="rounded-lg px-3 py-2 text-sm text-[var(--text-secondary)] transition-colors hover:bg-[var(--bg-hover)] hover:text-[var(--text-primary)]"
            @click="theme.toggle()"
          >
            {{ theme.isLight ? "Dark" : "Light" }}
          </button>

          <button
            type="button"
            class="rounded-lg px-3 py-2 text-sm text-red-400 transition-colors hover:bg-[var(--bg-hover)] hover:text-red-300"
            @click="logout"
          >
            Logout
          </button>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-7xl p-6">
      <RouterView />
    </main>
  </div>
</template>
