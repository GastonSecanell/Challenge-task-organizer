import { ref } from 'vue'
import { defineStore } from 'pinia'

let _id = 1

export const useToastStore = defineStore('toasts', () => {
  const items = ref([])

  function push({ message, type = 'info', timeoutMs = 3500 } = {}) {
    const id = _id++
    const toast = {
      id,
      message: String(message ?? ''),
      type,
      createdAt: Date.now(),
    }

    items.value = [...items.value, toast]

    if (timeoutMs && timeoutMs > 0) {
      window.setTimeout(() => remove(id), timeoutMs)
    }

    return id
  }

  function success(message, timeoutMs = 3000) {
    return push({ message, type: 'success', timeoutMs })
  }

  function error(message, timeoutMs = 4500) {
    return push({ message, type: 'error', timeoutMs })
  }

  function warning(message, timeoutMs = 4000) {
    return push({ message, type: 'warning', timeoutMs })
  }

  function info(message, timeoutMs = 3500) {
    return push({ message, type: 'info', timeoutMs })
  }

  function remove(id) {
    items.value = items.value.filter((t) => t.id !== id)
  }

  function clear() {
    items.value = []
  }

  return {
    items,
    push,
    success,
    error,
    warning,
    info,
    remove,
    clear,
  }
})

