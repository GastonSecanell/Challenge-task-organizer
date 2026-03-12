export const TASK_ETIQUETA_STYLES = {
  DEV: {
    label: 'DEV',
    badge:
      'border-[var(--task-label-blue-border)] bg-[var(--task-label-blue-bg)] text-[var(--task-label-blue-text)]',
    chip:
      'border-[var(--task-label-blue-border)] bg-[var(--task-label-blue-bg)] text-[var(--task-label-blue-text)]',
    checkbox: 'accent-[var(--task-label-blue-accent)]',
  },
  QA: {
    label: 'QA',
    badge:
      'border-[var(--task-label-yellow-border)] bg-[var(--task-label-yellow-bg)] text-[var(--task-label-yellow-text)]',
    chip:
      'border-[var(--task-label-yellow-border)] bg-[var(--task-label-yellow-bg)] text-[var(--task-label-yellow-text)]',
    checkbox: 'accent-[var(--task-label-yellow-accent)]',
  },
  RRHH: {
    label: 'RRHH',
    badge:
      'border-[var(--task-label-violet-border)] bg-[var(--task-label-violet-bg)] text-[var(--task-label-violet-text)]',
    chip:
      'border-[var(--task-label-violet-border)] bg-[var(--task-label-violet-bg)] text-[var(--task-label-violet-text)]',
    checkbox: 'accent-[var(--task-label-violet-accent)]',
  },
}

export function normalizeEtiqueta(nombre) {
  return String(nombre ?? '').trim().toUpperCase()
}

export function getEtiquetaConfig(nombre) {
  const key = normalizeEtiqueta(nombre)

  return TASK_ETIQUETA_STYLES[key] ?? {
    label: key || '-',
    badge:
      'border-[var(--border-default)] bg-[var(--bg-page)] text-[var(--text-primary)]',
    chip:
      'border-[var(--border-default)] bg-[var(--bg-page)] text-[var(--text-primary)]',
    checkbox: 'accent-[var(--accent)]',
  }
}

export function getEtiquetaStyle(nombre) {
  return getEtiquetaConfig(nombre)
}