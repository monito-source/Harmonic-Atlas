import { useMemo, useState } from 'react'
import { buildDefaultStructureFromSections, getDefaultSectionName } from '../utils.js'

export default function StructurePanel({ structure, sections, onChange }) {
  const safeSections = Array.isArray(sections) ? sections : []
  const safeStructure = Array.isArray(structure) ? structure : []
  const [draggingIndex, setDraggingIndex] = useState(null)
  const [dragOverIndex, setDragOverIndex] = useState(null)

  const chips = useMemo(() => {
    if (!safeStructure.length) {
      return [<span key="empty" className="wpss-structure__chip is-empty">—</span>]
    }
    return safeStructure.map((call, index) => (
      <span key={`chip-${index}`} className="wpss-structure__chip">
        {getStructureDisplayLabel(call, safeSections)}
      </span>
    ))
  }, [safeStructure, safeSections])

  const handleAddCall = () => {
    if (!safeSections.length) return
    const next = [...safeStructure, { ref: safeSections[0].id, repeat: 1 }]
    onChange(next)
  }

  const handleDuplicateCall = (index) => {
    const call = safeStructure[index]
    if (!call) return
    const next = [...safeStructure]
    next.splice(index + 1, 0, { ...call })
    onChange(next)
  }

  const handleRemoveCall = (index) => {
    const next = [...safeStructure]
    next.splice(index, 1)
    onChange(next)
  }

  const moveCallTo = (fromIndex, toIndex) => {
    if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0) return
    if (fromIndex >= safeStructure.length || toIndex >= safeStructure.length) return
    const next = [...safeStructure]
    const [moved] = next.splice(fromIndex, 1)
    const target = fromIndex < toIndex ? toIndex - 1 : toIndex
    next.splice(target, 0, moved)
    onChange(next)
  }

  const handleReset = () => {
    onChange(buildDefaultStructureFromSections(safeSections))
  }

  if (!safeSections.length) {
    return <p className="wpss-empty">Agrega secciones antes de definir una estructura.</p>
  }

  return (
    <div className="wpss-structure">
      <header className="wpss-structure__header">
        <h3>Estructura</h3>
        <button type="button" className="button button-secondary" onClick={handleAddCall}>
          Añadir llamada
        </button>
      </header>
      <p className="wpss-structure__hint">
        Define el orden final y las repeticiones de secciones para la vista de lectura.
      </p>
      <div className="wpss-structure__toggle">
        <button type="button" className="button button-link" onClick={handleReset}>
          Restablecer al orden por secciones
        </button>
      </div>
      <div className="wpss-structure__summary">
        <span className="wpss-structure__summary-label">Resumen</span>
        <div className="wpss-structure__chips">{chips}</div>
      </div>
      {safeStructure.length ? (
        <ol className="wpss-structure__list">
          {safeStructure.map((call, index) => (
            <li
              key={`call-${index}`}
              className={`wpss-structure-call ${draggingIndex === index ? 'is-dragging' : ''} ${
                dragOverIndex === index ? 'is-dragover' : ''
              }`}
              onDragOver={(event) => {
                event.preventDefault()
                setDragOverIndex(index)
              }}
              onDrop={(event) => {
                event.preventDefault()
                if (draggingIndex === null) return
                moveCallTo(draggingIndex, index)
                setDraggingIndex(null)
                setDragOverIndex(null)
              }}
              onDragLeave={() => {
                if (dragOverIndex === index) {
                  setDragOverIndex(null)
                }
              }}
            >
              <div className="wpss-structure-call__header">
                <span
                  className={`wpss-drag-handle ${draggingIndex === index ? 'is-dragging' : ''}`}
                  draggable
                  aria-label="Mover llamada"
                  title="Mover llamada"
                  onDragStart={(event) => {
                    if (event.dataTransfer) {
                      event.dataTransfer.setData('text/plain', String(index))
                    }
                    event.dataTransfer.effectAllowed = 'move'
                    setDraggingIndex(index)
                  }}
                  onDragEnd={() => {
                    setDraggingIndex(null)
                    setDragOverIndex(null)
                  }}
                >
                  ☰
                </span>
                <span className="wpss-structure__chip">{getStructureDisplayLabel(call, safeSections)}</span>
                <div className="wpss-structure-call__actions">
                  <button
                    type="button"
                    className="button button-small"
                    onClick={() => handleDuplicateCall(index)}
                  >
                    Duplicar
                  </button>
                  <button
                    type="button"
                    className="button button-link-delete"
                    onClick={() => handleRemoveCall(index)}
                  >
                    Eliminar
                  </button>
                </div>
              </div>
              <div className="wpss-structure-call__fields">
                <label>
                  <span>Sección</span>
                  <select
                    value={call.ref || ''}
                    onChange={(event) => {
                      const next = [...safeStructure]
                      next[index] = { ...call, ref: event.target.value }
                      onChange(next)
                    }}
                  >
                    {safeSections.map((section, sectionIndex) => (
                      <option key={section.id} value={section.id}>
                        {section.nombre || getDefaultSectionName(sectionIndex)}
                      </option>
                    ))}
                  </select>
                </label>
                <label>
                  <span>Repeticiones</span>
                  <input
                    type="number"
                    min="1"
                    max="16"
                    value={call.repeat || 1}
                    onChange={(event) => {
                      const parsed = parseInt(event.target.value, 10)
                      const repeat = Number.isInteger(parsed) && parsed > 0 ? Math.min(parsed, 16) : 1
                      const next = [...safeStructure]
                      next[index] = { ...call, repeat }
                      onChange(next)
                    }}
                  />
                </label>
                <label>
                  <span>Variante</span>
                  <input
                    type="text"
                    value={call.variante || ''}
                    maxLength={16}
                    onChange={(event) => {
                      const next = [...safeStructure]
                      next[index] = { ...call, variante: event.target.value.slice(0, 16) }
                      onChange(next)
                    }}
                  />
                </label>
                <label>
                  <span>Notas</span>
                  <input
                    type="text"
                    value={call.notas || ''}
                    maxLength={128}
                    onChange={(event) => {
                      const next = [...safeStructure]
                      next[index] = { ...call, notas: event.target.value.slice(0, 128) }
                      onChange(next)
                    }}
                  />
                </label>
              </div>
            </li>
          ))}
        </ol>
      ) : (
        <p className="wpss-empty">Aún no hay llamadas registradas.</p>
      )}
    </div>
  )
}

function getStructureDisplayLabel(call, sections) {
  if (call?.variante?.trim()) {
    const base = call.variante.trim()
    const repeat = parseInt(call?.repeat, 10)
    return Number.isInteger(repeat) && repeat > 1 ? `${base} x${repeat}` : base
  }
  if (call?.ref) {
    const match = sections.find((section) => section.id === call.ref)
    if (match?.nombre) {
      const repeat = parseInt(call?.repeat, 10)
      return Number.isInteger(repeat) && repeat > 1 ? `${match.nombre} x${repeat}` : match.nombre
    }
    return call.ref
  }
  return 'Sección'
}
