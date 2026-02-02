import { useMemo, useRef, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'
import { createEmptySegment, createEmptyVerse } from '../state.js'
import {
  formatSegmentsForStackedMode,
  getChordDisplayValue,
  getDefaultSectionName,
  getValidSegmentIndex,
  normalizeVerseOrder,
  stripHtml,
} from '../utils.js'
import MidiClipList from './MidiClipList.jsx'
import SectionsPanel from './SectionsPanel.jsx'

export default function VersesPanel({
  verses,
  sections,
  selectedSectionId,
  songBpm,
  onSelectSection,
  onSectionsChange,
  onAddSection,
  onDuplicateSection,
  onChange,
  onSplitSegment,
  onSplitVerse,
  onSplitSection,
  onSelectionChange,
  compactMidiRows = false,
  allowMidiRowToggle = false,
  midiRangePresets = [],
  midiRangeDefault = '',
  lockMidiRange = false,
}) {
  const { wpData } = useAppState()
  const bpmDefault = Number.isInteger(parseInt(songBpm, 10)) ? parseInt(songBpm, 10) : 120
  const safeVerses = Array.isArray(verses) ? verses : []
  const safeSections = Array.isArray(sections) ? sections : []
  const fallbackSection = safeSections[0]
  const activeSectionId =
    selectedSectionId && safeSections.some((section) => section.id === selectedSectionId)
      ? selectedSectionId
      : fallbackSection?.id || ''

  const [collapsed, setCollapsed] = useState(() => new Set())
  const [selection, setSelection] = useState({
    verseIndex: null,
    segmentIndex: null,
    start: null,
    end: null,
    element: null,
  })
  const [dragState, setDragState] = useState({
    type: null,
    verseIndex: null,
    segmentIndex: null,
  })
  const [dragOver, setDragOver] = useState({ verseIndex: null, segmentIndex: null })
  const dragRef = useRef({ type: null, verseIndex: null, segmentIndex: null })
  const dragOverRef = useRef({ verseIndex: null, segmentIndex: null })
  const editorsRef = useRef(new Map())

  const versesInSection = useMemo(() => {
    if (!activeSectionId) return []
    return safeVerses
      .map((verse, index) => ({ verse, index }))
      .filter((item) => item.verse.section_id === activeSectionId)
  }, [safeVerses, activeSectionId])

  const handleSelectionUpdate = (verseIndex, segmentIndex, event) => {
    const element = event.currentTarget || event.target
    let start = null
    let end = null

    if (element && element.isContentEditable) {
      const selectionObj = window.getSelection()
      if (selectionObj && selectionObj.rangeCount) {
        const range = selectionObj.getRangeAt(0)
        if (element.contains(range.startContainer)) {
          const preRange = range.cloneRange()
          preRange.selectNodeContents(element)
          preRange.setEnd(range.startContainer, range.startOffset)
          start = preRange.toString().length
          end = start + range.toString().length
        }
      }
    } else {
      start = element?.selectionStart ?? null
      end = element?.selectionEnd ?? null
    }

    const next = { verseIndex, segmentIndex, start, end, element }
    setSelection(next)
    if (onSelectionChange) {
      onSelectionChange(verseIndex, segmentIndex, start, end, element)
    }
  }

  const normalizeSegmentHtml = (html) => {
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

  const applyTextFormat = (format, verseIndex, segmentIndex) => {
    const key = `${verseIndex}:${segmentIndex}`
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

    handleSegmentChange(verseIndex, segmentIndex, 'texto', element.innerHTML)
  }

  const canSplitAt = (verseIndex, segmentIndex) => {
    if (selection.verseIndex !== verseIndex || selection.segmentIndex !== segmentIndex) {
      return false
    }
    if (selection.start === null || selection.start !== selection.end) {
      return false
    }
    const verse = safeVerses[verseIndex]
    const segment = verse?.segmentos?.[segmentIndex]
    const texto = stripHtml(segment?.texto || '')
    return selection.start >= 0 && selection.start <= texto.length
  }

  const canSplitSectionAtVerse = (verseIndex) => {
    if (selection.verseIndex !== verseIndex || selection.segmentIndex === null) {
      return false
    }
    return canSplitAt(verseIndex, selection.segmentIndex)
  }

  const updateVerse = (verseIndex, updater) => {
    const next = [...safeVerses]
    const verse = next[verseIndex]
    if (!verse) return
    next[verseIndex] = typeof updater === 'function' ? updater(verse) : updater
    onChange(next)
  }

  const handleAddVerse = () => {
    if (!activeSectionId) return
    const next = [...safeVerses]
    const newVerse = createEmptyVerse(next.length + 1, activeSectionId)
    next.push(newVerse)
    normalizeVerseOrder(next)
    onChange(next)
  }

  const handleRemoveVerse = (verseIndex) => {
    const next = [...safeVerses]
    next.splice(verseIndex, 1)
    normalizeVerseOrder(next)
    onChange(next)
  }

  const moveVerseTo = (fromIndex, toIndex) => {
    if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0) return
    if (fromIndex >= safeVerses.length || toIndex >= safeVerses.length) return
    const next = [...safeVerses]
    const [moved] = next.splice(fromIndex, 1)
    const target = fromIndex < toIndex ? toIndex - 1 : toIndex
    next.splice(target, 0, moved)
    normalizeVerseOrder(next)
    onChange(next)
  }

  const handleToggleCollapsed = (verseIndex) => {
    setCollapsed((prev) => {
      const next = new Set(prev)
      if (next.has(verseIndex)) {
        next.delete(verseIndex)
      } else {
        next.add(verseIndex)
      }
      return next
    })
  }

  const handleAddSegment = (verseIndex) => {
    updateVerse(verseIndex, (verse) => ({
      ...verse,
      segmentos: [...(Array.isArray(verse.segmentos) ? verse.segmentos : []), createEmptySegment()],
    }))
  }

  const handleSegmentChange = (verseIndex, segmentIndex, field, value) => {
    updateVerse(verseIndex, (verse) => {
      const segmentos = Array.isArray(verse.segmentos) ? [...verse.segmentos] : []
      segmentos[segmentIndex] = {
        ...(segmentos[segmentIndex] || createEmptySegment()),
        [field]: value,
      }
      return { ...verse, segmentos }
    })
  }

  const handleSegmentMidiChange = (verseIndex, segmentIndex, clips) => {
    updateVerse(verseIndex, (verse) => {
      const segmentos = Array.isArray(verse.segmentos) ? [...verse.segmentos] : []
      const current = segmentos[segmentIndex] || createEmptySegment()
      segmentos[segmentIndex] = { ...current, midi_clips: clips }
      return { ...verse, segmentos }
    })
  }

  const handleVerseMidiChange = (verseIndex, clips) => {
    updateVerse(verseIndex, (verse) => ({ ...verse, midi_clips: clips }))
  }

  const adjustEventIndexAfterRemove = (verse, segmentIndex) => {
    const current = getValidSegmentIndex(verse.evento_armonico, verse.segmentos.length)
    if (!verse.evento_armonico || current === null) {
      return verse
    }
    const nextEvent = { ...verse.evento_armonico }
    if (current === segmentIndex) {
      delete nextEvent.segment_index
    } else if (current > segmentIndex) {
      nextEvent.segment_index = current - 1
    }
    return { ...verse, evento_armonico: nextEvent }
  }

  const handleRemoveSegment = (verseIndex, segmentIndex) => {
    updateVerse(verseIndex, (verse) => {
      const segmentos = Array.isArray(verse.segmentos) ? [...verse.segmentos] : []
      if (segmentos.length <= 1) {
        return verse
      }
      segmentos.splice(segmentIndex, 1)
      const updated = adjustEventIndexAfterRemove({ ...verse, segmentos }, segmentIndex)
      return updated
    })
  }

  const handleDuplicateSegment = (verseIndex, segmentIndex) => {
    updateVerse(verseIndex, (verse) => {
      const segmentos = Array.isArray(verse.segmentos) ? [...verse.segmentos] : []
      const segment = segmentos[segmentIndex]
      if (!segment) return verse
      segmentos.splice(segmentIndex + 1, 0, { ...segment })
      const current = getValidSegmentIndex(verse.evento_armonico, segmentos.length - 1)
      if (verse.evento_armonico && current !== null && current > segmentIndex) {
        verse = { ...verse, evento_armonico: { ...verse.evento_armonico, segment_index: current + 1 } }
      }
      return { ...verse, segmentos }
    })
  }

  const handleMoveSegment = (verseIndex, segmentIndex, direction) => {
    updateVerse(verseIndex, (verse) => {
      const segmentos = Array.isArray(verse.segmentos) ? [...verse.segmentos] : []
      const target = segmentIndex + direction
      if (target < 0 || target >= segmentos.length) return verse
      const temp = segmentos[segmentIndex]
      segmentos[segmentIndex] = segmentos[target]
      segmentos[target] = temp

      const current = getValidSegmentIndex(verse.evento_armonico, segmentos.length)
      let nextEvent = verse.evento_armonico
      if (nextEvent && current !== null) {
        if (current === segmentIndex) {
          nextEvent = { ...nextEvent, segment_index: target }
        } else if (current === target) {
          nextEvent = { ...nextEvent, segment_index: segmentIndex }
        }
      }
      return { ...verse, segmentos, evento_armonico: nextEvent }
    })
  }

  const handleSegmentDrop = (verseIndex, segmentIndex, event) => {
    let fromIndex = dragState.segmentIndex
    if (dragState.type !== 'segment') {
      if (event?.dataTransfer) {
        const payload = event.dataTransfer.getData('text/plain') || ''
        const parts = payload.split('-').map((part) => parseInt(part, 10))
        if (parts.length === 2 && !Number.isNaN(parts[0]) && !Number.isNaN(parts[1])) {
          if (parts[0] !== verseIndex) return
          fromIndex = parts[1]
        } else {
          return
        }
      } else {
        return
      }
    }

    if (dragState.type === 'segment' && dragState.verseIndex !== verseIndex) return
    if (fromIndex === null || fromIndex === segmentIndex) return
    updateVerse(verseIndex, (verse) => {
      const segmentos = Array.isArray(verse.segmentos) ? [...verse.segmentos] : []
      const [moved] = segmentos.splice(fromIndex, 1)
      const target = fromIndex < segmentIndex ? segmentIndex - 1 : segmentIndex
      segmentos.splice(target, 0, moved)

      let evento = verse.evento_armonico
      const current = getValidSegmentIndex(evento, segmentos.length)
      if (evento && current !== null) {
        let nextIndex = current
        if (current === fromIndex) {
          nextIndex = target
        } else if (fromIndex < current && target >= current) {
          nextIndex = current - 1
        } else if (fromIndex > current && target <= current) {
          nextIndex = current + 1
        }
        evento = { ...evento, segment_index: nextIndex }
      }

      return { ...verse, segmentos, evento_armonico: evento }
    })
    setDragState({ type: null, verseIndex: null, segmentIndex: null })
    setDragOver({ verseIndex: null, segmentIndex: null })
  }

  const toggleSegmentEvent = (verseIndex, segmentIndex) => {
    updateVerse(verseIndex, (verse) => {
      if (!verse.evento_armonico || !verse.evento_armonico.tipo) {
        return verse
      }

      const total = Array.isArray(verse.segmentos) ? verse.segmentos.length : 0
      if (segmentIndex < 0 || segmentIndex >= total) {
        return verse
      }

      const current = getValidSegmentIndex(verse.evento_armonico, total)
      const nextEvent = { ...verse.evento_armonico }
      if (current !== null && current === segmentIndex) {
        delete nextEvent.segment_index
      } else {
        nextEvent.segment_index = segmentIndex
      }
      return { ...verse, evento_armonico: nextEvent }
    })
  }

  const updateEventType = (verseIndex, tipo) => {
    updateVerse(verseIndex, (verse) => {
      if (!tipo) {
        return { ...verse, evento_armonico: null }
      }

      const previous =
        verse.evento_armonico && typeof verse.evento_armonico === 'object' ? verse.evento_armonico : null
      const next = { tipo }

      if (previous && previous.tipo === tipo) {
        if ('modulacion' === tipo) {
          if (previous.tonica_destino) next.tonica_destino = previous.tonica_destino
          if (previous.campo_armonico_destino) next.campo_armonico_destino = previous.campo_armonico_destino
        } else if ('prestamo' === tipo) {
          if (previous.tonica_origen) next.tonica_origen = previous.tonica_origen
          if (previous.campo_armonico_origen) next.campo_armonico_origen = previous.campo_armonico_origen
        }
        if (Object.prototype.hasOwnProperty.call(previous, 'segment_index')) {
          const index = getValidSegmentIndex(previous, verse.segmentos?.length)
          if (index !== null) {
            next.segment_index = index
          }
        }
      }

      return { ...verse, evento_armonico: next }
    })
  }

  const updateEventField = (verseIndex, field, value) => {
    updateVerse(verseIndex, (verse) => {
      if (!verse.evento_armonico) {
        return verse
      }
      return {
        ...verse,
        evento_armonico: {
          ...verse.evento_armonico,
          [field]: value,
        },
      }
    })
  }

  const getEventTemplates = (tipo, currentIndex) => {
    const templates = []
    safeVerses.forEach((verse, index) => {
      if (index === currentIndex) return
      const evento = verse?.evento_armonico
      if (!evento || evento.tipo !== tipo) return
      templates.push({ ...evento })
    })
    return templates
  }

  const getEventTemplateLabel = (template) => {
    if (!template || !template.tipo) return 'Evento'
    if ('modulacion' === template.tipo) {
      const destino = [template.tonica_destino || '', template.campo_armonico_destino || '']
        .filter(Boolean)
        .join(' ')
      return destino ? `Modulación: ${destino}` : 'Modulación'
    }
    if ('prestamo' === template.tipo) {
      const origen = [template.tonica_origen || '', template.campo_armonico_origen || '']
        .filter(Boolean)
        .join(' ')
      return origen ? `Préstamo: ${origen}` : 'Préstamo'
    }
    return 'Evento'
  }

  const applyEventTemplate = (verseIndex, tipo, templateIndex) => {
    const templates = getEventTemplates(tipo, verseIndex)
    const template = templates[templateIndex]
    if (!template) return
    updateVerse(verseIndex, (verse) => {
      const nextEvent = { tipo }
      if ('modulacion' === tipo) {
        nextEvent.tonica_destino = template.tonica_destino || ''
        nextEvent.campo_armonico_destino = template.campo_armonico_destino || ''
      } else if ('prestamo' === tipo) {
        nextEvent.tonica_origen = template.tonica_origen || ''
        nextEvent.campo_armonico_origen = template.campo_armonico_origen || ''
      }
      if (verse.evento_armonico && Object.prototype.hasOwnProperty.call(verse.evento_armonico, 'segment_index')) {
        const currentIndex = getValidSegmentIndex(verse.evento_armonico, verse.segmentos?.length)
        if (currentIndex !== null) {
          nextEvent.segment_index = currentIndex
        }
      }
      return { ...verse, evento_armonico: nextEvent }
    })
  }

  const buildVersePreview = (verse) => {
    const segmentos = Array.isArray(verse.segmentos) ? verse.segmentos : []
    if (!segmentos.length) {
      return 'Verso vacío'
    }
    return segmentos
      .map((segment) => {
        const texto = segment.texto ? stripHtml(String(segment.texto)).trim() : ''
        const acorde = getChordDisplayValue(segment.acorde ? String(segment.acorde).trim() : '')
        if (texto && acorde) {
          return `${texto} ${acorde}`
        }
        return texto || acorde || ''
      })
      .filter(Boolean)
      .join(' ')
      .slice(0, 160)
  }

  const renderPreviewVerse = (verse, verseIndex) => {
    const segmentos = Array.isArray(verse.segmentos) ? verse.segmentos : []
    if (!segmentos.length) {
      return (
        <li key={`preview-${verseIndex}`} className="wpss-section-preview__verse is-empty">
          <span>Verso vacío</span>
        </li>
      )
    }

    const lines = formatSegmentsForStackedMode(segmentos)
    return (
      <li key={`preview-${verseIndex}`} className="wpss-section-preview__verse">
        <pre className="wpss-reading__stack">{`${lines.chords}\n${lines.lyrics}`}</pre>
        {verse.instrumental ? <span className="wpss-reading__instrumental">Instrumental</span> : null}
      </li>
    )
  }

  return (
    <div className="wpss-verses">
      <div className="wpss-verses__header">
        <div className="wpss-section-selector">
          {safeSections.map((section, index) => (
            <button
              key={section.id}
              type="button"
              className={`wpss-section-tab ${activeSectionId === section.id ? 'is-active' : ''}`}
              onClick={() => onSelectSection && onSelectSection(section.id)}
            >
              {section.nombre || getDefaultSectionName(index)}
            </button>
          ))}
        </div>
        <div className="wpss-section-tools">
          <button type="button" className="button button-secondary" onClick={() => onAddSection && onAddSection()}>
            Añadir sección
          </button>
          <details className="wpss-sections-inline">
            <summary>Gestionar secciones</summary>
            <SectionsPanel
              sections={safeSections}
              selectedSectionId={activeSectionId}
              verses={safeVerses}
              songBpm={bpmDefault}
              onSelect={onSelectSection}
              onChange={onSectionsChange}
              onDuplicate={onDuplicateSection}
              compactMidiRows={compactMidiRows}
              allowMidiRowToggle={allowMidiRowToggle}
              midiRangePresets={midiRangePresets}
              midiRangeDefault={midiRangeDefault}
              lockMidiRange={lockMidiRange}
            />
          </details>
        </div>
      </div>
      <div className="wpss-verses__panel">
        <div className="wpss-verse-group">
          <div className="wpss-verse-group__header">
            <div>
              <strong>
                {safeSections.find((section) => section.id === activeSectionId)?.nombre ||
                  getDefaultSectionName(0)}
              </strong>
              <span className="wpss-verse-group__meta">{versesInSection.length} versos</span>
            </div>
            <button type="button" className="button button-secondary" onClick={handleAddVerse}>
              Añadir verso
            </button>
          </div>
          <div className="wpss-section-preview">
            <div className="wpss-section-preview__header">
              <strong>Vista previa</strong>
              <span>
                {safeSections.find((section) => section.id === activeSectionId)?.nombre ||
                  getDefaultSectionName(0)}
              </span>
            </div>
            {versesInSection.length ? (
              <ul className="wpss-section-preview__body">
                {versesInSection.map(({ verse }, index) => renderPreviewVerse(verse, index))}
              </ul>
            ) : (
              <p className="wpss-empty">Sin versos en esta sección.</p>
            )}
          </div>
          {versesInSection.length ? (
            versesInSection.map(({ verse, index: verseIndex }) => {
              const isCollapsed = collapsed.has(verseIndex)
              const preview = buildVersePreview(verse)
              const label = verse.instrumental ? `Instrumental ${verseIndex + 1}` : `Verso ${verseIndex + 1}`
              const collapsedLabel = preview ? `${label} · ${preview}` : label
              const segmentos = Array.isArray(verse.segmentos) ? verse.segmentos : []
              const segmentTarget = getValidSegmentIndex(verse.evento_armonico, segmentos.length)
              const eventType = verse.evento_armonico?.tipo || ''
              const templates = eventType ? getEventTemplates(eventType, verseIndex) : []
              return (
                <div
                  key={`verse-${verseIndex}`}
                  className={`wpss-verse-card ${isCollapsed ? 'is-collapsed' : ''} ${
                    dragOver.verseIndex === verseIndex ? 'is-dragover' : ''
                  }`}
                  onDragOver={(event) => {
                    event.preventDefault()
                    setDragOver({ verseIndex, segmentIndex: null })
                    dragOverRef.current = { verseIndex, segmentIndex: null }
                  }}
                  onDrop={(event) => {
                    event.preventDefault()
                    if (dragState.type === 'verse') {
                      let fromIndex = dragState.verseIndex
                      if (event.dataTransfer) {
                        const payload = parseInt(event.dataTransfer.getData('text/plain'), 10)
                        if (!Number.isNaN(payload)) {
                          fromIndex = payload
                        }
                      }
                      moveVerseTo(fromIndex, verseIndex)
                      setDragState({ type: null, verseIndex: null, segmentIndex: null })
                      setDragOver({ verseIndex: null, segmentIndex: null })
                      dragRef.current = { type: null, verseIndex: null, segmentIndex: null }
                      dragOverRef.current = { verseIndex: null, segmentIndex: null }
                    }
                  }}
                >
                  <div className="wpss-verse-card__header">
                    <button
                      type="button"
                      className="button button-small"
                      onClick={() => handleToggleCollapsed(verseIndex)}
                    >
                      {isCollapsed ? '▸' : '▾'}
                    </button>
                    <span
                      className={`wpss-drag-handle ${dragState.type === 'verse' ? 'is-dragging' : ''}`}
                      draggable
                      aria-label="Mover verso"
                      title="Mover verso"
                      onDragStart={(event) => {
                        if (event.dataTransfer) {
                          event.dataTransfer.setData('text/plain', String(verseIndex))
                        }
                        event.dataTransfer.effectAllowed = 'move'
                        setDragState({ type: 'verse', verseIndex, segmentIndex: null })
                        dragRef.current = { type: 'verse', verseIndex, segmentIndex: null }
                      }}
                      onDragEnd={() => {
                        const refState = dragRef.current
                        const refOver = dragOverRef.current
                        if (refState.type === 'verse' && refOver.verseIndex !== null) {
                          moveVerseTo(refState.verseIndex, refOver.verseIndex)
                        }
                        setDragState({ type: null, verseIndex: null, segmentIndex: null })
                        setDragOver({ verseIndex: null, segmentIndex: null })
                        dragRef.current = { type: null, verseIndex: null, segmentIndex: null }
                        dragOverRef.current = { verseIndex: null, segmentIndex: null }
                      }}
                    >
                      ☰
                    </span>
                    <strong>{isCollapsed ? collapsedLabel : label}</strong>
                    <div className="wpss-verse-actions">
                      <button
                        type="button"
                        className="button button-small"
                        onClick={() =>
                          onSplitSection &&
                          onSplitSection(
                            verseIndex,
                            selection.segmentIndex ?? 0,
                            selection.element,
                          )
                        }
                      >
                        Dividir sección
                      </button>
                      <button
                        type="button"
                        className="button button-link-delete"
                        onClick={() => handleRemoveVerse(verseIndex)}
                      >
                        Eliminar
                      </button>
                    </div>
                  </div>
                  <div className="wpss-verse-card__body">
                    <div className="wpss-segment-list">
                      {segmentos.map((segment, segmentIndex) => {
                        const isEventTarget = segmentTarget !== null && segmentTarget === segmentIndex
                        const canSelectEvent = !!eventType
                        const canSplit = canSplitAt(verseIndex, segmentIndex)
                        const isDragOver =
                          dragOver.verseIndex === verseIndex && dragOver.segmentIndex === segmentIndex

                        return (
                          <div
                            key={`segment-${verseIndex}-${segmentIndex}`}
                            className={`wpss-segment ${isEventTarget ? 'is-event-target' : ''} ${
                              isDragOver ? 'is-dragover' : ''
                            }`}
                            onDragOver={(event) => {
                              if (dragState.type !== 'segment') return
                              event.preventDefault()
                              setDragOver({ verseIndex, segmentIndex })
                              dragOverRef.current = { verseIndex, segmentIndex }
                            }}
                            onDrop={(event) => {
                              event.preventDefault()
                              handleSegmentDrop(verseIndex, segmentIndex, event)
                            }}
                          >
                            <div className="wpss-segment__drag">
                              <span
                                className={`wpss-drag-handle ${
                                  dragState.type === 'segment' ? 'is-dragging' : ''
                                }`}
                                draggable
                                aria-label="Mover segmento"
                                title="Mover segmento"
                                onDragStart={(event) => {
                                  if (event.dataTransfer) {
                                    event.dataTransfer.setData('text/plain', `${verseIndex}-${segmentIndex}`)
                                  }
                                  event.dataTransfer.effectAllowed = 'move'
                                  setDragState({ type: 'segment', verseIndex, segmentIndex })
                                  dragRef.current = { type: 'segment', verseIndex, segmentIndex }
                                }}
                                onDragEnd={() => {
                                  const refState = dragRef.current
                                  const refOver = dragOverRef.current
                                  if (
                                    refState.type === 'segment'
                                    && refOver.verseIndex === verseIndex
                                    && refOver.segmentIndex !== null
                                  ) {
                                    handleSegmentDrop(verseIndex, refOver.segmentIndex)
                                  }
                                  setDragState({ type: null, verseIndex: null, segmentIndex: null })
                                  setDragOver({ verseIndex: null, segmentIndex: null })
                                  dragRef.current = { type: null, verseIndex: null, segmentIndex: null }
                                  dragOverRef.current = { verseIndex: null, segmentIndex: null }
                                }}
                              >
                                ⋮⋮
                              </span>
                            </div>
                            <div className="wpss-segment__fields">
                              <div>
                                <span>Texto</span>
                                <div className="wpss-text-tools">
                                  <button
                                    type="button"
                                    className="button button-small"
                                    onMouseDown={(event) => event.preventDefault()}
                                    onClick={() => applyTextFormat('bold', verseIndex, segmentIndex)}
                                  >
                                    B
                                  </button>
                                  <button
                                    type="button"
                                    className="button button-small"
                                    onMouseDown={(event) => event.preventDefault()}
                                    onClick={() => applyTextFormat('underline', verseIndex, segmentIndex)}
                                  >
                                    U
                                  </button>
                                  <button
                                    type="button"
                                    className="button button-small"
                                    onMouseDown={(event) => event.preventDefault()}
                                    onClick={() => applyTextFormat('light', verseIndex, segmentIndex)}
                                  >
                                    Light
                                  </button>
                                  <button
                                    type="button"
                                    className="button button-small"
                                    onMouseDown={(event) => event.preventDefault()}
                                    onClick={() => applyTextFormat('clear', verseIndex, segmentIndex)}
                                  >
                                    Normal
                                  </button>
                                </div>
                                <div
                                  className="wpss-segment__text"
                                  contentEditable
                                  suppressContentEditableWarning
                                  ref={(node) => {
                                    const key = `${verseIndex}:${segmentIndex}`
                                    if (!node) {
                                      editorsRef.current.delete(key)
                                      return
                                    }
                                    editorsRef.current.set(key, node)
                                    const selectionObj = window.getSelection()
                                    const hasSelection =
                                      selectionObj && selectionObj.rangeCount
                                        ? node.contains(selectionObj.anchorNode)
                                        : false
                                    const isActive = document.activeElement === node || hasSelection
                                    if (!isActive && node.innerHTML !== (segment.texto || '')) {
                                      node.innerHTML = segment.texto || ''
                                    }
                                  }}
                                  onInput={(event) =>
                                    handleSegmentChange(
                                      verseIndex,
                                      segmentIndex,
                                      'texto',
                                      normalizeSegmentHtml(event.currentTarget.innerHTML),
                                    )
                                  }
                                  onKeyDown={(event) => {
                                    if (event.key !== 'Enter') return
                                    event.preventDefault()
                                    document.execCommand('insertLineBreak')
                                  }}
                                  onFocus={(event) => handleSelectionUpdate(verseIndex, segmentIndex, event)}
                                  onClick={(event) => handleSelectionUpdate(verseIndex, segmentIndex, event)}
                                  onKeyUp={(event) => handleSelectionUpdate(verseIndex, segmentIndex, event)}
                                  onMouseUp={(event) => handleSelectionUpdate(verseIndex, segmentIndex, event)}
                                />
                              </div>
                              <label>
                                <span>Acorde</span>
                                <input
                                  type="text"
                                  value={segment.acorde || ''}
                                  onChange={(event) =>
                                    handleSegmentChange(verseIndex, segmentIndex, 'acorde', event.target.value)
                                  }
                                />
                              </label>
                            </div>
                            <div className="wpss-segment__actions">
                              <button
                                type="button"
                                className="button button-small"
                                onClick={() => handleDuplicateSegment(verseIndex, segmentIndex)}
                              >
                                {wpData?.strings?.segmentDuplicate || 'Duplicar'}
                              </button>
                              <button
                                type="button"
                                className="button button-small wpss-segment__split"
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={(event) => {
                                  const key = `${verseIndex}:${segmentIndex}`
                                  const editor = editorsRef.current.get(key)
                                  if (editor && onSplitSegment) {
                                    onSplitSegment(verseIndex, segmentIndex, editor)
                                  }
                                }}
                              >
                                {wpData?.strings?.segmentSplit || 'Dividir'}
                              </button>
                              <button
                                type="button"
                                className="button button-small"
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={(event) => {
                                  const key = `${verseIndex}:${segmentIndex}`
                                  const editor = editorsRef.current.get(key)
                                  if (editor && onSplitVerse) {
                                    onSplitVerse(verseIndex, segmentIndex, editor)
                                  }
                                }}
                              >
                                Cortar verso
                              </button>
                              <button
                                type="button"
                                className="button button-link-delete"
                                onClick={() => handleRemoveSegment(verseIndex, segmentIndex)}
                              >
                                Eliminar
                              </button>
                            </div>
                            {canSelectEvent ? (
                              <button
                                type="button"
                                className={`button button-small wpss-segment__event ${
                                  isEventTarget ? 'is-active' : ''
                                }`}
                                onClick={() => toggleSegmentEvent(verseIndex, segmentIndex)}
                              >
                                {isEventTarget
                                  ? wpData?.strings?.segmentEventSelected || 'Evento anclado (clic para quitar)'
                                  : wpData?.strings?.segmentEventSelect || 'Anclar evento aquí'}
                              </button>
                            ) : null}
                            <div className="wpss-segment__midi">
                                <MidiClipList
                                  clips={segment?.midi_clips}
                                  onChange={(clips) => handleSegmentMidiChange(verseIndex, segmentIndex, clips)}
                                  emptyLabel="Añadir MIDI al segmento"
                                  defaultTempo={bpmDefault}
                                  compactRows={compactMidiRows}
                                  allowRowToggle={allowMidiRowToggle}
                                  rangePresets={midiRangePresets}
                                  defaultRange={midiRangeDefault}
                                  lockRange={lockMidiRange}
                                />
                            </div>
                          </div>
                        )
                      })}
                      <div className="wpss-segment-add">
                        <button
                          type="button"
                          className="button button-secondary"
                          onClick={() => handleAddSegment(verseIndex)}
                        >
                          {wpData?.strings?.segmentAdd || 'Añadir segmento'}
                        </button>
                      </div>
                    </div>

                    <div className="wpss-verse-detail">
                      <label>
                        <span>Sección</span>
                        <select
                          value={verse.section_id || ''}
                          onChange={(event) => {
                            updateVerse(verseIndex, { ...verse, section_id: event.target.value })
                          }}
                        >
                          {safeSections.map((section, index) => (
                            <option key={section.id} value={section.id}>
                              {section.nombre || getDefaultSectionName(index)}
                            </option>
                          ))}
                        </select>
                      </label>
                      <label>
                        <span>Evento armónico</span>
                        <select
                          value={eventType}
                          onChange={(event) => updateEventType(verseIndex, event.target.value)}
                        >
                          <option value="">Sin evento</option>
                          <option value="modulacion">Modulación</option>
                          <option value="prestamo">Préstamo</option>
                        </select>
                      </label>

                      {eventType === 'modulacion' ? (
                        <div className="wpss-verse-event">
                          <label>
                            <span>Tónica destino</span>
                            <input
                              type="text"
                              list="wpss-tonicas"
                              value={verse.evento_armonico?.tonica_destino || ''}
                              onChange={(event) =>
                                updateEventField(verseIndex, 'tonica_destino', event.target.value)
                              }
                            />
                          </label>
                          <label>
                            <span>Campo destino</span>
                            <input
                              type="text"
                              list="wpss-campos-armonicos"
                              value={verse.evento_armonico?.campo_armonico_destino || ''}
                              onChange={(event) =>
                                updateEventField(verseIndex, 'campo_armonico_destino', event.target.value)
                              }
                            />
                          </label>
                        </div>
                      ) : null}

                      {eventType === 'prestamo' ? (
                        <div className="wpss-verse-event">
                          <label>
                            <span>Tónica origen</span>
                            <input
                              type="text"
                              list="wpss-tonicas"
                              value={verse.evento_armonico?.tonica_origen || ''}
                              onChange={(event) =>
                                updateEventField(verseIndex, 'tonica_origen', event.target.value)
                              }
                            />
                          </label>
                          <label>
                            <span>Campo origen</span>
                            <input
                              type="text"
                              list="wpss-campos-armonicos"
                              value={verse.evento_armonico?.campo_armonico_origen || ''}
                              onChange={(event) =>
                                updateEventField(verseIndex, 'campo_armonico_origen', event.target.value)
                              }
                            />
                          </label>
                        </div>
                      ) : null}

                      {eventType ? (
                        <p className="wpss-verse-event__hint">
                          {segmentTarget !== null
                            ? `${wpData?.strings?.segmentEventLabel || 'Segmento'} ${segmentTarget + 1}`
                            : wpData?.strings?.segmentEventHint || 'Selecciona un segmento para resaltar el evento.'}
                        </p>
                      ) : null}

                      {eventType && templates.length ? (
                        <label>
                          <span>Usar evento existente</span>
                          <select
                            defaultValue=""
                            onChange={(event) => {
                              const value = event.target.value
                              if (value === '') return
                              applyEventTemplate(verseIndex, eventType, parseInt(value, 10))
                              event.target.value = ''
                            }}
                          >
                            <option value="">Selecciona un evento</option>
                            {templates.map((template, index) => (
                              <option key={`template-${verseIndex}-${index}`} value={index}>
                                {getEventTemplateLabel(template)}
                              </option>
                            ))}
                          </select>
                        </label>
                      ) : null}

                      <label>
                        <span>Comentario</span>
                        <textarea
                          value={verse.comentario || ''}
                          onChange={(event) =>
                            updateVerse(verseIndex, { ...verse, comentario: event.target.value })
                          }
                        />
                      </label>
                      <label className="wpss-toggle">
                        <input
                          type="checkbox"
                          checked={!!verse.instrumental}
                          onChange={(event) =>
                            updateVerse(verseIndex, { ...verse, instrumental: event.target.checked })
                          }
                        />
                        <span>Instrumental</span>
                      </label>
                      <div className="wpss-verse-midi">
                        <MidiClipList
                          clips={verse.midi_clips}
                          onChange={(clips) => handleVerseMidiChange(verseIndex, clips)}
                          emptyLabel="Añadir MIDI al verso"
                          defaultTempo={bpmDefault}
                          compactRows={compactMidiRows}
                          allowRowToggle={allowMidiRowToggle}
                          rangePresets={midiRangePresets}
                          defaultRange={midiRangeDefault}
                          lockRange={lockMidiRange}
                        />
                      </div>
                    </div>
                  </div>
                </div>
              )
            })
          ) : (
            <p className="wpss-empty">
              {safeVerses.length
                ? 'Sin versos en esta sección.'
                : wpData?.strings?.versesEmpty || 'Aún no hay versos.'}
            </p>
          )}
        </div>
      </div>
    </div>
  )
}
