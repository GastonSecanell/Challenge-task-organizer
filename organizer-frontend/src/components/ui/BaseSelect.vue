<script setup>
import { ChevronDown } from 'lucide-vue-next'

const props = defineProps({
  modelValue: {
    type: [String, Number],
    default: '',
  },
  options: {
    type: Array,
    default: () => [],
  },
  optionLabel: {
    type: String,
    default: 'label',
  },
  optionValue: {
    type: String,
    default: 'value',
  },
  placeholder: {
    type: String,
    default: 'Seleccionar...',
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  name: {
    type: String,
    default: '',
  },
  id: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:modelValue', 'change'])

function onChange(event) {
  const value = event.target.value
  emit('update:modelValue', value)
  emit('change', value)
}
</script>

<template>
  <div class="base-select">
    <select
      :id="id"
      :name="name"
      :value="modelValue"
      :disabled="disabled"
      class="base-select__control"
      @change="onChange"
    >
      <option value="">{{ placeholder }}</option>

      <option
        v-for="option in options"
        :key="option[optionValue]"
        :value="option[optionValue]"
      >
        {{ option[optionLabel] }}
      </option>
    </select>

    <ChevronDown class="base-select__icon" />
  </div>
</template>