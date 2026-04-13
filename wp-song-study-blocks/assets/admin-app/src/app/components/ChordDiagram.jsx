import {
  getChordInstrumentDefinition,
  getChordInstrumentLabel,
} from '../chordInstruments.js'
import { SAX_KEY_GROUPS } from '../saxFingerings.js'

const PIANO_KEYS = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B']
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

function normalizePitchClass(value) {
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

function getShapeNotes(shape, instrument) {
  const instrumentDefinition = getChordInstrumentDefinition(instrument)
  if (instrumentDefinition?.renderer === 'piano' && Array.isArray(shape?.keys) && shape.keys.length) {
    return shape.keys
  }
  return Array.isArray(shape?.notes) ? shape.notes : []
}

function parseFretValue(value) {
  if (typeof value === 'number') {
    return value
  }
  const token = String(value || '').trim().toLowerCase()
  if (!token) {
    return null
  }
  if (token === 'x') {
    return 'x'
  }
  if (token === 'o') {
    return 0
  }
  const numeric = parseInt(token, 10)
  return Number.isNaN(numeric) ? null : numeric
}

export default function ChordDiagram({ shape, instrument }) {
  return <ChordDiagramRenderer shape={shape} instrument={instrument} />
}

export function ChordDiagramRenderer({ shape, instrument, editable = false, onShapeChange = null }) {
  if (!shape) {
    return null
  }

  const definition = getChordInstrumentDefinition(instrument)

  if (definition?.renderer === 'fretted') {
    return (
      <FrettedDiagram
        shape={shape}
        instrument={definition}
        editable={editable}
        onShapeChange={onShapeChange}
      />
    )
  }

  if (definition?.renderer === 'piano') {
    return <PianoDiagram shape={shape} instrument={definition} />
  }

  if (definition?.renderer === 'wind') {
    return (
      <WindDiagram
        shape={shape}
        instrument={definition}
        editable={editable}
        onShapeChange={onShapeChange}
      />
    )
  }

  return <NoteListDiagram shape={shape} instrument={definition} />
}

function FrettedDiagram({ shape, instrument, editable = false, onShapeChange = null }) {
  const strings = Array.isArray(instrument?.strings) ? instrument.strings : []
  const fretsRaw = Array.isArray(shape?.frets) ? shape.frets : []
  const frets = strings.map((_, index) => parseFretValue(fretsRaw[index]))
  const fingers = Array.isArray(shape?.fingers) ? shape.fingers.filter(Boolean) : []
  const notes = getShapeNotes(shape, instrument?.id)
  const parsedBase = parseInt(shape?.baseFret, 10)
  const baseFret = Number.isInteger(parsedBase) && parsedBase > 0 ? parsedBase : 1
  const maxFret = frets.reduce((acc, value) => (typeof value === 'number' ? Math.max(acc, value) : acc), 0)
  const startFret = maxFret > baseFret + 3 ? Math.max(baseFret, maxFret - 3) : baseFret
  const fretRows = Array.from({ length: 4 }, (_, index) => startFret + index)

  const writeFrets = (nextFrets) => {
    if (!editable || typeof onShapeChange !== 'function') {
      return
    }
    onShapeChange({
      ...shape,
      frets: nextFrets.map((value) => (value === null ? '' : value)),
    })
  }

  const handleFretClick = (stringIndex, fret) => {
    const nextFrets = [...frets]
    nextFrets[stringIndex] = nextFrets[stringIndex] === fret ? null : fret
    writeFrets(nextFrets)
  }

  const handleOpenToggle = (stringIndex) => {
    const currentValue = frets[stringIndex]
    const nextFrets = [...frets]
    if (currentValue === 0) {
      nextFrets[stringIndex] = 'x'
    } else if (currentValue === 'x') {
      nextFrets[stringIndex] = null
    } else {
      nextFrets[stringIndex] = 0
    }
    writeFrets(nextFrets)
  }

  const shiftBaseFret = (delta) => {
    if (!editable || typeof onShapeChange !== 'function') {
      return
    }
    onShapeChange({
      ...shape,
      baseFret: Math.max(1, baseFret + delta),
    })
  }

  return (
    <span
      className={`wpss-chord-diagram-preview wpss-chord-diagram-preview--${instrument?.renderer} ${editable ? 'is-editable' : ''}`.trim()}
    >
      {shape?.label ? <strong className="wpss-chord-diagram-preview__label">{shape.label}</strong> : null}
      <span className="wpss-chord-diagram-preview__meta">
        {getChordInstrumentLabel(instrument?.id)}
        {baseFret > 1 ? ` · traste ${baseFret}` : ''}
      </span>
      {editable ? (
        <span className="wpss-fretted-diagram__controls">
          <button type="button" className="button button-small" onClick={() => shiftBaseFret(-1)}>
            - traste
          </button>
          <button type="button" className="button button-small" onClick={() => shiftBaseFret(1)}>
            + traste
          </button>
        </span>
      ) : null}
      <span className="wpss-fretted-diagram" style={{ '--wpss-diagram-strings': strings.length }}>
        <span className="wpss-fretted-diagram__tuning">
          {strings.map((stringLabel) => (
            <span key={`tuning-${instrument?.id}-${stringLabel}`} className="wpss-fretted-diagram__tuning-cell">
              {stringLabel}
            </span>
          ))}
        </span>
        <span className="wpss-fretted-diagram__open">
          {strings.map((stringLabel, index) => {
            const value = frets[index]
            const state = value === 'x' ? 'x' : value === 0 ? 'o' : ''
            if (!editable) {
              return (
                <span key={`open-${instrument?.id}-${stringLabel}`} className="wpss-fretted-diagram__open-cell">
                  {state}
                </span>
              )
            }
            return (
              <button
                key={`open-${instrument?.id}-${stringLabel}`}
                type="button"
                className={`wpss-fretted-diagram__open-cell is-button ${state ? 'has-state' : ''}`.trim()}
                onClick={() => handleOpenToggle(index)}
                title="Alternar abierta, muteada o vacía"
              >
                {state || '·'}
              </button>
            )
          })}
        </span>
        <span className="wpss-fretted-diagram__grid">
          {fretRows.map((fret) => (
            <span key={`fret-${instrument?.id}-${fret}`} className="wpss-fretted-diagram__row">
              {strings.map((stringLabel, index) => {
                const value = frets[index]
                const isActive = typeof value === 'number' && value === fret
                if (!editable) {
                  return (
                    <span
                      key={`cell-${instrument?.id}-${stringLabel}-${fret}`}
                      className={`wpss-fretted-diagram__cell ${isActive ? 'is-active' : ''}`}
                    />
                  )
                }
                return (
                  <button
                    key={`cell-${instrument?.id}-${stringLabel}-${fret}`}
                    type="button"
                    className={`wpss-fretted-diagram__cell is-button ${isActive ? 'is-active' : ''}`.trim()}
                    onClick={() => handleFretClick(index, fret)}
                    title={`Cuerda ${stringLabel}, traste ${fret}`}
                  />
                )
              })}
            </span>
          ))}
        </span>
        <span className="wpss-fretted-diagram__frets">
          {fretRows.map((fret) => (
            <span key={`label-${instrument?.id}-${fret}`} className="wpss-fretted-diagram__fret-label">
              {fret}
            </span>
          ))}
        </span>
      </span>
      {editable ? (
        <span className="wpss-chord-diagram-preview__notes">
          Haz clic en la grilla para dibujar. La fila superior cambia abierta, muteada o vacía.
        </span>
      ) : null}
      {fingers.length ? (
        <span className="wpss-chord-diagram-preview__notes">Dedos: {fingers.join(', ')}</span>
      ) : null}
      {notes.length ? (
        <span className="wpss-chord-diagram-preview__notes">Notas: {notes.join(', ')}</span>
      ) : null}
    </span>
  )
}

function PianoDiagram({ shape, instrument }) {
  const notes = getShapeNotes(shape, instrument?.id)
  const activeKeys = new Set(notes.map((note) => normalizePitchClass(note)).filter(Boolean))

  return (
    <span className={`wpss-chord-diagram-preview wpss-chord-diagram-preview--${instrument?.renderer}`}>
      {shape?.label ? <strong className="wpss-chord-diagram-preview__label">{shape.label}</strong> : null}
      <span className="wpss-chord-diagram-preview__meta">{getChordInstrumentLabel(instrument?.id)}</span>
      <span className="wpss-piano-diagram">
        {PIANO_KEYS.map((key) => (
          <span
            key={`piano-${key}`}
            className={`wpss-piano-diagram__key ${activeKeys.has(key) ? 'is-active' : ''} ${key.includes('#') ? 'is-sharp' : 'is-natural'}`}
          >
            {key}
          </span>
        ))}
      </span>
      <span className="wpss-chord-diagram-preview__notes">
        {notes.length ? notes.join(', ') : 'Sin teclas definidas'}
      </span>
    </span>
  )
}

function WindDiagram({ shape, instrument, editable = false, onShapeChange = null }) {
  const notes = getShapeNotes(shape, instrument?.id)
  const activeKeys = Array.isArray(shape?.keys) ? shape.keys.filter(Boolean) : []
  const activeKeySet = new Set(activeKeys)

  const toggleKey = (keyId) => {
    if (!editable || typeof onShapeChange !== 'function') {
      return
    }
    const nextKeys = activeKeySet.has(keyId)
      ? activeKeys.filter((currentKey) => currentKey !== keyId)
      : [...activeKeys, keyId]
    onShapeChange({
      ...shape,
      keys: nextKeys,
    })
  }

  return (
    <span
      className={`wpss-chord-diagram-preview wpss-chord-diagram-preview--${instrument?.renderer} ${editable ? 'is-editable' : ''}`.trim()}
    >
      {shape?.label ? <strong className="wpss-chord-diagram-preview__label">{shape.label}</strong> : null}
      <span className="wpss-chord-diagram-preview__meta">{getChordInstrumentLabel(instrument?.id)}</span>
      <span className="wpss-wind-diagram">
        <span className="wpss-wind-diagram__spine" aria-hidden="true" />
        <span className="wpss-wind-diagram__body">
          {SAX_KEY_GROUPS.map((group) => (
            <span
              key={`wind-group-${group.id}`}
              className={`wpss-wind-diagram__group wpss-wind-diagram__group--${group.id}`}
            >
              <span className="wpss-wind-diagram__group-label">{group.label}</span>
              <span className="wpss-wind-diagram__keys">
                {group.keys.map((key) => {
                  const isActive = activeKeySet.has(key.id)
                  if (!editable) {
                    return (
                      <span
                        key={`wind-key-${group.id}-${key.id}`}
                        className={`wpss-wind-diagram__key ${isActive ? 'is-active' : ''}`.trim()}
                      >
                        {key.label}
                      </span>
                    )
                  }
                  return (
                    <button
                      key={`wind-key-${group.id}-${key.id}`}
                      type="button"
                      className={`wpss-wind-diagram__key is-button ${isActive ? 'is-active' : ''}`.trim()}
                      onClick={() => toggleKey(key.id)}
                      title={`Alternar llave ${key.label}`}
                    >
                      {key.label}
                    </button>
                  )
                })}
              </span>
            </span>
          ))}
        </span>
      </span>
      {editable ? (
        <span className="wpss-chord-diagram-preview__notes">
          Activa las llaves usadas por esta nota. Puedes guardar una forma por cada voz del acorde.
        </span>
      ) : null}
      <span className="wpss-chord-diagram-preview__notes">
        {notes.length ? `Notas: ${notes.join(', ')}` : 'Sin notas definidas'}
      </span>
      <span className="wpss-chord-diagram-preview__notes">
        {activeKeys.length ? `Llaves: ${activeKeys.join(', ')}` : 'Sin digitación definida'}
      </span>
    </span>
  )
}

function NoteListDiagram({ shape, instrument }) {
  const notes = getShapeNotes(shape, instrument?.id)

  return (
    <span className={`wpss-chord-diagram-preview wpss-chord-diagram-preview--${instrument?.renderer}`}>
      {shape?.label ? <strong className="wpss-chord-diagram-preview__label">{shape.label}</strong> : null}
      <span className="wpss-chord-diagram-preview__meta">{getChordInstrumentLabel(instrument?.id)}</span>
      <span className="wpss-chord-diagram-preview__notes">
        {notes.length ? notes.join(', ') : 'Sin notas definidas'}
      </span>
    </span>
  )
}
