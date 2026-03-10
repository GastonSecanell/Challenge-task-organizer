<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toasts'
import BaseButton from '@/components/ui/BaseButton.vue'

const auth = useAuthStore()
const toasts = useToastStore()
const router = useRouter()
const route = useRoute()

const email = ref('admin@tareas.local.com')
const password = ref('administrador')

const isLoading = ref(false)
const error = ref(null)

async function submit() {
  error.value = null
  isLoading.value = true

  try {
    await auth.login({
      email: email.value,
      password: password.value,
    })

    toasts.push({
      type: 'success',
      message: 'Sesión iniciada.',
    })

    const redirectTo = route.query.redirect?.toString() || '/dashboard'
    await router.push(redirectTo)
  } catch (e) {
    error.value = e?.message ?? String(e)

    toasts.push({
      type: 'error',
      message: error.value,
    })
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-[var(--bg-page)] px-4">
    <div class="w-full max-w-md rounded-2xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-8 shadow-lg">
      <div class="mb-8 text-center">
        <h1 class="text-2xl font-semibold text-[var(--text-primary)]">
          Gestión de Tareas
        </h1>

        <p class="mt-1 text-sm text-[var(--text-muted)]">
          Ingresá con tu cuenta para continuar
        </p>
      </div>

      <form class="space-y-5" @submit.prevent="submit">
        <div>
          <label class="mb-1 block text-sm text-[var(--text-secondary)]">
            Correo
          </label>

          <input
            v-model="email"
            type="email"
            autocomplete="username"
            class="w-full rounded-lg border border-[var(--border-default)] bg-[var(--bg-page)] px-3 py-2 text-[var(--text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]"
          >
        </div>

        <div>
          <label class="mb-1 block text-sm text-[var(--text-secondary)]">
            Contraseña
          </label>

          <input
            v-model="password"
            type="password"
            autocomplete="current-password"
            class="w-full rounded-lg border border-[var(--border-default)] bg-[var(--bg-page)] px-3 py-2 text-[var(--text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]"
          >
        </div>

        <div
          v-if="error"
          class="rounded-md border border-[var(--danger)] bg-red-500/10 px-3 py-2 text-sm text-[var(--danger)]"
        >
          {{ error }}
        </div>

        <BaseButton
          type="submit"
          block
          :disabled="isLoading"
        >
          <svg
            v-if="isLoading"
            class="h-4 w-4 animate-spin"
            viewBox="0 0 24 24"
            fill="none"
          >
            <circle
              class="opacity-25"
              cx="12"
              cy="12"
              r="10"
              stroke="currentColor"
              stroke-width="4"
            />
            <path
              class="opacity-75"
              fill="currentColor"
              d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
            />
          </svg>

          <span>
            {{ isLoading ? 'Ingresando...' : 'Entrar' }}
          </span>
        </BaseButton>

        <div class="rounded-xl border border-[var(--border-default)] bg-[var(--bg-page)] p-3 text-xs text-[var(--text-muted)]">
          <p><strong>Admin:</strong> admin@tareas.local.com / administrador</p>
          <p><strong>Operador:</strong> operator@tareas.local.com / operador</p>
          <p><strong>Consulta:</strong> consult@tareas.local.com / consulta</p>
        </div>
      </form>
    </div>
  </div>
</template>