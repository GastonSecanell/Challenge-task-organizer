const freeze = (arr) => Object.freeze(arr.map((x) => Object.freeze(x)))

export const coverColors = freeze([
  { name: 'Azul', value: '#1d4ed8' },
  { name: 'Celeste', value: '#0ea5e9' },
  { name: 'Verde', value: '#16a34a' },
  { name: 'Lima', value: '#84cc16' },
  { name: 'Amarillo', value: '#f59e0b' },
  { name: 'Naranja', value: '#f97316' },
  { name: 'Rojo', value: '#ef4444' },
  { name: 'Rosa', value: '#ec4899' },
  { name: 'Violeta', value: '#8b5cf6' },
  { name: 'Gris', value: '#6b7280' },
])

export const quillToolbar = Object.freeze([
  ['bold', 'italic', 'underline', 'strike'],
  [{ header: 1 }, { header: 2 }],
  [{ list: 'ordered' }, { list: 'bullet' }],
  ['link', 'blockquote', 'code-block'],
  [{ color: [] }, { background: [] }],
  ['clean'],
])