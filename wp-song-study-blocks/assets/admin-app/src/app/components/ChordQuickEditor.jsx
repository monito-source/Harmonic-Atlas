import { ChordDiagramRenderer } from './ChordDiagram.jsx'
import TextCommitInput from './TextCommitInput.jsx'
import {
  CHORD_INSTRUMENTS,
  createEmptyDiagramShape,
  getChordInstrumentDefinition,
} from '../chordInstruments.js'
import {
  buildAutoWindShapes,
  getWindChordNoteCandidates,
  getWindPresetKeys,
  getWindPresetNoteCandidates,
} from '../saxFingerings.js'

const ROOT_OPTIONS = ['C', 'C#', 'Db', 'D', 'D#', 'Eb', 'E', 'F', 'F#', 'Gb', 'G', 'G#', 'Ab', 'A', 'A#', 'Bb', 'B']

const parseList = (value) =>
  String(value || '')
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)

const parseFrets = (value) =>
  parseList(value).map((token) => {
    if (/^-?\d+$/.test(token)) {
      return parseInt(token, 10)
    }
    return token
  })

const formatList = (value) => (Array.isArray(value) ? value.join(', ') : '')
const formatFrets = (value) => (Array.isArray(value) ? value.join(', ') : '')
export default function ChordQuickEditor({
  chord,
  saving = false,
  title = 'Editar acorde',
  subtitle = '',
  error = '',
  onChange,
  onSave,
  onCancel,
}) {
  if (!chord) {
    return null
  }

  const updateChord = (patch) => {
    onChange?.({ ...chord, ...patch })
  }

  const handleDiagramChange = (instrumentId, shapeIndex, shapePatch) => {
    const diagrams = chord?.diagrams && typeof chord.diagrams === 'object' ? chord.diagrams : {}
    const currentShapes = Array.isArray(diagrams[instrumentId]) ? diagrams[instrumentId] : []
    const nextShapes = currentShapes.map((shape, index) => (index === shapeIndex ? { ...shape, ...shapePatch } : shape))
    updateChord({ diagrams: { ...diagrams, [instrumentId]: nextShapes } })
  }

  const handleAddDiagram = (instrumentId) => {
    const diagrams = chord?.diagrams && typeof chord.diagrams === 'object' ? chord.diagrams : {}
    const currentShapes = Array.isArray(diagrams[instrumentId]) ? diagrams[instrumentId] : []
    updateChord({
      diagrams: { ...diagrams, [instrumentId]: [...currentShapes, createEmptyDiagramShape(instrumentId)] },
    })
  }

  const handleRemoveDiagram = (instrumentId, shapeIndex) => {
    const diagrams = chord?.diagrams && typeof chord.diagrams === 'object' ? chord.diagrams : {}
    const currentShapes = Array.isArray(diagrams[instrumentId]) ? diagrams[instrumentId] : []
    updateChord({
      diagrams: { ...diagrams, [instrumentId]: currentShapes.filter((_, index) => index !== shapeIndex) },
    })
  }

  const handleGenerateWindShapes = (instrumentId) => {
    const diagrams = chord?.diagrams && typeof chord.diagrams === 'object' ? chord.diagrams : {}
    const currentShapes = Array.isArray(diagrams[instrumentId]) ? diagrams[instrumentId] : []
    const nextShapes = buildAutoWindShapes(chord, { existingShapes: currentShapes })
    if (!nextShapes.length) {
      return
    }
    updateChord({ diagrams: { ...diagrams, [instrumentId]: nextShapes } })
  }

  const handleApplyWindPreset = (instrumentId, shapeIndex) => {
    const diagrams = chord?.diagrams && typeof chord.diagrams === 'object' ? chord.diagrams : {}
    const currentShapes = Array.isArray(diagrams[instrumentId]) ? diagrams[instrumentId] : []
    const shape = currentShapes[shapeIndex]
    const presetNote = getWindPresetNoteCandidates(shape, chord)[0]
    const presetKeys = getWindPresetKeys(presetNote)
    if (!presetNote || !presetKeys.length) {
      return
    }
    handleDiagramChange(instrumentId, shapeIndex, {
      keys: presetKeys,
      notes: Array.isArray(shape?.notes) && shape.notes.length ? shape.notes : [presetNote],
    })
  }

  return (
    <div className="wpss-chord-quick-editor">
      <div className="wpss-chord-quick-editor__header">
        <div>
          <strong>{title}</strong>
          {subtitle ? <p>{subtitle}</p> : null}
        </div>
        <div className="wpss-chord-quick-editor__actions">
          <button type="button" className="button button-secondary" onClick={onCancel} disabled={saving}>
            Cerrar
          </button>
          <button type="button" className="button button-primary" onClick={onSave} disabled={saving}>
            {saving ? 'Guardando…' : 'Guardar acorde'}
          </button>
        </div>
      </div>

      {error ? <p className="wpss-error">{error}</p> : null}

      <div className="wpss-field-group">
        <label className="wpss-field">
          <span>Nombre principal</span>
          <input
            type="text"
            value={chord.name || ''}
            onChange={(event) => updateChord({ name: event.target.value })}
          />
        </label>
        <label className="wpss-field">
          <span>Alias</span>
          <TextCommitInput
            type="text"
            value={formatList(chord.aliases)}
            format={(nextValue) => String(nextValue || '')}
            parse={(nextValue) => parseList(nextValue)}
            onCommit={(nextValue) => updateChord({ aliases: nextValue })}
          />
        </label>
        <label className="wpss-field">
          <span>Nota base</span>
          <select
            value={chord.root_base || ''}
            onChange={(event) => updateChord({ root_base: event.target.value })}
          >
            <option value="">Seleccionar</option>
            {ROOT_OPTIONS.map((note) => (
              <option key={`quick-root-${note}`} value={note}>
                {note}
              </option>
            ))}
          </select>
        </label>
        <label className="wpss-field">
          <span>Notas del acorde</span>
          <TextCommitInput
            type="text"
            value={formatList(chord.notes)}
            format={(nextValue) => String(nextValue || '')}
            parse={(nextValue) => parseList(nextValue)}
            onCommit={(nextValue) => updateChord({ notes: nextValue })}
          />
        </label>
      </div>

      <div className="wpss-chord-diagrams">
        <strong>Diagramas</strong>
        {CHORD_INSTRUMENTS.map((instrument) => {
          const diagrams = chord?.diagrams?.[instrument.id]
          const shapes = Array.isArray(diagrams) ? diagrams : []
          const definition = getChordInstrumentDefinition(instrument.id)

          return (
            <div key={`quick-diagram-${instrument.id}`} className="wpss-chord-diagrams__group">
              <div className="wpss-chord-diagrams__header">
                <span>{instrument.label}</span>
                <div className="wpss-chord-diagrams__actions">
                  {definition?.renderer === 'wind' && getWindChordNoteCandidates(chord).length ? (
                    <button
                      type="button"
                      className="button button-small"
                      onClick={() => handleGenerateWindShapes(instrument.id)}
                    >
                      Generar por voces
                    </button>
                  ) : null}
                  <button type="button" className="button button-small" onClick={() => handleAddDiagram(instrument.id)}>
                    Añadir forma
                  </button>
                </div>
              </div>
              {shapes.length ? (
                <div className="wpss-chord-diagrams__list">
                  {shapes.map((shape, shapeIndex) => (
                    <div key={`quick-shape-${instrument.id}-${shapeIndex}`} className="wpss-chord-diagram">
                      {definition?.renderer === 'wind' ? (
                        <div className="wpss-chord-diagram__toolbar">
                          <button
                            type="button"
                            className="button button-small"
                            onClick={() => handleApplyWindPreset(instrument.id, shapeIndex)}
                            disabled={!getWindPresetKeys(getWindPresetNoteCandidates(shape, chord)[0]).length}
                          >
                            {getWindPresetNoteCandidates(shape, chord)[0]
                              ? `Cargar preset ${getWindPresetNoteCandidates(shape, chord)[0]}`
                              : 'Sin preset'}
                          </button>
                        </div>
                      ) : null}
                      <div className="wpss-field-group">
                        <label className="wpss-field">
                          <span>Etiqueta</span>
                          <input
                            type="text"
                            value={shape?.label || ''}
                            onChange={(event) =>
                              handleDiagramChange(instrument.id, shapeIndex, { label: event.target.value })
                            }
                          />
                        </label>
                        {definition?.renderer === 'fretted' ? (
                          <label className="wpss-field">
                            <span>Traste base</span>
                            <input
                              type="number"
                              min="1"
                              value={shape?.baseFret || 1}
                              onChange={(event) =>
                                handleDiagramChange(instrument.id, shapeIndex, {
                                  baseFret: parseInt(event.target.value, 10) || 1,
                                })
                              }
                            />
                          </label>
                        ) : null}
                        <label className="wpss-field">
                          <span>
                            {definition?.renderer === 'fretted'
                              ? `Posiciones (${definition.strings.join(',')})`
                              : definition?.renderer === 'piano'
                                ? 'Teclas (C,E,G,Bb)'
                                : definition?.renderer === 'wind'
                                  ? 'Llaves activas (oct, lh1, rh1...)'
                                  : 'Notas o referencia'}
                          </span>
                          <TextCommitInput
                            type="text"
                            value={
                              definition?.renderer === 'fretted'
                                ? formatFrets(shape?.frets)
                                : definition?.renderer === 'piano' || definition?.renderer === 'wind'
                                  ? formatList(shape?.keys)
                                  : formatList(shape?.notes)
                            }
                            format={(nextValue) => String(nextValue || '')}
                            parse={(nextValue) => nextValue}
                            onCommit={(nextValue) =>
                              handleDiagramChange(
                                instrument.id,
                                shapeIndex,
                                definition?.renderer === 'fretted'
                                  ? { frets: parseFrets(nextValue) }
                                  : definition?.renderer === 'piano' || definition?.renderer === 'wind'
                                    ? { keys: parseList(nextValue) }
                                    : { notes: parseList(nextValue) },
                              )
                            }
                          />
                        </label>
                        {definition?.renderer === 'fretted' ? (
                          <label className="wpss-field">
                            <span>Dedos</span>
                            <TextCommitInput
                              type="text"
                              value={formatList(shape?.fingers)}
                              format={(nextValue) => String(nextValue || '')}
                              parse={(nextValue) => parseList(nextValue)}
                              onCommit={(nextValue) =>
                                handleDiagramChange(instrument.id, shapeIndex, {
                                  fingers: nextValue,
                                })
                              }
                            />
                          </label>
                        ) : null}
                        <label className="wpss-field">
                          <span>{definition?.renderer === 'wind' ? 'Nota o voces cubiertas' : 'Notas de apoyo'}</span>
                          <TextCommitInput
                            type="text"
                            value={formatList(shape?.notes)}
                            format={(nextValue) => String(nextValue || '')}
                            parse={(nextValue) => parseList(nextValue)}
                            onCommit={(nextValue) =>
                              handleDiagramChange(instrument.id, shapeIndex, {
                                notes: nextValue,
                              })
                            }
                          />
                        </label>
                      </div>
                      <div className="wpss-chord-diagram__preview">
                        <ChordDiagramRenderer
                          shape={shape}
                          instrument={instrument.id}
                          editable={definition?.renderer === 'fretted' || definition?.renderer === 'wind'}
                          onShapeChange={(nextShape) => handleDiagramChange(instrument.id, shapeIndex, nextShape)}
                        />
                      </div>
                      <button
                        type="button"
                        className="button button-small button-link-delete"
                        onClick={() => handleRemoveDiagram(instrument.id, shapeIndex)}
                      >
                        Quitar forma
                      </button>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="wpss-empty">Sin diagramas para {instrument.label}.</p>
              )}
            </div>
          )
        })}
      </div>
    </div>
  )
}
