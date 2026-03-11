<script setup>
import { watch } from 'vue'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  title: {
    type: String,
    default: '',
  },
  widthClass: {
    type: String,
    default: 'max-w-2xl',
  },
  closeOnBackdrop: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['close'])

function handleBackdropClick() {
  if (!props.closeOnBackdrop) return
  emit('close')
}

watch(
  () => props.open,
  (value) => {
    document.body.style.overflow = value ? 'hidden' : ''
  }
)
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[9998] flex items-center justify-center px-4 py-6"
    >
      <div
        class="absolute inset-0 bg-black/60 backdrop-blur-[2px]"
        @click="handleBackdropClick"
      />

      <div
        class="relative z-[9999] w-full rounded-2xl border border-[var(--border-default)] bg-[var(--bg-surface)] shadow-2xl"
        :class="widthClass"
      >
        <div class="flex items-center justify-between border-b border-[var(--border-default)] px-5 py-4">
          <h2 class="text-base font-semibold text-[var(--text-primary)]">
            {{ title }}
          </h2>

          <button
            type="button"
            class="rounded-md p-2 text-[var(--text-secondary)] hover:bg-[var(--bg-hover)] hover:text-[var(--text-primary)]"
            @click="$emit('close')"
          >
            <i class="mdi mdi-close text-lg" aria-hidden="true" />
          </button>
        </div>

        <div class="max-h-[80vh] overflow-y-auto px-5 py-4">
          <slot />
        </div>

        <div
          v-if="$slots.footer"
          class="flex items-center justify-end gap-2 border-t border-[var(--border-default)] px-5 py-4"
        >
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Teleport>
</template>