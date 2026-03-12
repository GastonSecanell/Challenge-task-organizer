<script setup>
import { computed, reactive, ref, watch } from 'vue'
import BaseModal from '@/components/ui/BaseModal.vue'
import BaseSpinner from '@/components/ui/BaseSpinner.vue'
import BaseButton from '@/components/ui/BaseButton.vue'
import { useToastStore } from '@/stores/toasts'
import { UsersApi } from '@/lib/api/users'
import { RolesApi } from '@/lib/api/roles'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  userId: {
    type: [Number, String, null],
    default: null,
  },
})

const emit = defineEmits(['close', 'saved'])

const toasts = useToastStore()

const isLoading = ref(false)
const isSaving = ref(false)

const roles = ref([])

const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role_id: '',
})

const errors = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role_id: '',
})

const isEdit = computed(() => Boolean(props.userId))

function resetForm() {
  form.name = ''
  form.email = ''
  form.password = ''
  form.password_confirmation = ''
  form.role_id = ''

  clearErrors()
}

function clearErrors() {
  errors.name = ''
  errors.email = ''
  errors.password = ''
  errors.password_confirmation = ''
  errors.role_id = ''
}

function validate() {
  clearErrors()
  let valid = true

  if (!form.name.trim()) {
    errors.name = 'El nombre es obligatorio.'
    valid = false
  }

  if (!form.email.trim()) {
    errors.email = 'El correo es obligatorio.'
    valid = false
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
    errors.email = 'Ingresá un correo válido.'
    valid = false
  }

  if (!form.role_id) {
    errors.role_id = 'Debés seleccionar un rol.'
    valid = false
  }

  if (!isEdit.value) {
    if (!form.password) {
      errors.password = 'La contraseña es obligatoria.'
      valid = false
    } else if (form.password.length < 6) {
      errors.password = 'La contraseña debe tener al menos 6 caracteres.'
      valid = false
    }

    if (!form.password_confirmation) {
      errors.password_confirmation = 'Debés confirmar la contraseña.'
      valid = false
    } else if (form.password_confirmation !== form.password) {
      errors.password_confirmation = 'Las contraseñas no coinciden.'
      valid = false
    }
  }

  if (isEdit.value && form.password) {
    if (form.password.length < 6) {
      errors.password = 'La contraseña debe tener al menos 6 caracteres.'
      valid = false
    }

    if (form.password_confirmation !== form.password) {
      errors.password_confirmation = 'Las contraseñas no coinciden.'
      valid = false
    }
  }

  return valid
}

async function loadRoles() {
  const res = await RolesApi.list()
  roles.value = res?.data ?? []
}

async function loadUser(id) {
  const res = await UsersApi.get(id)
  const user = res?.data ?? null

  if (!user) {
    throw new Error('No se pudo cargar el usuario.')
  }

  form.name = user.name || ''
  form.email = user.email || ''
  form.role_id = user.role?.id || ''
  form.password = ''
  form.password_confirmation = ''
}

async function initModal() {
  if (!props.open) return

  isLoading.value = true
  resetForm()

  try {
    await loadRoles()

    if (props.userId) {
      await loadUser(props.userId)
    }
  } catch (error) {
    toasts.error(error?.message || 'No se pudo cargar el formulario.')
    emit('close')
  } finally {
    isLoading.value = false
  }
}

async function submitUsuario() {
  if (!validate()) return

  isSaving.value = true

  try {
    const payload = {
      name: form.name,
      email: form.email,
      role_id: form.role_id,
    }

    if (form.password) {
      payload.password = form.password
      payload.password_confirmation = form.password_confirmation
    }

    if (isEdit.value) {
      const res = await UsersApi.update(props.userId, payload)
      toasts.success(res?.message || 'Usuario actualizado correctamente')
    } else {
      const res = await UsersApi.create(payload)
      toasts.success(res?.message || 'Usuario creado correctamente')
    }

    emit('saved')
  } catch (error) {
    toasts.error(error?.message || 'No se pudo guardar el usuario.')
  } finally {
    isSaving.value = false
  }
}

watch(
  () => [props.open, props.userId],
  () => {
    if (props.open) {
      initModal()
    }
  },
  { immediate: false }
)
</script>

<template>
  <BaseModal
    :open="open"
    :title="isEdit ? 'Editar usuario' : 'Nuevo usuario'"
    width-class="max-w-xl"
    @close="$emit('close')"
  >
    <div v-if="isLoading" class="flex min-h-[220px] items-center justify-center">
      <BaseSpinner size="lg" label="Cargando formulario..." />
    </div>

    <form v-else class="space-y-4" @submit.prevent="submitUsuario">
      <div>
        <label class="mb-1 block text-sm text-[var(--text-secondary)]">
          Nombre
        </label>
        <input
          v-model="form.name"
          type="text"
          class="w-full rounded-lg border border-[var(--border-default)] bg-[var(--bg-page)] px-3 py-2 text-[var(--text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]"
        >
        <p v-if="errors.name" class="mt-1 text-xs text-[var(--danger)]">
          {{ errors.name }}
        </p>
      </div>

      <div>
        <label class="mb-1 block text-sm text-[var(--text-secondary)]">
          Correo
        </label>
        <input
          v-model="form.email"
          type="email"
          class="w-full rounded-lg border border-[var(--border-default)] bg-[var(--bg-page)] px-3 py-2 text-[var(--text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]"
        >
        <p v-if="errors.email" class="mt-1 text-xs text-[var(--danger)]">
          {{ errors.email }}
        </p>
      </div>

      <div>
        <label class="mb-1 block text-sm text-[var(--text-secondary)]">
          Rol
        </label>
        <select
          v-model="form.role_id"
          class="w-full rounded-lg border border-[var(--border-default)] bg-[var(--bg-page)] px-3 py-2 text-[var(--text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]"
        >
          <option value="">Seleccionar rol</option>
          <option
            v-for="role in roles"
            :key="role.id"
            :value="role.id"
          >
            {{ role.name }}
          </option>
        </select>
        <p v-if="errors.role_id" class="mt-1 text-xs text-[var(--danger)]">
          {{ errors.role_id }}
        </p>
      </div>

      <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        <div>
          <label class="mb-1 block text-sm text-[var(--text-secondary)]">
            {{ isEdit ? 'Nueva contraseña' : 'Contraseña' }}
          </label>
          <input
            v-model="form.password"
            type="password"
            class="w-full rounded-lg border border-[var(--border-default)] bg-[var(--bg-page)] px-3 py-2 text-[var(--text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]"
          >
          <p v-if="errors.password" class="mt-1 text-xs text-[var(--danger)]">
            {{ errors.password }}
          </p>
        </div>

        <div>
          <label class="mb-1 block text-sm text-[var(--text-secondary)]">
            Confirmar contraseña
          </label>
          <input
            v-model="form.password_confirmation"
            type="password"
            class="w-full rounded-lg border border-[var(--border-default)] bg-[var(--bg-page)] px-3 py-2 text-[var(--text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--accent)]"
          >
          <p v-if="errors.password_confirmation" class="mt-1 text-xs text-[var(--danger)]">
            {{ errors.password_confirmation }}
          </p>
        </div>
      </div>
    </form>

    <template #footer>
      <BaseButton variant="ghost" @click="$emit('close')">
        Cancelar
      </BaseButton>

      <BaseButton :disabled="isSaving" @click="submitUsuario">
        <BaseSpinner v-if="isSaving" size="sm" />
        <span>{{ isSaving ? 'Guardando...' : 'Guardar' }}</span>
      </BaseButton>
    </template>
  </BaseModal>
</template>