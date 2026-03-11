<script setup>
import { ChevronDown } from 'lucide-vue-next'

defineProps({
  modelValue: {
    type: [String, Number],
    default: '',
  },
  roles: {
    type: Array,
    default: () => [],
  },
  placeholder: {
    type: String,
    default: 'Todos los roles',
  },
  className: {
    type: String,
    default: '',
  },
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <div class="relative" :class="className">
    <select
      :value="modelValue"
      class="h-9 w-full appearance-none rounded-md border border-[var(--border-default)] bg-[var(--bg-surface)] px-3 pr-8 text-sm text-[var(--text-primary)] transition-colors focus:border-[var(--accent)] focus:outline-none focus:ring-1 focus:ring-[var(--accent)]/30"
      @change="emit('update:modelValue', $event.target.value)"
    >
      <option value="">{{ placeholder }}</option>

      <option
        v-for="role in roles"
        :key="role.id"
        :value="role.id"
      >
        {{ role.name }}
      </option>
    </select>

    <ChevronDown
      class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--chart-organic)]"
      aria-hidden="true"
    />
  </div>
</template>