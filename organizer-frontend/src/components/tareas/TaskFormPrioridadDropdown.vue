<script setup>
import { computed, ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { ChevronDown } from 'lucide-vue-next'
import { getPrioridadClass, getPrioridadLabel } from '@/lib/taskPrioridades'

const props = defineProps({
  modelValue: {
    type: [Number, String, null],
    default: '',
  },
  prioridades: {
    type: Array,
    default: () => [],
  },
  error: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue'])

const triggerRef = ref(null)
const panelRef = ref(null)
const isOpen = ref(false)

const selectedPrioridad = computed(() =>
  props.prioridades.find((item) => Number(item.id) === Number(props.modelValue)) ?? null,
)

const triggerClass = computed(() => {
  return [
    'inline-flex w-full items-center justify-between rounded-xl border px-3 py-2.5 text-sm transition',
    props.disabled
      ? 'cursor-not-allowed border-[var(--border-default)] opacity-70'
      : props.error
        ? 'border-red-500/60 ring-2 ring-red-500/15'
        : 'border-[var(--border-default)] hover:border-[var(--accent)]',
  ].join(' ')
})

const position = ref({
  top: 0,
  left: 0,
})

function calculatePosition() {
  if (!triggerRef.value) return

  const rect = triggerRef.value.getBoundingClientRect()
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
  if (props.disabled) return
  isOpen.value = true
  await nextTick()
  calculatePosition()
}

function closeDropdown() {
  isOpen.value = false
}

async function toggleDropdown() {
  if (props.disabled) return

  if (isOpen.value) {
    closeDropdown()
    return
  }

  await openDropdown()
}

function selectPrioridad(prioridad) {
  if (props.disabled) return
  emit('update:modelValue', Number(prioridad.id))
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

watch(
  () => props.disabled,
  (value) => {
    if (value && isOpen.value) {
      closeDropdown()
    }
  },
)

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
      :disabled="disabled"
      :class="triggerClass"
      @click.stop="toggleDropdown"
    >
      <span
        v-if="selectedPrioridad"
        class="inline-flex min-w-[96px] items-center justify-center rounded-full border px-3 py-1.5 text-xs font-semibold"
        :class="getPrioridadClass(selectedPrioridad.prioridad)"
      >
        {{ getPrioridadLabel(selectedPrioridad.prioridad) }}
      </span>

      <span
        v-else
        class="text-sm text-[var(--text-muted)]"
      >
        Seleccionar prioridad
      </span>

      <ChevronDown class="h-4 w-4 text-[var(--text-secondary)]" />
    </button>

    <Teleport to="body">
      <div
        v-if="isOpen && !disabled"
        ref="panelRef"
        class="fixed z-[99999] rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-2 shadow-2xl"
        :style="{
          top: `${position.top}px`,
          left: `${position.left}px`,
          width: `${triggerRef?.offsetWidth || 240}px`,
        }"
      >
        <button
          v-for="prioridad in prioridades"
          :key="prioridad.id"
          type="button"
          class="flex w-full items-center justify-between rounded-lg px-2.5 py-2 text-left text-sm transition hover:bg-[var(--bg-hover)]"
          @click="selectPrioridad(prioridad)"
        >
          <span class="text-[var(--text-primary)]">{{ prioridad.prioridad }}</span>

          <span
            class="inline-flex min-w-[88px] items-center justify-center rounded-full border px-2.5 py-1 text-[11px] font-semibold"
            :class="getPrioridadClass(prioridad.prioridad)"
          >
            {{ prioridad.prioridad }}
          </span>
        </button>
      </div>
    </Teleport>
  </div>
</template>