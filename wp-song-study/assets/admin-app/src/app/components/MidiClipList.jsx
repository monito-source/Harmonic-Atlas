import MidiSketch, { createDefaultMidi, playMidiData } from './MidiSketch.jsx'
import { useEffect } from 'react'

const INSTRUMENT_OPTIONS = [
  { value: 'basic', label: 'Basico' },
  { value: 'piano', label: 'Piano' },
  { value: 'guitar', label: 'Guitarra' },
  { value: 'voice', label: 'Voz' },
]

function hasMidiNotes(clip) {
  return Array.isArray(clip?.midi?.notes) && clip.midi.notes.length
}

export default function MidiClipList({
  clips,
  onChange,
  readOnly = false,
  showOnlyActiveRows = false,
  emptyLabel = 'Añadir MIDI',
  defaultTempo = 120,
  repeatsEnabled = true,
}) {
  const safeClips = Array.isArray(clips) ? clips : []
  const visibleClips = readOnly ? safeClips.filter(hasMidiNotes) : safeClips

  if (!visibleClips.length && readOnly) {
    return null
  }

  const updateClips = (next) => {
    if (readOnly) return
    if (onChange) {
      onChange(next)
    }
  }

  const handleAdd = () => {
    const next = [...safeClips]
    const name = `MIDI ${next.length + 1}`
    next.push({ name, instrument: 'basic', repeat: 1, midi: createDefaultMidi(defaultTempo) })
    updateClips(next)
  }

  const handleRemove = (index) => {
    const next = [...safeClips]
    next.splice(index, 1)
    updateClips(next)
  }

  const handleUpdate = (index, patch) => {
    const next = [...safeClips]
    next[index] = { ...next[index], ...patch }
    updateClips(next)
  }

  useEffect(() => {
    if (readOnly) return
    if (!safeClips.length) return
    const needsTempoFix = safeClips.some((clip) => {
      const midi = clip?.midi
      if (!midi) return false
      return !Number.isInteger(parseInt(midi.tempo, 10))
    })
    if (!needsTempoFix) return
    const next = safeClips.map((clip) => {
      const midi = clip?.midi
      if (!midi) return clip
      const tempo = parseInt(midi.tempo, 10)
      if (Number.isInteger(tempo)) return clip
      return { ...clip, midi: { ...midi, tempo: defaultTempo } }
    })
    updateClips(next)
  }, [defaultTempo, readOnly, safeClips])

  return (
    <div className="wpss-midi-clips">
      {visibleClips.map((clip, index) => {
        const name = clip?.name ? String(clip.name) : `MIDI ${index + 1}`
        const instrument = clip?.instrument || 'basic'
        const midi = clip?.midi || null
        const repeatRaw = parseInt(clip?.repeat, 10)
        const repeat = Number.isInteger(repeatRaw) && repeatRaw > 0 ? Math.min(repeatRaw, 32) : 1
        const effectiveMidi =
          midi && !Number.isInteger(parseInt(midi.tempo, 10)) ? { ...midi, tempo: defaultTempo } : midi

        return (
          <div key={`clip-${index}`} className="wpss-midi-clip">
            <div className="wpss-midi-clip__header">
              {readOnly ? (
                <>
                  <span className="wpss-midi-clip__title">{name}</span>
                  <button
                    type="button"
                    className="button button-small"
                    onClick={() => playMidiData(effectiveMidi, instrument, repeatsEnabled ? repeat : 1)}
                  >
                    Reproducir
                  </button>
                  {repeat > 1 ? <span className="wpss-midi-clip__repeat">{`x${repeat}`}</span> : null}
                </>
              ) : (
                <>
                  <label className="wpss-midi-clip__field">
                    <span>Nombre</span>
                    <input
                      type="text"
                      value={name}
                      maxLength={64}
                      onChange={(event) => handleUpdate(index, { name: event.target.value.slice(0, 64) })}
                    />
                  </label>
                  <label className="wpss-midi-clip__field">
                    <span>Timbre</span>
                    <select
                      value={instrument}
                      onChange={(event) => handleUpdate(index, { instrument: event.target.value })}
                    >
                      {INSTRUMENT_OPTIONS.map((option) => (
                        <option key={option.value} value={option.value}>
                          {option.label}
                        </option>
                      ))}
                    </select>
                  </label>
                  <label className="wpss-midi-clip__field">
                    <span>Repeticiones</span>
                    <input
                      type="number"
                      min="1"
                      max="32"
                      value={repeat}
                      onChange={(event) => {
                        const parsed = parseInt(event.target.value, 10)
                        const nextRepeat = Number.isInteger(parsed) && parsed > 0 ? Math.min(parsed, 32) : 1
                        handleUpdate(index, { repeat: nextRepeat })
                      }}
                    />
                  </label>
                  <button
                    type="button"
                    className="button button-link-delete"
                    onClick={() => handleRemove(index)}
                  >
                    Eliminar
                  </button>
                </>
              )}
            </div>
            {readOnly ? (
              <details className="wpss-midi-clip__details">
                <summary>Ver notas</summary>
                <MidiSketch
                  midi={effectiveMidi}
                  readOnly
                  showOnlyActiveRows={showOnlyActiveRows}
                  instrument={instrument}
                />
              </details>
            ) : (
              <MidiSketch
                midi={effectiveMidi}
                onChange={(nextMidi) => handleUpdate(index, { midi: nextMidi })}
                instrument={instrument}
              />
            )}
          </div>
        )
      })}
      {readOnly ? null : (
        <button type="button" className="button button-small wpss-midi-clips__add" onClick={handleAdd}>
          {emptyLabel}
        </button>
      )}
    </div>
  )
}
