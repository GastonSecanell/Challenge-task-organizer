<script setup>
import { computed, ref, watch } from 'vue'
import { extractDominantColor } from '@/lib/imageColor'
import { getAvatarObjectUrl } from '@/lib/avatars'

const props = defineProps({
  card: { type: Object, required: true },
  columnId: { type: [Number, String], required: true },
  isSaving: { type: Boolean, default: false },
  isDragging: { type: Boolean, default: false },
  canDelete: { type: Boolean, default: false },
  isDeleting: { type: Boolean, default: false },
})

const emit = defineEmits(['open', 'delete'])

// Color dominante extraído de la imagen de portada (reemplaza el azul hardcodeado)
const extractedCoverBg = ref(null)
watch(
  () => props.card?.cover_image_url,
  async (url) => { extractedCoverBg.value = url ? await extractDominantColor(url) : null },
  { immediate: true },
)

// Fondo sólido detrás de la imagen: cover_color manual > extraído > fallback neutro
const coverFallbackBg = computed(() =>
  props.card?.cover_color || extractedCoverBg.value || '#1e3a5f',
)

const checklistTotal = computed(() => Number(props.card?.checklist_items_count ?? 0))
const checklistDone = computed(() => Number(props.card?.checklist_done_count ?? 0))

const isChecklistComplete = computed(() =>
  checklistTotal.value > 0 && checklistDone.value === checklistTotal.value
)

const isCardComplete = computed(() => Boolean(props.card?.is_done))

function formatDue(iso) {
  if (!iso) return ''
  try {
    const d = new Date(iso)
    const dd = String(d.getDate()).padStart(2, '0')
    const mm = String(d.getMonth() + 1).padStart(2, '0')
    return `${dd}/${mm}`
  } catch {
    return ''
  }
}

const sortedMembers = computed(() => {
  const out = []
  const members = Array.isArray(props.card?.members) ? props.card.members : []
  if (members.length) out.push(...members)
  else if (props.card?.assignee) out.push(props.card.assignee)

  return out
    .filter((u) => Number(u?.id))
    .slice()
    .sort((a, b) => String(a?.name || '').localeCompare(String(b?.name || ''), 'es', { sensitivity: 'base' }))
})

const commentCount = computed(() => Number(props.card?.comments_count ?? 0))
const avatarUrlsByUserId = ref({})

watch(
  sortedMembers,
  async (members) => {
    const next = {}
    for (const m of members ?? []) {
      const id = Number(m?.id)
      if (!id || !m?.has_avatar) continue
      const url = await getAvatarObjectUrl(m)
      if (url) next[id] = url
    }
    avatarUrlsByUserId.value = next
  },
  { immediate: true },
)

function userInitial(user) {
  const v = String(user?.name || user?.email || '?').trim()
  return v ? v.slice(0, 1).toUpperCase() : '?'
}

const badges = computed(() => {
  const out = []
  //if (props.card?.due_at) out.push({ key: 'due', icon: 'mdi-clock-outline', text: formatDue(props.card.due_at) })
  const a = Number(props.card?.attachments_count ?? 0)
  if (a > 0) out.push({ key: 'Adjuntos', icon: 'mdi-paperclip', text: String(a) })
  const total = Number(props.card?.checklist_items_count ?? 0)
  const done = Number(props.card?.checklist_done_count ?? 0)
  if (total > 0) out.push({ key: 'tareas', icon: 'mdi-checkbox-marked-outline', text: `${done}/${total}` })
  if (commentCount.value > 0) out.push({ key: 'Comentarios', icon: 'mdi-comment-outline', text: String(commentCount.value) })
  return out
})
</script>

<template>
  <div
    :class="['card', props.isDragging ? 'card--dragging' : '']"
    :data-card-id="card.id"
    :data-column-id="columnId"
    role="button"
    tabindex="0"
    @click="emit('open', { cardId: card.id })"
    @keydown.enter.prevent="emit('open', { cardId: card.id })"
  >
    <div v-if="props.canDelete" class="card__top-actions">
      <md-circular-progress
        v-if="props.isDeleting"
        indeterminate
        class="md-spinner-inline-md"
      ></md-circular-progress>
      <md-icon-button
        v-else
        type="button"
        title="Eliminar tarjeta"
        @click.stop="emit('delete', { cardId: card.id, columnId })"
      >
        <i class="mdi mdi-trash-can-outline" aria-hidden="true"></i>
      </md-icon-button>
    </div>

    <div
      v-if="card.cover_image_url || card.cover_color"
      class="card__cover"
      :class="card.cover_size === 'large' ? 'card__cover--lg' : ''"
      :style="
        card.cover_image_url
          ? {
              backgroundImage: `url(${card.cover_image_url})`,
              backgroundSize: 'contain',
              backgroundPosition: 'center',
              backgroundRepeat: 'no-repeat',
              backgroundColor: coverFallbackBg,
            }
          : { background: coverFallbackBg }
      "
    />

    <div v-if="(card.labels?.length ?? 0) > 0" class="card__labels">
      <span
        v-for="l in card.labels"
        :key="l.id"
        class="card__label"
        :title="l.name"
        :style="{ background: l.color || '#091e42' }"
      />
    </div>

    <div class="card__title">
      <i
        v-if="Boolean(card.is_done)"
        class="mdi mdi-checkbox-marked-circle card__title-done"
        aria-hidden="true"
        title="Tarjeta completada"
      ></i>
      <span>{{ card.title }}</span>
    </div>

    <div class="card__meta">
      <div class="card__badges">
        <span v-if="isSaving" class="card__saving">Guardando…</span>
        <span
            v-for="b in badges"
            :key="b.key"
            :class="[
              'card__badge',
              b.key === 'tareas' && isChecklistComplete ? 'card__badge--success' : '',
            ]"
            :title="b.key"
          >
          <i class="mdi" :class="b.icon" aria-hidden="true"></i>
          <span>{{ b.text }}</span>
        </span>
      </div>
      <div v-if="sortedMembers.length" class="card__members">
        <span v-for="m in sortedMembers" :key="m.id" class="card__assignee" :title="m.name || m.email || 'Miembro'">
          <img v-if="avatarUrlsByUserId[Number(m.id)]" :src="avatarUrlsByUserId[Number(m.id)]" alt="" />
          <span v-else>{{ userInitial(m) }}</span>
        </span>
      </div>
    </div>
  </div>
</template>

