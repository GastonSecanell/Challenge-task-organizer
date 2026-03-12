function isValidDate(date) {
  return date instanceof Date && !Number.isNaN(date.getTime())
}

function buildLocalDate(year, month, day, hours = 0, minutes = 0, seconds = 0) {
  const parsed = new Date(year, month - 1, day, hours, minutes, seconds)

  if (
    parsed.getFullYear() !== year ||
    parsed.getMonth() !== month - 1 ||
    parsed.getDate() !== day ||
    parsed.getHours() !== hours ||
    parsed.getMinutes() !== minutes ||
    parsed.getSeconds() !== seconds
  ) {
    return null
  }

  return parsed
}

export function parseLocalDateOnly(value) {
  if (!value) return null

  if (value instanceof Date) {
    return isValidDate(value) ? new Date(value) : null
  }

  const str = String(value).trim()

  // Caso "DD/MM/YYYY"
  let match = str.match(/^(\d{2})\/(\d{2})\/(\d{4})$/)
  if (match) {
    const [, day, month, year] = match
    return buildLocalDate(Number(year), Number(month), Number(day))
  }

  // Caso "DD/MM/YYYY HH:mm" o "DD/MM/YYYY HH:mm:ss"
  match = str.match(/^(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})(?::(\d{2}))?$/)
  if (match) {
    const [, day, month, year, hours, minutes, seconds] = match
    return buildLocalDate(
      Number(year),
      Number(month),
      Number(day),
      Number(hours),
      Number(minutes),
      Number(seconds ?? 0)
    )
  }

  // Caso "YYYY-MM-DD" -> forzar local
  if (/^\d{4}-\d{2}-\d{2}$/.test(str)) {
    const [year, month, day] = str.split('-').map(Number)
    return buildLocalDate(year, month, day)
  }

  // Caso "YYYY-MM-DD HH:mm" o "YYYY-MM-DD HH:mm:ss"
  if (/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/.test(str)) {
    const normalized = str.replace(' ', 'T')
    const parsed = new Date(normalized)
    return isValidDate(parsed) ? parsed : null
  }

  // Caso ISO u otros parseables
  const parsed = new Date(str)
  return isValidDate(parsed) ? parsed : null
}

export function startOfDay(date = new Date()) {
  const d = new Date(date)
  d.setHours(0, 0, 0, 0)
  return d
}

export function formatFechaAR(value) {
  const date = parseLocalDateOnly(value)
  if (!date) return '-'

  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = date.getFullYear()

  return `${day}/${month}/${year}`
}

export function formatFechaHoraAR(value) {
  const date = parseLocalDateOnly(value)
  if (!date) return '-'

  const day = String(date.getDate()).padStart(2, '0')
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const year = date.getFullYear()
  const hours = String(date.getHours()).padStart(2, '0')
  const minutes = String(date.getMinutes()).padStart(2, '0')

  return `${day}/${month}/${year} ${hours}:${minutes}`
}

export function diffDaysFromToday(value) {
  const target = parseLocalDateOnly(value)
  if (!target) return null

  const today = startOfDay(new Date())
  const due = startOfDay(target)

  return Math.ceil((due.getTime() - today.getTime()) / 86400000)
}

export function isOverdue(value) {
  const days = diffDaysFromToday(value)
  return days !== null && days < 0
}

export function getDueDateTone(value) {
  const days = diffDaysFromToday(value)

  if (days === null) return 'neutral'
  if (days < 0) return 'overdue'
  if (days <= 1) return 'urgent'
  if (days <= 3) return 'near'
  if (days <= 7) return 'medium'
  return 'far'
}

export function getDueDateClass(value) {
  const tone = getDueDateTone(value)

  if (tone === 'overdue') {
    return 'border border-[var(--task-due-overdue-border)] bg-[var(--task-due-overdue-bg)] text-[var(--task-due-overdue-text)]'
  }

  if (tone === 'urgent') {
    return 'border border-[var(--task-due-urgent-border)] bg-[var(--task-due-urgent-bg)] text-[var(--task-due-urgent-text)]'
  }

  if (tone === 'near') {
    return 'border border-[var(--task-due-near-border)] bg-[var(--task-due-near-bg)] text-[var(--task-due-near-text)]'
  }

  if (tone === 'medium') {
    return 'border border-[var(--task-due-medium-border)] bg-[var(--task-due-medium-bg)] text-[var(--task-due-medium-text)]'
  }

  if (tone === 'far') {
    return 'border border-[var(--task-due-far-border)] bg-[var(--task-due-far-bg)] text-[var(--task-due-far-text)]'
  }

  return 'text-[var(--text-secondary)]'
}

export function getDueDateLabel(value) {
  if (!value) return '-'

  const days = diffDaysFromToday(value)
  const fecha = formatFechaAR(value)

  if (days === null) return fecha
  if (days < 0) return `${fecha} · Vencida`
  if (days === 0) return `${fecha} · Hoy`
  if (days === 1) return `${fecha} · Mañana`

  return `${fecha} · ${days} días`
}