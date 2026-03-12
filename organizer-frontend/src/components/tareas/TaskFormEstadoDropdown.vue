<script setup>
import { computed, ref, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { ChevronDown } from 'lucide-vue-next'
import { getEstadoClass, getEstadoLabel } from '@/lib/taskEstados'

const props = defineProps({
  modelValue: {
    type: String,
    default: 'pendiente',
  },
  error: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue'])

const triggerRef = ref(null)
const panelRef = ref(null)
const isOpen = ref(false)

const position = ref({
  top: 0,
  left: 0,
})

const options = [
  { value: 'pendiente', label: 'PENDIENTE' },
  { value: 'en_progreso', label: 'EN PROGRESO' },
  { value: 'completada', label: 'COMPLETADA' },
]

const triggerClass = computed(() => {
  return [
    'inline-flex w-full items-center justify-between rounded-xl border px-3 py-2.5 text-sm transition',
    props.error
      ? 'border-red-500/60 ring-2 ring-red-500/15'
      : 'border-[var(--border-default)] hover:border-[var(--accent)]',
  ].join(' ')
})

function calculatePosition() {
  if (!triggerRef.value) return

  const rect = triggerRef.value.getBoundingClientRect()
  const panelWidth = rect.width
  const spacing = 8

  let left = rect.left
  let top = rect.bottom + spacing

  if (panelRef.value) {
    const panelHeight = panelRef.value.offsetHeight || 0
    const viewportHeight = window.innerHeight

    if (top + panelHeight > viewportHeight - 12) {
      top = Math.max(12, rect.top - panelHeight - spacing)
    }
  }

  position.value = { top, left }
}

async function openDropdown() {
  isOpen.value = true
  await nextTick()
  calculatePosition()
}

function closeDropdown() {
  isOpen.value = false
}

async function toggleDropdown() {
  if (isOpen.value) {
    closeDropdown()
    return
  }

  await openDropdown()
}

function selectEstado(value) {
  emit('update:modelValue', value)
  closeDropdown()
}

function handleClickOutside(event) {
  if (!isOpen.value) return

  const clickedTrigger = triggerRef.value?.contains(event.target)
  const clickedPanel = panelRef.value?.contains(event.target)

  if (!clickedTrigger && !clickedPanel) {
    closeDropdown()
  }
}

function handleWindowChange() {
  if (isOpen.value) {
    calculatePosition()
  }
}

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside)
  window.addEventListener('resize', handleWindowChange)
  window.addEventListener('scroll', handleWindowChange, true)
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleClickOutside)
  window.removeEventListener('resize', handleWindowChange)
  window.removeEventListener('scroll', handleWindowChange, true)
})
</script>

<template>
  <div class="w-full">
    <button
      ref="triggerRef"
      type="button"
      :class="triggerClass"
      @click.stop="toggleDropdown"
    >
      <span
        class="inline-flex min-w-[124px] items-center justify-center rounded-full border px-3 py-1.5 text-xs font-semibold"
        :class="getEstadoClass(modelValue)"
      >
        {{ getEstadoLabel(modelValue) }}
      </span>

      <ChevronDown class="h-4 w-4 text-[var(--text-secondary)]" />
    </button>

    <Teleport to="body">
      <div
        v-if="isOpen"
        ref="panelRef"
        class="fixed z-[99999] rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-2 shadow-2xl"
        :style="{
          top: `${position.top}px`,
          left: `${position.left}px`,
          width: `${triggerRef?.offsetWidth || 240}px`,
        }"
      >
        <button
          v-for="option in options"
          :key="option.value"
          type="button"
          class="flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-left text-sm transition hover:bg-[var(--bg-hover)]"
          @click="selectEstado(option.value)"
        >
          <span class="text-[var(--text-primary)]">{{ option.label }}</span>

          <span
            class="inline-flex min-w-[108px] items-center justify-center rounded-full border px-2.5 py-1 text-[11px] font-semibold"
            :class="getEstadoClass(option.value)"
          >
            {{ option.label }}
          </span>
        </button>
      </div>
    </Teleport>
  </div>
</template>