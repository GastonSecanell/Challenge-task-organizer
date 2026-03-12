<script setup>
import { computed } from 'vue'
import { CheckCircle2, AlertCircle, AlertTriangle, Info, X } from 'lucide-vue-next'
import { useToastStore } from '@/stores/toasts'

const toasts = useToastStore()

const ordered = computed(() => toasts.items)

function iconFor(type) {
  if (type === 'success') return CheckCircle2
  if (type === 'error') return AlertCircle
  if (type === 'warning') return AlertTriangle
  return Info
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
    class="pointer-events-none fixed right-4 bottom-4 z-[9999] flex w-full max-w-sm flex-col gap-3"
    aria-live="polite"
    aria-relevant="additions removals"
  >
    <transition-group name="toast" tag="div" class="flex flex-col gap-3">
      <div
        v-for="t in ordered"
        :key="t.id"
        class="pointer-events-auto flex items-start gap-3 rounded-2xl border px-4 py-3 shadow-lg backdrop-blur-sm"
        :class="toneClass(t.type)"
      >
        <component
          :is="iconFor(t.type)"
          class="mt-0.5 h-5 w-5 shrink-0"
          aria-hidden="true"
        />

        <div class="min-w-0 flex-1">
          <p class="break-words text-sm leading-5">
            {{ t.message }}
          </p>
        </div>

        <button
          type="button"
          class="shrink-0 rounded-md p-1 text-[var(--text-secondary)] transition hover:bg-[var(--bg-hover)] hover:text-[var(--text-primary)]"
          title="Cerrar"
          @click="toasts.remove(t.id)"
        >
          <X class="h-4 w-4" />
        </button>
      </div>
    </transition-group>
  </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.22s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateY(-8px) scale(0.98);
}

.toast-leave-to {
  opacity: 0;
  transform: translateY(-8px) scale(0.98);
}
</style>