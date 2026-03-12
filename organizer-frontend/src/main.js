import './assets/main.css'
import '@fontsource/roboto/400.css'
import '@fontsource/roboto/500.css'
import '@fontsource/roboto/700.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import '@vueup/vue-quill/dist/vue-quill.snow.css'
import router from './router'

import { initHttp } from '@/lib/http'
import { useAuthStore } from '@/stores/auth'
import { useThemeStore } from './stores/theme'

const app = createApp(App)

const pinia = createPinia()
app.use(pinia)

initHttp({
    getToken: () => useAuthStore(pinia).token,
})
useThemeStore(pinia)

app.use(router)
app.mount('#app')
