import { useRef } from 'react'

const DEFAULT_COLOR = '#3b82f6'

const normalizeCommentHtml = (html) => {
  if (!html) return ''
  return html
    .replace(/<div><br><\/div>/gi, '<br>')
    .replace(/<\/div>/gi, '<br>')
    .replace(/<div>/gi, '')
    .replace(/<p><br><\/p>/gi, '<br>')
    .replace(/<\/p>/gi, '<br>')
    .replace(/<p[^>]*>/gi, '')
    .replace(/(<br>\s*)+$/gi, '')
}

const unwrapNode = (node) => {
  if (!node || !node.parentNode) return
  const parent = node.parentNode
  while (node.firstChild) {
    parent.insertBefore(node.firstChild, node)
  }
  parent.removeChild(node)
}

export default function CommentEditor({ comments = [], onChange, label }) {
  const editorsRef = useRef(new Map())

  const updateComments = (next) => {
    if (onChange) {
      onChange(next)
    }
  }

  const updateComment = (index, patch) => {
    const next = [...comments]
    const current = next[index] || {}
    next[index] = { ...current, ...patch }
    updateComments(next)
  }

  const removeComment = (index) => {
    const next = comments.filter((_, idx) => idx !== index)
    updateComments(next)
  }

  const addComment = () => {
    const next = [
      ...comments,
      {
        id: `cmt-${Date.now()}-${Math.floor(Math.random() * 10000)}`,
        texto: '',
        color: DEFAULT_COLOR,
      },
    ]
    updateComments(next)
  }

  const applyCommentFormat = (format, key, index) => {
    const element = editorsRef.current.get(key)
    if (!element || !element.isContentEditable) return

    element.focus()
    const selectionObj = window.getSelection()
    if (!selectionObj || selectionObj.rangeCount === 0) return

    const range = selectionObj.getRangeAt(0)
    if (!element.contains(range.startContainer)) return

    if (format === 'bold') {
      document.execCommand('bold')
    } else if (format === 'underline') {
      document.execCommand('underline')
    } else if (format === 'light') {
      if (range.collapsed) return
      const fragment = range.cloneContents()
      if (fragment.querySelector && fragment.querySelector('div, p, br')) return

      const startLight = range.startContainer.parentElement?.closest?.('.wpss-text-light')
      const endLight = range.endContainer.parentElement?.closest?.('.wpss-text-light')
      if (startLight && startLight === endLight) {
        unwrapNode(startLight)
      } else {
        const span = document.createElement('span')
        span.className = 'wpss-text-light'
        const extracted = range.extractContents()
        span.appendChild(extracted)
        range.insertNode(span)
      }
    } else if (format === 'clear') {
      document.execCommand('removeFormat')
      const fragment = range.cloneContents()
      if (fragment.querySelector && fragment.querySelector('span.wpss-text-light')) {
        element.querySelectorAll('span.wpss-text-light').forEach((node) => {
          if (range.intersectsNode(node)) {
            unwrapNode(node)
          }
        })
      }
    } else {
      return
    }

    updateComment(index, { texto: normalizeCommentHtml(element.innerHTML) })
  }

  return (
    <div className="wpss-comments">
      {label ? <strong className="wpss-comments__label">{label}</strong> : null}
      {comments.length ? (
        comments.map((comment, index) => {
          const key = comment.id || `cmt-${index}`
          const color = comment.color || DEFAULT_COLOR
          const editorRef = (node) => {
            if (node) {
              editorsRef.current.set(key, node)
              if (document.activeElement !== node && node.innerHTML !== (comment.texto || '')) {
                node.innerHTML = comment.texto || ''
              }
            } else {
              editorsRef.current.delete(key)
            }
          }

          return (
            <div key={key} className="wpss-comment" style={{ '--note-color': color }}>
              <div className="wpss-comment__toolbar">
                <button type="button" className="button button-small" onClick={() => applyCommentFormat('bold', key, index)}>
                  B
                </button>
                <button
                  type="button"
                  className="button button-small"
                  onClick={() => applyCommentFormat('underline', key, index)}
                >
                  U
                </button>
                <button type="button" className="button button-small" onClick={() => applyCommentFormat('light', key, index)}>
                  Light
                </button>
                <button type="button" className="button button-small" onClick={() => applyCommentFormat('clear', key, index)}>
                  Normal
                </button>
                <input
                  type="color"
                  value={color}
                  onChange={(event) => updateComment(index, { color: event.target.value })}
                  aria-label="Color de nota"
                />
                <button type="button" className="button button-link-delete" onClick={() => removeComment(index)}>
                  Eliminar
                </button>
              </div>
              <div
                className="wpss-comment__editor"
                contentEditable
                suppressContentEditableWarning
                ref={editorRef}
                onInput={(event) => updateComment(index, { texto: normalizeCommentHtml(event.currentTarget.innerHTML) })}
              />
            </div>
          )
        })
      ) : (
        <p className="wpss-empty">Sin notas.</p>
      )}
      <button type="button" className="button button-secondary" onClick={addComment}>
        Añadir nota
      </button>
    </div>
  )
}
