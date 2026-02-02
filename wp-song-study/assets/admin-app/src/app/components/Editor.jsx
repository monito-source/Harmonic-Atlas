import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'
import {
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

const AUTOSAVE_DELAY = 800

export default function Editor({ onShowList }) {
  const { state, dispatch, api, wpData } = useAppState()
  const [editingSong, setEditingSong] = useState(state.editingSong)
  const [selectedSectionId, setSelectedSectionId] = useState(() => state.ui?.selectedSectionId ?? null)
  const autosaveRef = useRef(null)
  const selectionRef = useRef({ verse: null, segment: null, start: null, end: null, element: null })
  const lastSilentErrorRef = useRef(null)
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
    return () => {
      if (autosaveRef.current) {
        clearTimeout(autosaveRef.current)
      }
    }
  }, [])

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

    if (!editingSong.titulo.trim()) {
      warnSilent(wpData?.strings?.titleRequired || 'El título es obligatorio.')
      return
    }

    if (!editingSong.tonica.trim()) {
      warnSilent(wpData?.strings?.tonicaRequired || 'La tónica es obligatoria.')
      return
    }

    const segmentError = validateSegments(editingSong.versos, wpData?.strings)
    if (segmentError) {
      warnSilent(segmentError)
      return
    }

    const eventError = validateEventosArmonicos(editingSong.versos, wpData?.strings)
    if (eventError) {
      warnSilent(eventError)
      return
    }

    const estructuraPayload = normalizeStructureFromApi(editingSong.estructura || [], editingSong.secciones || [])
      const payload = {
        id: editingSong.id || null,
        titulo: editingSong.titulo,
        bpm: editingSong.bpm,
        tonica: editingSong.tonica,
      campo_armonico: editingSong.campo_armonico,
      campo_armonico_predominante: editingSong.campo_armonico_predominante,
      ficha_autores: editingSong.ficha_autores || '',
      ficha_anio: editingSong.ficha_anio || '',
      ficha_pais: editingSong.ficha_pais || '',
      ficha_estado_legal: editingSong.ficha_estado_legal || '',
      ficha_licencia: editingSong.ficha_licencia || '',
      ficha_fuente_verificacion: editingSong.ficha_fuente_verificacion || '',
      ficha_incompleta: !!editingSong.ficha_incompleta,
      ficha_incompleta_motivo: editingSong.ficha_incompleta_motivo || '',
      prestamos_cancion: editingSong.prestamos,
      modulaciones_cancion: editingSong.modulaciones,
      secciones: Array.isArray(editingSong.secciones)
        ? editingSong.secciones.map((section) => ({
            ...section,
            midi_clips: normalizeMidiClipsForSave(section.midi_clips),
          }))
        : [],
      versos: editingSong.versos.map((verso) => {
        const segmentos = Array.isArray(verso.segmentos)
          ? verso.segmentos.map((segment) => normalizeSegmentForSave(segment))
          : []
        const evento = prepareEventoArmonicoForPayload(verso.evento_armonico, segmentos.length)

        return {
          orden: verso.orden,
          segmentos,
          comentario: verso.comentario,
          evento_armonico: evento,
          instrumental: !!verso.instrumental,
          midi_clips: normalizeMidiClipsForSave(verso.midi_clips),
          section_id: verso.section_id || '',
          fin_de_estrofa: !!verso.fin_de_estrofa,
          nombre_estrofa: verso.fin_de_estrofa ? verso.nombre_estrofa || '' : '',
        }
      }),
      colecciones: Array.isArray(editingSong.colecciones) ? editingSong.colecciones.map((item) => item.id) : [],
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
          : editingSong.bpm
        const secciones = normalizeSectionsFromApi(body.secciones || editingSong.secciones, bpmDefault)
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

  const handleSectionSelect = (id) => {
    persistSelectedSection(id)
  }

  const handleSectionChange = (nextSections) => {
    const nextSong = { ...editingSong, secciones: nextSections }
    const nextSelected = ensureSectionsIntegrity(nextSong, selectedSectionId)
    persistSelectedSection(nextSelected)
    updateSong({ ...nextSong })
    scheduleAutosave()
  }

  const handleStructureChange = (nextStructure) => {
    updateSong({ ...editingSong, estructura: nextStructure })
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
        <div className="wpss-section wpss-section--meta">
          <header>
            <h3>Datos generales</h3>
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
        </div>

        <details className="wpss-section wpss-section--collapsible">
          <summary>
            <span>Ficha tecnica</span>
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

        <details className="wpss-section wpss-section--collapsible" open>
          <summary>
            <span>Versos</span>
          </summary>
          <VersesPanel
            verses={editingSong.versos}
            sections={editingSong.secciones}
            selectedSectionId={selectedSectionId}
            songBpm={editingSong.bpm}
            onSelectSection={handleSectionSelect}
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
          />
        </details>

        <details className="wpss-section wpss-section--collapsible">
          <summary>
            <span>Gestionar estructura de canción</span>
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
