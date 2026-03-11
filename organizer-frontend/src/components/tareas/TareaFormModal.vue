<script setup>
import { reactive, ref, watch, computed } from 'vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseSpinner from '@/components/ui/BaseSpinner.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useToastStore } from '@/stores/toasts'
import { TareasApi } from '@/lib/api/tareas'
import { PrioridadesApi } from '@/lib/api/prioridades'
import { EtiquetasApi } from '@/lib/api/etiquetas'
import { getEtiquetaStyle } from '@/lib/taskEtiquetas'

const props = defineProps({
  open: Boolean,
  tareaId: [Number, String, null],
})

const emit = defineEmits(['close', 'saved'])

const toasts = useToastStore()

const isLoading = ref(false)
const isSaving = ref(false)

const prioridades = ref([])
const etiquetasDisponibles = ref([])

const form = reactive({
  titulo: '',
  descripcion: '',
  estado: 'pendiente',
  fecha_vencimiento: '',
  prioridad_id: '',
  etiquetas: [],
})

const errors = reactive({
  titulo: '',
  descripcion: '',
  estado: '',
  fecha_vencimiento: '',
  prioridad_id: '',
  etiquetas: '',
})

const isEdit = computed(() => Boolean(props.tareaId))

function resetErrors() {
  errors.titulo = ''
  errors.descripcion = ''
  errors.estado = ''
  errors.fecha_vencimiento = ''
  errors.prioridad_id = ''
  errors.etiquetas = ''
}

function resetForm() {
  form.titulo = ''
  form.descripcion = ''
  form.estado = 'pendiente'
  form.fecha_vencimiento = ''
  form.prioridad_id = ''
  form.etiquetas = []
  resetErrors()
}

function validate() {
  resetErrors()

  let valid = true

  if (!form.titulo.trim()) {
    errors.titulo = 'El título es obligatorio.'
    valid = false
  }

  if (!form.descripcion.trim()) {
    errors.descripcion = 'La descripción es obligatoria.'
    valid = false
  }

  if (!form.estado) {
    errors.estado = 'El estado es obligatorio.'
    valid = false
  }

  if (!form.prioridad_id) {
    errors.prioridad_id = 'La prioridad es obligatoria.'
    valid = false
  }

  return valid
}

async function loadCatalogos() {
  const [resPrioridades, resEtiquetas] = await Promise.all([
    PrioridadesApi.list(),
    EtiquetasApi.list(),
  ])

  prioridades.value = resPrioridades?.data ?? []
  etiquetasDisponibles.value = resEtiquetas?.data ?? []
}

async function loadTarea(id) {
  const res = await TareasApi.get(id)
  const tarea = res?.data

  form.titulo = tarea?.titulo ?? ''
  form.descripcion = tarea?.descripcion ?? ''
  form.estado = tarea?.estado ?? 'pendiente'
  form.fecha_vencimiento = tarea?.fecha_vencimiento ?? ''
  form.prioridad_id = tarea?.prioridad_id ?? ''
  form.etiquetas = Array.isArray(tarea?.etiquetas)
    ? tarea.etiquetas.map(et => et.id)
    : []
}

async function initModal() {
  if (!props.open) return

  isLoading.value = true

  try {
    resetForm()
    await loadCatalogos()

    if (props.tareaId) {
      await loadTarea(props.tareaId)
    }
  } catch (error) {
    toasts.error('No se pudieron cargar los datos del formulario.')
    emit('close')
  } finally {
    isLoading.value = false
  }
}

async function submit() {
  if (!validate()) return

  isSaving.value = true

  try {
    const payload = {
      titulo: form.titulo.trim(),
      descripcion: form.descripcion.trim(),
      estado: form.estado,
      fecha_vencimiento: form.fecha_vencimiento || null,
      prioridad_id: Number(form.prioridad_id),
      etiquetas: form.etiquetas.map(Number),
    }

    if (isEdit.value) {
      await TareasApi.update(props.tareaId, payload)
      toasts.success('Tarea actualizada.')
    } else {
      await TareasApi.create(payload)
      toasts.success('Tarea creada.')
    }

    emit('saved')
  } catch (error) {
    toasts.error(error?.message || 'Error al guardar la tarea.')
  } finally {
    isSaving.value = false
  }
}

watch(
  () => [props.open, props.tareaId],
  () => {
    if (props.open) {
      initModal()
    }
  },
  { immediate: false }
)
</script>

<template>
  <BaseModal
    :open="open"
    :title="isEdit ? 'Editar tarea' : 'Nueva tarea'"
    width-class="max-w-2xl"
    @close="$emit('close')"
  >
    <div v-if="isLoading" class="flex justify-center py-10">
      <BaseSpinner size="lg" />
    </div>

    <form v-else class="space-y-4" @submit.prevent="submit">
      <div>
        <label class="mb-1 block text-sm font-medium text-[var(--text-primary)]">
          Título
        </label>

        <input
          v-model="form.titulo"
          class="w-full rounded-md border border-[var(--border-default)] bg-[var(--bg-surface)] px-3 py-2 text-sm text-[var(--text-primary)]"
        >

        <p v-if="errors.titulo" class="mt-1 text-xs text-red-500">
          {{ errors.titulo }}
        </p>
      </div>

      <div>
        <label class="mb-1 block text-sm font-medium text-[var(--text-primary)]">
          Descripción
        </label>

        <textarea
          v-model="form.descripcion"
          rows="4"
          class="w-full rounded-md border border-[var(--border-default)] bg-[var(--bg-surface)] px-3 py-2 text-sm text-[var(--text-primary)]"
        />

        <p v-if="errors.descripcion" class="mt-1 text-xs text-red-500">
          {{ errors.descripcion }}
        </p>
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <label class="mb-1 block text-sm font-medium text-[var(--text-primary)]">
            Estado
          </label>

          <select
            v-model="form.estado"
            class="w-full rounded-md border border-[var(--border-default)] bg-[var(--bg-surface)] px-3 py-2 text-sm text-[var(--text-primary)]"
          >
            <option value="pendiente">Pendiente</option>
            <option value="en_progreso">En progreso</option>
            <option value="completada">Completada</option>
          </select>

          <p v-if="errors.estado" class="mt-1 text-xs text-red-500">
            {{ errors.estado }}
          </p>
        </div>

        <div>
          <label class="mb-1 block text-sm font-medium text-[var(--text-primary)]">
            Fecha de vencimiento
          </label>

          <input
            v-model="form.fecha_vencimiento"
            type="date"
            class="w-full rounded-md border border-[var(--border-default)] bg-[var(--bg-surface)] px-3 py-2 text-sm text-[var(--text-primary)]"
          >

          <p v-if="errors.fecha_vencimiento" class="mt-1 text-xs text-red-500">
            {{ errors.fecha_vencimiento }}
          </p>
        </div>
      </div>

      <div>
        <label class="mb-1 block text-sm font-medium text-[var(--text-primary)]">
          Prioridad
        </label>

        <select
          v-model="form.prioridad_id"
          class="w-full rounded-md border border-[var(--border-default)] bg-[var(--bg-surface)] px-3 py-2 text-sm text-[var(--text-primary)]"
        >
          <option value="">Seleccionar prioridad</option>
          <option
            v-for="prioridad in prioridades"
            :key="prioridad.id"
            :value="prioridad.id"
          >
            {{ prioridad.prioridad }}
          </option>
        </select>

        <p v-if="errors.prioridad_id" class="mt-1 text-xs text-red-500">
          {{ errors.prioridad_id }}
        </p>
      </div>

      <div>
        <label class="mb-2 block text-sm font-medium text-[var(--text-primary)]">
          Etiquetas
        </label>

        <div class="grid grid-cols-2 gap-2 md:grid-cols-3">
          <label
            v-for="etiqueta in etiquetasDisponibles"
            :key="etiqueta.id"
            class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm font-medium transition hover:opacity-90"
            :class="getEtiquetaStyle(etiqueta.etiqueta).chip"
          >
            <input
              v-model="form.etiquetas"
              type="checkbox"
              :value="etiqueta.id"
              :class="getEtiquetaStyle(etiqueta.etiqueta).checkbox"
            >
            <span>{{ etiqueta.etiqueta }}</span>
          </label>
        </div>

        <p v-if="errors.etiquetas" class="mt-1 text-xs text-red-500">
          {{ errors.etiquetas }}
        </p>
      </div>
    </form>

    <template #footer>
      <BaseButton variant="ghost" @click="$emit('close')">
        Cancelar
      </BaseButton>

      <BaseButton :disabled="isSaving" @click="submit">
        <BaseSpinner v-if="isSaving" size="sm" />
        {{ isSaving ? 'Guardando...' : 'Guardar' }}
      </BaseButton>
    </template>
  </BaseModal>
</template>