<script setup>
import { ref, nextTick, onMounted, onBeforeUnmount } from 'vue'
import { ChevronDown } from 'lucide-vue-next'
import BaseSpinner from '@/components/ui/BaseSpinner.vue'

const props = defineProps({
  value: {
    type: String,
    required: true,
  },
})

const emit = defineEmits(['change'])

const triggerRef = ref(null)
const panelRef = ref(null)

const isOpen = ref(false)
const isSaving = ref(false)

const position = ref({
  top: 0,
  left: 0,
})

const options = [
  { value: 'pendiente', label: 'PENDIENTE' },
  { value: 'en_progreso', label: 'EN PROGRESO' },
  { value: 'completada', label: 'COMPLETADA' },
]

function estadoLabel(estado) {
  if (estado === 'en_progreso') return 'EN PROGRESO'
  if (estado === 'completada') return 'COMPLETADA'
  return 'PENDIENTE'
}

function estadoClass(estado) {
  if (estado === 'completada') {
    return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300'
  }

  if (estado === 'en_progreso') {
    return 'border-amber-500/30 bg-amber-500/10 text-amber-300'
  }

  return 'border-slate-500/30 bg-slate-500/10 text-slate-300'
}

function calculatePosition() {
  if (!triggerRef.value) return

  const rect = triggerRef.value.getBoundingClientRect()
  const panelWidth = 240
  const spacing = 8

  let left = rect.left
  let top = rect.bottom + spacing

  const viewportWidth = window.innerWidth
  const viewportHeight = window.innerHeight

  if (left + panelWidth > viewportWidth - 12) {
    left = Math.max(12, viewportWidth - panelWidth - 12)
  }

  if (panelRef.value) {
    const panelHeight = panelRef.value.offsetHeight || 0

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

async function selectEstado(estado) {
  if (isSaving.value || estado === props.value) {
    closeDropdown()
    return
  }

  isSaving.value = true

  try {
    await emit('change', estado)
    closeDropdown()
  } finally {
    isSaving.value = false
  }
}

function handleClickOutside(event) {
  if (!isOpen.value) return

  const clickedTrigger = triggerRef.value?.contains(event.target)
  const clickedPanel = panelRef.value?.contains(event.target)

  if (clickedTrigger || clickedPanel) return

  closeDropdown()
}

function handleWindowChange() {
  if (!isOpen.value) return
  calculatePosition()
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
  <div class="inline-flex">
    <button
      ref="triggerRef"
      type="button"
      class="inline-flex min-w-[124px] items-center justify-between gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition hover:opacity-90"
      :class="estadoClass(value)"
      @click.stop="toggleDropdown"
    >
      <span class="truncate">{{ estadoLabel(value) }}</span>
      <ChevronDown class="h-3.5 w-3.5 shrink-0" />
    </button>

    <Teleport to="body">
      <div
        v-if="isOpen"
        ref="panelRef"
        class="fixed z-[99999] w-[260px] rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-2 shadow-2xl"
        :style="{
          top: `${position.top}px`,
          left: `${position.left}px`,
        }"
      >
        <div class="mb-2 px-2 pt-1 text-xs font-semibold uppercase tracking-wide text-[var(--text-muted)]">
          Cambiar estado
        </div>

        <button
          v-for="option in options"
          :key="option.value"
          type="button"
          class="flex w-full items-center justify-between rounded-md px-2.5 py-2 text-left text-sm transition hover:bg-[var(--bg-hover)]"
          @click="selectEstado(option.value)"
        >
          <span class="text-[var(--text-primary)]">{{ option.label }}</span>

          <span
            class="inline-flex min-w-[108px] items-center justify-center rounded-full border px-2.9 py-1 text-[11px] font-semibold"
            :class="estadoClass(option.value)"
          >
            {{ option.label }}
          </span>
        </button>

        <div v-if="isSaving" class="flex justify-center py-2">
          <BaseSpinner size="sm" />
        </div>
      </div>
    </Teleport>
  </div>
</template>