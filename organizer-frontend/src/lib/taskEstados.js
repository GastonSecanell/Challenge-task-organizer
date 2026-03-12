export const TASK_ESTADO_VALUES = Object.freeze({
  PENDIENTE: 'pendiente',
  EN_PROGRESO: 'en_progreso',
  COMPLETADA: 'completada',
})

export const TASK_ESTADOS = Object.freeze([
  {
    value: TASK_ESTADO_VALUES.PENDIENTE,
    label: 'PENDIENTE',
    className:
      'border-[var(--task-status-pending-border)] bg-[var(--task-status-pending-bg)] text-[var(--task-status-pending-text)]',
  },
  {
    value: TASK_ESTADO_VALUES.EN_PROGRESO,
    label: 'EN PROGRESO',
    className:
      'border-[var(--task-status-progress-border)] bg-[var(--task-status-progress-bg)] text-[var(--task-status-progress-text)]',
  },
  {
    value: TASK_ESTADO_VALUES.COMPLETADA,
    label: 'COMPLETADA',
    className:
      'border-[var(--task-status-done-border)] bg-[var(--task-status-done-bg)] text-[var(--task-status-done-text)]',
  },
])

function normalizeEstado(estado) {
  return String(estado ?? '').trim().toLowerCase()
}

function findEstado(estado) {
  const value = normalizeEstado(estado)
  return TASK_ESTADOS.find((item) => item.value === value) ?? TASK_ESTADOS[0]
}

export function getEstadoLabel(estado) {
  return findEstado(estado).label
}

export function getEstadoClass(estado) {
  return findEstado(estado).className
}

export function isValidEstado(estado) {
  const value = normalizeEstado(estado)
  return TASK_ESTADOS.some((item) => item.value === value)
}