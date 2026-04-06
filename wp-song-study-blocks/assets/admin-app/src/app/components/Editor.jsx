import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'
import {
  formatSegmentsForStackedMode,
  getDefaultSectionName,
  getValidSegmentIndex,
  normalizeSectionsFromApi,
  normalizeStructureFromApi,
  normalizeVerseOrder,
  prepareEventoArmonicoForPayload,
  decodeUnicodeTokens,
  validateEventosArmonicos,
  validateSegments,
} from '../utils.js'
import { createEmptySegment, createEmptySong, createEmptyVerse, createSection } from '../state.js'
import StructurePanel from './StructurePanel.jsx'
import VersesPanel from './VersesPanel.jsx'
import SectionsPanel from './SectionsPanel.jsx'
import CommentEditor from './CommentEditor.jsx'
import MidiClipList from './MidiClipList.jsx'
import InlineMediaQuickActions from './InlineMediaQuickActions.jsx'
import SongMediaPermissionsFields from './SongMediaPermissionsFields.jsx'
import SongVisibilityAccessFields from './SongVisibilityAccessFields.jsx'
import EditorPreviewMediaAttachments from './EditorPreviewMediaAttachments.jsx'
import SongMediaManager from './SongMediaManager.jsx'
import {
  getSectionLevelAttachments,
  getSegmentLevelAttachments,
  getSongLevelAttachments,
  getVerseLevelAttachments,
} from './ReadingMediaAttachments.jsx'

const AUTOSAVE_DELAY = 800
const UNDO_HISTORY_LIMIT = 10
const MOBILE_EDITOR_PREVIEW_QUERY = '(max-width: 840px)'
const PREVIEW_SCALE_LEVELS = [10, 12, 15, 18, 22, 27, 33, 40, 50, 63, 79, 100]
const TAG_SUGGESTIONS_PAGE_SIZE = 10
const TOUCH_DRAG_ACTIVATION_DELAY = 180
const TOUCH_DRAG_CANCEL_DISTANCE = 10

const normalizeTagValue = (value) => String(value || '').trim().replace(/\s+/g, ' ')

const cloneSongSnapshot = (song) => {
  if (typeof structuredClone === 'function') {
    return structuredClone(song)
  }
  return JSON.parse(JSON.stringify(song))
}

const serializeSongSnapshot = (song) => JSON.stringify(song ?? null)

const getNearestPreviewScaleIndex = (value) => {
  if (!Number.isFinite(value)) {
    return PREVIEW_SCALE_LEVELS.length - 1
  }
  let nearestIndex = 0
  let nearestDistance = Number.POSITIVE_INFINITY
  PREVIEW_SCALE_LEVELS.forEach((level, index) => {
    const distance = Math.abs(level - value)
    if (distance < nearestDistance) {
      nearestDistance = distance
      nearestIndex = index
    }
  })
  return nearestIndex
}

const isCompactEditorPreviewViewport = () => {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return false
  }
  return window.matchMedia(MOBILE_EDITOR_PREVIEW_QUERY).matches
}

const getTouchDistance = (touches) => {
  if (!touches || touches.length < 2) {
    return 0
  }
  const first = touches[0]
  const second = touches[1]
  const deltaX = second.clientX - first.clientX
  const deltaY = second.clientY - first.clientY
  return Math.hypot(deltaX, deltaY)
}

const getAttachmentContextKey = (attachment) => {
  if (!attachment || typeof attachment !== 'object') {
    return ''
  }
  const anchorType = String(attachment.anchor_type || 'song')
  if (anchorType === 'segment') {
    return `segment:${Number(attachment.verse_index) || 0}:${Number(attachment.segment_index) || 0}`
  }
  if (anchorType === 'verse') {
    return `verse:${Number(attachment.verse_index) || 0}`
  }
  if (anchorType === 'section') {
    return `section:${String(attachment.section_id || '')}`
  }
  return 'song'
}

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

const formatCollectionAssignment = (collection) => {
  if (!collection || typeof collection !== 'object') return ''
  const assignedBy = collection.assigned_by_user_name || ''
  if (collection.assigned_by_author) {
    return assignedBy ? `Asignado por transcriptor: ${assignedBy}` : 'Asignado por transcriptor'
  }
  return assignedBy ? `Asignado por: ${assignedBy}` : ''
}

const normalizeTagCandidate = (candidate, availableTags = []) => {
  if (candidate === null || typeof candidate === 'undefined') {
    return null
  }

  if (typeof candidate === 'object') {
    const candidateCount = Number.isFinite(Number(candidate?.count)) ? Number(candidate.count) : null
    const fallbackName = String(
      candidate?.name
      || candidate?.nombre
      || candidate?.label
      || candidate?.slug
      || '',
    ).trim()
    const candidateId = Number(candidate?.id ?? candidate?.term_id ?? candidate?.termId)
    if (!fallbackName && !Number.isInteger(candidateId)) {
      return null
    }
    return {
      id: Number.isInteger(candidateId) ? candidateId : null,
      name: fallbackName || `Tag ${candidateId}`,
      slug: String(candidate?.slug || fallbackName || '').trim().toLowerCase(),
      count: candidateCount,
    }
  }

  const rawValue = normalizeTagValue(candidate)
  if (!rawValue) {
    return null
  }

  const numericId = Number(rawValue)
  const hasNumericId = Number.isInteger(numericId) && numericId > 0
  const lowered = rawValue.toLowerCase()
  const existing = availableTags.find((tag) => {
    const tagId = Number(tag?.id ?? tag?.term_id ?? tag?.termId)
    if (hasNumericId && Number.isInteger(tagId) && tagId === numericId) {
      return true
    }
    return (
      String(tag?.slug || '').toLowerCase() === lowered
      || String(tag?.name || tag?.nombre || '').toLowerCase() === lowered
    )
  })

  if (existing) {
    return {
      id: Number.isInteger(Number(existing?.id)) ? Number(existing.id) : null,
      name: String(existing?.name || rawValue).trim(),
      slug: String(existing?.slug || existing?.name || rawValue).trim().toLowerCase(),
      count: Number.isFinite(Number(existing?.count)) ? Number(existing.count) : null,
    }
  }

  return hasNumericId
    ? { id: numericId, name: `Tag ${numericId}`, slug: '', count: 0 }
    : { id: null, name: rawValue, slug: lowered, count: 0 }
}

const getTagLabel = (tag) => String(tag?.name || tag?.nombre || tag?.slug || '').trim()
const getTagKey = (tag) => String(tag?.slug || tag?.name || tag?.id || '').toLowerCase()

const areTagSetsEqual = (first, second) => {
  const left = new Set((Array.isArray(first) ? first : []).map((tag) => getTagKey(tag)).filter(Boolean))
  const right = new Set((Array.isArray(second) ? second : []).map((tag) => getTagKey(tag)).filter(Boolean))
  if (left.size !== right.size) return false
  for (const key of left) {
    if (!right.has(key)) return false
  }
  return true
}

export default function Editor({ onShowList }) {
  const { state, dispatch, api, wpData } = useAppState()
  const [editingSong, setEditingSong] = useState(state.editingSong)
  const [selectedSectionId, setSelectedSectionId] = useState(() => state.ui?.selectedSectionId ?? null)
  const [isSidebarCollapsed] = useState(false)
  const [previewScale, setPreviewScale] = useState(100)
  const [isCompactPreviewViewport, setIsCompactPreviewViewport] = useState(() => isCompactEditorPreviewViewport())
  const [previewRatio, setPreviewRatio] = useState(35)
  const [isResizingPreview, setIsResizingPreview] = useState(false)
  const [selectedVerseIndexes, setSelectedVerseIndexes] = useState(() => new Set())
  const [navLevel, setNavLevel] = useState('sections')
  const [sidebarWidth, setSidebarWidth] = useState(null)
  const [isResizingSidebar, setIsResizingSidebar] = useState(false)
  const [sectionDragIndex, setSectionDragIndex] = useState(null)
  const [sectionDragOverIndex, setSectionDragOverIndex] = useState(null)
  const [verseDragIndex, setVerseDragIndex] = useState(null)
  const [verseDragOverIndex, setVerseDragOverIndex] = useState(null)
  const [expandedSectionId, setExpandedSectionId] = useState(null)
  const [verseFocusRequest, setVerseFocusRequest] = useState(null)
  const [tagInputValue, setTagInputValue] = useState('')
  const [showTagSuggestions, setShowTagSuggestions] = useState(false)
  const [tagSuggestionsPage, setTagSuggestionsPage] = useState(1)
  const [pendingAttachmentActions, setPendingAttachmentActions] = useState({})
  const [selectedAttachmentId, setSelectedAttachmentId] = useState(null)
  const [contextualToolTabsByTarget, setContextualToolTabsByTarget] = useState({})
  const [isContextualToolbarExpanded, setIsContextualToolbarExpanded] = useState(false)
  const [contextualScopeMode, setContextualScopeMode] = useState('auto')
  const [showPreviewAttachments, setShowPreviewAttachments] = useState(true)
  const [isEditorFullscreen, setIsEditorFullscreen] = useState(false)
  const [selectionState, setSelectionState] = useState({
    verse: null,
    segment: null,
    start: null,
    end: null,
    element: null,
  })
  const editingSongRef = useRef(state.editingSong)
  const editorRef = useRef(null)
  const layoutRef = useRef(null)
  const mainSectionRef = useRef(null)
  const sidebarRef = useRef(null)
  const previewScrollRef = useRef(null)
  const workspaceToolsRef = useRef(null)
  const previewSectionRefs = useRef(new Map())
  const autosaveRef = useRef(null)
  const undoHistoryRef = useRef([])
  const selectionRef = useRef({ verse: null, segment: null, start: null, end: null, element: null })
  const lastSilentErrorRef = useRef(null)
  const editingSongSignatureRef = useRef(serializeSongSnapshot(state.editingSong))
  const previewScaleRef = useRef(previewScale)
  const previewPinchRef = useRef({ active: false, startDistance: 0, startScale: 100 })
  const draggingSegmentRef = useRef(null)
  const previewTouchDragSessionRef = useRef(null)
  const previewTouchDragTimerRef = useRef(null)
  const pendingContextualToolbarOpenRef = useRef(null)
  const verseFocusRequestIdRef = useRef(0)
  const loadedSongKeyRef = useRef(state.editingSong?.id ?? '__new__')
  const currentUserId = wpData?.currentUserId || 0
  const preferCompactMidiRows = !!wpData?.isPublicReader
  const midiRangePresets = Array.isArray(wpData?.midiRanges) ? wpData.midiRanges : []
  const midiRangeDefault = wpData?.midiRangeDefault ? String(wpData.midiRangeDefault) : ''
  const lockMidiRange = !!wpData?.isPublicReader
  const canDeleteSong =
    !!editingSong?.id &&
    Number(editingSong?.autor_id) === Number(currentUserId)

  const persistSelectedSection = useCallback(
    (nextId) => {
      setSelectedSectionId(nextId)
      dispatch({
        type: 'SET_STATE',
        payload: { ui: { ...state.ui, selectedSectionId: nextId } },
      })
    },
    [dispatch, state.ui],
  )

  useEffect(() => {
    const nextSongKey = state.editingSong?.id ?? '__new__'
    const shouldResetUndoHistory = nextSongKey !== loadedSongKeyRef.current

    setEditingSong(state.editingSong)
    editingSongRef.current = state.editingSong
    editingSongSignatureRef.current = serializeSongSnapshot(state.editingSong)
    if (shouldResetUndoHistory) {
      undoHistoryRef.current = []
    }
    loadedSongKeyRef.current = nextSongKey
  }, [state.editingSong])

  useEffect(() => {
    editingSongRef.current = editingSong
  }, [editingSong])

  useEffect(() => {
    setTagInputValue('')
    setTagSuggestionsPage(1)
    setShowTagSuggestions(false)
  }, [editingSong.id])

  useEffect(() => {
    if (Array.isArray(state.collections?.items) && state.collections.items.length) {
      return
    }

    api
      .listCollections()
      .then((response) => {
        const items = Array.isArray(response?.data) ? response.data : []
        dispatch({
          type: 'SET_STATE',
          payload: {
            collections: { ...state.collections, items },
          },
        })
      })
      .catch(() => {})
  }, [api, dispatch, state.collections])

  useEffect(() => {
    if (Array.isArray(state.songTags) && state.songTags.length) {
      return
    }

    api
      .listSongTags()
      .then((response) => {
        dispatch({
          type: 'SET_STATE',
          payload: {
            songTags: Array.isArray(response?.data) ? response.data : [],
          },
        })
      })
      .catch(() => {})
  }, [api, dispatch, state.songTags])

  useEffect(() => {
    if (Array.isArray(state.projects) && state.projects.length) {
      return
    }

    api
      .listProjects()
      .then((response) => {
        dispatch({
          type: 'SET_STATE',
          payload: {
            projects: Array.isArray(response?.data) ? response.data : [],
          },
        })
      })
      .catch(() => {})
  }, [api, dispatch, state.projects])

  useEffect(() => {
    return () => {
      if (autosaveRef.current) {
        clearTimeout(autosaveRef.current)
      }
    }
  }, [])

  useEffect(() => {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
      return undefined
    }
    const mediaQuery = window.matchMedia(MOBILE_EDITOR_PREVIEW_QUERY)
    const syncViewport = (isCompact) => {
      setIsCompactPreviewViewport(isCompact)
      if (!isCompact) {
        setPreviewScale(100)
      }
    }
    syncViewport(mediaQuery.matches)
    const onChange = (event) => syncViewport(!!event?.matches)
    if (typeof mediaQuery.addEventListener === 'function') {
      mediaQuery.addEventListener('change', onChange)
      return () => mediaQuery.removeEventListener('change', onChange)
    }
    mediaQuery.addListener(onChange)
    return () => mediaQuery.removeListener(onChange)
  }, [])

  useEffect(() => {
    previewScaleRef.current = previewScale
  }, [previewScale])

  useEffect(() => {
    const secciones = Array.isArray(editingSong.secciones) ? editingSong.secciones : []
    if (!secciones.length) {
      if (selectedSectionId !== null) {
        persistSelectedSection(null)
      }
      return
    }

    if (!selectedSectionId || !secciones.some((section) => section.id === selectedSectionId)) {
      persistSelectedSection(secciones[0].id)
    }
  }, [editingSong.secciones, persistSelectedSection, selectedSectionId])

  useEffect(() => {
    const secciones = Array.isArray(editingSong.secciones) ? editingSong.secciones : []
    if (!secciones.length) {
      return
    }
    const current = Array.isArray(editingSong.estructura) ? editingSong.estructura : []
    const normalized = normalizeStructureFromApi(current, secciones)
    if (!structuresMatch(current, normalized)) {
      updateSong({ ...editingSong, estructura: normalized })
    }
  }, [editingSong.estructura, editingSong.secciones])

  const camposOptions = useMemo(() => {
    const campos = state.camposNames || []
    return [''].concat(campos)
  }, [state.camposNames])

  const updateSong = (updater) => {
    setEditingSong((prev) => {
      const next = typeof updater === 'function' ? updater({ ...prev }) : updater
      const nextSnapshot = cloneSongSnapshot(next)
      const nextSignature = serializeSongSnapshot(nextSnapshot)

      if (nextSignature === editingSongSignatureRef.current) {
        editingSongRef.current = prev
        return prev
      }

      const history = undoHistoryRef.current
      history.push(cloneSongSnapshot(prev))
      if (history.length > UNDO_HISTORY_LIMIT) {
        history.splice(0, history.length - UNDO_HISTORY_LIMIT)
      }

      editingSongRef.current = nextSnapshot
      editingSongSignatureRef.current = nextSignature
      return nextSnapshot
    })
  }

  const availableCollections = Array.isArray(state.collections?.items) ? state.collections.items : []
  const availableProjects = Array.isArray(state.projects) ? state.projects : []
  const selectedRehearsalProjectIds = useMemo(
    () => (Array.isArray(editingSong.rehearsal_project_ids)
      ? Array.from(new Set(editingSong.rehearsal_project_ids.map((item) => Number(item)).filter((item) => Number.isInteger(item) && item > 0)))
      : []),
    [editingSong.rehearsal_project_ids],
  )
  const selectedRehearsalProjects = useMemo(
    () => availableProjects.filter((project) => selectedRehearsalProjectIds.includes(Number(project?.id))),
    [availableProjects, selectedRehearsalProjectIds],
  )
  const mediaPermissionsKey = useMemo(() => {
    const settings = editingSong?.adjuntos_permisos && typeof editingSong.adjuntos_permisos === 'object'
      ? editingSong.adjuntos_permisos
      : {}
    const mode = String(settings.visibility_mode || 'private')
    const groups = Array.isArray(settings.visibility_group_ids) ? settings.visibility_group_ids.join('-') : ''
    const users = Array.isArray(settings.visibility_user_ids) ? settings.visibility_user_ids.join('-') : ''
    return `${editingSong?.id || 'new'}:${mode}:${groups}:${users}`
  }, [editingSong?.adjuntos_permisos, editingSong?.id])
  const availableTags = useMemo(
    () => (Array.isArray(state.songTags) ? state.songTags : [])
      .map((tag) => normalizeTagCandidate(tag, []))
      .filter(Boolean),
    [state.songTags],
  )
  const selectedTags = useMemo(() => {
    const rawTags = Array.isArray(editingSong.tags) ? editingSong.tags : []
    const normalized = []
    const seen = new Set()

    rawTags.forEach((tag) => {
      const normalizedTag = normalizeTagCandidate(tag, availableTags)
      if (!normalizedTag) {
        return
      }
      const key = getTagKey(normalizedTag)
      if (!key || seen.has(key)) {
        return
      }
      seen.add(key)
      normalized.push(normalizedTag)
    })

    return normalized
  }, [availableTags, editingSong.tags])
  const selectedTagKeys = useMemo(
    () => new Set(selectedTags.map((tag) => getTagKey(tag)).filter(Boolean)),
    [selectedTags],
  )
  const tagInputNormalized = useMemo(() => normalizeTagValue(tagInputValue), [tagInputValue])
  const tagInputKey = tagInputNormalized.toLowerCase()
  const tagInputMatchesExisting = useMemo(
    () => !!tagInputKey && availableTags.some((tag) => getTagKey(tag) === tagInputKey),
    [availableTags, tagInputKey],
  )
  const canCreateTag = !!tagInputKey && !tagInputMatchesExisting && !selectedTagKeys.has(tagInputKey)
  const filteredTagSuggestions = useMemo(() => {
    const query = tagInputNormalized.toLowerCase()
    return availableTags
      .filter((tag) => {
        const key = getTagKey(tag)
        if (!key || selectedTagKeys.has(key)) return false
        if (!query) return true
        return (
          String(tag?.name || '').toLowerCase().includes(query)
          || String(tag?.slug || '').toLowerCase().includes(query)
        )
      })
      .sort((a, b) => {
        const countA = Number.isFinite(Number(a?.count)) ? Number(a.count) : 0
        const countB = Number.isFinite(Number(b?.count)) ? Number(b.count) : 0
        if (countA !== countB) return countB - countA
        return String(a?.name || '').localeCompare(String(b?.name || ''), 'es', { sensitivity: 'base' })
      })
  }, [availableTags, selectedTagKeys, tagInputNormalized])
  const totalTagSuggestionPages = useMemo(
    () => Math.max(1, Math.ceil(filteredTagSuggestions.length / TAG_SUGGESTIONS_PAGE_SIZE)),
    [filteredTagSuggestions.length],
  )
  const paginatedTagSuggestions = useMemo(() => {
    const currentPage = Math.min(tagSuggestionsPage, totalTagSuggestionPages)
    const start = (currentPage - 1) * TAG_SUGGESTIONS_PAGE_SIZE
    return filteredTagSuggestions.slice(start, start + TAG_SUGGESTIONS_PAGE_SIZE)
  }, [filteredTagSuggestions, tagSuggestionsPage, totalTagSuggestionPages])

  const buildTagOption = useCallback((value) => {
    const normalizedValue = normalizeTagValue(value)
    if (!normalizedValue) return null
    return normalizeTagCandidate(normalizedValue, availableTags)
  }, [availableTags])

  const parseTagValues = useCallback((values) => {
    const list = Array.isArray(values) ? values : [values]
    return list
      .flatMap((value) => String(value || '').split(','))
      .map((value) => normalizeTagValue(value))
      .filter(Boolean)
  }, [])

  const commitTags = useCallback((values) => {
    const nextItems = parseTagValues(values)
      .map((value) => buildTagOption(value))
      .filter(Boolean)

    if (!nextItems.length) {
      return false
    }

    let changed = false

    updateSong((current) => {
      const currentTags = (Array.isArray(current.tags) ? current.tags : [])
        .map((tag) => normalizeTagCandidate(tag, availableTags))
        .filter(Boolean)
      const seen = new Set(
        currentTags.map((tag) => getTagKey(tag)).filter(Boolean),
      )
      const merged = [...currentTags]

      nextItems.forEach((tag) => {
        const key = getTagKey(tag)
        if (!key || seen.has(key)) {
          return
        }
        seen.add(key)
        merged.push(tag)
        changed = true
      })

      return changed ? { ...current, tags: merged } : current
    })

    if (changed) {
      scheduleAutosave()
    }

    return changed
  }, [availableTags, buildTagOption, parseTagValues])

  const handleTagInputChange = (event) => {
    setTagInputValue(event.target.value || '')
    setTagSuggestionsPage(1)
    setShowTagSuggestions(true)
  }
  const handleTagInputFocus = () => {
    setShowTagSuggestions(true)
  }

  const handleTagInputKeyDown = (event) => {
    if (event.key === 'Enter' || event.key === ',') {
      event.preventDefault()
      if (commitTags(tagInputValue)) {
        setTagInputValue('')
      }
      return
    }

    if (event.key === 'Escape') {
      event.preventDefault()
      setShowTagSuggestions(false)
      if (tagInputValue) {
        setTagInputValue('')
      }
      return
    }

    if (event.key === 'Backspace' && !tagInputValue.trim() && selectedTags.length) {
      event.preventDefault()
      const lastTag = selectedTags[selectedTags.length - 1]
      handleRemoveTag(lastTag)
    }
  }

  const handleTagInputBlur = () => {
    window.setTimeout(() => {
      setShowTagSuggestions(false)
    }, 120)
    if (commitTags(tagInputValue)) {
      setTagInputValue('')
      return
    }
    if (tagInputValue && !tagInputValue.trim()) {
      setTagInputValue('')
    }
  }

  const handleSelectSuggestedTag = (tag) => {
    if (commitTags(getTagLabel(tag))) {
      setTagInputValue('')
      setTagSuggestionsPage(1)
    }
  }

  const handleCreateTagFromInput = () => {
    if (commitTags(tagInputValue)) {
      setTagInputValue('')
      setTagSuggestionsPage(1)
      setShowTagSuggestions(true)
    }
  }

  const handleClearTags = () => {
    if (!selectedTags.length) return
    updateSong((current) => ({ ...current, tags: [] }))
    scheduleAutosave()
  }

  const handleRemoveTag = (tagToRemove) => {
    const removeKey = getTagKey(tagToRemove)
    if (!removeKey) return

    updateSong((current) => {
      const currentTags = (Array.isArray(current.tags) ? current.tags : [])
        .map((tag) => normalizeTagCandidate(tag, availableTags))
        .filter(Boolean)
      const nextTags = currentTags.filter((tag) => getTagKey(tag) !== removeKey)
      if (nextTags.length === currentTags.length) {
        return current
      }
      return { ...current, tags: nextTags }
    })
    scheduleAutosave()
  }

  const handleToggleCollection = (collection) => {
    const collectionId = Number(collection?.id)
    if (!Number.isInteger(collectionId) || collectionId <= 0) {
      return
    }

    updateSong((current) => {
      const currentCollections = Array.isArray(current.colecciones) ? current.colecciones : []
      const exists = currentCollections.some((item) => Number(item?.id) === collectionId)
      const nextCollections = exists
        ? currentCollections.filter((item) => Number(item?.id) !== collectionId)
        : currentCollections.concat({
            id: collectionId,
            nombre: collection?.nombre || `Repertorio #${collectionId}`,
            descripcion: collection?.descripcion || '',
          })
      return { ...current, colecciones: nextCollections }
    })
    scheduleAutosave()
  }

  const handleDeleteSong = () => {
    if (!editingSong?.id || !canDeleteSong) {
      return
    }
    const confirmed = window.confirm(
      `¿Eliminar "${editingSong.titulo || 'esta canción'}"? Esta acción no se puede deshacer.`,
    )
    if (!confirmed) return
    api
      .deleteSong(editingSong.id)
      .then(() => {
        dispatch({
          type: 'SET_STATE',
          payload: {
            editingSong: createEmptySong(),
            selectedSongId: null,
            feedback: { message: 'Canción eliminada.', type: 'success' },
          },
        })
        if (onShowList) {
          onShowList()
        }
      })
      .catch((error) => {
        const message = error?.payload?.message || 'No fue posible eliminar la canción.'
        dispatch({ type: 'SET_STATE', payload: { error: message } })
      })
  }

  const syncLegacyFromSections = (song, sections) => {
    if (!song || !Array.isArray(song.versos)) {
      return
    }

    song.versos.forEach((verso) => {
      verso.fin_de_estrofa = false
      verso.nombre_estrofa = ''
    })

    if (!sections.length) {
      return
    }

    sections.forEach((section, index) => {
      const versosSeccion = song.versos.filter((verso) => verso.section_id === section.id)
      if (!versosSeccion.length) {
        return
      }

      const ultimo = versosSeccion[versosSeccion.length - 1]
      if (!ultimo) {
        return
      }

      if (index < sections.length - 1) {
        ultimo.fin_de_estrofa = true
        ultimo.nombre_estrofa = sections[index + 1].nombre || ''
      }
    })
  }

  const structuresMatch = (current, next) => {
    if (current.length !== next.length) {
      return false
    }
    return current.every((call, index) => {
      const compare = next[index]
      return (
        call?.ref === compare?.ref
        && (call?.variante || '') === (compare?.variante || '')
        && (call?.notas || '') === (compare?.notas || '')
      )
    })
  }

  const ensureSectionsIntegrity = (song, desiredSelected) => {
    let secciones = Array.isArray(song.secciones) ? song.secciones : []
    const used = new Set()

    secciones = secciones.map((seccion, index) => {
      let id = seccion && seccion.id ? String(seccion.id).trim() : ''
      let nombre = seccion && seccion.nombre ? String(seccion.nombre) : ''
      const midiClips = Array.isArray(seccion?.midi_clips) ? seccion.midi_clips : []
      const comentarios = Array.isArray(seccion?.comentarios) ? seccion.comentarios : []

      if (!id) {
        id = createSection('', index).id
      }

      while (used.has(id)) {
        id = createSection('', index).id
      }

      used.add(id)

      if (!nombre.trim()) {
        nombre = getDefaultSectionName(index)
      }

      return {
        id,
        nombre: nombre.slice(0, 64),
        midi_clips: midiClips,
        comentarios,
      }
    })

    if (!secciones.length) {
      secciones = [createSection('', 0)]
    }

    song.secciones = secciones
    const ids = secciones.map((section) => section.id)
    const fallbackId = ids[0]
    const nextSelected = desiredSelected && ids.includes(desiredSelected) ? desiredSelected : fallbackId

    if (Array.isArray(song.versos)) {
      song.versos.forEach((verso) => {
        if (!verso.section_id || !ids.includes(verso.section_id)) {
          verso.section_id = fallbackId
        }
      })
    }

    song.estructura = normalizeStructureFromApi(song.estructura || [], secciones)
    syncLegacyFromSections(song, secciones)
    return nextSelected
  }

  const scheduleAutosave = () => {
    if (autosaveRef.current) {
      clearTimeout(autosaveRef.current)
    }
    dispatch({ type: 'SET_STATE', payload: { saving: true } })
    autosaveRef.current = window.setTimeout(() => {
      autosaveRef.current = null
      saveSong(true)
    }, AUTOSAVE_DELAY)
  }

  const undoLastEdit = () => {
    const previousSnapshot = undoHistoryRef.current.pop()
    if (!previousSnapshot) {
      return false
    }

    if (autosaveRef.current) {
      clearTimeout(autosaveRef.current)
      autosaveRef.current = null
    }

    const restoredSnapshot = cloneSongSnapshot(previousSnapshot)
    const restoredSignature = serializeSongSnapshot(restoredSnapshot)

    setEditingSong(restoredSnapshot)
    editingSongRef.current = restoredSnapshot
    editingSongSignatureRef.current = restoredSignature
    scheduleAutosave()

    return true
  }

  useEffect(() => {
    if (typeof window === 'undefined') {
      return undefined
    }

    const handleUndoKeyDown = (event) => {
      if (!(event.ctrlKey || event.metaKey) || event.altKey || event.shiftKey) {
        return
      }

      if (String(event.key || '').toLowerCase() !== 'z') {
        return
      }

      const target = event.target
      if (editorRef.current && target instanceof Node && !editorRef.current.contains(target)) {
        return
      }

      if (!undoHistoryRef.current.length) {
        return
      }

      event.preventDefault()
      event.stopPropagation()
      undoLastEdit()
    }

    window.addEventListener('keydown', handleUndoKeyDown, true)
    return () => window.removeEventListener('keydown', handleUndoKeyDown, true)
  })

  const saveSong = (silent = false) => {
    const currentSong = editingSongRef.current
    const normalizeMidiClipsForSave = (clips) => {
      if (!Array.isArray(clips)) return []
      return clips.map((clip) => {
        if (!clip || typeof clip !== 'object') return clip
        const name = clip.name ? decodeUnicodeTokens(clip.name) : clip.name
        return name === clip.name ? clip : { ...clip, name }
      })
    }

    const normalizeSegmentForSave = (segment) => {
      if (!segment || typeof segment !== 'object') return segment
      const midiClips = normalizeMidiClipsForSave(segment.midi_clips)
      return midiClips === segment.midi_clips ? segment : { ...segment, midi_clips: midiClips }
    }

    const warnSilent = (message) => {
      if (!silent) {
        dispatch({ type: 'SET_STATE', payload: { error: message } })
        return
      }
      const warning = `Autosave pausado: ${message}`
      if (lastSilentErrorRef.current === warning) {
        return
      }
      lastSilentErrorRef.current = warning
      dispatch({
        type: 'SET_STATE',
        payload: { feedback: { message: warning, type: 'warning' }, error: null },
      })
    }

    if (!currentSong.titulo.trim()) {
      warnSilent(wpData?.strings?.titleRequired || 'El título es obligatorio.')
      return
    }

    const segmentError = validateSegments(currentSong.versos, wpData?.strings)
    if (segmentError) {
      warnSilent(segmentError)
      return
    }

    const eventError = validateEventosArmonicos(currentSong.versos, wpData?.strings)
    if (eventError) {
      warnSilent(eventError)
      return
    }

    const estructuraPayload = normalizeStructureFromApi(currentSong.estructura || [], currentSong.secciones || [])
    const songFromList = Array.isArray(state.songs)
      ? state.songs.find((item) => Number(item?.id) === Number(currentSong.id))
      : null
    const currentSongTags = Array.isArray(currentSong.tags) ? currentSong.tags : []
    const fallbackSongTags = Array.isArray(songFromList?.tags) ? songFromList.tags : []
    const normalizedCurrentTags = currentSongTags
      .map((item) => normalizeTagCandidate(item, availableTags))
      .filter(Boolean)
    const normalizedFallbackTags = fallbackSongTags
      .map((item) => normalizeTagCandidate(item, availableTags))
      .filter(Boolean)
    const resolvedTagsForPayload = normalizedCurrentTags.length
      ? normalizedCurrentTags
      : normalizedFallbackTags

    const payload = {
        id: currentSong.id || null,
        titulo: currentSong.titulo,
        bpm: currentSong.bpm,
        tonica: currentSong.tonica,
      campo_armonico: currentSong.campo_armonico,
      campo_armonico_predominante: currentSong.campo_armonico_predominante,
      ficha_autores: currentSong.ficha_autores || '',
      ficha_anio: currentSong.ficha_anio || '',
      ficha_pais: currentSong.ficha_pais || '',
      ficha_estado_legal: currentSong.ficha_estado_legal || '',
      ficha_licencia: currentSong.ficha_licencia || '',
      ficha_fuente_verificacion: currentSong.ficha_fuente_verificacion || '',
      ficha_incompleta: !!currentSong.ficha_incompleta,
      ficha_incompleta_motivo: currentSong.ficha_incompleta_motivo || '',
      visibility_mode: currentSong.visibility_mode || 'private',
      visibility_project_ids: Array.isArray(currentSong.visibility_project_ids)
        ? Array.from(new Set(currentSong.visibility_project_ids.map((item) => Number(item)).filter((item) => Number.isInteger(item) && item > 0)))
        : [],
      visibility_group_ids: Array.isArray(currentSong.visibility_group_ids)
        ? Array.from(new Set(currentSong.visibility_group_ids.map((item) => Number(item)).filter((item) => Number.isInteger(item) && item > 0)))
        : [],
      visibility_user_ids: Array.isArray(currentSong.visibility_user_ids)
        ? Array.from(new Set(currentSong.visibility_user_ids.map((item) => Number(item)).filter((item) => Number.isInteger(item) && item > 0)))
        : [],
      rehearsal_project_ids: Array.isArray(currentSong.rehearsal_project_ids)
        ? Array.from(new Set(currentSong.rehearsal_project_ids.map((item) => Number(item)).filter((item) => Number.isInteger(item) && item > 0)))
        : [],
      prestamos_cancion: currentSong.prestamos,
      modulaciones_cancion: currentSong.modulaciones,
      secciones: Array.isArray(currentSong.secciones)
        ? currentSong.secciones.map((section) => ({
            ...section,
            midi_clips: normalizeMidiClipsForSave(section.midi_clips),
          }))
        : [],
      versos: currentSong.versos.map((verso) => {
        const segmentos = Array.isArray(verso.segmentos)
          ? verso.segmentos.map((segment) => normalizeSegmentForSave(segment))
          : []
        const evento = prepareEventoArmonicoForPayload(verso.evento_armonico, segmentos.length)

        return {
          orden: verso.orden,
          segmentos,
          comentario: verso.comentario,
          comentarios: Array.isArray(verso.comentarios) ? verso.comentarios : [],
          evento_armonico: evento,
          instrumental: !!verso.instrumental,
          midi_clips: normalizeMidiClipsForSave(verso.midi_clips),
          section_id: verso.section_id || '',
          fin_de_estrofa: !!verso.fin_de_estrofa,
          nombre_estrofa: verso.fin_de_estrofa ? verso.nombre_estrofa || '' : '',
        }
      }),
      colecciones: Array.isArray(currentSong.colecciones) ? currentSong.colecciones.map((item) => item.id) : [],
      tags: resolvedTagsForPayload.map((item) => item.id || item.name || item.slug),
      adjuntos: Array.isArray(currentSong.adjuntos) ? currentSong.adjuntos : [],
      adjuntos_permisos: currentSong.adjuntos_permisos || { visibility_mode: 'private', visibility_group_ids: [], visibility_user_ids: [] },
      estructura: estructuraPayload,
      estructura_personalizada: true,
    }

    api
      .saveSong(payload)
      .then((response) => {
        if (silent) {
          dispatch({ type: 'SET_STATE', payload: { saving: false } })
        }
        if (silent && lastSilentErrorRef.current && state.feedback?.message === lastSilentErrorRef.current) {
          lastSilentErrorRef.current = null
          dispatch({ type: 'SET_STATE', payload: { feedback: null } })
        }
        const body = response.data || {}
        const bpmDefault = Number.isInteger(parseInt(body.bpm, 10))
          ? parseInt(body.bpm, 10)
          : currentSong.bpm
        const secciones = normalizeSectionsFromApi(body.secciones || currentSong.secciones, bpmDefault)
        const estructura = normalizeStructureFromApi(body.estructura || [], secciones)
    const normalizedBodyTags = Array.isArray(body.tags)
      ? body.tags.map((item) => normalizeTagCandidate(item, availableTags)).filter(Boolean)
      : []
        const fallbackSong = currentSong && typeof currentSong === 'object' ? currentSong : editingSong
        const fallbackAttachmentPermissions =
          fallbackSong?.adjuntos_permisos && typeof fallbackSong.adjuntos_permisos === 'object'
            ? fallbackSong.adjuntos_permisos
            : { visibility_mode: 'private', visibility_group_ids: [], visibility_user_ids: [] }
        const normalizedSong = {
          ...fallbackSong,
          id: body.id || fallbackSong?.id || null,
          autor_id: body.autor_id || fallbackSong?.autor_id || null,
          autor_nombre: body.autor_nombre || fallbackSong?.autor_nombre || '',
          es_reversion: body.es_reversion !== undefined ? !!body.es_reversion : !!fallbackSong?.es_reversion,
          reversion_origen_id: body.reversion_origen_id || fallbackSong?.reversion_origen_id || null,
          reversion_origen_titulo:
            body.reversion_origen_titulo || fallbackSong?.reversion_origen_titulo || '',
          reversion_raiz_id: body.reversion_raiz_id || fallbackSong?.reversion_raiz_id || null,
          reversion_raiz_titulo: body.reversion_raiz_titulo || fallbackSong?.reversion_raiz_titulo || '',
          reversion_autor_origen_id:
            body.reversion_autor_origen_id || fallbackSong?.reversion_autor_origen_id || null,
          reversion_autor_origen_nombre:
            body.reversion_autor_origen_nombre || fallbackSong?.reversion_autor_origen_nombre || '',
          estado_transcripcion:
            body.estado_transcripcion || fallbackSong?.estado_transcripcion || 'sin_iniciar',
          estado_transcripcion_label:
            body.estado_transcripcion_label || fallbackSong?.estado_transcripcion_label || 'Sin iniciar',
          estado_ensayo: body.estado_ensayo || fallbackSong?.estado_ensayo || 'sin_ensayar',
          estado_ensayo_label:
            body.estado_ensayo_label || fallbackSong?.estado_ensayo_label || 'No ensayada',
          bpm: bpmDefault,
          visibility_mode: body.visibility_mode || fallbackSong?.visibility_mode || 'private',
          visibility_project_ids: Array.isArray(body.visibility_project_ids)
            ? body.visibility_project_ids
            : (fallbackSong?.visibility_project_ids || []),
          visibility_projects: Array.isArray(body.visibility_projects)
            ? body.visibility_projects
            : (fallbackSong?.visibility_projects || []),
          visibility_group_ids: Array.isArray(body.visibility_group_ids)
            ? body.visibility_group_ids
            : (fallbackSong?.visibility_group_ids || []),
          visibility_groups: Array.isArray(body.visibility_groups)
            ? body.visibility_groups
            : (fallbackSong?.visibility_groups || []),
          visibility_user_ids: Array.isArray(body.visibility_user_ids)
            ? body.visibility_user_ids
            : (fallbackSong?.visibility_user_ids || []),
          visibility_users: Array.isArray(body.visibility_users)
            ? body.visibility_users
            : (fallbackSong?.visibility_users || []),
          rehearsal_project_ids: Array.isArray(body.rehearsal_project_ids)
            ? body.rehearsal_project_ids
            : (fallbackSong?.rehearsal_project_ids || []),
          rehearsal_projects: Array.isArray(body.rehearsal_projects)
            ? body.rehearsal_projects
            : (fallbackSong?.rehearsal_projects || []),
          can_upload_rehearsals:
            body.can_upload_rehearsals !== undefined
              ? !!body.can_upload_rehearsals
              : !!fallbackSong?.can_upload_rehearsals,
          tags: normalizedBodyTags.length ? normalizedBodyTags : resolvedTagsForPayload,
          adjuntos: Array.isArray(body.adjuntos)
            ? body.adjuntos
            : (Array.isArray(fallbackSong?.adjuntos) ? fallbackSong.adjuntos : []),
          adjuntos_permisos:
            body.adjuntos_permisos && typeof body.adjuntos_permisos === 'object'
              ? body.adjuntos_permisos
              : fallbackAttachmentPermissions,
          secciones,
          estructura,
          estructuraPersonalizada: true,
        }
        const listSongPatch = {
          ...songFromList,
          ...normalizedSong,
          id: body.id,
          titulo: normalizedSong.titulo || body.titulo || songFromList?.titulo || '',
          tonica: normalizedSong.tonica || body.tonica || songFromList?.tonica || '',
          bpm: normalizedSong.bpm,
          tags: normalizedSong.tags,
          colecciones: Array.isArray(currentSong.colecciones) ? currentSong.colecciones : (songFromList?.colecciones || []),
          visibility_mode: normalizedSong.visibility_mode,
          visibility_project_ids: normalizedSong.visibility_project_ids,
          visibility_projects: normalizedSong.visibility_projects,
          visibility_group_ids: normalizedSong.visibility_group_ids,
          visibility_groups: normalizedSong.visibility_groups,
          visibility_user_ids: normalizedSong.visibility_user_ids,
          visibility_users: normalizedSong.visibility_users,
          rehearsal_project_ids: normalizedSong.rehearsal_project_ids,
          rehearsal_projects: normalizedSong.rehearsal_projects,
        }
        const updatedSongs = Array.isArray(state.songs)
          ? (() => {
              const targetId = Number(body.id || currentSong.id || 0)
              const exists = state.songs.some((item) => Number(item?.id) === targetId)
              if (!exists) {
                return [listSongPatch].concat(state.songs)
              }
              return state.songs.map((item) => (Number(item?.id) === targetId ? { ...item, ...listSongPatch } : item))
            })()
          : state.songs

        const shouldSync = !silent || !editingSong.id || body.id !== editingSong.id

        if (shouldSync) {
          dispatch({
            type: 'SET_STATE',
            payload: {
              selectedSongId: body.id,
              editingSong: normalizedSong,
              songs: updatedSongs,
              feedback: !silent
                ? { message: wpData?.strings?.saved || 'Cambios guardados.', type: 'success' }
                : null,
              error: null,
            },
          })
          setEditingSong(normalizedSong)
        } else {
          setEditingSong(normalizedSong)
          dispatch({
            type: 'SET_STATE',
            payload: {
              selectedSongId: body.id,
              editingSong: normalizedSong,
              songs: updatedSongs,
              feedback: null,
              error: null,
            },
          })
        }

        const tagsChanged = !areTagSetsEqual(normalizedBodyTags, normalizedFallbackTags)
        if (tagsChanged) {
          api
            .listSongTags()
            .then((listResponse) => {
              dispatch({
                type: 'SET_STATE',
                payload: { songTags: Array.isArray(listResponse?.data) ? listResponse.data : [] },
              })
            })
            .catch(() => {})
        }
      })
      .catch((error) => {
        if (silent) {
          dispatch({ type: 'SET_STATE', payload: { saving: false } })
          return
        }
        const message = error?.payload?.message || wpData?.strings?.error || 'Ocurrió un error al guardar.'
        dispatch({ type: 'SET_STATE', payload: { error: message } })
      })
  }

  const handleOpenReadingView = () => {
    dispatch({
      type: 'SET_STATE',
      payload: {
        editingSong: editingSongRef.current,
        activeTab: 'reading',
      },
    })
  }

  const updateSegmentSelection = (verseIndex, segmentIndex, start, end, element) => {
    const nextSelection = {
      verse: verseIndex,
      segment: segmentIndex,
      start,
      end,
      element,
    }
    selectionRef.current = nextSelection
    setSelectionState(nextSelection)
  }

  const requestVerseSegmentFocus = useCallback((verseIndex, segmentIndex = 0, selectAll = true) => {
    verseFocusRequestIdRef.current += 1
    const requestId = verseFocusRequestIdRef.current
    setVerseFocusRequest({ requestId, verseIndex, segmentIndex, selectAll })
    selectionRef.current = {
      verse: verseIndex,
      segment: segmentIndex,
      start: 0,
      end: 0,
      element: null,
    }
  }, [])

  const handleVerseFocusHandled = useCallback((requestId) => {
    setVerseFocusRequest((prev) => (prev?.requestId === requestId ? null : prev))
  }, [])

  const handleQuickUploadAttachment = useCallback(async (target, mode, file) => {
    if (!target || typeof target !== 'object') {
      return
    }
    if (!file) {
      return
    }

    const currentSong = editingSongRef.current
    if (!currentSong?.id) {
      const message = 'Primero guarda la canción para poder adjuntar audios o fotos.'
      dispatch({ type: 'SET_STATE', payload: { error: message } })
      return
    }

    let resolvedTarget = {
      anchor_type: String(target.anchor_type || 'song'),
      section_id: String(target.section_id || ''),
      verse_index: Number(target.verse_index) || 0,
      segment_index: Number(target.segment_index) || 0,
    }

    if (resolvedTarget.anchor_type === 'segment' && !resolvedTarget.section_id) {
      const verse = Array.isArray(currentSong?.versos)
        ? currentSong.versos[resolvedTarget.verse_index]
        : null
      resolvedTarget = {
        ...resolvedTarget,
        section_id: String(verse?.section_id || selectedSectionId || ''),
      }
    }

    if (resolvedTarget.anchor_type === 'section' && !resolvedTarget.section_id) {
      resolvedTarget = {
        ...resolvedTarget,
        section_id: String(selectedSectionId || ''),
      }
    }

    const type = mode === 'importPhoto' || mode === 'capturePhoto' ? 'photo' : 'audio'
    const sourceKind = mode === 'recordAudio'
      ? 'recording'
      : mode === 'capturePhoto'
        ? 'capture'
        : 'import'
    const mediaPermissions = currentSong?.adjuntos_permisos || {}

    const formData = new FormData()
    formData.append('song_id', String(currentSong.id))
    formData.append('title', String(file.name || `${type}-${Date.now()}`))
    formData.append('type', type)
    formData.append('source_kind', sourceKind)
    formData.append('anchor_type', resolvedTarget.anchor_type)
    formData.append(
      'section_id',
      resolvedTarget.anchor_type === 'section' || resolvedTarget.anchor_type === 'segment'
        ? (resolvedTarget.section_id || '')
        : '',
    )
    formData.append(
      'verse_index',
      String(
        resolvedTarget.anchor_type === 'verse' || resolvedTarget.anchor_type === 'segment'
          ? resolvedTarget.verse_index
          : 0,
      ),
    )
    formData.append(
      'segment_index',
      String(resolvedTarget.anchor_type === 'segment' ? resolvedTarget.segment_index : 0),
    )
    formData.append('visibility_mode', String(mediaPermissions.visibility_mode || 'private'))
    formData.append(
      'visibility_group_ids',
      JSON.stringify(Array.isArray(mediaPermissions.visibility_group_ids) ? mediaPermissions.visibility_group_ids : []),
    )
    formData.append(
      'visibility_user_ids',
      JSON.stringify(Array.isArray(mediaPermissions.visibility_user_ids) ? mediaPermissions.visibility_user_ids : []),
    )
    formData.append('duration_seconds', '0')
    formData.append('file', file)

    try {
      const busyKey = `upload-${Date.now()}`
      setPendingAttachmentActions((prev) => ({
        ...prev,
        [busyKey]: 'Subiendo a Drive…',
      }))
      const response = await api.uploadSongAttachment(formData)
      const attachments = Array.isArray(response?.data?.adjuntos) ? response.data.adjuntos : []
      updateSong((prev) => ({ ...prev, adjuntos: attachments }))
      dispatch({
        type: 'SET_STATE',
        payload: {
          feedback: { message: response?.data?.message || 'Adjunto subido a Google Drive.', type: 'success' },
          error: null,
        },
      })
    } catch (requestError) {
      const message = requestError?.payload?.message || 'No fue posible subir el adjunto.'
      dispatch({ type: 'SET_STATE', payload: { error: message } })
      throw requestError
    } finally {
      setPendingAttachmentActions((prev) => {
        const next = { ...prev }
        Object.keys(next).forEach((key) => {
          if (key.startsWith('upload-')) {
            delete next[key]
          }
        })
        return next
      })
    }
  }, [api, dispatch, selectedSectionId])

  const handlePreviewUnlinkAttachment = useCallback(async (attachment) => {
    const attachmentId = attachment?.id
    const songId = editingSongRef.current?.id
    if (!songId || !attachmentId) return

    try {
      setPendingAttachmentActions((prev) => ({ ...prev, [attachmentId]: 'Quitando de la canción…' }))
      const response = await api.unlinkSongAttachment(songId, attachmentId)
      const attachments = Array.isArray(response?.data?.adjuntos) ? response.data.adjuntos : []
      updateSong((prev) => ({ ...prev, adjuntos: attachments }))
      setSelectedAttachmentId((prev) => (String(prev || '') === String(attachmentId) ? null : prev))
      dispatch({
        type: 'SET_STATE',
        payload: {
          feedback: { message: response?.data?.message || 'Adjunto quitado de la canción.', type: 'success' },
          error: null,
        },
      })
    } catch (requestError) {
      const message = requestError?.payload?.message || 'No fue posible quitar el adjunto.'
      dispatch({ type: 'SET_STATE', payload: { error: message } })
    } finally {
      setPendingAttachmentActions((prev) => {
        const next = { ...prev }
        delete next[attachmentId]
        return next
      })
    }
  }, [api, dispatch])

  const handlePreviewRenameAttachment = useCallback(async (attachment) => {
    const attachmentId = attachment?.id
    const songId = editingSongRef.current?.id
    if (!songId || !attachmentId) return

    const currentTitle = String(attachment?.title || attachment?.file_name || '').trim()
    const nextTitle = window.prompt('Nuevo nombre del adjunto', currentTitle)
    if (nextTitle === null) return

    const normalizedTitle = String(nextTitle).trim()
    if (!normalizedTitle) {
      dispatch({ type: 'SET_STATE', payload: { error: 'El adjunto necesita un nombre.' } })
      return
    }
    if (normalizedTitle === currentTitle) return

    try {
      setPendingAttachmentActions((prev) => ({ ...prev, [attachmentId]: 'Renombrando en Drive…' }))
      const response = await api.updateSongAttachment(songId, attachmentId, {
        title: normalizedTitle,
        type: attachment?.type || 'audio',
        source_kind: attachment?.source_kind || 'import',
        anchor_type: attachment?.anchor_type || 'song',
        section_id:
          attachment?.anchor_type === 'section' || attachment?.anchor_type === 'segment'
            ? (attachment?.section_id || '')
            : '',
        verse_index:
          attachment?.anchor_type === 'verse' || attachment?.anchor_type === 'segment'
            ? Number(attachment?.verse_index) || 0
            : 0,
        segment_index: attachment?.anchor_type === 'segment' ? Number(attachment?.segment_index) || 0 : 0,
        duration_seconds: Number(attachment?.duration_seconds) || 0,
      })
      const attachments = Array.isArray(response?.data?.adjuntos) ? response.data.adjuntos : []
      updateSong((prev) => ({ ...prev, adjuntos: attachments }))
      dispatch({
        type: 'SET_STATE',
        payload: {
          feedback: { message: response?.data?.message || 'Adjunto renombrado.', type: 'success' },
          error: null,
        },
      })
    } catch (requestError) {
      const message = requestError?.payload?.message || 'No fue posible renombrar el adjunto.'
      dispatch({ type: 'SET_STATE', payload: { error: message } })
    } finally {
      setPendingAttachmentActions((prev) => {
        const next = { ...prev }
        delete next[attachmentId]
        return next
      })
    }
  }, [api, dispatch])

  const handlePreviewDeleteAttachment = useCallback(async (attachment) => {
    const attachmentId = attachment?.id
    const songId = editingSongRef.current?.id
    if (!songId || !attachmentId) return

    const confirmed = window.confirm(
      `¿Eliminar definitivamente "${attachment.title || attachment.file_name || attachment.id}" del Google Drive?`,
    )
    if (!confirmed) return

    try {
      setPendingAttachmentActions((prev) => ({ ...prev, [attachmentId]: 'Eliminando de Drive…' }))
      const response = await api.deleteSongAttachment(songId, attachmentId)
      const attachments = Array.isArray(response?.data?.adjuntos) ? response.data.adjuntos : []
      updateSong((prev) => ({ ...prev, adjuntos: attachments }))
      setSelectedAttachmentId((prev) => (String(prev || '') === String(attachmentId) ? null : prev))
      dispatch({
        type: 'SET_STATE',
        payload: {
          feedback: { message: response?.data?.message || 'Adjunto eliminado del Drive.', type: 'success' },
          error: null,
        },
      })
    } catch (requestError) {
      const message = requestError?.payload?.message || 'No fue posible eliminar el adjunto del Drive.'
      dispatch({ type: 'SET_STATE', payload: { error: message } })
    } finally {
      setPendingAttachmentActions((prev) => {
        const next = { ...prev }
        delete next[attachmentId]
        return next
      })
    }
  }, [api, dispatch])

  const splitSegment = (verseIndex, segmentIndex, textarea) => {
    if (!textarea?.isContentEditable) {
      updateSegmentSelection(
        verseIndex,
        segmentIndex,
        textarea?.selectionStart ?? null,
        textarea?.selectionEnd ?? null,
        textarea ?? null,
      )
    }

    const selection = selectionRef.current
    const verse = editingSong.versos[verseIndex]
    if (!verse || !verse.segmentos[segmentIndex]) {
      return
    }

    if (!textarea?.isContentEditable && (selection.start === null || selection.start !== selection.end)) {
      return
    }

    const segment = verse.segmentos[segmentIndex]

    const splitHtml = splitSegmentHtml(textarea)
    if (!splitHtml) {
      return
    }
    const { beforeHtml, afterHtml, textLength, cursor } = splitHtml

    if (cursor <= 0 || cursor >= textLength) {
      return
    }

    segment.texto = beforeHtml
    const nuevo = {
      texto: afterHtml,
      acorde: segment.acorde,
    }

    const current = getValidSegmentIndex(verse.evento_armonico, verse.segmentos.length)
    verse.segmentos.splice(segmentIndex + 1, 0, nuevo)
    if (verse.evento_armonico && current !== null && current > segmentIndex) {
      verse.evento_armonico.segment_index = current + 1
    }

    updateSong({ ...editingSong })
    scheduleAutosave()
  }

  const splitVerseFromCursor = (verseIndex, segmentIndex, textarea) => {
    if (!textarea?.isContentEditable) {
      updateSegmentSelection(
        verseIndex,
        segmentIndex,
        textarea?.selectionStart ?? null,
        textarea?.selectionEnd ?? null,
        textarea ?? null,
      )
    }

    const selection = selectionRef.current
    const sourceVerses = Array.isArray(editingSong.versos) ? editingSong.versos : []
    const sourceVerse = sourceVerses[verseIndex]
    if (!sourceVerse || !sourceVerse.segmentos[segmentIndex]) {
      return
    }

    if (!textarea?.isContentEditable && (selection.start === null || selection.start !== selection.end)) {
      return
    }

    const splitHtml = splitSegmentHtml(textarea)
    if (!splitHtml) {
      return
    }
    const { beforeHtml, afterHtml, textLength, cursor } = splitHtml

    if (cursor < 0 || cursor > textLength) {
      return
    }

    const nextVerses = sourceVerses.map((verse) => ({
      ...verse,
      segmentos: Array.isArray(verse.segmentos) ? [...verse.segmentos] : [],
    }))
    const verse = nextVerses[verseIndex]
    const segment = { ...(verse.segmentos[segmentIndex] || createEmptySegment()) }
    segment.texto = beforeHtml
    verse.segmentos[segmentIndex] = segment

    const newSegments = []
    if (afterHtml) {
      newSegments.push({ texto: afterHtml, acorde: segment.acorde })
    }

    for (let i = segmentIndex + 1; i < verse.segmentos.length; i += 1) {
      newSegments.push({ ...verse.segmentos[i] })
    }

    verse.segmentos = verse.segmentos.slice(0, segmentIndex + 1)

    const nuevoVerso = createEmptyVerse(verse.orden + 1, verse.section_id)
    nuevoVerso.instrumental = !!verse.instrumental
    nuevoVerso.segmentos = newSegments.length ? newSegments : [createEmptySegment()]

    if (verse.evento_armonico && Object.prototype.hasOwnProperty.call(verse.evento_armonico, 'segment_index')) {
      const currentIndex = getValidSegmentIndex(verse.evento_armonico, verse.segmentos.length)
      if (currentIndex !== null && currentIndex > segmentIndex) {
        const movedEvent = { ...verse.evento_armonico }
        const offset = segmentIndex + 1
        movedEvent.segment_index = currentIndex - offset
        nuevoVerso.evento_armonico = movedEvent
        delete verse.evento_armonico.segment_index
      }
    }

    nextVerses.splice(verseIndex + 1, 0, nuevoVerso)
    normalizeVerseOrder(nextVerses)
    updateSong({ ...editingSong, versos: nextVerses })
    scheduleAutosave()
  }

  const splitSectionFromCursor = (verseIndex, segmentIndex, textarea) => {
    if (!textarea?.isContentEditable) {
      updateSegmentSelection(
        verseIndex,
        segmentIndex,
        textarea?.selectionStart ?? null,
        textarea?.selectionEnd ?? null,
        textarea ?? null,
      )
    }

    const selection = selectionRef.current
    const verse = editingSong.versos[verseIndex]
    if (!verse || !verse.segmentos[segmentIndex]) {
      return
    }

    if (!textarea?.isContentEditable && (selection.start === null || selection.start !== selection.end)) {
      return
    }

    const segment = verse.segmentos[segmentIndex]
    const splitHtml = splitSegmentHtml(textarea)
    if (!splitHtml) {
      return
    }
    const { beforeHtml, afterHtml, textLength, cursor } = splitHtml

    if (cursor < 0 || cursor > textLength) {
      return
    }

    const sections = Array.isArray(editingSong.secciones) ? editingSong.secciones : []
    const currentSectionId = verse.section_id || sections[0]?.id
    const currentSectionIndex = sections.findIndex((section) => section.id === currentSectionId)
    const insertIndex = currentSectionIndex >= 0 ? currentSectionIndex + 1 : sections.length
    const newSection = createSection('', insertIndex)
    editingSong.secciones.splice(insertIndex, 0, newSection)

    const assignSectionFromIndex = (startIndex) => {
      for (let i = startIndex; i < editingSong.versos.length; i += 1) {
        if (editingSong.versos[i].section_id === currentSectionId) {
          editingSong.versos[i].section_id = newSection.id
        }
      }
    }

    if (cursor <= 0) {
      assignSectionFromIndex(verseIndex)
    } else if (cursor >= textLength) {
      assignSectionFromIndex(verseIndex + 1)
    } else {
      segment.texto = beforeHtml

      const newSegments = []
      if (afterHtml) {
        newSegments.push({ texto: afterHtml, acorde: segment.acorde })
      }
      for (let i = segmentIndex + 1; i < verse.segmentos.length; i += 1) {
        newSegments.push({ ...verse.segmentos[i] })
      }
      verse.segmentos = verse.segmentos.slice(0, segmentIndex + 1)

      const nuevoVerso = createEmptyVerse(verse.orden + 1, newSection.id)
      nuevoVerso.instrumental = !!verse.instrumental
      nuevoVerso.segmentos = newSegments.length ? newSegments : [createEmptySegment()]

      if (verse.evento_armonico && Object.prototype.hasOwnProperty.call(verse.evento_armonico, 'segment_index')) {
        const currentIndex = getValidSegmentIndex(verse.evento_armonico, verse.segmentos.length)
        if (currentIndex !== null && currentIndex > segmentIndex) {
          const movedEvent = { ...verse.evento_armonico }
          const offset = segmentIndex + 1
          movedEvent.segment_index = currentIndex - offset
          nuevoVerso.evento_armonico = movedEvent
          delete verse.evento_armonico.segment_index
        }
      }

      editingSong.versos.splice(verseIndex + 1, 0, nuevoVerso)
      assignSectionFromIndex(verseIndex + 2)
      normalizeVerseOrder(editingSong.versos)
    }

    const nextSelected = ensureSectionsIntegrity(editingSong, newSection.id)
    persistSelectedSection(nextSelected)
    updateSong({ ...editingSong })
    scheduleAutosave()
  }

  const selectSectionOnly = (id) => {
    const emptySelection = { verse: null, segment: null, start: null, end: null, element: null }
    setContextualScopeMode('auto')
    persistSelectedSection(id)
    selectionRef.current = emptySelection
    setSelectionState(emptySelection)
    setSelectedVerseIndexes((prev) => {
      if (!prev.size) return prev
      const next = new Set(
        Array.from(prev).filter((index) => (Array.isArray(editingSong.versos) ? editingSong.versos[index] : null)?.section_id === id),
      )
      return next
    })
    scrollPreviewToSection(id)
  }

  const getActiveSectionId = () => {
    const sections = Array.isArray(editingSong.secciones) ? editingSong.secciones : []
    if (!sections.length) return ''
    if (selectedSectionId && sections.some((section) => section.id === selectedSectionId)) {
      return selectedSectionId
    }
    return sections[0].id
  }

  const activeSectionId = getActiveSectionId()
  const activeSection = Array.isArray(editingSong.secciones)
    ? editingSong.secciones.find((section) => section.id === activeSectionId)
    : null
  const sectionCounts = useMemo(() => {
    const map = new Map()
    if (Array.isArray(editingSong.versos)) {
      editingSong.versos.forEach((verse) => {
        const id = verse.section_id || ''
        map.set(id, (map.get(id) || 0) + 1)
      })
    }
    return map
  }, [editingSong.versos])
  const versesInActiveSection = Array.isArray(editingSong.versos)
    ? editingSong.versos
        .map((verse, index) => ({ verse, index }))
        .filter((item) => item.verse.section_id === activeSectionId)
    : []
  const hasVerseFilter = selectedVerseIndexes.size > 0
  const showSectionEmptyState = navLevel === 'sections'
  const isFocusWork = navLevel !== 'sections'
  const useMasterPreview = navLevel === 'verses'
  const selectedVerseIndex =
    selectedVerseIndexes.size === 1 ? Array.from(selectedVerseIndexes.values())[0] : null
  const selectedVerseInSectionPosition =
    selectedVerseIndex === null
      ? -1
      : versesInActiveSection.findIndex((item) => item.index === selectedVerseIndex)
  const selectedVerse =
    selectedVerseIndex === null ? null : (Array.isArray(editingSong.versos) ? editingSong.versos[selectedVerseIndex] : null)
  const selectedVerseLabel =
    selectedVerseIndex === null
      ? selectedVerseIndexes.size > 1
        ? `${selectedVerseIndexes.size} versos`
        : ''
      : selectedVerse?.nombre
        ? String(selectedVerse.nombre)
        : selectedVerse?.instrumental
          ? `Instrumental ${selectedVerseInSectionPosition + 1}`
          : `Verso ${selectedVerseInSectionPosition + 1}`
  const selectedSegment =
    selectionState.verse !== null && selectionState.segment !== null
      ? (Array.isArray(editingSong.versos)
          ? editingSong.versos[selectionState.verse]?.segmentos?.[selectionState.segment] || null
          : null)
      : null

  const allVerses = Array.isArray(editingSong.versos) ? editingSong.versos : []
  const sectionsList = Array.isArray(editingSong.secciones) ? editingSong.secciones : []
  const versesBySection = useMemo(() => {
    const map = new Map()
    allVerses.forEach((verse, index) => {
      const id = verse.section_id || ''
      if (!map.has(id)) map.set(id, [])
      map.get(id).push({ verse, index })
    })
    return map
  }, [allVerses])
  const activeSectionNavIndex = sectionsList.findIndex((section) => section.id === activeSectionId)
  const sectionNavItems = sectionsList.map((section, index) => ({
    id: section.id,
    index,
    label: section.nombre || getDefaultSectionName(index),
    count: versesBySection.get(section.id)?.length || 0,
    indicators: buildContentIndicators({
      attachments: getSectionLevelAttachments(editingSong, section.id),
      comments: section?.comentarios,
      midiClips: section?.midi_clips,
    }),
  }))
  const allAttachments = useMemo(
    () => (Array.isArray(editingSong?.adjuntos) ? editingSong.adjuntos.filter((item) => item && typeof item === 'object') : []),
    [editingSong],
  )
  const songLevelAttachments = useMemo(() => getSongLevelAttachments(editingSong), [editingSong])
  const selectedSection = sectionsList.find((section) => section.id === activeSectionId) || null
  const contextualTarget = useMemo(() => {
    if (contextualScopeMode === 'song') {
      return {
        type: 'song',
        label: editingSong.titulo || 'Canción completa',
        meta: 'Nivel general de la canción',
        target: { anchor_type: 'song' },
        attachments: songLevelAttachments,
        comments: [],
        midiClips: [],
        verseIndex: null,
        segmentIndex: null,
      }
    }

    if (
      selectionState.verse !== null
      && selectionState.segment !== null
      && selectedSegment
    ) {
      const verse = allVerses[selectionState.verse] || null
      const sectionId = verse?.section_id || activeSectionId || ''
      const sectionIndex = sectionsList.findIndex((section) => section.id === sectionId)
      const sectionName = sectionIndex >= 0
        ? sectionsList[sectionIndex]?.nombre || getDefaultSectionName(sectionIndex)
        : activeSection?.nombre || getDefaultSectionName(0)
      return {
        type: 'segment',
        label: `Segmento ${selectionState.segment + 1}`,
        meta: sectionName,
        target: {
          anchor_type: 'segment',
          section_id: sectionId,
          verse_index: selectionState.verse,
          segment_index: selectionState.segment,
        },
        attachments: getSegmentLevelAttachments(editingSong, selectionState.verse).filter(
          (item) => Number(item?.segment_index) === Number(selectionState.segment),
        ),
        comments: Array.isArray(selectedSegment?.comentarios) ? selectedSegment.comentarios : [],
        midiClips: Array.isArray(selectedSegment?.midi_clips) ? selectedSegment.midi_clips : [],
        verseIndex: selectionState.verse,
        segmentIndex: selectionState.segment,
      }
    }

    if (selectedVerse && selectedVerseIndex !== null) {
      return {
        type: 'verse',
        label: selectedVerseLabel || 'Verso',
        meta: activeSection?.nombre || getDefaultSectionName(0),
        target: {
          anchor_type: 'verse',
          section_id: selectedVerse.section_id || activeSectionId || '',
          verse_index: selectedVerseIndex,
        },
        attachments: getVerseLevelAttachments(editingSong, selectedVerseIndex),
        comments: Array.isArray(selectedVerse?.comentarios) ? selectedVerse.comentarios : [],
        midiClips: Array.isArray(selectedVerse?.midi_clips) ? selectedVerse.midi_clips : [],
        verseIndex: selectedVerseIndex,
        segmentIndex: null,
      }
    }

    return {
      type: 'section',
      label: selectedSection?.nombre || getDefaultSectionName(0),
      meta: 'Sección activa',
      target: { anchor_type: 'section', section_id: activeSectionId || '' },
      attachments: getSectionLevelAttachments(editingSong, activeSectionId),
      comments: Array.isArray(selectedSection?.comentarios) ? selectedSection.comentarios : [],
      midiClips: Array.isArray(selectedSection?.midi_clips) ? selectedSection.midi_clips : [],
      verseIndex: null,
      segmentIndex: null,
    }
  }, [
    activeSection,
    activeSectionId,
    allVerses,
    contextualScopeMode,
    editingSong,
    sectionsList,
    selectedSection,
    selectedSegment,
    selectedVerse,
    selectedVerseIndex,
    selectedVerseLabel,
    selectionState.segment,
    selectionState.verse,
    songLevelAttachments,
  ])
  const contextualTargetKey = useMemo(() => {
    if (contextualTarget.type === 'song') {
      return 'song'
    }
    if (contextualTarget.type === 'segment') {
      return `segment:${contextualTarget.verseIndex}:${contextualTarget.segmentIndex}`
    }
    if (contextualTarget.type === 'verse') {
      return `verse:${contextualTarget.verseIndex}`
    }
    return `section:${activeSectionId || ''}`
  }, [activeSectionId, contextualTarget])
  const contextualToolTab = contextualToolTabsByTarget[contextualTargetKey] || null
  const selectedAttachment = useMemo(
    () =>
      selectedAttachmentId === null
        ? null
        : allAttachments.find((item) => String(item?.id || '') === String(selectedAttachmentId)) || null,
    [allAttachments, selectedAttachmentId],
  )
  const selectedAttachmentContextKey = useMemo(
    () => getAttachmentContextKey(selectedAttachment),
    [selectedAttachment],
  )
  const selectedAttachmentMatchesContext = useMemo(() => {
    if (!selectedAttachment) {
      return false
    }
    return selectedAttachmentContextKey === 'song' || selectedAttachmentContextKey === contextualTargetKey
  }, [contextualTargetKey, selectedAttachment, selectedAttachmentContextKey])
  const selectedAudioAttachment =
    selectedAttachmentMatchesContext && selectedAttachment?.type !== 'photo' ? selectedAttachment : null
  const selectedPhotoAttachment =
    selectedAttachmentMatchesContext && selectedAttachment?.type === 'photo' ? selectedAttachment : null
  const annotationNavigationTargets = useMemo(() => {
    const targets = []

    sectionsList.forEach((section, index) => {
      const comments = Array.isArray(section?.comentarios) ? section.comentarios : []
      if (comments.length) {
        targets.push({
          key: `section:${section.id}`,
          type: 'section',
          sectionId: section.id,
          verseIndex: null,
          segmentIndex: null,
          label: section.nombre || getDefaultSectionName(index),
          count: comments.length,
        })
      }
    })

    allVerses.forEach((verse, verseIndex) => {
      const comments = Array.isArray(verse?.comentarios) ? verse.comentarios : []
      if (comments.length) {
        const sectionId = verse?.section_id || ''
        const sectionIndex = sectionsList.findIndex((section) => section.id === sectionId)
        const sectionLabel = sectionIndex >= 0
          ? sectionsList[sectionIndex]?.nombre || getDefaultSectionName(sectionIndex)
          : getDefaultSectionName(0)
        targets.push({
          key: `verse:${verseIndex}`,
          type: 'verse',
          sectionId,
          verseIndex,
          segmentIndex: null,
          label: `${verse?.nombre || `Verso ${verseIndex + 1}`} · ${sectionLabel}`,
          count: comments.length,
        })
      }

      const segments = Array.isArray(verse?.segmentos) ? verse.segmentos : []
      segments.forEach((segment, segmentIndex) => {
        const segmentComments = Array.isArray(segment?.comentarios) ? segment.comentarios : []
        if (!segmentComments.length) {
          return
        }
        const sectionId = verse?.section_id || ''
        const sectionIndex = sectionsList.findIndex((section) => section.id === sectionId)
        const sectionLabel = sectionIndex >= 0
          ? sectionsList[sectionIndex]?.nombre || getDefaultSectionName(sectionIndex)
          : getDefaultSectionName(0)
        targets.push({
          key: `segment:${verseIndex}:${segmentIndex}`,
          type: 'segment',
          sectionId,
          verseIndex,
          segmentIndex,
          label: `Segmento ${segmentIndex + 1} · ${sectionLabel}`,
          count: segmentComments.length,
        })
      })
    })

    return targets
  }, [allVerses, sectionsList])
  const audioNavigationItems = useMemo(
    () => allAttachments.filter((item) => item?.type !== 'photo'),
    [allAttachments],
  )
  const visibleAudioNavigationItems = useMemo(() => {
    if (contextualTarget.type === 'song') {
      return contextualTarget.attachments.filter((item) => item?.type !== 'photo')
    }
    return audioNavigationItems
  }, [audioNavigationItems, contextualTarget])
  const currentAnnotationNavigationIndex = useMemo(
    () => annotationNavigationTargets.findIndex((item) => item.key === contextualTargetKey),
    [annotationNavigationTargets, contextualTargetKey],
  )
  const currentAudioNavigationIndex = useMemo(() => {
    if (selectedAudioAttachment) {
      return visibleAudioNavigationItems.findIndex((item) => String(item?.id || '') === String(selectedAudioAttachment.id))
    }
    return visibleAudioNavigationItems.findIndex((item) => getAttachmentContextKey(item) === contextualTargetKey)
  }, [contextualTargetKey, selectedAudioAttachment, visibleAudioNavigationItems])

  useEffect(() => {
    if (contextualScopeMode !== 'song') {
      return
    }
    if (selectionState.verse !== null || selectedVerseIndex !== null) {
      setContextualScopeMode('auto')
    }
  }, [contextualScopeMode, selectedVerseIndex, selectionState.verse])
  const hasSegmentFormatTools =
    contextualTarget.type === 'segment'
    && contextualTarget.verseIndex !== null
    && contextualTarget.segmentIndex !== null
  const contextualTabItems = [
    ...(hasSegmentFormatTools ? [{ id: 'format', label: 'Formato' }] : []),
    { id: 'audio', label: 'Audio' },
    { id: 'photos', label: 'Fotos' },
    { id: 'annotations', label: 'Anotaciones' },
    { id: 'midi', label: 'MIDI' },
    { id: 'reading', label: 'Lectura' },
    { id: 'options', label: 'Opciones' },
  ]

  const toggleEditorFullscreen = useCallback(async () => {
    const sectionNode = mainSectionRef.current
    if (!sectionNode || typeof document === 'undefined') {
      return
    }

    try {
      if (document.fullscreenElement === sectionNode) {
        await document.exitFullscreen?.()
        return
      }
      await sectionNode.requestFullscreen?.()
    } catch {
      setIsEditorFullscreen((current) => !current)
    }
  }, [])

  const getVerseSummary = (verse) => {
    const summary = formatSegmentsForStackedMode(Array.isArray(verse?.segmentos) ? verse.segmentos : [])
    return summary.lyrics || 'Verso vacío'
  }

  const getVerseStackPreview = (verse) => {
    const summary = formatSegmentsForStackedMode(Array.isArray(verse?.segmentos) ? verse.segmentos : [])
    const chords = (summary.chords || '').replace(/\s+$/g, '')
    const lyrics = (summary.lyrics || '').replace(/\s+$/g, '')
    return {
      chords: chords || ' ',
      lyrics: lyrics || 'Verso vacío',
    }
  }

  const handleSelectVerse = (sectionId, index) => {
    const emptySelection = { verse: null, segment: null, start: null, end: null, element: null }
    setContextualScopeMode('auto')
    persistSelectedSection(sectionId)
    selectionRef.current = emptySelection
    setSelectionState(emptySelection)
    setSelectedVerseIndexes(new Set([index]))
    setExpandedSectionId(sectionId)
    setNavLevel('verses')
  }

  const clearVerseSelection = useCallback(() => {
    setContextualScopeMode('auto')
    setSelectedVerseIndexes((prev) => (prev.size ? new Set() : prev))
    setExpandedSectionId(null)
    setVerseFocusRequest(null)
    const emptySelection = { verse: null, segment: null, start: null, end: null, element: null }
    selectionRef.current = emptySelection
    setSelectionState(emptySelection)
  }, [])

  const handleVerseSelectionClearClick = useCallback(
    (event) => {
      if (!selectedVerseIndexes.size) {
        return
      }
      const target = event.target
      if (!(target instanceof Element)) {
        return
      }
      if (
        target.closest(
          '.wpss-verse-inline-editor, .wpss-verse-card, .wpss-preview-verse-card:not(.wpss-preview-verse-card--ghost), .wpss-verse-card-mini:not(.wpss-verse-card-mini--ghost), .wpss-section-preview__workspace-tools',
        )
      ) {
        return
      }
      clearVerseSelection()
    },
    [clearVerseSelection, selectedVerseIndexes.size],
  )

  const enterVerseLevel = (sectionId) => {
    setContextualScopeMode('auto')
    persistSelectedSection(sectionId)
    clearVerseSelection()
    setExpandedSectionId(sectionId)
    setNavLevel('verses')
  }

  const backToSections = () => {
    setContextualScopeMode('auto')
    clearVerseSelection()
    setExpandedSectionId(null)
    setNavLevel('sections')
  }

  const clampPreviewRatio = (value) => Math.min(Math.max(value, 20), 50)
  const clampSidebarWidth = (value) => Math.min(Math.max(value, 120), 320)
  const clampPreviewScale = (value) =>
    Math.min(Math.max(value, PREVIEW_SCALE_LEVELS[0]), PREVIEW_SCALE_LEVELS[PREVIEW_SCALE_LEVELS.length - 1])
  const previewScaleIndex = getNearestPreviewScaleIndex(previewScale)
  const canPreviewZoomOut = previewScaleIndex > 0
  const canPreviewZoomIn = previewScaleIndex < PREVIEW_SCALE_LEVELS.length - 1
  const handlePreviewScaleStep = (direction) => {
    const nextIndex = Math.min(
      PREVIEW_SCALE_LEVELS.length - 1,
      Math.max(0, previewScaleIndex + direction),
    )
    setPreviewScale(PREVIEW_SCALE_LEVELS[nextIndex])
  }
  const scrollPreviewToSection = useCallback((sectionId, behavior = 'smooth') => {
    if (!sectionId) {
      return
    }

    const shell = previewScrollRef.current
    const target = previewSectionRefs.current.get(sectionId)
    if (!shell || !target || typeof shell.scrollTo !== 'function') {
      return
    }

    const alignToTarget = (scrollBehavior = behavior) => {
      const shellRect = shell.getBoundingClientRect()
      const targetRect = target.getBoundingClientRect()
      const maxTop = Math.max(shell.scrollHeight - shell.clientHeight, 0)
      const maxLeft = Math.max(shell.scrollWidth - shell.clientWidth, 0)
      const nextTop = Math.min(Math.max(shell.scrollTop + (targetRect.top - shellRect.top) - 8, 0), maxTop)
      const nextLeft = Math.min(Math.max(shell.scrollLeft + (targetRect.left - shellRect.left) - 8, 0), maxLeft)
      shell.scrollTo({ top: nextTop, left: nextLeft, behavior: scrollBehavior })
    }

    if (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function') {
      window.requestAnimationFrame(() => alignToTarget(behavior))
      window.setTimeout(() => alignToTarget('auto'), 260)
      return
    }

    alignToTarget(behavior)
  }, [])

  const ensureContextTargetVisible = useCallback((behavior = 'smooth') => {
    const shell = previewScrollRef.current
    if (!shell) {
      return
    }

    let target = null
    if (contextualTarget.type === 'segment' && contextualTarget.verseIndex !== null && contextualTarget.segmentIndex !== null) {
      target = shell.querySelector(
        `[data-wpss-segment-key="${contextualTarget.verseIndex}:${contextualTarget.segmentIndex}"]`,
      )
    } else if (contextualTarget.type === 'verse' && contextualTarget.verseIndex !== null) {
      target = shell.querySelector(`[data-wpss-verse-index="${contextualTarget.verseIndex}"]`)
    } else if (contextualTarget.type === 'section' && activeSectionId) {
      target = previewSectionRefs.current.get(activeSectionId) || null
    }

    if (!(target instanceof Element)) {
      return
    }

    const shellRect = shell.getBoundingClientRect()
    const targetRect = target.getBoundingClientRect()
    const topOverflow = targetRect.top < shellRect.top + 12
    const bottomOverflow = targetRect.bottom > shellRect.bottom - 12

    if (!topOverflow && !bottomOverflow) {
      return
    }

    if (typeof target.scrollIntoView === 'function') {
      target.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior })
    }
  }, [activeSectionId, contextualTarget])

  useEffect(() => {
    const pendingOpen = pendingContextualToolbarOpenRef.current
    if (pendingOpen && pendingOpen.targetKey === contextualTargetKey) {
      setContextualToolTabsByTarget((prev) => ({ ...prev, [contextualTargetKey]: pendingOpen.tabId }))
      setSelectedAttachmentId(pendingOpen.attachmentId)
      setIsContextualToolbarExpanded(true)
      pendingContextualToolbarOpenRef.current = null
      return
    }
    setIsContextualToolbarExpanded(false)
  }, [contextualTargetKey])

  useEffect(() => {
    if (!selectedAttachment) {
      return
    }
    if (selectedAttachmentContextKey !== 'song' && selectedAttachmentContextKey !== contextualTargetKey) {
      setSelectedAttachmentId(null)
    }
  }, [contextualTargetKey, selectedAttachment, selectedAttachmentContextKey])

  useEffect(() => {
    if (typeof document === 'undefined') {
      return undefined
    }

    const handleFullscreenChange = () => {
      setIsEditorFullscreen(document.fullscreenElement === mainSectionRef.current)
    }

    document.addEventListener('fullscreenchange', handleFullscreenChange)
    handleFullscreenChange()
    return () => {
      document.removeEventListener('fullscreenchange', handleFullscreenChange)
    }
  }, [])

  useEffect(() => {
    if (!isContextualToolbarExpanded) {
      return
    }
    if (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function') {
      const frameId = window.requestAnimationFrame(() => ensureContextTargetVisible('auto'))
      return () => window.cancelAnimationFrame(frameId)
    }
    ensureContextTargetVisible('auto')
    return undefined
  }, [contextualTargetKey, contextualToolTab, ensureContextTargetVisible, isContextualToolbarExpanded])

  useEffect(() => {
    if (!isContextualToolbarExpanded) {
      return undefined
    }
    if (contextualToolTab === 'format' && contextualTarget.type === 'segment') {
      return undefined
    }

    const handlePointerDown = (event) => {
      const tools = workspaceToolsRef.current
      if (!tools || !(event.target instanceof Node) || tools.contains(event.target)) {
        return
      }
      setIsContextualToolbarExpanded(false)
    }

    const handleFocusIn = (event) => {
      const tools = workspaceToolsRef.current
      if (!tools || !(event.target instanceof Node) || tools.contains(event.target)) {
        return
      }
      setIsContextualToolbarExpanded(false)
    }

    document.addEventListener('pointerdown', handlePointerDown, true)
    document.addEventListener('focusin', handleFocusIn, true)
    return () => {
      document.removeEventListener('pointerdown', handlePointerDown, true)
      document.removeEventListener('focusin', handleFocusIn, true)
    }
  }, [contextualTarget.type, contextualToolTab, isContextualToolbarExpanded])
  const editorGridTemplateColumns = isCompactPreviewViewport
    ? 'minmax(0, 1fr)'
    : isFocusWork && !useMasterPreview
      ? `minmax(520px, ${100 - previewRatio}fr) 8px minmax(320px, ${previewRatio}fr)`
      : `${
          isSidebarCollapsed
            ? '32px'
            : sidebarWidth
              ? `${sidebarWidth}px`
              : 'max-content'
        } 8px minmax(360px, 1fr)`

  useEffect(() => {
    if (!isResizingPreview) {
      return undefined
    }

    const handleMove = (event) => {
      const layout = layoutRef.current
      const sidebar = sidebarRef.current
      if (!layout || !sidebar) {
        return
      }

      const layoutRect = layout.getBoundingClientRect()
      const sidebarRect = sidebar.getBoundingClientRect()
      const total = layoutRect.right - sidebarRect.right
      if (total <= 0) {
        return
      }
      const pointer = event.clientX - sidebarRect.right
      const ratio = clampPreviewRatio(Math.round((1 - pointer / total) * 100))
      setPreviewRatio(ratio)
    }

    const handleUp = () => {
      setIsResizingPreview(false)
    }

    window.addEventListener('pointermove', handleMove)
    window.addEventListener('pointerup', handleUp)
    return () => {
      window.removeEventListener('pointermove', handleMove)
      window.removeEventListener('pointerup', handleUp)
    }
  }, [isResizingPreview])

  useEffect(() => {
    if (!isCompactPreviewViewport) {
      return undefined
    }
    const node = previewScrollRef.current
    if (!node || typeof node.addEventListener !== 'function') {
      return undefined
    }

    const pinch = previewPinchRef.current
    const finishPinch = () => {
      if (!pinch.active) {
        return
      }
      pinch.active = false
      pinch.startDistance = 0
      pinch.startScale = previewScaleRef.current
      setPreviewScale((current) => PREVIEW_SCALE_LEVELS[getNearestPreviewScaleIndex(current)])
    }

    const handleTouchStart = (event) => {
      if (event.touches.length !== 2) {
        return
      }
      const distance = getTouchDistance(event.touches)
      if (!distance) {
        return
      }
      pinch.active = true
      pinch.startDistance = distance
      pinch.startScale = previewScaleRef.current
    }

    const handleTouchMove = (event) => {
      if (!pinch.active || event.touches.length !== 2) {
        return
      }
      const distance = getTouchDistance(event.touches)
      if (!distance || !pinch.startDistance) {
        return
      }
      const scaled = pinch.startScale * (distance / pinch.startDistance)
      setPreviewScale(clampPreviewScale(Math.round(scaled)))
      event.preventDefault()
    }

    node.addEventListener('touchstart', handleTouchStart, { passive: false })
    node.addEventListener('touchmove', handleTouchMove, { passive: false })
    node.addEventListener('touchend', finishPinch)
    node.addEventListener('touchcancel', finishPinch)

    return () => {
      node.removeEventListener('touchstart', handleTouchStart)
      node.removeEventListener('touchmove', handleTouchMove)
      node.removeEventListener('touchend', finishPinch)
      node.removeEventListener('touchcancel', finishPinch)
    }
  }, [isCompactPreviewViewport])

  useEffect(() => {
    if (!isResizingSidebar) {
      return undefined
    }

    const handleMove = (event) => {
      const layout = layoutRef.current
      if (!layout) return
      const rect = layout.getBoundingClientRect()
      const next = clampSidebarWidth(event.clientX - rect.left)
      setSidebarWidth(next)
    }

    const handleUp = () => {
      setIsResizingSidebar(false)
    }

    window.addEventListener('pointermove', handleMove)
    window.addEventListener('pointerup', handleUp)
    return () => {
      window.removeEventListener('pointermove', handleMove)
      window.removeEventListener('pointerup', handleUp)
    }
  }, [isResizingSidebar])

  const handleSectionChange = (nextSections) => {
    const prevNameMap = new Map(
      (Array.isArray(editingSong.secciones) ? editingSong.secciones : []).map((section) => [
        section.id,
        section.nombre || '',
      ]),
    )
    const normalizedSections = (Array.isArray(nextSections) ? nextSections : []).map((section) => {
      const incomingName = section?.nombre ? String(section.nombre) : ''
      if (incomingName.trim()) {
        return section
      }
      const fallbackName = section?.id ? prevNameMap.get(section.id) || '' : ''
      return fallbackName ? { ...section, nombre: fallbackName } : section
    })
    const nextSong = { ...editingSong, secciones: normalizedSections }
    const nextSelected = ensureSectionsIntegrity(nextSong, selectedSectionId)
    persistSelectedSection(nextSelected)
    updateSong({ ...nextSong })
    scheduleAutosave()
  }

  const handleStructureChange = (nextStructure) => {
    updateSong({ ...editingSong, estructura: nextStructure })
    scheduleAutosave()
  }

  const handleAddVerse = () => {
    if (!activeSectionId) return
    handleAddVerseToSection(activeSectionId)
  }

  const handleAddVerseToSection = (sectionId) => {
    if (!sectionId) return
    const nextVerses = Array.isArray(editingSong.versos) ? [...editingSong.versos] : []
    const newVerse = { ...createEmptyVerse(nextVerses.length + 1, sectionId), nombre: '' }
    const insertAfter = nextVerses.reduce((lastIndex, verse, index) => {
      if ((verse.section_id || '') === sectionId) {
        return index
      }
      return lastIndex
    }, -1)
    const nextVerseIndex = insertAfter >= 0 ? insertAfter + 1 : nextVerses.length
    if (insertAfter >= 0) {
      nextVerses.splice(insertAfter + 1, 0, newVerse)
    } else {
      nextVerses.push(newVerse)
    }
    normalizeVerseOrder(nextVerses)
    const nextSong = { ...editingSong, versos: nextVerses }
    syncLegacyFromSections(nextSong, Array.isArray(nextSong.secciones) ? nextSong.secciones : [])
    persistSelectedSection(sectionId)
    setSelectedVerseIndexes(new Set([nextVerseIndex]))
    setExpandedSectionId(sectionId)
    setNavLevel('verses')
    requestVerseSegmentFocus(nextVerseIndex, 0, true)
    updateSong(nextSong)
    scheduleAutosave()
  }

  const handleDuplicateVerseAtIndex = (verseIndex) => {
    const source = Array.isArray(editingSong.versos) ? editingSong.versos[verseIndex] : null
    if (!source) return
    const nextVerses = Array.isArray(editingSong.versos) ? [...editingSong.versos] : []
    const clone = JSON.parse(JSON.stringify(source))
    clone.id = null
    nextVerses.splice(verseIndex + 1, 0, clone)
    normalizeVerseOrder(nextVerses)
    const nextSong = { ...editingSong, versos: nextVerses }
    syncLegacyFromSections(nextSong, Array.isArray(nextSong.secciones) ? nextSong.secciones : [])
    updateSong(nextSong)
    scheduleAutosave()
  }

  const handleRemoveVerseAtIndex = (verseIndex) => {
    const source = Array.isArray(editingSong.versos) ? editingSong.versos[verseIndex] : null
    if (!source) return
    const sourceSectionId = source.section_id || ''
    const totalInSection = (Array.isArray(editingSong.versos) ? editingSong.versos : []).filter(
      (verse) => (verse.section_id || '') === sourceSectionId,
    ).length
    if (totalInSection <= 1) return

    const nextVerses = Array.isArray(editingSong.versos) ? [...editingSong.versos] : []
    nextVerses.splice(verseIndex, 1)
    normalizeVerseOrder(nextVerses)
    const nextSong = { ...editingSong, versos: nextVerses }
    syncLegacyFromSections(nextSong, Array.isArray(nextSong.secciones) ? nextSong.secciones : [])
    updateSong(nextSong)
    scheduleAutosave()
    clearVerseSelection()
  }

  const handleRenameVerseAtIndex = (verseIndex) => {
    const source = Array.isArray(editingSong.versos) ? editingSong.versos[verseIndex] : null
    if (!source) return
    const currentName = source.nombre ? String(source.nombre) : ''
    const nextName = window.prompt('Nombre del verso', currentName)
    if (nextName === null) return
    const nextVerses = Array.isArray(editingSong.versos) ? [...editingSong.versos] : []
    nextVerses[verseIndex] = { ...source, nombre: String(nextName).slice(0, 64) }
    const nextSong = { ...editingSong, versos: nextVerses }
    syncLegacyFromSections(nextSong, Array.isArray(nextSong.secciones) ? nextSong.secciones : [])
    updateSong(nextSong)
    scheduleAutosave()
  }

  const handleDuplicateSection = (index) => {
    const sections = Array.isArray(editingSong.secciones) ? [...editingSong.secciones] : []
    const source = sections[index]
    if (!source) return

    const baseName = source.nombre || getDefaultSectionName(index)
    const newSection = {
      id: createSection('', index + 1).id,
      nombre: `${baseName} copia`.slice(0, 64),
    }

    sections.splice(index + 1, 0, newSection)

    const nextVerses = Array.isArray(editingSong.versos) ? [...editingSong.versos] : []
    const versesToCopy = nextVerses
      .map((verse, verseIndex) => ({ verse, verseIndex }))
      .filter((item) => item.verse.section_id === source.id)

    if (versesToCopy.length) {
      const lastIndex = versesToCopy[versesToCopy.length - 1].verseIndex
      const clones = versesToCopy.map(({ verse }) => {
        const clone = JSON.parse(JSON.stringify(verse))
        clone.id = null
        clone.section_id = newSection.id
        return clone
      })
      nextVerses.splice(lastIndex + 1, 0, ...clones)
      normalizeVerseOrder(nextVerses)
    }

    const nextSong = { ...editingSong, secciones: sections, versos: nextVerses }
    const nextSelected = ensureSectionsIntegrity(nextSong, newSection.id)
    persistSelectedSection(nextSelected)
    updateSong({ ...nextSong })
    scheduleAutosave()
  }

  const handleRemoveSection = (index) => {
    const sections = Array.isArray(editingSong.secciones) ? [...editingSong.secciones] : []
    if (sections.length <= 1) return
    sections.splice(index, 1)
    handleSectionChange(sections)
  }

  const handleSectionNameChange = (sectionId, value) => {
    const sections = Array.isArray(editingSong.secciones) ? [...editingSong.secciones] : []
    const index = sections.findIndex((section) => section.id === sectionId)
    if (index === -1) return
    sections[index] = { ...sections[index], nombre: value.slice(0, 64) }
    handleSectionChange(sections)
  }

  const moveSection = (fromIndex, toIndex) => {
    if (fromIndex === toIndex) return
    const sections = Array.isArray(editingSong.secciones) ? [...editingSong.secciones] : []
    if (!sections[fromIndex]) return
    const [moved] = sections.splice(fromIndex, 1)
    sections.splice(toIndex, 0, moved)
    handleSectionChange(sections)
  }

  const getSectionAppendIndex = useCallback((verses, sectionId) => {
    if (!sectionId) {
      return Array.isArray(verses) ? verses.length : 0
    }

    const safeVerses = Array.isArray(verses) ? verses : []
    let lastMatch = -1
    safeVerses.forEach((verse, index) => {
      if ((verse?.section_id || '') === sectionId) {
        lastMatch = index
      }
    })

    if (lastMatch >= 0) {
      return lastMatch + 1
    }

    const sections = Array.isArray(editingSong.secciones) ? editingSong.secciones : []
    const sectionIndex = sections.findIndex((section) => section.id === sectionId)
    if (sectionIndex < 0) {
      return safeVerses.length
    }

    for (let index = sectionIndex + 1; index < sections.length; index += 1) {
      const nextSectionId = sections[index]?.id || ''
      const nextVerseIndex = safeVerses.findIndex((verse) => (verse?.section_id || '') === nextSectionId)
      if (nextVerseIndex >= 0) {
        return nextVerseIndex
      }
    }

    return safeVerses.length
  }, [editingSong.secciones])

  const moveVerseToPosition = useCallback((fromIndex, targetSectionId, targetIndex = null, placement = 'before') => {
    if (fromIndex < 0) {
      return false
    }

    const verses = Array.isArray(editingSong.versos) ? editingSong.versos : []
    if (fromIndex >= verses.length) {
      return false
    }

    const safeTargetSectionId = String(targetSectionId || verses[fromIndex]?.section_id || '')
    if (!safeTargetSectionId) {
      return false
    }

    const nextVerses = [...verses]
    const [movedVerse] = nextVerses.splice(fromIndex, 1)
    if (!movedVerse) {
      return false
    }

    let insertIndex = nextVerses.length
    if (Number.isInteger(targetIndex) && targetIndex >= 0 && targetIndex <= verses.length) {
      insertIndex = targetIndex
      if (fromIndex < targetIndex) {
        insertIndex -= 1
      }
      if (placement === 'after') {
        insertIndex += 1
      }
    } else {
      insertIndex = getSectionAppendIndex(nextVerses, safeTargetSectionId)
    }

    nextVerses.splice(insertIndex, 0, {
      ...movedVerse,
      section_id: safeTargetSectionId,
    })

    normalizeVerseOrder(nextVerses)
    const nextSong = { ...editingSong, versos: nextVerses }
    syncLegacyFromSections(nextSong, Array.isArray(nextSong.secciones) ? nextSong.secciones : [])
    updateSong(nextSong)
    scheduleAutosave()
    return true
  }, [editingSong, getSectionAppendIndex])

  const adjustEventIndexAfterSegmentRemoval = useCallback((verse, removedIndex) => {
    if (!verse?.evento_armonico) {
      return verse
    }

    const currentIndex = getValidSegmentIndex(verse.evento_armonico, verse.segmentos?.length)
    if (currentIndex === null) {
      return verse
    }

    const nextEvent = { ...verse.evento_armonico }
    if (currentIndex === removedIndex) {
      delete nextEvent.segment_index
    } else if (currentIndex > removedIndex) {
      nextEvent.segment_index = currentIndex - 1
    }

    return { ...verse, evento_armonico: nextEvent }
  }, [])

  function moveSegmentToTargetVerse(fromVerseIndex, fromSegmentIndex, targetVerseIndex) {
    const verses = Array.isArray(editingSong.versos) ? editingSong.versos : []
    if (
      fromVerseIndex < 0
      || targetVerseIndex < 0
      || fromVerseIndex >= verses.length
      || targetVerseIndex >= verses.length
    ) {
      return false
    }

    const nextVerses = verses.map((verse) => ({
      ...verse,
      segmentos: Array.isArray(verse.segmentos) ? [...verse.segmentos] : [],
    }))
    const sourceVerse = nextVerses[fromVerseIndex]
    const targetVerse = nextVerses[targetVerseIndex]
    const [movedSegment] = sourceVerse?.segmentos?.splice(fromSegmentIndex, 1) || []

    if (!movedSegment || !targetVerse) {
      return false
    }

    if (!sourceVerse.segmentos.length) {
      sourceVerse.segmentos = [createEmptySegment()]
    }

    nextVerses[fromVerseIndex] = adjustEventIndexAfterSegmentRemoval(sourceVerse, fromSegmentIndex)
    targetVerse.segmentos = targetVerse.segmentos.concat({ ...movedSegment })

    normalizeVerseOrder(nextVerses)
    const nextSong = { ...editingSong, versos: nextVerses }
    syncLegacyFromSections(nextSong, Array.isArray(nextSong.secciones) ? nextSong.secciones : [])
    updateSong(nextSong)
    scheduleAutosave()
    persistSelectedSection(targetVerse.section_id || sourceVerse.section_id || '')
    setSelectedVerseIndexes(new Set([targetVerseIndex]))
    setExpandedSectionId(targetVerse.section_id || sourceVerse.section_id || '')
    requestVerseSegmentFocus(targetVerseIndex, targetVerse.segmentos.length - 1, true)
    return true
  }

  function moveSegmentToNewVerse(fromVerseIndex, fromSegmentIndex, targetSectionId) {
    const verses = Array.isArray(editingSong.versos) ? editingSong.versos : []
    if (fromVerseIndex < 0 || fromVerseIndex >= verses.length) {
      return false
    }

    const safeTargetSectionId = String(targetSectionId || '')
    if (!safeTargetSectionId) {
      return false
    }

    const nextVerses = verses.map((verse) => ({
      ...verse,
      segmentos: Array.isArray(verse.segmentos) ? [...verse.segmentos] : [],
    }))
    const sourceVerse = nextVerses[fromVerseIndex]
    const [movedSegment] = sourceVerse?.segmentos?.splice(fromSegmentIndex, 1) || []
    if (!movedSegment) {
      return false
    }

    if (!sourceVerse.segmentos.length) {
      sourceVerse.segmentos = [createEmptySegment()]
    }
    nextVerses[fromVerseIndex] = adjustEventIndexAfterSegmentRemoval(sourceVerse, fromSegmentIndex)

    const insertIndex = getSectionAppendIndex(nextVerses, safeTargetSectionId)
    const newVerse = createEmptyVerse(insertIndex + 1, safeTargetSectionId)
    newVerse.segmentos = [{ ...movedSegment }]

    nextVerses.splice(insertIndex, 0, newVerse)
    normalizeVerseOrder(nextVerses)

    const nextSong = { ...editingSong, versos: nextVerses }
    syncLegacyFromSections(nextSong, Array.isArray(nextSong.secciones) ? nextSong.secciones : [])
    updateSong(nextSong)
    scheduleAutosave()
    persistSelectedSection(safeTargetSectionId)
    setSelectedVerseIndexes(new Set([insertIndex]))
    setExpandedSectionId(safeTargetSectionId)
    requestVerseSegmentFocus(insertIndex, 0, true)
    return true
  }

  const beginVerseDrag = (event, fromIndex) => {
    event.dataTransfer.setData('text/plain', String(fromIndex))
    event.dataTransfer.setData('application/x-wpss-verse-index', String(fromIndex))
    event.dataTransfer.effectAllowed = 'move'
    setVerseDragIndex(fromIndex)
  }

  const getDraggedVerseIndex = (event) => {
    const typedPayload = parseInt(event.dataTransfer.getData('application/x-wpss-verse-index'), 10)
    if (!Number.isNaN(typedPayload)) {
      return typedPayload
    }
    const payload = parseInt(event.dataTransfer.getData('text/plain'), 10)
    if (!Number.isNaN(payload)) {
      return payload
    }
    return verseDragIndex
  }

  const beginSegmentDrag = useCallback((verseIndex, segmentIndex) => {
    draggingSegmentRef.current = { verseIndex, segmentIndex }
  }, [])

  const clearSegmentDrag = useCallback(() => {
    draggingSegmentRef.current = null
  }, [])

  const getDraggedSegmentMeta = useCallback((event) => {
    if (draggingSegmentRef.current) {
      return draggingSegmentRef.current
    }
    if (!event?.dataTransfer) {
      return null
    }
    const payload = event.dataTransfer.getData('application/x-wpss-segment')
    if (!payload) {
      return null
    }
    try {
      const parsed = JSON.parse(payload)
      if (Number.isInteger(parsed?.verseIndex) && Number.isInteger(parsed?.segmentIndex)) {
        return parsed
      }
    } catch {
      return null
    }
    return null
  }, [])

  const handleSectionRenamePrompt = useCallback((sectionId) => {
    const sections = Array.isArray(editingSong.secciones) ? editingSong.secciones : []
    const sectionIndex = sections.findIndex((section) => section.id === sectionId)
    const section = sectionIndex >= 0 ? sections[sectionIndex] : null
    if (!section) {
      return
    }
    const currentName = String(section.nombre || getDefaultSectionName(sectionIndex))
    const nextName = window.prompt('Nombre de la sección', currentName)
    if (nextName === null) {
      return
    }
    handleSectionNameChange(sectionId, String(nextName))
  }, [editingSong.secciones])

  const handleContextualTabToggle = useCallback((tabId) => {
    if (!tabId) {
      return
    }
    setContextualToolTabsByTarget((prev) => ({ ...prev, [contextualTargetKey]: tabId }))
    setIsContextualToolbarExpanded((current) => {
      if (current && contextualToolTab === tabId) {
        return false
      }
      return true
    })
  }, [contextualTargetKey, contextualToolTab])

  const openContextualTarget = useCallback((target, tabId) => {
    if (!target || !tabId) {
      return
    }

    setContextualScopeMode('auto')

    const targetType = String(target.type || 'section')
    const targetSectionId = String(target.sectionId || activeSectionId || '')
    const targetVerseIndex = Number.isInteger(target.verseIndex) ? target.verseIndex : null
    const targetSegmentIndex = Number.isInteger(target.segmentIndex) ? target.segmentIndex : null
    const targetKey =
      targetType === 'segment'
        ? `segment:${targetVerseIndex}:${targetSegmentIndex}`
        : targetType === 'verse'
          ? `verse:${targetVerseIndex}`
          : `section:${targetSectionId}`

    setSelectedAttachmentId(null)

    if (targetKey === contextualTargetKey) {
      setContextualToolTabsByTarget((prev) => ({ ...prev, [targetKey]: tabId }))
      setIsContextualToolbarExpanded(true)
      if (targetType === 'segment' && targetVerseIndex !== null && targetSegmentIndex !== null) {
        requestVerseSegmentFocus(targetVerseIndex, targetSegmentIndex, false)
      }
      return
    }

    pendingContextualToolbarOpenRef.current = {
      attachmentId: null,
      targetKey,
      tabId,
    }

    const emptySelection = { verse: null, segment: null, start: null, end: null, element: null }

    if (targetType === 'segment' && targetVerseIndex !== null && targetSegmentIndex !== null) {
      persistSelectedSection(targetSectionId)
      setExpandedSectionId(targetSectionId)
      setNavLevel('verses')
      setSelectedVerseIndexes(new Set([targetVerseIndex]))
      selectionRef.current = emptySelection
      setSelectionState(emptySelection)
      requestVerseSegmentFocus(targetVerseIndex, targetSegmentIndex, false)
      return
    }

    if (targetType === 'verse' && targetVerseIndex !== null) {
      persistSelectedSection(targetSectionId)
      setExpandedSectionId(targetSectionId)
      setNavLevel('verses')
      setSelectedVerseIndexes(new Set([targetVerseIndex]))
      selectionRef.current = emptySelection
      setSelectionState(emptySelection)
      return
    }

    persistSelectedSection(targetSectionId)
    setExpandedSectionId(targetSectionId)
    setSelectedVerseIndexes(new Set())
    selectionRef.current = emptySelection
    setSelectionState(emptySelection)
  }, [activeSectionId, contextualTargetKey, persistSelectedSection, requestVerseSegmentFocus])

  const applyContextualTextFormat = useCallback((format) => {
    if (
      contextualTarget.type !== 'segment'
      || contextualTarget.verseIndex === null
      || contextualTarget.segmentIndex === null
      || !selectionState.element
      || !selectionState.element.isContentEditable
    ) {
      return
    }

    const element = selectionState.element
    element.focus()
    const selectionObj = window.getSelection()
    if (!selectionObj || selectionObj.rangeCount === 0) {
      return
    }

    const range = selectionObj.getRangeAt(0)
    if (!element.contains(range.startContainer)) {
      return
    }

    if (format === 'bold') {
      document.execCommand('bold')
    } else if (format === 'underline') {
      document.execCommand('underline')
    } else if (format === 'light') {
      if (range.collapsed) {
        return
      }
      const fragment = range.cloneContents()
      if (fragment.querySelector && fragment.querySelector('div, p, br')) {
        return
      }
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
      element.querySelectorAll('span.wpss-text-light').forEach((node) => unwrapNode(node))
    } else {
      return
    }

    const verseIndex = contextualTarget.verseIndex
    const segmentIndex = contextualTarget.segmentIndex
    const nextVerses = Array.isArray(editingSong.versos) ? [...editingSong.versos] : []
    const verse = nextVerses[verseIndex]
    const nextSegments = Array.isArray(verse?.segmentos) ? [...verse.segmentos] : []
    const segment = nextSegments[segmentIndex]
    if (!verse || !segment) {
      return
    }
    nextSegments[segmentIndex] = {
      ...segment,
      texto: normalizeSegmentHtml(element.innerHTML),
    }
    nextVerses[verseIndex] = { ...verse, segmentos: nextSegments }
    const nextSong = { ...editingSong, versos: nextVerses }
    updateSong(nextSong)
    scheduleAutosave()
    updateSegmentSelection(
      verseIndex,
      segmentIndex,
      selectionObj.anchorOffset ?? null,
      selectionObj.focusOffset ?? null,
      element,
    )
  }, [contextualTarget, editingSong, selectionState.element])

  const handleAttachmentSelect = useCallback((attachment) => {
    if (!attachment?.id) {
      return
    }

    const tabId = attachment?.type === 'photo' ? 'photos' : 'audio'
    const targetKey = getAttachmentContextKey(attachment) || contextualTargetKey
    setContextualScopeMode(targetKey === 'song' ? 'song' : 'auto')
    setSelectedAttachmentId(attachment.id)

    if (targetKey === 'song' || targetKey === contextualTargetKey) {
      setContextualToolTabsByTarget((prev) => ({ ...prev, [contextualTargetKey]: tabId }))
      setIsContextualToolbarExpanded(true)
      return
    }

    pendingContextualToolbarOpenRef.current = {
      attachmentId: attachment.id,
      targetKey,
      tabId,
    }

    const emptySelection = { verse: null, segment: null, start: null, end: null, element: null }
    const sectionId = String(attachment.section_id || '')
    const verseIndex = Number(attachment.verse_index)
    const segmentIndex = Number(attachment.segment_index)

    if (attachment.anchor_type === 'segment') {
      persistSelectedSection(sectionId)
      setExpandedSectionId(sectionId)
      setNavLevel('verses')
      setSelectedVerseIndexes(new Set([verseIndex]))
      const nextSelection = {
        verse: verseIndex,
        segment: segmentIndex,
        start: null,
        end: null,
        element: null,
      }
      selectionRef.current = nextSelection
      setSelectionState(nextSelection)
      return
    }

    if (attachment.anchor_type === 'verse') {
      persistSelectedSection(sectionId)
      setExpandedSectionId(sectionId)
      setNavLevel('verses')
      setSelectedVerseIndexes(new Set([verseIndex]))
      selectionRef.current = emptySelection
      setSelectionState(emptySelection)
      return
    }

    if (attachment.anchor_type === 'section') {
      persistSelectedSection(sectionId)
      setExpandedSectionId(sectionId)
      setSelectedVerseIndexes(new Set())
      selectionRef.current = emptySelection
      setSelectionState(emptySelection)
      return
    }

    setContextualToolTabsByTarget((prev) => ({ ...prev, [contextualTargetKey]: tabId }))
    setIsContextualToolbarExpanded(true)
  }, [contextualTargetKey, persistSelectedSection])

  const navigateAnnotationByStep = useCallback((step) => {
    if (!annotationNavigationTargets.length) {
      return
    }
    const baseIndex = currentAnnotationNavigationIndex >= 0 ? currentAnnotationNavigationIndex : 0
    const nextIndex = (baseIndex + step + annotationNavigationTargets.length) % annotationNavigationTargets.length
    openContextualTarget(annotationNavigationTargets[nextIndex], 'annotations')
  }, [annotationNavigationTargets, currentAnnotationNavigationIndex, openContextualTarget])

  const navigateAudioByStep = useCallback((step) => {
    if (!visibleAudioNavigationItems.length) {
      return
    }
    const baseIndex = currentAudioNavigationIndex >= 0 ? currentAudioNavigationIndex : 0
    const nextIndex = (baseIndex + step + visibleAudioNavigationItems.length) % visibleAudioNavigationItems.length
    handleAttachmentSelect(visibleAudioNavigationItems[nextIndex])
  }, [currentAudioNavigationIndex, handleAttachmentSelect, visibleAudioNavigationItems])

  const handleVerseCardDragOver = (event, toIndex) => {
    if (verseDragIndex === null && !draggingSegmentRef.current) return
    event.preventDefault()
    event.stopPropagation()
    setVerseDragOverIndex(toIndex)
  }

  const handleVerseCardDrop = (event, toIndex) => {
    event.preventDefault()
    event.stopPropagation()
    const draggedSegment = getDraggedSegmentMeta(event)
    if (draggedSegment) {
      moveSegmentToTargetVerse(draggedSegment.verseIndex, draggedSegment.segmentIndex, toIndex)
      clearSegmentDrag()
      return
    }
    const fromIndex = getDraggedVerseIndex(event)
    if (fromIndex !== null && fromIndex !== undefined) {
      const targetSectionId = Array.isArray(editingSong.versos) ? editingSong.versos[toIndex]?.section_id || '' : ''
      moveVerseToPosition(fromIndex, targetSectionId, toIndex, 'before')
    }
    setVerseDragIndex(null)
    setVerseDragOverIndex(null)
  }

  const handleVerseCardDragEnd = () => {
    setVerseDragIndex(null)
    setVerseDragOverIndex(null)
  }

  const handleSectionSurfaceDragOver = useCallback((event) => {
    if (sectionDragIndex === null && verseDragIndex === null && !draggingSegmentRef.current) {
      return
    }
    event.preventDefault()
    event.stopPropagation()
  }, [sectionDragIndex, verseDragIndex])

  const handleSectionSurfaceDrop = useCallback((event, sectionId, sectionIndex) => {
    event.preventDefault()
    event.stopPropagation()
    const draggedSectionIndex = parseInt(
      event.dataTransfer.getData('application/x-wpss-section-index') || '',
      10,
    )
    if (!Number.isNaN(draggedSectionIndex)) {
      moveSection(draggedSectionIndex, sectionIndex)
      setSectionDragIndex(null)
      setSectionDragOverIndex(null)
      return
    }
    const draggedSegment = getDraggedSegmentMeta(event)
    if (draggedSegment) {
      moveSegmentToNewVerse(draggedSegment.verseIndex, draggedSegment.segmentIndex, sectionId)
      clearSegmentDrag()
      return
    }
    const fromIndex = getDraggedVerseIndex(event)
    if (fromIndex !== null && fromIndex !== undefined) {
      moveVerseToPosition(fromIndex, sectionId)
    }
    setVerseDragIndex(null)
    setVerseDragOverIndex(null)
  }, [
    clearSegmentDrag,
    getDraggedSegmentMeta,
    getDraggedVerseIndex,
    moveSegmentToNewVerse,
    moveVerseToPosition,
  ])

  const clearPreviewTouchDragTimer = useCallback(() => {
    if (previewTouchDragTimerRef.current !== null) {
      window.clearTimeout(previewTouchDragTimerRef.current)
      previewTouchDragTimerRef.current = null
    }
  }, [])

  const clearPreviewTouchDragState = useCallback(() => {
    clearPreviewTouchDragTimer()
    previewTouchDragSessionRef.current?.cleanup?.()
    previewTouchDragSessionRef.current = null
    setSectionDragIndex(null)
    setSectionDragOverIndex(null)
    setVerseDragIndex(null)
    setVerseDragOverIndex(null)
  }, [clearPreviewTouchDragTimer])

  const resolvePreviewTouchDropTarget = useCallback((clientX, clientY) => {
    if (typeof document === 'undefined') {
      return null
    }

    const target = document.elementFromPoint(clientX, clientY)
    if (!(target instanceof Element)) {
      return null
    }

    const previewVerseNode = target.closest('[data-wpss-preview-verse-index]')
    if (previewVerseNode) {
      const verseIndex = parseInt(previewVerseNode.getAttribute('data-wpss-preview-verse-index') || '', 10)
      const sectionId = String(previewVerseNode.getAttribute('data-wpss-preview-section-id') || '')
      if (!Number.isNaN(verseIndex)) {
        return { type: 'verse', verseIndex, sectionId }
      }
    }

    const previewSectionNode = target.closest('[data-wpss-preview-section-index]')
    if (previewSectionNode) {
      const sectionIndex = parseInt(previewSectionNode.getAttribute('data-wpss-preview-section-index') || '', 10)
      const sectionId = String(previewSectionNode.getAttribute('data-wpss-preview-section-id') || '')
      if (!Number.isNaN(sectionIndex)) {
        return { type: 'section', sectionIndex, sectionId }
      }
    }

    const sidebarSectionNode = target.closest('[data-wpss-section-pill-index]')
    if (sidebarSectionNode) {
      const sectionIndex = parseInt(sidebarSectionNode.getAttribute('data-wpss-section-pill-index') || '', 10)
      const sectionId = String(sidebarSectionNode.getAttribute('data-wpss-section-id') || '')
      if (!Number.isNaN(sectionIndex)) {
        return { type: 'section', sectionIndex, sectionId }
      }
    }

    return null
  }, [])

  const beginPreviewTouchDrag = useCallback((event, payload) => {
    if (!payload || (event.pointerType && event.pointerType === 'mouse')) {
      return
    }

    const handleNode = event.currentTarget
    if (!(handleNode instanceof Element)) {
      return
    }

    event.preventDefault()
    event.stopPropagation()
    clearPreviewTouchDragState()

    const pointerId = event.pointerId
    const startX = event.clientX
    const startY = event.clientY

    const updateHoverState = (clientX, clientY) => {
      const target = resolvePreviewTouchDropTarget(clientX, clientY)
      if (payload.type === 'section') {
        setSectionDragOverIndex(target?.type === 'section' ? target.sectionIndex : null)
      } else if (payload.type === 'verse') {
        setVerseDragOverIndex(target?.type === 'verse' ? target.verseIndex : null)
      }
    }

    const cleanup = () => {
      window.removeEventListener('pointermove', handleMove)
      window.removeEventListener('pointerup', handleUp)
      window.removeEventListener('pointercancel', handleCancel)
    }

    const activate = () => {
      previewTouchDragTimerRef.current = null
      previewTouchDragSessionRef.current = {
        ...payload,
        pointerId,
        startX,
        startY,
        active: true,
        cleanup,
      }
      if (payload.type === 'section') {
        setSectionDragIndex(payload.index)
      } else if (payload.type === 'verse') {
        setVerseDragIndex(payload.index)
      }
      updateHoverState(startX, startY)
    }

    const finishDrag = (clientX, clientY) => {
      const target = resolvePreviewTouchDropTarget(clientX, clientY)
      let moved = false

      if (payload.type === 'section' && target?.type === 'section') {
        moved = moveSection(payload.index, target.sectionIndex)
      } else if (payload.type === 'verse') {
        if (target?.type === 'verse') {
          moved = moveVerseToPosition(payload.index, target.sectionId, target.verseIndex, 'before')
        } else if (target?.type === 'section') {
          moved = moveVerseToPosition(payload.index, target.sectionId)
        }
      }

      clearPreviewTouchDragState()
      return moved
    }

    const handleMove = (moveEvent) => {
      if (moveEvent.pointerId !== pointerId) {
        return
      }

      const session = previewTouchDragSessionRef.current
      if (!session?.active) {
        if (Math.hypot(moveEvent.clientX - startX, moveEvent.clientY - startY) > TOUCH_DRAG_CANCEL_DISTANCE) {
          clearPreviewTouchDragState()
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

      const isActive = !!previewTouchDragSessionRef.current?.active
      upEvent.preventDefault()
      if (isActive) {
        finishDrag(upEvent.clientX, upEvent.clientY)
      } else {
        clearPreviewTouchDragState()
      }
    }

    const handleCancel = (cancelEvent) => {
      if (cancelEvent.pointerId !== pointerId) {
        return
      }
      clearPreviewTouchDragState()
    }

    previewTouchDragSessionRef.current = {
      ...payload,
      pointerId,
      startX,
      startY,
      active: false,
      cleanup,
    }
    previewTouchDragTimerRef.current = window.setTimeout(activate, TOUCH_DRAG_ACTIVATION_DELAY)
    window.addEventListener('pointermove', handleMove, { passive: false })
    window.addEventListener('pointerup', handleUp)
    window.addEventListener('pointercancel', handleCancel)
  }, [clearPreviewTouchDragState, moveSection, moveVerseToPosition, resolvePreviewTouchDropTarget])

  useEffect(() => () => {
    clearPreviewTouchDragState()
  }, [clearPreviewTouchDragState])

  const handleSectionCommentsChange = (sectionId, comments) => {
    const sections = Array.isArray(editingSong.secciones) ? [...editingSong.secciones] : []
    const index = sections.findIndex((section) => section.id === sectionId)
    if (index === -1) return
    sections[index] = { ...sections[index], comentarios: comments }
    handleSectionChange(sections)
  }

  const handleSectionMidiChange = (sectionId, clips) => {
    const sections = Array.isArray(editingSong.secciones) ? [...editingSong.secciones] : []
    const index = sections.findIndex((section) => section.id === sectionId)
    if (index === -1) return
    sections[index] = { ...sections[index], midi_clips: clips }
    handleSectionChange(sections)
  }

  const handleVerseCommentsChange = (verseIndex, comments) => {
    const nextVerses = Array.isArray(editingSong.versos) ? [...editingSong.versos] : []
    const verse = nextVerses[verseIndex]
    if (!verse) return
    nextVerses[verseIndex] = { ...verse, comentarios: comments }
    handleVerseChange(nextVerses)
  }

  const handleSegmentCommentsChange = (verseIndex, segmentIndex, comments) => {
    const nextVerses = Array.isArray(editingSong.versos) ? [...editingSong.versos] : []
    const verse = nextVerses[verseIndex]
    if (!verse) return
    const segmentos = Array.isArray(verse.segmentos) ? [...verse.segmentos] : []
    if (!segmentos[segmentIndex]) return
    segmentos[segmentIndex] = { ...segmentos[segmentIndex], comentarios: comments }
    nextVerses[verseIndex] = { ...verse, segmentos }
    handleVerseChange(nextVerses)
  }

  const handleVerseMidiChange = (verseIndex, clips) => {
    const nextVerses = Array.isArray(editingSong.versos) ? [...editingSong.versos] : []
    const verse = nextVerses[verseIndex]
    if (!verse) return
    nextVerses[verseIndex] = { ...verse, midi_clips: clips }
    handleVerseChange(nextVerses)
  }

  const handleSegmentMidiChange = (verseIndex, segmentIndex, clips) => {
    const nextVerses = Array.isArray(editingSong.versos) ? [...editingSong.versos] : []
    const verse = nextVerses[verseIndex]
    if (!verse) return
    const segmentos = Array.isArray(verse.segmentos) ? [...verse.segmentos] : []
    const current = segmentos[segmentIndex]
    if (!current) return
    segmentos[segmentIndex] = { ...current, midi_clips: clips }
    nextVerses[verseIndex] = { ...verse, segmentos }
    handleVerseChange(nextVerses)
  }

  const handleVerseChange = (nextVerses) => {
    const nextSong = { ...editingSong, versos: nextVerses }
    syncLegacyFromSections(nextSong, Array.isArray(nextSong.secciones) ? nextSong.secciones : [])
    updateSong({ ...nextSong })
    scheduleAutosave()
  }

  const handleDuplicateSegmentAtIndex = (verseIndex, segmentIndex) => {
    const nextVerses = Array.isArray(editingSong.versos) ? [...editingSong.versos] : []
    const verse = nextVerses[verseIndex]
    if (!verse) return
    const segmentos = Array.isArray(verse.segmentos) ? [...verse.segmentos] : []
    const segment = segmentos[segmentIndex]
    if (!segment) return
    segmentos.splice(segmentIndex + 1, 0, { ...segment })
    nextVerses[verseIndex] = { ...verse, segmentos }
    handleVerseChange(nextVerses)
  }

  const handleRemoveSegmentAtIndex = (verseIndex, segmentIndex) => {
    const nextVerses = Array.isArray(editingSong.versos) ? [...editingSong.versos] : []
    const verse = nextVerses[verseIndex]
    if (!verse) return
    const segmentos = Array.isArray(verse.segmentos) ? [...verse.segmentos] : []
    if (segmentos.length <= 1 || !segmentos[segmentIndex]) return
    segmentos.splice(segmentIndex, 1)
    nextVerses[verseIndex] = { ...adjustEventIndexAfterSegmentRemoval({ ...verse, segmentos }, segmentIndex) }
    handleVerseChange(nextVerses)
  }

  const handleAddSection = () => {
    const nextSections = Array.isArray(editingSong.secciones) ? [...editingSong.secciones] : []
    const section = createSection('', nextSections.length)
    nextSections.push(section)
    const nextSong = { ...editingSong, secciones: nextSections }
    const nextSelected = ensureSectionsIntegrity(nextSong, section.id)
    persistSelectedSection(nextSelected)
    updateSong({ ...nextSong })
    scheduleAutosave()
  }

  const splitSegmentHtml = (element) => {
    if (!element || !element.isContentEditable) {
      const texto = element?.value ?? ''
      const cursor = selectionRef.current.start || 0
      return {
        beforeHtml: texto.slice(0, cursor),
        afterHtml: texto.slice(cursor),
        textLength: texto.length,
        cursor,
      }
    }

    const textLength = element.textContent ? element.textContent.length : 0
    const buildRangeAt = (cursor) => {
      const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT, null)
      let remaining = cursor
      let target = walker.nextNode()
      while (target) {
        const length = target.textContent ? target.textContent.length : 0
        if (remaining <= length) {
          break
        }
        remaining -= length
        target = walker.nextNode()
      }
      if (!target) {
        return null
      }
      const nextRange = document.createRange()
      nextRange.setStart(target, remaining)
      nextRange.setEnd(target, remaining)
      return nextRange
    }

    let range = null
    if (Number.isInteger(selectionRef.current.start)) {
      const clamped = Math.min(Math.max(selectionRef.current.start, 0), textLength)
      range = buildRangeAt(clamped)
    }
    if (!range) {
      const selectionObj = window.getSelection()
      const candidate = selectionObj && selectionObj.rangeCount > 0 ? selectionObj.getRangeAt(0) : null
      if (candidate && element.contains(candidate.startContainer)) {
        range = candidate
      }
    }
    if (!range) {
      return { beforeHtml: '', afterHtml: '', textLength, cursor: 0 }
    }

    const preRange = range.cloneRange()
    preRange.selectNodeContents(element)
    preRange.setEnd(range.startContainer, range.startOffset)
    const cursor = preRange.toString().length
    const safeCursor = Math.min(Math.max(cursor, 0), textLength)

    const toHtml = (r) => {
      const div = document.createElement('div')
      div.appendChild(r.cloneContents())
      return div.innerHTML
    }

    const beforeHtml = toHtml(preRange)
    const postRange = range.cloneRange()
    postRange.selectNodeContents(element)
    postRange.setStart(range.endContainer, range.endOffset)
    const afterHtml = toHtml(postRange)

    return { beforeHtml, afterHtml, textLength, cursor: safeCursor }
  }

  return (
    <section ref={editorRef} className="wpss-panel wpss-panel--editor">
      <header className="wpss-panel__header">
        <div>
          <h2>{editingSong.id ? editingSong.titulo || 'Canción' : wpData?.strings?.newSong || 'Nueva canción'}</h2>
          <p className="wpss-panel__meta">
            {editingSong.id ? `ID ${editingSong.id}` : '—'}
            {editingSong.es_reversion
              ? ` · Reversión de ${editingSong.reversion_origen_titulo || `#${editingSong.reversion_origen_id || '—'}`}`
              : ''}
            {editingSong.es_reversion && editingSong.reversion_autor_origen_nombre
              ? ` · Autor origen: ${editingSong.reversion_autor_origen_nombre}`
              : ''}
          </p>
        </div>
          <div className="wpss-panel__actions">
            {onShowList ? (
              <button
                type="button"
                className="button button-secondary"
                onClick={onShowList}
              >
                Ver canciones
              </button>
            ) : null}
            <button
              className="button"
              type="button"
              onClick={handleOpenReadingView}
          >
            {wpData?.strings?.readingView || 'Vista de lectura'}
          </button>
          <button className="button button-primary" type="button" onClick={() => saveSong(false)}>
            {wpData?.strings?.saveSong || 'Guardar canción'}
          </button>
          {canDeleteSong ? (
            <button className="button button-danger" type="button" onClick={handleDeleteSong}>
              Eliminar
            </button>
          ) : null}
            {state.saving ? <span className="wpss-save-status">Guardando…</span> : null}
        </div>
      </header>

      {state.error ? (
        <div className="notice notice-error">
          <p>{state.error}</p>
        </div>
      ) : null}
      {state.feedback?.message ? (
        <div className={`notice notice-${state.feedback.type || 'success'}`}>
          <p>{state.feedback.message}</p>
        </div>
      ) : null}

      <form className="wpss-editor" onSubmit={(event) => event.preventDefault()}>
        <div className="wpss-section wpss-section--meta wpss-section--discreet">
          <header>
            <h3>Datos base</h3>
          </header>
          <div className="wpss-field-group">
            <label>
              <span>Título</span>
              <input
                type="text"
                value={editingSong.titulo}
                onChange={(event) => {
                  updateSong({ ...editingSong, titulo: event.target.value })
                  scheduleAutosave()
                }}
              />
            </label>
            <label>
              <span>Tónica</span>
              <input
                type="text"
                value={editingSong.tonica}
                onChange={(event) => {
                  updateSong({ ...editingSong, tonica: event.target.value })
                  scheduleAutosave()
                }}
              />
            </label>
            <label>
              <span>BPM global</span>
              <input
                type="number"
                min="40"
                max="240"
                value={editingSong.bpm ?? 120}
                onChange={(event) => {
                  const next = parseInt(event.target.value, 10)
                  updateSong({ ...editingSong, bpm: Number.isInteger(next) ? next : 120 })
                  scheduleAutosave()
                }}
              />
            </label>
          </div>
          <div className="wpss-field-group">
            <label>
              <span>Campo armónico (modo)</span>
              <select
                value={editingSong.campo_armonico}
                onChange={(event) => {
                  updateSong({ ...editingSong, campo_armonico: event.target.value })
                  scheduleAutosave()
                }}
              >
                {camposOptions.map((option) => (
                  <option key={option} value={option}>
                    {option || 'Selecciona un modo'}
                  </option>
                ))}
              </select>
            </label>
            <label>
              <span>Campo armónico predominante</span>
              <textarea
                value={editingSong.campo_armonico_predominante}
                onChange={(event) => {
                  updateSong({ ...editingSong, campo_armonico_predominante: event.target.value })
                  scheduleAutosave()
                }}
              />
            </label>
            <label className="wpss-tags-field">
              <span>Tags</span>
              <div className="wpss-tags-meta">
                <span className="wpss-tags-count">
                  {selectedTags.length
                    ? `${selectedTags.length} tag${selectedTags.length === 1 ? '' : 's'} asignada${selectedTags.length === 1 ? '' : 's'}`
                    : 'Sin tags asignadas'}
                </span>
                <button
                  type="button"
                  className="button button-small wpss-tags-clear"
                  onClick={handleClearTags}
                  disabled={!selectedTags.length}
                >
                  Limpiar
                </button>
              </div>
              <div className="wpss-tags-input">
                {selectedTags.length ? selectedTags.map((tag) => {
                  const key = tag?.id || tag?.slug || tag?.name
                  const label = getTagLabel(tag)
                  return (
                    <button
                      key={key}
                      type="button"
                      className="wpss-tag-chip"
                      onClick={() => handleRemoveTag(tag)}
                      aria-label={`Quitar tag ${label}`}
                    >
                      <span>{label || 'Sin nombre'}</span>
                      <span aria-hidden="true">×</span>
                    </button>
                  )
                }) : (
                  <span className="wpss-tags-empty">Agregá la primera tag para categorizar la canción.</span>
                )}
                <input
                  type="text"
                  value={tagInputValue}
                  onChange={handleTagInputChange}
                  onKeyDown={handleTagInputKeyDown}
                  onFocus={handleTagInputFocus}
                  onBlur={handleTagInputBlur}
                  list="wpss-song-tags"
                  placeholder="Buscar o crear tag (Enter o coma)"
                />
                <button
                  type="button"
                  className="button button-secondary wpss-tags-add"
                  onMouseDown={(event) => event.preventDefault()}
                  onClick={handleCreateTagFromInput}
                  disabled={!tagInputNormalized}
                >
                  Agregar
                </button>
              </div>
              <small className="wpss-tags-help">Enter agrega, Backspace quita la última. Escape limpia la búsqueda.</small>
              {(showTagSuggestions && (filteredTagSuggestions.length || tagInputNormalized)) ? (
                <div className="wpss-tag-suggestions" role="listbox" aria-label="Tags sugeridas">
                  <div className="wpss-tag-suggestions__header">
                    <span>
                      {tagInputNormalized ? `Coincidencias para "${tagInputNormalized}"` : 'Sugerencias'}
                    </span>
                    <span className="wpss-tag-suggestions__count">
                      {filteredTagSuggestions.length} disponibles
                    </span>
                  </div>
                  <div className="wpss-tag-suggestions__list">
                    {canCreateTag ? (
                      <button
                        type="button"
                        className="wpss-tag-suggestion wpss-tag-suggestion--create"
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={handleCreateTagFromInput}
                      >
                        <span className="wpss-tag-suggestion__icon">+</span>
                        <span className="wpss-tag-suggestion__name">Crear "{tagInputNormalized}"</span>
                      </button>
                    ) : null}
                    {paginatedTagSuggestions.map((tag) => (
                      <button
                        key={tag.id || tag.slug || tag.name}
                        type="button"
                        className="wpss-tag-suggestion"
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={() => handleSelectSuggestedTag(tag)}
                      >
                        <span className="wpss-tag-suggestion__name">{getTagLabel(tag)}</span>
                        {Number.isInteger(Number(tag?.count)) ? (
                          <span className="wpss-tag-suggestion__count">{Number(tag.count)}</span>
                        ) : null}
                      </button>
                    ))}
                    {!paginatedTagSuggestions.length && !canCreateTag ? (
                      <div className="wpss-tag-suggestions__empty">No hay tags que coincidan.</div>
                    ) : null}
                  </div>
                  {filteredTagSuggestions.length > TAG_SUGGESTIONS_PAGE_SIZE ? (
                    <div className="wpss-tag-suggestions__pagination">
                      <button
                        type="button"
                        className="button button-small"
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={() => setTagSuggestionsPage((page) => Math.max(1, page - 1))}
                        disabled={tagSuggestionsPage <= 1}
                      >
                        ← Anterior
                      </button>
                      <span>
                        Página {Math.min(tagSuggestionsPage, totalTagSuggestionPages)} de {totalTagSuggestionPages}
                      </span>
                      <button
                        type="button"
                        className="button button-small"
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={() => setTagSuggestionsPage((page) => Math.min(totalTagSuggestionPages, page + 1))}
                        disabled={tagSuggestionsPage >= totalTagSuggestionPages}
                      >
                        Siguiente →
                      </button>
                    </div>
                  ) : null}
                </div>
              ) : null}
            </label>
          </div>
          <div className="wpss-field-group">
            <label className="wpss-field">
              <span>Repertorios asignados</span>
              <div className="wpss-collections__shared-list">
                {availableCollections.length ? availableCollections.map((collection) => {
                  const collectionId = Number(collection?.id)
                  const assigned = (editingSong.colecciones || []).find((item) => Number(item?.id) === collectionId)
                  return (
                    <label key={collectionId} className="wpss-collections__shared-item">
                      <input
                        type="checkbox"
                        checked={!!assigned}
                        onChange={() => handleToggleCollection(collection)}
                      />
                      <span>
                        <strong>{collection?.nombre || `Repertorio #${collectionId}`}</strong>
                        {assigned ? <small> · {formatCollectionAssignment(assigned)}</small> : null}
                      </span>
                    </label>
                  )
                }) : <span>No hay repertorios disponibles todavía.</span>}
              </div>
            </label>
          </div>
          <SongMediaPermissionsFields
            key={mediaPermissionsKey}
            song={editingSong}
            onChangeSong={(nextSong) => updateSong(nextSong)}
            onRequestAutosave={() => saveSong(true)}
          />
          <details className="wpss-section wpss-section--collapsible wpss-section--nested">
            <summary>
              <span>Ficha técnica y metadatos</span>
            </summary>
            <div className="wpss-field-group">
              <label>
                <span>Autor(es)</span>
                <input
                  type="text"
                  value={editingSong.ficha_autores || ''}
                  onChange={(event) => {
                    updateSong({ ...editingSong, ficha_autores: event.target.value })
                    scheduleAutosave()
                  }}
                />
              </label>
              <label>
                <span>Año</span>
                <input
                  type="text"
                  value={editingSong.ficha_anio || ''}
                  onChange={(event) => {
                    updateSong({ ...editingSong, ficha_anio: event.target.value })
                    scheduleAutosave()
                  }}
                />
              </label>
              <label>
                <span>Pais</span>
                <input
                  type="text"
                  value={editingSong.ficha_pais || ''}
                  onChange={(event) => {
                    updateSong({ ...editingSong, ficha_pais: event.target.value })
                    scheduleAutosave()
                  }}
                />
              </label>
            </div>
            <div className="wpss-field-group">
              <SongVisibilityAccessFields
                song={editingSong}
                availableProjects={availableProjects}
                onChangeSong={(nextSong) => updateSong(nextSong)}
                onRequestAutosave={() => saveSong(true)}
              />
              <label>
                <span>Estado legal</span>
                <select
                  value={editingSong.ficha_estado_legal || ''}
                  onChange={(event) => {
                    updateSong({ ...editingSong, ficha_estado_legal: event.target.value })
                    scheduleAutosave()
                  }}
                >
                  <option value="">Selecciona</option>
                  <option value="dominio_publico">Dominio publico</option>
                  <option value="cc">CC</option>
                  <option value="licencia_directa">Licencia directa</option>
                </select>
              </label>
              {editingSong.ficha_estado_legal === 'cc' ? (
                <label>
                  <span>CC (especificar)</span>
                  <input
                    type="text"
                    value={editingSong.ficha_licencia || ''}
                    onChange={(event) => {
                      updateSong({ ...editingSong, ficha_licencia: event.target.value })
                      scheduleAutosave()
                    }}
                  />
                </label>
              ) : null}
              <label>
                <span>Fuente de verificacion</span>
                <input
                  type="text"
                  value={editingSong.ficha_fuente_verificacion || ''}
                  onChange={(event) => {
                    updateSong({ ...editingSong, ficha_fuente_verificacion: event.target.value })
                    scheduleAutosave()
                  }}
                />
              </label>
            </div>
            <div className="wpss-field">
              <span>Administración de grupos de ensayo</span>
              <p className="wpss-collections__hint">
                Los proyectos seleccionados podrán grabar y adjuntar audios dentro del área de ensayos. Estos materiales no se mezclan con los adjuntos generales de la canción.
              </p>
              {availableProjects.length ? (
                <>
                  <div className="wpss-collections__shared-list">
                    {availableProjects.map((project) => {
                      const projectId = Number(project?.id)
                      const checked = selectedRehearsalProjectIds.includes(projectId)

                      return (
                        <label key={projectId} className="wpss-collections__shared-item">
                          <input
                            type="checkbox"
                            checked={checked}
                            onChange={() => {
                              const nextIds = checked
                                ? selectedRehearsalProjectIds.filter((item) => item !== projectId)
                                : selectedRehearsalProjectIds.concat(projectId)
                              updateSong({
                                ...editingSong,
                                rehearsal_project_ids: nextIds,
                              })
                              scheduleAutosave()
                            }}
                          />
                          <span>
                            <strong>{project?.titulo || `Proyecto #${projectId}`}</strong>
                            {Array.isArray(project?.colaboradores) && project.colaboradores.length ? (
                              <small>{` · ${project.colaboradores.map((item) => item.nombre).join(' · ')}`}</small>
                            ) : (
                              <small> · Sin colaboradores asignados</small>
                            )}
                          </span>
                        </label>
                      )
                    })}
                  </div>
                  <p className="wpss-collections__hint">
                    {selectedRehearsalProjects.length
                      ? `${selectedRehearsalProjects.length} proyecto(s) habilitado(s) para ensayos.`
                      : 'Si no seleccionas proyectos, la canción no tendrá grupos de ensayo habilitados.'}
                  </p>
                </>
              ) : (
                <span>No hay proyectos disponibles todavía.</span>
              )}
            </div>
            <label className="wpss-toggle">
              <input
                type="checkbox"
                checked={!!editingSong.ficha_incompleta}
                onChange={(event) => {
                  updateSong({ ...editingSong, ficha_incompleta: event.target.checked })
                  scheduleAutosave()
                }}
              />
              <span>Ficha incompleta</span>
            </label>
            {editingSong.ficha_incompleta ? (
              <label>
                <span>Motivo</span>
                <textarea
                  value={editingSong.ficha_incompleta_motivo || ''}
                  onChange={(event) => {
                    updateSong({ ...editingSong, ficha_incompleta_motivo: event.target.value })
                    scheduleAutosave()
                  }}
                />
              </label>
            ) : null}
          </details>
        </div>

        <section
          ref={mainSectionRef}
          className={`wpss-section wpss-section--main ${isEditorFullscreen ? 'is-editor-fullscreen' : ''}`}
        >
          <header>
            <h3>Secciones, versos y segmentos</h3>
          </header>
          <div
            ref={layoutRef}
            className={`wpss-editor-layout ${isSidebarCollapsed ? 'is-sidebar-collapsed' : ''}`}
            style={{
              gridTemplateColumns: editorGridTemplateColumns,
            }}
          >
            {!isFocusWork || useMasterPreview ? (
              <aside ref={sidebarRef} className="wpss-editor-sidebar wpss-editor-column">
                <div className={`wpss-editor-sidebar__content nav-${navLevel}`}>
                  <div className="wpss-editor-sidebar__header">
                    <strong>Secciones</strong>
                    <button
                      type="button"
                      className="button button-secondary"
                      onClick={handleAddSection}
                    >
                      Añadir sección
                    </button>
                  </div>
                  <div className="wpss-section-pill-list">
                    {(Array.isArray(editingSong.secciones) ? editingSong.secciones : []).map((section, index) => (
	                      <div
	                        key={section.id}
	                        className={`wpss-section-pill ${activeSectionId === section.id ? 'is-active' : ''} ${
	                          sectionDragOverIndex === index ? 'is-dragover' : ''
	                        }`}
	                        data-wpss-section-pill-index={index}
	                        data-wpss-section-id={section.id}
	                        role="button"
	                        tabIndex={0}
                        onClick={(event) => {
                          if (event.target && event.target.tagName === 'INPUT') {
                            return
                          }
                          if (event.target && event.target.closest?.('.wpss-section-pill__actions')) {
                            return
                          }
                          selectSectionOnly(section.id)
                          if (navLevel === 'verses') {
                            setSelectedVerseIndexes(new Set())
                            setExpandedSectionId(section.id)
                          }
                        }}
                        onDoubleClick={(event) => {
                          if (event.target && event.target.tagName === 'INPUT') {
                            return
                          }
                          if (event.target && event.target.closest?.('.wpss-section-pill__actions')) {
                            return
                          }
                          event.preventDefault()
                        }}
                        onKeyDown={(event) => {
                          if (event.target && event.target.tagName === 'INPUT') {
                            return
                          }
                          if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault()
                            if (navLevel === 'verses') {
                              selectSectionOnly(section.id)
                              setSelectedVerseIndexes(new Set())
                              setExpandedSectionId(section.id)
                            } else {
                              enterVerseLevel(section.id)
                            }
                          }
                        }}
                        onDragOver={(event) => {
                          event.preventDefault()
                          setSectionDragOverIndex(index)
                        }}
                        onDrop={(event) => {
                          event.preventDefault()
                          const payload = parseInt(event.dataTransfer.getData('text/plain'), 10)
                          const fromIndex = Number.isNaN(payload) ? sectionDragIndex : payload
                          if (fromIndex !== null && fromIndex !== undefined) {
                            moveSection(fromIndex, index)
                          }
                          setSectionDragIndex(null)
                          setSectionDragOverIndex(null)
                        }}
                      >
	                        <span
	                          className="wpss-section-pill__drag"
	                          draggable
	                          aria-label="Mover sección"
	                          title="Arrastra para ordenar"
	                          onClick={(event) => event.stopPropagation()}
	                          onPointerDown={(event) => {
	                            event.stopPropagation()
	                            beginPreviewTouchDrag(event, { type: 'section', index })
	                          }}
	                          onContextMenu={(event) => event.preventDefault()}
	                          onDragStart={(event) => {
	                            event.dataTransfer.setData('text/plain', String(index))
	                            event.dataTransfer.effectAllowed = 'move'
                            setSectionDragIndex(index)
                          }}
                          onDragEnd={() => {
                            setSectionDragIndex(null)
                            setSectionDragOverIndex(null)
                          }}
                        >
                          ☰
                        </span>
                        <input
                          className="wpss-section-pill__name"
                          type="text"
                          value={section.nombre || ''}
                          placeholder={getDefaultSectionName(index)}
                          maxLength={64}
                          onClick={(event) => event.stopPropagation()}
                          onPointerDown={(event) => event.stopPropagation()}
                          onKeyDown={(event) => event.stopPropagation()}
                          onChange={(event) => handleSectionNameChange(section.id, event.target.value)}
                        />
                        <em>{sectionCounts.get(section.id) || 0}</em>
                      </div>
                    ))}
                    <button
                      type="button"
                      className="wpss-section-pill wpss-section-pill--ghost"
                      onClick={handleAddSection}
                    >
                      <span className="wpss-section-pill__ghost-plus">＋</span>
                      <span>Nueva sección</span>
                    </button>
                  </div>
                </div>
              </aside>
            ) : null}
            {!isFocusWork || useMasterPreview ? (
              <div
                className="wpss-editor-splitter wpss-editor-splitter--sidebar"
                role="separator"
                aria-orientation="vertical"
                title="Arrastra para ajustar el tamaño"
                onPointerDown={(event) => {
                  event.preventDefault()
                  setIsResizingSidebar(true)
                }}
              />
            ) : null}
            {isFocusWork && !useMasterPreview ? (
              <div className={`wpss-editor-work wpss-editor-column nav-${navLevel}`}>
              {isFocusWork ? (
                <div className="wpss-work-breadcrumb">
                  <button type="button" className="button button-small" onClick={backToSections}>
                    Secciones
                  </button>
                  <span className="wpss-breadcrumb-sep">›</span>
                  <button
                    type="button"
                    className="button button-small button-secondary"
                    onClick={() => {
                      clearVerseSelection()
                      setNavLevel('verses')
                    }}
                  >
                    {activeSection?.nombre || getDefaultSectionName(0)}
                  </button>
                  {navLevel === 'manage' ? (
                    <>
                      <span className="wpss-breadcrumb-sep">›</span>
                      <span className="wpss-breadcrumb-current">Administrar</span>
                    </>
                  ) : null}
                  {navLevel === 'verses' && selectedVerseLabel ? (
                    <>
                      <span className="wpss-breadcrumb-sep">›</span>
                      <span className="wpss-breadcrumb-current">{selectedVerseLabel}</span>
                    </>
                  ) : null}
                  {navLevel === 'verses' ? (
                    <div className="wpss-work-breadcrumb__controls">
                      <label>
                        <span>Verso</span>
                        <select
                          value={selectedVerseIndex ?? ''}
                          onChange={(event) => {
                            const nextIndex = Number(event.target.value)
                            if (Number.isNaN(nextIndex)) {
                              clearVerseSelection()
                              return
                            }
                            setSelectedVerseIndexes(new Set([nextIndex]))
                          }}
                        >
                          <option value="">Selecciona un verso</option>
                          {versesInActiveSection.map(({ verse, index }, verseIndex) => (
                            <option key={`verse-select-${index}`} value={index}>
                              {verse.nombre
                                ? String(verse.nombre)
                                : verse.instrumental
                                  ? `Instrumental ${verseIndex + 1}`
                                  : `Verso ${verseIndex + 1}`}
                            </option>
                          ))}
                        </select>
                      </label>
                    </div>
                  ) : null}
                </div>
              ) : null}
              {navLevel === 'verses' ? (
                <div className="wpss-section-tools">
                  <div className="wpss-section-tools__header">
                    <strong>{activeSection?.nombre || getDefaultSectionName(0)}</strong>
                    <span>Notas y MIDI de sección</span>
                  </div>
                  <MidiClipList
                    clips={activeSection?.midi_clips}
                    onChange={(clips) => handleSectionMidiChange(activeSectionId, clips)}
                    emptyLabel="Añadir MIDI a la sección"
                    defaultTempo={editingSong.bpm}
                    compactRows={preferCompactMidiRows}
                    allowRowToggle={preferCompactMidiRows}
                    rangePresets={midiRangePresets}
                    defaultRange={midiRangeDefault}
                    lockRange={lockMidiRange}
                  />
                  <CommentEditor
                    label="Notas de sección"
                    comments={activeSection?.comentarios || []}
                    defaultTitle={activeSection?.nombre || getDefaultSectionName(0)}
                    onChange={(next) => handleSectionCommentsChange(activeSectionId, next)}
                  />
                </div>
              ) : null}
              {showSectionEmptyState ? (
                <div className="wpss-work-empty">
                  <p>Selecciona una sección para comenzar.</p>
                </div>
              ) : navLevel === 'manage' ? (
                <div className="wpss-work-manage">
                  <SectionsPanel
                    sections={editingSong.secciones}
                    selectedSectionId={activeSectionId}
                    verses={editingSong.versos}
                    songBpm={editingSong.bpm}
                    onSelect={selectSectionOnly}
                    onChange={handleSectionChange}
                    onDuplicate={handleDuplicateSection}
                    compactMidiRows={preferCompactMidiRows}
                    allowMidiRowToggle={preferCompactMidiRows}
                    midiRangePresets={midiRangePresets}
                    midiRangeDefault={midiRangeDefault}
                    lockMidiRange={lockMidiRange}
                    filterSectionId={activeSectionId}
                    onQuickUploadAttachment={handleQuickUploadAttachment}
                  />
                  <CommentEditor
                    label="Notas de sección"
                    comments={activeSection?.comentarios || []}
                    defaultTitle={activeSection?.nombre || getDefaultSectionName(0)}
                    onChange={(next) => handleSectionCommentsChange(activeSectionId, next)}
                  />
                </div>
              ) : navLevel === 'verses' ? (
                <div
                  className="wpss-work-verse-explorer"
                  onClickCapture={handleVerseSelectionClearClick}
                >
                  <div className="wpss-work-verse-list">
                    <div className="wpss-work-verse-list__header">
                      <p>Selecciona un verso para editarlo.</p>
                      <button type="button" className="button button-secondary" onClick={handleAddVerse}>
                        Añadir verso
                      </button>
                    </div>
                    <div className="wpss-work-verse-sections">
                      {sectionsList.map((section, sectionIndex) => {
                        const sectionVerses = versesBySection.get(section.id) || []
                        const sectionIndicators = buildContentIndicators({
                          attachments: getSectionLevelAttachments(editingSong, section.id),
                          comments: section?.comentarios,
                          midiClips: section?.midi_clips,
                        })
                        const isActiveSection = section.id === activeSectionId
                        const previewVerses = sectionVerses.slice(0, 2)
                        return (
                          <section
                            key={`verse-browser-${section.id}`}
                            className={`wpss-verse-section-mini ${isActiveSection ? 'is-active' : ''}`}
                          >
                            <header className="wpss-verse-section-mini__header">
                              <button
                                type="button"
                                className="wpss-verse-section-mini__title"
                                onClick={() => selectSectionOnly(section.id)}
                              >
                                {section.nombre || getDefaultSectionName(sectionIndex)}
                              </button>
                              <ContentIndicators
                                items={sectionIndicators}
                                onSelect={(item) => openContextualTarget({ type: 'section', sectionId: section.id }, item.tabId)}
                              />
                              <span>{sectionVerses.length} versos</span>
                              <button
                                type="button"
                                className="button button-small"
                                onClick={() => handleAddVerseToSection(section.id)}
                              >
                                + verso
                              </button>
                            </header>
                            {!isActiveSection ? (
                              <div className="wpss-verse-section-mini__summary">
                                {previewVerses.length ? (
                                  <ul>
                                    {previewVerses.map(({ verse, index: verseId }) => (
                                      <li key={`verse-mini-summary-${section.id}-${verseId}`}>{getVerseSummary(verse)}</li>
                                    ))}
                                  </ul>
                                ) : (
                                  <p className="wpss-empty">Sin versos en esta sección.</p>
                                )}
                              </div>
                            ) : (
                              <>
                                <div className="wpss-work-verse-list__grid">
                                  {sectionVerses.length ? (
                                    sectionVerses.map(({ verse, index }, verseIndex) => {
                                      const isSelectedCard =
                                        selectedVerseIndex !== null
                                        && selectedVerseIndex === index
                                        && selectedVerse?.section_id === section.id
                                      const preview = getVerseStackPreview(verse)
                                      const verseIndicators = buildContentIndicators({
                                        attachments: getVerseLevelAttachments(editingSong, index),
                                        comments: verse?.comentarios,
                                        midiClips: verse?.midi_clips,
                                      })
                                      if (isSelectedCard) {
                                        return (
                                          <div
                                            key={`verse-card-editor-${section.id}-${index}`}
                                            className="wpss-verse-inline-editor"
                                          >
                                            <VersesPanel
                                              verses={editingSong.versos}
                                              sections={editingSong.secciones}
                                              selectedSectionId={activeSectionId}
                                              songBpm={editingSong.bpm}
                                              onSelectSection={selectSectionOnly}
                                              onSectionsChange={handleSectionChange}
                                              onAddSection={handleAddSection}
                                              onDuplicateSection={handleDuplicateSection}
                                              onChange={handleVerseChange}
                                              onSplitSegment={splitSegment}
                                              onSplitVerse={splitVerseFromCursor}
                                              onSplitSection={splitSectionFromCursor}
                                              onSelectionChange={updateSegmentSelection}
                                              focusRequest={verseFocusRequest}
                                              onFocusRequestHandled={handleVerseFocusHandled}
                                              compactMidiRows={preferCompactMidiRows}
                                              allowMidiRowToggle={preferCompactMidiRows}
                                              midiRangePresets={midiRangePresets}
                                              midiRangeDefault={midiRangeDefault}
                                              lockMidiRange={lockMidiRange}
                                              showHeader={false}
                                              showPreview={false}
                                              visibleVerseIndexes={new Set([index])}
                                              songAttachments={editingSong.adjuntos}
                                              onContextIndicatorSelect={openContextualTarget}
                                              onQuickUploadAttachment={handleQuickUploadAttachment}
                                              onBeginSegmentDrag={beginSegmentDrag}
                                              onEndSegmentDrag={clearSegmentDrag}
                                              onMoveSegmentToVerse={moveSegmentToTargetVerse}
                                              onMoveSegmentToNewVerse={moveSegmentToNewVerse}
                                              useContextualToolbar
                                            />
                                          </div>
                                        )
                                      }
                                      return (
                                      <div
                                        key={`verse-card-${section.id}-${index}`}
                                        className={`wpss-verse-card-mini ${
                                          selectedVerseIndex === index ? 'is-selected' : ''
                                        } ${verseDragOverIndex === index ? 'is-dragover' : ''}`}
                                        role="button"
                                        tabIndex={0}
                                        onClick={() => handleSelectVerse(section.id, index)}
                                        onKeyDown={(event) => {
                                          if (event.key === 'Enter' || event.key === ' ') {
                                            event.preventDefault()
                                            handleSelectVerse(section.id, index)
                                          }
                                        }}
                                        onDragOver={(event) => handleVerseCardDragOver(event, index)}
                                        onDrop={(event) => handleVerseCardDrop(event, index)}
                                      >
                                        <span
                                          className={`wpss-verse-card-mini__drag ${
                                            verseDragIndex === index ? 'is-dragging' : ''
                                          }`}
                                          draggable
                                          aria-label="Mover verso"
                                          title="Arrastra para ordenar"
                                          onClick={(event) => event.stopPropagation()}
                                          onPointerDown={(event) => event.stopPropagation()}
                                          onDragStart={(event) => beginVerseDrag(event, index)}
                                          onDragEnd={handleVerseCardDragEnd}
                                        >
                                          ☰
                                        </span>
                                        <strong>
                                          <span>
                                            {verse.nombre
                                              ? verse.nombre
                                              : verse.instrumental
                                                ? `Instrumental ${verseIndex + 1}`
                                                : `Verso ${verseIndex + 1}`}
                                          </span>
                                          <ContentIndicators
                                            items={verseIndicators}
                                            onSelect={(item) =>
                                              openContextualTarget(
                                                {
                                                  type: 'verse',
                                                  sectionId: verse.section_id || section.id,
                                                  verseIndex: index,
                                                },
                                                item.tabId,
                                              )
                                            }
                                          />
                                        </strong>
                                        <pre className="wpss-verse-card-mini__stack">{`${preview.chords}\n${preview.lyrics}`}</pre>
                                      </div>
                                      )
                                    })
                                  ) : null}
                                  <button
                                    type="button"
                                    className="wpss-verse-card-mini wpss-verse-card-mini--ghost"
                                    onClick={() => handleAddVerseToSection(section.id)}
                                  >
                                    <strong>Nuevo verso</strong>
                                    <span className="wpss-ghost-invite__hint">Agregar a esta sección</span>
                                  </button>
                                </div>
                              </>
                            )}
                          </section>
                        )
                      })}
                    </div>
                  </div>
                </div>
              ) : (
                <VersesPanel
                  verses={editingSong.versos}
                  sections={editingSong.secciones}
                  selectedSectionId={activeSectionId}
                  songBpm={editingSong.bpm}
                  onSelectSection={selectSectionOnly}
                  onSectionsChange={handleSectionChange}
                  onAddSection={handleAddSection}
                  onDuplicateSection={handleDuplicateSection}
                  onChange={handleVerseChange}
                  onSplitSegment={splitSegment}
                  onSplitVerse={splitVerseFromCursor}
                  onSplitSection={splitSectionFromCursor}
                  onSelectionChange={updateSegmentSelection}
                  focusRequest={verseFocusRequest}
                  onFocusRequestHandled={handleVerseFocusHandled}
                  compactMidiRows={preferCompactMidiRows}
                  allowMidiRowToggle={preferCompactMidiRows}
                  midiRangePresets={midiRangePresets}
                  midiRangeDefault={midiRangeDefault}
                  lockMidiRange={lockMidiRange}
                  showHeader={false}
                  showPreview={false}
                  visibleVerseIndexes={hasVerseFilter ? selectedVerseIndexes : null}
                  songAttachments={editingSong.adjuntos}
                  onContextIndicatorSelect={openContextualTarget}
                  onQuickUploadAttachment={handleQuickUploadAttachment}
                  onBeginSegmentDrag={beginSegmentDrag}
                  onEndSegmentDrag={clearSegmentDrag}
                  onMoveSegmentToVerse={moveSegmentToTargetVerse}
                  onMoveSegmentToNewVerse={moveSegmentToNewVerse}
                  useContextualToolbar
                />
              )}
            </div>
            ) : null}
            {isFocusWork && !useMasterPreview ? (
              <div
                className="wpss-editor-splitter"
                role="separator"
                aria-orientation="vertical"
                title="Arrastra para ajustar el tamaño"
                onPointerDown={(event) => {
                  event.preventDefault()
                  setIsResizingPreview(true)
                }}
              />
            ) : null}
            <aside
              className={`wpss-editor-preview wpss-editor-column ${isFocusWork && !useMasterPreview ? '' : 'is-sections'}`}
              onClickCapture={handleVerseSelectionClearClick}
            >
              <div className="wpss-section-preview">
                <div className="wpss-section-preview__header">
                  <strong>Vista previa</strong>
                  <div className="wpss-section-preview__header-actions">
                    <span>
                      {useMasterPreview ? 'Todas las secciones' : isFocusWork ? activeSection?.nombre || getDefaultSectionName(0) : 'Todas las secciones'}
                    </span>
	                    <div className="wpss-section-preview__context-actions">
	                      <button type="button" className="button button-small" onClick={handleAddSection}>
	                        + Sección
                      </button>
                      <button
                        type="button"
                        className="button button-small"
                        onClick={handleAddVerse}
                        disabled={!activeSectionId}
	                      >
	                        + Verso
	                      </button>
	                      <button
	                        type="button"
	                        className={`button button-small ${contextualTarget.type === 'song' ? 'button-secondary' : ''}`}
	                        onClick={() => {
	                          if (contextualTarget.type === 'song') {
	                            setContextualScopeMode('auto')
	                            setIsContextualToolbarExpanded(false)
	                            return
	                          }
	                          setSelectedAttachmentId(null)
	                          setContextualScopeMode('song')
	                          setContextualToolTabsByTarget((prev) => ({ ...prev, song: prev.song || 'audio' }))
	                          setIsContextualToolbarExpanded(true)
	                        }}
	                      >
	                        {contextualTarget.type === 'song' ? 'Salir de canción' : 'Canción'}
	                      </button>
	                    </div>
                    {isCompactPreviewViewport ? (
                      <div className="wpss-preview-zoom-controls" role="group" aria-label="Zoom de vista previa">
                        <button
                          type="button"
                          className="button button-small"
                          onClick={() => handlePreviewScaleStep(-1)}
                          disabled={!canPreviewZoomOut}
                        >
                          -
                        </button>
                        <button
                          type="button"
                          className="button button-small wpss-preview-zoom-reset"
                          onClick={() => setPreviewScale(100)}
                          disabled={previewScale === 100}
                        >
                          {`${previewScale}%`}
                        </button>
                        <button
                          type="button"
                          className="button button-small"
                          onClick={() => handlePreviewScaleStep(1)}
                          disabled={!canPreviewZoomIn}
                        >
                          +
                        </button>
                      </div>
                    ) : null}
                    {useMasterPreview ? (
                      <button type="button" className="button button-small" onClick={clearVerseSelection}>
                        Plegar todo
                      </button>
                    ) : null}
                  </div>
                </div>
                {showPreviewAttachments && songLevelAttachments.length ? (
                  <EditorPreviewMediaAttachments
                    attachments={songLevelAttachments}
                    title="Adjuntos de la canción"
                    compact
                    activeAttachmentId={selectedAttachmentId}
                    onSelectAttachment={handleAttachmentSelect}
                    pendingActionById={pendingAttachmentActions}
                  />
	                ) : null}
	                <div className="wpss-section-preview__workspace-tools" ref={workspaceToolsRef}>
	                  <div className="wpss-section-preview__tools wpss-section-preview__tools--contextual">
	                    <div className="wpss-section-preview__tools-bar">
	                      <div className="wpss-section-preview__tools-context">
	                        <span className="wpss-section-preview__tools-label">
	                          {contextualTarget.type === 'song'
	                            ? 'Herramientas de la canción'
	                            : contextualTarget.type === 'section'
	                            ? 'Herramientas de la sección'
	                            : contextualTarget.type === 'verse'
	                              ? 'Herramientas del verso'
                              : 'Herramientas del segmento'}
                        </span>
	                        <strong>{contextualTarget.label}</strong>
	                        {contextualTarget.meta ? <span>{contextualTarget.meta}</span> : null}
	                      </div>
	                      <div
	                        className="wpss-section-preview__tool-tabs"
	                        role="tablist"
	                        aria-label="Barra contextual"
	                      >
	                        {contextualTabItems.map((tab) => (
	                          <button
	                            key={`context-tool-${tab.id}`}
	                            type="button"
	                            role="tab"
	                            className={`button button-small wpss-section-preview__tool-tab ${
	                              contextualToolTab === tab.id && isContextualToolbarExpanded ? 'is-active' : ''
	                            }`}
	                            aria-selected={contextualToolTab === tab.id && isContextualToolbarExpanded}
	                            onClick={() => handleContextualTabToggle(tab.id)}
	                          >
	                            <span>{tab.label}</span>
	                          </button>
	                        ))}
	                      </div>
	                    </div>
	                    {isContextualToolbarExpanded && contextualToolTab ? (
	                      <div className="wpss-section-preview__tool-panel">
	                        {contextualToolTab === 'format' ? (
                          <div className="wpss-section-preview__tool-stack">
                            <div className="wpss-section-preview__format-tools">
                              <button
                                type="button"
                                className="button button-small"
                                disabled={!selectionState.element}
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => applyContextualTextFormat('bold')}
                              >
                                B
                              </button>
                              <button
                                type="button"
                                className="button button-small"
                                disabled={!selectionState.element}
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => applyContextualTextFormat('underline')}
                              >
                                U
                              </button>
                              <button
                                type="button"
                                className="button button-small"
                                disabled={!selectionState.element}
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => applyContextualTextFormat('light')}
                              >
                                Light
                              </button>
                              <button
                                type="button"
                                className="button button-small"
                                disabled={!selectionState.element}
                                onMouseDown={(event) => event.preventDefault()}
                                onClick={() => applyContextualTextFormat('clear')}
                              >
                                Normal
                              </button>
	                            </div>
	                          </div>
	                        ) : null}
	                        {contextualToolTab === 'reading' ? (
	                          <div className="wpss-section-preview__tool-stack">
	                            <div className="wpss-section-preview__options-grid">
	                              <button
	                                type="button"
	                                className="button button-small"
	                                onClick={() => setShowPreviewAttachments((current) => !current)}
	                              >
	                                {showPreviewAttachments ? 'Ocultar adjuntos' : 'Mostrar adjuntos'}
	                              </button>
	                              <button
	                                type="button"
	                                className="button button-small"
	                                onClick={toggleEditorFullscreen}
	                              >
	                                {isEditorFullscreen ? 'Salir de pantalla completa' : 'Pantalla completa'}
	                              </button>
	                            </div>
	                          </div>
	                        ) : null}
	                        {contextualToolTab === 'audio' ? (
                          <div className="wpss-section-preview__tool-stack">
	                            {visibleAudioNavigationItems.length ? (
	                              <div className="wpss-section-preview__navigator">
                                <button type="button" className="button button-small" onClick={() => navigateAudioByStep(-1)}>
                                  ← Anterior
                                </button>
	                                <span>
	                                  {`${Math.max(currentAudioNavigationIndex, 0) + 1}/${visibleAudioNavigationItems.length} ${
	                                    (selectedAudioAttachment || visibleAudioNavigationItems[currentAudioNavigationIndex >= 0 ? currentAudioNavigationIndex : 0])?.title
	                                    || (selectedAudioAttachment || visibleAudioNavigationItems[currentAudioNavigationIndex >= 0 ? currentAudioNavigationIndex : 0])?.file_name
	                                    || 'Audio'
	                                  }`}
                                </span>
                                <button type="button" className="button button-small" onClick={() => navigateAudioByStep(1)}>
                                  Siguiente →
                                </button>
                              </div>
                            ) : null}
                            <InlineMediaQuickActions
                              target={{ ...(contextualTarget.target || {}), compactRecorder: true }}
                              onUpload={handleQuickUploadAttachment}
                              allowedModes={['importAudio', 'recordAudio']}
                            />
                            {contextualTarget.attachments.filter((item) => item?.type !== 'photo').length ? (
                              <EditorPreviewMediaAttachments
                                attachments={contextualTarget.attachments.filter((item) => item?.type !== 'photo')}
                                title="Audios"
                                compact
                                activeAttachmentId={selectedAttachmentId}
                                onSelectAttachment={handleAttachmentSelect}
                                showActions
                                onRename={handlePreviewRenameAttachment}
                                onUnlink={handlePreviewUnlinkAttachment}
                                onDelete={handlePreviewDeleteAttachment}
                                pendingActionById={pendingAttachmentActions}
                              />
                            ) : (
                              <p className="wpss-empty">Aún no hay audios en este nivel.</p>
                            )}
                            {selectedAudioAttachment ? (
                              <div className="wpss-preview-media__manager">
                                <div className="wpss-preview-media__manager-head">
                                  <div className="wpss-preview-media__manager-title">
                                    <span>Audio seleccionado</span>
                                    <strong>{selectedAudioAttachment.title || selectedAudioAttachment.file_name || 'Audio'}</strong>
                                  </div>
                                  {pendingAttachmentActions?.[selectedAudioAttachment.id] ? (
                                    <span className="wpss-preview-media__status">{pendingAttachmentActions[selectedAudioAttachment.id]}</span>
                                  ) : null}
                                </div>
                                <audio
                                  className="wpss-reading-media__audio"
                                  controls
                                  preload="none"
                                  src={selectedAudioAttachment.stream_url || ''}
                                />
                                <div className="wpss-preview-media__manager-actions">
                                  {selectedAudioAttachment?.can_manage ? (
                                    <>
                                      <button type="button" className="button button-small" onClick={() => handlePreviewRenameAttachment(selectedAudioAttachment)}>
                                        Renombrar
                                      </button>
                                      <button type="button" className="button button-small" onClick={() => handlePreviewUnlinkAttachment(selectedAudioAttachment)}>
                                        Quitar
                                      </button>
                                    </>
                                  ) : null}
                                  {selectedAudioAttachment?.can_delete_file ? (
                                    <button type="button" className="button button-secondary button-small" onClick={() => handlePreviewDeleteAttachment(selectedAudioAttachment)}>
                                      Eliminar de Drive
                                    </button>
                                  ) : null}
                                </div>
                              </div>
                            ) : contextualTarget.attachments.filter((item) => item?.type !== 'photo').length ? (
                              <p className="wpss-empty">Selecciona un audio para administrarlo desde aquí.</p>
                            ) : null}
                          </div>
                        ) : null}
                        {contextualToolTab === 'photos' ? (
                          <div className="wpss-section-preview__tool-stack">
                            <InlineMediaQuickActions
                              target={contextualTarget.target}
                              onUpload={handleQuickUploadAttachment}
                              allowedModes={['importPhoto', 'capturePhoto']}
                            />
                            {contextualTarget.attachments.filter((item) => item?.type === 'photo').length ? (
                              <EditorPreviewMediaAttachments
                                attachments={contextualTarget.attachments.filter((item) => item?.type === 'photo')}
                                title="Fotos"
                                compact
                                activeAttachmentId={selectedAttachmentId}
                                onSelectAttachment={handleAttachmentSelect}
                                pendingActionById={pendingAttachmentActions}
                              />
                            ) : (
                              <p className="wpss-empty">Aún no hay fotos en este nivel.</p>
                            )}
                            {selectedPhotoAttachment ? (
                              <div className="wpss-preview-media__manager">
                                <div className="wpss-preview-media__manager-head">
                                  <div className="wpss-preview-media__manager-title">
                                    <span>Foto seleccionada</span>
                                    <strong>{selectedPhotoAttachment.title || selectedPhotoAttachment.file_name || 'Foto'}</strong>
                                  </div>
                                  {pendingAttachmentActions?.[selectedPhotoAttachment.id] ? (
                                    <span className="wpss-preview-media__status">{pendingAttachmentActions[selectedPhotoAttachment.id]}</span>
                                  ) : null}
                                </div>
                                <a href={selectedPhotoAttachment.stream_url || '#'} target="_blank" rel="noreferrer">
                                  <img
                                    className="wpss-reading-media__image"
                                    src={selectedPhotoAttachment.stream_url || ''}
                                    alt={selectedPhotoAttachment.title || selectedPhotoAttachment.file_name || 'Foto'}
                                    loading="lazy"
                                  />
                                </a>
                                <div className="wpss-preview-media__manager-actions">
                                  {selectedPhotoAttachment?.can_manage ? (
                                    <>
                                      <button type="button" className="button button-small" onClick={() => handlePreviewRenameAttachment(selectedPhotoAttachment)}>
                                        Renombrar
                                      </button>
                                      <button type="button" className="button button-small" onClick={() => handlePreviewUnlinkAttachment(selectedPhotoAttachment)}>
                                        Quitar
                                      </button>
                                    </>
                                  ) : null}
                                  {selectedPhotoAttachment?.can_delete_file ? (
                                    <button type="button" className="button button-secondary button-small" onClick={() => handlePreviewDeleteAttachment(selectedPhotoAttachment)}>
                                      Eliminar de Drive
                                    </button>
                                  ) : null}
                                </div>
                              </div>
                            ) : contextualTarget.attachments.filter((item) => item?.type === 'photo').length ? (
                              <p className="wpss-empty">Selecciona una foto para administrarla desde aquí.</p>
                            ) : null}
                          </div>
                        ) : null}
	                        {contextualToolTab === 'annotations' ? (
	                          <div className="wpss-section-preview__tool-stack">
	                            {contextualTarget.type !== 'song' && annotationNavigationTargets.length ? (
	                              <div className="wpss-section-preview__navigator">
                                <button type="button" className="button button-small" onClick={() => navigateAnnotationByStep(-1)}>
                                  ← Anterior
                                </button>
                                <span>
                                  {currentAnnotationNavigationIndex >= 0
                                    ? `${currentAnnotationNavigationIndex + 1}/${annotationNavigationTargets.length} ${annotationNavigationTargets[currentAnnotationNavigationIndex].label}`
                                    : `${annotationNavigationTargets.length} anotaciones`}
                                </span>
                                <button type="button" className="button button-small" onClick={() => navigateAnnotationByStep(1)}>
                                  Siguiente →
	                                </button>
	                              </div>
	                            ) : null}
	                            {contextualTarget.type === 'song' ? (
	                              <p className="wpss-empty">Las anotaciones de canción completa aún no están habilitadas. Usa Audio o Fotos para incrustables globales.</p>
	                            ) : null}
	                            {contextualTarget.type === 'section' ? (
	                              <CommentEditor
                                label="Anotaciones de la sección"
                                comments={contextualTarget.comments}
                                defaultTitle={contextualTarget.label}
                                onChange={(next) => handleSectionCommentsChange(activeSectionId, next)}
                              />
                            ) : null}
                            {contextualTarget.type === 'verse' && contextualTarget.verseIndex !== null ? (
                              <CommentEditor
                                label="Anotaciones del verso"
                                comments={contextualTarget.comments}
                                defaultTitle={contextualTarget.label}
                                onChange={(next) => handleVerseCommentsChange(contextualTarget.verseIndex, next)}
                              />
                            ) : null}
                            {contextualTarget.type === 'segment' && contextualTarget.verseIndex !== null && contextualTarget.segmentIndex !== null ? (
                              <CommentEditor
                                label="Anotaciones del segmento"
                                comments={contextualTarget.comments}
                                defaultTitle={contextualTarget.label}
                                onChange={(next) =>
                                  handleSegmentCommentsChange(
                                    contextualTarget.verseIndex,
                                    contextualTarget.segmentIndex,
                                    next,
                                  )
                                }
                              />
                            ) : null}
                          </div>
                        ) : null}
	                        {contextualToolTab === 'midi' ? (
	                          <div className="wpss-section-preview__tool-stack">
	                            {contextualTarget.type === 'song' ? (
	                              <p className="wpss-empty">El MIDI global de canción aún no está habilitado en esta vista. Usa Audio o Fotos para incrustables de canción.</p>
	                            ) : null}
	                            {contextualTarget.type === 'section' ? (
	                              <MidiClipList
                                clips={contextualTarget.midiClips}
                                onChange={(clips) => handleSectionMidiChange(activeSectionId, clips)}
                                emptyLabel="Añadir MIDI a la sección"
                                defaultTempo={editingSong.bpm}
                                compactRows={preferCompactMidiRows}
                                allowRowToggle={preferCompactMidiRows}
                                rangePresets={midiRangePresets}
                                defaultRange={midiRangeDefault}
                                lockRange={lockMidiRange}
                              />
                            ) : null}
                            {contextualTarget.type === 'verse' && contextualTarget.verseIndex !== null ? (
                              <MidiClipList
                                clips={contextualTarget.midiClips}
                                onChange={(clips) => handleVerseMidiChange(contextualTarget.verseIndex, clips)}
                                emptyLabel="Añadir MIDI al verso"
                                defaultTempo={editingSong.bpm}
                                compactRows={preferCompactMidiRows}
                                allowRowToggle={preferCompactMidiRows}
                                rangePresets={midiRangePresets}
                                defaultRange={midiRangeDefault}
                                lockRange={lockMidiRange}
                              />
                            ) : null}
                            {contextualTarget.type === 'segment' && contextualTarget.verseIndex !== null && contextualTarget.segmentIndex !== null ? (
                              <MidiClipList
                                clips={contextualTarget.midiClips}
                                onChange={(clips) =>
                                  handleSegmentMidiChange(
                                    contextualTarget.verseIndex,
                                    contextualTarget.segmentIndex,
                                    clips,
                                  )
                                }
                                emptyLabel="Añadir MIDI al segmento"
                                defaultTempo={editingSong.bpm}
                                compactRows={preferCompactMidiRows}
                                allowRowToggle={preferCompactMidiRows}
                                rangePresets={midiRangePresets}
                                defaultRange={midiRangeDefault}
                                lockRange={lockMidiRange}
                              />
                            ) : null}
                          </div>
                        ) : null}
	                        {contextualToolTab === 'options' ? (
	                          <div className="wpss-section-preview__tool-stack">
	                            <div className="wpss-section-preview__options-grid">
	                              {contextualTarget.type === 'song' ? (
	                                <p className="wpss-empty">Usa las pestañas Audio y Fotos para administrar incrustables de la canción completa.</p>
	                              ) : null}
	                              {contextualTarget.type === 'section' ? (
                                <>
                                  <button type="button" className="button button-small" onClick={() => handleAddVerseToSection(activeSectionId)}>
                                    Añadir verso
                                  </button>
                                  <button type="button" className="button button-small" onClick={() => handleSectionRenamePrompt(activeSectionId)}>
                                    Renombrar sección
                                  </button>
                                  <button type="button" className="button button-small" onClick={() => {
                                    const sectionIndex = sectionsList.findIndex((section) => section.id === activeSectionId)
                                    if (sectionIndex >= 0) handleDuplicateSection(sectionIndex)
                                  }}>
                                    Duplicar sección
                                  </button>
                                  <button
                                    type="button"
                                    className="button button-link-delete"
                                    onClick={() => {
                                      const sectionIndex = sectionsList.findIndex((section) => section.id === activeSectionId)
                                      if (sectionIndex >= 0) handleRemoveSection(sectionIndex)
                                    }}
                                    disabled={sectionsList.length <= 1}
                                  >
                                    Eliminar sección
                                  </button>
                                </>
                              ) : null}
                              {contextualTarget.type === 'verse' && contextualTarget.verseIndex !== null ? (
                                <>
                                  <button type="button" className="button button-small" onClick={() => handleRenameVerseAtIndex(contextualTarget.verseIndex)}>
                                    Renombrar verso
                                  </button>
                                  <button type="button" className="button button-small" onClick={() => handleDuplicateVerseAtIndex(contextualTarget.verseIndex)}>
                                    Duplicar verso
                                  </button>
                                  <button
                                    type="button"
                                    className="button button-small"
                                    onClick={() => {
                                      const verse = Array.isArray(editingSong.versos) ? editingSong.versos[contextualTarget.verseIndex] : null
                                      if (!verse) return
                                      const nextVerses = [...editingSong.versos]
                                      nextVerses[contextualTarget.verseIndex] = { ...verse, instrumental: !verse.instrumental }
                                      handleVerseChange(nextVerses)
                                    }}
                                  >
                                    Alternar instrumental
                                  </button>
                                  <button type="button" className="button button-link-delete" onClick={() => handleRemoveVerseAtIndex(contextualTarget.verseIndex)}>
                                    Eliminar verso
                                  </button>
                                </>
                              ) : null}
                              {contextualTarget.type === 'segment' && contextualTarget.verseIndex !== null && contextualTarget.segmentIndex !== null ? (
                                <>
                                  <button type="button" className="button button-small" onClick={() => handleDuplicateSegmentAtIndex(contextualTarget.verseIndex, contextualTarget.segmentIndex)}>
                                    Duplicar segmento
                                  </button>
                                  <button
                                    type="button"
                                    className="button button-small"
                                    onClick={() => {
                                      const editor = selectionState.element
                                      if (editor) {
                                        splitSegment(contextualTarget.verseIndex, contextualTarget.segmentIndex, editor)
                                      }
                                    }}
                                  >
                                    Dividir segmento
                                  </button>
                                  <button
                                    type="button"
                                    className="button button-small"
                                    onClick={() => {
                                      const editor = selectionState.element
                                      if (editor) {
                                        splitVerseFromCursor(contextualTarget.verseIndex, contextualTarget.segmentIndex, editor)
                                      }
                                    }}
                                  >
                                    Cortar verso
                                  </button>
                                  <button
                                    type="button"
                                    className="button button-link-delete"
                                    onClick={() => handleRemoveSegmentAtIndex(contextualTarget.verseIndex, contextualTarget.segmentIndex)}
                                  >
                                    Eliminar segmento
                                  </button>
                                </>
                              ) : null}
                            </div>
                          </div>
                        ) : null}
	                      </div>
	                    ) : null}
	                  </div>
	                  {sectionNavItems.length ? (
	                    <nav className="wpss-reading__section-nav wpss-editor__section-nav" aria-label="Navegación de secciones en escritura">
	                      {sectionNavItems.map((item) => (
	                        <button
	                          key={`editor-jump-section-${item.id}`}
	                          type="button"
	                          className={`button button-secondary wpss-reading__section-nav-pill ${
	                            activeSectionNavIndex === item.index ? 'is-active' : ''
	                          }`}
	                          onClick={() => {
	                            persistSelectedSection(item.id)
	                            setExpandedSectionId(item.id)
	                            setSelectedVerseIndexes(new Set())
	                            scrollPreviewToSection(item.id)
	                          }}
	                        >
	                          <span>{item.label}</span>
	                          <ContentIndicators
	                            items={item.indicators}
	                            onSelect={(indicator) => openContextualTarget({ type: 'section', sectionId: item.id }, indicator.tabId)}
	                          />
	                          {item.count > 0 ? (
	                            <span className="wpss-reading__section-nav-repeat">{item.count}</span>
	                          ) : null}
	                        </button>
	                      ))}
	                    </nav>
	                  ) : null}
	                </div>
                <div ref={previewScrollRef} className="wpss-section-preview__scroll-shell">
                  <div
                    className="wpss-section-preview__all wpss-section-preview__all--interactive"
                    style={{ '--wpss-preview-scale': previewScale / 100 }}
                  >
                    {sectionsList.length ? (
                      sectionsList.map((section, index) => {
                      const verses = versesBySection.get(section.id) || []
                      const sectionIndicators = buildContentIndicators({
                        attachments: getSectionLevelAttachments(editingSong, section.id),
                        comments: section?.comentarios,
                        midiClips: section?.midi_clips,
                      })
                      const isActiveSection = section.id === activeSectionId
                      const isExpandedSection = expandedSectionId === section.id
                      return (
	                        <div
	                          key={`preview-section-${section.id}`}
	                          className={`wpss-section-preview__group ${isActiveSection ? 'is-active' : ''}`}
	                          data-wpss-preview-section-id={section.id}
	                          data-wpss-preview-section-index={index}
	                          ref={(node) => {
	                            if (node) {
	                              previewSectionRefs.current.set(section.id, node)
                            } else {
                              previewSectionRefs.current.delete(section.id)
                            }
                          }}
                          onDragOver={handleSectionSurfaceDragOver}
                          onDrop={(event) => handleSectionSurfaceDrop(event, section.id, index)}
                        >
                          <div className="wpss-section-preview__group-header">
                            <button
                              type="button"
                              className="wpss-section-preview__group-title"
                              onClick={() => {
                                selectSectionOnly(section.id)
                                setSelectedVerseIndexes(new Set())
                                setExpandedSectionId((prev) => (prev === section.id ? null : section.id))
                              }}
                              aria-label={`Sección ${index + 1}`}
                            >
                              <span>{isExpandedSection ? '▾' : '▸'}</span>
                              <strong>{section.nombre || getDefaultSectionName(index)}</strong>
                              <ContentIndicators
                                items={sectionIndicators}
                                onSelect={(item) => openContextualTarget({ type: 'section', sectionId: section.id }, item.tabId)}
                              />
                              <span>{verses.length} versos</span>
                            </button>
                            <div className="wpss-section-preview__group-actions">
	                              <span
	                                className={`wpss-verse-card-mini__drag ${
	                                  sectionDragIndex === index ? 'is-dragging' : ''
	                                }`}
	                                draggable
	                                aria-label="Mover sección"
	                                title="Arrastra para ordenar sección"
	                                onPointerDown={(event) => {
	                                  event.stopPropagation()
	                                  beginPreviewTouchDrag(event, { type: 'section', index })
	                                }}
	                                onContextMenu={(event) => event.preventDefault()}
	                                onDragStart={(event) => {
	                                  event.dataTransfer.setData('text/plain', String(index))
	                                  event.dataTransfer.setData('application/x-wpss-section-index', String(index))
                                  event.dataTransfer.effectAllowed = 'move'
                                  setSectionDragIndex(index)
                                }}
                                onDragOver={(event) => {
                                  event.preventDefault()
                                  event.stopPropagation()
                                  setSectionDragOverIndex(index)
                                }}
                                onDrop={(event) => {
                                  event.preventDefault()
                                  event.stopPropagation()
                                  const payload = parseInt(
                                    event.dataTransfer.getData('application/x-wpss-section-index')
                                      || event.dataTransfer.getData('text/plain'),
                                    10,
                                  )
                                  const fromIndex = Number.isNaN(payload) ? sectionDragIndex : payload
                                  if (fromIndex !== null && fromIndex !== undefined) {
                                    moveSection(fromIndex, index)
                                  }
                                  setSectionDragIndex(null)
                                  setSectionDragOverIndex(null)
                                }}
                                onDragEnd={() => {
                                  setSectionDragIndex(null)
                                  setSectionDragOverIndex(null)
                                }}
                              >
                                ☰
                              </span>
                            </div>
                          </div>
                          {verses.length ? (
                            <div className="wpss-preview-verse-list">
                              {verses.map(({ verse, index: verseId }) => {
                                const preview = getVerseStackPreview(verse)
                                const isActiveVerse = selectedVerseIndex === verseId && isActiveSection && isExpandedSection
                                const verseTitle = verse?.nombre
                                  ? String(verse.nombre)
                                  : verse?.instrumental
                                    ? `Instrumental ${verseId + 1}`
                                    : `Verso ${verseId + 1}`
                                const verseAttachments = getVerseLevelAttachments(editingSong, verseId)
                                const segmentAttachments = getSegmentLevelAttachments(editingSong, verseId)
                                const verseIndicators = buildContentIndicators({
                                  attachments: verseAttachments,
                                  comments: verse?.comentarios,
                                  midiClips: verse?.midi_clips,
                                })
                                if (isActiveVerse) {
                                  return (
	                                    <div
	                                      key={`preview-verse-editor-${section.id}-${verseId}`}
	                                      className={`wpss-preview-verse-card is-active ${
	                                        verseDragOverIndex === verseId ? 'is-dragover' : ''
	                                      }`}
	                                      data-wpss-preview-verse-index={verseId}
	                                      data-wpss-preview-section-id={section.id}
	                                      onDragOver={(event) => handleVerseCardDragOver(event, verseId)}
	                                      onDrop={(event) => handleVerseCardDrop(event, verseId)}
	                                    >
                                      <div className="wpss-preview-verse-card__layout">
                                        <div className="wpss-preview-verse-card__main">
                                          <div className="wpss-preview-verse-card__toolbar">
	                                            <span
	                                              className={`wpss-verse-card-mini__drag ${
	                                                verseDragIndex === verseId ? 'is-dragging' : ''
	                                              }`}
	                                              draggable
	                                              aria-label="Mover verso"
	                                              title="Arrastra para ordenar"
	                                              onClick={(event) => event.stopPropagation()}
	                                              onPointerDown={(event) => {
	                                                event.stopPropagation()
	                                                beginPreviewTouchDrag(event, { type: 'verse', index: verseId })
	                                              }}
	                                              onContextMenu={(event) => event.preventDefault()}
	                                              onDragStart={(event) => beginVerseDrag(event, verseId)}
	                                              onDragEnd={handleVerseCardDragEnd}
	                                            >
                                              ☰
                                            </span>
                                            <strong className="wpss-preview-verse-card__title">
                                              <span>{verseTitle}</span>
                                              <ContentIndicators
                                                items={verseIndicators}
                                                onSelect={(item) =>
                                                  openContextualTarget(
                                                    {
                                                      type: 'verse',
                                                      sectionId: verse.section_id || section.id,
                                                      verseIndex: verseId,
                                                    },
                                                    item.tabId,
                                                  )
                                                }
                                              />
                                            </strong>
                                          </div>
                                          <div className="wpss-verse-inline-editor">
                                            <VersesPanel
                                              verses={editingSong.versos}
                                              sections={editingSong.secciones}
                                              selectedSectionId={activeSectionId}
                                              songBpm={editingSong.bpm}
                                              onSelectSection={selectSectionOnly}
                                              onSectionsChange={handleSectionChange}
                                              onAddSection={handleAddSection}
                                              onDuplicateSection={handleDuplicateSection}
                                              onChange={handleVerseChange}
                                              onSplitSegment={splitSegment}
                                              onSplitVerse={splitVerseFromCursor}
                                              onSplitSection={splitSectionFromCursor}
                                              onSelectionChange={updateSegmentSelection}
                                              focusRequest={verseFocusRequest}
                                              onFocusRequestHandled={handleVerseFocusHandled}
                                              compactMidiRows={preferCompactMidiRows}
                                              allowMidiRowToggle={preferCompactMidiRows}
                                              midiRangePresets={midiRangePresets}
                                              midiRangeDefault={midiRangeDefault}
                                              lockMidiRange={lockMidiRange}
                                              showHeader={false}
                                              showPreview={false}
                                              visibleVerseIndexes={new Set([verseId])}
                                              songAttachments={editingSong.adjuntos}
                                              onContextIndicatorSelect={openContextualTarget}
                                              onQuickUploadAttachment={handleQuickUploadAttachment}
                                              onBeginSegmentDrag={beginSegmentDrag}
                                              onEndSegmentDrag={clearSegmentDrag}
                                              onMoveSegmentToVerse={moveSegmentToTargetVerse}
                                              onMoveSegmentToNewVerse={moveSegmentToNewVerse}
                                              useContextualToolbar
                                            />
                                          </div>
                                        </div>
                                        {showPreviewAttachments && (verseAttachments.length || segmentAttachments.length) ? (
                                          <div className="wpss-preview-verse-card__attachments">
                                            {verseAttachments.length ? (
                                              <EditorPreviewMediaAttachments
                                                attachments={verseAttachments}
                                                title="Adjuntos del verso"
                                                compact
                                                activeAttachmentId={selectedAttachmentId}
                                                onSelectAttachment={handleAttachmentSelect}
                                                pendingActionById={pendingAttachmentActions}
                                              />
                                            ) : null}
                                            {segmentAttachments.length ? (
                                              <EditorPreviewMediaAttachments
                                                attachments={segmentAttachments}
                                                title="Adjuntos por fragmento"
                                                compact
                                                groupedBySegment
                                                activeAttachmentId={selectedAttachmentId}
                                                onSelectAttachment={handleAttachmentSelect}
                                                pendingActionById={pendingAttachmentActions}
                                              />
                                            ) : null}
                                          </div>
                                        ) : null}
                                      </div>
                                    </div>
                                  )
                                }
                                return (
	                                  <div
	                                    key={`preview-verse-${section.id}-${verseId}`}
	                                    className={`wpss-preview-verse-card ${
	                                      isActiveVerse ? 'is-active' : ''
	                                    } ${verseDragOverIndex === verseId ? 'is-dragover' : ''}`}
	                                    data-wpss-preview-verse-index={verseId}
	                                    data-wpss-preview-section-id={section.id}
	                                    role="button"
	                                    tabIndex={0}
                                    onClick={() => handleSelectVerse(section.id, verseId)}
                                    onKeyDown={(event) => {
                                      if (event.key === 'Enter' || event.key === ' ') {
                                        event.preventDefault()
                                        handleSelectVerse(section.id, verseId)
                                      }
                                    }}
                                    onDragOver={(event) => handleVerseCardDragOver(event, verseId)}
                                    onDrop={(event) => handleVerseCardDrop(event, verseId)}
                                  >
                                    <div className="wpss-preview-verse-card__layout">
                                      <div className="wpss-preview-verse-card__main">
                                        <div className="wpss-preview-verse-card__toolbar">
	                                          <span
	                                            className={`wpss-verse-card-mini__drag ${
	                                              verseDragIndex === verseId ? 'is-dragging' : ''
	                                            }`}
	                                            draggable
	                                            aria-label="Mover verso"
	                                            title="Arrastra para ordenar"
	                                            onClick={(event) => event.stopPropagation()}
	                                            onPointerDown={(event) => {
	                                              event.stopPropagation()
	                                              beginPreviewTouchDrag(event, { type: 'verse', index: verseId })
	                                            }}
	                                            onContextMenu={(event) => event.preventDefault()}
	                                            onDragStart={(event) => beginVerseDrag(event, verseId)}
	                                            onDragEnd={handleVerseCardDragEnd}
	                                          >
                                            ☰
                                          </span>
                                          <strong className="wpss-preview-verse-card__title">
                                            <span>{verseTitle}</span>
                                            <ContentIndicators
                                              items={verseIndicators}
                                              onSelect={(item) =>
                                                openContextualTarget(
                                                  {
                                                    type: 'verse',
                                                    sectionId: verse.section_id || section.id,
                                                    verseIndex: verseId,
                                                  },
                                                  item.tabId,
                                                )
                                              }
                                            />
                                          </strong>
                                        </div>
                                        <pre className="wpss-verse-card-mini__stack">{`${preview.chords}\n${preview.lyrics}`}</pre>
                                      </div>
                                      {showPreviewAttachments && (verseAttachments.length || segmentAttachments.length) ? (
                                        <div className="wpss-preview-verse-card__attachments">
                                          {verseAttachments.length ? (
                                            <EditorPreviewMediaAttachments
                                              attachments={verseAttachments}
                                              title="Adjuntos del verso"
                                              compact
                                              activeAttachmentId={selectedAttachmentId}
                                              onSelectAttachment={handleAttachmentSelect}
                                              pendingActionById={pendingAttachmentActions}
                                            />
                                          ) : null}
                                          {segmentAttachments.length ? (
                                            <EditorPreviewMediaAttachments
                                              attachments={segmentAttachments}
                                              title="Adjuntos por fragmento"
                                              compact
                                              groupedBySegment
                                              activeAttachmentId={selectedAttachmentId}
                                              onSelectAttachment={handleAttachmentSelect}
                                              pendingActionById={pendingAttachmentActions}
                                            />
                                          ) : null}
                                        </div>
                                      ) : null}
                                    </div>
                                  </div>
                                )
                              })}
                              {isActiveSection ? (
                                <button
                                  type="button"
                                  className="wpss-preview-verse-card wpss-preview-verse-card--ghost"
                                  onClick={() => handleAddVerseToSection(section.id)}
                                >
                                  <strong>Nuevo verso</strong>
                                  <span className="wpss-ghost-invite__hint">Agregar a esta sección</span>
                                </button>
                              ) : null}
                            </div>
                          ) : isActiveSection ? (
                            <button
                              type="button"
                              className="wpss-preview-verse-card wpss-preview-verse-card--ghost"
                              onClick={() => handleAddVerseToSection(section.id)}
                            >
                              <strong>Nuevo verso</strong>
                              <span className="wpss-ghost-invite__hint">Agregar a esta sección</span>
                            </button>
                          ) : (
                            <p className="wpss-empty">Sin versos</p>
                          )}
                        </div>
                      )
                      })
                    ) : (
                      <div className="wpss-section-preview__empty">
                        <p className="wpss-empty">Sin secciones.</p>
                        <button
                          type="button"
                          className="button button-secondary"
                          onClick={handleAddSection}
                        >
                          Añadir sección
                        </button>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            </aside>
          </div>
        </section>

        <details className="wpss-section wpss-section--collapsible">
          <summary>
            <span>Estructura completa</span>
          </summary>
          <StructurePanel
            structure={editingSong.estructura}
            sections={editingSong.secciones}
            onChange={handleStructureChange}
          />
        </details>

        <SongMediaManager
          song={editingSong}
          onChangeSong={(nextSong) => updateSong(nextSong)}
          onRequestAutosave={scheduleAutosave}
          showPermissions={false}
        />

        <datalist id="wpss-song-tags">
          {availableTags.map((tag) => (
            <option key={tag.id || tag.slug || tag.name} value={getTagLabel(tag)} />
          ))}
        </datalist>
        <datalist id="wpss-tonicas">
          {(wpData?.tonicas || []).map((tonica) => (
            <option key={tonica} value={tonica} />
          ))}
        </datalist>
        <datalist id="wpss-campos-armonicos">
          {(wpData?.camposArmonicosNombres || []).map((campo) => (
            <option key={campo} value={campo} />
          ))}
        </datalist>
      </form>
    </section>
  )
}
