<script setup>
import { Pencil, Trash2 } from "lucide-vue-next";
defineProps({
  user: {
    type: Object,
    required: true,
  },
  index: {
    type: Number,
    default: 0,
  },
});

defineEmits(["edit", "delete"]);

function rowClass(index) {
  return index % 2 === 0
    ? "bg-[var(--bg-row-odd)] hover:bg-[var(--bg-hover)]"
    : "bg-[var(--bg-row-even)] hover:bg-[var(--bg-hover)]";
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
        {{ user.role?.name || "-" }}
      </span>
    </td>

    <td class="px-2 py-3 text-center">
      <div class="flex justify-center gap-2">
        <button
          class="text-[var(--text-secondary)] hover:text-[var(--accent)] transition"
          @click="$emit('edit', user)"
        >
          <Pencil class="w-4 h-4" />
        </button>

        <button
          class="text-[var(--text-secondary)] hover:text-red-500 transition"
          @click="$emit('delete', user)"
        >
          <Trash2 class="w-4 h-4" />
        </button>
      </div>
    </td>
  </tr>
</template>
