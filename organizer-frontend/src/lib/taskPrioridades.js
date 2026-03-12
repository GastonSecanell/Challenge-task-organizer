function normalizePrioridad(prioridad) {
  return String(prioridad ?? '').trim().toUpperCase()
}

export function getPrioridadLabel(prioridad) {
  const value = normalizePrioridad(prioridad)
  return value || '-'
}

export function getPrioridadClass(prioridad) {
  const value = normalizePrioridad(prioridad)

  if (value === 'ALTA') {
    return 'border-[var(--task-priority-high-border)] bg-[var(--task-priority-high-bg)] text-[var(--task-priority-high-text)]'
  }

  if (value === 'MEDIA') {
    return 'border-[var(--task-priority-medium-border)] bg-[var(--task-priority-medium-bg)] text-[var(--task-priority-medium-text)]'
  }

  return 'border-[var(--task-priority-low-border)] bg-[var(--task-priority-low-bg)] text-[var(--task-priority-low-text)]'
}