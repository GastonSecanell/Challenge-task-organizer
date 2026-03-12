import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

const LS_THEME = 'tareas.theme'

function apply(theme) {
  document.documentElement.dataset.theme = theme
}

export const useThemeStore = defineStore('theme', () => {
  const theme = ref(localStorage.getItem(LS_THEME) || 'light')
  apply(theme.value)

  const isDark = computed(() => theme.value === 'dark')

  function set(next) {
    theme.value = next
    localStorage.setItem(LS_THEME, next)
    apply(next)
  }

  function toggle() {
    set(isDark.value ? 'light' : 'dark')
  }

  return { theme, isDark, set, toggle }
})

