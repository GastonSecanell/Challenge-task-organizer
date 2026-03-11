<script setup>
import { computed } from 'vue'
import { ChevronDown, ChevronLeft, ChevronRight } from 'lucide-vue-next'

const props = defineProps({
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
})

const emit = defineEmits(['update:page', 'update:perPage'])

const totalPages = computed(() => Math.max(1, Math.ceil(props.total / props.perPage)))
const start = computed(() => (props.total === 0 ? 0 : (props.page - 1) * props.perPage + 1))
const end = computed(() => Math.min(props.page * props.perPage, props.total))
</script>

<template>
  <div class="mt-4 flex w-full items-center justify-between text-[var(--text-secondary)]">
    <div class="flex items-center gap-2 text-sm lg:text-base ml-2">
      <span>
        <span class="text-[var(--accent)]">{{ start }}–{{ end }}</span>
        de {{ total }}
      </span>
    </div>

    <div class="flex items-center gap-6">
      <div class="flex items-center gap-2">
        <span class="text-sm">Filas por página</span>

        <div class="relative">
          <select
            :value="perPage"
            class="h-8 appearance-none rounded-md border border-[var(--border-default)] bg-[var(--bg-surface)] pl-2 pr-8 text-[var(--text-primary)] focus:border-[var(--accent)] focus:outline-none"
            @change="$emit('update:perPage', Number($event.target.value))"
          >
            <option
              v-for="n in [5, 10, 20, 50]"
              :key="n"
              :value="n"
            >
              {{ n }}
            </option>
          </select>

          <ChevronDown
            class="pointer-events-none absolute right-2 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--chart-organic)]"
          />
        </div>
      </div>

      <div class="ml-4 flex items-center gap-2">
        <button
          type="button"
          :disabled="page === 1"
          class="flex h-7 w-7 items-center justify-center rounded-md border border-[var(--border-default)] bg-[var(--bg-surface)] text-[var(--chart-organic)] transition hover:border-[var(--accent)] hover:text-[var(--accent)] disabled:opacity-40"
          @click="$emit('update:page', page - 1)"
        >
          <ChevronLeft class="h-4 w-4" />
        </button>

        <button
          type="button"
          :disabled="page >= totalPages"
          class="flex h-7 w-7 items-center justify-center rounded-md border border-[var(--border-default)] bg-[var(--bg-surface)] text-[var(--chart-organic)] transition hover:border-[var(--accent)] hover:text-[var(--accent)] disabled:opacity-40"
          @click="$emit('update:page', page + 1)"
        >
          <ChevronRight class="h-4 w-4" />
        </button>
      </div>
    </div>
  </div>
</template>