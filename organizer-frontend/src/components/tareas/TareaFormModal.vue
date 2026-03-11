<script setup>
import { reactive, ref, watch, computed } from 'vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseSpinner from '@/components/ui/BaseSpinner.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useToastStore } from '@/stores/toasts'
import { TareasApi } from '@/lib/api/tareas'

const props = defineProps({
  open: Boolean,
  tareaId: [Number, String, null]
})

const emit = defineEmits(['close','saved'])

const toasts = useToastStore()

const isLoading = ref(false)
const isSaving = ref(false)

const form = reactive({
  titulo: '',
  descripcion: '',
  estado: 'pendiente'
})

const errors = reactive({
  titulo: ''
})

const isEdit = computed(() => Boolean(props.tareaId))

function resetForm() {
  form.titulo = ''
  form.descripcion = ''
  form.estado = 'pendiente'
}

function validate() {

  errors.titulo = ''

  if (!form.titulo.trim()) {
    errors.titulo = 'El título es obligatorio'
    return false
  }

  return true
}

async function loadTarea(id) {

  const res = await TareasApi.get(id)

  const tarea = res?.data

  form.titulo = tarea.titulo
  form.descripcion = tarea.descripcion
  form.estado = tarea.estado
}

async function initModal() {

  if (!props.open) return

  isLoading.value = true

  try {

    resetForm()

    if (props.tareaId) {
      await loadTarea(props.tareaId)
    }

  } catch (error) {

    toasts.error('No se pudo cargar la tarea')
    emit('close')

  } finally {

    isLoading.value = false

  }
}

async function submit() {

  if (!validate()) return

  isSaving.value = true

  try {

    if (isEdit.value) {

      await TareasApi.update(props.tareaId, form)
      toasts.success('Tarea actualizada')

    } else {

      await TareasApi.create(form)
      toasts.success('Tarea creada')

    }

    emit('saved')

  } catch (error) {

    toasts.error(error?.message || 'Error al guardar')

  } finally {

    isSaving.value = false

  }
}

watch(
  () => [props.open, props.tareaId],
  () => {
    if (props.open) {
      initModal()
    }
  }
)
</script>

<template>

<BaseModal
  :open="open"
  :title="isEdit ? 'Editar tarea' : 'Nueva tarea'"
  width-class="max-w-xl"
  @close="$emit('close')"
>

<div v-if="isLoading" class="flex justify-center py-10">
  <BaseSpinner size="lg"/>
</div>

<form v-else class="space-y-4" @submit.prevent="submit">

  <div>
    <label class="text-sm">Título</label>

    <input
      v-model="form.titulo"
      class="w-full border rounded px-3 py-2"
    />

    <p v-if="errors.titulo" class="text-red-500 text-xs">
      {{ errors.titulo }}
    </p>
  </div>

  <div>
    <label class="text-sm">Descripción</label>

    <textarea
      v-model="form.descripcion"
      class="w-full border rounded px-3 py-2"
    />
  </div>

  <div>
    <label class="text-sm">Estado</label>

    <select
      v-model="form.estado"
      class="w-full border rounded px-3 py-2"
    >
      <option value="pendiente">Pendiente</option>
      <option value="en_progreso">En progreso</option>
      <option value="completada">Completada</option>
    </select>
  </div>

</form>

<template #footer>

  <BaseButton variant="ghost" @click="$emit('close')">
    Cancelar
  </BaseButton>

  <BaseButton :disabled="isSaving" @click="submit">

    <BaseSpinner v-if="isSaving" size="sm" />

    {{ isSaving ? 'Guardando...' : 'Guardar' }}

  </BaseButton>

</template>

</BaseModal>

</template>