<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useTareasStore } from '@/stores/tareas'

const router = useRouter()
const tareasStore = useTareasStore()

onMounted(() => {
  tareasStore.fetchTareas()
})

function edit(id) {
  router.push(`/tareas/${id}/editar`)
}

function remove(id) {
  if (confirm('¿Eliminar tarea?')) {
    tareasStore.deleteTarea(id)
  }
}
</script>

<template>

  <div class="space-y-6">

    <div class="flex justify-between items-center">
      <h2 class="text-xl font-semibold">Tareas</h2>

      <button
        class="px-4 py-2 rounded bg-[var(--accent)] text-white"
        @click="$router.push('/tareas/nueva')"
      >
        Nueva tarea
      </button>
    </div>

    <div
      v-for="tarea in tareasStore.tareas"
      :key="tarea.id"
      class="p-4 rounded border border-[var(--border-default)] bg-[var(--bg-surface)]"
    >

      <div class="flex justify-between">

        <div>
          <h3 class="font-medium">
            {{ tarea.titulo }}
          </h3>

          <p class="text-sm text-[var(--text-secondary)]">
            {{ tarea.descripcion }}
          </p>
        </div>

        <div class="flex gap-2">

          <button
            class="text-sm text-blue-400"
            @click="edit(tarea.id)"
          >
            Editar
          </button>

          <button
            class="text-sm text-red-400"
            @click="remove(tarea.id)"
          >
            Eliminar
          </button>

        </div>

      </div>

    </div>

  </div>

</template>