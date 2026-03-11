<script setup>
defineProps({
  size: {
    type: String,
    default: 'md',
    validator: (value) => ['sm', 'md', 'lg'].includes(value),
  },
  label: {
    type: String,
    default: '',
  },
  center: {
    type: Boolean,
    default: false,
  },
})

const sizeClasses = {
  sm: 'h-4 w-4 border-2',
  md: 'h-6 w-6 border-2',
  lg: 'h-10 w-10 border-[3px]',
}
</script>

<template>
  <div :class="center ? 'flex items-center justify-center' : 'inline-flex items-center'">
    <div
      class="animate-spin rounded-full border-[var(--border-default)] border-t-[var(--accent)]"
      :class="sizeClasses[size]"
      aria-hidden="true"
    />
    <span v-if="label" class="ml-2 text-sm text-[var(--text-secondary)]">
      {{ label }}
    </span>
  </div>
</template>