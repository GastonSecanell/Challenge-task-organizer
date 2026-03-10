import { ref } from 'vue'
import { defineStore } from 'pinia'

let _id = 1

export const useToastStore = defineStore('toasts', () => {
  const items = ref([])

  function push({ message, type = 'info', timeoutMs = 3500 } = {}) {
    const id = _id++
    const toast = { id, message: String(message ?? ''), type, createdAt: Date.now() }
    items.value = [...items.value, toast]

    if (timeoutMs && timeoutMs > 0) {
      window.setTimeout(() => remove(id), timeoutMs)
    }
    return id
  }

  function remove(id) {
    items.value = items.value.filter((t) => t.id !== id)
  }

  function clear() {
    items.value = []
  }

  return { items, push, remove, clear }
})

