<script setup>
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { AuditApi } from '@/lib/api/audit'

const auth = useAuthStore()

const logs = ref([])
const error = ref(null)
const isLoading = ref(false)
const search = ref('')

const canUse = computed(() => auth.canReadAudit)

function formatDate(value) {
  if (!value) return '—'
  try {
    return new Date(value).toLocaleString('es-AR', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    })
  } catch {
    return value
  }
}

function formatPayload(payload) {
  try {
    return JSON.stringify(payload ?? {}, null, 2)
  } catch {
    return String(payload ?? '')
  }
}

function entityLabel(log) {
  const type = log?.entity_type ?? '—'
  const id = log?.entity_id ?? '—'
  return `${type} #${id}`
}

function actionLabel(action) {
  return String(action || '—').replaceAll('_', ' ')
}

const filteredLogs = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return logs.value

  return logs.value.filter((l) => {
    const user = String(l?.user?.email ?? '').toLowerCase()
    const action = String(l?.action ?? '').toLowerCase()
    const entity = String(l?.entity_type ?? '').toLowerCase()
    const entityId = String(l?.entity_id ?? '').toLowerCase()
    const payload = formatPayload(l?.payload).toLowerCase()

    return (
      user.includes(q) ||
      action.includes(q) ||
      entity.includes(q) ||
      entityId.includes(q) ||
      payload.includes(q)
    )
  })
})

async function load() {
  if (!canUse.value) return

  isLoading.value = true
  error.value = null
  try {
    const json = await AuditApi.getLogs()
    logs.value = json.data ?? []
  } catch (e) {
    error.value = e?.message ?? String(e)
  } finally {
    isLoading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <div class="page__header">
      <div>
        <div class="page__title">Auditoría</div>
        <div class="page__subtitle">Últimos eventos</div>
      </div>
    </div>

    <div v-if="!canUse" class="alert alert--danger">
      No tenés permisos para ver esta sección.
    </div>

    <template v-else>
      <div v-if="error" class="alert alert--danger">{{ error }}</div>

      <div class="table-card">
        <div class="table-card__head">
          <div class="table-card__title-wrap">
            <div class="table-card__title">Logs</div>
            <div class="table-card__subtitle">
              {{ filteredLogs.length }} registro<span v-if="filteredLogs.length !== 1">s</span>
            </div>
          </div>

          <div class="table-card__filters">
            <input
              v-model="search"
              type="text"
              class="input input--sm audit-search"
              placeholder="Buscar por usuario, acción, entidad o payload…"
            />
          </div>
        </div>

        <div v-if="isLoading" class="table-card__loading">
          <md-circular-progress
            indeterminate
            class="md-spinner-inline-big"
          ></md-circular-progress>
          <!-- <div class="muted">Cargando logs…</div> -->
        </div>

        <div v-else-if="filteredLogs.length === 0" class="table-card__empty">
          <div class="muted">
            {{ search ? 'No hay resultados para la búsqueda.' : 'No hay logs para mostrar.' }}
          </div>
        </div>

        <div v-else class="table-wrap">
          <table class="table table--audit">
            <thead>
              <tr>
                <th class="col-date">Fecha</th>
                <th class="col-user">Usuario</th>
                <th class="col-action">Acción</th>
                <th class="col-entity">Entidad</th>
                <th class="col-payload">Payload</th>
              </tr>
            </thead>

            <tbody>
              <tr v-for="l in filteredLogs" :key="l.id">
                <td>
                  <div class="audit-date">{{ formatDate(l.created_at) }}</div>
                </td>

                <td>
                  <div class="audit-user">{{ l.user?.email ?? '—' }}</div>
                </td>

                <td>
                  <span class="audit-badge">
                    {{ actionLabel(l.action) }}
                  </span>
                </td>

                <td>
                  <span class="audit-entity">
                    {{ entityLabel(l) }}
                  </span>
                </td>

                <td>
                  <pre class="audit-payload">{{ formatPayload(l.payload) }}</pre>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>