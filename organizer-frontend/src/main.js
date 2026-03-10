import './assets/main.css'
import './lib/material'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'

import { initHttp } from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from './stores/theme'

const app = createApp(App)

const pinia = createPinia()
app.use(pinia)

initHttp({
    getToken: () => useAuthStore(pinia).token,
    // baseURL: '' // si después querés prefijo global
})
console.log('BASE_URL FRONT:', import.meta.env.BASE_URL)
console.log('API URL:', import.meta.env.VITE_API_URL)
useThemeStore(pinia)

app.use(router)
app.mount('#app')