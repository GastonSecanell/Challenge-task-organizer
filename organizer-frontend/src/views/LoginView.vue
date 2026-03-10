<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toasts'

const auth = useAuthStore()
const toasts = useToastStore()
const router = useRouter()
const route = useRoute()

const email = ref('admin@board.local')
const password = ref('administrativo')

const isLoading = ref(false)
const error = ref(null)

async function submit() {
  error.value = null
  isLoading.value = true
  try {
    await auth.login({ email: email.value, password: password.value })
    toasts.push({ type: 'success', message: 'Sesión iniciada.' })
    const redirectTo = route.query.redirect?.toString() || '/dashboard'
    await router.push(redirectTo)
  } catch (e) {
    error.value = e?.message ?? String(e)
    toasts.push({ type: 'error', message: error.value })
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="login-page">
    <div class="login-card">
      <div class="login-card__title">Trollo</div>
      <div class="login-card__subtitle">Como Trello pero con O</div>

      <form class="form" @submit.prevent="submit">
        <label class="field">
          <span class="field__label">Correo Oficial</span>
          <input v-model="email" class="input" type="email" autocomplete="username" />
        </label>

        <label class="field">
          <span class="field__label">Contraseña</span>
          <input v-model="password" class="input" type="password" autocomplete="current-password" />
        </label>

        <div v-if="error" class="alert alert--danger">{{ error }}</div>

        <md-filled-button class="md-btn-block" type="submit" :disabled="isLoading">
          <i class="mdi mdi-login" aria-hidden="true"></i>
          <span>Entrar</span>
          <md-circular-progress v-if="isLoading" indeterminate class="md-spinner-inline"></md-circular-progress>
        </md-filled-button>
      </form>
    </div>
  </div>
</template>

