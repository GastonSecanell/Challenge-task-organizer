<script setup>
import { Search, Trash2, Plus, Minus } from 'lucide-vue-next'
import BaseSelectRole from '@/components/ui/BaseSelectRole.vue'

const props = defineProps({
  search: {
    type: String,
    default: '',
  },
  filters: {
    type: Object,
    required: true,
  },
  showFilters: {
    type: Boolean,
    default: false,
  },
  hasActiveFilters: {
    type: Boolean,
    default: false,
  },
  start: {
    type: Number,
    default: 0,
  },
  end: {
    type: Number,
    default: 0,
  },
  total: {
    type: Number,
    default: 0,
  },
  prioridades: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits([
  'update:search',
  'update:filters',
  'update:showFilters',
  'resetFilters',
])

const inputBase =
  'h-9 w-full rounded-md border border-[var(--border-default)] bg-[var(--bg-surface)] px-3 text-sm text-[var(--text-primary)] placeholder-[var(--text-muted)] transition-colors focus:border-[var(--accent)] focus:outline-none focus:ring-1 focus:ring-[var(--accent)]/30'

function updateFilter(key, value) {
  emit('update:filters', {
    ...props.filters,
    [key]: value,
  })
}
</script>

<template>
  <div class="border-b border-[var(--border-default)] px-4 py-6 lg:px-8">
    <div class="grid items-center gap-4 lg:grid-cols-[auto_260px_auto_1fr_auto_auto]">
      <h1 class="whitespace-nowrap text-xl font-bold text-[var(--text-primary)]">
        Tareas
      </h1>

      <div class="relative w-full">
        <input
          :value="search"
          type="text"
          placeholder="Buscar..."
          class="pl-9"
          :class="inputBase"
          @input="$emit('update:search', $event.target.value)"
        >
        <Search
          class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--text-secondary)]"
          aria-hidden="true"
        />
      </div>

      <button
        type="button"
        :title="showFilters ? 'Ocultar filtros' : 'Más filtros'"
        class="flex h-9 w-9 items-center justify-center rounded-md border border-[var(--border-default)] text-[var(--text-secondary)] transition hover:bg-[var(--bg-hover)]"
        @click="$emit('update:showFilters', !showFilters)"
      >
        <Minus v-if="showFilters" class="h-4 w-4" />
        <Plus v-else class="h-4 w-4" />
      </button>

      <div
        class="flex items-center gap-4 overflow-hidden transition-[max-height,opacity] duration-300"
        :class="showFilters ? 'max-h-20 opacity-100' : 'max-h-0 opacity-0'"
      >
        <select
          :value="filters.estado"
          :class="[inputBase, 'w-[180px]']"
          @change="updateFilter('estado', $event.target.value)"
        >
          <option value="">Todos los estados</option>
          <option value="pendiente">Pendiente</option>
          <option value="en_progreso">En progreso</option>
          <option value="completada">Completada</option>
        </select>

        <div class="relative w-[220px]">
          <select
            :value="filters.prioridad_id"
            :class="[inputBase, 'appearance-none pr-8']"
            @change="updateFilter('prioridad_id', $event.target.value)"
          >
            <option value="">Todas las prioridades</option>
            <option
              v-for="prioridad in prioridades"
              :key="prioridad.id"
              :value="prioridad.id"
            >
              {{ prioridad.prioridad }}
            </option>
          </select>
        </div>

        <input
          :value="filters.fecha_vencimiento"
          type="date"
          :class="[inputBase, 'w-[180px]']"
          @input="updateFilter('fecha_vencimiento', $event.target.value)"
        >
      </div>

      <button
        v-if="hasActiveFilters"
        type="button"
        title="Limpiar filtros"
        class="flex h-9 w-9 items-center justify-center rounded-md border border-[var(--border-default)] text-[var(--danger)] transition hover:bg-red-500/10"
        @click="$emit('resetFilters')"
      >
        <Trash2 class="h-4 w-4" />
      </button>
      <div v-else />

      <span class="whitespace-nowrap text-sm text-[var(--text-secondary)]">
        <span class="text-[var(--accent)]">{{ start }}–{{ end }}</span>
        de {{ total }}
      </span>
    </div>
  </div>
</template>