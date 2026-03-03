import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'
import {
  formatSegmentsForStackedCells,
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
import { createEmptySegment, createEmptyVerse, createSection } from '../state.js'
import StructurePanel from './StructurePanel.jsx'
import VersesPanel from './VersesPanel.jsx'
import SectionsPanel from './SectionsPanel.jsx'
import CommentEditor from './CommentEditor.jsx'
import MidiClipList from './MidiClipList.jsx'

const AUTOSAVE_DELAY = 800
const MOBILE_EDITOR_PREVIEW_QUERY = '(max-width: 840px)'
const PREVIEW_SCALE_LEVELS = [10, 12, 15, 18, 22, 27, 33, 40, 50, 63, 79, 100]

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

export default function Editor({ onShowList }) {
  const { state, dispatch, api, wpData } = useAppState()
  const [editingSong, setEditingSong] = useState(state.editingSong)
  const [selectedSectionId, setSelectedSectionId] = useState(() => state.ui?.selectedSectionId ?? null)
  const [isSidebarCollapsed, setIsSidebarCollapsed] = useState(false)
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
  const editingSongRef = useRef(state.editingSong)
  const layoutRef = useRef(null)
  const sidebarRef = useRef(null)
  const previewScrollRef = useRef(null)
  const previewSectionRefs = useRef(new Map())
  const autosaveRef = useRef(null)
  const selectionRef = useRef({ verse: null, segment: null, start: null, end: null, element: null })
  const lastSilentErrorRef = useRef(null)
  const previewScaleRef = useRef(previewScale)
  const previewPinchRef = useRef({ active: false, startDistance: 0, startScale: 100 })
  const isAdmin = !!wpData?.isAdmin
  const currentUserId = wpData?.currentUserId || 0
  const preferCompactMidiRows = !!wpData?.isPublicReader
  const midiRangePresets = Array.isArray(wpData?.midiRanges) ? wpData.midiRanges : []
  const midiRangeDefault = wpData?.midiRangeDefault ? String(wpData.midiRangeDefault) : ''
  const lockMidiRange = !!wpData?.isPublicReader
  const canDeleteSong =
    !!editingSong?.id &&
    (isAdmin || Number(editingSong?.autor_id) === Number(currentUserId))

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
    setEditingSong(state.editingSong)
  }, [state.editingSong])

  useEffect(() => {
    editingSongRef.current = editingSong
  }, [editingSong])


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
      editingSongRef.current = next
      return next
    })
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

    if (!currentSong.tonica.trim()) {
      warnSilent(wpData?.strings?.tonicaRequired || 'La tónica es obligatoria.')
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
        const normalizedSong = {
          ...editingSong,
          id: body.id,
          bpm: bpmDefault,
          secciones,
          estructura,
          estructuraPersonalizada: true,
        }

        const shouldSync = !silent || !editingSong.id || body.id !== editingSong.id

        if (shouldSync) {
          dispatch({
            type: 'SET_STATE',
            payload: {
              selectedSongId: body.id,
              editingSong: normalizedSong,
              feedback: !silent
                ? { message: wpData?.strings?.saved || 'Cambios guardados.', type: 'success' }
                : null,
              error: null,
            },
          })
          setEditingSong(normalizedSong)
        } else {
          dispatch({
            type: 'SET_STATE',
            payload: {
              selectedSongId: body.id,
              feedback: null,
              error: null,
            },
          })
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

  const updateSegmentSelection = (verseIndex, segmentIndex, start, end, element) => {
    selectionRef.current = {
      verse: verseIndex,
      segment: segmentIndex,
      start,
      end,
      element,
    }
  }

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
    const texto = segment.texto || ''

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
    persistSelectedSection(id)
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

  const renderStackedPreview = (segmentos, key) => {
    if (!Array.isArray(segmentos) || !segmentos.length) {
      return (
        <li key={key} className="wpss-section-preview__verse is-empty">
          <span>Verso vacío</span>
        </li>
      )
    }
    const lines = formatSegmentsForStackedCells(segmentos)
    return (
      <li key={key} className="wpss-section-preview__verse">
        <pre className="wpss-reading__stack">
          <span className="wpss-reading__stack-chords">
            {lines.chords.map((cell, cellIndex) => (
              <span key={`preview-chord-${key}-${cellIndex}`}>
                {cell.text ? <span className="wpss-reading__stack-chord">{cell.text}</span> : null}
                {cell.spacer ? <span className="wpss-reading__stack-spacer">{cell.spacer}</span> : null}
              </span>
            ))}
          </span>
          {'\n'}
          <span>{lines.lyrics}</span>
        </pre>
      </li>
    )
  }

  const handleSelectVerse = (sectionId, index) => {
    persistSelectedSection(sectionId)
    setSelectedVerseIndexes(new Set([index]))
    setExpandedSectionId(sectionId)
    setNavLevel('verses')
  }

  const clearVerseSelection = () => {
    setSelectedVerseIndexes(new Set())
    setExpandedSectionId(null)
  }

  const enterVerseLevel = (sectionId) => {
    persistSelectedSection(sectionId)
    clearVerseSelection()
    setExpandedSectionId(sectionId)
    setNavLevel('verses')
  }

  const enterSectionManage = (sectionId) => {
    persistSelectedSection(sectionId)
    clearVerseSelection()
    setNavLevel('manage')
  }

  const backToSections = () => {
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
    if (insertAfter >= 0) {
      nextVerses.splice(insertAfter + 1, 0, newVerse)
    } else {
      nextVerses.push(newVerse)
    }
    normalizeVerseOrder(nextVerses)
    const nextSong = { ...editingSong, versos: nextVerses }
    syncLegacyFromSections(nextSong, Array.isArray(nextSong.secciones) ? nextSong.secciones : [])
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

  const moveVerseInSection = (fromIndex, toIndex) => {
    if (fromIndex === toIndex || fromIndex < 0 || toIndex < 0) {
      return false
    }

    const verses = Array.isArray(editingSong.versos) ? editingSong.versos : []
    if (fromIndex >= verses.length || toIndex >= verses.length) {
      return false
    }

    const fromVerse = verses[fromIndex]
    const toVerse = verses[toIndex]
    if (!fromVerse || !toVerse) {
      return false
    }

    const sectionId = fromVerse.section_id || ''
    if ((toVerse.section_id || '') !== sectionId) {
      return false
    }

    const sectionIndexes = verses
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

    const nextVerses = [...verses]
    const targetSlots = [...sectionIndexes].sort((a, b) => a - b)
    const reorderedVerses = reorderedIndexes.map((index) => verses[index])
    targetSlots.forEach((slot, slotIndex) => {
      nextVerses[slot] = reorderedVerses[slotIndex]
    })

    normalizeVerseOrder(nextVerses)
    const nextSong = { ...editingSong, versos: nextVerses }
    syncLegacyFromSections(nextSong, Array.isArray(nextSong.secciones) ? nextSong.secciones : [])
    updateSong(nextSong)
    scheduleAutosave()
    return true
  }

  const beginVerseDrag = (event, fromIndex) => {
    event.dataTransfer.setData('text/plain', String(fromIndex))
    event.dataTransfer.effectAllowed = 'move'
    setVerseDragIndex(fromIndex)
  }

  const getDraggedVerseIndex = (event) => {
    const payload = parseInt(event.dataTransfer.getData('text/plain'), 10)
    if (!Number.isNaN(payload)) {
      return payload
    }
    return verseDragIndex
  }

  const handleVerseCardDragOver = (event, toIndex) => {
    if (verseDragIndex === null) return
    event.preventDefault()
    event.stopPropagation()
    setVerseDragOverIndex(toIndex)
  }

  const handleVerseCardDrop = (event, toIndex) => {
    event.preventDefault()
    event.stopPropagation()
    const fromIndex = getDraggedVerseIndex(event)
    if (fromIndex !== null && fromIndex !== undefined) {
      moveVerseInSection(fromIndex, toIndex)
    }
    setVerseDragIndex(null)
    setVerseDragOverIndex(null)
  }

  const handleVerseCardDragEnd = () => {
    setVerseDragIndex(null)
    setVerseDragOverIndex(null)
  }

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

  const handleVerseChange = (nextVerses) => {
    const nextSong = { ...editingSong, versos: nextVerses }
    syncLegacyFromSections(nextSong, Array.isArray(nextSong.secciones) ? nextSong.secciones : [])
    updateSong({ ...nextSong })
    scheduleAutosave()
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
    <section className="wpss-panel wpss-panel--editor">
      <header className="wpss-panel__header">
        <div>
          <h2>{editingSong.id ? editingSong.titulo || 'Canción' : wpData?.strings?.newSong || 'Nueva canción'}</h2>
          <p className="wpss-panel__meta">{editingSong.id ? `ID ${editingSong.id}` : '—'}</p>
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
              onClick={() => dispatch({ type: 'SET_STATE', payload: { activeTab: 'reading' } })}
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
          </div>
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

        <section className="wpss-section wpss-section--main">
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
                          onPointerDown={(event) => event.stopPropagation()}
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
                        <div className="wpss-section-pill__actions">
                          <details className="wpss-action-menu">
                            <summary
                              aria-label="Acciones de sección"
                              title="Acciones de sección"
                              onClick={(event) => event.stopPropagation()}
                              onPointerDown={(event) => event.stopPropagation()}
                            >
                              ⋯
                            </summary>
                            <div className="wpss-action-menu__panel">
                              <button
                                type="button"
                                className="button button-small"
                                onClick={(event) => {
                                  event.stopPropagation()
                                  handleDuplicateSection(index)
                                }}
                              >
                                Duplicar
                              </button>
                              <button
                                type="button"
                                className="button button-small"
                                onClick={(event) => {
                                  event.stopPropagation()
                                  selectSectionOnly(section.id)
                                  clearVerseSelection()
                                  setNavLevel('verses')
                                }}
                              >
                                MIDI sección
                              </button>
                              <button
                                type="button"
                                className="button button-link-delete"
                                onClick={(event) => {
                                  event.stopPropagation()
                                  handleRemoveSection(index)
                                }}
                                disabled={(Array.isArray(editingSong.secciones) ? editingSong.secciones.length : 0) <= 1}
                              >
                                Eliminar
                              </button>
                            </div>
                          </details>
                        </div>
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
                  />
                  <CommentEditor
                    label="Notas de sección"
                    comments={activeSection?.comentarios || []}
                    defaultTitle={activeSection?.nombre || getDefaultSectionName(0)}
                    onChange={(next) => handleSectionCommentsChange(activeSectionId, next)}
                  />
                </div>
              ) : navLevel === 'verses' ? (
                <div className="wpss-work-verse-explorer">
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
                                              compactMidiRows={preferCompactMidiRows}
                                              allowMidiRowToggle={preferCompactMidiRows}
                                              midiRangePresets={midiRangePresets}
                                              midiRangeDefault={midiRangeDefault}
                                              lockMidiRange={lockMidiRange}
                                              showHeader={false}
                                              showPreview={false}
                                              visibleVerseIndexes={new Set([index])}
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
                                          {verse.nombre
                                            ? verse.nombre
                                            : verse.instrumental
                                              ? `Instrumental ${verseIndex + 1}`
                                              : `Verso ${verseIndex + 1}`}
                                        </strong>
                                        <pre className="wpss-verse-card-mini__stack">{`${preview.chords}\n${preview.lyrics}`}</pre>
                                        <details
                                          className="wpss-verse-card-mini__menu wpss-action-menu"
                                          onClick={(event) => event.stopPropagation()}
                                        >
                                          <summary aria-label="Opciones del verso" title="Opciones del verso">
                                            ⋯
                                          </summary>
                                          <div className="wpss-action-menu__panel">
                                            <button
                                              type="button"
                                              className="button button-small"
                                              onClick={() => handleRenameVerseAtIndex(index)}
                                            >
                                              Renombrar
                                            </button>
                                            <button
                                              type="button"
                                              className="button button-small"
                                              onClick={() => handleDuplicateVerseAtIndex(index)}
                                            >
                                              Duplicar
                                            </button>
                                            <button
                                              type="button"
                                              className="button button-link-delete"
                                              onClick={() => handleRemoveVerseAtIndex(index)}
                                              disabled={sectionVerses.length <= 1}
                                            >
                                              Eliminar
                                            </button>
                                          </div>
                                        </details>
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
                                    <pre className="wpss-verse-card-mini__stack">{` \n...`}</pre>
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
                  compactMidiRows={preferCompactMidiRows}
                  allowMidiRowToggle={preferCompactMidiRows}
                  midiRangePresets={midiRangePresets}
                  midiRangeDefault={midiRangeDefault}
                  lockMidiRange={lockMidiRange}
                  showHeader={false}
                  showPreview={false}
                  visibleVerseIndexes={hasVerseFilter ? selectedVerseIndexes : null}
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
            <aside className={`wpss-editor-preview wpss-editor-column ${isFocusWork && !useMasterPreview ? '' : 'is-sections'}`}>
              <div className="wpss-section-preview">
                <div className="wpss-section-preview__header">
                  <strong>Vista previa</strong>
                  <div className="wpss-section-preview__header-actions">
                    <span>
                      {useMasterPreview ? 'Todas las secciones' : isFocusWork ? activeSection?.nombre || getDefaultSectionName(0) : 'Todas las secciones'}
                    </span>
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
                <div ref={previewScrollRef} className="wpss-section-preview__scroll-shell">
                  <div
                    className="wpss-section-preview__all wpss-section-preview__all--interactive"
                    style={{ '--wpss-preview-scale': previewScale / 100 }}
                  >
                    {sectionsList.length ? (
                      sectionsList.map((section, index) => {
                      const verses = versesBySection.get(section.id) || []
                      const isActiveSection = section.id === activeSectionId
                      const isExpandedSection = expandedSectionId === section.id
                      return (
                        <div
                          key={`preview-section-${section.id}`}
                          className={`wpss-section-preview__group ${isActiveSection ? 'is-active' : ''}`}
                          ref={(node) => {
                            if (node) {
                              previewSectionRefs.current.set(section.id, node)
                            } else {
                              previewSectionRefs.current.delete(section.id)
                            }
                          }}
                        >
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
                            <span>{verses.length} versos</span>
                          </button>
                          {isExpandedSection ? (
                            <details className="wpss-preview-section-menu">
                              <summary>Opciones de sección</summary>
                              <div className="wpss-preview-section-menu__body">
                                <MidiClipList
                                  clips={section?.midi_clips}
                                  onChange={(clips) => handleSectionMidiChange(section.id, clips)}
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
                                  comments={section?.comentarios || []}
                                  defaultTitle={section?.nombre || getDefaultSectionName(index)}
                                  onChange={(next) => handleSectionCommentsChange(section.id, next)}
                                />
                              </div>
                            </details>
                          ) : null}
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
                                if (isActiveVerse) {
                                  return (
                                    <div
                                      key={`preview-verse-editor-${section.id}-${verseId}`}
                                      className={`wpss-preview-verse-card is-active ${
                                        verseDragOverIndex === verseId ? 'is-dragover' : ''
                                      }`}
                                      onDragOver={(event) => handleVerseCardDragOver(event, verseId)}
                                      onDrop={(event) => handleVerseCardDrop(event, verseId)}
                                    >
                                      <div className="wpss-preview-verse-card__toolbar">
                                        <span
                                          className={`wpss-verse-card-mini__drag ${
                                            verseDragIndex === verseId ? 'is-dragging' : ''
                                          }`}
                                          draggable
                                          aria-label="Mover verso"
                                          title="Arrastra para ordenar"
                                          onClick={(event) => event.stopPropagation()}
                                          onPointerDown={(event) => event.stopPropagation()}
                                          onDragStart={(event) => beginVerseDrag(event, verseId)}
                                          onDragEnd={handleVerseCardDragEnd}
                                        >
                                          ☰
                                        </span>
                                        <strong className="wpss-preview-verse-card__title">{verseTitle}</strong>
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
                                          compactMidiRows={preferCompactMidiRows}
                                          allowMidiRowToggle={preferCompactMidiRows}
                                          midiRangePresets={midiRangePresets}
                                          midiRangeDefault={midiRangeDefault}
                                          lockMidiRange={lockMidiRange}
                                          showHeader={false}
                                          showPreview={false}
                                          visibleVerseIndexes={new Set([verseId])}
                                        />
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
                                    <div className="wpss-preview-verse-card__toolbar">
                                      <span
                                        className={`wpss-verse-card-mini__drag ${
                                          verseDragIndex === verseId ? 'is-dragging' : ''
                                        }`}
                                        draggable
                                        aria-label="Mover verso"
                                        title="Arrastra para ordenar"
                                        onClick={(event) => event.stopPropagation()}
                                        onPointerDown={(event) => event.stopPropagation()}
                                        onDragStart={(event) => beginVerseDrag(event, verseId)}
                                        onDragEnd={handleVerseCardDragEnd}
                                      >
                                        ☰
                                      </span>
                                      <strong className="wpss-preview-verse-card__title">{verseTitle}</strong>
                                    </div>
                                    <pre className="wpss-verse-card-mini__stack">{`${preview.chords}\n${preview.lyrics}`}</pre>
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
                                  <pre className="wpss-verse-card-mini__stack">{` \n...`}</pre>
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
                              <pre className="wpss-verse-card-mini__stack">{` \n...`}</pre>
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
            <span>Estructura personalizada</span>
          </summary>
          <StructurePanel
            structure={editingSong.estructura}
            sections={editingSong.secciones}
            onChange={handleStructureChange}
          />
        </details>

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
