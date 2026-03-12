export const events = new EventTarget()

export function emitUnauthorized() {
  events.dispatchEvent(new Event('unauthorized'))
}