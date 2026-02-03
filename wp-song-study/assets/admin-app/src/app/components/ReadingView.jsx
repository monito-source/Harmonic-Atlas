import { useMemo, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'
import {
  endsWithJoiner,
  formatSegmentsForStackedCells,
  formatSegmentsForStackedMode,
  getChordDisplayValue,
  getDefaultSectionName,
  getValidSegmentIndex,
} from '../utils.js'
import { buildMidiClipGroups, playMidiClipGroupsSequence, togglePlayback } from './MidiSketch.jsx'
import MidiClipList from './MidiClipList.jsx'

export default function ReadingView({ onExit, exitLabel, onShowList, onEdit }) {
  const { state, dispatch, wpData } = useAppState()
  const song = state.editingSong
  const bpmDefault = Number.isInteger(parseInt(song?.bpm, 10)) ? parseInt(song.bpm, 10) : 120
  const hasVerses = Array.isArray(song.versos) && song.versos.length
  const [repeatsEnabled, setRepeatsEnabled] = useState(true)
  const [linkedPlayback, setLinkedPlayback] = useState(true)
  const [showMidi, setShowMidi] = useState(true)
  const [showSectionTitles, setShowSectionTitles] = useState(true)
  const [activePlaybackKey, setActivePlaybackKey] = useState(null)
  const [activePlaybackMeta, setActivePlaybackMeta] = useState(null)

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

  return (
    <div className="wpss-reading">
      <div className="wpss-reading__header">
        <div>
          <h3>{song.titulo || wpData?.strings?.newSong || 'Nueva canción'}</h3>
          <p>
            <strong>Tónica:</strong> {song.tonica || '—'}
          </p>
          <p>
            <strong>Campo armónico:</strong> {song.campo_armonico || '—'}
          </p>
        </div>
        <div className="wpss-reading__actions">
          <div className="wpss-reading__group">
            <span className="wpss-reading__group-label">Vista</span>
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
          </div>
          <div className="wpss-reading__group">
            <span className="wpss-reading__group-label">Orden</span>
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
          </div>
          <div className="wpss-reading__group">
            <span className="wpss-reading__group-label">MIDI</span>
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
          </div>
          <div className="wpss-reading__group">
            <span className="wpss-reading__group-label">Notas</span>
            <div className="wpss-reading__group-controls">
              <button
                type="button"
                className={`button button-secondary ${state.readingShowNotes ? 'is-active' : ''}`}
                onClick={() => dispatch({ type: 'SET_STATE', payload: { readingShowNotes: !state.readingShowNotes } })}
              >
                {state.readingShowNotes ? 'Ocultar notas' : 'Mostrar notas'}
              </button>
            </div>
          </div>
          <div className="wpss-reading__group">
            <span className="wpss-reading__group-label">Secciones</span>
            <div className="wpss-reading__group-controls">
              <button
                type="button"
                className={`button button-secondary ${showSectionTitles ? 'is-active' : ''}`}
                onClick={() => setShowSectionTitles((prev) => !prev)}
              >
                {showSectionTitles ? 'Omitir titulos de secciones' : 'Mostrar titulos de secciones'}
              </button>
            </div>
          </div>
          <div className="wpss-reading__group wpss-reading__group--actions">
            <span className="wpss-reading__group-label">Acciones</span>
            <div className="wpss-reading__group-controls">
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
              {onShowList ? (
                <button type="button" className="button button-secondary" onClick={onShowList}>
                  Ver canciones
                </button>
              ) : null}
            </div>
          </div>
        </div>
      </div>
      <div className="wpss-reading__sections">
        {!hasVerses ? (
          <p className="wpss-empty">{wpData?.strings?.readingEmpty || 'Sin contenido para mostrar.'}</p>
        ) : (
          groups.map((group, index) => {
            const heading = group.variant ? `${group.title} (${group.variant})` : group.title
            const canPlaySection = showMidi && hasMidiInGroup(group)
            return (
              <details
                key={`reading-${index}`}
                className={`wpss-reading__section ${
                  activePlaybackMeta?.sectionIndex === index ? 'is-playing' : ''
                }`}
                open
              >
                <summary className="wpss-reading__section-summary">
                  {showSectionTitles ? (
                    <h4 className="wpss-section-title">
                      {heading}
                      {group.repeat > 1 ? <span className="wpss-reading__repeat">{`x${group.repeat}`}</span> : null}
                    </h4>
                  ) : (
                    <>
                      <span className="wpss-reading__section-toggle" aria-hidden="true">▾</span>
                      <span className="wpss-reading__section-sr">Sección</span>
                    </>
                  )}
                </summary>
                <div className="wpss-reading__section-body">
                  {canPlaySection ? (
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
                    </div>
                  ) : null}
                  {group.notes ? <p className="wpss-reading__notes">{group.notes}</p> : null}
                  {state.readingShowNotes && Array.isArray(group.section?.comentarios) && group.section.comentarios.length ? (
                    <div className="wpss-reading__notes-block">
                      {group.section.comentarios.map((note) => (
                        <div
                          key={note.id}
                          className="wpss-reading__note"
                          style={{ '--note-color': note.color || '#3b82f6' }}
                        >
                          <strong>Sección</strong>
                          <span dangerouslySetInnerHTML={{ __html: note.texto || '' }} />
                        </div>
                      ))}
                    </div>
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
                            mode={state.readingMode}
                            defaultTempo={bpmDefault}
                            repeatsEnabled={repeatsEnabled}
                            linkedPlayback={linkedPlayback}
                            showMidi={showMidi}
                            showNotes={state.readingShowNotes}
                            sectionIndex={index}
                            verseIndex={verseIndex}
                            activePlaybackMeta={activePlaybackMeta}
                          />
                        ))
                      : null}
                  </ol>
                </div>
              </details>
            )
          })
        )}
      </div>
    </div>
  )
}

function ReadingVerse({
  verse,
  mode,
  defaultTempo,
  repeatsEnabled,
  linkedPlayback,
  showMidi,
  showNotes,
  sectionIndex,
  verseIndex,
  activePlaybackMeta,
}) {
  const segmentos = Array.isArray(verse.segmentos) ? verse.segmentos : []
  const instrumental = verse.instrumental ? <span className="wpss-reading__instrumental">Instrumental</span> : null
  const evento = renderEventoChip(verse.evento_armonico, segmentos.length)
  const comentario = verse.comentario ? <span className="wpss-reading__comment">{verse.comentario}</span> : null
  const metaContent = [instrumental, evento, comentario].filter(Boolean)
  const meta = metaContent.length ? <div className="wpss-reading__meta">{metaContent}</div> : null
  const verseNotes =
    showNotes && Array.isArray(verse.comentarios) ? verse.comentarios : []
  const segmentNotes = showNotes
    ? segmentos.flatMap((segmento, index) =>
        Array.isArray(segmento?.comentarios)
          ? segmento.comentarios.map((note) => ({
              ...note,
              scope: `Segmento ${index + 1}`,
            }))
          : [],
      )
    : []
  const notesBlock =
    showNotes && (verseNotes.length || segmentNotes.length) ? (
      <div className="wpss-reading__notes-block">
        {verseNotes.map((note) => (
          <div
            key={note.id}
            className="wpss-reading__note"
            style={{ '--note-color': note.color || '#3b82f6' }}
          >
            <strong>Verso</strong>
            <span dangerouslySetInnerHTML={{ __html: note.texto || '' }} />
          </div>
        ))}
        {segmentNotes.map((note) => (
          <div
            key={note.id}
            className="wpss-reading__note"
            style={{ '--note-color': note.color || '#3b82f6' }}
          >
            <strong>{note.scope}</strong>
            <span dangerouslySetInnerHTML={{ __html: note.texto || '' }} />
          </div>
        ))}
      </div>
    ) : null
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

  const isActive =
    activePlaybackMeta?.sectionIndex === sectionIndex
    && activePlaybackMeta?.verseIndex === verseIndex
  const activeSegmentIndex = isActive ? activePlaybackMeta?.segmentIndex : null

  if (mode === 'stacked') {
    const lines = formatSegmentsForStackedCells(segmentos)
    return (
      <li className={isActive ? 'is-playing' : ''}>
        <pre className="wpss-reading__stack">
          <span className="wpss-reading__stack-chords">
            {lines.chords.map((cell, cellIndex) => (
              <span key={`chord-${cellIndex}`}>
                {cell.text ? <span className="wpss-reading__stack-chord">{cell.text}</span> : null}
                {cell.spacer ? <span className="wpss-reading__stack-spacer">{cell.spacer}</span> : null}
              </span>
            ))}
          </span>
          {'\n'}
          <span>{lines.lyrics}</span>
        </pre>
        {meta}
        {notesBlock}
        {verseMidi}
        {segmentMidis}
      </li>
    )
  }

  const targetIndex = getValidSegmentIndex(verse.evento_armonico, segmentos.length)
  const joiners = segmentos.map((segmento) => endsWithJoiner(segmento?.texto || ''))
  const parts = segmentos
    .map((segmento, index) => {
      const acordeValue = getChordDisplayValue(segmento?.acorde || '')
      const acorde = acordeValue ? <span className="wpss-reading__chord">[{acordeValue}]</span> : null
      const texto = segmento.texto || ''
      const classes = ['wpss-reading__segment']
      if (index > 0 && joiners[index - 1]) {
        classes.push('is-joined-prev')
      }
      if (joiners[index] && index < segmentos.length - 1) {
        classes.push('is-joined-next')
      }
      if (targetIndex !== null && targetIndex === index) {
        classes.push('is-event-target')
      }
      if (activeSegmentIndex !== null && activeSegmentIndex === index) {
        classes.push('is-playing')
      }
      if (showNotes && Array.isArray(segmento?.comentarios) && segmento.comentarios.length) {
        classes.push('has-note')
      }
      return {
        key: `segment-${index}`,
        classes: classes.join(' '),
        acorde,
        texto,
        joiner: joiners[index] && index < segmentos.length - 1,
        noteColor: segmento?.comentarios?.[0]?.color || '#3b82f6',
      }
    })
    .filter(Boolean)

  return (
    <li className={isActive ? 'is-playing' : ''}>
      <div className="wpss-reading__line">
        {parts.map((part, index) => (
          <span
            key={part.key}
            className={part.classes}
            style={part.classes.includes('has-note') ? { '--note-color': part.noteColor } : undefined}
          >
            {part.acorde}
            {part.texto ? <span dangerouslySetInnerHTML={{ __html: part.texto }} /> : null}
            {!part.joiner && index < parts.length - 1 ? <span className="wpss-reading__gap"> </span> : null}
          </span>
        ))}
      </div>
      {meta}
      {notesBlock}
      {verseMidi}
      {segmentMidis}
    </li>
  )
}

function renderEventoChip(evento, segmentCount) {
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
    const destino = [evento.tonica_destino || '', evento.campo_armonico_destino || ''].filter(Boolean).join(' ')
    return (
      <span className="wpss-event-chip">
        {`Modulación → ${destino || '—'}`} {segmentBadge}
      </span>
    )
  }

  if (evento.tipo === 'prestamo') {
    const origen = [evento.tonica_origen || '', evento.campo_armonico_origen || ''].filter(Boolean).join(' ')
    return (
      <span className="wpss-event-chip">
        {`Préstamo ← ${origen || '—'}`} {segmentBadge}
      </span>
    )
  }

  return null
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

 
