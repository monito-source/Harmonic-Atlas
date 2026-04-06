import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'
import { createEmptySegment, createEmptyVerse } from '../state.js'
import {
  formatSegmentsForStackedMode,
  getChordPreviewValue,
  getDefaultSectionName,
  getValidSegmentIndex,
  normalizeVerseOrder,
  stripHtml,
} from '../utils.js'
import MidiClipList from './MidiClipList.jsx'
import CommentEditor from './CommentEditor.jsx'
import SectionsPanel from './SectionsPanel.jsx'
import InlineMediaQuickActions from './InlineMediaQuickActions.jsx'

const TOUCH_DRAG_ACTIVATION_DELAY = 180
const TOUCH_DRAG_CANCEL_DISTANCE = 10
const SPECIAL_CHORD_SUGGESTIONS = [
  { value: 'null', display: 'Silencio', count: 0, order: -2 },
  { value: 'still', display: 'Mantener', count: 0, order: -1 },
]

const buildContentIndicators = ({ attachments = [], comments = [], midiClips = [] }) => {
  const safeAttachments = Array.isArray(attachments) ? attachments : []
  const indicators = []
  if (safeAttachments.some((item) => item?.type !== 'photo')) {
    indicators.push({ key: 'audio', title: 'Tiene audio', tabId: 'audio' })
  }
  if (safeAttachments.some((item) => item?.type === 'photo')) {
    indicators.push({ key: 'photo', title: 'Tiene foto', tabId: 'photos' })
  }
  if (Array.isArray(comments) && comments.length) {
    indicators.push({ key: 'annotation', title: 'Tiene anotaciones', tabId: 'annotations' })
  }
  if (Array.isArray(midiClips) && midiClips.length) {
    indicators.push({ key: 'midi', title: 'Tiene MIDI', tabId: 'midi' })
  }
  return indicators
}

const normalizeChordSuggestionValue = (value) => {
  const normalized = value ? String(value).trim() : ''
  if (!normalized || normalized === '?') {
    return ''
  }
  const normalizedToken = normalized.toLowerCase()
  if (normalizedToken === 'silencio') {
    return 'null'
  }
  if (normalizedToken === 'mantener' || normalizedToken === 'sostener') {
    return 'still'
  }
  const preview = getChordPreviewValue(normalized)
  const display = preview ? String(preview).trim() : normalized
  return display && display !== '?' ? normalized : ''
}

const buildChordSuggestionLibrary = (verses = []) => {
  const items = new Map()
  let order = 0
  ;(Array.isArray(verses) ? verses : []).forEach((verse) => {
    const segments = Array.isArray(verse?.segmentos) ? verse.segmentos : []
    segments.forEach((segment) => {
      const value = normalizeChordSuggestionValue(segment?.acorde)
      if (!value) {
        return
      }
      const key = value.toLowerCase()
      const existing = items.get(key)
      if (existing) {
        existing.count += 1
        return
      }
      items.set(key, {
        value,
        display: getChordPreviewValue(value) || value,
        count: 1,
        order,
      })
      order += 1
    })
  })
  return Array.from(items.values()).sort((a, b) =>
    b.count - a.count
    || a.order - b.order
    || String(a.display || a.value || '').localeCompare(String(b.display || b.value || ''), 'es', {
      sensitivity: 'base',
    }),
  )
}

const filterChordSuggestions = (library = [], query = '') => {
  const items = Array.isArray(library) ? library : []
  const normalizedQuery = query ? String(query).trim().toLowerCase() : ''
  const specials = SPECIAL_CHORD_SUGGESTIONS.filter((item) => {
    if (!normalizedQuery) {
      return true
    }
    return (
      item.value.includes(normalizedQuery)
      || item.display.toLowerCase().includes(normalizedQuery)
    )
  })
  if (!normalizedQuery) {
    return specials.concat(items).slice(0, 8)
  }
  const exact = []
  const startsWith = []
  const contains = []
  items.forEach((item) => {
    const value = String(item?.value || '').toLowerCase()
    const display = String(item?.display || '').toLowerCase()
    const haystack = `${value} ${display}`.trim()
    if (!haystack.includes(normalizedQuery)) {
      return
    }
    if (value === normalizedQuery || display === normalizedQuery) {
      exact.push(item)
      return
    }
    if (value.startsWith(normalizedQuery) || display.startsWith(normalizedQuery)) {
      startsWith.push(item)
      return
    }
    contains.push(item)
  })
  return specials.concat(exact, startsWith, contains).slice(0, 8)
}

function ContentIndicatorIcon({ type }) {
  if (type === 'audio') {
    return (
      <svg viewBox="0 0 16 16" aria-hidden="true">
        <path d="M10.5 2.5v7.1a2.4 2.4 0 1 1-1-1.94V4.9L6 5.8V11a2.4 2.4 0 1 1-1-1.94V5l5.5-1.5Z" fill="currentColor" />
      </svg>
    )
  }
  if (type === 'photo') {
    return (
      <svg viewBox="0 0 16 16" aria-hidden="true">
        <path d="M3 4.5h2l.8-1.3h4.4l.8 1.3h2A1.5 1.5 0 0 1 14.5 6v6A1.5 1.5 0 0 1 13 13.5H3A1.5 1.5 0 0 1 1.5 12V6A1.5 1.5 0 0 1 3 4.5Zm5 2A2.7 2.7 0 1 0 8 11.9 2.7 2.7 0 0 0 8 6.5Zm0 1.2A1.5 1.5 0 1 1 6.5 9.2 1.5 1.5 0 0 1 8 7.7Z" fill="currentColor" />
      </svg>
    )
  }
  if (type === 'annotation') {
    return (
      <svg viewBox="0 0 16 16" aria-hidden="true">
        <path d="M3 2.5h10A1.5 1.5 0 0 1 14.5 4v5A1.5 1.5 0 0 1 13 10.5H8.4L5 13v-2.5H3A1.5 1.5 0 0 1 1.5 9V4A1.5 1.5 0 0 1 3 2.5Zm1.5 2v1h7v-1Zm0 2.5v1h5v-1Z" fill="currentColor" />
      </svg>
    )
  }
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true">
      <path d="M2 3.5h12v9H2Zm1.2 1.2v6.6h9.6V4.7Zm1.1 1.1h1v4.4h-1Zm2.2 0h1v4.4h-1Zm2.2 0h1v4.4h-1Zm2.2 0h1v4.4h-1Z" fill="currentColor" />
    </svg>
  )
}

function ContentIndicators({ items = [], onSelect = null }) {
  if (!Array.isArray(items) || !items.length) {
    return null
  }
  return (
    <span className="wpss-content-indicators" aria-label="Contenido disponible">
      {items.map((item) => (
        <span
          key={item.key}
          className={`wpss-content-indicator is-${item.key}`}
          title={item.title}
          aria-label={item.title}
          role={typeof onSelect === 'function' ? 'button' : undefined}
          tabIndex={typeof onSelect === 'function' ? 0 : undefined}
          onClick={(event) => {
            event.stopPropagation()
            onSelect?.(item)
          }}
          onKeyDown={(event) => {
            if (typeof onSelect !== 'function') {
              return
            }
            if (event.key === 'Enter' || event.key === ' ') {
              event.preventDefault()
              event.stopPropagation()
              onSelect(item)
            }
          }}
        >
          <ContentIndicatorIcon type={item.key} />
        </span>
      ))}
    </span>
  )
}

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
  focusRequest = null,
  onFocusRequestHandled,
  compactMidiRows = false,
  allowMidiRowToggle = false,
  midiRangePresets = [],
  midiRangeDefault = '',
  lockMidiRange = false,
  showHeader = true,
  showPreview = true,
  visibleVerseIndexes = null,
  songAttachments = [],
  onContextIndicatorSelect,
  onQuickUploadAttachment,
  onBeginSegmentDrag,
  onEndSegmentDrag,
  onMoveSegmentToVerse,
  onMoveSegmentToNewVerse,
  useContextualToolbar = false,
}) {
  const { wpData } = useAppState()
  const bpmDefault = Number.isInteger(parseInt(songBpm, 10)) ? parseInt(songBpm, 10) : 120
  const safeVerses = Array.isArray(verses) ? verses : []
  const safeSections = Array.isArray(sections) ? sections : []
  const safeAttachments = Array.isArray(songAttachments) ? songAttachments : []
  const fallbackSection = safeSections[0]
  const activeSectionId =
    selectedSectionId && safeSections.some((section) => section.id === selectedSectionId)
      ? selectedSectionId
      : fallbackSection?.id || ''
  const activeSectionName =
    safeSections.find((section) => section.id === activeSectionId)?.nombre || getDefaultSectionName(0)

  const getVerseAttachments = useCallback(
    (verseIndex) =>
      safeAttachments.filter(
        (item) => String(item?.anchor_type || '') === 'verse' && Number(item?.verse_index) === Number(verseIndex),
      ),
    [safeAttachments],
  )

  const getSegmentAttachments = useCallback(
    (verseIndex, segmentIndex) =>
      safeAttachments.filter(
        (item) =>
          String(item?.anchor_type || '') === 'segment'
          && Number(item?.verse_index) === Number(verseIndex)
          && Number(item?.segment_index) === Number(segmentIndex),
      ),
    [safeAttachments],
  )

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
  const [openNotes, setOpenNotes] = useState(() => new Set())
  const [moveTargets, setMoveTargets] = useState({})
  const [segmentMoveTargets, setSegmentMoveTargets] = useState({})
  const [chordSuggestionsState, setChordSuggestionsState] = useState({
    verseIndex: null,
    segmentIndex: null,
    query: '',
    highlightedIndex: 0,
  })
  const dragRef = useRef({ type: null, verseIndex: null, segmentIndex: null })
  const dragOverRef = useRef({ verseIndex: null, segmentIndex: null })
  const dragDropHandledRef = useRef(false)
  const touchDragSessionRef = useRef(null)
  const touchDragTimerRef = useRef(null)
  const editorsRef = useRef(new Map())
  const chordInputRefs = useRef(new Map())
  const pendingSegmentFocusRef = useRef(null)
  const chordSuggestionLibrary = useMemo(() => buildChordSuggestionLibrary(safeVerses), [safeVerses])
  const visibleChordSuggestions = useMemo(
    () => filterChordSuggestions(chordSuggestionLibrary, chordSuggestionsState.query),
    [chordSuggestionLibrary, chordSuggestionsState.query],
  )

  const clearSelection = useCallback(() => {
    setSelection((prev) => {
      if (
        prev.verseIndex === null
        && prev.segmentIndex === null
        && prev.start === null
        && prev.end === null
        && prev.element === null
      ) {
        return prev
      }
      return {
        verseIndex: null,
        segmentIndex: null,
        start: null,
        end: null,
        element: null,
      }
    })
    if (onSelectionChange) {
      onSelectionChange(null, null, null, null, null)
    }
  }, [onSelectionChange])

  const handlePanelClickCapture = useCallback(
    (event) => {
      if (selection.verseIndex === null && selection.segmentIndex === null) {
        return
      }
      const target = event.target
      if (!(target instanceof Element)) {
        return
      }
      if (target.closest('.wpss-segment, .wpss-verse-actions, .wpss-verse-format-toolbar')) {
        return
      }
      clearSelection()
    },
    [clearSelection, selection.segmentIndex, selection.verseIndex],
  )

  const versesInSection = !activeSectionId
    ? []
    : safeVerses
        .map((verse, index) => ({ verse, index }))
        .filter((item) => item.verse.section_id === activeSectionId)

  const visibleVersesInSection =
    !visibleVerseIndexes || !(visibleVerseIndexes instanceof Set) || visibleVerseIndexes.size === 0
      ? versesInSection
      : versesInSection.filter((item) => visibleVerseIndexes.has(item.index))

  const verseLabelMap = (() => {
    const map = new Map()
    safeVerses.forEach((verse, index) => {
      const sectionVerses = safeVerses
        .slice(0, index + 1)
        .filter((item) => (item.section_id || '') === (verse.section_id || ''))
      const position = sectionVerses.length
      const label = verse?.nombre
        ? String(verse.nombre)
        : verse?.instrumental
          ? `Instrumental ${position}`
          : `Verso ${position}`
      map.set(index, label)
    })
    return map
  })()

  const buildSegmentMoveTarget = (segmentKey, fallbackSectionId) => {
    const stored = segmentMoveTargets[segmentKey]
    if (stored && typeof stored === 'object') {
      return {
        sectionId: stored.sectionId || fallbackSectionId || activeSectionId || '',
        verseValue: stored.verseValue || '__new__',
      }
    }
    return {
      sectionId: fallbackSectionId || activeSectionId || '',
      verseValue: '__new__',
    }
  }

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

  const queueSegmentFocus = useCallback(
    (verseIndex, segmentIndex, selectAll = true, requestId = null) => {
      pendingSegmentFocusRef.current = { verseIndex, segmentIndex, selectAll, requestId }
      setSelection({
        verseIndex,
        segmentIndex,
        start: 0,
        end: 0,
        element: null,
      })
      if (onSelectionChange) {
        onSelectionChange(verseIndex, segmentIndex, 0, 0, null)
      }
    },
    [onSelectionChange],
  )

  useEffect(() => {
    if (!focusRequest || !Number.isInteger(focusRequest.verseIndex) || !Number.isInteger(focusRequest.segmentIndex)) {
      return
    }
    queueSegmentFocus(
      focusRequest.verseIndex,
      focusRequest.segmentIndex,
      focusRequest.selectAll !== false,
      focusRequest.requestId ?? null,
    )
  }, [focusRequest, queueSegmentFocus])

  useEffect(() => {
    const pendingFocus = pendingSegmentFocusRef.current
    if (!pendingFocus) {
      return undefined
    }

    const key = `${pendingFocus.verseIndex}:${pendingFocus.segmentIndex}`
    const element = editorsRef.current.get(key)
    if (!element || !element.isContentEditable) {
      return undefined
    }

    const applyPendingFocus = () => {
      pendingSegmentFocusRef.current = null
      element.focus()

      const selectionObj = window.getSelection()
      const range = document.createRange()
      const textLength = stripHtml(element.innerHTML || '').length

      range.selectNodeContents(element)
      if (!pendingFocus.selectAll) {
        range.collapse(false)
      }

      if (selectionObj) {
        selectionObj.removeAllRanges()
        selectionObj.addRange(range)
      }

      const nextSelection = {
        verseIndex: pendingFocus.verseIndex,
        segmentIndex: pendingFocus.segmentIndex,
        start: pendingFocus.selectAll ? 0 : textLength,
        end: textLength,
        element,
      }
      setSelection(nextSelection)
      if (onSelectionChange) {
        onSelectionChange(
          nextSelection.verseIndex,
          nextSelection.segmentIndex,
          nextSelection.start,
          nextSelection.end,
          nextSelection.element,
        )
      }
      if (pendingFocus.requestId !== null && onFocusRequestHandled) {
        onFocusRequestHandled(pendingFocus.requestId)
      }
    }

    if (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function') {
      const frameId = window.requestAnimationFrame(applyPendingFocus)
      return () => window.cancelAnimationFrame(frameId)
    }

    applyPendingFocus()
    return undefined
  }, [onFocusRequestHandled, onSelectionChange, safeVerses, visibleVersesInSection])

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
    queueSegmentFocus(safeVerses.length, 0, true)
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

  const handleMoveVerseToSection = (verseIndex, sectionId) => {
    const next = [...safeVerses]
    const verse = next[verseIndex]
    if (!verse) return
    next[verseIndex] = { ...verse, section_id: sectionId }
    normalizeVerseOrder(next)
    onChange(next)
  }

  const cloneDeep = (value) => {
    if (value === undefined) return undefined
    try {
      return JSON.parse(JSON.stringify(value))
    } catch {
      return value
    }
  }

  const handleDuplicateVerse = (verseIndex) => {
    const source = safeVerses[verseIndex]
    if (!source) return

    const clone = {
      ...source,
      id: null,
      segmentos: (Array.isArray(source.segmentos) ? source.segmentos : []).map((segment) => ({
        ...createEmptySegment(),
        ...segment,
        comentarios: cloneDeep(Array.isArray(segment?.comentarios) ? segment.comentarios : []),
        midi_clips: cloneDeep(Array.isArray(segment?.midi_clips) ? segment.midi_clips : []),
      })),
      comentarios: cloneDeep(Array.isArray(source.comentarios) ? source.comentarios : []),
      evento_armonico: source.evento_armonico ? cloneDeep(source.evento_armonico) : null,
      midi_clips: cloneDeep(Array.isArray(source.midi_clips) ? source.midi_clips : []),
    }

    const next = [...safeVerses]
    next.splice(verseIndex + 1, 0, clone)
    normalizeVerseOrder(next)
    onChange(next)
  }

  const resetDragState = () => {
    setDragState({ type: null, verseIndex: null, segmentIndex: null })
    setDragOver({ verseIndex: null, segmentIndex: null })
    dragRef.current = { type: null, verseIndex: null, segmentIndex: null }
    dragOverRef.current = { verseIndex: null, segmentIndex: null }
    dragDropHandledRef.current = false
  }

  const moveVerseTo = (fromIndex, toIndex) => {
    if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0) return false
    if (fromIndex >= safeVerses.length || toIndex >= safeVerses.length) return false

    const fromVerse = safeVerses[fromIndex]
    const toVerse = safeVerses[toIndex]
    if (!fromVerse || !toVerse) return false

    const sectionId = fromVerse.section_id || ''
    if ((toVerse.section_id || '') !== sectionId) {
      return false
    }

    const sectionIndexes = safeVerses
      .map((verse, index) => ({ verse, index }))
      .filter((item) => (item.verse.section_id || '') === sectionId)
      .map((item) => item.index)

    const fromPosition = sectionIndexes.indexOf(fromIndex)
    const toPosition = sectionIndexes.indexOf(toIndex)
    if (fromPosition < 0 || toPosition < 0 || fromPosition === toPosition) {
      return false
    }

    const reorderedIndexes = [...sectionIndexes]
    const [movedIndex] = reorderedIndexes.splice(fromPosition, 1)
    reorderedIndexes.splice(toPosition, 0, movedIndex)

    const next = [...safeVerses]
    const targetSlots = [...sectionIndexes].sort((a, b) => a - b)
    const reorderedVerses = reorderedIndexes.map((index) => safeVerses[index])
    targetSlots.forEach((slot, slotIndex) => {
      next[slot] = reorderedVerses[slotIndex]
    })

    normalizeVerseOrder(next)
    onChange(next)
    return true
  }

  const toggleNotes = (key) => {
    setOpenNotes((prev) => {
      const next = new Set(prev)
      if (next.has(key)) {
        next.delete(key)
      } else {
        next.add(key)
      }
      return next
    })
  }

  const handleVerseCommentsChange = (verseIndex, comments) => {
    const nextVerses = [...safeVerses]
    const verse = nextVerses[verseIndex]
    if (!verse) return
    nextVerses[verseIndex] = { ...verse, comentarios: comments }
    if (onChange) {
      onChange(nextVerses)
    }
  }

  const handleSegmentCommentsChange = (verseIndex, segmentIndex, comments) => {
    const nextVerses = [...safeVerses]
    const verse = nextVerses[verseIndex]
    if (!verse) return
    const segmentos = Array.isArray(verse.segmentos) ? [...verse.segmentos] : []
    if (!segmentos[segmentIndex]) return
    segmentos[segmentIndex] = { ...segmentos[segmentIndex], comentarios: comments }
    nextVerses[verseIndex] = { ...verse, segmentos }
    if (onChange) {
      onChange(nextVerses)
    }
  }

  const handleAddSegment = (verseIndex) => {
    const nextSegmentIndex = Array.isArray(safeVerses[verseIndex]?.segmentos)
      ? safeVerses[verseIndex].segmentos.length
      : 0
    queueSegmentFocus(verseIndex, nextSegmentIndex, true)
    updateVerse(verseIndex, (verse) => ({
      ...verse,
      segmentos: [
        ...(Array.isArray(verse.segmentos) ? verse.segmentos : []),
        { ...createEmptySegment(), texto: '...' },
      ],
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

  const handleSegmentChordBlur = (verseIndex, segmentIndex, value) => {
    const normalized = normalizeChordSuggestionValue(value) || (value ? String(value).trim() : '')
    handleSegmentChange(verseIndex, segmentIndex, 'acorde', normalized || '?')
  }

  const openChordSuggestions = useCallback((verseIndex, segmentIndex, query = '') => {
    setChordSuggestionsState({
      verseIndex,
      segmentIndex,
      query: String(query || ''),
      highlightedIndex: 0,
    })
  }, [])

  const closeChordSuggestions = useCallback(() => {
    setChordSuggestionsState({
      verseIndex: null,
      segmentIndex: null,
      query: '',
      highlightedIndex: 0,
    })
  }, [])

  const applyChordSuggestion = useCallback((verseIndex, segmentIndex, nextValue) => {
    const normalized = nextValue ? String(nextValue).trim() : ''
    handleSegmentChange(verseIndex, segmentIndex, 'acorde', normalized)
    setChordSuggestionsState({
      verseIndex,
      segmentIndex,
      query: normalized,
      highlightedIndex: 0,
    })
    const key = `${verseIndex}:${segmentIndex}`
    const input = chordInputRefs.current.get(key)
    if (input) {
      requestAnimationFrame(() => {
        input.focus()
        const end = normalized.length
        if (typeof input.setSelectionRange === 'function') {
          input.setSelectionRange(end, end)
        }
      })
    }
  }, [handleSegmentChange])

  useEffect(() => {
    if (chordSuggestionsState.verseIndex === null || chordSuggestionsState.segmentIndex === null) {
      return
    }
    if (!visibleChordSuggestions.length) {
      setChordSuggestionsState((prev) =>
        prev.highlightedIndex === 0 ? prev : { ...prev, highlightedIndex: 0 },
      )
      return
    }
    setChordSuggestionsState((prev) => {
      const nextIndex = Math.min(prev.highlightedIndex, visibleChordSuggestions.length - 1)
      return nextIndex === prev.highlightedIndex ? prev : { ...prev, highlightedIndex: nextIndex }
    })
  }, [visibleChordSuggestions, chordSuggestionsState.verseIndex, chordSuggestionsState.segmentIndex])

  const handleSegmentTextBlur = (verseIndex, segmentIndex, html, element) => {
    const normalizedHtml = normalizeSegmentHtml(html)
    const normalizedText = stripHtml(normalizedHtml).trim()
    const nextValue = normalizedText ? normalizedHtml : '...'
    if (element && element.innerHTML !== nextValue) {
      element.innerHTML = nextValue
    }
    handleSegmentChange(verseIndex, segmentIndex, 'texto', nextValue)
  }

  const handleSegmentMidiChange = (verseIndex, segmentIndex, clips) => {
    updateVerse(verseIndex, (verse) => {
      const segmentos = Array.isArray(verse.segmentos) ? [...verse.segmentos] : []
      const current = segmentos[segmentIndex] || createEmptySegment()
      segmentos[segmentIndex] = { ...current, midi_clips: clips }
      return { ...verse, segmentos }
    })
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

  const handleSegmentDrop = (verseIndex, segmentIndex, event) => {
    let fromIndex = dragState.segmentIndex
    if (dragState.type !== 'segment') {
      if (event?.dataTransfer) {
        const payload = event.dataTransfer.getData('text/plain') || ''
        const parts = payload.split('-').map((part) => parseInt(part, 10))
        if (parts.length === 2 && !Number.isNaN(parts[0]) && !Number.isNaN(parts[1])) {
          if (parts[0] !== verseIndex) return false
          fromIndex = parts[1]
        } else {
          return false
        }
      } else {
        return false
      }
    }

    if (dragState.type === 'segment' && dragState.verseIndex !== verseIndex) return false
    if (fromIndex === null || fromIndex === segmentIndex) return false
    updateVerse(verseIndex, (verse) => {
      const segmentos = Array.isArray(verse.segmentos) ? [...verse.segmentos] : []
      const [moved] = segmentos.splice(fromIndex, 1)
      if (!moved) return verse
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
    return true
  }

  const clearTouchDragTimer = useCallback(() => {
    if (touchDragTimerRef.current !== null) {
      window.clearTimeout(touchDragTimerRef.current)
      touchDragTimerRef.current = null
    }
  }, [])

  const clearTouchDragSession = useCallback(() => {
    const activeType = touchDragSessionRef.current?.active ? touchDragSessionRef.current.type : null
    clearTouchDragTimer()
    touchDragSessionRef.current?.cleanup?.()
    touchDragSessionRef.current = null
    resetDragState()
    if (activeType === 'segment' && onEndSegmentDrag) {
      onEndSegmentDrag()
    }
  }, [clearTouchDragTimer, onEndSegmentDrag, resetDragState])

  const resolveTouchDropTarget = useCallback((clientX, clientY) => {
    if (typeof document === 'undefined') {
      return null
    }

    const target = document.elementFromPoint(clientX, clientY)
    if (!(target instanceof Element)) {
      return null
    }

    const segmentNode = target.closest('[data-wpss-segment-key]')
    if (segmentNode) {
      const key = String(segmentNode.getAttribute('data-wpss-segment-key') || '')
      const [verseIndexRaw, segmentIndexRaw] = key.split(':')
      const verseIndex = parseInt(verseIndexRaw || '', 10)
      const segmentIndex = parseInt(segmentIndexRaw || '', 10)
      if (!Number.isNaN(verseIndex) && !Number.isNaN(segmentIndex)) {
        return { type: 'segment', verseIndex, segmentIndex }
      }
    }

    const verseNode = target.closest('[data-wpss-verse-index]')
    if (verseNode) {
      const verseIndex = parseInt(verseNode.getAttribute('data-wpss-verse-index') || '', 10)
      if (!Number.isNaN(verseIndex)) {
        return { type: 'verse', verseIndex }
      }
    }

    const previewVerseNode = target.closest('[data-wpss-preview-verse-index]')
    if (previewVerseNode) {
      const verseIndex = parseInt(previewVerseNode.getAttribute('data-wpss-preview-verse-index') || '', 10)
      if (!Number.isNaN(verseIndex)) {
        return { type: 'preview-verse', verseIndex }
      }
    }

    const previewSectionNode = target.closest('[data-wpss-preview-section-id]')
    if (previewSectionNode) {
      const sectionId = String(previewSectionNode.getAttribute('data-wpss-preview-section-id') || '')
      if (sectionId) {
        return { type: 'preview-section', sectionId }
      }
    }

    return null
  }, [])

  const beginTouchHandleDrag = useCallback((event, payload) => {
    if (!payload || (event.pointerType && event.pointerType === 'mouse')) {
      return
    }

    event.preventDefault()
    event.stopPropagation()
    clearTouchDragSession()

    const pointerId = event.pointerId
    const startX = event.clientX
    const startY = event.clientY

    const updateHoverState = (clientX, clientY) => {
      const target = resolveTouchDropTarget(clientX, clientY)
      if (payload.type === 'verse') {
        const nextVerseIndex = target?.type === 'verse' ? target.verseIndex : null
        setDragOver({ verseIndex: nextVerseIndex, segmentIndex: null })
        dragOverRef.current = { verseIndex: nextVerseIndex, segmentIndex: null }
        return
      }

      if (payload.type === 'segment') {
        const nextDragOver =
          target?.type === 'segment'
            ? { verseIndex: target.verseIndex, segmentIndex: target.segmentIndex }
            : { verseIndex: null, segmentIndex: null }
        setDragOver(nextDragOver)
        dragOverRef.current = nextDragOver
      }
    }

    const cleanup = () => {
      window.removeEventListener('pointermove', handleMove)
      window.removeEventListener('pointerup', handleUp)
      window.removeEventListener('pointercancel', handleCancel)
    }

    const activate = () => {
      touchDragTimerRef.current = null
      touchDragSessionRef.current = {
        ...payload,
        pointerId,
        startX,
        startY,
        active: true,
        cleanup,
      }
      setDragState({
        type: payload.type,
        verseIndex: payload.verseIndex,
        segmentIndex: payload.segmentIndex ?? null,
      })
      dragRef.current = {
        type: payload.type,
        verseIndex: payload.verseIndex,
        segmentIndex: payload.segmentIndex ?? null,
      }
      dragDropHandledRef.current = false
      if (payload.type === 'segment' && onBeginSegmentDrag) {
        onBeginSegmentDrag(payload.verseIndex, payload.segmentIndex)
      }
      updateHoverState(startX, startY)
    }

    const finishDrag = (clientX, clientY) => {
      const target = resolveTouchDropTarget(clientX, clientY)
      let handled = false

      if (payload.type === 'verse' && target?.type === 'verse') {
        handled = moveVerseTo(payload.verseIndex, target.verseIndex)
      } else if (payload.type === 'segment') {
        if (target?.type === 'segment') {
          handled = handleSegmentDrop(target.verseIndex, target.segmentIndex)
        } else if (target?.type === 'preview-verse' && onMoveSegmentToVerse) {
          handled = !!onMoveSegmentToVerse(payload.verseIndex, payload.segmentIndex, target.verseIndex)
        } else if (target?.type === 'preview-section' && onMoveSegmentToNewVerse) {
          handled = !!onMoveSegmentToNewVerse(payload.verseIndex, payload.segmentIndex, target.sectionId)
        }
      }

      dragDropHandledRef.current = handled
      clearTouchDragSession()
    }

    const handleMove = (moveEvent) => {
      if (moveEvent.pointerId !== pointerId) {
        return
      }

      const session = touchDragSessionRef.current
      if (!session?.active) {
        if (Math.hypot(moveEvent.clientX - startX, moveEvent.clientY - startY) > TOUCH_DRAG_CANCEL_DISTANCE) {
          clearTouchDragSession()
        }
        return
      }

      moveEvent.preventDefault()
      updateHoverState(moveEvent.clientX, moveEvent.clientY)
    }

    const handleUp = (upEvent) => {
      if (upEvent.pointerId !== pointerId) {
        return
      }
      const isActive = !!touchDragSessionRef.current?.active
      upEvent.preventDefault()
      if (isActive) {
        finishDrag(upEvent.clientX, upEvent.clientY)
      } else {
        clearTouchDragSession()
      }
    }

    const handleCancel = (cancelEvent) => {
      if (cancelEvent.pointerId !== pointerId) {
        return
      }
      clearTouchDragSession()
    }

    touchDragSessionRef.current = {
      ...payload,
      pointerId,
      startX,
      startY,
      active: false,
      cleanup,
    }
    touchDragTimerRef.current = window.setTimeout(activate, TOUCH_DRAG_ACTIVATION_DELAY)
    window.addEventListener('pointermove', handleMove, { passive: false })
    window.addEventListener('pointerup', handleUp)
    window.addEventListener('pointercancel', handleCancel)
  }, [
    clearTouchDragSession,
    handleSegmentDrop,
    moveVerseTo,
    onBeginSegmentDrag,
    onMoveSegmentToNewVerse,
    onMoveSegmentToVerse,
    resolveTouchDropTarget,
  ])

  useEffect(() => () => {
    clearTouchDragSession()
  }, [clearTouchDragSession])

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
    <div className="wpss-verses" onClickCapture={handlePanelClickCapture}>
      {showHeader ? (
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
                onQuickUploadAttachment={onQuickUploadAttachment}
              />
            </details>
          </div>
        </div>
      ) : null}
      <div className="wpss-verses__panel">
        <div className="wpss-verse-group">
          <div className="wpss-verse-group__header">
            <div>
              <strong>
                {safeSections.find((section) => section.id === activeSectionId)?.nombre ||
                  getDefaultSectionName(0)}
              </strong>
              <span className="wpss-verse-group__meta">{visibleVersesInSection.length} versos</span>
            </div>
            <button type="button" className="button button-secondary" onClick={handleAddVerse}>
              Añadir verso
            </button>
          </div>
          {showPreview ? (
            <div className="wpss-section-preview">
              <div className="wpss-section-preview__header">
                <strong>Vista previa</strong>
                <span>
                  {safeSections.find((section) => section.id === activeSectionId)?.nombre ||
                    getDefaultSectionName(0)}
                </span>
              </div>
              {visibleVersesInSection.length ? (
                <ul className="wpss-section-preview__body">
                  {visibleVersesInSection.map(({ verse }, index) => renderPreviewVerse(verse, index))}
                </ul>
              ) : (
                <p className="wpss-empty">Sin versos en esta sección.</p>
              )}
            </div>
          ) : null}
          {visibleVersesInSection.length ? (
            visibleVersesInSection.map(({ verse, index: verseIndex }) => {
              const label = verse.instrumental ? `Instrumental ${verseIndex + 1}` : `Verso ${verseIndex + 1}`
              const segmentos = Array.isArray(verse.segmentos) ? verse.segmentos : []
              const segmentTarget = getValidSegmentIndex(verse.evento_armonico, segmentos.length)
              const eventType = verse.evento_armonico?.tipo || ''
              const templates = eventType ? getEventTemplates(eventType, verseIndex) : []
              const moveTarget = moveTargets[verseIndex] ?? (verse.section_id || activeSectionId || '')
              const activeSegmentIndex =
                selection.verseIndex === verseIndex && selection.segmentIndex !== null
                  ? selection.segmentIndex
                  : null
              const canQuickSplitVerse =
                activeSegmentIndex !== null && canSplitAt(verseIndex, activeSegmentIndex)
              const canQuickSplitSection = canSplitSectionAtVerse(verseIndex)
              const verseIndicators = buildContentIndicators({
                attachments: getVerseAttachments(verseIndex),
                comments: verse?.comentarios,
                midiClips: verse?.midi_clips,
              })
              return (
                <div
                  key={`verse-${verseIndex}`}
                  className={`wpss-verse-card ${dragOver.verseIndex === verseIndex ? 'is-dragover' : ''}`}
                  data-wpss-verse-index={verseIndex}
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
                      const moved = moveVerseTo(fromIndex, verseIndex)
                      dragDropHandledRef.current = moved
                      resetDragState()
                    }
                  }}
                >
                  <div className="wpss-verse-card__header">
	                    <span
	                      className={`wpss-drag-handle ${dragState.type === 'verse' ? 'is-dragging' : ''}`}
	                      draggable
	                      aria-label="Mover verso"
	                      title="Mover verso"
	                      onPointerDown={(event) => {
	                        event.stopPropagation()
	                        beginTouchHandleDrag(event, { type: 'verse', verseIndex })
	                      }}
	                      onContextMenu={(event) => event.preventDefault()}
	                      onDragStart={(event) => {
	                        if (event.dataTransfer) {
	                          event.dataTransfer.setData('text/plain', String(verseIndex))
                        }
                        event.dataTransfer.effectAllowed = 'move'
                        dragDropHandledRef.current = false
                        setDragState({ type: 'verse', verseIndex, segmentIndex: null })
                        dragRef.current = { type: 'verse', verseIndex, segmentIndex: null }
                      }}
                      onDragEnd={() => {
                        const refState = dragRef.current
                        const refOver = dragOverRef.current
                        if (
                          !dragDropHandledRef.current
                          && refState.type === 'verse'
                          && refOver.verseIndex !== null
                        ) {
                          moveVerseTo(refState.verseIndex, refOver.verseIndex)
                        }
                        resetDragState()
                      }}
                    >
                      ☰
                    </span>
                    <strong className="wpss-verse-card__label">
                      <span>{label}</span>
                      <ContentIndicators
                        items={verseIndicators}
                        onSelect={(item) =>
                          onContextIndicatorSelect?.(
                            {
                              type: 'verse',
                              sectionId: verse.section_id || activeSectionId,
                              verseIndex,
                            },
                            item.tabId,
                          )
                        }
                      />
                    </strong>
                    <div className="wpss-verse-actions">
                      {activeSegmentIndex !== null ? (
                        <>
                          <button
                            type="button"
                            className="button button-small"
                            disabled={!canQuickSplitSection}
                            onClick={() =>
                              onSplitSection &&
                              onSplitSection(
                                verseIndex,
                                activeSegmentIndex ?? 0,
                                selection.element,
                              )
                            }
                          >
                            Dividir sección
                          </button>
                          <button
                            type="button"
                            className="button button-small"
                            disabled={!canQuickSplitVerse}
                            onMouseDown={(event) => event.preventDefault()}
                            onClick={() => {
                              const key = `${verseIndex}:${activeSegmentIndex}`
                              const editor = activeSegmentIndex === null ? null : editorsRef.current.get(key)
                              if (editor && onSplitVerse && activeSegmentIndex !== null) {
                                onSplitVerse(verseIndex, activeSegmentIndex, editor)
                              }
                            }}
                          >
                            Cortar verso
                          </button>
                        </>
                      ) : null}
                      {!useContextualToolbar ? (
                        <button
                          type="button"
                          className={`button button-small ${openNotes.has(`verse-${verseIndex}`) ? 'is-active' : ''}`}
                          onClick={() => toggleNotes(`verse-${verseIndex}`)}
                        >
                          Notas
                        </button>
                      ) : null}
                      <button
                        type="button"
                        className={`button button-small ${verse.instrumental ? 'is-active' : ''}`}
                        onClick={() => updateVerse(verseIndex, { ...verse, instrumental: !verse.instrumental })}
                      >
                        Instrumental
                      </button>
                      {!useContextualToolbar ? (
                        <details className="wpss-action-menu">
                          <summary aria-label="Acciones del verso" title="Acciones del verso">⋯</summary>
                          <div className="wpss-action-menu__panel">
                            <InlineMediaQuickActions
                              target={{ anchor_type: 'verse', verse_index: verseIndex }}
                              onUpload={onQuickUploadAttachment}
                            />
                            <label>
                              <span>Mover a sección</span>
                              <select
                                value={moveTarget}
                                onChange={(event) =>
                                  setMoveTargets((prev) => ({ ...prev, [verseIndex]: event.target.value }))
                                }
                              >
                                {safeSections.map((section, index) => (
                                  <option key={`move-${verseIndex}-${section.id}`} value={section.id}>
                                    {section.nombre || getDefaultSectionName(index)}
                                  </option>
                                ))}
                              </select>
                            </label>
                            <button
                              type="button"
                              className="button button-small"
                              onClick={() => handleMoveVerseToSection(verseIndex, moveTarget)}
                              disabled={!moveTarget || moveTarget === (verse.section_id || '')}
                            >
                              Mover verso
                            </button>
                            <button
                              type="button"
                              className="button button-small"
                              onClick={() => handleDuplicateVerse(verseIndex)}
                            >
                              Duplicar verso
                            </button>
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
                        </details>
                      ) : null}
                    </div>
                  </div>
                  <div className="wpss-verse-card__body">
                    {activeSegmentIndex !== null && !useContextualToolbar ? (
                      <div className="wpss-verse-format-toolbar">
                        <span>Formato</span>
                        <button
                          type="button"
                          className="button button-small"
                          onMouseDown={(event) => event.preventDefault()}
                          onClick={() => applyTextFormat('bold', verseIndex, activeSegmentIndex)}
                        >
                          B
                        </button>
                        <button
                          type="button"
                          className="button button-small"
                          onMouseDown={(event) => event.preventDefault()}
                          onClick={() => applyTextFormat('underline', verseIndex, activeSegmentIndex)}
                        >
                          U
                        </button>
                        <button
                          type="button"
                          className="button button-small"
                          onMouseDown={(event) => event.preventDefault()}
                          onClick={() => applyTextFormat('light', verseIndex, activeSegmentIndex)}
                        >
                          Light
                        </button>
                        <button
                          type="button"
                          className="button button-small"
                          onMouseDown={(event) => event.preventDefault()}
                          onClick={() => applyTextFormat('clear', verseIndex, activeSegmentIndex)}
                        >
                          Normal
                        </button>
                      </div>
                    ) : null}
                    <div className="wpss-segment-list">
                      {segmentos.map((segment, segmentIndex) => {
                        const isEventTarget = segmentTarget !== null && segmentTarget === segmentIndex
                        const isEditingSegment =
                          selection.verseIndex === verseIndex && selection.segmentIndex === segmentIndex
                        const chordValue = getChordPreviewValue(segment?.acorde)
                        const segmentChordLabel = (chordValue ? String(chordValue).trim() : '') || '?'
                        const segmentPreviewText = stripHtml(segment?.texto || '').trim() || '...'
                        const chordInputSize = Math.max(2, String(segment?.acorde || '').length + 1)
                        const isChordSuggestionsOpen =
                          chordSuggestionsState.verseIndex === verseIndex
                          && chordSuggestionsState.segmentIndex === segmentIndex
                          && visibleChordSuggestions.length > 0
                        const isDragOver =
                          dragOver.verseIndex === verseIndex && dragOver.segmentIndex === segmentIndex
                        const segmentMoveKey = `${verseIndex}:${segmentIndex}`
                        const segmentMoveTarget = buildSegmentMoveTarget(segmentMoveKey, verse.section_id || activeSectionId)
                        const segmentIndicators = buildContentIndicators({
                          attachments: getSegmentAttachments(verseIndex, segmentIndex),
                          comments: segment?.comentarios,
                          midiClips: segment?.midi_clips,
                        })

                        return (
                          <div
                            key={`segment-${verseIndex}-${segmentIndex}`}
                            className={`wpss-segment ${isEventTarget ? 'is-event-target' : ''} ${
                              isDragOver ? 'is-dragover' : ''
                            } ${isEditingSegment ? 'is-editing' : ''}`}
                            data-wpss-segment-key={`${verseIndex}:${segmentIndex}`}
                            onDragOver={(event) => {
                              if (dragState.type !== 'segment') return
                              event.preventDefault()
                              setDragOver({ verseIndex, segmentIndex })
                              dragOverRef.current = { verseIndex, segmentIndex }
                            }}
                            onDrop={(event) => {
                              event.preventDefault()
                              const moved = handleSegmentDrop(verseIndex, segmentIndex, event)
                              dragDropHandledRef.current = moved
                              resetDragState()
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
	                                onPointerDown={(event) => {
	                                  event.stopPropagation()
	                                  beginTouchHandleDrag(event, { type: 'segment', verseIndex, segmentIndex })
	                                }}
	                                onContextMenu={(event) => event.preventDefault()}
	                                onDragStart={(event) => {
	                                  if (event.dataTransfer) {
	                                    event.dataTransfer.setData('text/plain', `${verseIndex}-${segmentIndex}`)
                                    event.dataTransfer.setData(
                                      'application/x-wpss-segment',
                                      JSON.stringify({ verseIndex, segmentIndex }),
                                    )
                                  }
                                  event.dataTransfer.effectAllowed = 'move'
                                  dragDropHandledRef.current = false
                                  setDragState({ type: 'segment', verseIndex, segmentIndex })
                                  dragRef.current = { type: 'segment', verseIndex, segmentIndex }
                                  if (onBeginSegmentDrag) {
                                    onBeginSegmentDrag(verseIndex, segmentIndex)
                                  }
                                }}
                                onDragEnd={() => {
                                  const refState = dragRef.current
                                  const refOver = dragOverRef.current
                                  if (
                                    !dragDropHandledRef.current
                                    &&
                                    refState.type === 'segment'
                                    && refOver.verseIndex === verseIndex
                                    && refOver.segmentIndex !== null
                                  ) {
                                    handleSegmentDrop(verseIndex, refOver.segmentIndex)
                                  }
                                  resetDragState()
                                  if (onEndSegmentDrag) {
                                    onEndSegmentDrag()
                                  }
                                }}
                              >
                                ⋮⋮
                              </span>
                            </div>
                            <button
                              type="button"
                              className="wpss-segment__summary"
                              onClick={() => {
                                setSelection({
                                  verseIndex,
                                  segmentIndex,
                                  start: null,
                                  end: null,
                                  element: null,
                                })
                                if (onSelectionChange) {
                                  onSelectionChange(verseIndex, segmentIndex, null, null, null)
                                }
                              }}
                            >
                              <strong>{segmentChordLabel}</strong>
                              <span className="wpss-segment__summary-copy">
                                <span>{segmentPreviewText}</span>
                                <ContentIndicators
                                  items={segmentIndicators}
                                  onSelect={(item) =>
                                    onContextIndicatorSelect?.(
                                      {
                                        type: 'segment',
                                        sectionId: verse.section_id || activeSectionId,
                                        verseIndex,
                                        segmentIndex,
                                      },
                                      item.tabId,
                                    )
                                  }
                                />
                              </span>
                            </button>
                            <div className="wpss-segment__fields">
                              <label className="wpss-segment__chord-field">
                                <span>Acorde</span>
                                <input
                                  type="text"
                                  size={chordInputSize}
                                  value={segment.acorde || ''}
                                  ref={(node) => {
                                    const key = `${verseIndex}:${segmentIndex}`
                                    if (!node) {
                                      chordInputRefs.current.delete(key)
                                      return
                                    }
                                    chordInputRefs.current.set(key, node)
                                  }}
                                  onFocus={(event) => {
                                    openChordSuggestions(verseIndex, segmentIndex, event.target.value)
                                  }}
                                  onClick={(event) => {
                                    openChordSuggestions(verseIndex, segmentIndex, event.target.value)
                                  }}
                                  onChange={(event) => {
                                    handleSegmentChange(verseIndex, segmentIndex, 'acorde', event.target.value)
                                    openChordSuggestions(verseIndex, segmentIndex, event.target.value)
                                  }}
                                  onKeyDown={(event) => {
                                    if (!visibleChordSuggestions.length && event.key !== 'ArrowDown') {
                                      if (event.key === 'Escape') {
                                        closeChordSuggestions()
                                      }
                                      return
                                    }
                                    if (event.key === 'ArrowDown') {
                                      event.preventDefault()
                                      if (!visibleChordSuggestions.length) {
                                        openChordSuggestions(verseIndex, segmentIndex, event.currentTarget.value)
                                        return
                                      }
                                      setChordSuggestionsState((prev) => ({
                                        ...prev,
                                        highlightedIndex:
                                          (prev.highlightedIndex + 1) % visibleChordSuggestions.length,
                                      }))
                                      return
                                    }
                                    if (event.key === 'ArrowUp') {
                                      event.preventDefault()
                                      setChordSuggestionsState((prev) => ({
                                        ...prev,
                                        highlightedIndex:
                                          prev.highlightedIndex <= 0
                                            ? visibleChordSuggestions.length - 1
                                            : prev.highlightedIndex - 1,
                                      }))
                                      return
                                    }
                                    if (event.key === 'Enter') {
                                      const suggestion =
                                        visibleChordSuggestions[
                                          Math.max(
                                            0,
                                            Math.min(
                                              chordSuggestionsState.highlightedIndex,
                                              visibleChordSuggestions.length - 1,
                                            ),
                                          )
                                        ]
                                      if (suggestion) {
                                        event.preventDefault()
                                        applyChordSuggestion(verseIndex, segmentIndex, suggestion.value)
                                      }
                                      return
                                    }
                                    if (event.key === 'Escape') {
                                      event.preventDefault()
                                      closeChordSuggestions()
                                    }
                                  }}
                                  onBlur={(event) => {
                                    handleSegmentChordBlur(verseIndex, segmentIndex, event.target.value)
                                    closeChordSuggestions()
                                  }}
                                />
                                {isChordSuggestionsOpen ? (
                                  <div className="wpss-chord-suggestions" role="listbox" aria-label="Sugerencias de acorde">
                                    {visibleChordSuggestions.map((item, suggestionIndex) => (
                                      <button
                                        key={`${item.value}-${suggestionIndex}`}
                                        type="button"
                                        className={`wpss-chord-suggestion ${
                                          chordSuggestionsState.highlightedIndex === suggestionIndex
                                            ? 'is-active'
                                            : ''
                                        }`}
                                        onMouseDown={(event) => {
                                          event.preventDefault()
                                          applyChordSuggestion(verseIndex, segmentIndex, item.value)
                                        }}
                                      >
                                        <strong>{item.display || item.value}</strong>
                                        <span>{item.count > 1 ? `${item.count} usos` : '1 uso'}</span>
                                      </button>
                                    ))}
                                  </div>
                                ) : null}
                              </label>
                              <div>
                                <span>Texto</span>
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
                                  onBlur={(event) =>
                                    handleSegmentTextBlur(
                                      verseIndex,
                                      segmentIndex,
                                      event.currentTarget.innerHTML,
                                      event.currentTarget,
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
                            </div>
                            <div className="wpss-segment__actions">
                              {!useContextualToolbar ? (
                                <button
                                  type="button"
                                  className={`button button-small ${openNotes.has(`segment-${verseIndex}-${segmentIndex}`) ? 'is-active' : ''}`}
                                  onClick={() => toggleNotes(`segment-${verseIndex}-${segmentIndex}`)}
                                >
                                  Notas
                                </button>
                              ) : null}
                              {!useContextualToolbar ? (
                                <details className="wpss-action-menu">
                                  <summary aria-label="Acciones del segmento" title="Acciones del segmento">⋯</summary>
                                  <div className="wpss-action-menu__panel">
                                    <InlineMediaQuickActions
                                      target={{
                                        anchor_type: 'segment',
                                        section_id: verse.section_id || activeSectionId || '',
                                        verse_index: verseIndex,
                                        segment_index: segmentIndex,
                                      }}
                                      onUpload={onQuickUploadAttachment}
                                    />
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
                                      onClick={() => {
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
                                      onClick={() => {
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
                                  <label>
                                    <span>Sección destino</span>
                                    <select
                                      value={segmentMoveTarget.sectionId}
                                      onChange={(event) =>
                                        setSegmentMoveTargets((prev) => ({
                                          ...prev,
                                          [segmentMoveKey]: {
                                            sectionId: event.target.value,
                                            verseValue: '__new__',
                                          },
                                        }))
                                      }
                                    >
                                      {safeSections.map((section, index) => (
                                        <option key={`segment-target-section-${segmentMoveKey}-${section.id}`} value={section.id}>
                                          {section.nombre || getDefaultSectionName(index)}
                                        </option>
                                      ))}
                                    </select>
                                  </label>
                                  <label>
                                    <span>Verso destino</span>
                                    <select
                                      value={segmentMoveTarget.verseValue}
                                      onChange={(event) =>
                                        setSegmentMoveTargets((prev) => ({
                                          ...prev,
                                          [segmentMoveKey]: {
                                            ...segmentMoveTarget,
                                            verseValue: event.target.value,
                                          },
                                        }))
                                      }
                                    >
                                      <option value="__new__">Crear verso nuevo</option>
                                      {safeVerses
                                        .map((targetVerse, targetVerseIndex) => ({ targetVerse, targetVerseIndex }))
                                        .filter(({ targetVerse }) =>
                                          (targetVerse.section_id || '') === segmentMoveTarget.sectionId,
                                        )
                                        .map(({ targetVerseIndex }) => (
                                          <option
                                            key={`segment-target-verse-${segmentMoveKey}-${targetVerseIndex}`}
                                            value={String(targetVerseIndex)}
                                          >
                                            {verseLabelMap.get(targetVerseIndex) || `Verso ${targetVerseIndex + 1}`}
                                          </option>
                                        ))}
                                    </select>
                                  </label>
                                  <button
                                    type="button"
                                    className="button button-small"
                                    onClick={() => {
                                      if (segmentMoveTarget.verseValue === '__new__') {
                                        onMoveSegmentToNewVerse?.(
                                          verseIndex,
                                          segmentIndex,
                                          segmentMoveTarget.sectionId,
                                        )
                                        return
                                      }
                                      const targetVerseIndex = parseInt(segmentMoveTarget.verseValue, 10)
                                      if (!Number.isNaN(targetVerseIndex)) {
                                        onMoveSegmentToVerse?.(verseIndex, segmentIndex, targetVerseIndex)
                                      }
                                    }}
                                  >
                                    Mover segmento
                                  </button>
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
                                  {eventType ? (
                                    <button
                                      type="button"
                                      className={`button button-small ${isEventTarget ? 'is-active' : ''}`}
                                      onClick={() => toggleSegmentEvent(verseIndex, segmentIndex)}
                                    >
                                      {isEventTarget ? 'Desanclar evento' : 'Anclar evento aquí'}
                                    </button>
                                  ) : null}
                                  {eventType === 'modulacion' ? (
                                    <>
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
                                    </>
                                  ) : null}
                                  {eventType === 'prestamo' ? (
                                    <>
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
                                    </>
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
                                </div>
                              </details>
                              ) : null}
                            </div>
                            {!useContextualToolbar && openNotes.has(`segment-${verseIndex}-${segmentIndex}`) ? (
                              <CommentEditor
                                label="Notas del segmento"
                                comments={segment.comentarios || []}
                                defaultTitle={activeSectionName}
                                onChange={(next) =>
                                  handleSegmentCommentsChange(verseIndex, segmentIndex, next)
                                }
                              />
                            ) : null}
                            {!useContextualToolbar ? (
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
                            ) : null}
                          </div>
                        )
                      })}
                      <div className="wpss-segment-add">
                        <button
                          type="button"
                          className="button button-secondary wpss-segment-add__ghost"
                          onClick={() => handleAddSegment(verseIndex)}
                        >
                          + Segmento
                        </button>
                      </div>
                    </div>

                    {!useContextualToolbar && openNotes.has(`verse-${verseIndex}`) ? (
                      <CommentEditor
                        label="Notas del verso"
                        comments={verse.comentarios || []}
                        defaultTitle={activeSectionName}
                        onChange={(next) => handleVerseCommentsChange(verseIndex, next)}
                      />
                    ) : null}
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
