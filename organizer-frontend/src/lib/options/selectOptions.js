const freeze = (arr) => Object.freeze(arr.map((x) => Object.freeze(x)))

export const consultaOptions = freeze([
  { id: 1, name: 'NRO DOCUMENTO' },
  { id: 2, name: 'APELLIDO Y NOMBRES' },
])

export const estadoOptions = freeze([
  { id: 1, name: 'ACTIVO' },
  { id: 2, name: 'INACTIVO' },
])

export const yesOrNoOptions = freeze([
  { id: 1, name: 'SI' },
  { id: 2, name: 'NO' },
])

export const typeOptions = freeze([
  { id: 'frontend', name: 'Frontend' },
  { id: 'backend', name: 'Backend' },
])

export const orderMenu = freeze(
  Array.from({ length: 10 }, (_v, i) => {
    const n = i + 1
    return { id: n, name: String(n) }
  }),
)

export const typebdOptions = freeze([
  { id: 'local', name: 'Local... (http://127.0.0.1:8000)' },
  { id: 'test', name: 'Test... http://10.1.3.181/...' },
  { id: 'tapp', name: 'TAPP... https://tapp.santafe.gov.ar...' },
  { id: 'app', name: 'APP... https://app.santafe.gov.ar...' },
])