import { transposePitchToken } from './utils.js'

const ENHARMONIC_TO_SHARP = {
  'B#': 'C',
  Db: 'C#',
  Eb: 'D#',
  Fb: 'E',
  'E#': 'F',
  Gb: 'F#',
  Ab: 'G#',
  Bb: 'A#',
  Cb: 'B',
}

export const SAX_KEY_GROUPS = [
  {
    id: 'octave',
    label: 'Octava',
    keys: [
      { id: 'oct', label: 'Oct' },
      { id: 'bis', label: 'Bis' },
    ],
  },
  {
    id: 'left',
    label: 'Izquierda',
    keys: [
      { id: 'lh1', label: '1' },
      { id: 'lh2', label: '2' },
      { id: 'lh3', label: '3' },
      { id: 'gsharp', label: 'G#' },
    ],
  },
  {
    id: 'right',
    label: 'Derecha',
    keys: [
      { id: 'rh1', label: '1' },
      { id: 'rh2', label: '2' },
      { id: 'rh3', label: '3' },
      { id: 'low-csharp', label: 'C#' },
      { id: 'low-b', label: 'B' },
      { id: 'low-bflat', label: 'Bb' },
    ],
  },
  {
    id: 'side',
    label: 'Laterales',
    keys: [
      { id: 'side-c', label: 'C' },
      { id: 'side-bflat', label: 'Bb' },
      { id: 'side-fsharp', label: 'F#' },
      { id: 'high-fsharp', label: 'Alt F#' },
    ],
  },
]

export const SAX_FINGERING_PRESETS = {
  C: ['oct', 'lh2'],
  'C#': ['oct', 'side-c'],
  D: ['oct', 'lh1', 'lh2', 'lh3', 'rh1', 'rh2', 'rh3'],
  'D#': ['oct', 'lh1', 'lh2', 'lh3', 'rh1', 'rh2', 'rh3', 'side-c'],
  E: ['oct', 'lh1', 'lh2', 'lh3', 'rh1', 'rh2'],
  F: ['oct', 'lh1', 'lh2', 'lh3', 'rh1'],
  'F#': ['oct', 'lh1', 'lh2', 'lh3', 'rh1', 'side-fsharp'],
  G: ['oct', 'lh1', 'lh2', 'lh3'],
  'G#': ['oct', 'gsharp'],
  A: ['oct', 'lh1', 'lh2'],
  'A#': ['oct', 'bis', 'lh1'],
  B: ['oct', 'lh1'],
}

export function normalizeWindNote(value) {
  const raw = String(value || '').trim()
  if (!raw) {
    return ''
  }
  const match = raw.match(/^([A-Ga-g])([#b♯♭]?)/)
  if (!match) {
    return raw.toUpperCase()
  }
  const letter = match[1].toUpperCase()
  const accidentalRaw = match[2]
  const accidental =
    accidentalRaw === '#' || accidentalRaw === '♯'
      ? '#'
      : accidentalRaw === 'b' || accidentalRaw === '♭'
        ? 'b'
        : ''
  const normalized = `${letter}${accidental}`
  return ENHARMONIC_TO_SHARP[normalized] || normalized
}

export function getWindPresetKeys(note) {
  const normalized = normalizeWindNote(note)
  return normalized ? SAX_FINGERING_PRESETS[normalized] || [] : []
}

export function getWindPresetNoteCandidates(shape, chord) {
  const shapeNotes = Array.isArray(shape?.notes) ? shape.notes.filter(Boolean) : []
  const chordVoicing = Array.isArray(chord?.voicing) ? chord.voicing.filter(Boolean) : []
  const chordNotes = Array.isArray(chord?.notes) ? chord.notes.filter(Boolean) : []
  const merged = [...shapeNotes, ...chordVoicing, ...chordNotes]
  return Array.from(new Set(merged.map((note) => normalizeWindNote(note)).filter(Boolean)))
}

function getRootCandidate(value) {
  const raw = String(value || '').trim()
  if (!raw) {
    return ''
  }
  const match = raw.match(/^([A-Ga-g](?:[#b♯♭])?)/)
  return match ? normalizeWindNote(match[1]) : ''
}

export function getWindChordNoteCandidates(chord, semitoneShift = 0, fallbackToken = '') {
  const diagrams = chord?.diagrams && typeof chord.diagrams === 'object' ? chord.diagrams : {}
  const diagramNotes = Object.entries(diagrams).flatMap(([instrumentId, shapes]) => {
    if (!Array.isArray(shapes)) {
      return []
    }
    return shapes.flatMap((shape) => {
      const notes = Array.isArray(shape?.notes) ? shape.notes : []
      const keys = instrumentId === 'piano' && Array.isArray(shape?.keys) ? shape.keys : []
      return [...notes, ...keys]
    })
  })
  const rootCandidates = [
    chord?.root_base,
    chord?.name,
    chord?.id,
    fallbackToken,
  ]
  const merged = [
    ...(Array.isArray(chord?.voicing) ? chord.voicing : []),
    ...(Array.isArray(chord?.notes) ? chord.notes : []),
    ...diagramNotes,
    ...rootCandidates,
  ]
  const normalized = Array.from(
    new Set(
      merged
        .map((note) => {
          const normalizedNote = normalizeWindNote(note)
          return normalizedNote || getRootCandidate(note)
        })
        .filter(Boolean)
        .map((note) => {
          const preferFlats = String(note || '').includes('b')
          return transposePitchToken(note, semitoneShift, preferFlats) || note
        }),
    ),
  )
  return normalized
}

export function buildAutoWindShapes(chord, options = {}) {
  const {
    semitoneShift = 0,
    fallbackToken = '',
    existingShapes = [],
  } = options
  const voiceNotes = getWindChordNoteCandidates(chord, semitoneShift, fallbackToken)
  return voiceNotes.map((note, index) => {
    const currentShape =
      existingShapes[index] && typeof existingShapes[index] === 'object'
        ? existingShapes[index]
        : {}
    const presetKeys = getWindPresetKeys(note)
    return {
      label: currentShape.label || `Voz ${index + 1}: ${note}`,
      keys: Array.isArray(currentShape.keys) && currentShape.keys.length ? currentShape.keys : presetKeys,
      notes: Array.isArray(currentShape.notes) && currentShape.notes.length ? currentShape.notes : [note],
    }
  })
}
