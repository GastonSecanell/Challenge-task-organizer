<script setup>
import { computed } from 'vue'
import { Search, ArrowUpDown, ArrowUp, ArrowDown } from 'lucide-vue-next'
import BaseSpinner from '@/components/ui/BaseSpinner.vue'
import UsersTableToolbar from './UsersTableToolbar.vue'
import UsersTableRow from './UsersTableRow.vue'

const props = defineProps({
  users: {
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
  roles: {
    type: Array,
    default: () => [],
  },
  canManageUsers: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits([
  'update:search',
  'update:filters',
  'update:showFilters',
  'resetFilters',
  'edit',
  'delete',
  'sort',
])

const hasActiveFilters = computed(() => {
  return (
    Boolean(props.search) ||
    Boolean(props.filters?.name) ||
    Boolean(props.filters?.email) ||
    Boolean(props.filters?.role_id)
  )
})

const effectiveTotal = computed(() =>
  props.total > 0 ? props.total : props.users.length,
)

const start = computed(() =>
  props.users.length === 0 ? 0 : (props.page - 1) * props.perPage + 1,
)

const end = computed(() =>
  Math.min(start.value + props.users.length - 1, effectiveTotal.value),
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
    <UsersTableToolbar
      :search="search"
      :filters="filters"
      :show-filters="showFilters"
      :has-active-filters="hasActiveFilters"
      :start="start"
      :end="end"
      :total="effectiveTotal"
      :roles="roles"
      @update:search="$emit('update:search', $event)"
      @update:filters="$emit('update:filters', $event)"
      @update:showFilters="$emit('update:showFilters', $event)"
      @resetFilters="$emit('resetFilters')"
    />

    <div class="relative min-h-[260px]">
      <div class="dark-scroll max-h-[520px] overflow-auto">
        <table class="w-full min-w-[760px] border-collapse text-sm">
          <thead class="sticky top-0 z-20 bg-[var(--bg-surface)] text-xs">
            <tr class="border-t border-[var(--border-default)]">
              <th
                class="px-8 py-3 text-left font-medium text-[var(--text-secondary)]"
              >
                <button
                  type="button"
                  class="inline-flex items-center gap-1 transition hover:text-[var(--accent)]"
                  @click="$emit('sort', 'name')"
                >
                  <span>Nombre</span>
                  <component :is="iconFor('name')" class="h-3.5 w-3.5" />
                </button>
              </th>

              <th
                class="px-4 py-3 text-left font-medium text-[var(--text-secondary)]"
              >
                <button
                  type="button"
                  class="inline-flex items-center gap-1 transition hover:text-[var(--accent)]"
                  @click="$emit('sort', 'email')"
                >
                  <span>Email</span>
                  <component :is="iconFor('email')" class="h-3.5 w-3.5" />
                </button>
              </th>

              <th
                class="px-4 py-3 text-left font-medium text-[var(--text-secondary)]"
              >
                Rol
              </th>

              <th
                class="px-4 py-3 text-center font-medium text-[var(--text-secondary)]"
              >
                Opciones
              </th>
            </tr>
          </thead>

          <tbody :class="loading ? 'pointer-events-none opacity-40' : ''">
            <tr v-if="!loading && users.length === 0">
              <td
                colspan="4"
                class="px-6 py-16 text-center text-sm text-[var(--text-secondary)]"
              >
                <div class="flex flex-col items-center gap-3">
                  <Search
                    class="h-6 w-6 text-[var(--text-muted)]"
                    aria-hidden="true"
                  />
                  <span class="font-medium text-[var(--text-primary)]">
                    No se encontraron registros
                  </span>
                  <span v-if="search" class="text-xs text-[var(--text-muted)]">
                    Probá con otro término de búsqueda
                  </span>
                </div>
              </td>
            </tr>

            <UsersTableRow
              v-for="(user, index) in users"
              :key="user.id"
              :user="user"
              :index="index"
              :can-manage-users="canManageUsers"
              @edit="$emit('edit', $event)"
              @delete="$emit('delete', $event)"
            />
          </tbody>
        </table>
      </div>

      <transition name="fade">
        <div
          v-if="loading"
          class="absolute inset-0 z-30 flex flex-col items-center justify-center gap-3 bg-[color:rgba(15,23,42,0.35)] backdrop-blur-[2px]"
        >
          <BaseSpinner size="lg" label="Cargando usuarios..." />
        </div>
      </transition>
    </div>
  </div>
</template>