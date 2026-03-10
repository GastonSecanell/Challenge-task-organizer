<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { AdminApi } from '@/lib/api/admin'
import { useToastStore } from '@/stores/toasts'
import { getAvatarObjectUrl, revokeAvatarObjectUrl } from '@/lib/avatars'
import { validateAvatarFile } from '@/lib/avatarValidation'
import AvatarEditorModal from '@/components/AvatarEditorModal.vue'

const auth = useAuthStore()
const toasts = useToastStore()

const users = ref([])
const error = ref(null)
const isLoading = ref(false)
const isCreating = ref(false)
const isUploadingByUserId = ref({})
const avatarUrlsByUserId = ref({})
const avatarInputByUserId = ref({})
const roles = ref([])
const isLoadingRoles = ref(false)
const isSavingUserById = ref({})
const createErrors = ref({})
const editErrors = ref({})

const editingUserId = ref(null)
const editForm = reactive({
  name: '',
  email: '',
  password: '',
  role_ids: [],
})

const avatarModal = reactive({
  open: false,
  userId: null,
  file: null,
  imageUrl: '',
  isSaving: false,
})

const form = ref({
  name: '',
  email: '',
  role_id: 2,
  password: '',
})

const roleOptions = computed(() =>
  (roles.value ?? []).map((r) => ({
    value: Number(r.id),
    label: r.name || `Rol #${r.id}`,
  })),
)

const canUse = computed(() => auth.isAdmin)

function roleNameById(id) {
  const n = Number(id)
  return roles.value.find((r) => Number(r.id) === n)?.name || `Rol #${n}`
}

function normalizeUserRoleIds(u) {
  const rel = Array.isArray(u?.roles)
    ? u.roles.map((r) => Number(r?.id)).filter((id) => id > 0)
    : []

  if (rel.length) return Array.from(new Set(rel))

  const single = Number(u?.role_id || u?.role || 0)
  return single > 0 ? [single] : []
}

function validateName(name) {
  const v = String(name || '').trim()
  if (!v) return 'El nombre es obligatorio.'
  if (v.length < 2) return 'El nombre debe tener al menos 2 caracteres.'
  if (v.length > 255) return 'El nombre no puede superar 255 caracteres.'
  return null
}

function validateEmail(email) {
  const v = String(email || '').trim()
  if (!v) return 'El correo es obligatorio.'
  if (v.length > 255) return 'El correo no puede superar 255 caracteres.'
  const ok = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)
  if (!ok) return 'El correo no tiene un formato válido.'
  return null
}

function validatePassword(password, { required = false } = {}) {
  const v = String(password || '')
  if (!v && !required) return null
  if (!v && required) return 'La contraseña es obligatoria.'
  if (v.length < 6) return 'La contraseña debe tener al menos 6 caracteres.'
  if (v.length > 255) return 'La contraseña no puede superar 255 caracteres.'
  return null
}

function validateCreateForm() {
  const errs = {}

  const nErr = validateName(form.value.name)
  if (nErr) errs.name = nErr

  const eErr = validateEmail(form.value.email)
  if (eErr) errs.email = eErr

  const pErr = validatePassword(form.value.password, { required: true })
  if (pErr) errs.password = pErr

  const roleId = Number(form.value.role_id || 0)
  if (!roleId) errs.role_id = 'Seleccioná un rol.'

  createErrors.value = errs
  return Object.keys(errs).length === 0
}

function startEditUser(u) {
  editingUserId.value = Number(u?.id)
  editErrors.value = {}
  editForm.name = String(u?.name || '')
  editForm.email = String(u?.email || '')
  editForm.password = ''
  editForm.role_ids = normalizeUserRoleIds(u)
}

function cancelEditUser() {
  editingUserId.value = null
  editForm.name = ''
  editForm.email = ''
  editForm.password = ''
  editForm.role_ids = []
  editErrors.value = {}
}

function toggleEditRole(roleId) {
  const id = Number(roleId)
  if (!id) return

  if (editForm.role_ids.includes(id)) {
    editForm.role_ids = editForm.role_ids.filter((x) => x !== id)
  } else {
    editForm.role_ids = [...editForm.role_ids, id]
  }
}

function validateEditForm() {
  const errs = {}

  const nErr = validateName(editForm.name)
  if (nErr) errs.name = nErr

  const eErr = validateEmail(editForm.email)
  if (eErr) errs.email = eErr

  const pErr = validatePassword(editForm.password, { required: false })
  if (pErr) errs.password = pErr

  if (!editForm.role_ids?.length) errs.role_ids = 'Seleccioná al menos un rol.'

  editErrors.value = errs
  return Object.keys(errs).length === 0
}

async function saveEditUser(u) {
  const uid = Number(u?.id)
  if (!uid) return
  if (!validateEditForm()) return

  isSavingUserById.value[uid] = true

  try {
    const payload = {
      name: String(editForm.name || '').trim(),
      email: String(editForm.email || '').trim(),
      role_ids: Array.from(
        new Set((editForm.role_ids || []).map((x) => Number(x)).filter((x) => x > 0))
      ),
    }

    if (String(editForm.password || '').trim()) {
      payload.password = String(editForm.password)
    }

    const body = await AdminApi.updateUser(uid, payload)
    const updated = body?.data ?? null

    if (updated) {
      users.value = users.value.map((x) => (Number(x.id) === uid ? updated : x))
    }

    cancelEditUser()
    toasts.push({ type: 'success', message: 'Usuario actualizado.', timeoutMs: 1600 })
  } catch (e) {
    toasts.push({ type: 'error', message: e?.message ?? 'No se pudo actualizar el usuario.' })
  } finally {
    isSavingUserById.value[uid] = false
  }
}

async function load() {
  isLoading.value = true
  isLoadingRoles.value = true
  error.value = null

  try {
    const [rolesJson, usersJson] = await Promise.all([
      AdminApi.getRoles(),
      AdminApi.getUsers(),
    ])

    roles.value = rolesJson?.data ?? []

    const defaultRole = Number(
      roles.value.find((r) => Number(r.id) === 2)?.id || roles.value[0]?.id || 2
    )

    if (!Number(form.value.role_id)) {
      form.value.role_id = defaultRole
    }

    users.value = usersJson?.data ?? []

    for (const u of users.value) {
      const uid = Number(u.id)
      revokeAvatarObjectUrl(uid)
      avatarUrlsByUserId.value[uid] = null

      if (u.has_avatar) {
        avatarUrlsByUserId.value[uid] = await getAvatarObjectUrl(u, auth.token)
      }
    }
  } catch (e) {
    error.value = e?.message ?? String(e)
  } finally {
    isLoading.value = false
    isLoadingRoles.value = false
  }
}

async function createUser() {
  if (!validateCreateForm()) return

  error.value = null
  isCreating.value = true

  try {
    const payload = {
      name: String(form.value.name || '').trim(),
      email: String(form.value.email || '').trim(),
      role_id: Number(form.value.role_id),
      password: String(form.value.password || ''),
    }

    const json = await AdminApi.createUser(payload)
    users.value = [json.data, ...users.value]

    const defaultRole = Number(
      roles.value.find((r) => Number(r.id) === 2)?.id || roles.value[0]?.id || 2
    )

    form.value = {
      name: '',
      email: '',
      role_id: defaultRole,
      password: '',
    }

    createErrors.value = {}
    toasts.push({ type: 'success', message: 'Usuario creado.', timeoutMs: 1800 })
  } catch (e) {
    error.value = e?.message ?? String(e)
    toasts.push({ type: 'error', message: error.value || 'No se pudo crear el usuario.' })
  } finally {
    isCreating.value = false
  }
}

function userInitial(u) {
  return String(u?.name || '?').slice(0, 1).toUpperCase()
}

function bindAvatarInput(userId, el) {
  const uid = Number(userId)
  if (!uid) return

  if (el) avatarInputByUserId.value[uid] = el
  else delete avatarInputByUserId.value[uid]
}

function openAvatarPicker(userId) {
  const uid = Number(userId)
  const input = avatarInputByUserId.value[uid]
  input?.click?.()
}

function closeAvatarModal() {
  avatarModal.open = false
  avatarModal.userId = null
  avatarModal.file = null
  avatarModal.imageUrl = ''
  avatarModal.isSaving = false
}

function openAvatarCrop(u, ev) {
  const uid = Number(u?.id)
  const file = ev?.target?.files?.[0]

  if (ev?.target) ev.target.value = ''
  if (!uid || !file) return

  const msg = validateAvatarFile(file)
  if (msg) {
    toasts.push({ type: 'error', message: msg })
    return
  }

  closeAvatarModal()
  avatarModal.userId = uid
  avatarModal.file = file
  avatarModal.imageUrl = ''
  avatarModal.open = true
}

function editExistingAvatar(u) {
  const uid = Number(u?.id)
  if (!uid) return

  const currentUrl = avatarUrlsByUserId.value[uid]
  if (!currentUrl) {
    toasts.push({ type: 'info', message: 'Este usuario no tiene avatar cargado.' })
    return
  }

  closeAvatarModal()
  avatarModal.userId = uid
  avatarModal.file = null
  avatarModal.imageUrl = currentUrl
  avatarModal.open = true
}

async function saveAvatarCrop(file) {
  const uid = Number(avatarModal.userId || 0)
  if (!uid || !file) return

  avatarModal.isSaving = true
  isUploadingByUserId.value[uid] = true

  try {
    const json = await AdminApi.uploadUserAvatar(uid, file)

    revokeAvatarObjectUrl(uid)

    const prev = users.value.find((x) => Number(x.id) === uid) || {}
    const freshUser = {
      ...prev,
      id: uid,
      has_avatar: true,
      avatar_url: json?.data?.avatar_url ?? prev.avatar_url,
    }

    avatarUrlsByUserId.value[uid] = await getAvatarObjectUrl(freshUser, auth.token)

    users.value = users.value.map((x) =>
      Number(x.id) === uid
        ? { ...x, has_avatar: true, avatar_url: freshUser.avatar_url }
        : x
    )

    if (Number(auth.user?.id) === uid) {
      await auth.fetchMe()
    }

    toasts.push({
      type: 'success',
      message: 'Avatar actualizado.',
      timeoutMs: 1800,
    })

    closeAvatarModal()
  } catch (e) {
    toasts.push({
      type: 'error',
      message: e?.message ?? 'No se pudo subir el avatar.',
    })
  } finally {
    avatarModal.isSaving = false
    isUploadingByUserId.value[uid] = false
  }
}

async function removeAvatar(u) {
  const uid = Number(u?.id)
  if (!uid) return

  isUploadingByUserId.value[uid] = true

  try {
    await AdminApi.deleteUserAvatar(uid)

    revokeAvatarObjectUrl(uid)
    avatarUrlsByUserId.value[uid] = null

    users.value = users.value.map((x) =>
      Number(x.id) === uid
        ? { ...x, has_avatar: false, avatar_url: null }
        : x
    )

    if (Number(auth.user?.id) === uid) {
      await auth.fetchMe()
    }

    toasts.push({ type: 'info', message: 'Avatar eliminado.', timeoutMs: 1600 })
  } catch (e) {
    toasts.push({
      type: 'error',
      message: e?.message ?? 'No se pudo eliminar el avatar.',
    })
  } finally {
    isUploadingByUserId.value[uid] = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <div class="page__header">
      <div>
        <div class="page__title">Usuarios</div>
        <div class="page__subtitle">Solo administradores</div>
      </div>
    </div>

    <div v-if="!canUse" class="alert alert--danger">
      No tenés permisos para ver esta sección.
    </div>

    <template v-else>
      <div class="card-ui">
        <div class="card-ui__title">Crear usuario</div>

        <div class="grid grid--2">
          <label class="field">
            <span class="field__label">Nombre</span>
            <input v-model="form.name" class="input" />
            <span v-if="createErrors.name" class="field__error">{{ createErrors.name }}</span>
          </label>

          <label class="field">
            <span class="field__label">Correo</span>
            <input v-model="form.email" class="input" type="email" />
            <span v-if="createErrors.email" class="field__error">{{ createErrors.email }}</span>
          </label>

          <label class="field">
            <span class="field__label">Rol</span>
            <select v-model="form.role_id" class="input input--select" :disabled="isLoadingRoles">
              <option v-for="r in roleOptions" :key="r.value" :value="r.value">
                {{ r.label }}
              </option>
            </select>
            <span v-if="createErrors.role_id" class="field__error">{{ createErrors.role_id }}</span>
          </label>

          <label class="field">
            <span class="field__label">Contraseña</span>
            <input v-model="form.password" class="input" type="password" />
            <span v-if="createErrors.password" class="field__error">{{ createErrors.password }}</span>
          </label>
        </div>

        <div v-if="error" class="alert alert--danger">{{ error }}</div>

        <md-filled-button type="button" class="mt-4" :disabled="isCreating" @click="createUser">
          <i class="mdi mdi-account-plus-outline" aria-hidden="true"></i>
          <span>Crear</span>
          <md-circular-progress
            v-if="isCreating"
            indeterminate
            class="md-spinner-inline"
          ></md-circular-progress>
        </md-filled-button>
      </div>

      <div class="table-card">
        <div class="table-card__title">Listado</div>

        <div v-if="isLoading" class="users-loading">
          <md-circular-progress
            indeterminate
            class="board-loading__spinner"
          ></md-circular-progress>
        </div>

        <table v-else class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Avatar</th>
              <th>Nombre</th>
              <th>Correo</th>
              <th>Rol</th>
              <th>Creado</th>
              <th>Acciones</th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="u in users" :key="u.id">
              <td>#{{ u.id }}</td>

              <td>
                <div class="user-avatar-cell">
                  <span class="mini-avatar" :title="u.name">
                    <img
                      v-if="avatarUrlsByUserId[Number(u.id)]"
                      :src="avatarUrlsByUserId[Number(u.id)]"
                      alt=""
                    />
                    <span v-else>{{ userInitial(u) }}</span>
                  </span>

                  <label class="file-btn" title="Subir nueva foto de perfil">
                    <input
                      :ref="(el) => bindAvatarInput(u.id, el)"
                      type="file"
                      accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                      @change="(ev) => openAvatarCrop(u, ev)"
                    />

                    <md-text-button
                      type="button"
                      :disabled="Boolean(isUploadingByUserId[Number(u.id)])"
                      @click.prevent="openAvatarPicker(u.id)"
                    >
                      <i class="mdi mdi-image-edit-outline" aria-hidden="true"></i>
                      <span class="mx-1">Foto</span>
                      <md-circular-progress
                        v-if="Boolean(isUploadingByUserId[Number(u.id)])"
                        indeterminate
                        class="md-spinner-inline"
                      ></md-circular-progress>
                    </md-text-button>
                  </label>

                  <md-text-button
                    v-if="u.has_avatar"
                    type="button"
                    title="Editar foto de perfil"
                    :disabled="Boolean(isUploadingByUserId[Number(u.id)])"
                    @click.prevent="editExistingAvatar(u)"
                  >
                    <i class="mdi mdi-crop" aria-hidden="true"></i>
                    <span class="mx-1">Editar</span>
                  </md-text-button>

                  <md-icon-button
                    v-if="u.has_avatar"
                    type="button"
                    title="Eliminar foto de perfil"
                    :disabled="Boolean(isUploadingByUserId[Number(u.id)])"
                    @click="removeAvatar(u)"
                  >
                    <i class="mdi mdi-trash-can-outline" aria-hidden="true"></i>
                  </md-icon-button>
                </div>
              </td>

              <td>
                <template v-if="editingUserId === Number(u.id)">
                  <input v-model="editForm.name" class="input input--compact" />
                  <div v-if="editErrors.name" class="field__error">{{ editErrors.name }}</div>
                </template>
                <template v-else>
                  {{ u.name }}
                </template>
              </td>

              <td>
                <template v-if="editingUserId === Number(u.id)">
                  <input v-model="editForm.email" class="input input--compact" type="email" />
                  <div v-if="editErrors.email" class="field__error">{{ editErrors.email }}</div>
                </template>
                <template v-else>
                  {{ u.email }}
                </template>
              </td>

              <td>
                <div v-if="editingUserId === Number(u.id)" class="roles-editor">
                  <label
                    v-for="r in roleOptions"
                    :key="`role-${u.id}-${r.value}`"
                    class="roles-editor__item"
                  >
                    <input
                      type="checkbox"
                      :checked="editForm.role_ids.includes(Number(r.value))"
                      :disabled="Boolean(isSavingUserById[Number(u.id)])"
                      @change="toggleEditRole(r.value)"
                    />
                    <span>{{ r.label }}</span>
                  </label>

                  <div v-if="editErrors.role_ids" class="field__error">
                    {{ editErrors.role_ids }}
                  </div>
                </div>

                <div v-else class="roles-view">
                  <span
                    v-for="rid in normalizeUserRoleIds(u)"
                    :key="`rid-${u.id}-${rid}`"
                    class="roles-view__chip"
                  >
                    {{ roleNameById(rid) }}
                  </span>
                </div>
              </td>

              <td>{{ u.created_at }}</td>

              <td>
                <div v-if="editingUserId === Number(u.id)" class="roles-editor__actions">
                  <input
                    v-model="editForm.password"
                    class="input input--compact admin-users__pwd"
                    type="password"
                    placeholder="Nueva contraseña (opcional)"
                  />

                  <span v-if="editErrors.password" class="field__error">
                    {{ editErrors.password }}
                  </span>

                  <md-text-button
                    type="button"
                    :disabled="Boolean(isSavingUserById[Number(u.id)])"
                    @click="cancelEditUser"
                  >
                    Cancelar
                  </md-text-button>

                  <md-filled-button
                    type="button"
                    :disabled="Boolean(isSavingUserById[Number(u.id)])"
                    @click="saveEditUser(u)"
                  >
                    Guardar
                    <md-circular-progress
                      v-if="Boolean(isSavingUserById[Number(u.id)])"
                      indeterminate
                      class="md-spinner-inline"
                    ></md-circular-progress>
                  </md-filled-button>
                </div>

                <div v-else class="roles-editor__actions">
                  <md-icon-button type="button" title="Editar usuario" @click="startEditUser(u)">
                    <i class="mdi mdi-pencil-outline" aria-hidden="true"></i>
                  </md-icon-button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <AvatarEditorModal
        :open="avatarModal.open"
        :file="avatarModal.file"
        :image-url="avatarModal.imageUrl"
        :saving="avatarModal.isSaving"
        title="Acomodar foto de perfil"
        @close="closeAvatarModal"
        @saved="saveAvatarCrop"
        @error="(e) => toasts.push({ type: 'error', message: e?.message ?? 'No se pudo preparar la imagen.' })"
      />
    </template>
  </div>
</template>