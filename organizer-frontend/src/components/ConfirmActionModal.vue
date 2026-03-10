<script setup>
const props = defineProps({
  isOpen: { type: Boolean, required: true },
  title: { type: String, default: 'Confirmar' },
  message: { type: String, default: '' },
  confirmText: { type: String, default: 'Eliminar' },
  cancelText: { type: String, default: 'Cancelar' },
  isLoading: { type: Boolean, default: false },
})

const emit = defineEmits(['confirm', 'close'])
</script>

<template>
  <div v-if="props.isOpen" class="confirm-backdrop" @click.self="emit('close')">
    <div class="confirm-modal">
      <div class="confirm-modal__title">{{ props.title }}</div>
      <div class="confirm-modal__message">{{ props.message }}</div>
      <div class="confirm-modal__actions">
        <md-text-button type="button" :disabled="props.isLoading" @click="emit('close')">
          <span>{{ props.cancelText }}</span>
        </md-text-button>
        <md-filled-button type="button" :disabled="props.isLoading" @click="emit('confirm')">
          <span>{{ props.confirmText }}</span>
          <md-circular-progress v-if="props.isLoading" indeterminate class="md-spinner-inline-md"></md-circular-progress>
        </md-filled-button>
      </div>
    </div>
  </div>
</template>

