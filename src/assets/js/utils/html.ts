export function escapeHtml(text: unknown): string {
  if (text == null) return ''
  const d = document.createElement('div')
  d.appendChild(document.createTextNode(String(text)))
  return d.innerHTML
}
