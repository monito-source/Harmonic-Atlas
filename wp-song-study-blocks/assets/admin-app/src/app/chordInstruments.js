export const DEFAULT_CHORD_INSTRUMENT_ID = 'guitar'

export const CHORD_INSTRUMENTS = [
  {
    id: 'guitar',
    label: 'Guitarra',
    renderer: 'fretted',
    strings: ['E', 'A', 'D', 'G', 'B', 'e'],
  },
  {
    id: 'ukulele',
    label: 'Ukulele',
    renderer: 'fretted',
    strings: ['G', 'C', 'E', 'A'],
  },
  {
    id: 'bass',
    label: 'Bajo',
    renderer: 'fretted',
    strings: ['E', 'A', 'D', 'G'],
  },
  {
    id: 'piano',
    label: 'Piano',
    renderer: 'piano',
  },
  {
    id: 'sax-alto',
    label: 'Sax alto',
    renderer: 'wind',
  },
  {
    id: 'sax-tenor',
    label: 'Sax tenor',
    renderer: 'wind',
  },
]

const CHORD_INSTRUMENT_LOOKUP = new Map(CHORD_INSTRUMENTS.map((instrument) => [instrument.id, instrument]))

export function getChordInstrumentDefinition(instrumentId) {
  return CHORD_INSTRUMENT_LOOKUP.get(instrumentId) || CHORD_INSTRUMENT_LOOKUP.get(DEFAULT_CHORD_INSTRUMENT_ID)
}

export function getChordInstrumentLabel(instrumentId) {
  return getChordInstrumentDefinition(instrumentId)?.label || instrumentId || DEFAULT_CHORD_INSTRUMENT_ID
}

export function isChordInstrumentSupported(instrumentId) {
  return CHORD_INSTRUMENT_LOOKUP.has(instrumentId)
}

export function sanitizeChordInstrumentId(instrumentId) {
  return isChordInstrumentSupported(instrumentId) ? instrumentId : DEFAULT_CHORD_INSTRUMENT_ID
}

export function createEmptyDiagramShape(instrumentId) {
  const instrument = getChordInstrumentDefinition(instrumentId)

  if (instrument?.renderer === 'fretted') {
    return {
      label: '',
      baseFret: 1,
      frets: [],
      fingers: [],
      notes: [],
    }
  }

  if (instrument?.renderer === 'piano') {
    return {
      label: '',
      keys: [],
      notes: [],
    }
  }

  return {
    label: '',
    keys: [],
    notes: [],
  }
}
