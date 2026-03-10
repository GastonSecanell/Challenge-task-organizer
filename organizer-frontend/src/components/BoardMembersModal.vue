<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toasts'
import { BoardsApi } from '@/lib/api/boards'
import { getAvatarObjectUrl } from '@/lib/avatars'
import ConfirmActionModal from './ConfirmActionModal.vue'

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

const members = ref([])
const options = ref([])

const memberQuery = ref('')
const addingById = ref({})
const removingById = ref({})
const avatarUrlsByUserId = ref({})

const confirmDelete = reactive({ open: false, user: null })
const confirmTransfer = reactive({ open: false, user: null })

const canManage = computed(() => auth.canManageBoards && !props.isArchived)

function unwrapList(body) {
  if (Array.isArray(body?.data)) return body.data
  if (Array.isArray(body)) return body
  return []
}

function userInitial(user) {
  return String(user?.name || '?').slice(0, 1).toUpperCase()
}

async function ensureAvatars(users) {
  const next = { ...avatarUrlsByUserId.value }
  for (const u of users ?? []) {
    const id = Number(u?.id)
    if (!id || !u?.has_avatar || next[id]) continue
    next[id] = await getAvatarObjectUrl(u, auth.token)
  }
  avatarUrlsByUserId.value = next
}

// ---------- Owner (desde API: is_owner) ----------
const ownerUser = computed(() => (members.value ?? []).find((u) => !!u?.is_owner) || null)
const ownerUserId = computed(() => Number(ownerUser.value?.id || 0))
const isOwnerUser = (u) => Boolean(u?.is_owner)
const isOwnerUserId = (uid) => Number(uid) === ownerUserId.value

// Solo admin o el owner actual puede transferir (y no en archivado)
const canTransferOwner = computed(() => {
  if (props.isArchived) return false
  const me = Number(auth.user?.id || auth.userId || 0)
  return Boolean(auth.isAdmin || (me && ownerUserId.value && me === ownerUserId.value))
})

// ---------- Load ----------
let loadReq = 0
async function load() {
  if (!props.isOpen || !props.boardId) return

  const reqId = ++loadReq
  isLoading.value = true
  error.value = null

  try {
    const [membersBody, optionsBody] = await Promise.all([
      BoardsApi.members(props.boardId),
      canManage.value ? BoardsApi.memberOptions(props.boardId) : Promise.resolve({ data: [] }),
    ])

    if (reqId !== loadReq) return

    members.value = unwrapList(membersBody)
      .slice()
      .sort((a, b) => String(a?.name || '').localeCompare(String(b?.name || '')))

    options.value = unwrapList(optionsBody)
      .slice()
      .sort((a, b) => String(a?.name || '').localeCompare(String(b?.name || '')))

    await ensureAvatars([...(members.value ?? []), ...(options.value ?? [])])
  } catch (e) {
    if (reqId !== loadReq) return
    error.value = e?.message ?? 'No se pudieron cargar los miembros.'
  } finally {
    if (reqId !== loadReq) return
    isLoading.value = false
  }
}

const availableOptions = computed(() => {
  const selected = new Set((members.value ?? []).map((u) => Number(u.id)))
  return (options.value ?? []).filter((u) => !selected.has(Number(u.id)))
})

const filteredAvailableOptions = computed(() => {
  const q = memberQuery.value.trim().toLowerCase()
  if (!q) return availableOptions.value
  return availableOptions.value.filter((u) => {
    const n = String(u?.name || '').toLowerCase()
    const e = String(u?.email || '').toLowerCase()
    return n.includes(q) || e.includes(q)
  })
})

// ---------- Add member ----------
async function addMember(u) {
  if (!canManage.value) return

  const uid = Number(u?.id || 0)
  if (!uid) return
  if (addingById.value[uid]) return

  addingById.value = { ...addingById.value, [uid]: true }

  try {
    await BoardsApi.addMember(props.boardId, uid)
    memberQuery.value = ''
    await load()
    toasts.push({ type: 'success', message: 'Miembro agregado al proyecto.', timeoutMs: 1600 })
    emit('changed')
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo agregar el miembro.' })
  } finally {
    const { [uid]: _ignored, ...rest } = addingById.value
    addingById.value = rest
  }
}

// ---------- Remove member ----------
function askRemoveMember(u) {
  if (!canManage.value) return
  if (isOwnerUser(u)) {
    toasts.push({ type: 'info', message: 'No se puede quitar al propietario del proyecto.' })
    return
  }
  confirmDelete.open = true
  confirmDelete.user = u
}

function closeConfirm() {
  confirmDelete.open = false
  confirmDelete.user = null
}

async function removeMemberNow() {
  const uid = Number(confirmDelete.user?.id || 0)
  if (!uid || removingById.value[uid]) return

  removingById.value = { ...removingById.value, [uid]: true }

  try {
    await BoardsApi.removeMember(props.boardId, uid)
    await load()
    toasts.push({ type: 'info', message: 'Miembro eliminado del proyecto.', timeoutMs: 1600 })
    emit('changed')
    closeConfirm()
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo eliminar el miembro.' })
  } finally {
    const { [uid]: _ignored, ...rest } = removingById.value
    removingById.value = rest
  }
}

// ---------- Transfer owner ----------
function askTransferOwner(u) {
  if (!canTransferOwner.value) return
  if (isOwnerUser(u)) return

  confirmTransfer.open = true
  confirmTransfer.user = u
}

function closeTransferConfirm() {
  confirmTransfer.open = false
  confirmTransfer.user = null
}

async function transferOwnerNow() {
  const uid = Number(confirmTransfer.user?.id || 0)
  if (!uid) return

  try {
    await BoardsApi.transferOwner(props.boardId, uid)

    // recargar para que cambie is_owner en members
    await load()

    toasts.push({ type: 'success', message: 'Propietario transferido.', timeoutMs: 1600 })
    emit('changed') // para que el padre refresque el board si necesita
    closeTransferConfirm()
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo transferir el propietario.' })
  }
}

// ---------- Watch ----------
watch(
  () => [props.isOpen, props.boardId, canManage.value],
  () => {
    if (!props.isOpen) {
      loadReq += 1
      isLoading.value = false
      error.value = null
      members.value = []
      options.value = []
      memberQuery.value = ''
      addingById.value = {}
      closeConfirm()
      closeTransferConfirm()
      return
    }
    load()
  },
  { immediate: true },
)
</script>

<template>
  <div v-if="isOpen" class="modal-backdrop" @click.self="emit('close')">
    <div class="modal modal--labels board-members-modal" role="dialog" aria-modal="true" aria-label="Miembros del proyecto">
      <!-- HEADER -->
      <div class="modal__top">
        <div class="modal__title">
          <i class="mdi mdi-account-multiple-outline" aria-hidden="true"></i>
          <span>Miembros del proyecto</span>
        </div>

        <md-icon-button type="button" @click="emit('close')">
          <i class="mdi mdi-close" aria-hidden="true"></i>
        </md-icon-button>
      </div>

      <!-- LOADING -->
      <div v-if="isLoading" class="modal__body modal__body--center">
        <md-circular-progress indeterminate class="my-4"></md-circular-progress>
      </div>

      <!-- BODY -->
      <div v-else class="modal__body m-4 board-members-modal__body">
        <div v-if="error" class="alert alert--danger">{{ error }}</div>
        <div v-if="props.isArchived" class="alert">Proyecto archivado: solo lectura.</div>

        <!-- OWNER BANNER -->
        <div class="board-members-modal__owner" v-if="ownerUser">
          <span class="badge badge--owner" title="Propietario del proyecto">
          <i class="mdi mdi-crown-outline" aria-hidden="true"></i>Propietario
          </span>
          <span class="board-members-modal__owner-meta">
            <strong>{{ ownerUser?.name }}</strong>
            <span class="muted">· {{ ownerUser?.email }}</span>
          </span>
          <span v-if="!canTransferOwner" class="muted board-members-modal__owner-hint">
            (Admin o propietario puede transferir)
          </span>
        </div>

        <!-- AGREGAR -->
        <div v-if="canManage" class="board-members-modal__section">
          <div class="members-popover__section-title">Agregar miembro al proyecto</div>

          <div class="board-members-modal__search">
            <input
              v-model="memberQuery"
              class="input input--sm"
              placeholder="Buscar usuario por nombre o email…"
              :disabled="availableOptions.length === 0"
            />
          </div>

          <div class="members-popover__list board-members-modal__list">
            <button
              v-for="u in filteredAvailableOptions"
              :key="`opt-${u.id}`"
              type="button"
              class="members-popover__member"
              :disabled="!!addingById[Number(u.id)]"
              @click="addMember(u)"
            >
              <span class="mini-avatar mini-avatar--sm">
                <img v-if="avatarUrlsByUserId[Number(u.id)]" :src="avatarUrlsByUserId[Number(u.id)]" alt="" />
                <span v-else>{{ userInitial(u) }}</span>
              </span>

              <span class="board-members-modal__meta">
                <span class="mini-picker__name">{{ u.name }}</span>
                <span class="mini-picker__muted board-members-modal__email">{{ u.email }}</span>
              </span>

              <span class="members-popover__right">
                <md-circular-progress v-if="addingById[Number(u.id)]" indeterminate class="md-spinner-inline-md" />
                <i v-else class="mdi mdi-account-plus-outline members-popover__check" aria-hidden="true"></i>
              </span>
            </button>

            <div v-if="availableOptions.length === 0" class="muted">No hay más usuarios para agregar.</div>
            <div v-else-if="filteredAvailableOptions.length === 0" class="muted">Sin resultados para ese filtro.</div>
          </div>
        </div>

        <!-- LISTA MIEMBROS -->
        <div class="board-members-modal__section">
          <div class="members-popover__section-title">Miembros actuales del proyecto</div>

          <div class="members-popover__list board-members-modal__list">
            <button v-for="u in members" :key="u.id" type="button" class="members-popover__selected-item" :disabled="!canManage">
              <span class="mini-avatar" :title="u.name || u.email || 'Miembro'">
                <img v-if="avatarUrlsByUserId[Number(u.id)]" :src="avatarUrlsByUserId[Number(u.id)]" alt="" />
                <span v-else>{{ userInitial(u) }}</span>
              </span>

              <span class="board-members-modal__meta">
                <span class="mini-picker__name mx-3">
                  {{ u.name }}
                  <span v-if="isOwnerUser(u)" title="Propietario">
                  <i class="mdi mdi-crown-outline" aria-hidden="true"></i>
                  </span>
                </span>
                <span class="mini-picker__muted board-members-modal__email mx-3">{{ u.email }}</span>
              </span>

              <span class="members-popover__right">
                <md-circular-progress
                  v-if="canManage && removingById[Number(u.id)]"
                  indeterminate
                  class="md-spinner-inline-md"
                />

                <template v-else-if="canManage">
                  <!-- Transferir: solo si NO es owner -->
                  <md-icon-button
                    v-if="canTransferOwner && !isOwnerUser(u)"
                    type="button"
                    :title="`Transferir propiedad a ${u?.name || 'este usuario'}`"
                    @click.stop="askTransferOwner(u)"
                  >
                    <i class="mdi mdi-crown-outline" aria-hidden="true"></i>
                  </md-icon-button>

                  <!-- Quitar: nunca al owner -->
                  <md-icon-button
                    v-if="!isOwnerUser(u)"
                    type="button"
                    title="Quitar del proyecto"
                    @click.stop="askRemoveMember(u)"
                  >
                    <i class="mdi mdi-account-remove-outline" aria-hidden="true"></i>
                  </md-icon-button>
                </template>

                <i v-else class="mdi mdi-check members-popover__check" aria-hidden="true"></i>
              </span>
            </button>

            <div v-if="members.length === 0" class="muted">No hay miembros asignados al proyecto.</div>
          </div>
        </div>
      </div>

      <!-- FOOTER -->
      <div class="modal__bottom">
        <md-text-button type="button" @click="emit('close')">Cerrar</md-text-button>
      </div>
    </div>
  </div>

  <!-- CONFIRM QUITAR -->
  <ConfirmActionModal
    :isOpen="confirmDelete.open"
    title="Quitar miembro"
    :message="`¿Quitar a ${confirmDelete.user?.name || 'este usuario'} del proyecto?`"
    confirmText="Quitar"
    cancelText="Cancelar"
    @close="closeConfirm"
    @confirm="removeMemberNow"
  />

  <!-- CONFIRM TRANSFER -->
  <ConfirmActionModal
    :isOpen="confirmTransfer.open"
    title="Transferir propiedad"
    :message="`¿Querés transferir la propiedad del proyecto a ${confirmTransfer.user?.name || 'este usuario'}?`"
    confirmText="Transferir"
    cancelText="Cancelar"
    @close="closeTransferConfirm"
    @confirm="transferOwnerNow"
  />
</template>