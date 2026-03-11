export const ETIQUETA_STYLES = {
  DEV: {
    badge: 'border-blue-500/30 bg-blue-500/10 text-blue-300',
    chip: 'border-blue-500/30 bg-blue-500/10 text-blue-300',
    checkbox: 'accent-blue-500',
  },
  QA: {
    badge: 'border-yellow-500/30 bg-yellow-500/10 text-yellow-300',
    chip: 'border-yellow-500/30 bg-yellow-500/10 text-yellow-300',
    checkbox: 'accent-yellow-500',
  },
  RRHH: {
    badge: 'border-violet-500/30 bg-violet-500/10 text-violet-300',
    chip: 'border-violet-500/30 bg-violet-500/10 text-violet-300',
    checkbox: 'accent-violet-500',
  },
}

function normalizeEtiqueta(nombre) {
  return String(nombre ?? '').trim().toUpperCase()
}

export function getEtiquetaStyle(nombre) {
  const key = normalizeEtiqueta(nombre)

  return ETIQUETA_STYLES[key] ?? {
    badge: 'border-[var(--border-default)] bg-[var(--bg-page)] text-[var(--text-primary)]',
    chip: 'border-[var(--border-default)] bg-[var(--bg-page)] text-[var(--text-primary)]',
    checkbox: 'accent-[var(--accent)]',
  }
}