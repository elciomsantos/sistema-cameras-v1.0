export function escapeHtml(text: unknown): string {
  if (text == null) return ''
  const d = document.createElement('div')
  d.appendChild(document.createTextNode(String(text)))
  return d.innerHTML
}

const SAFE_TAGS = new Set([
  'b', 'i', 'em', 'strong', 'small', 'p', 'br', 'img', 'span', 'div',
  'ul', 'ol', 'li', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
  'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'blockquote', 'pre', 'code',
])
const SAFE_ATTRS = new Set([
  'class', 'src', 'alt', 'href', 'target', 'rel',
  'width', 'height', 'id', 'title',
])

export function sanitizeHtml(input: string): string {
  const d = document.createElement('div')
  d.innerHTML = input
  function clean(node: Node): void {
    if (node.nodeType === 1) {
      const el = node as HTMLElement
      if (!SAFE_TAGS.has(el.tagName.toLowerCase())) {
        el.replaceWith(...Array.from(el.childNodes))
        return
      }
      const attrs = Array.from(el.attributes)
      for (const attr of attrs) {
        if (!SAFE_ATTRS.has(attr.name)) {
          el.removeAttribute(attr.name)
        }
      }
      const lc = el.tagName.toLowerCase()
      if (lc === 'a') {
        const href = el.getAttribute('href')?.toLowerCase().trim()
        if (!href || href.startsWith('javascript:') || href.startsWith('data:')) {
          el.setAttribute('href', '#')
        }
      }
      if (lc === 'img') {
        const src = el.getAttribute('src')?.toLowerCase().trim()
        if (!src || src.startsWith('data:')) {
          el.removeAttribute('src')
        }
      }
    }
    Array.from(node.childNodes).forEach(clean)
  }
  clean(d)
  return d.innerHTML
}
