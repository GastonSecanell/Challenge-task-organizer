<script setup>
import { Pencil, Trash2 } from 'lucide-vue-next'
import { getEtiquetaStyle } from '@/lib/taskEtiquetas'
import TaskEtiquetasDropdown from './TaskEtiquetasDropdown.vue'
import TaskEstadoDropdown from './TaskEstadoDropdown.vue'
import TaskPrioridadDropdown from './TaskPrioridadDropdown.vue'

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
})

const emit = defineEmits([
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
  emit('change-status', {
    id: props.item.id,
    estado,
  })
}

function handleChangePriority(prioridadId) {
  emit('change-priority', {
    id: props.item.id,
    prioridad_id: prioridadId,
  })
}
</script>

<template>
  <tr
    class="animate-row-enter border-t border-[var(--border-default)] transition-colors"
    :class="rowClass(index)"
  >
    <td class="px-4 py-3">
      <div class="font-medium text-[var(--text-primary)]">
        {{ item.titulo }}
      </div>
      <div class="mt-1 line-clamp-1 text-xs text-[var(--text-secondary)]">
        {{ item.descripcion || 'Sin descripción' }}
      </div>
    </td>

    <td class="px-4 py-3 text-sm text-[var(--text-secondary)]">
      <TaskPrioridadDropdown
        :value="item.prioridad"
        :prioridades="prioridades"
        @change="handleChangePriority"
      />
    </td>

    <td class="px-4 py-3">
      <div class="flex items-start gap-2">
        <div class="flex flex-wrap gap-1.5 min-w-[120px]">
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
          :tarea-id="item.id"
          :selected-etiquetas="item.etiquetas"
          @updated="$emit('labels-updated')"
        />
      </div>
    </td>

    <td class="px-4 py-3 text-sm text-[var(--text-secondary)] whitespace-nowrap">
      <TaskEstadoDropdown
        :value="item.estado"
        @change="handleChangeStatus"
      />
    </td>

    <td class="px-4 py-3 text-sm text-[var(--text-secondary)]">
      {{ item.fecha_vencimiento || '-' }}
    </td>

    <td class="px-2 py-3 text-center">
      <div class="flex justify-center gap-2">
        <button
          class="text-[var(--text-secondary)] transition hover:text-[var(--accent)]"
          @click="$emit('edit', item.id)"
        >
          <Pencil class="h-4 w-4" />
        </button>

        <button
          class="text-[var(--text-secondary)] transition hover:text-red-500"
          @click="$emit('delete', item)"
        >
          <Trash2 class="h-4 w-4" />
        </button>
      </div>
    </td>
  </tr>
</template>