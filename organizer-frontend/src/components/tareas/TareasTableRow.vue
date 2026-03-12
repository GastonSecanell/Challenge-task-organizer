<script setup>
import { Eye, Pencil, Trash2 } from 'lucide-vue-next'
import { getEtiquetaStyle } from '@/lib/taskEtiquetas'
import TaskEtiquetasDropdown from './TaskEtiquetasDropdown.vue'
import TaskEstadoDropdown from './TaskEstadoDropdown.vue'
import TaskPrioridadDropdown from './TaskPrioridadDropdown.vue'
import { getDueDateClass, getDueDateLabel } from '@/lib/taskDueDate'

const props = defineProps({
  item: {
    type: Object,
    required: true,
  },
  index: {
    type: Number,
    default: 0,
  },
  prioridades: {
    type: Array,
    default: () => [],
  },
  etiquetas: {
    type: Array,
    default: () => [],
  },
  canEdit: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'view',
  'edit',
  'delete',
  'change-status',
  'change-priority',
  'labels-updated',
])

function rowClass(index) {
  return index % 2 === 0
    ? 'bg-[var(--bg-row-odd)] hover:bg-[var(--bg-hover)]'
    : 'bg-[var(--bg-row-even)] hover:bg-[var(--bg-hover)]'
}

function handleChangeStatus(estado) {
  if (!props.canEdit) return

  emit('change-status', {
    id: props.item.id,
    estado,
  })
}

function handleChangePriority(prioridadId) {
  if (!props.canEdit) return

  emit('change-priority', {
    id: props.item.id,
    prioridad_id: prioridadId,
  })
}

function prioridadLabel(prioridad) {
  if (!prioridad) return 'Sin prioridad'
  if (typeof prioridad === 'string') return prioridad
  return prioridad.prioridad || 'Sin prioridad'
}

function estadoLabel(estado) {
  if (estado === 'pendiente') return 'Pendiente'
  if (estado === 'en_progreso') return 'En progreso'
  if (estado === 'completada') return 'Completada'
  return estado || '-'
}

function estadoBadgeClass(estado) {
  if (estado === 'pendiente') {
    return 'border-[var(--task-status-pending-border)] bg-[var(--task-status-pending-bg)] text-[var(--task-status-pending-text)]'
  }

  if (estado === 'en_progreso') {
    return 'border-[var(--task-status-progress-border)] bg-[var(--task-status-progress-bg)] text-[var(--task-status-progress-text)]'
  }

  if (estado === 'completada') {
    return 'border-[var(--task-status-done-border)] bg-[var(--task-status-done-bg)] text-[var(--task-status-done-text)]'
  }

  return 'border-[var(--border-default)] bg-[var(--bg-hover)] text-[var(--text-secondary)]'
}
</script>

<template>
  <tr
    class="animate-row-enter border-t border-[var(--border-default)] transition-colors"
    :class="rowClass(index)"
  >
    <td class="w-[40%] px-4 py-3">
      <div class="font-medium text-[var(--text-primary)]">
        {{ item.titulo }}
      </div>
      <div
        class="prose prose-sm mt-1 line-clamp-1 max-w-none text-xs text-[var(--text-secondary)]"
        v-html="item.descripcion || 'Sin descripción'"
      ></div>
    </td>

    <td class="px-4 py-3 align-middle">
      <TaskPrioridadDropdown
        v-if="canEdit"
        :value="item.prioridad"
        :prioridades="prioridades"
        @change="handleChangePriority"
      />

      <span
        v-else
        class="inline-flex rounded-full border border-[var(--border-default)] bg-[var(--bg-hover)] px-2.5 py-1 text-xs font-medium text-[var(--text-primary)]"
      >
        {{ prioridadLabel(item.prioridad) }}
      </span>
    </td>

    <td class="px-4 py-3">
      <div class="flex items-start gap-2">
        <div class="flex min-w-[120px] flex-wrap gap-1.5">
          <span
            v-for="etiqueta in item.etiquetas"
            :key="etiqueta.id"
            class="inline-flex items-center rounded-full border px-1 py-1 text-xs font-medium"
            :class="getEtiquetaStyle(etiqueta.etiqueta).badge"
          >
            {{ etiqueta.etiqueta }}
          </span>

          <span
            v-if="!item.etiquetas?.length"
            class="text-xs text-[var(--text-muted)]"
          >
            Sin etiquetas
          </span>
        </div>

        <TaskEtiquetasDropdown
          v-if="canEdit"
          :tarea-id="item.id"
          :selected-etiquetas="item.etiquetas"
          :etiquetas-disponibles="etiquetas"
          @updated="$emit('labels-updated')"
        />
      </div>
    </td>

    <td class="px-4 py-3 align-middle">
      <TaskEstadoDropdown
        v-if="canEdit"
        :value="item.estado"
        @change="handleChangeStatus"
      />

      <span
        v-else
        class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold"
        :class="estadoBadgeClass(item.estado)"
      >
        {{ estadoLabel(item.estado) }}
      </span>
    </td>

    <td class="px-4 py-3">
      <span
        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
        :class="getDueDateClass(item.fecha_vencimiento)"
      >
        {{ getDueDateLabel(item.fecha_vencimiento) }}
      </span>
    </td>

    <td class="px-8 py-3 text-center">
      <div class="flex justify-center gap-2">
        <button
          class="text-[var(--text-secondary)] transition hover:text-[var(--accent)]"
          title="Ver tarea"
          @click="$emit('view', item.id)"
        >
          <Eye class="h-4 w-4" />
        </button>

        <template v-if="canEdit">
          <button
            class="text-[var(--text-secondary)] transition hover:text-[var(--accent)]"
            title="Editar tarea"
            @click="$emit('edit', item.id)"
          >
            <Pencil class="h-4 w-4" />
          </button>

          <button
            class="text-[var(--text-secondary)] transition hover:text-red-500"
            title="Eliminar tarea"
            @click="$emit('delete', item)"
          >
            <Trash2 class="h-4 w-4" />
          </button>
        </template>
      </div>
    </td>
  </tr>
</template>