<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toasts'
import { LabelsApi } from '@/lib/api/labels'

const props = defineProps({
  boardId: { type: Number, required: true },
  isOpen: { type: Boolean, required: true },
  isArchived: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'changed'])

const auth = useAuthStore()
const toasts = useToastStore()

const isLoading = ref(false)
const error = ref(null)
const labels = ref([])

const canManage = computed(() => auth.isAdmin && !props.isArchived)

const newLabel = reactive({
  name: '',
  color: '#60a5fa',
  isSaving: false,
})

const savingById = ref({})

const COLORS = [
  '#60a5fa',
  '#34d399',
  '#fbbf24',
  '#fb7185',
  '#a78bfa',
  '#f97316',
  '#22c55e',
  '#ef4444',
  '#94a3b8',
]

function sortByPosition(arr) {
  return (arr || []).slice().sort((a, b) => (a?.position ?? 0) - (b?.position ?? 0))
}

// ✅ tolera backends que respondan {data: []} o [] (sin meternos todavía con la normalización global)
function unwrapList(body) {
  if (Array.isArray(body?.data)) return body.data
  if (Array.isArray(body)) return body
  return []
}
function unwrapItem(body) {
  return body?.data ?? body ?? null
}

// Evita que una respuesta vieja pise el estado (cerrar/abrir modal rápido)
let loadReq = 0

async function load() {
  if (!props.isOpen || !props.boardId) return

  const reqId = ++loadReq
  isLoading.value = true
  error.value = null

  try {
    const body = await LabelsApi.listByBoard(props.boardId)
    if (reqId !== loadReq) return

    labels.value = sortByPosition(unwrapList(body))
  } catch (e) {
    if (reqId !== loadReq) return
    error.value = e?.message ?? String(e)
  } finally {
    if (reqId !== loadReq) return
    isLoading.value = false
  }
}

function pickColor(cur, dir = 1) {
  const idx = Math.max(0, COLORS.indexOf(cur))
  const next = (idx + dir + COLORS.length) % COLORS.length
  return COLORS[next]
}

async function create() {
  if (!canManage.value) return

  const boardId = Number(props.boardId)
  const name = newLabel.name.trim()
  if (!boardId || !name) return

  newLabel.isSaving = true
  try {
    const body = await LabelsApi.create(boardId, { name, color: newLabel.color })
    const created = unwrapItem(body)

    if (created) labels.value = sortByPosition([...labels.value, created])

    newLabel.name = ''
    toasts.push({ type: 'success', message: 'Etiqueta creada.', timeoutMs: 1600 })
    emit('changed')
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo crear.' })
  } finally {
    newLabel.isSaving = false
  }
}

async function save(label) {
  if (!canManage.value) return

  const id = Number(label?.id)
  if (!id) return

  const name = String(label?.name ?? '').trim()
  const color = String(label?.color ?? '').trim()
  if (!name || !color) return

  // si ya está guardando, no re-dispares (blur+enter+blur)
  if (savingById.value[id]) return

  savingById.value = { ...savingById.value, [id]: true }
  try {
    const body = await LabelsApi.update(id, { name, color })
    const updated = unwrapItem(body) || { ...label, name, color }

    labels.value = labels.value.map((l) => (Number(l.id) === id ? updated : l))
    emit('changed')
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo guardar.' })
    await load()
  } finally {
    const { [id]: _ignored, ...rest } = savingById.value
    savingById.value = rest
  }
}

async function destroyLabel(label) {
  if (!canManage.value) return

  const id = Number(label?.id)
  if (!id) return
  if (savingById.value[id]) return

  const ok = confirm(`Eliminar etiqueta "${label?.name ?? ''}"?`)
  if (!ok) return

  savingById.value = { ...savingById.value, [id]: true }
  try {
    await LabelsApi.destroy(id)
    labels.value = labels.value.filter((l) => Number(l.id) !== id)
    toasts.push({ type: 'info', message: 'Etiqueta eliminada.', timeoutMs: 1600 })
    emit('changed')
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo eliminar.' })
  } finally {
    const { [id]: _ignored, ...rest } = savingById.value
    savingById.value = rest
  }
}

watch(
  () => [props.isOpen, props.boardId],
  () => {
    // al cerrar, invalida requests pendientes y limpia estados visuales
    if (!props.isOpen) {
      loadReq++
      isLoading.value = false
      error.value = null
      labels.value = []
      return
    }
    load()
  },
  { immediate: true },
)
</script>

<template>
  <div v-if="isOpen" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal modal--labels" role="dialog" aria-modal="true" aria-label="Etiquetas del proyecto">
      <div class="modal__top">
        <div class="modal__title">
          <i class="mdi mdi-tag-multiple-outline" aria-hidden="true"></i>
          <span>Etiquetas del proyecto</span>
        </div>
        <md-icon-button type="button" @click="emit('close')">
          <i class="mdi mdi-close" aria-hidden="true"></i>
        </md-icon-button>
      </div>

      <div v-if="isLoading" class="modal__body modal__body--center">
        <md-circular-progress indeterminate class="my-4"></md-circular-progress>
      </div>

      <div v-else class="modal__body m-4">
        <div v-if="error" class="alert alert--danger">{{ error }}</div>
        <div v-if="props.isArchived" class="alert">Proyecto archivado: solo lectura.</div>

        <div class="labels-manager">
          <div v-for="l in labels" :key="l.id" class="labels-manager__row">
            <button
              type="button"
              class="labels-manager__swatch"
              :style="{ background: l.color }"
              :disabled="!canManage || !!savingById[l.id]"
              :title="canManage ? 'Cambiar color' : 'Solo lectura'"
              @click="l.color = pickColor(l.color, 1)"
              @contextmenu.prevent="l.color = pickColor(l.color, -1)"
              @blur="save(l)"
            />
            <input
              v-model="l.name"
              class="input input--compact"
              :disabled="!canManage || !!savingById[l.id]"
              @keydown.enter.prevent="save(l)"
              @blur="save(l)"
            />
            <md-icon-button
              v-if="canManage"
              type="button"
              title="Eliminar"
              :disabled="!!savingById[l.id]"
              @click="destroyLabel(l)"
            >
              <i class="mdi mdi-trash-can-outline" aria-hidden="true"></i>
            </md-icon-button>
            <md-circular-progress
              v-if="!!savingById[l.id]"
              indeterminate
              class="md-spinner-inline-md"
            ></md-circular-progress>
          </div>
        </div>

        <div v-if="canManage" class="labels-manager__create">
          <div class="labels-manager__create-title">Crear etiqueta</div>

          <div class="labels-manager__create-row">
            <button
              type="button"
              class="labels-manager__swatch"
              :style="{ background: newLabel.color }"
              :disabled="newLabel.isSaving"
              title="Cambiar color"
              @click="newLabel.color = pickColor(newLabel.color, 1)"
              @contextmenu.prevent="newLabel.color = pickColor(newLabel.color, -1)"
            />
            <input
              v-model="newLabel.name"
              class="input"
              placeholder="Nombre (ej: Backend)"
              :disabled="newLabel.isSaving"
              @keydown.enter.prevent="create"
            />
            <md-filled-button
              type="button"
              :disabled="newLabel.isSaving || !newLabel.name.trim()"
              @click="create"
            >
              <i class="mdi mdi-plus" aria-hidden="true"></i>
              <span>Crear</span>
              <md-circular-progress
                v-if="newLabel.isSaving"
                indeterminate
                class="md-spinner-inline-md"
              ></md-circular-progress>
            </md-filled-button>
          </div>

          <div class="labels-manager__palette">
            <button
              v-for="c in COLORS"
              :key="c"
              type="button"
              class="labels-manager__dot"
              :style="{ background: c }"
              :title="c"
              @click="newLabel.color = c"
            />
          </div>
        </div>
      </div>

      <div class="modal__bottom">
        <md-text-button type="button" @click="emit('close')">Cerrar</md-text-button>
      </div>
    </div>
  </div>
</template>