<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import BaseButton from '@/components/ui/BaseButton.vue'
import UsersTable from '@/components/users/UsersTable.vue'
import BasePagination from '@/components/ui/BasePagination.vue'
import UserFormModal from '@/components/users/UserFormModal.vue'
import ConfirmActionModal from '@/components/ui/ConfirmActionModal.vue'
import { useToastStore } from '@/stores/toasts'
import { useAuthStore } from '@/stores/auth'
import { UsersApi } from '@/lib/api/users'
import { RolesApi } from '@/lib/api/roles'

const router = useRouter()
const auth = useAuthStore()
const toasts = useToastStore()

const items = ref([])
const roles = ref([])
const loading = ref(true)

const busqueda = ref('')

const filtros = ref({
  name: '',
  email: '',
  role_id: '',
  ordenar_por: 'id',
  direccion: 'desc',
})

const paginacion = ref({
  pagina_actual: 1,
  por_pagina: 10,
  total: 0,
  ultima_pagina: 1,
  desde: 0,
  hasta: 0,
})

const showFilters = ref(false)

const modalOpen = ref(false)
const selectedId = ref(null)

const confirmDeleteOpen = ref(false)
const deleting = ref(false)
const selectedUserToDelete = ref(null)

async function fetchRoles() {
  try {
    const res = await RolesApi.list()
    roles.value = res?.data ?? []
  } catch {
    toasts.error('No se pudieron cargar los roles.')
  }
}

async function fetchUsers() {
  loading.value = true

  try {
    const params = {
      pagina: paginacion.value.pagina_actual,
      por_pagina: paginacion.value.por_pagina,
      busqueda: busqueda.value || undefined,
      name: filtros.value.name || undefined,
      email: filtros.value.email || undefined,
      role_id: filtros.value.role_id || undefined,
      ordenar_por: filtros.value.ordenar_por,
      direccion: filtros.value.direccion,
    }

    const res = await UsersApi.list(params)

    items.value = res?.data ?? []
    paginacion.value = {
      pagina_actual: Number(res?.paginacion?.pagina_actual ?? 1),
      por_pagina: Number(res?.paginacion?.por_pagina ?? 10),
      total: Number(res?.paginacion?.total ?? 0),
      ultima_pagina: Number(res?.paginacion?.ultima_pagina ?? 1),
      desde: Number(res?.paginacion?.desde ?? 0),
      hasta: Number(res?.paginacion?.hasta ?? 0),
    }
  } catch {
    toasts.error('No se pudieron cargar los usuarios.')
  } finally {
    loading.value = false
  }
}

function updateSearch(value) {
  busqueda.value = value
  paginacion.value.pagina_actual = 1
  fetchUsers()
}

function updateFilters(value) {
  filtros.value = value
  paginacion.value.pagina_actual = 1
  fetchUsers()
}

function updateShowFilters(value) {
  showFilters.value = value
}

function resetFilters() {
  busqueda.value = ''
  filtros.value = {
    name: '',
    email: '',
    role_id: '',
    ordenar_por: 'id',
    direccion: 'desc',
  }
  paginacion.value.pagina_actual = 1
  fetchUsers()
}

function updateSort(column) {
  if (filtros.value.ordenar_por === column) {
    filtros.value.direccion =
      filtros.value.direccion === 'asc' ? 'desc' : 'asc'
  } else {
    filtros.value.ordenar_por = column
    filtros.value.direccion = 'asc'
  }

  paginacion.value.pagina_actual = 1
  fetchUsers()
}

async function updatePage(value) {
  if (value < 1 || value > paginacion.value.ultima_pagina) return
  paginacion.value.pagina_actual = value
  await fetchUsers()
}

async function updatePerPage(value) {
  paginacion.value.por_pagina = value
  paginacion.value.pagina_actual = 1
  await fetchUsers()
}

function openCreate() {
  if (!auth.canManageUsers) return
  selectedId.value = null
  modalOpen.value = true
}

function openEdit(user) {
  if (!auth.canManageUsers) return
  selectedId.value = user.id
  modalOpen.value = true
}

function closeModal() {
  modalOpen.value = false
  selectedId.value = null
}

async function handleSaved() {
  closeModal()
  await fetchUsers()
}

function handleDelete(user) {
  if (!auth.canManageUsers) return
  selectedUserToDelete.value = user
  confirmDeleteOpen.value = true
}

function closeDeleteModal() {
  if (deleting.value) return
  confirmDeleteOpen.value = false
  selectedUserToDelete.value = null
}

async function confirmDelete() {
  if (!auth.canManageUsers) return
  if (!selectedUserToDelete.value?.id) return

  deleting.value = true

  try {
    const res = await UsersApi.remove(selectedUserToDelete.value.id)

    confirmDeleteOpen.value = false
    selectedUserToDelete.value = null

    toasts.success(res?.message || 'Usuario eliminado correctamente')
    await fetchUsers()
  } catch {
    toasts.error('No se pudo eliminar el usuario.')
  } finally {
    deleting.value = false
  }
}

onMounted(async () => {
  if (!auth.canViewUsers) {
    router.replace('/tareas')
    return
  }

  await fetchRoles()
  await fetchUsers()
})
</script>

<template>
  <section class="space-y-4">
    <div class="flex items-center justify-end">
      <BaseButton v-if="auth.canManageUsers" @click="openCreate">
        Nuevo usuario
      </BaseButton>
    </div>

    <UsersTable
      :users="items"
      :roles="roles"
      :loading="loading"
      :page="paginacion.pagina_actual"
      :per-page="paginacion.por_pagina"
      :total="paginacion.total"
      :search="busqueda"
      :filters="filtros"
      :show-filters="showFilters"
      :can-manage-users="auth.canManageUsers"
      @update:search="updateSearch"
      @update:filters="updateFilters"
      @update:showFilters="updateShowFilters"
      @resetFilters="resetFilters"
      @sort="updateSort"
      @edit="openEdit"
      @delete="handleDelete"
    />

    <BasePagination
      :page="paginacion.pagina_actual"
      :per-page="paginacion.por_pagina"
      :total="paginacion.total"
      @update:page="updatePage"
      @update:perPage="updatePerPage"
    />

    <UserFormModal
      v-if="auth.canManageUsers"
      :open="modalOpen"
      :user-id="selectedId"
      @close="closeModal"
      @saved="handleSaved"
    />

    <ConfirmActionModal
      :open="confirmDeleteOpen"
      :is-loading="deleting"
      title="Eliminar usuario"
      :message="`Se eliminará el usuario '${selectedUserToDelete?.name || ''}'. Esta acción no se puede deshacer.`"
      confirm-text="Eliminar"
      cancel-text="Cancelar"
      @close="closeDeleteModal"
      @confirm="confirmDelete"
    />
  </section>
</template>