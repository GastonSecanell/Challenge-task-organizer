<script setup>
import { computed, ref, watch, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { ChevronDown } from 'lucide-vue-next'
import { getEtiquetaStyle } from '@/lib/taskEtiquetas'

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => [],
  },
  etiquetas: {
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

const selectedEtiquetas = computed(() => {
  return props.etiquetas.filter((item) =>
    props.modelValue.map(Number).includes(Number(item.id)),
  )
})

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

function toggleEtiqueta(id) {
  if (props.disabled) return

  const numericId = Number(id)
  const selected = props.modelValue.map(Number)

  if (selected.includes(numericId)) {
    emit('update:modelValue', selected.filter((item) => item !== numericId))
    return
  }

  emit('update:modelValue', [...selected, numericId])
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
      <div class="flex min-h-[24px] flex-1 flex-wrap items-center gap-1.5 text-left">
        <template v-if="selectedEtiquetas.length">
          <span
            v-for="etiqueta in selectedEtiquetas"
            :key="etiqueta.id"
            class="inline-flex items-center rounded-full border px-2 py-1 text-xs font-medium"
            :class="getEtiquetaStyle(etiqueta.etiqueta).badge"
          >
            {{ etiqueta.etiqueta }}
          </span>
        </template>

        <span v-else class="text-sm text-[var(--text-muted)]">
          Seleccionar etiquetas
        </span>
      </div>

      <ChevronDown class="ml-3 h-4 w-4 shrink-0 text-[var(--text-secondary)]" />
    </button>

    <Teleport to="body">
      <div
        v-if="isOpen && !disabled"
        ref="panelRef"
        class="fixed z-[99999] rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-3 shadow-2xl"
        :style="{
          top: `${position.top}px`,
          left: `${position.left}px`,
          width: `${triggerRef?.offsetWidth || 260}px`,
        }"
      >
        <div class="max-h-[280px] space-y-2 overflow-auto pr-1">
          <label
            v-for="etiqueta in etiquetas"
            :key="etiqueta.id"
            class="flex items-center gap-2 rounded-lg border px-2.5 py-2 text-sm font-medium transition"
            :class="[
              getEtiquetaStyle(etiqueta.etiqueta).chip,
              disabled ? 'cursor-not-allowed opacity-70' : 'cursor-pointer hover:opacity-90',
            ]"
          >
            <input
              type="checkbox"
              :disabled="disabled"
              :checked="modelValue.map(Number).includes(Number(etiqueta.id))"
              :class="getEtiquetaStyle(etiqueta.etiqueta).checkbox"
              @change="toggleEtiqueta(etiqueta.id)"
            >
            <span>{{ etiqueta.etiqueta }}</span>
          </label>
        </div>
      </div>
    </Teleport>
  </div>
</template>