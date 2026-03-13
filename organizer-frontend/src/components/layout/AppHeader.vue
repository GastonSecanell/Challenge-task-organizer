<script setup>
import { computed, ref, onMounted, onBeforeUnmount, watch } from "vue";
import { RouterLink, useRoute, useRouter } from "vue-router";
import {
  Moon,
  Sun,
  ChevronDown,
  User,
  LogOut,
  Shield,
  Menu,
  X,
} from "lucide-vue-next";
import { useAuthStore } from "@/stores/auth";
import { useThemeStore } from "@/stores/theme";
import { useToastStore } from "@/stores/toasts";

const auth = useAuthStore();
const theme = useThemeStore();
const toasts = useToastStore();
const route = useRoute();
const router = useRouter();

const profileOpen = ref(false);
const mobileNavOpen = ref(false);
const triggerRef = ref(null);
const panelRef = ref(null);

const navItems = computed(() => {
  const items = [{ label: "Tareas", to: "/tareas", name: "tareas.index" }];

  if (auth.canViewUsers) {
    items.push({ label: "Usuarios", to: "/usuarios", name: "usuarios.index" });
  }

  return items;
});

const currentUser = computed(() => {
  const fromStore = auth.user;
  if (fromStore) return fromStore;

  try {
    const raw =
      localStorage.getItem("user") ||
      sessionStorage.getItem("user") ||
      localStorage.getItem("auth_user") ||
      sessionStorage.getItem("auth_user");

    return raw ? JSON.parse(raw) : null;
  } catch {
    return null;
  }
});

const userName = computed(() => currentUser.value?.name || "Usuario");
const userEmail = computed(() => currentUser.value?.email || "-");
const userRole = computed(() => {
  if (auth.isAdmin) return "Administrador";
  if (auth.isConsulta) return "Consulta";
  return currentUser.value?.role?.name || "Sin rol";
});

const desktopNavClass =
  "hidden md:flex md:items-center md:gap-1 md:rounded-xl md:border md:border-[var(--border-default)] md:bg-[var(--bg-page)] md:p-1";

const navLinkBaseClass =
  "inline-flex items-center justify-center rounded-lg px-3 py-2 text-sm transition-colors";

function navLinkClass(item) {
  return isActive(item)
    ? "bg-[var(--accent)] text-white"
    : "text-[var(--text-secondary)] hover:bg-[var(--bg-hover)] hover:text-[var(--text-primary)]";
}

function isActive(item) {
  return route.name === item.name;
}

function toggleProfile() {
  profileOpen.value = !profileOpen.value;
}

function closeProfile() {
  profileOpen.value = false;
}

function toggleMobileNav() {
  mobileNavOpen.value = !mobileNavOpen.value;
}

function closeMobileNav() {
  mobileNavOpen.value = false;
}

function handleClickOutside(event) {
  if (!profileOpen.value) return;

  const clickedTrigger = triggerRef.value?.contains(event.target);
  const clickedPanel = panelRef.value?.contains(event.target);

  if (!clickedTrigger && !clickedPanel) {
    closeProfile();
  }
}

async function logout() {
  try {
    closeProfile();
    closeMobileNav();
    await auth.logout();
    toasts.success("Sesión cerrada correctamente");
    router.push("/login");
  } catch (error) {
    toasts.error(error?.message || "No se pudo cerrar la sesión");
  }
}

watch(
  () => route.fullPath,
  () => {
    closeMobileNav();
    closeProfile();
  },
);

onMounted(() => {
  document.addEventListener("mousedown", handleClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener("mousedown", handleClickOutside);
});
</script>

<template>
  <header
    class="sticky top-0 z-40 border-b border-[var(--border-default)] bg-[var(--bg-surface)]/95 backdrop-blur"
  >
    <div class="mx-auto max-w-7xl px-4 sm:px-6">
      <div class="flex min-h-[72px] items-center justify-between gap-3 py-3">
        <div class="flex min-w-0 items-center gap-6 lg:gap-10">
          <div class="min-w-0 shrink-0">
            <h1
              class="text-base font-bold leading-tight text-[var(--text-primary)] sm:text-lg"
            >
              Gestor de Tareas
            </h1>
            <p class="hidden text-xs text-[var(--text-muted)] sm:block pt-1.5">
              Challenge Laravel 12 + Vue 3 + Docker composer
            </p>
          </div>

        </div>
        <nav :class="desktopNavClass">
          <RouterLink
            v-for="item in navItems"
            :key="item.name"
            :to="item.to"
            :class="[navLinkBaseClass, navLinkClass(item)]"
          >
            {{ item.label }}
          </RouterLink>
        </nav>

        <div class="flex shrink-0 items-center gap-2">
          <button
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[var(--border-default)] bg-[var(--bg-page)] text-[var(--text-secondary)] transition hover:border-[var(--accent)] hover:bg-[var(--bg-hover)] hover:text-[var(--text-primary)] md:hidden"
            :title="mobileNavOpen ? 'Cerrar menú' : 'Abrir menú'"
            @click="toggleMobileNav"
          >
            <X v-if="mobileNavOpen" class="h-4 w-4" />
            <Menu v-else class="h-4 w-4" />
          </button>

          <button
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[var(--border-default)] bg-[var(--bg-page)] text-[var(--text-secondary)] transition hover:border-[var(--accent)] hover:bg-[var(--bg-hover)] hover:text-[var(--text-primary)]"
            :title="
              theme.isLight ? 'Activar modo oscuro' : 'Activar modo claro'
            "
            @click="theme.toggle()"
          >
            <Moon v-if="theme.isLight" class="h-4 w-4" />
            <Sun v-else class="h-4 w-4" />
          </button>

          <div class="relative">
            <button
              ref="triggerRef"
              type="button"
              class="inline-flex items-center gap-2 rounded-xl border border-[var(--border-default)] bg-[var(--bg-page)] px-2.5 py-2 text-left transition hover:border-[var(--accent)] hover:bg-[var(--bg-hover)] sm:gap-3 sm:px-3"
              @click="toggleProfile"
            >
              <div
                class="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--accent-soft)] text-[var(--accent)]"
              >
                <User class="h-4 w-4" />
              </div>

              <div class="hidden min-w-0 sm:block">
                <p
                  class="truncate text-sm font-semibold text-[var(--text-primary)]"
                >
                  {{ userName }}
                </p>
                <p class="truncate text-xs text-[var(--text-muted)]">
                  {{ userRole }}
                </p>
              </div>

              <ChevronDown
                class="hidden h-4 w-4 text-[var(--text-secondary)] sm:block"
              />
            </button>

            <transition
              enter-active-class="transition duration-150 ease-out"
              enter-from-class="translate-y-1 opacity-0 scale-95"
              enter-to-class="translate-y-0 opacity-100 scale-100"
              leave-active-class="transition duration-100 ease-in"
              leave-from-class="translate-y-0 opacity-100 scale-100"
              leave-to-class="translate-y-1 opacity-0 scale-95"
            >
              <div
                v-if="profileOpen"
                ref="panelRef"
                class="absolute right-0 top-[calc(100%+10px)] z-50 w-[280px] rounded-2xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-3 shadow-2xl sm:w-[320px]"
              >
                <div class="mb-3 border-b border-[var(--border-default)] pb-3">
                  <div class="flex items-start gap-3">
                    <div
                      class="flex h-11 w-11 items-center justify-center rounded-full bg-[var(--accent-soft)] text-[var(--accent)]"
                    >
                      <User class="h-5 w-5" />
                    </div>

                    <div class="min-w-0 flex-1">
                      <p
                        class="truncate text-sm font-semibold text-[var(--text-primary)]"
                      >
                        {{ userName }}
                      </p>
                      <p class="truncate text-xs text-[var(--text-secondary)]">
                        {{ userEmail }}
                      </p>

                      <div
                        class="mt-2 inline-flex items-center gap-1 rounded-full border border-[var(--border-default)] px-2 py-1 text-[11px] text-[var(--text-secondary)]"
                      >
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

      <transition
        enter-active-class="transition-all duration-200 ease-out"
        enter-from-class="-translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition-all duration-150 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="-translate-y-2 opacity-0"
      >
        <nav
          v-if="mobileNavOpen"
          class="mb-3 flex flex-col gap-2 rounded-2xl border border-[var(--border-default)] bg-[var(--bg-page)] p-2 md:hidden"
        >
          <RouterLink
            v-for="item in navItems"
            :key="item.name"
            :to="item.to"
            :class="[
              navLinkBaseClass,
              'w-full justify-start',
              navLinkClass(item),
            ]"
            @click="closeMobileNav"
          >
            {{ item.label }}
          </RouterLink>
        </nav>
      </transition>
    </div>
  </header>
</template>
