<script setup>
import { Pencil, Trash2 } from 'lucide-vue-next'

const props = defineProps({
  user: {
    type: Object,
    required: true,
  },
  index: {
    type: Number,
    default: 0,
  },
  canManageUsers: {
    type: Boolean,
    default: false,
  },
})

defineEmits(['edit', 'delete'])

function rowClass(index) {
  return index % 2 === 0
    ? 'bg-[var(--bg-row-odd)] hover:bg-[var(--bg-hover)]'
    : 'bg-[var(--bg-row-even)] hover:bg-[var(--bg-hover)]'
}
</script>

<template>
  <tr
    class="animate-row-enter border-t border-[var(--border-default)] transition-colors"
    :class="rowClass(index)"
  >
    <td class="px-8 py-3">
      <div>
        <div class="font-medium text-[var(--text-primary)]">
          {{ user.name }}
        </div>
        <div class="text-xs text-[var(--text-secondary)]">
          {{ user.email }}
        </div>
      </div>
    </td>

    <td class="px-4 py-3 text-sm text-[var(--text-secondary)]">
      {{ user.email }}
    </td>

    <td class="px-4 py-3 text-sm text-[var(--text-secondary)]">
      <span
        class="inline-flex items-center rounded-full border border-[var(--border-default)] px-2.5 py-1 text-xs text-[var(--text-primary)]"
      >
        {{ user.role?.name || '-' }}
      </span>
    </td>

    <td class="px-2 py-3 text-center">
      <div v-if="canManageUsers" class="flex justify-center gap-2">
        <button
          class="text-[var(--text-secondary)] transition hover:text-[var(--accent)]"
          title="Editar usuario"
          @click="$emit('edit', user)"
        >
          <Pencil class="h-4 w-4" />
        </button>

        <button
          class="text-[var(--text-secondary)] transition hover:text-red-500"
          title="Eliminar usuario"
          @click="$emit('delete', user)"
        >
          <Trash2 class="h-4 w-4" />
        </button>
      </div>

      <span v-else class="text-xs text-[var(--text-muted)]">
        Solo lectura
      </span>
    </td>
  </tr>
</template>