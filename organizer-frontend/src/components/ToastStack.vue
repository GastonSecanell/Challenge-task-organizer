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
</script>

<template>
  <div class="toast-stack" aria-live="polite" aria-relevant="additions removals">
    <div v-for="t in ordered" :key="t.id" class="toast" :class="`toast--${t.type}`">
      <i class="mdi toast__icon" :class="iconFor(t.type)" aria-hidden="true"></i>
      <div class="toast__message">{{ t.message }}</div>
      <md-icon-button type="button" class="toast__close" title="Cerrar" @click="toasts.remove(t.id)">
        <i class="mdi mdi-close" aria-hidden="true"></i>
      </md-icon-button>
    </div>
  </div>
</template>

