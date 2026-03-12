<script setup>
import { reactive, ref, watch, computed } from "vue";
import { QuillEditor } from "@vueup/vue-quill";

import BaseModal from "@/components/ui/BaseModal.vue";
import BaseSpinner from "@/components/ui/BaseSpinner.vue";
import BaseButton from "@/components/ui/BaseButton.vue";

import { useToastStore } from "@/stores/toasts";
import { TareasApi } from "@/lib/api/tareas";
import { isValidEstado } from "@/lib/taskEstados";

import TaskFormEstadoDropdown from "./TaskFormEstadoDropdown.vue";
import TaskFormPrioridadDropdown from "./TaskFormPrioridadDropdown.vue";
import TaskFormEtiquetasDropdown from "./TaskFormEtiquetasDropdown.vue";

const props = defineProps({
  open: Boolean,
  tareaId: [Number, String, null],
  viewMode: {
    type: Boolean,
    default: false,
  },
  prioridades: {
    type: Array,
    default: () => [],
  },
  etiquetas: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(["close", "saved"]);
const toasts = useToastStore();

const isLoading = ref(false);
const isSaving = ref(false);

const form = reactive({
  titulo: "",
  descripcion: "<p><br></p>",
  estado: "pendiente",
  fecha_vencimiento: "",
  prioridad_id: "",
  etiquetas: [],
});

const errors = reactive({
  titulo: "",
  descripcion: "",
  estado: "",
  fecha_vencimiento: "",
  prioridad_id: "",
  etiquetas: "",
});

const isEdit = computed(() => Boolean(props.tareaId) && !props.viewMode);
const isReadOnly = computed(() => props.viewMode === true);

const modalTitle = computed(() => {
  if (isReadOnly.value) return "Ver tarea";
  return isEdit.value ? "Editar tarea" : "Nueva tarea";
});

const safeDescripcionHtml = computed(() => {
  return (
    form.descripcion ||
    "<p class='text-[var(--text-muted)]'>Sin descripción</p>"
  );
});

function fieldClass(hasError = false) {
  return [
    "w-full rounded-xl border bg-[var(--bg-surface)] px-3 py-2.5 text-sm text-[var(--text-primary)] outline-none transition placeholder-[var(--text-muted)] disabled:cursor-not-allowed disabled:opacity-80",
    hasError
      ? "border-red-500/60 ring-2 ring-red-500/15"
      : "border-[var(--border-default)] focus:border-[var(--accent)] focus:ring-2 focus:ring-[var(--accent)]/15",
  ].join(" ");
}

function resetErrors() {
  errors.titulo = "";
  errors.descripcion = "";
  errors.estado = "";
  errors.fecha_vencimiento = "";
  errors.prioridad_id = "";
  errors.etiquetas = "";
}

function resetForm() {
  form.titulo = "";
  form.descripcion = "<p><br></p>";
  form.estado = "pendiente";
  form.fecha_vencimiento = "";
  form.prioridad_id = "";
  form.etiquetas = [];
  resetErrors();
}

function clearFieldError(field) {
  if (errors[field]) {
    errors[field] = "";
  }
}

function hasValidPrioridad(prioridadId) {
  if (!prioridadId) return false;

  return props.prioridades.some(
    (item) => Number(item.id) === Number(prioridadId),
  );
}

function formatDateForInput(value) {
  if (!value) return "";

  if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    return value;
  }

  if (/^\d{2}\/\d{2}\/\d{4}$/.test(value)) {
    const [dd, mm, yyyy] = value.split("/");
    return `${yyyy}-${mm}-${dd}`;
  }

  return "";
}

function formatDateForView(value) {
  if (!value) return "Sin fecha";

  if (/^\d{2}\/\d{2}\/\d{4}$/.test(value)) {
    return value;
  }

  if (/^\d{4}-\d{2}-\d{2}$/.test(value)) {
    const [yyyy, mm, dd] = value.split("-");
    return `${dd}/${mm}/${yyyy}`;
  }

  return value;
}

function stripHtml(html) {
  return String(html ?? "")
    .replace(/<[^>]*>/g, " ")
    .replace(/&nbsp;/gi, " ")
    .replace(/\s+/g, " ")
    .trim();
}

function validate() {
  resetErrors();

  let valid = true;

  if (!form.titulo.trim()) {
    errors.titulo = "El título es obligatorio.";
    valid = false;
  } else if (form.titulo.trim().length < 3) {
    errors.titulo = "El título debe tener al menos 3 caracteres.";
    valid = false;
  }

  if (!stripHtml(form.descripcion)) {
    errors.descripcion = "La descripción es obligatoria.";
    valid = false;
  } else if (stripHtml(form.descripcion).length < 5) {
    errors.descripcion = "La descripción debe tener al menos 5 caracteres.";
    valid = false;
  }

  if (!isValidEstado(form.estado)) {
    errors.estado = "El estado seleccionado no es válido.";
    valid = false;
  }

  if (!hasValidPrioridad(form.prioridad_id)) {
    errors.prioridad_id = "La prioridad seleccionada no es válida.";
    valid = false;
  }

  return valid;
}

function applyBackendErrors(error) {
  const backendErrors = error?.response?.data?.errors;
  if (!backendErrors || typeof backendErrors !== "object") return;

  Object.keys(errors).forEach((key) => {
    const value = backendErrors[key];
    if (Array.isArray(value) && value.length > 0) {
      errors[key] = String(value[0]);
    }
  });
}

async function loadTarea(id) {
  const res = await TareasApi.get(id);
  const tarea = res?.data;

  form.titulo = tarea?.titulo ?? "";
  form.descripcion = tarea?.descripcion ?? "";
  form.estado = tarea?.estado ?? "pendiente";
  form.fecha_vencimiento = formatDateForInput(tarea?.fecha_vencimiento ?? "");
  form.prioridad_id = tarea?.prioridad_id ? Number(tarea.prioridad_id) : "";
  form.etiquetas = Array.isArray(tarea?.etiquetas)
    ? tarea.etiquetas.map((et) => Number(et.id))
    : [];
}

async function initModal() {
  if (!props.open) return;

  isLoading.value = true;

  try {
    resetForm();

    if (props.tareaId) {
      await loadTarea(props.tareaId);
    }
  } catch {
    toasts.error("No se pudieron cargar los datos del formulario.");
    emit("close");
  } finally {
    isLoading.value = false;
  }
}

async function submitTarea() {
  if (isReadOnly.value) return;
  if (isSaving.value) return;
  if (!validate()) return;

  isSaving.value = true;
  resetErrors();

  try {
    const payload = {
      titulo: form.titulo.trim(),
      descripcion: form.descripcion,
      estado: form.estado,
      fecha_vencimiento: form.fecha_vencimiento || null,
      prioridad_id: Number(form.prioridad_id),
      etiquetas: form.etiquetas.map(Number),
    };

    if (isEdit.value) {
      const res = await TareasApi.update(props.tareaId, payload);
      toasts.success(res?.message || "Tarea actualizada");
    } else {
      const res = await TareasApi.create(payload);
      toasts.success(res?.message || "Tarea creada");
    }

    emit("saved");
  } catch (error) {
    applyBackendErrors(error);
    toasts.error(
      error?.response?.data?.message ||
        error?.message ||
        "Error al guardar la tarea.",
    );
  } finally {
    isSaving.value = false;
  }
}

watch(
  () => [props.open, props.tareaId, props.viewMode],
  () => {
    if (props.open) {
      initModal();
    }
  },
);

watch(
  () => form.titulo,
  () => clearFieldError("titulo"),
);
watch(
  () => form.descripcion,
  () => clearFieldError("descripcion"),
);
watch(
  () => form.estado,
  () => clearFieldError("estado"),
);
watch(
  () => form.fecha_vencimiento,
  () => clearFieldError("fecha_vencimiento"),
);
watch(
  () => form.prioridad_id,
  () => clearFieldError("prioridad_id"),
);
watch(
  () => form.etiquetas,
  () => clearFieldError("etiquetas"),
  { deep: true },
);
</script>

<template>
  <BaseModal
    :open="open"
    :title="modalTitle"
    width-class="max-w-3xl"
    @close="$emit('close')"
  >
    <div v-if="isLoading" class="flex justify-center py-10">
      <BaseSpinner size="lg" />
    </div>

    <form v-else class="space-y-5" @submit.prevent="submitTarea">
      <div class="grid grid-cols-1 gap-5">
        <div>
          <label
            class="mb-1.5 block text-sm font-semibold text-[var(--text-primary)]"
          >
            Título
          </label>

          <input
            v-model="form.titulo"
            type="text"
            maxlength="255"
            placeholder="Ej: Ajustar validación de tareas"
            :disabled="isReadOnly"
            :class="fieldClass(!!errors.titulo)"
          />

          <div class="mt-1 flex items-center justify-between gap-3">
            <p
              v-if="errors.titulo && !isReadOnly"
              class="text-xs font-medium text-red-500"
            >
              {{ errors.titulo }}
            </p>
            <span v-else class="text-xs text-[var(--text-muted)]">
              {{ form.titulo.trim().length }}/255
            </span>
          </div>
        </div>

        <div>
          <label
            class="mb-1.5 block text-sm font-semibold text-[var(--text-primary)]"
          >
            Descripción
          </label>

          <template v-if="isReadOnly">
            <div
              class="task-description-view rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] px-4 py-3 text-sm text-[var(--text-primary)]"
              v-html="safeDescripcionHtml"
            ></div>
          </template>

          <template v-else>
            <div
              class="rounded-xl border bg-[var(--bg-surface)] transition"
              :class="
                errors.descripcion
                  ? 'border-red-500/60 ring-2 ring-red-500/15'
                  : 'border-[var(--border-default)] focus-within:border-[var(--accent)] focus-within:ring-2 focus-within:ring-[var(--accent)]/15'
              "
            >
              <QuillEditor
                v-model:content="form.descripcion"
                contentType="html"
                theme="snow"
                toolbar="full"
                class="task-quill-editor"
                placeholder="Describí brevemente el alcance de la tarea"
              />
            </div>
          </template>

          <p
            v-if="errors.descripcion && !isReadOnly"
            class="mt-1 text-xs font-medium text-red-500"
          >
            {{ errors.descripcion }}
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div>
          <label
            class="mb-1.5 block text-sm font-semibold text-[var(--text-primary)]"
          >
            Estado
          </label>

          <TaskFormEstadoDropdown
            v-model="form.estado"
            :error="!!errors.estado"
            :disabled="isReadOnly"
          />

          <p
            v-if="errors.estado && !isReadOnly"
            class="mt-1 text-xs font-medium text-red-500"
          >
            {{ errors.estado }}
          </p>
        </div>

        <div>
          <label
            class="mb-1.5 block text-sm font-semibold text-[var(--text-primary)]"
          >
            Fecha de vencimiento
          </label>

          <template v-if="isReadOnly">
            <div
              class="inline-flex min-h-[46px] w-full items-center rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)] px-3 py-2.5 text-sm text-[var(--text-primary)]"
            >
              {{ formatDateForView(form.fecha_vencimiento) }}
            </div>
          </template>

          <template v-else>
            <input
              v-model="form.fecha_vencimiento"
              type="date"
              :class="fieldClass(!!errors.fecha_vencimiento)"
            />
          </template>

          <p
            v-if="errors.fecha_vencimiento && !isReadOnly"
            class="mt-1 text-xs font-medium text-red-500"
          >
            {{ errors.fecha_vencimiento }}
          </p>
        </div>
      </div>

      <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
        <div>
          <label
            class="mb-1.5 block text-sm font-semibold text-[var(--text-primary)]"
          >
            Prioridad
          </label>

          <TaskFormPrioridadDropdown
            v-model="form.prioridad_id"
            :prioridades="prioridades"
            :error="!!errors.prioridad_id"
            :disabled="isReadOnly"
          />

          <p
            v-if="errors.prioridad_id && !isReadOnly"
            class="mt-1 text-xs font-medium text-red-500"
          >
            {{ errors.prioridad_id }}
          </p>
        </div>

        <div>
          <label
            class="mb-1.5 block text-sm font-semibold text-[var(--text-primary)]"
          >
            Etiquetas
          </label>

          <TaskFormEtiquetasDropdown
            v-model="form.etiquetas"
            :etiquetas="etiquetas"
            :error="!!errors.etiquetas"
            :disabled="isReadOnly"
          />

          <p
            v-if="errors.etiquetas && !isReadOnly"
            class="mt-1 text-xs font-medium text-red-500"
          >
            {{ errors.etiquetas }}
          </p>
        </div>
      </div>
    </form>

    <template #footer>
      <div class="flex w-full items-center justify-end gap-3">
        <BaseButton
          variant="ghost"
          :disabled="isSaving"
          @click="$emit('close')"
        >
          {{ isReadOnly ? "Cerrar" : "Cancelar" }}
        </BaseButton>

        <BaseButton
          v-if="!isReadOnly"
          :disabled="isSaving"
          @click="submitTarea"
        >
          <BaseSpinner v-if="isSaving" size="sm" />
          {{
            isSaving
              ? "Guardando..."
              : isEdit
                ? "Guardar cambios"
                : "Crear tarea"
          }}
        </BaseButton>
      </div>
    </template>
  </BaseModal>
</template>
