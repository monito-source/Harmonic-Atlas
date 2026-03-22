import { useMemo, useRef, useState } from 'react'
import { generateSectionId, getDefaultSectionName } from '../utils.js'
import MidiClipList from './MidiClipList.jsx'

export default function SectionsPanel({
  sections,
  selectedSectionId,
  verses,
  songBpm,
  onSelect,
  onChange,
  onDuplicate,
  filterSectionId = null,
  compactMidiRows = false,
  allowMidiRowToggle = false,
  midiRangePresets = [],
  midiRangeDefault = '',
  lockMidiRange = false,
}) {
  const safeSections = Array.isArray(sections) ? sections : []
  const visibleSections = filterSectionId
    ? safeSections.filter((section) => section.id === filterSectionId)
    : safeSections
  const bpmDefault = Number.isInteger(parseInt(songBpm, 10)) ? parseInt(songBpm, 10) : 120
  const counts = useMemo(() => {
    const map = new Map()
    if (Array.isArray(verses)) {
      verses.forEach((verse) => {
        const id = verse.section_id || ''
        map.set(id, (map.get(id) || 0) + 1)
      })
    }
    return map
  }, [verses])

  const [draggingIndex, setDraggingIndex] = useState(null)
  const [dragOverIndex, setDragOverIndex] = useState(null)
  const [collapsed, setCollapsed] = useState(() => new Set())
  const draggingRef = useRef(null)
  const dragOverRef = useRef(null)

  if (!visibleSections.length) {
    return <p className="wpss-empty">Sin secciones registradas.</p>
  }

  const moveSectionTo = (fromIndex, toIndex) => {
    if (filterSectionId) {
      return
    }
    if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0) {
      return
    }
    if (fromIndex >= safeSections.length || toIndex >= safeSections.length) {
      return
    }

    const next = [...safeSections]
    const [moved] = next.splice(fromIndex, 1)
    const target = fromIndex < toIndex ? toIndex - 1 : toIndex
    next.splice(target, 0, moved)
    onChange(next)
  }

  const handleDuplicate = (index) => {
    if (onDuplicate) {
      onDuplicate(index)
      return
    }

    const section = safeSections[index]
    if (!section) return

    const baseName = section.nombre || getDefaultSectionName(index)
    const midiCopy = Array.isArray(section.midi_clips)
      ? section.midi_clips.map((clip) => ({
          name: clip.name,
          instrument: clip.instrument,
          midi: clip.midi
            ? {
                ...clip.midi,
                notes: Array.isArray(clip.midi.notes) ? clip.midi.notes.map((note) => ({ ...note })) : [],
              }
            : null,
        }))
      : []
    const nextSection = {
      id: generateSectionId(),
      nombre: `${baseName} copia`.slice(0, 64),
      midi_clips: midiCopy,
    }

    const next = [...safeSections]
    next.splice(index + 1, 0, nextSection)
    onChange(next)
    if (onSelect) {
      onSelect(nextSection.id)
    }
  }

  const handleRemove = (index) => {
    if (safeSections.length <= 1) return
    const next = [...safeSections]
    next.splice(index, 1)
    onChange(next)
  }

  const handleMidiChange = (index, clips) => {
    const next = [...safeSections]
    const section = next[index]
    if (!section) return
    next[index] = { ...section, midi_clips: clips }
    onChange(next)
  }

  return (
    <div className="wpss-sections-manager">
      {visibleSections.map((section, index) => {
        const isActive = selectedSectionId === section.id
        const isDragging = draggingIndex === index
        const isDragOver = dragOverIndex === index && draggingIndex !== null
        const isCollapsed = collapsed.has(section.id)
        return (
          <div
            key={section.id}
            className={`wpss-section-row ${isActive ? 'is-active' : ''} ${
              isDragging ? 'is-dragging' : ''
            } ${isDragOver ? 'is-dragover' : ''} ${isCollapsed ? 'is-collapsed' : ''}`}
            onDragOver={(event) => {
              if (filterSectionId) return
              event.preventDefault()
              setDragOverIndex(index)
              dragOverRef.current = index
            }}
            onDrop={(event) => {
              if (filterSectionId) return
              event.preventDefault()
              let fromIndex = draggingIndex
              if (event.dataTransfer) {
                const payload = parseInt(event.dataTransfer.getData('text/plain'), 10)
                if (!Number.isNaN(payload)) {
                  fromIndex = payload
                }
              }
              if (fromIndex === null) return
              moveSectionTo(fromIndex, index)
              setDraggingIndex(null)
              setDragOverIndex(null)
              draggingRef.current = null
              dragOverRef.current = null
            }}
            onDragLeave={() => {
              if (filterSectionId) return
              if (dragOverIndex === index) {
                setDragOverIndex(null)
                dragOverRef.current = null
              }
            }}
          >
            <div className="wpss-section-row__header">
              <button
                type="button"
                className="button button-small"
                onClick={() => {
                  setCollapsed((prev) => {
                    const next = new Set(prev)
                    if (next.has(section.id)) {
                      next.delete(section.id)
                    } else {
                      next.add(section.id)
                    }
                    return next
                  })
                }}
              >
                {isCollapsed ? '▸' : '▾'}
              </button>
              <span
                className={`wpss-drag-handle ${isDragging ? 'is-dragging' : ''}`}
                draggable={!filterSectionId}
                aria-label="Mover sección"
                title="Mover sección"
                onDragStart={(event) => {
                  if (filterSectionId) return
                  if (event.dataTransfer) {
                    event.dataTransfer.setData('text/plain', String(index))
                  }
                  event.dataTransfer.effectAllowed = 'move'
                  setDraggingIndex(index)
                  draggingRef.current = index
                }}
                onDragEnd={() => {
                  if (filterSectionId) return
                  const fromIndex = draggingRef.current
                  const toIndex = dragOverRef.current
                  if (fromIndex !== null && toIndex !== null) {
                    moveSectionTo(fromIndex, toIndex)
                  }
                  setDraggingIndex(null)
                  setDragOverIndex(null)
                  draggingRef.current = null
                  dragOverRef.current = null
                }}
              >
                ☰
              </span>
              <button
                type="button"
                className="wpss-section-row__select"
                onClick={() => onSelect && onSelect(section.id)}
              >
                {section.nombre || getDefaultSectionName(index)}
              </button>
              <span className="wpss-section-row__count">{counts.get(section.id) || 0} versos</span>
              <div className="wpss-section-row__actions">
                <button
                  type="button"
                  className="button button-small"
                  onClick={() => handleDuplicate(index)}
                >
                  Duplicar
                </button>
                <button
                  type="button"
                  className="button button-link-delete"
                  onClick={() => handleRemove(index)}
                  disabled={safeSections.length <= 1}
                >
                  Eliminar
                </button>
              </div>
            </div>
            {isCollapsed ? null : (
              <div className="wpss-section-row__body">
                <label>
                  <span>Nombre</span>
                  <input
                    type="text"
                    value={section.nombre || ''}
                    maxLength={64}
                    onChange={(event) => {
                      const next = [...safeSections]
                      next[index] = { ...section, nombre: event.target.value.slice(0, 64) }
                      onChange(next)
                    }}
                  />
                </label>
                <div className="wpss-section-row__midi">
                  <MidiClipList
                    clips={section.midi_clips}
                    onChange={(clips) => handleMidiChange(index, clips)}
                    emptyLabel="Añadir MIDI a la sección"
                    defaultTempo={bpmDefault}
                    compactRows={compactMidiRows}
                    allowRowToggle={allowMidiRowToggle}
                    rangePresets={midiRangePresets}
                    defaultRange={midiRangeDefault}
                    lockRange={lockMidiRange}
                  />
                </div>
              </div>
            )}
          </div>
        )
      })}
    </div>
  )
}
