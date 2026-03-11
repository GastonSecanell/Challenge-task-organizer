<script setup>
import { ref, watch, nextTick, onMounted, onBeforeUnmount } from "vue";
import { Teleport } from "vue";
import { Plus, X } from "lucide-vue-next";
import BaseSpinner from "@/components/ui/BaseSpinner.vue";
import { EtiquetasApi } from "@/lib/api/etiquetas";
import { TareasApi } from "@/lib/api/tareas";
import { getEtiquetaStyle } from "@/lib/taskEtiquetas";
import { useToastStore } from "@/stores/toasts";

const props = defineProps({
  tareaId: {
    type: Number,
    required: true,
  },
  selectedEtiquetas: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["updated"]);

const toasts = useToastStore();

const triggerRef = ref(null);
const panelRef = ref(null);

const isOpen = ref(false);
const isLoading = ref(false);
const isSaving = ref(false);

const etiquetasDisponibles = ref([]);
const selectedIds = ref([]);

const position = ref({
  top: 0,
  left: 0,
});

function syncSelectedFromProps() {
  selectedIds.value = Array.isArray(props.selectedEtiquetas)
    ? props.selectedEtiquetas.map((et) => Number(et.id))
    : [];
}

function calculatePosition() {
  if (!triggerRef.value) return;

  const rect = triggerRef.value.getBoundingClientRect();

  const panelWidth = 260;
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

async function ensureEtiquetasLoaded() {
  if (etiquetasDisponibles.value.length > 0) return;

  isLoading.value = true;
  try {
    const res = await EtiquetasApi.list();
    etiquetasDisponibles.value = res?.data ?? [];
  } catch (error) {
    toasts.error(error?.message || "No se pudieron cargar las etiquetas.");
  } finally {
    isLoading.value = false;
  }
}

async function openDropdown() {
  syncSelectedFromProps();
  isOpen.value = true;

  await nextTick();
  calculatePosition();

  await ensureEtiquetasLoaded();

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

async function toggleEtiqueta(etiquetaId) {
  const id = Number(etiquetaId);
  if (!id || isSaving.value) return;

  const exists = selectedIds.value.includes(id);

  if (exists) {
    selectedIds.value = selectedIds.value.filter((item) => item !== id);
  } else {
    selectedIds.value = [...selectedIds.value, id];
  }

  isSaving.value = true;

  try {
    await TareasApi.updateEtiquetas(props.tareaId, {
      etiquetas: selectedIds.value,
    });

    emit("updated");
  } catch (error) {
    console.error(error);
    toasts.error(
      error?.response?.data?.message ||
        error?.message ||
        "No se pudieron actualizar las etiquetas.",
    );
    syncSelectedFromProps();
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

watch(
  () => props.selectedEtiquetas,
  () => {
    syncSelectedFromProps();
  },
  { immediate: true, deep: true },
);

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
  <div class="shrink-0">
    <button
      ref="triggerRef"
      type="button"
      class="inline-flex h-6 w-6 items-center justify-center rounded-full border border-[var(--border-default)] bg-[var(--bg-surface)] text-[var(--text-secondary)] transition hover:border-[var(--accent)] hover:text-[var(--accent)]"
      title="Gestionar etiquetas"
      @click.stop="toggleDropdown"
    >
      <Plus class="h-3.5 w-3.5" />
    </button>

    <Teleport to="body">
      <div
        v-if="isOpen"
        ref="panelRef"
        class="fixed z-[99999] w-[260px] rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] p-3 shadow-2xl"
        :style="{
          top: `${position.top}px`,
          left: `${position.left}px`,
        }"
      >
        <div class="mb-3 flex items-center justify-between">
          <span class="text-sm font-semibold text-[var(--text-primary)]">
            Etiquetas
          </span>

          <button
            type="button"
            class="text-[var(--text-secondary)] transition hover:text-[var(--text-primary)]"
            @click="closeDropdown"
          >
            <X class="h-4 w-4" />
          </button>
        </div>

        <div v-if="isLoading" class="flex justify-center py-6">
          <BaseSpinner size="sm" />
        </div>

        <div v-else class="space-y-2">
          <label
            v-for="etiqueta in etiquetasDisponibles"
            :key="etiqueta.id"
            class="flex cursor-pointer items-center gap-2 rounded-md border px-2.5 py-2 text-sm font-medium transition hover:opacity-90"
            :class="getEtiquetaStyle(etiqueta.etiqueta).chip"
          >
            <input
              type="checkbox"
              :checked="selectedIds.includes(Number(etiqueta.id))"
              :disabled="isSaving"
              :class="getEtiquetaStyle(etiqueta.etiqueta).checkbox"
              @change="toggleEtiqueta(etiqueta.id)"
            />
            <span>{{ etiqueta.etiqueta }}</span>
          </label>

          <div
            v-if="isSaving"
            class="pt-2 text-center text-xs text-[var(--text-muted)]"
          >
            Guardando...
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
