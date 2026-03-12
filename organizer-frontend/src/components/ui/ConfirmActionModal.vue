<script setup>
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import BaseSpinner from '@/components/ui/BaseSpinner.vue'
import { AlertTriangle } from 'lucide-vue-next'

const props = defineProps({
  open: {
    type: Boolean,
    required: true,
  },
  title: {
    type: String,
    default: 'Confirmar acción',
  },
  message: {
    type: String,
    default: '',
  },
  confirmText: {
    type: String,
    default: 'Confirmar',
  },
  cancelText: {
    type: String,
    default: 'Cancelar',
  },
  isLoading: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['confirm', 'close'])
</script>

<template>
  <BaseModal
    :open="open"
    width-class="max-w-md"
    @close="emit('close')"
  >
    <div class="flex items-start gap-4">
      <div
        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-red-500/20 bg-red-500/10 text-red-400"
      >
        <AlertTriangle class="h-5 w-5" />
      </div>

      <div class="min-w-0 flex-1">
        <h3 class="text-base font-semibold text-[var(--text-primary)]">
          {{ title }}
        </h3>

        <p class="mt-2 text-sm leading-6 text-[var(--text-secondary)]">
          {{ message }}
        </p>
      </div>
    </div>

    <template #footer>
      <div class="flex w-full items-center justify-end gap-3">
        <BaseButton
          variant="ghost"
          :disabled="isLoading"
          @click="emit('close')"
        >
          {{ cancelText }}
        </BaseButton>

        <button
          type="button"
          :disabled="isLoading"
          class="inline-flex min-w-[132px] items-center justify-center gap-2 rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-500 disabled:cursor-not-allowed disabled:opacity-60"
          @click="emit('confirm')"
        >
          <BaseSpinner v-if="isLoading" size="sm" />
          <span>{{ isLoading ? 'Procesando...' : confirmText }}</span>
        </button>
      </div>
    </template>
  </BaseModal>
</template>