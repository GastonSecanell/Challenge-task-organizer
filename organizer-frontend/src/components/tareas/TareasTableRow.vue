<script setup>
import { Pencil, Trash2 } from 'lucide-vue-next'

defineProps({
  item: {
    type: Object,
    required: true,
  },
  index: {
    type: Number,
    default: 0,
  },
})

defineEmits(['edit', 'delete'])

function rowClass(index) {
  return index % 2 === 0
    ? 'bg-[var(--bg-row-odd)] hover:bg-[var(--bg-hover)]'
    : 'bg-[var(--bg-row-even)] hover:bg-[var(--bg-hover)]'
}

function estadoLabel(estado) {
  if (estado === 'en_progreso') return 'En progreso'
  if (estado === 'completada') return 'Completada'
  return 'Pendiente'
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
      {{ item.prioridad?.prioridad || '-' }}
    </td>

    <td class="px-4 py-3 text-sm text-[var(--text-secondary)]">
      <span class="inline-flex items-center rounded-full border border-[var(--border-default)] px-2.5 py-1 text-xs text-[var(--text-primary)]">
        {{ estadoLabel(item.estado) }}
      </span>
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