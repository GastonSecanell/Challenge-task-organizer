<script setup>
import { ref, nextTick, onMounted, onBeforeUnmount } from "vue";
import { ChevronDown } from "lucide-vue-next";
import BaseSpinner from "@/components/ui/BaseSpinner.vue";
import { getPrioridadClass, getPrioridadLabel } from "@/lib/taskPrioridades";

const props = defineProps({
  value: {
    type: Object,
    default: null,
  },
  prioridades: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["change"]);

const triggerRef = ref(null);
const panelRef = ref(null);

const isOpen = ref(false);
const isSaving = ref(false);

const position = ref({
  top: 0,
  left: 0,
});

function calculatePosition() {
  if (!triggerRef.value) return;

  const rect = triggerRef.value.getBoundingClientRect();
  const panelWidth = 240;
  const spacing = 8;

  let left = rect.left;
  let top = rect.bottom + spacing;

  const viewportWidth = window.innerWidth;
  const viewportHeight = window.innerHeight;

  if (left + panelWidth > viewportWidth - 12) {
    left = Math.max(12, viewportWidth - panelWidth - 12);
  }

  if (panelRef.value) {
    const panelHeight = panelRef.value.offsetHeight || 0;

    if (top + panelHeight > viewportHeight - 12) {
      top = Math.max(12, rect.top - panelHeight - spacing);
    }
  }

  position.value = { top, left };
}

async function openDropdown() {
  isOpen.value = true;
  await nextTick();
  calculatePosition();
}

function closeDropdown() {
  isOpen.value = false;
}

async function toggleDropdown() {
  if (isOpen.value) {
    closeDropdown();
    return;
  }

  await openDropdown();
}

async function selectPrioridad(prioridad) {
  if (isSaving.value || prioridad.id === props.value?.id) {
    closeDropdown();
    return;
  }

  isSaving.value = true;

  try {
    await emit("change", prioridad.id);
    closeDropdown();
  } finally {
    isSaving.value = false;
  }
}

function handleClickOutside(event) {
  if (!isOpen.value) return;

  const clickedTrigger = triggerRef.value?.contains(event.target);
  const clickedPanel = panelRef.value?.contains(event.target);

  if (clickedTrigger || clickedPanel) return;

  closeDropdown();
}

function handleWindowChange() {
  if (!isOpen.value) return;
  calculatePosition();
}

onMounted(() => {
  document.addEventListener("mousedown", handleClickOutside);
  window.addEventListener("resize", handleWindowChange);
  window.addEventListener("scroll", handleWindowChange, true);
});

onBeforeUnmount(() => {
  document.removeEventListener("mousedown", handleClickOutside);
  window.removeEventListener("resize", handleWindowChange);
  window.removeEventListener("scroll", handleWindowChange, true);
});
</script>

<template>
  <div class="inline-flex">
    <button
      ref="triggerRef"
      type="button"
      title="Cambiar prioridad"
      class="inline-flex min-w-[108px] items-center justify-between gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition hover:opacity-90"
      :class="getPrioridadClass(value?.prioridad)"
      @click.stop="toggleDropdown"
    >
      <span class="truncate">{{ getPrioridadLabel(value?.prioridad) }}</span>
      <ChevronDown class="h-3.5 w-3.5 shrink-0" />
    </button>

    <Teleport to="body">
      <div
        v-if="isOpen"
        ref="panelRef"
        class="fixed z-[99999] w-[240px] rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-2 shadow-2xl"
        :style="{
          top: `${position.top}px`,
          left: `${position.left}px`,
        }"
      >
        <div
          class="mb-2 px-2 pt-1 text-xs font-semibold uppercase tracking-wide text-[var(--text-muted)]"
        >
          Cambiar prioridad
        </div>

        <button
          v-for="prioridad in prioridades"
          :key="prioridad.id"
          type="button"
          class="flex w-full items-center justify-between rounded-md px-2.5 py-2 text-left text-sm transition hover:bg-[var(--bg-hover)]"
          @click="selectPrioridad(prioridad)"
        >
          <span class="text-[var(--text-primary)]">
            {{ prioridad.prioridad }}
          </span>

          <span
            class="inline-flex min-w-[88px] items-center justify-center rounded-full border px-2.5 py-1 text-[11px] font-semibold"
            :class="getPrioridadClass(prioridad.prioridad)"
          >
            {{ prioridad.prioridad }}
          </span>
        </button>

        <div v-if="isSaving" class="flex justify-center py-2">
          <BaseSpinner size="sm" />
        </div>
      </div>
    </Teleport>
  </div>
</template>
