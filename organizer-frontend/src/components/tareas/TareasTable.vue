<script setup>
import { computed } from 'vue'
import { Search, ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-vue-next'
import BaseSpinner from '@/components/ui/BaseSpinner.vue'
import TareasTableToolbar from './TareasTableToolbar.vue'
import TareasTableRow from './TareasTableRow.vue'

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  prioridades: {
    type: Array,
    default: () => [],
  },
  etiquetas: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  page: {
    type: Number,
    required: true,
  },
  perPage: {
    type: Number,
    required: true,
  },
  total: {
    type: Number,
    required: true,
  },
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
  canEdit: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'update:search',
  'update:filters',
  'update:showFilters',
  'resetFilters',
  'sort',
  'edit',
  'delete',
  'change-status',
  'change-priority',
  'labels-updated',
])

const hasActiveFilters = computed(() => {
  return (
    Boolean(props.search) ||
    Boolean(props.filters?.estado) ||
    Boolean(props.filters?.prioridad_id) ||
    Boolean(props.filters?.etiqueta_id) ||
    Boolean(props.filters?.fecha_vencimiento)
  )
})

const effectiveTotal = computed(() =>
  props.total > 0 ? props.total : props.items.length,
)

const start = computed(() =>
  props.items.length === 0 ? 0 : (props.page - 1) * props.perPage + 1,
)

const end = computed(() =>
  Math.min(start.value + props.items.length - 1, effectiveTotal.value),
)

function iconFor(column) {
  if (props.filters?.ordenar_por !== column) return ArrowUpDown
  return props.filters?.direccion === 'asc' ? ArrowUp : ArrowDown
}
</script>

<template>
  <div
    class="mt-6 overflow-hidden rounded-xl border border-[var(--border-default)] bg-[var(--bg-surface)]"
  >
    <TareasTableToolbar
      :search="search"
      :filters="filters"
      :show-filters="showFilters"
      :has-active-filters="hasActiveFilters"
      :start="start"
      :end="end"
      :total="effectiveTotal"
      :prioridades="prioridades"
      :etiquetas="etiquetas"
      @update:search="$emit('update:search', $event)"
      @update:filters="$emit('update:filters', $event)"
      @update:showFilters="$emit('update:showFilters', $event)"
      @resetFilters="$emit('resetFilters')"
    />

    <div class="relative min-h-[260px]">
      <div class="dark-scroll max-h-[620px] overflow-auto">
        <table class="w-full min-w-[1280px] border-collapse text-sm">
          <thead class="sticky top-0 z-20 bg-[var(--bg-surface)] text-xs">
            <tr class="border-t border-[var(--border-default)]">
              <th class="w-[50%] px-4 py-3 text-left font-medium text-[var(--text-secondary)]">
                <button
                  type="button"
                  class="inline-flex items-center gap-1 transition hover:text-[var(--accent)]"
                  @click="$emit('sort', 'titulo')"
                >
                  <span>Título</span>
                  <component :is="iconFor('titulo')" class="h-3.5 w-3.5" />
                </button>
              </th>

              <th class="px-4 py-3 text-left font-medium text-[var(--text-secondary)]">
                Prioridad
              </th>

              <th class="px-4 py-3 text-left font-medium text-[var(--text-secondary)]">
                Etiquetas
              </th>

              <th class="px-4 py-3 text-left font-medium text-[var(--text-secondary)]">
                <button
                  type="button"
                  class="inline-flex items-center gap-1 transition hover:text-[var(--accent)]"
                  @click="$emit('sort', 'estado')"
                >
                  <span>Estado</span>
                  <component :is="iconFor('estado')" class="h-3.5 w-3.5" />
                </button>
              </th>

              <th class="px-4 py-3 text-left font-medium text-[var(--text-secondary)]">
                <button
                  type="button"
                  class="inline-flex items-center gap-1 transition hover:text-[var(--accent)]"
                  @click="$emit('sort', 'fecha_vencimiento')"
                >
                  <span>Vencimiento</span>
                  <component
                    :is="iconFor('fecha_vencimiento')"
                    class="h-3.5 w-3.5"
                  />
                </button>
              </th>

              <th class="px-4 py-3 text-center font-medium text-[var(--text-secondary)]">
                Opciones
              </th>
            </tr>
          </thead>

          <tbody :class="loading ? 'pointer-events-none opacity-40' : ''">
            <tr v-if="!loading && items.length === 0">
              <td
                colspan="6"
                class="px-6 py-16 text-center text-sm text-[var(--text-secondary)]"
              >
                <div class="flex flex-col items-center gap-3">
                  <Search class="h-6 w-6 text-[var(--text-muted)]" />
                  <span class="font-medium text-[var(--text-primary)]">
                    No se encontraron tareas
                  </span>
                  <span v-if="search" class="text-xs text-[var(--text-muted)]">
                    Probá con otro término de búsqueda
                  </span>
                </div>
              </td>
            </tr>

            <TareasTableRow
              v-for="(item, index) in items"
              :key="item.id"
              :item="item"
              :index="index"
              :prioridades="prioridades"
              :etiquetas="etiquetas"
              :can-edit="canEdit"
              @edit="$emit('edit', $event)"
              @delete="$emit('delete', $event)"
              @change-status="$emit('change-status', $event)"
              @change-priority="$emit('change-priority', $event)"
              @labels-updated="$emit('labels-updated')"
            />
          </tbody>
        </table>
      </div>

      <transition name="fade">
        <div
          v-if="loading"
          class="absolute inset-0 z-30 flex flex-col items-center justify-center gap-3 bg-[color:rgba(15,23,42,0.35)] backdrop-blur-[2px]"
        >
          <BaseSpinner size="lg" label="Cargando tareas..." />
        </div>
      </transition>
    </div>
  </div>
</template>