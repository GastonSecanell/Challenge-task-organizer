<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useToastStore } from '@/stores/toasts'
import { useThemeStore } from '@/stores/theme'
import { getAvatarObjectUrl, revokeAvatarObjectUrl } from '@/lib/avatars'
import { validateAvatarFile } from '@/lib/avatarValidation'
import { BoardsApi } from '@/lib/api/boards'
import { AdminApi } from '@/lib/api/admin'
import ChangePasswordModal from '@/components/ChangePasswordModal.vue'
import AvatarEditorModal from '@/components/AvatarEditorModal.vue'

const auth = useAuthStore()
const toasts = useToastStore()
const theme = useThemeStore()
const router = useRouter()

const props = defineProps({
  isOpen: { type: Boolean, default: true },
  isCollapsed: { type: Boolean, default: false },
  isMobile: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'toggle-collapse'])

const boards = ref([])
const boardsError = ref(null)
const creating = ref(false)
const newBoardName = ref('')

const canCreateBoard = computed(() => auth.canManageBoards)

const rename = reactive({
  boardId: null,
  value: '',
  isSaving: false,
})

function normalizeList(body) {
  if (Array.isArray(body?.data)) return body.data
  if (Array.isArray(body)) return body
  return []
}

async function loadBoards() {
  boardsError.value = null
  try {
    const body = await BoardsApi.list()
    boards.value = normalizeList(body)
  } catch (e) {
    boardsError.value = e?.message ?? String(e)
  }
}

function startRename(board) {
  if (!auth.canManageBoards) return
  rename.boardId = Number(board.id)
  rename.value = board.name ?? ''
}

function cancelRename() {
  rename.boardId = null
  rename.value = ''
  rename.isSaving = false
}

async function saveRename() {
  if (!auth.canManageBoards) return

  const id = Number(rename.boardId)
  const name = rename.value.trim()
  if (!id || !name) return

  rename.isSaving = true
  try {
    const body = await BoardsApi.rename(id, name)
    const newName = body?.data?.name ?? body?.name ?? name

    boards.value = boards.value.map((b) =>
      Number(b.id) === id ? { ...b, name: newName } : b,
    )

    toasts.push({ type: 'success', message: 'Proyecto renombrado.', timeoutMs: 1800 })
    cancelRename()
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo renombrar.' })
  } finally {
    rename.isSaving = false
  }
}

async function createBoard() {
  const name = newBoardName.value.trim()
  if (!name) return

  creating.value = true
  try {
    const body = await BoardsApi.create(name)
    const board = body?.data ?? body ?? null

    if (board) boards.value = [board, ...boards.value]
    newBoardName.value = ''
    toasts.push({ type: 'success', message: 'Proyecto creado.' })
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo crear el proyecto.' })
  } finally {
    creating.value = false
  }
}

async function doLogout() {
  closeUserMenu()
  await auth.logout()
  toasts.push({ type: 'info', message: 'Sesión cerrada.' })
  await router.push('/login')
}

const myAvatarUrl = ref(null)
const myAvatarUserId = ref(null)
const isAvatarUploading = ref(false)

const userMenuOpen = ref(false)
const userMenuEl = ref(null)

const avatarInput = ref(null)

const avatarModal = reactive({
  open: false,
  file: null,
  imageUrl: '',
  isSaving: false,
})

function userInitial() {
  return String(auth.user?.name || '?').slice(0, 1).toUpperCase()
}

let avatarReqId = 0
async function loadMyAvatar() {
  const reqId = ++avatarReqId

  if (myAvatarUserId.value) {
    revokeAvatarObjectUrl(myAvatarUserId.value)
  }

  const uid = Number(auth.user?.id || 0)
  myAvatarUserId.value = uid || null

  if (!uid || !auth.user?.has_avatar) {
    myAvatarUrl.value = null
    return
  }

  const url = await getAvatarObjectUrl(auth.user, auth.token)
  if (reqId !== avatarReqId) return

  myAvatarUrl.value = url
}

function toggleUserMenu() {
  if (props.isCollapsed) {
    emit('toggle-collapse')
    return
  }
  userMenuOpen.value = !userMenuOpen.value
}

function closeUserMenu() {
  userMenuOpen.value = false
}

function onDocClick(ev) {
  const el = userMenuEl.value
  if (!el) return
  if (!el.contains(ev.target)) closeUserMenu()
}

function roleLabel(r) {
  const n = Number(r)
  if (n === 1) return 'Administrador'
  if (n === 2) return 'Operador'
  if (n === 3) return 'Consulta'
  if (n === 4) return 'Auditoría'
  return String(r ?? '')
}

const changePasswordModalOpen = ref(false)

function openChangePasswordModal() {
  closeUserMenu()
  changePasswordModalOpen.value = true
}

function closeChangePasswordModal() {
  changePasswordModalOpen.value = false
}

async function onPasswordChanged() {
  changePasswordModalOpen.value = false
  await auth.logout()
  toasts.push({
    type: 'success',
    message: 'Contraseña actualizada. Iniciá sesión nuevamente.',
    timeoutMs: 2200,
  })
  await router.push('/login')
}

function openAvatarPicker() {
  avatarInput.value?.click?.()
}

function openEditCurrentAvatar() {
  if (!myAvatarUrl.value) {
    toasts.push({ type: 'info', message: 'Todavía no tenés una foto cargada.' })
    return
  }

  avatarModal.file = null
  avatarModal.imageUrl = myAvatarUrl.value
  avatarModal.open = true
}

function closeAvatarModal() {
  avatarModal.open = false
  avatarModal.file = null
  avatarModal.imageUrl = ''
  avatarModal.isSaving = false
}

function onPickAvatarFile(ev) {
  const file = ev?.target?.files?.[0]
  if (ev?.target) ev.target.value = ''
  if (!file) return

  const msg = validateAvatarFile(file)
  if (msg) {
    toasts.push({ type: 'error', message: msg })
    return
  }

  avatarModal.file = file
  avatarModal.imageUrl = ''
  avatarModal.open = true
}

async function saveMyAvatar(file) {
  const uid = Number(auth.user?.id || 0)
  if (!uid || !file) return

  avatarModal.isSaving = true
  isAvatarUploading.value = true

  try {
    await AdminApi.uploadUserAvatar(uid, file)
    await auth.fetchMe()

    revokeAvatarObjectUrl(uid)
    myAvatarUrl.value = await getAvatarObjectUrl(auth.user, auth.token)

    toasts.push({
      type: 'success',
      message: 'Foto de perfil actualizada.',
      timeoutMs: 1800,
    })

    closeAvatarModal()
  } catch (e) {
    toasts.push({
      type: 'error',
      message: e?.message ?? 'No se pudo actualizar la foto.',
    })
  } finally {
    avatarModal.isSaving = false
    isAvatarUploading.value = false
  }
}

async function removeMyAvatar() {
  const uid = Number(auth.user?.id || 0)
  if (!uid) return

  isAvatarUploading.value = true

  try {
    await AdminApi.deleteUserAvatar(uid)
    revokeAvatarObjectUrl(uid)
    myAvatarUrl.value = null
    await auth.fetchMe()

    toasts.push({
      type: 'info',
      message: 'Foto eliminada.',
      timeoutMs: 1600,
    })
  } catch (e) {
    toasts.push({
      type: 'error',
      message: e?.message ?? 'No se pudo eliminar la foto.',
    })
  } finally {
    isAvatarUploading.value = false
  }
}

const profileAvatarModalOpen = ref(false)

function openProfileAvatarModal() {
  closeUserMenu()
  profileAvatarModalOpen.value = true
}

function closeProfileAvatarModal() {
  profileAvatarModalOpen.value = false
}

onMounted(() => {
  loadBoards()
  loadMyAvatar()
  document.addEventListener('click', onDocClick)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick)
  if (myAvatarUserId.value) revokeAvatarObjectUrl(myAvatarUserId.value)
})

watch(
  () => [auth.user?.id, auth.user?.has_avatar, auth.user?.avatar_url],
  () => {
    loadMyAvatar()
  },
)
</script>

<template>
  <aside class="sidebar" :class="props.isOpen ? 'sidebar--open' : 'sidebar--closed'">
    <div class="sidebar__top">
      <div class="sidebar__brand-row">
        <div class="sidebar__brand">
          <div class="sidebar__logo">TR</div>
          <div v-if="!props.isCollapsed" class="sidebar__brand-text">
            <div class="sidebar__appname">Trollo</div>
            <div class="sidebar__tagline">Como Trello pero con O</div>
          </div>
        </div>

        <div class="sidebar__top-buttons">
          <md-icon-button
            v-if="!props.isMobile"
            type="button"
            :title="props.isCollapsed ? 'Expandir' : 'Colapsar'"
            @click="emit('toggle-collapse')"
          >
            <i
              class="mdi"
              :class="props.isCollapsed ? 'mdi-chevron-right' : 'mdi-chevron-left'"
              aria-hidden="true"
            ></i>
          </md-icon-button>

          <md-icon-button v-if="props.isMobile" type="button" title="Cerrar" @click="emit('close')">
            <i class="mdi mdi-close" aria-hidden="true"></i>
          </md-icon-button>
        </div>
      </div>

      <div ref="userMenuEl" class="sidebar__userbox">
        <button type="button" class="user-chip" @click="toggleUserMenu">
          <span class="user-chip__avatar">
            <img v-if="myAvatarUrl" :src="myAvatarUrl" alt="" />
            <span v-else>{{ userInitial() }}</span>
          </span>

          <span v-if="!props.isCollapsed" class="user-chip__meta">
            <span class="user-chip__name">{{ auth.user?.name }}</span>
            <span class="user-chip__email">{{ auth.user?.email }}</span>
          </span>

          <i
            v-if="!props.isCollapsed"
            class="mdi mdi-chevron-down user-chip__chev"
            aria-hidden="true"
          ></i>
        </button>

        <div v-if="userMenuOpen" class="user-menu">
          <div class="user-menu__row">
            <span class="user-chip__avatar user-chip__avatar--lg">
              <img v-if="myAvatarUrl" :src="myAvatarUrl" alt="" />
              <span v-else>{{ userInitial() }}</span>
            </span>

            <div class="user-menu__meta">
              <div class="user-menu__name">{{ auth.user?.name }}</div>
              <div class="user-menu__email">{{ auth.user?.email }}</div>
              <div class="user-menu__role">
                {{ auth.user?.role_name || roleLabel(auth.user?.role_id ?? auth.user?.role) }}
              </div>
            </div>
          </div>

          <div class="user-menu__actions">
            <md-text-button type="button" @click="openProfileAvatarModal">
              <i class="mdi mdi-account-circle-outline mx-1" aria-hidden="true"></i>
              <span>Foto de perfil</span>
            </md-text-button>

            <md-text-button type="button" @click="theme.toggle()">
              <i
                class="mdi "
                :class="theme.isDark ? 'mdi-weather-sunny mx-1' : 'mdi-weather-night mx-1'"
                aria-hidden="true"
              ></i>
              <span>{{ theme.isDark ? 'Claro' : 'Oscuro' }}</span>
            </md-text-button>

            <md-text-button type="button" @click="openChangePasswordModal">
              <i class="mdi mdi-lock-reset mx-1" aria-hidden="true"></i>
              <span>Cambiar contraseña</span>
            </md-text-button>

            <md-text-button type="button" @click="doLogout">
              <i class="mdi mdi-logout mx-1" aria-hidden="true"></i>
              <span>Cerrar sesión</span>
            </md-text-button>
          </div>
        </div>
      </div>
    </div>

    <div class="sidebar__section">
      <nav class="sidebar__nav sidebar__nav--meta">
        <RouterLink class="nav-item" to="/dashboard" title="Dashboard">
          <i class="mdi mdi-view-dashboard-outline nav-item__icon" aria-hidden="true"></i>
          <span class="nav-item__text">Dashboard</span>
        </RouterLink>

        <RouterLink class="nav-item" to="/archived" title="Archivados">
          <i class="mdi mdi-archive-outline nav-item__icon" aria-hidden="true"></i>
          <span class="nav-item__text">Archivados</span>
        </RouterLink>
      </nav>

      <div class="sidebar__section-title mt-2">Proyectos</div>

      <div v-if="boardsError" class="sidebar__hint">Error: {{ boardsError }}</div>

      <nav class="sidebar__nav">
        <template v-for="b in boards" :key="b.id">
          <div v-if="rename.boardId === Number(b.id)" class="nav-item nav-item--editing">
            <span class="nav-item__dot" aria-hidden="true"></span>
            <input
              v-model="rename.value"
              class="input input--compact"
              @keydown.enter.prevent="saveRename"
              @keydown.escape.prevent="cancelRename"
              @blur="saveRename"
              autofocus
            />
            <md-icon-button type="button" title="Guardar" :disabled="rename.isSaving" @click="saveRename">
              <i class="mdi mdi-check" aria-hidden="true"></i>
            </md-icon-button>
            <md-icon-button type="button" title="Cancelar" :disabled="rename.isSaving" @click="cancelRename">
              <i class="mdi mdi-close" aria-hidden="true"></i>
            </md-icon-button>
            <md-circular-progress
              v-if="rename.isSaving"
              indeterminate
              class="md-spinner-inline"
            ></md-circular-progress>
          </div>

          <RouterLink v-else class="nav-item" :to="`/boards/${b.id}`" :title="b.name">
            <span class="nav-item__dot" aria-hidden="true"></span>
            <span class="nav-item__text" @dblclick.prevent.stop="startRename(b)">
              {{ b.name }}
            </span>
          </RouterLink>
        </template>
      </nav>

      <div v-if="canCreateBoard" class="sidebar__create">
        <input
          v-model="newBoardName"
          class="input"
          placeholder="Nuevo proyecto…"
          @keydown.enter.prevent="createBoard"
        />
        <md-filled-button type="button" :disabled="creating" @click="createBoard">
          <i class="mdi mdi-plus" aria-hidden="true"></i>
          <span>Crear</span>
          <md-circular-progress v-if="creating" indeterminate class="md-spinner-inline"></md-circular-progress>
        </md-filled-button>
      </div>
    </div>

    <div v-if="auth.isAdmin || auth.canReadAudit" class="sidebar__section">
      <div class="sidebar__section-title">Administración</div>
      <nav class="sidebar__nav">
        <RouterLink v-if="auth.isAdmin" class="nav-item" to="/admin/users">
          <i class="mdi mdi-account-multiple-outline nav-item__icon" aria-hidden="true"></i>
          <span class="nav-item__text">Usuarios</span>
        </RouterLink>

        <RouterLink v-if="auth.canReadAudit" class="nav-item" to="/admin/audit">
          <i class="mdi mdi-text-box-search-outline nav-item__icon" aria-hidden="true"></i>
          <span class="nav-item__text">Auditoría</span>
        </RouterLink>
      </nav>
    </div>
  </aside>

  <ChangePasswordModal
    :isOpen="changePasswordModalOpen"
    @close="closeChangePasswordModal"
    @changed="onPasswordChanged"
  />

  <div
  v-if="profileAvatarModalOpen"
  class="modal-backdrop"
  @click.self="closeProfileAvatarModal"
>
  <div class="modal profile-avatar-modal">
    <div class="modal__top">
      <div class="modal__title">Foto de perfil</div>
      <md-icon-button type="button" title="Cerrar" @click="closeProfileAvatarModal">
        <i class="mdi mdi-close" aria-hidden="true"></i>
      </md-icon-button>
    </div>

    <div class="modal__body profile-avatar-modal__body">
      <div class="profile-avatar-modal__header">
        <span class="profile-avatar-modal__avatar">
          <img v-if="myAvatarUrl" :src="myAvatarUrl" alt="" />
          <span v-else>{{ userInitial() }}</span>
        </span>

        <div class="profile-avatar-modal__meta">
          <div class="profile-avatar-modal__name">{{ auth.user?.name }}</div>
          <div class="profile-avatar-modal__email">{{ auth.user?.email }}</div>
        </div>
      </div>

      <input
        ref="avatarInput"
        type="file"
        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
        hidden
        @change="onPickAvatarFile"
      />

      <div class="profile-avatar-modal__actions">
        <md-filled-button type="button" :disabled="isAvatarUploading" @click="openAvatarPicker">
          <i class="mdi mdi-image-plus-outline" aria-hidden="true"></i>
          <span>{{ auth.user?.has_avatar ? 'Nueva foto' : 'Agregar foto' }}</span>
        </md-filled-button>

        <md-text-button
          v-if="auth.user?.has_avatar"
          type="button"
          :disabled="isAvatarUploading"
          @click="openEditCurrentAvatar"
        >
          <i class="mdi mdi-image-edit-outline" aria-hidden="true"></i>
          <span>Editar foto actual</span>
        </md-text-button>

        <md-text-button
          v-if="auth.user?.has_avatar"
          type="button"
          :disabled="isAvatarUploading"
          @click="removeMyAvatar"
        >
          <i class="mdi mdi-trash-can-outline" aria-hidden="true"></i>
          <span>Eliminar foto</span>
        </md-text-button>
      </div>
    </div>
  </div>
</div>

  <AvatarEditorModal
    :open="avatarModal.open"
    :file="avatarModal.file"
    :image-url="avatarModal.imageUrl"
    :saving="avatarModal.isSaving"
    title="Editar foto de perfil"
    @close="closeAvatarModal"
    @saved="saveMyAvatar"
    @error="(e) => toasts.push({ type: 'error', message: e?.message ?? 'No se pudo preparar la imagen.' })"
  />
</template>