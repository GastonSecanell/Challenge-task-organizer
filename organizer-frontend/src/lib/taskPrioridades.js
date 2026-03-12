export const TASK_PRIORIDAD_STYLES = {
  ALTA: {
    label: 'ALTA',
    className:
      'border-[var(--task-priority-high-border)] bg-[var(--task-priority-high-bg)] text-[var(--task-priority-high-text)]',
  },
  MEDIA: {
    label: 'MEDIA',
    className:
      'border-[var(--task-priority-medium-border)] bg-[var(--task-priority-medium-bg)] text-[var(--task-priority-medium-text)]',
  },
  BAJA: {
    label: 'BAJA',
    className:
      'border-[var(--task-priority-low-border)] bg-[var(--task-priority-low-bg)] text-[var(--task-priority-low-text)]',
  },
}

export function normalizePrioridad(prioridad) {
  return String(prioridad ?? '').trim().toUpperCase()
}

export function getPrioridadConfig(prioridad) {
  const key = normalizePrioridad(prioridad)

  return TASK_PRIORIDAD_STYLES[key] ?? {
    label: key || '-',
    className:
      'border-[var(--border-default)] bg-[var(--bg-hover)] text-[var(--text-primary)]',
  }
}

export function getPrioridadLabel(prioridad) {
  return getPrioridadConfig(prioridad).label
}

export function getPrioridadClass(prioridad) {
  return getPrioridadConfig(prioridad).className
}