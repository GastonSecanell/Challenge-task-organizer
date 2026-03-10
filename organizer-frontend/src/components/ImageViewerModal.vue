<script setup>
import { onBeforeUnmount, watch } from 'vue'

const props = defineProps({
  isOpen: { type: Boolean, required: true },
  src: { type: String, default: '' },
  title: { type: String, default: 'Imagen' },
})

const emit = defineEmits(['close'])

function onKeydown(ev) {
  if (ev.key === 'Escape') emit('close')
}

watch(
  () => props.isOpen,
  (open) => {
    if (open) window.addEventListener('keydown', onKeydown)
    else window.removeEventListener('keydown', onKeydown)
  },
  { immediate: true },
)

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <div v-if="isOpen" class="imgv-backdrop" @click.self="emit('close')">
    <div class="imgv-modal">
      <div class="imgv-top">
        <div class="imgv-title">{{ title }}</div>
        <md-icon-button type="button" title="Cerrar" @click="emit('close')">
          <i class="mdi mdi-close" aria-hidden="true"></i>
        </md-icon-button>
      </div>
      <div class="imgv-body">
        <img v-if="src" :src="src" :alt="title" />
      </div>
    </div>
  </div>
</template>

