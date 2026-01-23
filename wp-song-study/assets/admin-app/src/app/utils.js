export function getDefaultSectionName(index) {
  return `Sección ${index + 1}`
}

let sectionSeed = Date.now()
let sectionCounter = 0

export function generateSectionId() {
  sectionCounter += 1
  return `sec-${sectionSeed}-${sectionCounter}`
}

export const MIDI_DEFAULTS = {
  steps: 16,
  tempo: 120,
  notes: [],
}

const MIDI_INSTRUMENTS = ['basic', 'piano', 'guitar', 'voice']

export function normalizeMidiData(midi, defaultTempo = MIDI_DEFAULTS.tempo) {
  if (!midi || typeof midi !== 'object') {
    return null
  }

  const rawSteps = parseInt(midi.steps, 10)
  const rawTempo = parseInt(midi.tempo, 10)
  const steps = Number.isInteger(rawSteps) ? Math.min(Math.max(rawSteps, 4), 128) : MIDI_DEFAULTS.steps
  const tempo = Number.isInteger(rawTempo)
    ? Math.min(Math.max(rawTempo, 40), 240)
    : Math.min(Math.max(parseInt(defaultTempo, 10) || MIDI_DEFAULTS.tempo, 40), 240)

  const notesInput = Array.isArray(midi.notes) ? midi.notes : []
  const notes = []
  const seen = new Set()

  for (const note of notesInput) {
    if (!note) continue
    const step = parseInt(note.step, 10)
    const pitch = parseInt(note.pitch, 10)
    const rawLength = parseInt(note.length, 10)
    const length = Number.isInteger(rawLength) ? Math.min(Math.max(rawLength, 1), steps) : 1
    const rawVelocity = parseInt(note.velocity, 10)
    const velocity = Number.isInteger(rawVelocity) ? Math.min(Math.max(rawVelocity, 1), 127) : 96
    if (!Number.isInteger(step) || step < 0 || step >= steps) continue
    if (!Number.isInteger(pitch) || pitch < 0 || pitch > 127) continue
    const safeLength = step + length > steps ? steps - step : length
    if (safeLength < 1) continue
    const key = `${step}:${pitch}:${safeLength}`
    if (seen.has(key)) continue
    seen.add(key)
    notes.push({ step, pitch, length: safeLength, velocity })
  }

  return { steps, tempo, notes }
}

export function normalizeMidiClips(clips, legacyMidi = null, defaultTempo = MIDI_DEFAULTS.tempo) {
  let input = []
  let legacy = legacyMidi
  if (Array.isArray(clips)) {
    input = clips
  } else if (clips && typeof clips === 'object' && ('notes' in clips || 'tempo' in clips)) {
    legacy = clips
  }
  const normalized = []

  input.forEach((clip, index) => {
    if (!clip) return
    const midiSource = clip.midi ? clip.midi : clip
    const midi = normalizeMidiData(midiSource, defaultTempo)
    if (!midi) return
    const nameRaw = clip.name ? String(clip.name) : ''
    const name = nameRaw.trim() ? nameRaw.trim().slice(0, 64) : `MIDI ${index + 1}`
    const instrument = MIDI_INSTRUMENTS.includes(clip.instrument) ? clip.instrument : 'basic'
    const repeatRaw = parseInt(clip.repeat, 10)
    const repeat = Number.isInteger(repeatRaw) && repeatRaw > 0 ? Math.min(repeatRaw, 32) : 1
    normalized.push({ name, instrument, repeat, midi })
  })

  if (!normalized.length && legacy) {
    const midi = normalizeMidiData(legacy, defaultTempo)
    if (midi) {
      normalized.push({ name: 'MIDI 1', instrument: 'basic', repeat: 1, midi })
    }
  }

  return normalized
}

export function stripHtml(text) {
  if (!text) {
    return ''
  }
  const div = document.createElement('div')
  div.innerHTML = text
  return div.textContent || ''
}

export function normalizeSectionsFromApi(secciones, defaultTempo = MIDI_DEFAULTS.tempo) {
  if (!Array.isArray(secciones)) {
    return []
  }

  const used = new Set()

  return secciones.map((seccion, index) => {
    let id = seccion && seccion.id ? String(seccion.id) : generateSectionId()
    let nombre = seccion && seccion.nombre ? String(seccion.nombre) : ''

    id = id.trim()
    nombre = nombre.trim()

    if (!id) {
      id = generateSectionId()
    }

    while (used.has(id)) {
      id = generateSectionId()
    }

    used.add(id)

    if (!nombre) {
      nombre = getDefaultSectionName(index)
    }

    return {
      id,
      nombre: nombre.slice(0, 64),
      midi_clips: normalizeMidiClips(seccion?.midi_clips, seccion?.midi, defaultTempo),
    }
  })
}

export function normalizeVersesFromApi(versos, defaultTempo = MIDI_DEFAULTS.tempo) {
  if (!Array.isArray(versos) || !versos.length) {
    return []
  }

  return versos.map((verso, index) => {
    const segmentos = Array.isArray(verso.segmentos) && verso.segmentos.length
      ? verso.segmentos.map((segmento) => ({
          texto: segmento && segmento.texto ? segmento.texto : '',
          acorde: segmento && segmento.acorde ? segmento.acorde : '',
          midi_clips: normalizeMidiClips(segmento?.midi_clips, segmento?.midi, defaultTempo),
        }))
      : [{ texto: '', acorde: '', midi_clips: [] }]

    const evento = normalizeEventoArmonico(verso.evento_armonico || null, segmentos.length)

    return {
      id: verso.id || null,
      orden: verso.orden || index + 1,
      segmentos,
      comentario: verso.comentario || '',
      evento_armonico: evento,
      section_id: verso.section_id ? String(verso.section_id) : '',
      fin_de_estrofa: !!verso.fin_de_estrofa,
      nombre_estrofa: verso.nombre_estrofa ? String(verso.nombre_estrofa).slice(0, 64) : '',
      midi_clips: normalizeMidiClips(verso?.midi_clips, verso?.midi, defaultTempo),
    }
  })
}

export function buildDefaultStructureFromSections(secciones) {
  if (!Array.isArray(secciones)) {
    return []
  }

  return secciones
    .map((seccion) => {
      if (!seccion || !seccion.id) {
        return null
      }
      return { ref: String(seccion.id) }
    })
    .filter(Boolean)
}

export function normalizeStructureFromApi(estructura, secciones) {
  const sections = Array.isArray(secciones) ? secciones : []
  if (!sections.length) {
    return []
  }

  const defaultStructure = buildDefaultStructureFromSections(secciones)
  const validIds = new Set(defaultStructure.map((call) => call.ref))

  let structure = Array.isArray(estructura) ? estructura : []
  structure = structure
    .map((call) => {
      if (!call || !call.ref || !validIds.has(call.ref)) {
        return null
      }

      const normalized = { ref: String(call.ref) }
      if (call.variante) {
        normalized.variante = String(call.variante).slice(0, 16)
      }
      if (call.notas) {
        normalized.notas = String(call.notas).slice(0, 128)
      }
      return normalized
    })
    .filter(Boolean)

  return structure.length ? structure : defaultStructure
}

export function normalizeEventoArmonico(evento, segmentCount) {
  if (!evento || typeof evento !== 'object') {
    return null
  }

  const tipo = evento.tipo
  if (!tipo || !['modulacion', 'prestamo'].includes(tipo)) {
    return null
  }

  const limpio = { tipo }

  if ('modulacion' === tipo) {
    if (evento.tonica_destino) {
      limpio.tonica_destino = String(evento.tonica_destino)
    }
    if (evento.campo_armonico_destino) {
      limpio.campo_armonico_destino = String(evento.campo_armonico_destino)
    }
  } else if ('prestamo' === tipo) {
    if (evento.tonica_origen) {
      limpio.tonica_origen = String(evento.tonica_origen)
    }
    if (evento.campo_armonico_origen) {
      limpio.campo_armonico_origen = String(evento.campo_armonico_origen)
    }
  }

  if (Object.prototype.hasOwnProperty.call(evento, 'segment_index')) {
    const parsed = parseInt(evento.segment_index, 10)
    if (Number.isInteger(parsed) && parsed >= 0) {
      if (Number.isInteger(segmentCount) && parsed < segmentCount) {
        limpio.segment_index = parsed
      } else if (!Number.isInteger(segmentCount)) {
        limpio.segment_index = parsed
      }
    }
  }

  return limpio
}

export function getValidSegmentIndex(evento, segmentCount) {
  if (!evento || typeof evento !== 'object') {
    return null
  }

  if (!Object.prototype.hasOwnProperty.call(evento, 'segment_index')) {
    return null
  }

  const index = parseInt(evento.segment_index, 10)
  if (!Number.isInteger(index) || index < 0) {
    return null
  }

  if (Number.isInteger(segmentCount) && index >= segmentCount) {
    return null
  }

  return index
}

export function prepareEventoArmonicoForPayload(evento, segmentCount) {
  if (!evento || typeof evento !== 'object') {
    return null
  }

  const tipo = evento.tipo
  if (!tipo || !['modulacion', 'prestamo'].includes(tipo)) {
    return null
  }

  const payload = { tipo }

  if ('modulacion' === tipo) {
    if (evento.tonica_destino) {
      payload.tonica_destino = String(evento.tonica_destino)
    }
    if (evento.campo_armonico_destino) {
      payload.campo_armonico_destino = String(evento.campo_armonico_destino)
    }
  } else if ('prestamo' === tipo) {
    if (evento.tonica_origen) {
      payload.tonica_origen = String(evento.tonica_origen)
    }
    if (evento.campo_armonico_origen) {
      payload.campo_armonico_origen = String(evento.campo_armonico_origen)
    }
  }

  const index = getValidSegmentIndex(evento, segmentCount)
  if (null !== index) {
    payload.segment_index = index
  }

  return payload
}

export function normalizeVerseOrder(versos) {
  if (!Array.isArray(versos)) {
    return []
  }

  versos.forEach((verso, index) => {
    verso.orden = index + 1
  })

  return versos
}

export function validateSegments(versos, strings) {
  if (!Array.isArray(versos) || !versos.length) {
    return null
  }

  for (const verso of versos) {
    const verseMidiActive = Array.isArray(verso?.midi_clips)
      && verso.midi_clips.some((clip) => Array.isArray(clip?.midi?.notes) && clip.midi.notes.length)
    if (!Array.isArray(verso.segmentos) || !verso.segmentos.length) {
      if (verseMidiActive) {
        continue
      }
      return strings?.segmentRequired || 'Cada verso necesita al menos un segmento con texto o acorde.'
    }

    let previousTextless = false
    for (const segmento of verso.segmentos) {
      const textoVacio = !segmento.texto || !stripHtml(segmento.texto).trim()
      const acordeVacio = !segmento.acorde || !segmento.acorde.trim()
      const midiActivo = Array.isArray(segmento?.midi_clips)
        && segmento.midi_clips.some((clip) => Array.isArray(clip?.midi?.notes) && clip.midi.notes.length)
      const midiPresente = midiActivo || verseMidiActive

      if (textoVacio && acordeVacio && !midiPresente) {
        return strings?.segmentRequired || 'Cada verso necesita al menos un segmento con texto, acorde o MIDI.'
      }

      if (textoVacio && !midiPresente && previousTextless) {
        return strings?.segmentConsecutive || 'No se permiten segmentos consecutivos sin texto.'
      }

      previousTextless = textoVacio && !midiPresente
    }
  }

  return null
}

export function validateEventosArmonicos(versos, strings) {
  if (!Array.isArray(versos) || !versos.length) {
    return null
  }

  for (const verso of versos) {
    const evento = verso && verso.evento_armonico && typeof verso.evento_armonico === 'object'
      ? verso.evento_armonico
      : null

    if (!evento || !evento.tipo) {
      continue
    }

    if ('modulacion' === evento.tipo) {
      const hasDestino = (evento.tonica_destino && evento.tonica_destino.trim())
        || (evento.campo_armonico_destino && evento.campo_armonico_destino.trim())
      if (!hasDestino) {
        return strings?.eventoDatosRequeridos || 'Completa la tónica o el campo armónico del evento antes de guardar.'
      }
    } else if ('prestamo' === evento.tipo) {
      const hasOrigen = (evento.tonica_origen && evento.tonica_origen.trim())
        || (evento.campo_armonico_origen && evento.campo_armonico_origen.trim())
      if (!hasOrigen) {
        return strings?.eventoDatosRequeridos || 'Completa la tónica o el campo armónico del evento antes de guardar.'
      }
    }

    if (Array.isArray(verso.segmentos) && verso.segmentos.length) {
      const index = getValidSegmentIndex(evento, verso.segmentos.length)
      if (Object.prototype.hasOwnProperty.call(evento, 'segment_index') && null === index) {
        return strings?.eventoSegmentoInvalido || 'Selecciona un segmento válido para el evento armónico.'
      }
    }
  }

  return null
}
