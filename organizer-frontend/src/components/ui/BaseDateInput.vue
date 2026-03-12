<script setup>
const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  placeholder: {
    type: String,
    default: '',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  clearable: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'clear'])

function onInput(event) {
  emit('update:modelValue', event.target.value)
}

function clearValue() {
  emit('update:modelValue', '')
  emit('clear')
}
</script>

<template>
  <div class="base-date-input">
    <input
      :value="modelValue"
      type="date"
      :disabled="disabled"
      class="base-date-input__control"
      @input="onInput"
    >

    <button
      v-if="clearable && modelValue"
      type="button"
      class="base-date-input__clear"
      title="Limpiar fecha"
      @click="clearValue"
    >
      ×
    </button>
  </div>
</template>

<style scoped>
.base-date-input {
  position: relative;
  width: 100%;
}

.base-date-input__control {
  height: 36px;
  width: 100%;
  border-radius: 0.5rem;
  border: 1px solid var(--border-default);
  background: var(--bg-surface);
  color: var(--text-primary);
  padding: 0 2.25rem 0 0.75rem;
  font-size: 0.875rem;
  outline: none;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease,
    background-color 0.2s ease;
}

.base-date-input__control:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px color-mix(in srgb, var(--accent) 20%, transparent);
}

.base-date-input__control::-webkit-calendar-picker-indicator {
  cursor: pointer;
  opacity: 0.85;
}

.base-date-input__control::-webkit-datetime-edit {
  color: var(--text-primary);
}

.base-date-input__control::-webkit-datetime-edit-fields-wrapper {
  color: var(--text-primary);
}

.base-date-input__control::-webkit-datetime-edit-text {
  color: var(--text-muted);
}

.base-date-input__clear {
  position: absolute;
  right: 2rem;
  top: 50%;
  transform: translateY(-50%);
  border: 0;
  background: transparent;
  color: var(--text-muted);
  cursor: pointer;
  font-size: 1rem;
  line-height: 1;
}

.base-date-input__clear:hover {
  color: var(--danger);
}
</style>