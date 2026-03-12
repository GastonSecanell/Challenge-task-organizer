const freeze = (arr) => Object.freeze(arr.map((x) => Object.freeze(x)))

export const consultaOptions = freeze([
  { id: 1, name: 'NRO DOCUMENTO' },
  { id: 2, name: 'APELLIDO Y NOMBRES' },
])
