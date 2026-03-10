import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

function getPopoverRoom(el) {
  const node = el?.$el ?? el
  const rect = node?.getBoundingClientRect?.()
  if (!rect) return { above: 0, below: 0 }

  const bodyRect =
    node?.closest?.('.modal__body')?.getBoundingClientRect?.() ?? {
      top: 0,
      bottom: window.innerHeight,
    }

  const below = Math.max(0, bodyRect.bottom - rect.bottom)
  const above = Math.max(0, rect.top - bodyRect.top)
  return { above, below }
}

function shouldOpenPopoverUp(el, preferredHeight = 340) {
  const { above, below } = getPopoverRoom(el)
  return below < preferredHeight && above > below
}

/**
 * usePopoverPlacement
 * Maneja: isOpen, openUp, maxHeight, toggle(), refresh()
 * + listeners resize/scroll opcionales
 */
export function usePopoverPlacement({
  triggerElRef,
  preferredHeight = 340,
  minHeight = 200,
  maxHeightCap = 460,
  padding = 12,
  enableGlobalListeners = true,
} = {}) {
  const isOpen = ref(false)
  const openUp = ref(false)
  const maxHeight = ref(preferredHeight)

  function compute() {
    const el = triggerElRef?.value
    if (!el) return

    const { above, below } = getPopoverRoom(el)
    openUp.value = shouldOpenPopoverUp(el, preferredHeight)

    const room = openUp.value ? above : below
    maxHeight.value = Math.max(
      minHeight,
      Math.min(maxHeightCap, Math.floor(room - padding)),
    )
  }

  async function open() {
    isOpen.value = true
    await nextTick()
    compute()
  }

  function close() {
    isOpen.value = false
  }

  async function toggle() {
    if (isOpen.value) {
      close()
      return
    }
    await open()
  }

  async function refresh() {
    if (!isOpen.value) return
    await nextTick()
    compute()
  }

  // si abre/cierra, recalcular al abrir
  watch(isOpen, async (val) => {
    if (!val) return
    await nextTick()
    compute()
  })

  function onGlobal() {
    // solo refresca si está abierto
    if (!isOpen.value) return
    compute()
  }

  onMounted(() => {
    if (!enableGlobalListeners) return
    window.addEventListener('resize', onGlobal)
    window.addEventListener('scroll', onGlobal, true)
  })

  onBeforeUnmount(() => {
    if (!enableGlobalListeners) return
    window.removeEventListener('resize', onGlobal)
    window.removeEventListener('scroll', onGlobal, true)
  })

  return {
    isOpen,
    openUp,
    maxHeight,
    open,
    close,
    toggle,
    refresh,
  }
}