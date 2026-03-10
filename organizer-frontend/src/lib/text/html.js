import DOMPurify from 'dompurify'

export function escapeHtml(s) {
  return String(s || '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;')
}

/**
 * Linkify textos sueltos dentro de un HTML (sin tocar links ya existentes).
 * También fuerza target/rel seguros.
 */
export function decorateLinksAndLinkifyText(html) {
  try {
    const doc = new DOMParser().parseFromString(String(html || ''), 'text/html')
    const walker = doc.createTreeWalker(doc.body, NodeFilter.SHOW_TEXT)
    const urlRe = /(https?:\/\/[^\s<]+)/g

    const toProcess = []
    let node = walker.nextNode()
    while (node) {
      const parent = node.parentElement
      if (parent && !parent.closest('a') && !['SCRIPT', 'STYLE'].includes(parent.tagName)) {
        const text = String(node.nodeValue || '')
        if (urlRe.test(text)) toProcess.push(node)
      }
      node = walker.nextNode()
    }

    for (const textNode of toProcess) {
      const text = String(textNode.nodeValue || '')
      const frag = doc.createDocumentFragment()
      let lastIndex = 0
      urlRe.lastIndex = 0
      let m
      while ((m = urlRe.exec(text))) {
        const href = m[1]
        const start = m.index
        if (start > lastIndex) frag.appendChild(doc.createTextNode(text.slice(lastIndex, start)))
        const a = doc.createElement('a')
        a.setAttribute('href', href)
        a.setAttribute('target', '_blank')
        a.setAttribute('rel', 'noopener noreferrer')
        a.textContent = href
        frag.appendChild(a)
        lastIndex = start + href.length
      }
      if (lastIndex < text.length) frag.appendChild(doc.createTextNode(text.slice(lastIndex)))
      textNode.parentNode?.replaceChild(frag, textNode)
    }

    const links = Array.from(doc.querySelectorAll('a[href]'))
    for (const a of links) {
      const href = String(a.getAttribute('href') || '')
      if (!href || href.startsWith('javascript:') || href.startsWith('data:')) {
        a.removeAttribute('href')
        continue
      }
      a.setAttribute('target', '_blank')
      a.setAttribute('rel', 'noopener noreferrer')
    }

    return doc.body.innerHTML
  } catch {
    return String(html || '')
  }
}

/** Sanitiza HTML (perfil html) y permite target/rel */
export function sanitizeHtml(html) {
  return DOMPurify.sanitize(String(html || ''), {
    USE_PROFILES: { html: true },
    ADD_ATTR: ['target', 'rel', 'class'],
  })
}

/** Intercepta click en links dentro de un contenedor y abre en nueva pestaña */
export function openLinkInNewTab(ev) {
  const a = ev?.target?.closest?.('a[href]')
  if (!a) return false
  const href = a.getAttribute('href')
  if (!href) return true
  ev.preventDefault()
  ev.stopPropagation()
  window.open(href, '_blank', 'noopener,noreferrer')
  return true
}