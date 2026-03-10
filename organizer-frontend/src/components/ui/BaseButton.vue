<script setup>
import { computed } from 'vue'

const props = defineProps({
  type: {
    type: String,
    default: 'button',
  },
  variant: {
    type: String,
    default: 'default',
    validator: (value) => ['default', 'destructive', 'outline', 'ghost'].includes(value),
  },
  size: {
    type: String,
    default: 'default',
    validator: (value) => ['sm', 'default', 'lg', 'icon'].includes(value),
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  block: {
    type: Boolean,
    default: false,
  },
})

const baseClass = `
  inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md
  text-sm font-medium transition-colors
  focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--accent)]
  disabled:pointer-events-none disabled:opacity-50 active:scale-[0.98]
`

const variantClasses = {
  default: 'bg-[var(--accent)] text-white hover:brightness-110',
  destructive: 'bg-[var(--danger)] text-white hover:brightness-110',
  outline: 'border border-[var(--border-default)] bg-transparent text-[var(--text-primary)] hover:bg-[var(--bg-hover)]',
  ghost: 'text-[var(--text-primary)] hover:bg-[var(--bg-hover)]',
}

const sizeClasses = {
  sm: 'h-8 px-3 text-xs',
  default: 'h-9 px-4 py-2',
  lg: 'h-10 px-8',
  icon: 'h-9 w-9 p-0',
}

const classes = computed(() => [
  baseClass,
  variantClasses[props.variant],
  sizeClasses[props.size],
  props.block ? 'w-full' : '',
])
</script>

<template>
  <button
    :type="type"
    :disabled="disabled"
    :class="classes"
  >
    <slot />
  </button>
</template>