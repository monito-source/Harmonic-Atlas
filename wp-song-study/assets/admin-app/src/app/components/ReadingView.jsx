import { useMemo } from 'react'
import { useAppState } from '../StateProvider.jsx'
import { getDefaultSectionName, getValidSegmentIndex } from '../utils.js'

export default function ReadingView({ onExit, exitLabel }) {
  const { state, dispatch, wpData } = useAppState()
  const song = state.editingSong
  const hasVerses = Array.isArray(song.versos) && song.versos.length

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
        }))
  }, [song, state.readingFollowStructure, hasVerses])

  if (!hasVerses) {
    return <p className="wpss-empty">{wpData?.strings?.readingEmpty || 'Sin contenido para mostrar.'}</p>
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
          <div className="wpss-reading__modes">
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
          <div className="wpss-reading__structure">
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
      <div className="wpss-reading__sections">
        {groups.map((group, index) => {
          const heading = group.variant ? `${group.title} (${group.variant})` : group.title
          return (
            <section key={`reading-${index}`} className="wpss-reading__section">
              <h4 className="wpss-section-title">{heading}</h4>
              {group.notes ? <p className="wpss-reading__notes">{group.notes}</p> : null}
              <ol className="wpss-reading__verses">
                {Array.isArray(group.versos)
                  ? group.versos.map((verse, verseIndex) => (
                      <ReadingVerse key={`verse-${index}-${verseIndex}`} verse={verse} mode={state.readingMode} />
                    ))
                  : null}
              </ol>
            </section>
          )
        })}
      </div>
    </div>
  )
}

function ReadingVerse({ verse, mode }) {
  const segmentos = Array.isArray(verse.segmentos) ? verse.segmentos : []
  const evento = renderEventoChip(verse.evento_armonico, segmentos.length)
  const comentario = verse.comentario ? <span className="wpss-reading__comment">{verse.comentario}</span> : null
  const metaContent = [evento, comentario].filter(Boolean)
  const meta = metaContent.length ? <div className="wpss-reading__meta">{metaContent}</div> : null

  if (mode === 'stacked') {
    const lines = formatSegmentsForStackedMode(segmentos)
    return (
      <li>
        <pre className="wpss-reading__stack">{`${lines.chords}\n${lines.lyrics}`}</pre>
        {meta}
      </li>
    )
  }

  const targetIndex = getValidSegmentIndex(verse.evento_armonico, segmentos.length)
  const parts = segmentos
    .map((segmento, index) => {
      const acorde = segmento.acorde ? <span className="wpss-reading__chord">[{segmento.acorde}]</span> : null
      const texto = segmento.texto || ''
      const classes = ['wpss-reading__segment']
      if (targetIndex !== null && targetIndex === index) {
        classes.push('is-event-target')
      }
      return (
        <span key={`segment-${index}`} className={classes.join(' ')}>
          {acorde}
          {texto ? <span>{texto}</span> : null}
        </span>
      )
    })
    .filter(Boolean)

  return (
    <li>
      <div className="wpss-reading__line">{parts}</div>
      {meta}
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

  return estructura.map((call, index) => {
    const info = sectionMap.get(call.ref) || { ...fallback, index: 0 }
    const baseTitle = info?.nombre || getDefaultSectionName('index' in info ? info.index : index)
    const variant = call?.variante ? String(call.variante).slice(0, 16) : ''
    const notes = call?.notas ? String(call.notas).slice(0, 128) : ''
    const versosSeccion = versesBySection.get(call.ref) || []

    return {
      title: baseTitle,
      variant,
      notes,
      versos: versosSeccion,
    }
  })
}

function padEndSafe(value, length) {
  let result = String(value)
  while (result.length < length) {
    result += ' '
  }
  return result
}

function formatSegmentsForStackedMode(segmentos) {
  if (!Array.isArray(segmentos) || !segmentos.length) {
    return { chords: '', lyrics: '' }
  }

  const chordsParts = []
  const lyricsParts = []

  segmentos.forEach((segmento, index) => {
    const texto = segmento?.texto || ''
    const acorde = segmento?.acorde || ''
    const width = Math.max(texto.length, acorde.length)
    const padding = index === segmentos.length - 1 ? width : width + 2

    chordsParts.push(acorde ? padEndSafe(acorde, padding) : padEndSafe('', padding))
    lyricsParts.push(padEndSafe(texto, padding))
  })

  return {
    chords: chordsParts.join('').trimEnd(),
    lyrics: lyricsParts.join('').trimEnd(),
  }
}
