<script setup>
import { computed } from 'vue'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import BaseSelect from '@/components/ui/BaseSelect.vue'

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

const totalPages = computed(() =>
  Math.max(1, Math.ceil(props.total / props.perPage))
)

const start = computed(() =>
  props.total === 0 ? 0 : (props.page - 1) * props.perPage + 1
)

const end = computed(() =>
  Math.min(props.page * props.perPage, props.total)
)

const perPageOptions = [
  { value: 5, label: '5' },
  { value: 10, label: '10' },
  { value: 20, label: '20' },
  { value: 50, label: '50' },
]
</script>

<template>
  <div class="mt-4 flex w-full items-center justify-between text-[var(--text-secondary)]">
    
    <!-- rango -->
    <div class="flex items-center gap-2 text-sm lg:text-base ml-2">
      <span>
        <span class="text-[var(--accent)]">{{ start }}–{{ end }}</span>
        de {{ total }}
      </span>
    </div>

    <div class="flex items-center gap-6">

      <!-- filas por pagina -->
      <div class="flex items-center gap-2">
        <span class="text-sm">Filas por página</span>

        <div class="w-[70px]">
          <BaseSelect
            :model-value="perPage"
            :options="perPageOptions"
            option-label="label"
            option-value="value"
            placeholder=""
            @update:modelValue="emit('update:perPage', Number($event))"
          />
        </div>
      </div>

      <!-- botones -->
      <div class="ml-4 flex items-center gap-2">

        <button
          type="button"
          :disabled="page === 1"
          class="flex h-7 w-7 items-center justify-center rounded-md border border-[var(--border-default)] bg-[var(--bg-surface)] text-[var(--chart-organic)] transition hover:border-[var(--accent)] hover:text-[var(--accent)] disabled:opacity-40"
          @click="emit('update:page', page - 1)"
        >
          <ChevronLeft class="h-4 w-4" />
        </button>

        <button
          type="button"
          :disabled="page >= totalPages"
          class="flex h-7 w-7 items-center justify-center rounded-md border border-[var(--border-default)] bg-[var(--bg-surface)] text-[var(--chart-organic)] transition hover:border-[var(--accent)] hover:text-[var(--accent)] disabled:opacity-40"
          @click="emit('update:page', page + 1)"
        >
          <ChevronRight class="h-4 w-4" />
        </button>

      </div>
    </div>
  </div>
</template>