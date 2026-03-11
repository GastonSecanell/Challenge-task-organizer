<script setup>
import { onMounted, ref } from "vue";
import BaseButton from "@/components/ui/BaseButton.vue";
import UsersTable from "@/components/users/UsersTable.vue";
import BasePagination from "@/components/ui/BasePagination.vue";
import UserFormModal from "@/components/users/UserFormModal.vue";
import { useToastStore } from "@/stores/toasts";
import { UsersApi } from "@/lib/api/users";
import { RolesApi } from "@/lib/api/roles";

const toasts = useToastStore();

const items = ref([]);
const roles = ref([]);
const loading = ref(true);

const busqueda = ref("");

const filtros = ref({
  name: "",
  email: "",
  role_id: "",
  ordenar_por: "id",
  direccion: "desc",
});

const paginacion = ref({
  pagina_actual: 1,
  por_pagina: 10,
  total: 0,
  ultima_pagina: 1,
  desde: 0,
  hasta: 0,
});

const showFilters = ref(false);

const modalOpen = ref(false);
const selectedId = ref(null);

async function fetchRoles() {
  try {
    const res = await RolesApi.list();
    roles.value = res?.data ?? [];
  } catch {
    toasts.error("No se pudieron cargar los roles.");
  }
}

async function fetchUsers() {
  loading.value = true;

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
    };

    const res = await UsersApi.list(params);

    items.value = res?.data ?? [];
    paginacion.value = {
      pagina_actual: Number(res?.paginacion?.pagina_actual ?? 1),
      por_pagina: Number(res?.paginacion?.por_pagina ?? 10),
      total: Number(res?.paginacion?.total ?? 0),
      ultima_pagina: Number(res?.paginacion?.ultima_pagina ?? 1),
      desde: Number(res?.paginacion?.desde ?? 0),
      hasta: Number(res?.paginacion?.hasta ?? 0),
    };
  } catch (error) {
    toasts.error("No se pudieron cargar los usuarios.");
  } finally {
    loading.value = false;
  }
}

function updateSearch(value) {
  busqueda.value = value;
  paginacion.value.pagina_actual = 1;
  fetchUsers();
}

function updateFilters(value) {
  filtros.value = value;
  paginacion.value.pagina_actual = 1;
  fetchUsers();
}

function updateShowFilters(value) {
  showFilters.value = value;
}

function resetFilters() {
  busqueda.value = "";
  filtros.value = {
    name: "",
    email: "",
    role_id: "",
    ordenar_por: "id",
    direccion: "desc",
  };
  paginacion.value.pagina_actual = 1;
  fetchUsers();
}

function updateSort(column) {
  if (filtros.value.ordenar_por === column) {
    filtros.value.direccion = filtros.value.direccion === 'asc' ? 'desc' : 'asc';
  } else {
    filtros.value.ordenar_por = column;
    filtros.value.direccion = 'asc';
  }

  paginacion.value.pagina_actual = 1;
  fetchUsers();
}

async function updatePage(value) {
  if (value < 1 || value > paginacion.value.ultima_pagina) return;
  paginacion.value.pagina_actual = value;
  await fetchUsers();
}

async function updatePerPage(value) {
  paginacion.value.por_pagina = value;
  paginacion.value.pagina_actual = 1;
  await fetchUsers();
}

function openCreate() {
  selectedId.value = null;
  modalOpen.value = true;
}

function openEdit(user) {
  selectedId.value = user.id;
  modalOpen.value = true;
}

function closeModal() {
  modalOpen.value = false;
  selectedId.value = null;
}

async function handleSaved() {
  closeModal();
  await fetchUsers();
}

async function handleDelete(user) {
  const ok = window.confirm(`¿Eliminar a ${user.name}?`);
  if (!ok) return;

  try {
    await UsersApi.remove(user.id);
    toasts.success("Usuario eliminado correctamente.");
    await fetchUsers();
  } catch {
    toasts.error("No se pudo eliminar el usuario.");
  }
}

onMounted(async () => {
  await fetchRoles();
  await fetchUsers();
});
</script>

<template>
  <section class="space-y-4">
    <div class="flex items-center justify-end">
      <BaseButton @click="openCreate">Nuevo usuario</BaseButton>
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
      :open="modalOpen"
      :user-id="selectedId"
      @close="closeModal"
      @saved="handleSaved"
    />
  </section>
</template>