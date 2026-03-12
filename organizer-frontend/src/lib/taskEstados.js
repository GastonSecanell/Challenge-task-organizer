export const DEFAULT_TASK_ESTADO = 'pendiente'

export const TASK_ESTADOS = [
  {
    value: 'pendiente',
    label: 'Pendiente',
    shortLabel: 'PENDIENTE',
    className:
      'border-[var(--task-status-pending-border)] bg-[var(--task-status-pending-bg)] text-[var(--task-status-pending-text)]',
  },
  {
    value: 'en_progreso',
    label: 'En progreso',
    shortLabel: 'EN PROGRESO',
    className:
      'border-[var(--task-status-progress-border)] bg-[var(--task-status-progress-bg)] text-[var(--task-status-progress-text)]',
  },
  {
    value: 'completada',
    label: 'Completada',
    shortLabel: 'COMPLETADA',
    className:
      'border-[var(--task-status-done-border)] bg-[var(--task-status-done-bg)] text-[var(--task-status-done-text)]',
  },
]
export const TASK_ESTADO_FILTER_OPTIONS = [
  { value: '', label: 'Todos los estados' },
  ...TASK_ESTADOS.map((item) => ({
    value: item.value,
    label: item.label,
  })),
]

export function normalizeEstado(estado) {
  return String(estado ?? '').trim().toLowerCase()
}

export function getEstadoConfig(estado) {
  const key = normalizeEstado(estado)

  return (
    TASK_ESTADOS.find((item) => item.value === key) ?? {
      value: key,
      label: estado || '-',
      shortLabel: String(estado ?? '-').toUpperCase(),
      className:
        'border-[var(--border-default)] bg-[var(--bg-hover)] text-[var(--text-secondary)]',
    }
  )
}

export function getEstadoLabel(estado) {
  return getEstadoConfig(estado).label
}

export function getEstadoShortLabel(estado) {
  return getEstadoConfig(estado).shortLabel
}

export function getEstadoClass(estado) {
  return getEstadoConfig(estado).className
}

export function isValidEstado(estado) {
  const key = normalizeEstado(estado)
  return TASK_ESTADOS.some((item) => item.value === key)
}