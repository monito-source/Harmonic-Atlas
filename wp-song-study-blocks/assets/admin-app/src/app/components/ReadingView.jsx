import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { useAppState } from '../StateProvider.jsx'
import {
  endsWithJoiner,
  buildChordLookup,
  getChordFromLookup,
  getChordDisplayValue,
  isHoldChordToken,
  getDefaultSectionName,
  getValidSegmentIndex,
  transposePitchToken,
  transposeChordSymbol,
  stripHtml,
} from '../utils.js'
import {
  REHEARSAL_STATUS_OPTIONS,
  REHEARSAL_STATUS_LABELS,
  TRANSCRIPTION_STATUS_OPTIONS,
  TRANSCRIPTION_STATUS_LABELS,
  getStatusLabel,
} from '../songStatus.js'
import { buildMidiClipGroups, playMidiClipGroupsSequence, togglePlayback } from './MidiSketch.jsx'
import MidiClipList from './MidiClipList.jsx'
import InlineMediaQuickActions from './InlineMediaQuickActions.jsx'
import { mapSongToEditingSong, upsertSongInList } from '../songHydration.js'
import ChordDiagram from './ChordDiagram.jsx'
import ReadingMediaAttachments, {
  getSectionLevelAttachments,
  getSegmentLevelAttachments,
  getSongLevelAttachments,
  getVerseLevelAttachments,
  isRehearsalAttachment,
} from './ReadingMediaAttachments.jsx'
import {
  CHORD_INSTRUMENTS,
  DEFAULT_CHORD_INSTRUMENT_ID,
  getChordInstrumentDefinition,
  getChordInstrumentLabel,
  sanitizeChordInstrumentId,
} from '../chordInstruments.js'
import { buildAutoWindShapes, getWindChordNoteCandidates } from '../saxFingerings.js'

const formatCollectionAssignment = (collection) => {
  if (!collection || typeof collection !== 'object') return ''
  const assignedBy = collection.assigned_by_user_name || ''
  if (collection.assigned_by_author) return assignedBy ? `Transcriptor: ${assignedBy}` : 'Transcriptor'
  return assignedBy ? `Asignó: ${assignedBy}` : ''
}

const TRANSPOSE_TARGETS = [
  { id: 'concert', label: 'Concierto', semitones: 0 },
  { id: 'sax-alto-eb', label: 'Sax alto (Eb)', semitones: 9 },
  { id: 'sax-tenor-bb', label: 'Sax tenor (Bb)', semitones: 2 },
]
const INSTRUMENT_TRANSPOSE_TARGETS = {
  'sax-alto': 'sax-alto-eb',
  'sax-tenor': 'sax-tenor-bb',
}
const MOBILE_READING_QUERY = '(max-width: 860px)'
const PRINT_BODY_CLASS = 'wpss-print-mode'
const GLOBAL_READING_WIDTH_CLASS = 'wpss-reading-global-width'
const MOBILE_DEFAULT_READING_ZOOM = 60
const READING_ZOOM_MIN = 10
const READING_ZOOM_MAX = 180
const READING_ZOOM_STEP = 5
const LOCAL_READING_TOOLBAR_STORAGE_PREFIX = 'wpss-reading-toolbar:'
const SONG_REFRESH_INTERVAL_MS = 30000

const DEFAULT_LOCAL_READING_TOOLBAR_PREFERENCES = {
  repeatsEnabled: true,
  linkedPlayback: true,
  showMidi: true,
  showAttachments: true,
  minimizeAttachments: false,
  showSectionTitles: true,
  activeReadingToolTab: 'lectura',
}

const clampReadingZoom = (value) => {
  if (!Number.isFinite(value)) {
    return 100
  }
  return Math.min(READING_ZOOM_MAX, Math.max(READING_ZOOM_MIN, value))
}

const snapReadingZoom = (value) => {
  const bounded = clampReadingZoom(value)
  const snapped = Math.round(bounded / READING_ZOOM_STEP) * READING_ZOOM_STEP
  return clampReadingZoom(snapped)
}

const isCompactReadingViewport = () => {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return false
  }
  return window.matchMedia(MOBILE_READING_QUERY).matches
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

const normalizeProjectId = (value) => {
  const parsed = Number(value)
  return Number.isInteger(parsed) && parsed > 0 ? parsed : 0
}

const getLocalReadingToolbarStorageKey = (userId = 0) =>
  `${LOCAL_READING_TOOLBAR_STORAGE_PREFIX}${Number(userId) || 0}`

const loadLocalReadingToolbarPreferences = (userId = 0) => {
  if (typeof window === 'undefined' || !window.localStorage) {
    return { ...DEFAULT_LOCAL_READING_TOOLBAR_PREFERENCES }
  }
  try {
    const raw = window.localStorage.getItem(getLocalReadingToolbarStorageKey(userId))
    if (!raw) {
      return { ...DEFAULT_LOCAL_READING_TOOLBAR_PREFERENCES }
    }
    const parsed = JSON.parse(raw)
    return {
      repeatsEnabled:
        typeof parsed?.repeatsEnabled === 'boolean'
          ? parsed.repeatsEnabled
          : DEFAULT_LOCAL_READING_TOOLBAR_PREFERENCES.repeatsEnabled,
      linkedPlayback:
        typeof parsed?.linkedPlayback === 'boolean'
          ? parsed.linkedPlayback
          : DEFAULT_LOCAL_READING_TOOLBAR_PREFERENCES.linkedPlayback,
      showMidi:
        typeof parsed?.showMidi === 'boolean'
          ? parsed.showMidi
          : DEFAULT_LOCAL_READING_TOOLBAR_PREFERENCES.showMidi,
      showAttachments:
        typeof parsed?.showAttachments === 'boolean'
          ? parsed.showAttachments
          : DEFAULT_LOCAL_READING_TOOLBAR_PREFERENCES.showAttachments,
      minimizeAttachments:
        typeof parsed?.minimizeAttachments === 'boolean'
          ? parsed.minimizeAttachments
          : DEFAULT_LOCAL_READING_TOOLBAR_PREFERENCES.minimizeAttachments,
      showSectionTitles:
        typeof parsed?.showSectionTitles === 'boolean'
          ? parsed.showSectionTitles
          : DEFAULT_LOCAL_READING_TOOLBAR_PREFERENCES.showSectionTitles,
      activeReadingToolTab:
        typeof parsed?.activeReadingToolTab === 'string' && parsed.activeReadingToolTab.trim()
          ? parsed.activeReadingToolTab.trim()
          : DEFAULT_LOCAL_READING_TOOLBAR_PREFERENCES.activeReadingToolTab,
    }
  } catch {
    return { ...DEFAULT_LOCAL_READING_TOOLBAR_PREFERENCES }
  }
}

const persistLocalReadingToolbarPreferences = (userId = 0, preferences = {}) => {
  if (typeof window === 'undefined' || !window.localStorage) {
    return
  }
  try {
    window.localStorage.setItem(
      getLocalReadingToolbarStorageKey(userId),
      JSON.stringify({
        repeatsEnabled: !!preferences.repeatsEnabled,
        linkedPlayback: !!preferences.linkedPlayback,
        showMidi: !!preferences.showMidi,
        showAttachments: !!preferences.showAttachments,
        minimizeAttachments: !!preferences.minimizeAttachments,
        showSectionTitles: !!preferences.showSectionTitles,
        activeReadingToolTab:
          typeof preferences.activeReadingToolTab === 'string' && preferences.activeReadingToolTab.trim()
            ? preferences.activeReadingToolTab.trim()
            : DEFAULT_LOCAL_READING_TOOLBAR_PREFERENCES.activeReadingToolTab,
      }),
    )
  } catch {
    // Ignore local storage failures.
  }
}

const filterRehearsalAttachmentsByProject = (attachments, projectId) => {
  const normalizedProjectId = normalizeProjectId(projectId)
  return (Array.isArray(attachments) ? attachments : []).filter((attachment) => {
    if (!isRehearsalAttachment(attachment)) return false
    const projectIds = Array.isArray(attachment?.project_ids)
      ? attachment.project_ids.map((item) => normalizeProjectId(item)).filter((item) => item > 0)
      : []
    if (!normalizedProjectId) return projectIds.length > 0
    return projectIds.includes(normalizedProjectId)
  })
}

const buildRehearsalTitle = (target, projectTitle = '') => {
  const anchorType = target?.anchor_type || 'song'
  const scopeLabel = anchorType === 'section'
    ? target?.label || 'sección'
    : anchorType === 'verse'
      ? `verso ${Number(target?.verse_index) + 1}`
      : anchorType === 'segment'
        ? `fragmento ${Number(target?.segment_index) + 1}`
        : 'canción completa'
  return projectTitle ? `Ensayo · ${scopeLabel} · ${projectTitle}` : `Ensayo · ${scopeLabel}`
}

export default function ReadingView({ onExit, exitLabel, onShowList, onEdit }) {
  const { state, dispatch, api, wpData } = useAppState()
  const song = state.editingSong
  const availableProjects = Array.isArray(state.projects) ? state.projects : []
  const currentUserId = wpData?.currentUserId || 0
  const localToolbarPreferences = useMemo(
    () => loadLocalReadingToolbarPreferences(currentUserId),
    [currentUserId],
  )
  const isOwnSong = Number(song?.autor_id) === Number(currentUserId)
  const canManageStatuses = wpData?.canManage !== undefined ? !!wpData?.canManage : true
  const bpmDefault = Number.isInteger(parseInt(song?.bpm, 10)) ? parseInt(song.bpm, 10) : 120
  const hasVerses = Array.isArray(song?.versos) && song.versos.length > 0
  const chordsLibrary = Array.isArray(state.chords?.library) ? state.chords.library : []
  const chordLookup = useMemo(() => buildChordLookup(chordsLibrary), [chordsLibrary])
  const camposLibrary = Array.isArray(state.campos?.library) ? state.campos.library : []
  const camposLookup = useMemo(() => {
    const map = new Map()
    camposLibrary.forEach((campo) => {
      if (!campo) return
      const key = campo.slug || campo.nombre
      if (key) {
        map.set(key, campo.nombre || campo.slug)
      }
    })
    return map
  }, [camposLibrary])
  const readingInstrument = sanitizeChordInstrumentId(state.readingInstrument || DEFAULT_CHORD_INSTRUMENT_ID)
  const isDoubleColumn = !!state.readingDoubleColumn
  const transposeTargetId = state.readingTransposeTarget || 'concert'
  const transposeTarget = TRANSPOSE_TARGETS.find((target) => target.id === transposeTargetId) || TRANSPOSE_TARGETS[0]
  const transposeSemitones = transposeTarget.semitones
  const tonicBase = song.tonica || ''
  const tonicLabel = tonicBase ? transposeChordSymbol(tonicBase, transposeSemitones) : '—'
  const songVerses = useMemo(() => (Array.isArray(song?.versos) ? song.versos : []), [song?.versos])
  const allAttachments = useMemo(() => (Array.isArray(song?.adjuntos) ? song.adjuntos : []), [song?.adjuntos])
  const rehearsalProjects = useMemo(() => {
    const explicitProjects = Array.isArray(song?.rehearsal_projects) ? song.rehearsal_projects : []
    if (explicitProjects.length) {
      return explicitProjects
    }

    const rehearsalProjectIds = Array.isArray(song?.rehearsal_project_ids) ? song.rehearsal_project_ids : []
    if (!rehearsalProjectIds.length) {
      return []
    }

    const normalizedIds = Array.from(
      new Set(
        rehearsalProjectIds
          .map((item) => normalizeProjectId(item))
          .filter((item) => item > 0),
      ),
    )

    return normalizedIds.map((projectId) => {
      const matched = availableProjects.find((project) => normalizeProjectId(project?.id) === projectId)
      return {
        id: projectId,
        titulo: matched?.titulo || `Proyecto ${projectId}`,
        can_upload: !!song?.can_upload_rehearsals,
      }
    })
  }, [
    availableProjects,
    song?.can_upload_rehearsals,
    song?.rehearsal_project_ids,
    song?.rehearsal_projects,
  ])
  const [selectedRehearsalProjectId, setSelectedRehearsalProjectId] = useState(0)
  const genericAttachments = useMemo(
    () => allAttachments.filter((attachment) => !isRehearsalAttachment(attachment)),
    [allAttachments],
  )
  const rehearsalAttachments = useMemo(
    () => allAttachments.filter((attachment) => isRehearsalAttachment(attachment)),
    [allAttachments],
  )
  const activeRehearsalProject = useMemo(
    () => rehearsalProjects.find((project) => normalizeProjectId(project?.id) === normalizeProjectId(selectedRehearsalProjectId)) || null,
    [rehearsalProjects, selectedRehearsalProjectId],
  )
  const filteredRehearsalAttachments = useMemo(
    () => filterRehearsalAttachmentsByProject(rehearsalAttachments, selectedRehearsalProjectId),
    [rehearsalAttachments, selectedRehearsalProjectId],
  )
  const songLevelAttachments = useMemo(
    () => getSongLevelAttachments({ ...(song || {}), adjuntos: genericAttachments }),
    [genericAttachments, song],
  )
  const songLevelRehearsals = useMemo(
    () => getSongLevelAttachments({ ...(song || {}), adjuntos: filteredRehearsalAttachments }),
    [filteredRehearsalAttachments, song],
  )
  const canManageSongAttachments = typeof onEdit === 'function'
  const canUploadRehearsals = useMemo(
    () =>
      !!song?.id
      && rehearsalProjects.length > 0
      && rehearsalProjects.some((project) => project?.can_upload !== false)
      && (song?.can_upload_rehearsals !== undefined
        ? !!song.can_upload_rehearsals
        : true),
    [rehearsalProjects, song?.can_upload_rehearsals, song?.id],
  )
  const hasRehearsalWorkspace = !!song?.id && rehearsalProjects.length > 0
  const [repeatsEnabled, setRepeatsEnabled] = useState(localToolbarPreferences.repeatsEnabled)
  const [linkedPlayback, setLinkedPlayback] = useState(localToolbarPreferences.linkedPlayback)
  const [showMidi, setShowMidi] = useState(localToolbarPreferences.showMidi)
  const [showAttachments, setShowAttachments] = useState(localToolbarPreferences.showAttachments)
  const [minimizeAttachments, setMinimizeAttachments] = useState(localToolbarPreferences.minimizeAttachments)
  const [showSectionTitles, setShowSectionTitles] = useState(localToolbarPreferences.showSectionTitles)
  const [statusSaving, setStatusSaving] = useState({ transcription: false, rehearsal: false })
  const [pendingAttachmentActions, setPendingAttachmentActions] = useState({})
  const [songRefreshing, setSongRefreshing] = useState(false)
  const [activePlaybackKey, setActivePlaybackKey] = useState(null)
  const [activePlaybackMeta, setActivePlaybackMeta] = useState(null)
  const [activeSectionIndex, setActiveSectionIndex] = useState(0)
  const [activeReadingToolTab, setActiveReadingToolTab] = useState(localToolbarPreferences.activeReadingToolTab)
  const showSongMediaDock =
    showAttachments && (songLevelAttachments.length > 0 || songLevelRehearsals.length > 0)
  const [isCompactViewport, setIsCompactViewport] = useState(() => isCompactReadingViewport())
  const [readingZoom, setReadingZoom] = useState(() =>
    isCompactReadingViewport() ? MOBILE_DEFAULT_READING_ZOOM : 100,
  )
  const sectionRefs = useRef(new Map())
  const sectionsScrollRef = useRef(null)
  const readingRootRef = useRef(null)
  const pinchStateRef = useRef({ active: false, startDistance: 0, startZoom: 100 })
  const readingZoomRef = useRef(readingZoom)
  const refreshInFlightRef = useRef(false)
  const editingSongRef = useRef(state.editingSong)
  const songsRef = useRef(state.songs)

  useEffect(() => {
    if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
      return undefined
    }

    const mediaQuery = window.matchMedia(MOBILE_READING_QUERY)
    const syncLayout = (isCompact) => {
      setIsCompactViewport(isCompact)
    }

    syncLayout(mediaQuery.matches)

    const handleChange = (event) => {
      syncLayout(!!event?.matches)
    }

    if (typeof mediaQuery.addEventListener === 'function') {
      mediaQuery.addEventListener('change', handleChange)
      return () => mediaQuery.removeEventListener('change', handleChange)
    }

    mediaQuery.addListener(handleChange)
    return () => mediaQuery.removeListener(handleChange)
  }, [])

  useEffect(() => {
    if (!isCompactViewport) {
      return
    }
    setReadingZoom((current) => (current === 100 ? MOBILE_DEFAULT_READING_ZOOM : current))
  }, [isCompactViewport])

  useEffect(() => {
    readingZoomRef.current = readingZoom
  }, [readingZoom])

  useEffect(() => {
    editingSongRef.current = state.editingSong
    songsRef.current = state.songs
  }, [state.editingSong, state.songs])

  useEffect(() => {
    if (!isCompactViewport || !hasVerses) {
      return undefined
    }

    const node = sectionsScrollRef.current
    if (!node || typeof node.addEventListener !== 'function') {
      return undefined
    }

    const pinch = pinchStateRef.current
    const finishPinch = () => {
      if (!pinch.active) {
        return
      }
      pinch.active = false
      pinch.startDistance = 0
      pinch.startZoom = readingZoomRef.current
      setReadingZoom((current) => snapReadingZoom(current))
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
      pinch.startZoom = readingZoomRef.current
    }

    const handleTouchMove = (event) => {
      if (!pinch.active || event.touches.length !== 2) {
        return
      }
      const distance = getTouchDistance(event.touches)
      if (!distance || !pinch.startDistance) {
        return
      }
      const scaledZoom = pinch.startZoom * (distance / pinch.startDistance)
      const bounded = clampReadingZoom(Math.round(scaledZoom))
      setReadingZoom(bounded)
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
  }, [isCompactViewport, hasVerses])

  useEffect(() => {
    if (typeof document === 'undefined' || typeof window === 'undefined') {
      return undefined
    }

    const html = document.documentElement
    const body = document.body
    const root = readingRootRef.current

    const clearGlobalWidth = () => {
      html.classList.remove(GLOBAL_READING_WIDTH_CLASS)
      body.classList.remove(GLOBAL_READING_WIDTH_CLASS)
      html.style.removeProperty('--wpss-reading-content-width')
      body.style.removeProperty('--wpss-reading-content-width')
      if (root) {
        root.style.removeProperty('--wpss-reading-content-width')
      }
    }

    const resetComputedWidth = (readingNode) => {
      html.style.removeProperty('--wpss-reading-content-width')
      body.style.removeProperty('--wpss-reading-content-width')
      if (readingNode) {
        readingNode.style.removeProperty('--wpss-reading-content-width')
      }
    }

    if (!hasVerses) {
      clearGlobalWidth()
      return clearGlobalWidth
    }

    const computeTargetWidth = () => {
      const readingNode = readingRootRef.current
      if (!readingNode) {
        return
      }

      resetComputedWidth(readingNode)
      void readingNode.offsetWidth

      const viewportWidth = window.innerWidth || document.documentElement.clientWidth || readingNode.clientWidth || 0
      const resolved = Math.ceil(viewportWidth)
      const value = `${resolved}px`
      html.style.setProperty('--wpss-reading-content-width', value)
      body.style.setProperty('--wpss-reading-content-width', value)
      readingNode.style.setProperty('--wpss-reading-content-width', value)
    }

    html.classList.add(GLOBAL_READING_WIDTH_CLASS)
    body.classList.add(GLOBAL_READING_WIDTH_CLASS)

    let rafId = window.requestAnimationFrame(computeTargetWidth)
    const onResize = () => {
      if (rafId) {
        window.cancelAnimationFrame(rafId)
      }
      rafId = window.requestAnimationFrame(computeTargetWidth)
    }
    window.addEventListener('resize', onResize)

    let resizeObserver = null
    if (typeof window.ResizeObserver === 'function' && root) {
      resizeObserver = new window.ResizeObserver(() => {
        onResize()
      })
      resizeObserver.observe(root)
    }

    return () => {
      if (rafId) {
        window.cancelAnimationFrame(rafId)
      }
      window.removeEventListener('resize', onResize)
      if (resizeObserver) {
        resizeObserver.disconnect()
      }
      clearGlobalWidth()
    }
  }, [hasVerses, song, state.readingFollowStructure, state.readingMode, showMidi, state.readingShowNotes, isDoubleColumn])

  useEffect(() => {
    if (!rehearsalProjects.length) {
      setSelectedRehearsalProjectId(0)
      return
    }

    const currentId = normalizeProjectId(selectedRehearsalProjectId)
    const exists = rehearsalProjects.some((project) => normalizeProjectId(project?.id) === currentId)
    if (!exists) {
      setSelectedRehearsalProjectId(normalizeProjectId(rehearsalProjects[0]?.id))
    }
  }, [rehearsalProjects, selectedRehearsalProjectId])

  const groups = useMemo(() => {
    if (!hasVerses) {
      return []
    }
    return state.readingFollowStructure
      ? groupVersesByStructure(song)
      : groupVersesBySection(song).map((group, index) => ({
          title: group.section?.nombre || getDefaultSectionName(index),
          variant: '',
          notes: '',
          versos: group.versos,
          section: group.section,
        }))
  }, [song, state.readingFollowStructure, hasVerses])

  const currentSectionIndex = groups.length
    ? Math.min(activeSectionIndex, groups.length - 1)
    : 0

  const sectionNavItems = useMemo(
    () =>
      groups.map((group, index) => {
        const repeatRaw = parseInt(group?.repeat, 10)
        const repeat = Number.isInteger(repeatRaw) && repeatRaw > 1 ? Math.min(repeatRaw, 16) : 1
        return {
          index,
          label: getGroupHeading(group, index),
          repeat,
        }
      }),
    [groups],
  )

  const readingToolTabs = useMemo(() => {
    const tabs = [
      { id: 'lectura', label: 'Lectura' },
      ...(hasRehearsalWorkspace ? [{ id: 'ensayo', label: 'Ensayo' }] : []),
      { id: 'vista', label: 'Vista' },
      { id: 'orden', label: 'Orden' },
      { id: 'midi', label: 'MIDI' },
      { id: 'adjuntos', label: 'Adjuntos' },
      { id: 'notas', label: 'Notas' },
      { id: 'secciones', label: 'Secciones' },
      { id: 'transposicion', label: 'Trasponer' },
      { id: 'instrumento', label: 'Instrumento' },
    ]
    if (canManageStatuses) {
      tabs.push({ id: 'estados', label: 'Estados' })
    }
    return tabs
  }, [canManageStatuses, hasRehearsalWorkspace])

  const buildClipSteps = (clips, meta) =>
    buildMidiClipGroups(clips, linkedPlayback).map((group) => ({ clips: group, meta }))

  const buildSectionSteps = (group, sectionIndex) => {
    const steps = []
    steps.push(...buildClipSteps(group.section?.midi_clips, { sectionIndex, verseIndex: null, segmentIndex: null }))
    const verses = Array.isArray(group.versos) ? group.versos : []
    verses.forEach((verse, verseIndex) => {
      steps.push(
        ...buildClipSteps(verse?.midi_clips, { sectionIndex, verseIndex, segmentIndex: null }),
      )
      const segmentos = Array.isArray(verse?.segmentos) ? verse.segmentos : []
      segmentos.forEach((segmento, segmentIndex) => {
        steps.push(
          ...buildClipSteps(segmento?.midi_clips, { sectionIndex, verseIndex, segmentIndex }),
        )
      })
    })
    return steps
  }

  const hasMidiInGroup = (group) => {
    if (Array.isArray(group.section?.midi_clips) && group.section.midi_clips.length) {
      return true
    }
    const verses = Array.isArray(group.versos) ? group.versos : []
    return verses.some((verse) => {
      if (Array.isArray(verse?.midi_clips) && verse.midi_clips.length) {
        return true
      }
      const segmentos = Array.isArray(verse?.segmentos) ? verse.segmentos : []
      return segmentos.some((segmento) => Array.isArray(segmento?.midi_clips) && segmento.midi_clips.length)
    })
  }

  const handlePlaySteps = (key, steps) => {
    if (!steps.length) {
      return
    }
    const result = togglePlayback(
      key,
      () =>
        playMidiClipGroupsSequence(steps, {
          defaultTempo: bpmDefault,
          repeatsEnabled,
          onStepStart: (meta) => setActivePlaybackMeta(meta || null),
        }),
      () => {
        setActivePlaybackKey(null)
        setActivePlaybackMeta(null)
      },
    )
    setActivePlaybackKey(result.playing ? key : null)
  }

  const handlePlayAll = () => {
    const steps = groups.flatMap((group, index) => {
      const repeat = Number.isInteger(parseInt(group.repeat, 10)) ? parseInt(group.repeat, 10) : 1
      const clamped = Math.min(Math.max(repeat, 1), 16)
      const sectionSteps = buildSectionSteps(group, index)
      return Array.from({ length: clamped }, () => sectionSteps).flat()
    })
    handlePlaySteps('all', steps)
  }

  const handlePrint = () => {
    if (
      typeof window === 'undefined'
      || typeof document === 'undefined'
      || typeof window.print !== 'function'
    ) {
      return
    }

    const previousFollowStructure = !!state.readingFollowStructure
    const runPrint = () => {
      document.body.classList.add(PRINT_BODY_CLASS)
      window.print()
    }

    const cleanup = () => {
      document.body.classList.remove(PRINT_BODY_CLASS)
      if (!previousFollowStructure) {
        dispatch({ type: 'SET_STATE', payload: { readingFollowStructure: false } })
      }
    }

    window.addEventListener('afterprint', cleanup, { once: true })

    if (!previousFollowStructure) {
      dispatch({ type: 'SET_STATE', payload: { readingFollowStructure: true } })
      if (typeof window.requestAnimationFrame === 'function') {
        window.requestAnimationFrame(() => {
          window.requestAnimationFrame(runPrint)
        })
      } else {
        window.setTimeout(runPrint, 60)
      }
      return
    }

    runPrint()
  }

  const handleSectionJump = (index) => {
    const target = sectionRefs.current.get(index)
    if (!target) {
      return
    }
    target.open = true
    const sectionsScrollNode = sectionsScrollRef.current
    if (sectionsScrollNode && typeof sectionsScrollNode.scrollTo === 'function') {
      const getClampedTop = (rawTop) => {
        const maxTop = Math.max(sectionsScrollNode.scrollHeight - sectionsScrollNode.clientHeight, 0)
        return Math.min(Math.max(rawTop, 0), maxTop)
      }

      const getDesiredTop = () => {
        const sectionsRect = sectionsScrollNode.getBoundingClientRect()
        const targetRect = target.getBoundingClientRect()
        const visualDelta = targetRect.top - sectionsRect.top
        return getClampedTop(sectionsScrollNode.scrollTop + visualDelta - 6)
      }

      const alignToTarget = (behavior = 'smooth') => {
        sectionsScrollNode.scrollTo({ top: getDesiredTop(), behavior })
      }

      alignToTarget('smooth')
      window.setTimeout(() => {
        const desiredTop = getDesiredTop()
        const delta = desiredTop - sectionsScrollNode.scrollTop
        if (Math.abs(delta) > 1) {
          sectionsScrollNode.scrollTop = desiredTop
        }
      }, 300)
    } else if (typeof target.scrollIntoView === 'function') {
      target.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
    setActiveSectionIndex(index)
  }

  const canZoomOut = readingZoom > READING_ZOOM_MIN
  const canZoomIn = readingZoom < READING_ZOOM_MAX
  const zoomFactor = readingZoom / 100
  const handleZoomChange = (nextZoom) => {
    const parsed = Number.parseInt(nextZoom, 10)
    if (!Number.isFinite(parsed)) {
      return
    }
    setReadingZoom(clampReadingZoom(parsed))
  }

  const handleZoomStep = (direction) => {
    setReadingZoom((current) => clampReadingZoom(current + (direction * READING_ZOOM_STEP)))
  }

  const patchSongState = (patch) => {
    const songId = song?.id
    if (!songId) return
    dispatch({
      type: 'SET_STATE',
      payload: {
        editingSong: { ...state.editingSong, ...patch },
        songs: state.songs.map((item) =>
          Number(item.id) === Number(songId) ? { ...item, ...patch } : item,
        ),
      },
    })
  }

  const refreshCurrentSong = useCallback(async ({ manual = false } = {}) => {
    const songId = Number(song?.id || 0)
    if (!songId || refreshInFlightRef.current) {
      return
    }

    refreshInFlightRef.current = true
    setSongRefreshing(true)

    try {
      const response = state.view === 'public' ? await api.getPublicSong(songId) : await api.getSong(songId)
      const refreshedSong = response?.data || {}
      const normalizedSong = mapSongToEditingSong(refreshedSong)
      const currentEditingSnapshot = JSON.stringify(editingSongRef.current || {})
      const nextEditingSnapshot = JSON.stringify(normalizedSong)
      const currentListSong = Array.isArray(songsRef.current)
        ? songsRef.current.find((item) => Number(item?.id || 0) === songId)
        : null
      const currentListSnapshot = JSON.stringify(currentListSong || {})
      const nextListSnapshot = JSON.stringify(refreshedSong || {})

      if (currentEditingSnapshot !== nextEditingSnapshot || currentListSnapshot !== nextListSnapshot) {
        dispatch({
          type: 'SET_STATE',
          payload: {
            editingSong: normalizedSong,
            songs: upsertSongInList(songsRef.current, refreshedSong),
            selectedSongId: Number(refreshedSong?.id || songId),
            error: null,
          },
        })
      }
    } catch (error) {
      if (manual) {
        const payloadMessage = error?.payload?.message
        const deniedMessage = payloadMessage || 'No fue posible refrescar la canción.'
        const fallbackMessage = wpData?.strings?.loadSongError || 'No fue posible refrescar la canción.'
        const message = error?.status === 403 ? deniedMessage : fallbackMessage
        dispatch({ type: 'SET_STATE', payload: { error: message } })
      }
    } finally {
      refreshInFlightRef.current = false
      setSongRefreshing(false)
    }
  }, [api, dispatch, song?.id, state.view, wpData])

  const handleUploadRehearsal = async (target, mode, file) => {
    const songId = song?.id
    const projectId = normalizeProjectId(selectedRehearsalProjectId)
    if (!songId || !projectId) {
      dispatch({ type: 'SET_STATE', payload: { error: 'Selecciona primero el proyecto del ensayo.' } })
      return
    }

    const projectTitle = activeRehearsalProject?.titulo || ''
    const formData = new FormData()
    formData.append('song_id', String(songId))
    formData.append('type', 'audio')
    formData.append('attachment_role', 'rehearsal')
    formData.append('title', buildRehearsalTitle(target, projectTitle))
    formData.append('source_kind', mode === 'recordAudio' ? 'recording' : 'import')
    formData.append('anchor_type', String(target?.anchor_type || 'song'))
    formData.append('section_id', String(target?.section_id || ''))
    formData.append('verse_index', String(Number(target?.verse_index) || 0))
    formData.append('segment_index', String(Number(target?.segment_index) || 0))
    formData.append('project_ids', JSON.stringify([projectId]))
    formData.append('duration_seconds', '0')
    formData.append('file', file)

    try {
      const response = await api.uploadSongAttachment(formData)
      const attachments = Array.isArray(response?.data?.adjuntos) ? response.data.adjuntos : []
      patchSongState({ adjuntos: attachments })
      dispatch({
        type: 'SET_STATE',
        payload: {
          feedback: { message: response?.data?.message || 'Ensayo guardado en Google Drive.', type: 'success' },
          error: null,
        },
      })
    } catch (error) {
      const message = error?.payload?.message || 'No fue posible guardar el ensayo.'
      dispatch({ type: 'SET_STATE', payload: { error: message } })
      throw error
    }
  }

  const handleUploadAttachment = async (target, mode, file) => {
    const songId = song?.id
    if (!songId) {
      return
    }

    const type = mode === 'importPhoto' || mode === 'capturePhoto' ? 'photo' : 'audio'
    const sourceKind = mode === 'recordAudio'
      ? 'recording'
      : mode === 'capturePhoto'
        ? 'capture'
        : 'import'
    const mediaPermissions = song?.adjuntos_permisos || {}
    const resolvedTarget = {
      anchor_type: String(target?.anchor_type || 'song'),
      section_id: String(target?.section_id || ''),
      verse_index: Number(target?.verse_index) || 0,
      segment_index: Number(target?.segment_index) || 0,
    }

    const formData = new FormData()
    formData.append('song_id', String(songId))
    formData.append('title', String(file?.name || `${type}-${Date.now()}`))
    formData.append('type', type)
    formData.append('source_kind', sourceKind)
    formData.append('anchor_type', resolvedTarget.anchor_type)
    formData.append(
      'section_id',
      resolvedTarget.anchor_type === 'section' || resolvedTarget.anchor_type === 'segment'
        ? resolvedTarget.section_id
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

    const busyKey = `upload-${Date.now()}`
    try {
      setPendingAttachmentActions((prev) => ({
        ...prev,
        [busyKey]: 'Subiendo a Drive...',
      }))
      const response = await api.uploadSongAttachment(formData)
      const attachments = Array.isArray(response?.data?.adjuntos) ? response.data.adjuntos : []
      patchSongState({ adjuntos: attachments })
      dispatch({
        type: 'SET_STATE',
        payload: {
          feedback: { message: response?.data?.message || 'Adjunto subido a Google Drive.', type: 'success' },
          error: null,
        },
      })
    } catch (error) {
      const message = error?.payload?.message || 'No fue posible subir el adjunto.'
      dispatch({ type: 'SET_STATE', payload: { error: message } })
      throw error
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
  }

  const handleDeleteAttachment = async (attachment) => {
    const songId = song?.id
    const attachmentId = attachment?.id
    if (!songId || !attachmentId || !attachment?.can_delete_file) {
      return
    }

    const confirmed = window.confirm(
      `¿Eliminar definitivamente "${attachment.title || attachment.file_name || attachment.id}" del Google Drive?`,
    )
    if (!confirmed) {
      return
    }

    try {
      setPendingAttachmentActions((prev) => ({ ...prev, [attachmentId]: 'Eliminando de Drive...' }))
      const response = await api.deleteSongAttachment(songId, attachmentId)
      const attachments = Array.isArray(response?.data?.adjuntos) ? response.data.adjuntos : []
      patchSongState({ adjuntos: attachments })
      dispatch({
        type: 'SET_STATE',
        payload: {
          feedback: { message: response?.data?.message || 'Adjunto eliminado del Drive.', type: 'success' },
          error: null,
        },
      })
    } catch (error) {
      const message = error?.payload?.message || 'No fue posible eliminar el adjunto del Drive.'
      dispatch({ type: 'SET_STATE', payload: { error: message } })
    } finally {
      setPendingAttachmentActions((prev) => {
        const next = { ...prev }
        delete next[attachmentId]
        return next
      })
    }
  }

  const handleTranscriptionStatusChange = (nextStatus) => {
    if (!song?.id || !isOwnSong || !canManageStatuses) return
    setStatusSaving((prev) => ({ ...prev, transcription: true }))
    api
      .setSongTranscriptionStatus(song.id, nextStatus)
      .then((response) => {
        const body = response?.data || {}
        const nextSong = body.song && typeof body.song === 'object' ? body.song : null
        patchSongState(
          nextSong || {
            estado_transcripcion: body.estado_transcripcion || nextStatus,
            estado_transcripcion_label:
              body.estado_transcripcion_label
              || getStatusLabel(TRANSCRIPTION_STATUS_LABELS, body.estado_transcripcion || nextStatus),
          },
        )
      })
      .catch((error) => {
        const message =
          error?.payload?.message || 'No fue posible actualizar el estado de transcripción.'
        dispatch({ type: 'SET_STATE', payload: { error: message } })
      })
      .finally(() => {
        setStatusSaving((prev) => ({ ...prev, transcription: false }))
      })
  }

  const handleRehearsalStatusChange = (nextStatus) => {
    if (!song?.id || !canManageStatuses) return
    setStatusSaving((prev) => ({ ...prev, rehearsal: true }))
    api
      .setSongRehearsalStatus(song.id, nextStatus)
      .then((response) => {
        const body = response?.data || {}
        const nextSong = body.song && typeof body.song === 'object' ? body.song : null
        patchSongState(
          nextSong || {
            estado_ensayo: body.estado_ensayo || nextStatus,
            estado_ensayo_label:
              body.estado_ensayo_label
              || getStatusLabel(REHEARSAL_STATUS_LABELS, body.estado_ensayo || nextStatus),
          },
        )
      })
      .catch((error) => {
        const message = error?.payload?.message || 'No fue posible actualizar tu estado de ensayo.'
        dispatch({ type: 'SET_STATE', payload: { error: message } })
      })
      .finally(() => {
        setStatusSaving((prev) => ({ ...prev, rehearsal: false }))
      })
  }

  useEffect(() => {
    if (!readingToolTabs.some((tab) => tab.id === activeReadingToolTab)) {
      setActiveReadingToolTab(readingToolTabs[0]?.id || 'lectura')
    }
  }, [activeReadingToolTab, readingToolTabs])

  useEffect(() => {
    persistLocalReadingToolbarPreferences(currentUserId, {
      repeatsEnabled,
      linkedPlayback,
      showMidi,
      showAttachments,
      minimizeAttachments,
      showSectionTitles,
      activeReadingToolTab,
    })
  }, [
    currentUserId,
    repeatsEnabled,
    linkedPlayback,
    showMidi,
    showAttachments,
    minimizeAttachments,
    showSectionTitles,
    activeReadingToolTab,
  ])

  useEffect(() => {
    if (!song?.id) {
      return undefined
    }

    const intervalId = window.setInterval(() => {
      if (typeof document !== 'undefined' && document.visibilityState === 'hidden') {
        return
      }
      refreshCurrentSong()
    }, SONG_REFRESH_INTERVAL_MS)

    return () => window.clearInterval(intervalId)
  }, [refreshCurrentSong, song?.id])

  const activeReadingToolLabel =
    readingToolTabs.find((tab) => tab.id === activeReadingToolTab)?.label || 'Lectura'

  return (
    <div className={`wpss-reading ${isCompactViewport ? 'is-compact' : ''}`} ref={readingRootRef}>
      <div className="wpss-reading__header">
        <div className="wpss-reading__header-top">
          <div className="wpss-reading__header-main">
            <div className="wpss-reading__meta-block">
              <h3>{song.titulo || wpData?.strings?.newSong || 'Nueva canción'}</h3>
              <p>
                <strong>Tónica:</strong> {tonicLabel}
                {tonicBase && transposeSemitones ? (
                  <span className="wpss-reading__transpose-origin">{` (concierto: ${tonicBase})`}</span>
                ) : null}
              </p>
              <p>
                <strong>Campo armónico:</strong> {song.campo_armonico || '—'}
              </p>
            </div>
            <div className="wpss-reading__quick-actions" role="toolbar" aria-label="Acciones rápidas de lectura">
              <button
                type="button"
                className="button button-secondary"
                onClick={() => refreshCurrentSong({ manual: true })}
                disabled={songRefreshing}
              >
                {songRefreshing ? 'Refrescando…' : 'Refrescar'}
              </button>
              <button
                type="button"
                className="button button-secondary"
                onClick={handlePlayAll}
              >
                {activePlaybackKey === 'all' ? 'Detener reproducción' : 'Reproducir todo'}
              </button>
              {onEdit ? (
                <button type="button" className="button button-secondary" onClick={onEdit}>
                  {wpData?.strings?.editorView || 'Editar'}
                </button>
              ) : null}
              <button
                type="button"
                className="button button-secondary wpss-reading__print-button"
                onClick={handlePrint}
              >
                <PrintIcon />
                <span>Imprimir lectura</span>
              </button>
              {onShowList ? (
                <button type="button" className="button button-secondary" onClick={onShowList}>
                  Ver canciones
                </button>
              ) : null}
              <button
                type="button"
                className="button"
                onClick={() => {
                  if (onExit) {
                    onExit()
                  } else {
                    dispatch({ type: 'SET_STATE', payload: { activeTab: 'editor' } })
                  }
                }}
              >
                {exitLabel || wpData?.strings?.readingExit || 'Salir'}
              </button>
            </div>
          </div>
        </div>
        <div className="wpss-reading__tools-shell">
          <div className="wpss-reading__toolbar" role="tablist" aria-label="Herramientas de lectura">
            {readingToolTabs.map((tab) => (
              <button
                key={tab.id}
                type="button"
                role="tab"
                className={`button button-secondary wpss-reading__tool-tab ${
                  activeReadingToolTab === tab.id ? 'is-active' : ''
                }`}
                aria-selected={activeReadingToolTab === tab.id}
                aria-controls={`wpss-reading-tool-panel-${tab.id}`}
                id={`wpss-reading-tool-tab-${tab.id}`}
                onClick={() => setActiveReadingToolTab(tab.id)}
              >
                {tab.label}
              </button>
            ))}
          </div>
          <div
            className="wpss-reading__tool-panel"
            role="tabpanel"
            id={`wpss-reading-tool-panel-${activeReadingToolTab}`}
            aria-labelledby={`wpss-reading-tool-tab-${activeReadingToolTab}`}
          >
            <span className="wpss-reading__group-label">{activeReadingToolLabel}</span>
            {activeReadingToolTab === 'lectura' ? (
              <div className="wpss-reading__group-controls">
                {!isCompactViewport ? (
                  <button
                    type="button"
                    className={`button button-secondary ${isDoubleColumn ? 'is-active' : ''}`}
                    onClick={() =>
                      dispatch({ type: 'SET_STATE', payload: { readingDoubleColumn: !state.readingDoubleColumn } })
                    }
                  >
                    {isDoubleColumn ? 'Ver en 1 columna' : 'Ver en 2 columnas'}
                  </button>
                ) : null}
                <div className="wpss-reading__zoom-controls" role="group" aria-label="Zoom de lectura">
                  <button
                    type="button"
                    className="button button-secondary"
                    onClick={() => handleZoomStep(-1)}
                    disabled={!canZoomOut}
                    aria-label="Alejar vista"
                  >
                    -
                  </button>
                  <button
                    type="button"
                    className="button button-secondary wpss-reading__zoom-reset"
                    onClick={() => setReadingZoom(100)}
                    disabled={readingZoom === 100}
                    aria-label="Restablecer zoom"
                  >
                    {`${readingZoom}%`}
                  </button>
                  <label className="wpss-reading__zoom-slider-field">
                    <span className="screen-reader-text">Ajustar zoom</span>
                    <input
                      type="range"
                      className="wpss-reading__zoom-slider"
                      min={READING_ZOOM_MIN}
                      max={READING_ZOOM_MAX}
                      step={READING_ZOOM_STEP}
                      value={readingZoom}
                      onChange={(event) => handleZoomChange(event.target.value)}
                      aria-label="Ajustar zoom"
                    />
                  </label>
                  <button
                    type="button"
                    className="button button-secondary"
                    onClick={() => handleZoomStep(1)}
                    disabled={!canZoomIn}
                    aria-label="Acercar vista"
                  >
                    +
                  </button>
                </div>
              </div>
            ) : null}
            {activeReadingToolTab === 'ensayo' ? (
              <div className="wpss-reading__group-controls wpss-reading__group-controls--rehearsal">
                {rehearsalProjects.length ? (
                  <label className="wpss-reading__status-field">
                    <span>Proyecto de ensayo</span>
                    <select
                      value={String(selectedRehearsalProjectId || '')}
                      onChange={(event) => setSelectedRehearsalProjectId(normalizeProjectId(event.target.value))}
                    >
                      {rehearsalProjects.map((project) => (
                        <option key={`rehearsal-project-${project.id}`} value={String(project.id)}>
                          {project?.titulo || `Proyecto #${project.id}`}
                        </option>
                      ))}
                    </select>
                  </label>
                ) : (
                  <span className="wpss-reading__status-label">
                    Esta canción no tiene proyectos habilitados para ensayos.
                  </span>
                )}
                <span className="wpss-reading__status-label">
                  {activeRehearsalProject
                    ? `Escuchando tomas de ${activeRehearsalProject.titulo}.`
                    : 'Selecciona un proyecto para filtrar los ensayos.'}
                </span>
                {canUploadRehearsals && activeRehearsalProject ? (
                  <InlineMediaQuickActions
                    target={{ anchor_type: 'song', label: 'canción completa', compactRecorder: true }}
                    onUpload={handleUploadRehearsal}
                    allowedModes={['importAudio', 'recordAudio']}
                  />
                ) : null}
              </div>
            ) : null}
            {activeReadingToolTab === 'vista' ? (
              <div className="wpss-reading__group-controls">
                <button
                  type="button"
                  className={`button button-secondary ${state.readingMode === 'inline' ? 'is-active' : ''}`}
                  onClick={() => dispatch({ type: 'SET_STATE', payload: { readingMode: 'inline' } })}
                >
                  {wpData?.strings?.readingModeInline || 'Acordes inline'}
                </button>
                <button
                  type="button"
                  className={`button button-secondary ${state.readingMode === 'stacked' ? 'is-active' : ''}`}
                  onClick={() => dispatch({ type: 'SET_STATE', payload: { readingMode: 'stacked' } })}
                >
                  {wpData?.strings?.readingModeStacked || 'Acordes arriba'}
                </button>
              </div>
            ) : null}
            {activeReadingToolTab === 'orden' ? (
              <div className="wpss-reading__group-controls">
                <button
                  type="button"
                  className={`button button-secondary ${state.readingFollowStructure ? '' : 'is-active'}`}
                  onClick={() => dispatch({ type: 'SET_STATE', payload: { readingFollowStructure: false } })}
                >
                  {wpData?.strings?.readingFollowSections || 'Ordenar por secciones'}
                </button>
                <button
                  type="button"
                  className={`button button-secondary ${state.readingFollowStructure ? 'is-active' : ''}`}
                  onClick={() => dispatch({ type: 'SET_STATE', payload: { readingFollowStructure: true } })}
                >
                  {wpData?.strings?.readingFollowStructure || 'Seguir estructura'}
                </button>
              </div>
            ) : null}
            {activeReadingToolTab === 'midi' ? (
              <div className="wpss-reading__group-controls">
                <button
                  type="button"
                  className={`button button-secondary ${showMidi ? 'is-active' : ''}`}
                  onClick={() => setShowMidi((prev) => !prev)}
                >
                  {showMidi ? 'Omitir MIDI' : 'Mostrar MIDI'}
                </button>
                <button
                  type="button"
                  className={`button button-secondary ${repeatsEnabled ? 'is-active' : ''}`}
                  onClick={() => setRepeatsEnabled((prev) => !prev)}
                >
                  {repeatsEnabled ? 'Repeticiones activas' : 'Repeticiones apagadas'}
                </button>
                <button
                  type="button"
                  className={`button button-secondary ${linkedPlayback ? 'is-active' : ''}`}
                  onClick={() => setLinkedPlayback((prev) => !prev)}
                >
                  {linkedPlayback ? 'Vinculos activos' : 'Vinculos apagados'}
                </button>
              </div>
            ) : null}
            {activeReadingToolTab === 'adjuntos' ? (
              <div className="wpss-reading__group-controls">
                <button
                  type="button"
                  className={`button button-secondary ${showAttachments ? 'is-active' : ''}`}
                  onClick={() => setShowAttachments((prev) => !prev)}
                >
                  {showAttachments ? 'Ocultar adjuntos' : 'Mostrar adjuntos'}
                </button>
                <button
                  type="button"
                  className={`button button-secondary ${minimizeAttachments ? 'is-active' : ''}`}
                  onClick={() => setMinimizeAttachments((prev) => !prev)}
                >
                  {minimizeAttachments ? 'Minimizado' : 'Minimizar'}
                </button>
              </div>
            ) : null}
            {activeReadingToolTab === 'notas' ? (
              <div className="wpss-reading__group-controls">
                <button
                  type="button"
                  className={`button button-secondary ${state.readingShowNotes ? 'is-active' : ''}`}
                  onClick={() => dispatch({ type: 'SET_STATE', payload: { readingShowNotes: !state.readingShowNotes } })}
                >
                  {state.readingShowNotes ? 'Ocultar notas' : 'Mostrar notas'}
                </button>
              </div>
            ) : null}
            {activeReadingToolTab === 'secciones' ? (
              <div className="wpss-reading__group-controls">
                <button
                  type="button"
                  className={`button button-secondary ${showSectionTitles ? 'is-active' : ''}`}
                  onClick={() => setShowSectionTitles((prev) => !prev)}
                >
                  {showSectionTitles ? 'Omitir titulos de secciones' : 'Mostrar titulos de secciones'}
                </button>
              </div>
            ) : null}
            {activeReadingToolTab === 'transposicion' ? (
              <div className="wpss-reading__group-controls">
                <select
                  value={transposeTarget.id}
                  onChange={(event) =>
                    dispatch({ type: 'SET_STATE', payload: { readingTransposeTarget: event.target.value } })
                  }
                >
                  {TRANSPOSE_TARGETS.map((target) => (
                    <option key={target.id} value={target.id}>
                      {target.label}
                    </option>
                  ))}
                </select>
              </div>
            ) : null}
            {activeReadingToolTab === 'instrumento' ? (
              <div className="wpss-reading__group-controls">
                <select
                  value={readingInstrument}
                  onChange={(event) => {
                    const nextInstrument = event.target.value
                    const nextPayload = { readingInstrument: nextInstrument }
                    const autoTransposeTarget = INSTRUMENT_TRANSPOSE_TARGETS[nextInstrument] || ''
                    if (autoTransposeTarget) {
                      nextPayload.readingTransposeTarget = autoTransposeTarget
                    } else if (transposeTargetId !== 'concert' && !INSTRUMENT_TRANSPOSE_TARGETS[readingInstrument]) {
                      nextPayload.readingTransposeTarget = 'concert'
                    } else if (transposeTargetId !== 'concert' && INSTRUMENT_TRANSPOSE_TARGETS[readingInstrument]) {
                      nextPayload.readingTransposeTarget = 'concert'
                    }
                    dispatch({ type: 'SET_STATE', payload: nextPayload })
                  }}
                >
                  {CHORD_INSTRUMENTS.map((instrument) => (
                    <option key={instrument.id} value={instrument.id}>
                      {instrument.label}
                    </option>
                  ))}
                </select>
              </div>
            ) : null}
            {activeReadingToolTab === 'estados' && canManageStatuses ? (
              <div className="wpss-reading__group-controls wpss-reading__group-controls--status">
                {isOwnSong ? (
                  <label className="wpss-reading__status-field">
                    <span>Transcripción</span>
                    <select
                      value={song?.estado_transcripcion || 'sin_iniciar'}
                      disabled={statusSaving.transcription || !song?.id}
                      onChange={(event) => handleTranscriptionStatusChange(event.target.value)}
                    >
                      {TRANSCRIPTION_STATUS_OPTIONS.map((option) => (
                        <option key={option.id} value={option.id}>
                          {option.label}
                        </option>
                      ))}
                    </select>
                  </label>
                ) : (
                  <span className="wpss-reading__status-label">
                    Transcripción: {song?.estado_transcripcion_label || 'Sin iniciar'}
                  </span>
                )}
                <label className="wpss-reading__status-field">
                  <span>Ensayo (yo)</span>
                  <select
                    value={song?.estado_ensayo || 'sin_ensayar'}
                    disabled={statusSaving.rehearsal || !song?.id}
                    onChange={(event) => handleRehearsalStatusChange(event.target.value)}
                  >
                    {REHEARSAL_STATUS_OPTIONS.map((option) => (
                      <option key={option.id} value={option.id}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                </label>
                {Array.isArray(song?.colecciones) && song.colecciones.length ? (
                  <span className="wpss-reading__status-label">
                    Repertorios: {song.colecciones.map((collection) => {
                      const detail = formatCollectionAssignment(collection)
                      return detail ? `${collection.nombre} (${detail})` : collection.nombre
                    }).join(' · ')}
                  </span>
                ) : null}
              </div>
            ) : null}
          </div>
        </div>
        {showSongMediaDock ? (
          <div className="wpss-reading__media-dock">
            {showAttachments || activeReadingToolTab === 'adjuntos' ? (
              <ReadingMediaAttachments
                attachments={songLevelAttachments}
                title="Adjuntos de la canción"
                emptyLabel=""
                compact
                minimal={minimizeAttachments}
                onDelete={handleDeleteAttachment}
                pendingActionById={pendingAttachmentActions}
              />
            ) : null}
            {showAttachments && songLevelRehearsals.length ? (
              <ReadingMediaAttachments
                attachments={songLevelRehearsals}
                title={activeRehearsalProject ? `Ensayos · ${activeRehearsalProject.titulo}` : 'Ensayos de la canción'}
                compact
                minimal={minimizeAttachments}
                onDelete={handleDeleteAttachment}
                pendingActionById={pendingAttachmentActions}
              />
            ) : null}
          </div>
        ) : null}
        {sectionNavItems.length ? (
          <nav className="wpss-reading__section-nav" aria-label="Navegación de secciones">
            {sectionNavItems.map((item) => (
              <button
                key={`jump-section-${item.index}`}
                type="button"
                className={`button button-secondary wpss-reading__section-nav-pill ${
                  currentSectionIndex === item.index ? 'is-active' : ''
                }`}
                onClick={() => handleSectionJump(item.index)}
                aria-controls={`wpss-reading-section-${item.index}`}
              >
                <span>{item.label}</span>
                {item.repeat > 1 ? (
                  <span className="wpss-reading__section-nav-repeat">{`x${item.repeat}`}</span>
                ) : null}
              </button>
            ))}
          </nav>
        ) : null}
      </div>
      <div className="wpss-reading__sections-frame">
        <div className="wpss-reading__sections-scroll" ref={sectionsScrollRef}>
          <div
            className={`wpss-reading__sections ${isDoubleColumn ? 'is-double-column' : ''} ${
              isCompactViewport ? 'is-mobile-scale' : ''
            }`}
            style={{ '--wpss-reading-zoom': zoomFactor }}
          >
            {!hasVerses ? (
              <p className="wpss-empty">{wpData?.strings?.readingEmpty || 'Sin contenido para mostrar.'}</p>
            ) : (
              groups.map((group, index) => {
            const heading = getGroupHeading(group, index)
            const canPlaySection = showMidi && hasMidiInGroup(group)
            const sectionNotes = Array.isArray(group.section?.comentarios) ? group.section.comentarios : []
            const sectionAttachments = getSectionLevelAttachments(song, group.section?.id)
            const sectionStandardAttachments = sectionAttachments.filter((attachment) => !isRehearsalAttachment(attachment))
            const sectionRehearsalAttachments = filterRehearsalAttachmentsByProject(
              sectionAttachments,
              selectedRehearsalProjectId,
            )
            return (
              <section
                key={`reading-${index}`}
                id={`wpss-reading-section-${index}`}
                className={`wpss-reading__section ${
                  activePlaybackMeta?.sectionIndex === index ? 'is-playing' : ''
                } ${currentSectionIndex === index ? 'is-current' : ''}`}
                onClick={() => setActiveSectionIndex(index)}
                ref={(node) => {
                  if (node) {
                    sectionRefs.current.set(index, node)
                    return
                  }
                  sectionRefs.current.delete(index)
                }}
              >
                <div className="wpss-reading__section-header">
                  <div className="wpss-reading__section-heading">
                    {showSectionTitles ? (
                      <h4 className="wpss-section-title">
                        {heading}
                        {group.repeat > 1 ? <span className="wpss-reading__repeat">{`x${group.repeat}`}</span> : null}
                      </h4>
                    ) : (
                      <span className="wpss-reading__section-heading-fallback">{`Sección ${index + 1}`}</span>
                    )}
                  </div>
                  {canPlaySection ? (
                    <div className="wpss-reading__section-tools" aria-label="Acciones de la sección">
                      <span className="wpss-reading__section-tools-label">Acciones de la sección</span>
                      <div className="wpss-reading__section-actions">
                        <button
                          type="button"
                          className="button button-small"
                          onClick={(event) => {
                            event.preventDefault()
                            event.stopPropagation()
                            const key = `section-${index}`
                            const steps = buildSectionSteps(group, index)
                            handlePlaySteps(key, steps)
                          }}
                        >
                          {activePlaybackKey === `section-${index}` ? 'Detener sección' : 'Reproducir sección'}
                        </button>
                        {canUploadRehearsals && activeReadingToolTab === 'ensayo' && activeRehearsalProject ? (
                          <InlineMediaQuickActions
                            target={{
                              anchor_type: 'section',
                              section_id: group.section?.id || '',
                              label: heading,
                              compactRecorder: true,
                            }}
                            onUpload={handleUploadRehearsal}
                            allowedModes={['importAudio', 'recordAudio']}
                          />
                        ) : null}
                      </div>
                    </div>
                  ) : canUploadRehearsals && activeReadingToolTab === 'ensayo' && activeRehearsalProject ? (
                    <div className="wpss-reading__section-tools" aria-label="Ensayo de la sección">
                      <div className="wpss-reading__section-actions">
                        <InlineMediaQuickActions
                          target={{
                            anchor_type: 'section',
                            section_id: group.section?.id || '',
                            label: heading,
                            compactRecorder: true,
                          }}
                          onUpload={handleUploadRehearsal}
                          allowedModes={['importAudio', 'recordAudio']}
                        />
                      </div>
                    </div>
                  ) : null}
                </div>
                <div className="wpss-reading__section-body">
                  <SectionNotes
                    notes={state.readingShowNotes ? sectionNotes : []}
                    verseNotes={state.readingShowNotes ? group.versos : []}
                    sectionTitle={group.section?.nombre || heading}
                  >
                    {(noteState) => {
                      const sectionClass = noteState.sectionColors.length ? 'wpss-reading__note-scope--section' : ''
                      const sectionStyle = noteState.sectionColors.length
                        ? { '--note-color': noteState.sectionColors[0] }
                        : undefined
                      return (
                        <div className={sectionClass} style={sectionStyle}>
                          {group.notes ? <p className="wpss-reading__notes">{group.notes}</p> : null}
                          {showAttachments && sectionStandardAttachments.length ? (
                            <ReadingMediaAttachments
                              attachments={sectionStandardAttachments}
                              title="Adjuntos de la sección"
                              compact
                              minimal={minimizeAttachments}
                              onDelete={handleDeleteAttachment}
                              pendingActionById={pendingAttachmentActions}
                            />
                          ) : null}
                          {showAttachments && sectionRehearsalAttachments.length ? (
                            <ReadingMediaAttachments
                              attachments={sectionRehearsalAttachments}
                              title="Ensayos de la sección"
                              compact
                              minimal={minimizeAttachments}
                              onDelete={handleDeleteAttachment}
                              pendingActionById={pendingAttachmentActions}
                            />
                          ) : null}
                          {showMidi
                            ? renderReadingMidiClips(
                              group.section?.midi_clips,
                              bpmDefault,
                              repeatsEnabled,
                              linkedPlayback,
                            )
                          : null}
                          <ol className="wpss-reading__verses">
                            {Array.isArray(group.versos)
                              ? group.versos.map((verse, verseIndex) => (
                                  <ReadingVerse
                                    key={`verse-${index}-${verseIndex}`}
                                    verse={verse}
                                    song={song}
                                    mode={state.readingMode}
                                    defaultTempo={bpmDefault}
                                    repeatsEnabled={repeatsEnabled}
                                    linkedPlayback={linkedPlayback}
                                    showMidi={showMidi}
                                    showAttachments={showAttachments}
                                    minimizeAttachments={minimizeAttachments}
                                    showNotes={state.readingShowNotes}
                                    sectionIndex={index}
                                    verseIndex={verseIndex}
                                    activePlaybackMeta={activePlaybackMeta}
                                    noteHighlights={noteState}
                                    chordLookup={chordLookup}
                                    chordInstrument={readingInstrument}
                                    camposLookup={camposLookup}
                                    transposeSemitones={transposeSemitones}
                                    globalVerseIndex={songVerses.indexOf(verse)}
                                    activeReadingToolTab={activeReadingToolTab}
                                    canUploadRehearsals={canUploadRehearsals}
                                    activeRehearsalProject={activeRehearsalProject}
                                    selectedRehearsalProjectId={selectedRehearsalProjectId}
                                    onUploadRehearsal={handleUploadRehearsal}
                                    onDeleteAttachment={handleDeleteAttachment}
                                    pendingActionById={pendingAttachmentActions}
                                  />
                                ))
                              : null}
                          </ol>
                        </div>
                      )
                    }}
                  </SectionNotes>
                </div>
              </section>
              )
            })
          )}
          </div>
        </div>
      </div>
    </div>
  )
}

function ReadingVerse({
  song,
  verse,
  mode,
  defaultTempo,
  repeatsEnabled,
  linkedPlayback,
  showMidi,
  showAttachments,
  minimizeAttachments,
  showNotes,
  sectionIndex,
  verseIndex,
  activePlaybackMeta,
  noteHighlights,
  chordLookup,
  chordInstrument,
  camposLookup,
  transposeSemitones,
  globalVerseIndex,
  activeReadingToolTab,
  canUploadRehearsals,
  activeRehearsalProject,
  selectedRehearsalProjectId,
  onUploadRehearsal,
  onDeleteAttachment,
  pendingActionById,
}) {
  const segmentos = Array.isArray(verse.segmentos) ? verse.segmentos : []
  const instrumental = verse.instrumental ? <span className="wpss-reading__instrumental">Instrumental</span> : null
  const evento = renderEventoChip(verse.evento_armonico, segmentos.length, transposeSemitones)
  const comentario = verse.comentario ? <span className="wpss-reading__comment">{verse.comentario}</span> : null
  const metaContent = [instrumental, evento, comentario].filter(Boolean)
  const meta = metaContent.length ? <div className="wpss-reading__meta">{metaContent}</div> : null
  const verseMidi = showMidi
    ? renderReadingMidiClips(verse.midi_clips, defaultTempo, repeatsEnabled, linkedPlayback)
    : null
  const segmentMidiItems = segmentos
    .map((segmento) =>
      showMidi ? renderReadingMidiClips(segmento?.midi_clips, defaultTempo, repeatsEnabled, linkedPlayback) : null
    )
    .filter(Boolean)
  const segmentMidis = segmentMidiItems.length ? (
    <div className="wpss-reading__midis">{segmentMidiItems}</div>
  ) : null
  const verseAttachments = useMemo(
    () => getVerseLevelAttachments(song, globalVerseIndex),
    [song, globalVerseIndex],
  )
  const segmentAttachments = useMemo(
    () => getSegmentLevelAttachments(song, globalVerseIndex),
    [song, globalVerseIndex],
  )
  const segmentAudioAttachments = useMemo(
    () => segmentAttachments.filter((attachment) => attachment?.type === 'audio'),
    [segmentAttachments],
  )
  const segmentVisualAttachments = useMemo(
    () => segmentAttachments.filter((attachment) => attachment?.type !== 'audio'),
    [segmentAttachments],
  )
  const verseStandardAttachments = useMemo(
    () => verseAttachments.filter((attachment) => !isRehearsalAttachment(attachment)),
    [verseAttachments],
  )
  const verseRehearsalAttachments = useMemo(
    () => filterRehearsalAttachmentsByProject(verseAttachments, selectedRehearsalProjectId),
    [selectedRehearsalProjectId, verseAttachments],
  )
  const segmentAudioByIndex = useMemo(() => {
    const map = new Map()
    segmentAudioAttachments.forEach((attachment) => {
      const key = Number(attachment?.segment_index) || 0
      if (!map.has(key)) {
        map.set(key, [])
      }
      map.get(key).push(attachment)
    })
    return map
  }, [segmentAudioAttachments])
  const segmentAudioRefs = useRef(new Map())
  const [activeSegmentAudioId, setActiveSegmentAudioId] = useState(null)

  const isActive =
    activePlaybackMeta?.sectionIndex === sectionIndex
    && activePlaybackMeta?.verseIndex === verseIndex
  const playbackSegmentIndex = isActive ? activePlaybackMeta?.segmentIndex : null
  const verseNoteColor = showNotes ? noteHighlights?.verseColors?.get(verseIndex) || null : null
  const isVerseNoteActive = !!verseNoteColor

  const targetIndex = getValidSegmentIndex(verse.evento_armonico, segmentos.length)
  const joiners = segmentos.map((segmento) => endsWithJoiner(segmento?.texto || ''))

  useEffect(() => {
    return () => {
      segmentAudioRefs.current.forEach((audio) => {
        if (audio && typeof audio.pause === 'function') {
          audio.pause()
        }
      })
      segmentAudioRefs.current.clear()
    }
  }, [])

  useEffect(() => {
    if (showAttachments) {
      return
    }
    segmentAudioRefs.current.forEach((audio) => {
      if (audio && typeof audio.pause === 'function') {
        audio.pause()
      }
    })
    setActiveSegmentAudioId(null)
  }, [showAttachments])

  const setSegmentAudioRef = (attachmentId, node) => {
    if (!attachmentId) return
    if (node) {
      segmentAudioRefs.current.set(attachmentId, node)
      return
    }
    segmentAudioRefs.current.delete(attachmentId)
  }

  const handleToggleSegmentAudio = async (attachment) => {
    const attachmentId = attachment?.id
    if (!attachmentId) return
    const targetAudio = segmentAudioRefs.current.get(attachmentId)
    if (!targetAudio) return

    if (activeSegmentAudioId === attachmentId && !targetAudio.paused) {
      targetAudio.pause()
      setActiveSegmentAudioId(null)
      return
    }

    segmentAudioRefs.current.forEach((audio, candidateId) => {
      if (candidateId !== attachmentId && audio && typeof audio.pause === 'function') {
        audio.pause()
      }
    })

    try {
      await targetAudio.play()
      setActiveSegmentAudioId(attachmentId)
    } catch (_error) {
      setActiveSegmentAudioId(null)
    }
  }

  const parts = segmentos
    .map((segmento, index) => {
      const chordToken = segmento?.acorde || ''
      const holdToken = String(chordToken || '').trim().toLowerCase()
      const isNullHold = holdToken === 'null'
      const isStillHold = holdToken === 'still'
      const acordeBase = getChordDisplayValue(chordToken)
      const acordeValue = transposeChordSymbol(acordeBase, transposeSemitones)
      const isHoldChord = isHoldChordToken(chordToken)
      const chordLabel = isNullHold ? '[Silencio]' : isStillHold ? '' : acordeValue ? `[${acordeValue}]` : ''
      const chordLookupToken = acordeValue || ''
      const chordSourceToken = acordeBase || ''
      const texto = segmento.texto || ''
      const textPlain = stripHtml(texto)
      const classes = ['wpss-reading__segment']
      if (isHoldChord) {
        classes.push('is-hold-chord')
      }
      if (index > 0 && joiners[index - 1]) {
        classes.push('is-joined-prev')
      }
      if (joiners[index] && index < segmentos.length - 1) {
        classes.push('is-joined-next')
      }
      if (targetIndex !== null && targetIndex === index) {
        classes.push('is-event-target')
      }
      if (playbackSegmentIndex !== null && playbackSegmentIndex === index) {
        classes.push('is-playing')
      }
      const segmentNoteColor = showNotes
        ? noteHighlights?.segmentColors?.get(`${verseIndex}:${index}`) || null
        : null
      if (segmentNoteColor) {
        classes.push('has-note')
        classes.push('is-note-active')
      }
      return {
        key: `segment-${index}`,
        index,
        classes: classes.join(' '),
        chordLabel,
        chordLookupToken,
        chordSourceToken,
        isHoldChord,
        texto,
        textPlain,
        joiner: joiners[index] && index < segmentos.length - 1,
        noteColor: segmentNoteColor || '#3b82f6',
        audioAttachments: segmentAudioByIndex.get(index) || [],
      }
    })
    .filter(Boolean)

  const segmentAudioElements = segmentAudioAttachments.length ? (
    <div className="wpss-reading__segment-audio-cache" aria-hidden="true">
      {segmentAudioAttachments.map((attachment) => (
        <audio
          key={`segment-audio-${attachment.id}`}
          ref={(node) => setSegmentAudioRef(attachment.id, node)}
          preload="none"
          src={attachment?.stream_url || ''}
          onPause={() => {
            if (activeSegmentAudioId === attachment.id) {
              setActiveSegmentAudioId(null)
            }
          }}
          onEnded={() => {
            if (activeSegmentAudioId === attachment.id) {
              setActiveSegmentAudioId(null)
            }
          }}
        />
      ))}
    </div>
  ) : null

  if (mode === 'stacked') {
    const cells = buildStackedCells(parts)
    return (
      <li className={isActive ? 'is-playing' : ''}>
        <div
          className={`wpss-reading__verse-body ${isVerseNoteActive ? 'is-note-active' : ''}`}
          style={isVerseNoteActive ? { '--note-color': verseNoteColor } : undefined}
        >
          <div className="wpss-reading__stack-shell">
            <pre className="wpss-reading__stack">
              <span className="wpss-reading__stack-chords">
                {cells.map((cell) => (
                  <span
                    key={`chord-${cell.key}`}
                    className={cell.classes}
                    style={cell.hasNote ? { '--note-color': cell.noteColor } : undefined}
                  >
                    {renderChordLabel(cell, chordLookup, chordInstrument, camposLookup, true, transposeSemitones)}
                    {showAttachments && cell.audioAttachments?.length ? (
                      <SegmentInlineAudioButtons
                        attachments={cell.audioAttachments}
                        activeAttachmentId={activeSegmentAudioId}
                        onToggle={handleToggleSegmentAudio}
                      />
                    ) : null}
                    {cell.chordSpacer ? <span className="wpss-reading__stack-spacer">{cell.chordSpacer}</span> : null}
                  </span>
                ))}
              </span>
              {'\n'}
              <span className="wpss-reading__stack-lyrics">
                {cells.map((cell) => (
                  <span
                    key={`lyric-${cell.key}`}
                    className={cell.classes}
                    style={cell.hasNote ? { '--note-color': cell.noteColor } : undefined}
                  >
                    {cell.textPlain}
                    {cell.textSpacer ? <span className="wpss-reading__stack-spacer">{cell.textSpacer}</span> : null}
                  </span>
                ))}
              </span>
            </pre>
          </div>
          {segmentAudioElements}
          {meta}
          {canUploadRehearsals && activeReadingToolTab === 'ensayo' && activeRehearsalProject ? (
            <div className="wpss-reading__verse-tools">
              <InlineMediaQuickActions
                target={{
                  anchor_type: 'verse',
                  verse_index: globalVerseIndex,
                  label: `verso ${globalVerseIndex + 1}`,
                  compactRecorder: true,
                }}
                onUpload={onUploadRehearsal}
                allowedModes={['importAudio', 'recordAudio']}
              />
            </div>
          ) : null}
          {verseMidi}
          {segmentMidis}
          {showAttachments && segmentVisualAttachments.length ? (
            <ReadingMediaAttachments
              attachments={segmentVisualAttachments}
              title="Fotos por fragmento"
              compact
              minimal={minimizeAttachments}
              groupedBySegment
              onDelete={onDeleteAttachment}
              pendingActionById={pendingActionById}
            />
          ) : null}
          {showAttachments && verseStandardAttachments.length ? (
            <ReadingMediaAttachments
              attachments={verseStandardAttachments}
              title="Adjuntos del verso"
              compact
              minimal={minimizeAttachments}
              onDelete={onDeleteAttachment}
              pendingActionById={pendingActionById}
            />
          ) : null}
          {showAttachments && verseRehearsalAttachments.length ? (
            <ReadingMediaAttachments
              attachments={verseRehearsalAttachments}
              title="Ensayos del verso"
              compact
              minimal={minimizeAttachments}
              onDelete={onDeleteAttachment}
              pendingActionById={pendingActionById}
            />
          ) : null}
        </div>
      </li>
    )
  }

  return (
    <li className={isActive ? 'is-playing' : ''}>
      <div
        className={`wpss-reading__verse-body ${isVerseNoteActive ? 'is-note-active' : ''}`}
        style={isVerseNoteActive ? { '--note-color': verseNoteColor } : undefined}
      >
        <div className="wpss-reading__line">
          {parts.map((part, index) => (
            <span
              key={part.key}
              className={part.classes}
              style={part.classes.includes('has-note') ? { '--note-color': part.noteColor } : undefined}
            >
              {renderChordLabel(part, chordLookup, chordInstrument, camposLookup, false, transposeSemitones)}
              {showAttachments && part.audioAttachments?.length ? (
                <SegmentInlineAudioButtons
                  attachments={part.audioAttachments}
                  activeAttachmentId={activeSegmentAudioId}
                  onToggle={handleToggleSegmentAudio}
                />
              ) : null}
              {part.texto ? <span dangerouslySetInnerHTML={{ __html: part.texto }} /> : null}
              {!part.joiner && index < parts.length - 1 ? <span className="wpss-reading__gap"> </span> : null}
            </span>
          ))}
        </div>
        {segmentAudioElements}
        {meta}
        {canUploadRehearsals && activeReadingToolTab === 'ensayo' && activeRehearsalProject ? (
          <div className="wpss-reading__verse-tools">
            <InlineMediaQuickActions
              target={{
                anchor_type: 'verse',
                verse_index: globalVerseIndex,
                label: `verso ${globalVerseIndex + 1}`,
                compactRecorder: true,
              }}
              onUpload={onUploadRehearsal}
              allowedModes={['importAudio', 'recordAudio']}
            />
          </div>
        ) : null}
        {verseMidi}
        {segmentMidis}
        {showAttachments && segmentVisualAttachments.length ? (
          <ReadingMediaAttachments
            attachments={segmentVisualAttachments}
            title="Fotos por fragmento"
            compact
            minimal={minimizeAttachments}
            groupedBySegment
            onDelete={onDeleteAttachment}
            pendingActionById={pendingActionById}
          />
        ) : null}
        {showAttachments && verseStandardAttachments.length ? (
          <ReadingMediaAttachments
            attachments={verseStandardAttachments}
            title="Adjuntos del verso"
            compact
            minimal={minimizeAttachments}
            onDelete={onDeleteAttachment}
            pendingActionById={pendingActionById}
          />
        ) : null}
        {showAttachments && verseRehearsalAttachments.length ? (
          <ReadingMediaAttachments
            attachments={verseRehearsalAttachments}
            title="Ensayos del verso"
            compact
            minimal={minimizeAttachments}
            onDelete={onDeleteAttachment}
            pendingActionById={pendingActionById}
          />
        ) : null}
      </div>
    </li>
  )
}

function SegmentInlineAudioButtons({ attachments = [], activeAttachmentId = null, onToggle }) {
  if (!Array.isArray(attachments) || !attachments.length) {
    return null
  }

  return (
    <span className="wpss-reading__segment-audio-buttons">
      {attachments.map((attachment, index) => {
        const attachmentId = attachment?.id
        const isActive = attachmentId && activeAttachmentId === attachmentId
        const label = attachment?.title || attachment?.file_name || `Audio ${index + 1}`
        return (
          <button
            key={`segment-audio-button-${attachmentId || index}`}
            type="button"
            className={`wpss-reading__segment-audio-button ${isActive ? 'is-active' : ''}`}
            onClick={(event) => {
              event.preventDefault()
              event.stopPropagation()
              onToggle?.(attachment)
            }}
            aria-label={`${isActive ? 'Pausar' : 'Reproducir'} ${label}`}
            title={label}
          >
            {isActive ? '❚❚' : '▶'}
          </button>
        )
      })}
    </span>
  )
}

function resolveChordForTooltip(part, chordLookup) {
  if (!part || !chordLookup) {
    return null
  }
  const chordLabelToken =
    typeof part.chordLabel === 'string' ? part.chordLabel.replace(/^\[|\]$/g, '') : ''
  return (
    getChordFromLookup(part.chordLookupToken, chordLookup)
    || getChordFromLookup(part.chordSourceToken, chordLookup)
    || getChordFromLookup(chordLabelToken, chordLookup)
  )
}

function renderChordLabel(part, chordLookup, chordInstrument, camposLookup, isStacked, transposeSemitones = 0) {
  if (!part?.chordLabel) {
    return null
  }
  const className = isStacked ? 'wpss-reading__stack-chord' : ''
  if (part.isHoldChord) {
    return (
      <span className={`wpss-reading__chord wpss-reading__chord--silence ${className}`.trim()}>
        {part.chordLabel}
      </span>
    )
  }
  return (
    <ChordHover
      label={part?.chordLabel}
      chord={resolveChordForTooltip(part, chordLookup)}
      instrument={chordInstrument}
      camposLookup={camposLookup}
      displayToken={part?.chordLookupToken || ''}
      transposeSemitones={transposeSemitones}
      className={className}
    />
  )
}

function buildStackedCells(parts) {
  if (!Array.isArray(parts) || !parts.length) {
    return []
  }
  return parts.map((part, index) => {
    const chordLabel = part.chordLabel || ''
    const textPlain = part.textPlain || ''
    const width = Math.max(chordLabel.length, textPlain.length)
    const padding = part.joiner || index === parts.length - 1 ? width : width + 2
    return {
      ...part,
      chordSpacer: padding > chordLabel.length ? ' '.repeat(padding - chordLabel.length) : '',
      textSpacer: padding > textPlain.length ? ' '.repeat(padding - textPlain.length) : '',
      hasNote: part.classes.includes('has-note'),
    }
  })
}

function ChordHover({
  label,
  chord,
  instrument,
  camposLookup,
  displayToken = '',
  transposeSemitones = 0,
  className = '',
}) {
  if (!label) {
    return null
  }

  const rootRef = useRef(null)
  const popoverRef = useRef(null)
  const [isOpen, setIsOpen] = useState(false)
  const [popoverStyle, setPopoverStyle] = useState(null)
  const shapes = Array.isArray(chord?.diagrams?.[instrument]) ? chord.diagrams[instrument] : []
  const hasChord = !!chord
  const canOpenPopover = hasChord
  const syncPopoverPosition = useCallback(() => {
    const root = rootRef.current
    if (!root) {
      return
    }
    const rect = root.getBoundingClientRect()
    setPopoverStyle({
      left: Math.max(12, rect.left),
      top: Math.max(12, rect.top - 12),
      transform: 'translateY(-100%)',
    })
  }, [])

  useEffect(() => {
    if (!isOpen) {
      return undefined
    }

    syncPopoverPosition()

    const handlePointerDown = (event) => {
      const root = rootRef.current
      const popover = popoverRef.current
      if (!root) {
        return
      }
      if (root.contains(event.target) || (popover && popover.contains(event.target))) {
        return
      }
      setIsOpen(false)
    }

    const handleKeyDown = (event) => {
      if (event.key === 'Escape') {
        setIsOpen(false)
      }
    }

    const handleWindowChange = () => {
      syncPopoverPosition()
    }

    document.addEventListener('pointerdown', handlePointerDown)
    document.addEventListener('keydown', handleKeyDown)
    window.addEventListener('resize', handleWindowChange)
    window.addEventListener('scroll', handleWindowChange, true)

    return () => {
      document.removeEventListener('pointerdown', handlePointerDown)
      document.removeEventListener('keydown', handleKeyDown)
      window.removeEventListener('resize', handleWindowChange)
      window.removeEventListener('scroll', handleWindowChange, true)
    }
  }, [isOpen, syncPopoverPosition])

  const tooltip = hasChord && isOpen && typeof document !== 'undefined' && popoverStyle
    ? createPortal(
      <span
        ref={popoverRef}
        className="wpss-chord-tooltip wpss-chord-tooltip--popover"
        style={popoverStyle}
      >
        <ChordTooltip
          chord={chord}
          instrument={instrument}
          camposLookup={camposLookup}
          displayToken={displayToken}
          transposeSemitones={transposeSemitones}
        />
      </span>,
      document.body
    )
    : null

  return (
    <span
      ref={rootRef}
      className={`wpss-reading__chord wpss-chord-hover ${className} ${hasChord ? 'has-data' : ''} ${isOpen ? 'is-open' : ''}`.trim()}
    >
      {canOpenPopover ? (
        <button
          type="button"
          className="wpss-chord-hover__trigger"
          aria-expanded={isOpen}
          onMouseEnter={() => {
            syncPopoverPosition()
            setIsOpen(true)
          }}
          onFocus={() => {
            syncPopoverPosition()
            setIsOpen(true)
          }}
          onClick={(event) => {
            event.preventDefault()
            event.stopPropagation()
            syncPopoverPosition()
            setIsOpen(true)
          }}
        >
          {label}
        </button>
      ) : (
        <span className="wpss-chord-hover__label">{label}</span>
      )}
      {tooltip}
    </span>
  )
}

function PrintIcon() {
  return (
    <svg
      className="wpss-reading__print-icon"
      width="16"
      height="16"
      viewBox="0 0 24 24"
      aria-hidden="true"
      focusable="false"
    >
      <path
        fill="currentColor"
        d="M6 7V3h12v4h-2V5H8v2H6zm1 3h10a3 3 0 0 1 3 3v5h-3v3H7v-3H4v-5a3 3 0 0 1 3-3zm2 9h6v-4H9v4zm9-3v-3a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1v3h1v-3h10v3h1z"
      />
    </svg>
  )
}

function ChordTooltip({ chord, instrument, camposLookup, displayToken = '', transposeSemitones = 0 }) {
  if (!chord) {
    return null
  }
  const instrumentDefinition = getChordInstrumentDefinition(instrument)
  const isWindInstrument = instrumentDefinition?.renderer === 'wind'
  const [activeShapeIndex, setActiveShapeIndex] = useState(0)
  const aliases = Array.isArray(chord.aliases) ? chord.aliases : []
  const windNotes = isWindInstrument
    ? getWindChordNoteCandidates(chord, transposeSemitones, displayToken)
    : []
  const notes = isWindInstrument ? windNotes : Array.isArray(chord.notes) ? chord.notes : []
  const voicing = isWindInstrument ? windNotes : Array.isArray(chord.voicing) ? chord.voicing : []
  const enarmonics = Array.isArray(chord.enarmonics) ? chord.enarmonics : []
  const relations = Array.isArray(chord.relations) ? chord.relations : []
  const root = isWindInstrument
    ? transposePitchToken(chord.root_base || '', transposeSemitones, String(chord.root_base || '').includes('b')) || displayToken || chord.root_base || ''
    : chord.root_base || ''
  const voices = Number.isInteger(chord.voices) && chord.voices > 0 ? chord.voices : null
  const quality =
    chord.quality === 'other' ? chord.quality_other || 'Otro' : chord.quality || ''
  const paradigm = chord.paradigm || ''
  const evolution = Array.isArray(chord.evolution) ? chord.evolution : []
  const diagrams = chord?.diagrams?.[instrument]
  const shapes = isWindInstrument
    ? buildAutoWindShapes(chord, {
      semitoneShift: transposeSemitones,
      fallbackToken: displayToken,
      existingShapes: Array.isArray(diagrams)
        ? diagrams.map((shape) => ({ label: shape?.label || '' }))
        : [],
    })
    : Array.isArray(diagrams) ? diagrams : []
  const instrumentLabel = getChordInstrumentLabel(instrument)
  const relationLabels = relations
    .map((relation) => {
      const campoLabel =
        relation.campo && camposLookup && camposLookup.get(relation.campo)
          ? camposLookup.get(relation.campo)
          : relation.campo || ''
      const grado = formatRomanCase(relation.grado || '', relation.case || 'original')
      if (!campoLabel && !grado) {
        return ''
      }
      return `${campoLabel || 'Campo'}: ${grado || '—'}`
    })
    .filter(Boolean)
  const transposedVoiceSummary = isWindInstrument && notes.length
    ? notes.join(', ')
    : ''
  const activeWindShape = isWindInstrument && shapes.length
    ? shapes[((activeShapeIndex % shapes.length) + shapes.length) % shapes.length]
    : null

  useEffect(() => {
    setActiveShapeIndex(0)
  }, [instrument, chord?.id, displayToken, shapes.length])

  return (
    <>
      <strong className="wpss-chord-tooltip__title">
        {isWindInstrument && displayToken ? displayToken : chord.name || chord.id || 'Acorde'}
      </strong>
      {root ? <span className="wpss-chord-tooltip__meta">Root: {root}</span> : null}
      {quality ? <span className="wpss-chord-tooltip__meta">Quality: {quality}</span> : null}
      {voices ? <span className="wpss-chord-tooltip__meta">Voces: {voices}</span> : null}
      {aliases.length ? (
        <span className="wpss-chord-tooltip__meta">Alias: {aliases.join(', ')}</span>
      ) : null}
      {evolution.length ? (
        <span className="wpss-chord-tooltip__meta">Evolución: {evolution.join(' → ')}</span>
      ) : null}
      {enarmonics.length ? (
        <span className="wpss-chord-tooltip__meta">Enarmónicos: {enarmonics.join(', ')}</span>
      ) : null}
      {notes.length && !isWindInstrument ? (
        <span className="wpss-chord-tooltip__meta">Notas: {notes.join(', ')}</span>
      ) : null}
      {voicing.length && !isWindInstrument ? (
        <span className="wpss-chord-tooltip__meta">Voicing: {voicing.join(', ')}</span>
      ) : null}
      {transposedVoiceSummary ? (
        <span className="wpss-chord-tooltip__meta">Voces sax: {transposedVoiceSummary}</span>
      ) : null}
      {paradigm ? (
        <span className="wpss-chord-tooltip__meta">Paradigma: {paradigm}</span>
      ) : null}
      {relationLabels.length ? (
        <span className="wpss-chord-tooltip__meta">
          Campos: {relationLabels.join(' · ')}
        </span>
      ) : null}
      <span className="wpss-chord-tooltip__meta">Instrumento: {instrumentLabel}</span>
      {isWindInstrument && shapes.length > 1 ? (
        <span className="wpss-chord-tooltip__carousel">
          <button
            type="button"
            className="wpss-chord-tooltip__nav"
            onClick={() => setActiveShapeIndex((current) => current - 1)}
            aria-label="Voz anterior"
          >
            ‹
          </button>
          <span className="wpss-chord-tooltip__nav-status">
            Voz {((activeShapeIndex % shapes.length) + shapes.length) % shapes.length + 1} / {shapes.length}
          </span>
          <button
            type="button"
            className="wpss-chord-tooltip__nav"
            onClick={() => setActiveShapeIndex((current) => current + 1)}
            aria-label="Siguiente voz"
          >
            ›
          </button>
        </span>
      ) : null}
      {shapes.length ? (
        <span className="wpss-chord-tooltip__diagrams">
          {isWindInstrument && activeWindShape ? (
            <ChordDiagram shape={activeWindShape} instrument={instrument} />
          ) : (
            shapes.map((shape, index) => (
              <ChordDiagram key={`diagram-${index}`} shape={shape} instrument={instrument} />
            ))
          )}
        </span>
      ) : (
        <span className="wpss-chord-tooltip__meta">Sin diagrama para este instrumento.</span>
      )}
    </>
  )
}

function formatRomanCase(value, mode) {
  if (!value) {
    return ''
  }
  const text = String(value)
  if (mode === 'upper') {
    return text.replace(/[ivxlcdm]/gi, (match) => match.toUpperCase())
  }
  if (mode === 'lower') {
    return text.replace(/[ivxlcdm]/gi, (match) => match.toLowerCase())
  }
  return text
}

function SectionNotes({ notes, verseNotes, sectionTitle = '', children }) {
  const flattened = useMemo(() => {
    const sectionNotes = Array.isArray(notes) ? notes : []
    const verses = Array.isArray(verseNotes) ? verseNotes : []
    const sectionName = sectionTitle || 'Sección'
    const toNote = (note, fallbackTitle, extra = {}) => {
      const safeNote = note && typeof note === 'object' ? note : {}
      const title = safeNote.titulo ? String(safeNote.titulo).slice(0, 64) : fallbackTitle
      const uidBase = safeNote.id
        ? String(safeNote.id)
        : `${extra.scope || 'nota'}-${extra.verseIndex ?? 'x'}-${extra.segmentIndex ?? 'x'}`
      return {
        ...safeNote,
        ...extra,
        uid: `${uidBase}-${extra.scopeType || 'scope'}-${extra.verseIndex ?? 'x'}-${extra.segmentIndex ?? 'x'}-${extra.order ?? 0}`,
        titulo: title,
      }
    }
    const verseItems = verses.flatMap((verse, verseIndex) => {
      const verseComments = Array.isArray(verse?.comentarios) ? verse.comentarios : []
      const verseTitle = verse?.nombre
        ? String(verse.nombre)
        : verse?.instrumental
          ? `Instrumental ${verseIndex + 1}`
          : `Verso ${verseIndex + 1}`
      const segmentComments = Array.isArray(verse?.segmentos)
        ? verse.segmentos.flatMap((segment, segmentIndex) =>
            Array.isArray(segment?.comentarios)
              ? segment.comentarios.map((note, order) =>
                  toNote(note, sectionName, {
                    scope: `Segmento ${segmentIndex + 1}`,
                    scopeType: 'segment',
                    verseIndex,
                    segmentIndex,
                    order,
                  }),
                )
              : [],
          )
        : []
      return [
        ...verseComments.map((note, order) =>
          toNote(note, sectionName, {
            scope: `Verso (${verseTitle})`,
            scopeType: 'verse',
            verseIndex,
            order,
          }),
        ),
        ...segmentComments,
      ]
    })
    return [
      ...sectionNotes.map((note, order) =>
        toNote(note, sectionName, {
          scope: `Sección (${sectionName})`,
          scopeType: 'section',
          order,
        }),
      ),
      ...verseItems,
    ]
  }, [notes, sectionTitle, verseNotes])

  const [hiddenNoteIds, setHiddenNoteIds] = useState(() => new Set())
  useEffect(() => {
    if (!flattened.length) {
      setHiddenNoteIds(new Set())
      return
    }
    setHiddenNoteIds((previous) => {
      const valid = new Set(flattened.map((note) => note.uid))
      const next = new Set()
      previous.forEach((id) => {
        if (valid.has(id)) {
          next.add(id)
        }
      })
      return next
    })
  }, [flattened])

  const visibleNotes = flattened.filter((note) => !hiddenNoteIds.has(note.uid))
  const frameColor = visibleNotes[0]?.color || flattened[0]?.color || '#3b82f6'
  const noteState = useMemo(() => {
    const sectionColors = []
    const verseColors = new Map()
    const segmentColors = new Map()
    visibleNotes.forEach((note) => {
      const color = note.color || '#3b82f6'
      if (note.scopeType === 'section') {
        sectionColors.push(color)
        return
      }
      if (note.scopeType === 'verse') {
        if (!verseColors.has(note.verseIndex)) {
          verseColors.set(note.verseIndex, color)
        }
        return
      }
      if (note.scopeType === 'segment') {
        const key = `${note.verseIndex}:${note.segmentIndex}`
        if (!segmentColors.has(key)) {
          segmentColors.set(key, color)
        }
      }
    })
    return { sectionColors, verseColors, segmentColors }
  }, [visibleNotes])

  if (!flattened.length) {
    const emptyState = { sectionColors: [], verseColors: new Map(), segmentColors: new Map() }
    return typeof children === 'function' ? children(emptyState) : children
  }

  return (
    <div className="wpss-reading__note-frame has-notes" style={{ '--note-color': frameColor }}>
      <NoteTabs
        notes={flattened}
        hiddenNoteIds={hiddenNoteIds}
        onToggle={(uid) =>
          setHiddenNoteIds((previous) => {
            const next = new Set(previous)
            if (next.has(uid)) {
              next.delete(uid)
            } else {
              next.add(uid)
            }
            return next
          })
        }
      />
      {typeof children === 'function' ? children(noteState) : children}
      <div className="wpss-reading__note-cards">
        {visibleNotes.length ? (
          visibleNotes.map((note) => <NoteCard key={note.uid} note={note} />)
        ) : (
          <p className="wpss-empty">Todas las notas están ocultas.</p>
        )}
      </div>
    </div>
  )
}

function NoteTabs({ notes, hiddenNoteIds, onToggle }) {
  return (
    <div className="wpss-reading__note-tabs">
      {notes.map((note, index) => {
        const label = note.titulo || note.scope || `Nota ${index + 1}`
        const isVisible = !hiddenNoteIds.has(note.uid)
        return (
          <button
            key={note.uid}
            type="button"
            className={`wpss-reading__note-tab ${isVisible ? 'is-active' : ''}`}
            onClick={() => onToggle(note.uid)}
            style={{ '--note-color': note.color || '#3b82f6' }}
            title={note.scope || ''}
          >
            {label}
          </button>
        )
      })}
    </div>
  )
}

function NoteCard({ note }) {
  if (!note) return null
  return (
    <div className="wpss-reading__note-card" style={{ '--note-color': note.color || '#3b82f6' }}>
      <div className="wpss-reading__note-card-head">
        <strong>{note.titulo || 'Nota'}</strong>
        <span>{note.scope || 'Nota'}</span>
      </div>
      <span dangerouslySetInnerHTML={{ __html: note.texto || '' }} />
    </div>
  )
}

function renderEventoChip(evento, segmentCount, transposeSemitones = 0) {
  if (!evento || !evento.tipo) {
    return null
  }

  let segmentBadge = null
  if (Object.prototype.hasOwnProperty.call(evento, 'segment_index')) {
    const index = getValidSegmentIndex(evento, segmentCount)
    if (index !== null) {
      segmentBadge = <span className="wpss-event-chip__badge">{`Segmento ${index + 1}`}</span>
    }
  }

  if (evento.tipo === 'modulacion') {
    const destino = [
      evento.tonica_destino ? transposeChordSymbol(evento.tonica_destino, transposeSemitones) : '',
      evento.campo_armonico_destino || '',
    ]
      .filter(Boolean)
      .join(' ')
    return (
      <span className="wpss-event-chip">
        {`Modulación → ${destino || '—'}`} {segmentBadge}
      </span>
    )
  }

  if (evento.tipo === 'prestamo') {
    const origen = [
      evento.tonica_origen ? transposeChordSymbol(evento.tonica_origen, transposeSemitones) : '',
      evento.campo_armonico_origen || '',
    ]
      .filter(Boolean)
      .join(' ')
    return (
      <span className="wpss-event-chip">
        {`Préstamo ← ${origen || '—'}`} {segmentBadge}
      </span>
    )
  }

  return null
}

function getGroupHeading(group, index) {
  if (!group) {
    return getDefaultSectionName(index)
  }
  const base = group.title || group.section?.nombre || getDefaultSectionName(index)
  return group.variant ? `${base} (${group.variant})` : base
}

function groupVersesBySection(song) {
  const sections = Array.isArray(song.secciones) ? song.secciones : []
  const verses = Array.isArray(song.versos) ? song.versos : []

  if (!sections.length) {
    return verses.length ? [{ section: { id: '', nombre: getDefaultSectionName(0) }, versos: verses }] : []
  }

  const map = new Map()
  sections.forEach((section) => {
    map.set(section.id, [])
  })

  const fallback = sections[0]?.id || ''
  verses.forEach((verse) => {
    const sectionId = map.has(verse.section_id) ? verse.section_id : fallback
    if (!map.has(sectionId)) {
      map.set(sectionId, [])
    }
    map.get(sectionId).push(verse)
  })

  const groups = []
  sections.forEach((section) => {
    const groupVerses = map.get(section.id) || []
    if (groupVerses.length) {
      groups.push({ section, versos: groupVerses })
    }
  })

  return groups
}

function groupVersesByStructure(song) {
  const sections = Array.isArray(song.secciones) ? song.secciones : []
  const estructura = Array.isArray(song.estructura) ? song.estructura : []
  const verses = Array.isArray(song.versos) ? song.versos : []

  if (!sections.length) {
    return []
  }

  if (!estructura.length) {
    return groupVersesBySection(song).map((group, index) => ({
      title: group.section?.nombre || getDefaultSectionName(index),
      variant: '',
      notes: '',
      versos: group.versos,
      section: group.section,
      repeat: 1,
    }))
  }

  const sectionMap = new Map()
  sections.forEach((section, index) => {
    sectionMap.set(section.id, { ...section, index })
  })

  const fallback = sections[0]
  const versesBySection = new Map()

  verses.forEach((verse) => {
    const sectionId = sectionMap.has(verse.section_id) ? verse.section_id : fallback.id
    if (!versesBySection.has(sectionId)) {
      versesBySection.set(sectionId, [])
    }
    versesBySection.get(sectionId).push(verse)
  })

  const expanded = []

  estructura.forEach((call, index) => {
    const info = sectionMap.get(call.ref) || { ...fallback, index: 0 }
    const baseTitle = info?.nombre || getDefaultSectionName('index' in info ? info.index : index)
    const variant = call?.variante ? String(call.variante).slice(0, 16) : ''
    const notes = call?.notas ? String(call.notas).slice(0, 128) : ''
    const versosSeccion = versesBySection.get(call.ref) || []
    const repeatRaw = parseInt(call?.repeat, 10)
    const repeat = Number.isInteger(repeatRaw) && repeatRaw > 0 ? Math.min(repeatRaw, 16) : 1

    expanded.push({
      title: baseTitle,
      variant,
      notes,
      versos: versosSeccion,
      section: info,
      repeat,
    })
  })

  return expanded
}

function renderReadingMidiClips(clips, defaultTempo, repeatsEnabled, linkedPlayback) {
  if (!Array.isArray(clips) || !clips.length) {
    return null
  }

  return (
    <div className="wpss-reading__midi">
      <MidiClipList
        clips={clips}
        readOnly
        showOnlyActiveRows
        defaultTempo={defaultTempo}
        repeatsEnabled={repeatsEnabled}
        linkedPlayback={linkedPlayback}
      />
    </div>
  )
}

 
