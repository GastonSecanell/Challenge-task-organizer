<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useToastStore } from '@/stores/toasts'
import { AuthApi } from '@/lib/api/auth'

const props = defineProps({
  isOpen: { type: Boolean, default: false },
})

const emit = defineEmits(['close', 'changed'])

const toasts = useToastStore()

const isSaving = ref(false)
const showCurrent = ref(false)
const showNext = ref(false)
const showConfirm = ref(false)

const form = reactive({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
})

const errors = reactive({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
})

function resetForm() {
  form.current_password = ''
  form.new_password = ''
  form.new_password_confirmation = ''

  errors.current_password = ''
  errors.new_password = ''
  errors.new_password_confirmation = ''

  isSaving.value = false
  showCurrent.value = false
  showNext.value = false
  showConfirm.value = false
}

watch(
  () => props.isOpen,
  (open) => {
    if (open) resetForm()
  },
)

function close() {
  if (isSaving.value) return
  emit('close')
}

const canSubmit = computed(() => {
  return (
    form.current_password.trim() &&
    form.new_password.trim() &&
    form.new_password_confirmation.trim()
  )
})

function validate() {
  errors.current_password = ''
  errors.new_password = ''
  errors.new_password_confirmation = ''

  let ok = true

  if (!form.current_password.trim()) {
    errors.current_password = 'Ingresá tu contraseña actual.'
    ok = false
  }

  if (!form.new_password.trim()) {
    errors.new_password = 'Ingresá la nueva contraseña.'
    ok = false
  } else if (form.new_password.length < 8) {
    errors.new_password = 'La nueva contraseña debe tener al menos 8 caracteres.'
    ok = false
  }

  if (!form.new_password_confirmation.trim()) {
    errors.new_password_confirmation = 'Confirmá la nueva contraseña.'
    ok = false
  } else if (form.new_password !== form.new_password_confirmation) {
    errors.new_password_confirmation = 'Las contraseñas no coinciden.'
    ok = false
  }

  if (
    form.current_password &&
    form.new_password &&
    form.current_password === form.new_password
  ) {
    errors.new_password = 'La nueva contraseña no puede ser igual a la actual.'
    ok = false
  }

  return ok
}

async function submit() {
  if (isSaving.value) return
  if (!validate()) return

  isSaving.value = true
  try {
    await AuthApi.changePassword({
      current_password: form.current_password,
      new_password: form.new_password,
      new_password_confirmation: form.new_password_confirmation,
    })

    emit('changed')
  } catch (e) {
    const message = e?.message ?? 'No se pudo cambiar la contraseña.'

    if (/actual/i.test(message) || /incorrecta/i.test(message)) {
      errors.current_password = message
    } else {
      toasts.push({ type: 'error', message })
    }
  } finally {
    isSaving.value = false
  }
}
</script>

<template>
  <div v-if="isOpen" class="user-modal-backdrop" @click.self="close">
    <div class="user-modal">
      <div class="user-modal__header">
        <div class="user-modal__title">Cambiar contraseña</div>

        <md-icon-button type="button" title="Cerrar" :disabled="isSaving" @click="close">
          <i class="mdi mdi-close" aria-hidden="true"></i>
        </md-icon-button>
      </div>

      <div class="user-modal__body">
        <div class="user-modal__field">
          <label class="user-modal__label">Contraseña actual</label>
          <div class="user-modal__input-wrap">
            <input
              :type="showCurrent ? 'text' : 'password'"
              v-model="form.current_password"
              class="user-modal__input"
              placeholder="Ingresá tu contraseña actual"
            />
            <md-icon-button
              type="button"
              class="user-modal__toggle"
              @click="showCurrent = !showCurrent"
            >
              <i class="mdi" :class="showCurrent ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"></i>
            </md-icon-button>
          </div>
          <div v-if="errors.current_password" class="user-modal__error">
            {{ errors.current_password }}
          </div>
        </div>

        <div class="user-modal__field">
          <label class="user-modal__label">Nueva contraseña</label>
          <div class="user-modal__input-wrap">
            <input
              :type="showNext ? 'text' : 'password'"
              v-model="form.new_password"
              class="user-modal__input"
              placeholder="Ingresá la nueva contraseña"
            />
            <md-icon-button
              type="button"
              class="user-modal__toggle"
              @click="showNext = !showNext"
            >
              <i class="mdi" :class="showNext ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"></i>
            </md-icon-button>
          </div>
          <div v-if="errors.new_password" class="user-modal__error">
            {{ errors.new_password }}
          </div>
        </div>

        <div class="user-modal__field">
          <label class="user-modal__label">Confirmar nueva contraseña</label>
          <div class="user-modal__input-wrap">
            <input
              :type="showConfirm ? 'text' : 'password'"
              v-model="form.new_password_confirmation"
              class="user-modal__input"
              placeholder="Repetí la nueva contraseña"
            />
            <md-icon-button
              type="button"
              class="user-modal__toggle"
              @click="showConfirm = !showConfirm"
            >
              <i class="mdi" :class="showConfirm ? 'mdi-eye-off-outline' : 'mdi-eye-outline'"></i>
            </md-icon-button>
          </div>
          <div v-if="errors.new_password_confirmation" class="user-modal__error">
            {{ errors.new_password_confirmation }}
          </div>
        </div>
      </div>

      <div class="user-modal__footer">
        <md-text-button type="button" :disabled="isSaving" @click="close">
          Cancelar
        </md-text-button>

        <md-filled-button type="button" :disabled="isSaving || !canSubmit" @click="submit">
          <span>Actualizar contraseña</span>
          <md-circular-progress
            v-if="isSaving"
            indeterminate
            class="md-spinner-inline"
          ></md-circular-progress>
        </md-filled-button>
      </div>
    </div>
  </div>
</template>