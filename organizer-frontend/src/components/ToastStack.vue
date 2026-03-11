<script setup>
import { computed } from 'vue'
import { useToastStore } from '@/stores/toasts'

const toasts = useToastStore()

const ordered = computed(() => toasts.items)

function iconFor(type) {
  if (type === 'success') return 'mdi-check-circle-outline'
  if (type === 'error') return 'mdi-alert-circle-outline'
  if (type === 'warning') return 'mdi-alert-outline'
  return 'mdi-information-outline'
}

function toneClass(type) {
  if (type === 'success') {
    return 'border-[var(--success)] bg-green-500/10 text-[var(--text-primary)]'
  }

  if (type === 'error') {
    return 'border-[var(--danger)] bg-red-500/10 text-[var(--text-primary)]'
  }

  if (type === 'warning') {
    return 'border-yellow-500 bg-yellow-500/10 text-[var(--text-primary)]'
  }

  return 'border-[var(--accent)] bg-[var(--accent-soft)] text-[var(--text-primary)]'
}
</script>

<template>
  <div
    class="pointer-events-none fixed right-4 top-4 z-[9999] flex w-full max-w-sm flex-col gap-3"
    aria-live="polite"
    aria-relevant="additions removals"
  >
    <div
      v-for="t in ordered"
      :key="t.id"
      class="pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 shadow-lg backdrop-blur-sm"
      :class="toneClass(t.type)"
    >
      <i
        class="mdi mt-0.5 text-lg"
        :class="iconFor(t.type)"
        aria-hidden="true"
      />

      <div class="min-w-0 flex-1">
        <p class="break-words text-sm leading-5">
          {{ t.message }}
        </p>
      </div>

      <button
        type="button"
        class="shrink-0 rounded-md p-1 text-[var(--text-secondary)] hover:bg-[var(--bg-hover)] hover:text-[var(--text-primary)]"
        title="Cerrar"
        @click="toasts.remove(t.id)"
      >
        <i class="mdi mdi-close text-base" aria-hidden="true" />
      </button>
    </div>
  </div>
</template>

