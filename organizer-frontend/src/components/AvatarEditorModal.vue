<script setup>
import { reactive, watch, onBeforeUnmount } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  file: { type: [File, null], default: null },
  imageUrl: { type: String, default: '' },
  title: { type: String, default: 'Acomodar foto de perfil' },
  saving: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'saved', 'error'])

const crop = reactive({
  src: null,
  zoom: 1,
  x: 0,
  y: 0,
})

function revokePreview() {
  if (crop.src && typeof crop.src === 'string' && crop.src.startsWith('blob:')) {
    try {
      URL.revokeObjectURL(crop.src)
    } catch {}
  }
}

function resetCrop() {
  crop.zoom = 1
  crop.x = 0
  crop.y = 0
}

function loadSource() {
  revokePreview()
  crop.src = null
  resetCrop()

  if (props.file) {
    crop.src = URL.createObjectURL(props.file)
    return
  }

  if (props.imageUrl) {
    crop.src = props.imageUrl
  }
}

watch(
  () => [props.file, props.imageUrl, props.open],
  () => {
    if (!props.open) return
    loadSource()
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  revokePreview()
})

function close() {
  if (props.saving) return
  emit('close')
}

async function buildCroppedAvatarFile() {
  if (!crop.src) {
    throw new Error('No hay imagen para recortar.')
  }

  const img = new Image()
  img.crossOrigin = 'anonymous'
  img.src = crop.src

  await new Promise((resolve, reject) => {
    img.onload = resolve
    img.onerror = () => reject(new Error('No se pudo cargar la imagen seleccionada.'))
  })

  const viewport = 320
  const scale = Math.max(0.6, Math.min(Number(crop.zoom || 1), 3))
  const dx = Number(crop.x || 0)
  const dy = Number(crop.y || 0)

  const coverScale = Math.max(viewport / img.naturalWidth, viewport / img.naturalHeight)
  const baseW = img.naturalWidth * coverScale
  const baseH = img.naturalHeight * coverScale

  const canvas = document.createElement('canvas')
  canvas.width = viewport
  canvas.height = viewport

  const ctx = canvas.getContext('2d')
  if (!ctx) {
    throw new Error('No se pudo preparar el recorte.')
  }

  ctx.imageSmoothingEnabled = true
  ctx.imageSmoothingQuality = 'high'
  ctx.clearRect(0, 0, viewport, viewport)

  ctx.save()
  ctx.translate(viewport / 2 + dx, viewport / 2 + dy)
  ctx.scale(scale, scale)
  ctx.drawImage(img, -baseW / 2, -baseH / 2, baseW, baseH)
  ctx.restore()

  const blob = await new Promise((resolve) => {
    canvas.toBlob(resolve, 'image/jpeg', 0.92)
  })

  if (!blob) {
    throw new Error('No se pudo generar la imagen recortada.')
  }

  return new File([blob], `avatar-${Date.now()}.jpg`, { type: 'image/jpeg' })
}

async function save() {
  try {
    const file = await buildCroppedAvatarFile()
    emit('saved', file)
  } catch (e) {
    emit('error', e)
  }
}
</script>

<template>
  <div v-if="open" class="modal-backdrop" @click.self="close">
    <div class="modal avatar-editor-modal">
      <div class="modal__top">
        <div class="modal__title">{{ title }}</div>

        <md-icon-button type="button" title="Cerrar" :disabled="saving" @click="close">
          <i class="mdi mdi-close" aria-hidden="true"></i>
        </md-icon-button>
      </div>

      <div class="modal__body avatar-editor-modal__body">
        <div class="avatar-cropper">
          <div class="avatar-cropper__viewport">
            <img
              v-if="crop.src"
              :src="crop.src"
              alt="Vista previa del avatar"
              class="avatar-cropper__img"
              :style="{ transform: `translate(${crop.x}px, ${crop.y}px) scale(${crop.zoom})` }"
              draggable="false"
            />
          </div>

          <div class="avatar-cropper__controls">
            <label class="field">
              <span class="field__label">Zoom</span>
              <input v-model.number="crop.zoom" type="range" min="0.6" max="3" step="0.01" />
            </label>

            <label class="field">
              <span class="field__label">Horizontal</span>
              <input v-model.number="crop.x" type="range" min="-180" max="180" step="1" />
            </label>

            <label class="field">
              <span class="field__label">Vertical</span>
              <input v-model.number="crop.y" type="range" min="-180" max="180" step="1" />
            </label>

            <div class="muted">
              Ajustá la imagen dentro del recorte cuadrado. El archivo final se guarda optimizado en JPG.
            </div>
          </div>
        </div>
      </div>

      <div class="modal__bottom">
        <md-text-button type="button" :disabled="saving" @click="close">
          Cancelar
        </md-text-button>

        <md-filled-button type="button" :disabled="saving || !crop.src" @click="save">
          Guardar foto
          <md-circular-progress
            v-if="saving"
            indeterminate
            class="md-spinner-inline"
          ></md-circular-progress>
        </md-filled-button>
      </div>
    </div>
  </div>
</template>