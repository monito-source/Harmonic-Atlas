import { useEffect, useMemo, useRef, useState } from 'react'
import { MIDI_DEFAULTS, normalizeMidiData } from '../utils.js'

const NOTE_NAMES = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B']
const MIDI_BASE = 48
const MIDI_RANGE = 36
let sharedAudioContext = null
let sharedMidiClipboard = null

function midiToFreq(pitch) {
  return 440 * 2 ** ((pitch - 69) / 12)
}

function getSharedAudioContext() {
  if (!sharedAudioContext) {
    const Context = window.AudioContext || window.webkitAudioContext
    sharedAudioContext = Context ? new Context() : null
  }
  return sharedAudioContext
}

function pitchToLabel(pitch) {
  const name = NOTE_NAMES[pitch % 12] || 'C'
  const octave = Math.floor(pitch / 12) - 1
  return `${name}${octave}`
}

function playPreviewNote(pitch, instrument = 'basic', velocity = 96) {
  const ctx = getSharedAudioContext()
  if (!ctx) return
  const settings = INSTRUMENT_SETTINGS[instrument] || INSTRUMENT_SETTINGS.basic
  const start = ctx.currentTime + 0.01
  const duration = 0.25
  const osc = ctx.createOscillator()
  const gain = ctx.createGain()
  osc.type = settings.type
  osc.frequency.value = midiToFreq(pitch)
  const velocityGain = Math.min(Math.max(velocity, 1), 127) / 127
  const boost = velocity >= 120 ? 1.8 : 1
  gain.gain.setValueAtTime(0.0001, start)
  gain.gain.exponentialRampToValueAtTime(
    Math.min(settings.sustain * velocityGain * boost, 1.2),
    start + settings.attack,
  )
  gain.gain.exponentialRampToValueAtTime(0.0001, start + duration)
  osc.connect(gain)
  gain.connect(ctx.destination)
  osc.start(start)
  osc.stop(start + duration + settings.decay)
}

export function createDefaultMidi(tempo = MIDI_DEFAULTS.tempo) {
  const normalizedTempo = Number.isInteger(parseInt(tempo, 10)) ? parseInt(tempo, 10) : MIDI_DEFAULTS.tempo
  return { ...MIDI_DEFAULTS, tempo: normalizedTempo, notes: [] }
}

const INSTRUMENT_SETTINGS = {
  basic: { type: 'sine', attack: 0.01, decay: 0.2, sustain: 0.2 },
  piano: { type: 'triangle', attack: 0.005, decay: 0.15, sustain: 0.18 },
  guitar: { type: 'sawtooth', attack: 0.004, decay: 0.12, sustain: 0.14 },
  voice: { type: 'sine', attack: 0.03, decay: 0.08, sustain: 0.16 },
}

export function playMidiData(midi, instrument = 'basic', repeat = 1) {
  const normalized = normalizeMidiData(midi)
  if (!normalized || !normalized.notes.length) {
    return
  }
  const repeatCount = Math.min(Math.max(parseInt(repeat, 10) || 1, 1), 32)
  const settings = INSTRUMENT_SETTINGS[instrument] || INSTRUMENT_SETTINGS.basic
  window.setTimeout(() => {
    const ctx = getSharedAudioContext()
    if (!ctx) return
    const baseTime = ctx.currentTime + 0.05
    const stepDuration = 60 / normalized.tempo / 4
    const loopDuration = normalized.steps * stepDuration

    for (let loop = 0; loop < repeatCount; loop += 1) {
      const loopOffset = loopDuration * loop
      normalized.notes.forEach((note) => {
        const start = baseTime + loopOffset + note.step * stepDuration
        const lengthSteps = Math.max(1, parseInt(note.length, 10) || 1)
        const duration = stepDuration * lengthSteps
        const osc = ctx.createOscillator()
        const gain = ctx.createGain()
        osc.type = settings.type
        osc.frequency.value = midiToFreq(note.pitch)
        const velocity = Number.isInteger(parseInt(note.velocity, 10)) ? parseInt(note.velocity, 10) : 96
        const velocityGain = Math.min(Math.max(velocity, 1), 127) / 127
        const boost = velocity >= 120 ? 1.8 : 1
        gain.gain.setValueAtTime(0.0001, start)
        gain.gain.exponentialRampToValueAtTime(
          Math.min(settings.sustain * velocityGain * boost, 1.2),
          start + settings.attack,
        )
        gain.gain.exponentialRampToValueAtTime(0.0001, start + duration * 0.9 + settings.decay)
        osc.connect(gain)
        gain.connect(ctx.destination)
        osc.start(start)
        osc.stop(start + duration)
      })
    }
  }, 0)
}

export default function MidiSketch({
  midi,
  onChange,
  onRemove,
  readOnly = false,
  showOnlyActiveRows = false,
  instrument = 'basic',
}) {
  const normalized = useMemo(() => normalizeMidiData(midi), [midi])
  const [dragNote, setDragNote] = useState(null)
  const dragRef = useRef(null)
  const [selectMode, setSelectMode] = useState(false)
  const [selectionBox, setSelectionBox] = useState(null)
  const [selectionPreview, setSelectionPreview] = useState(null)
  const selectionRef = useRef(null)
  const moveRef = useRef(null)
  const [cursor, setCursor] = useState(null)
  const [hoverCell, setHoverCell] = useState(null)
  const [tempoInput, setTempoInput] = useState('')
  const [stepsInput, setStepsInput] = useState('')

  if (!normalized) {
    return null
  }

  const { steps, tempo, notes } = normalized
  const pitches = useMemo(
    () => Array.from({ length: MIDI_RANGE }, (_, index) => MIDI_BASE + index),
    []
  )
  const noteSet = useMemo(() => {
    const map = new Map()
    notes.forEach((note) => {
      const length = Math.max(1, parseInt(note.length, 10) || 1)
      for (let i = 0; i < length; i += 1) {
        const key = `${note.step + i}:${note.pitch}`
        map.set(key, { status: i === 0 ? 'head' : 'sustain', velocity: note.velocity })
      }
    })
    if (dragNote && !dragNote.removing) {
      const start = Math.min(dragNote.startStep, dragNote.endStep)
      const end = Math.max(dragNote.startStep, dragNote.endStep)
      for (let step = start; step <= end; step += 1) {
        const key = `${step}:${dragNote.pitch}`
        map.set(key, { status: step === start ? 'head' : 'sustain', velocity: dragNote.velocity })
      }
    }
    return map
  }, [notes, dragNote])
  const activeBox = selectionPreview || selectionBox
  const isCellSelected = (step, pitch) => {
    if (!activeBox) return false
    const minStep = Math.min(activeBox.startStep, activeBox.endStep)
    const maxStep = Math.max(activeBox.startStep, activeBox.endStep)
    const minPitch = Math.min(activeBox.startPitch, activeBox.endPitch)
    const maxPitch = Math.max(activeBox.startPitch, activeBox.endPitch)
    return step >= minStep && step <= maxStep && pitch >= minPitch && pitch <= maxPitch
  }
  const activePitchSet = useMemo(() => {
    const set = new Set()
    notes.forEach((note) => {
      set.add(note.pitch)
    })
    return set
  }, [notes])
  const visiblePitches = useMemo(() => {
    if (!showOnlyActiveRows) {
      return pitches
    }
    return pitches.filter((pitch) => activePitchSet.has(pitch))
  }, [pitches, showOnlyActiveRows, activePitchSet])

  const emitChange = (next) => {
    if (readOnly) {
      return
    }
    if (onChange) {
      onChange(next)
    }
  }

  useEffect(() => {
    if (readOnly) return
    setTempoInput(Number.isInteger(parseInt(tempo, 10)) ? String(tempo) : '')
    setStepsInput(Number.isInteger(parseInt(steps, 10)) ? String(steps) : '')
  }, [tempo, steps, readOnly])

  const handleTempoChange = (event) => {
    if (readOnly) {
      return
    }
    const value = event.target.value
    setTempoInput(value)
    const parsed = parseInt(value, 10)
    if (Number.isInteger(parsed)) {
      emitChange({ ...normalized, tempo: Math.min(Math.max(parsed, 40), 240) })
    }
  }

  const handleTempoBlur = () => {
    if (readOnly) return
    const parsed = parseInt(tempoInput, 10)
    const nextTempo = Number.isInteger(parsed) ? Math.min(Math.max(parsed, 40), 240) : MIDI_DEFAULTS.tempo
    setTempoInput(String(nextTempo))
    emitChange({ ...normalized, tempo: nextTempo })
  }

  const handleClear = () => {
    if (readOnly) {
      return
    }
    emitChange({ ...normalized, notes: [] })
  }

  const handleStepsChange = (event) => {
    if (readOnly) {
      return
    }
    const value = event.target.value
    setStepsInput(value)
    const parsed = parseInt(value, 10)
    const nextSteps = Number.isInteger(parsed) ? Math.min(Math.max(parsed, 4), 128) : null
    if (!nextSteps) return
    const nextNotes = notes
      .map((note) => {
        if (note.step >= nextSteps) {
          return null
        }
        const length = Math.max(1, parseInt(note.length, 10) || 1)
        const nextLength = Math.min(length, nextSteps - note.step)
        return { ...note, length: nextLength }
      })
      .filter(Boolean)
    emitChange({ ...normalized, steps: nextSteps, notes: nextNotes })
  }

  const handleStepsBlur = () => {
    if (readOnly) return
    const parsed = parseInt(stepsInput, 10)
    const nextSteps = Number.isInteger(parsed) ? Math.min(Math.max(parsed, 4), 128) : MIDI_DEFAULTS.steps
    setStepsInput(String(nextSteps))
    const nextNotes = notes
      .map((note) => {
        if (note.step >= nextSteps) {
          return null
        }
        const length = Math.max(1, parseInt(note.length, 10) || 1)
        const nextLength = Math.min(length, nextSteps - note.step)
        return { ...note, length: nextLength }
      })
      .filter(Boolean)
    emitChange({ ...normalized, steps: nextSteps, notes: nextNotes })
  }

  const handleExtendSteps = () => {
    if (readOnly) {
      return
    }
    const nextSteps = Math.min((normalized.steps || MIDI_DEFAULTS.steps) + 16, 128)
    emitChange({ ...normalized, steps: nextSteps })
  }

  const handleCellMouseDown = (step, pitch, event) => {
    if (readOnly) return
    if (!selectMode && event?.shiftKey) {
      const existingIndex = notes.findIndex(
        (note) => note.pitch === pitch && step >= note.step && step < note.step + (note.length || 1)
      )
      if (existingIndex !== -1) {
        const nextNotes = [...notes]
        const current = nextNotes[existingIndex]
        const nextVelocity = current.velocity >= 120 ? 96 : 127
        nextNotes[existingIndex] = { ...current, velocity: nextVelocity }
        emitChange({ ...normalized, notes: nextNotes })
        return
      }
    }
    if (event?.button === 2) {
      event.preventDefault()
      setSelectMode(true)
      const next = { startStep: step, endStep: step, startPitch: pitch, endPitch: pitch }
      selectionRef.current = next
      setSelectionBox(next)
      setSelectionPreview(null)
      return
    }
    setCursor({ step, pitch })
    if (selectMode) {
      const existingIndex = notes.findIndex(
        (note) => note.pitch === pitch && step >= note.step && step < note.step + (note.length || 1)
      )
      if (existingIndex !== -1) {
        const note = notes[existingIndex]
        const box = {
          startStep: note.step,
          endStep: note.step + (note.length || 1) - 1,
          startPitch: note.pitch,
          endPitch: note.pitch,
        }
        setSelectionBox(box)
        moveRef.current = { originStep: step, originPitch: pitch, box }
        return
      }
      const next = { startStep: step, endStep: step, startPitch: pitch, endPitch: pitch }
      selectionRef.current = next
      setSelectionBox(next)
      setSelectionPreview(null)
      return
    }
    if (event?.button === 0) {
      const previewVelocity = event?.shiftKey ? 127 : 96
      playPreviewNote(pitch, instrument, previewVelocity)
    }
    if (selectionBox && isCellSelected(step, pitch)) {
      moveRef.current = {
        originStep: step,
        originPitch: pitch,
        box: selectionBox,
      }
      return
    }
    const existingIndex = notes.findIndex(
      (note) => note.pitch === pitch && step >= note.step && step < note.step + (note.length || 1)
    )
    const removing = existingIndex !== -1
    const next = {
      pitch,
      startStep: step,
      endStep: step,
      removing,
      targetIndex: existingIndex,
      velocity: event?.shiftKey ? 127 : 96,
    }
    dragRef.current = next
    setDragNote(next)
  }

  const handleCellMouseEnter = (step, pitch) => {
    if (readOnly) return
    setHoverCell({ step, pitch })
    if (selectionRef.current) {
      const next = { ...selectionRef.current, endStep: step, endPitch: pitch }
      selectionRef.current = next
      setSelectionBox(next)
      return
    }
    if (moveRef.current) {
      const { originStep, originPitch, box } = moveRef.current
      const deltaStep = step - originStep
      const deltaPitch = pitch - originPitch
      const minPitch = MIDI_BASE
      const maxPitch = MIDI_BASE + MIDI_RANGE - 1
      const startStep = Math.max(0, box.startStep + deltaStep)
      const endStep = Math.min(steps - 1, box.endStep + deltaStep)
      const startPitch = Math.max(minPitch, box.startPitch + deltaPitch)
      const endPitch = Math.min(maxPitch, box.endPitch + deltaPitch)
      setSelectionPreview({ startStep, endStep, startPitch, endPitch })
      return
    }
    if (!dragRef.current) return
    if (dragRef.current.pitch !== pitch) return
    if (dragRef.current.endStep === step) return
    const next = { ...dragRef.current, endStep: step, removing: false }
    dragRef.current = next
    setDragNote(next)
  }

  const finalizeDrag = () => {
    if (selectionRef.current) {
      selectionRef.current = null
      return
    }
    if (moveRef.current) {
      if (readOnly) {
        moveRef.current = null
        setSelectionPreview(null)
        return
      }
      const { originStep, originPitch, box } = moveRef.current
      moveRef.current = null
      const deltaStep = (selectionPreview ? selectionPreview.startStep : box.startStep) - box.startStep
      const deltaPitch = (selectionPreview ? selectionPreview.startPitch : box.startPitch) - box.startPitch
      const minStep = Math.min(box.startStep, box.endStep)
      const maxStep = Math.max(box.startStep, box.endStep)
      const minPitch = Math.min(box.startPitch, box.endPitch)
      const maxPitch = Math.max(box.startPitch, box.endPitch)
      const minPitchLimit = MIDI_BASE
      const maxPitchLimit = MIDI_BASE + MIDI_RANGE - 1
      const nextNotes = []
      const moved = []
      notes.forEach((note) => {
        const headInRange =
          note.step >= minStep && note.step <= maxStep && note.pitch >= minPitch && note.pitch <= maxPitch
        if (headInRange) {
          moved.push({ ...note })
        } else {
          nextNotes.push({ ...note })
        }
      })
      moved.forEach((note) => {
        const nextStep = Math.min(Math.max(note.step + deltaStep, 0), steps - 1)
        const nextPitch = Math.min(Math.max(note.pitch + deltaPitch, minPitchLimit), maxPitchLimit)
        const length = Math.max(1, parseInt(note.length, 10) || 1)
        const nextLength = Math.min(length, steps - nextStep)
        nextNotes.push({ step: nextStep, pitch: nextPitch, length: nextLength, velocity: note.velocity || 96 })
      })
      nextNotes.sort((a, b) => (a.step - b.step) || (a.pitch - b.pitch))
      emitChange({ ...normalized, notes: nextNotes })
      setSelectionPreview(null)
      setSelectionBox({
        startStep: Math.min(box.startStep + deltaStep, box.endStep + deltaStep),
        endStep: Math.max(box.startStep + deltaStep, box.endStep + deltaStep),
        startPitch: Math.min(box.startPitch + deltaPitch, box.endPitch + deltaPitch),
        endPitch: Math.max(box.startPitch + deltaPitch, box.endPitch + deltaPitch),
      })
      return
    }
    const current = dragRef.current
    if (!current) return
    dragRef.current = null
    setDragNote(null)
    if (readOnly) return

    const start = Math.min(current.startStep, current.endStep)
    const end = Math.max(current.startStep, current.endStep)
    const length = Math.max(1, end - start + 1)

    if (current.removing && current.startStep === current.endStep && current.targetIndex >= 0) {
      const nextNotes = [...notes]
      nextNotes.splice(current.targetIndex, 1)
      emitChange({ ...normalized, notes: nextNotes })
      return
    }

    const nextNotes = notes.filter((note) => {
      if (note.pitch !== current.pitch) {
        return true
      }
      const noteEnd = note.step + (note.length || 1) - 1
      return end < note.step || start > noteEnd
    })
    nextNotes.push({ step: start, pitch: current.pitch, length, velocity: current.velocity || 96 })
    nextNotes.sort((a, b) => (a.step - b.step) || (a.pitch - b.pitch))
    emitChange({ ...normalized, notes: nextNotes })
  }

  useEffect(() => {
    if (!dragNote && !selectionRef.current && !moveRef.current) return
    const handleUp = () => finalizeDrag()
    window.addEventListener('mouseup', handleUp)
    return () => window.removeEventListener('mouseup', handleUp)
  }, [dragNote, notes, normalized, selectionBox, cursor])

  const hasSelection = !!selectionBox
  const selectionBounds = selectionBox
    ? {
        minStep: Math.min(selectionBox.startStep, selectionBox.endStep),
        maxStep: Math.max(selectionBox.startStep, selectionBox.endStep),
        minPitch: Math.min(selectionBox.startPitch, selectionBox.endPitch),
        maxPitch: Math.max(selectionBox.startPitch, selectionBox.endPitch),
      }
    : null

  const copySelection = () => {
    if (!selectionBounds) return
    const selectedNotes = notes
      .filter(
        (note) =>
          note.step >= selectionBounds.minStep &&
          note.step <= selectionBounds.maxStep &&
          note.pitch >= selectionBounds.minPitch &&
          note.pitch <= selectionBounds.maxPitch,
      )
      .map((note) => ({
        step: note.step - selectionBounds.minStep,
        pitch: note.pitch - selectionBounds.minPitch,
        length: note.length || 1,
        velocity: note.velocity || 96,
      }))
    sharedMidiClipboard = {
      notes: selectedNotes,
      width: selectionBounds.maxStep - selectionBounds.minStep + 1,
      height: selectionBounds.maxPitch - selectionBounds.minPitch + 1,
    }
  }

  const cutSelection = () => {
    if (readOnly || !selectionBounds) return
    copySelection()
    const nextNotes = notes.filter(
      (note) =>
        note.step < selectionBounds.minStep ||
        note.step > selectionBounds.maxStep ||
        note.pitch < selectionBounds.minPitch ||
        note.pitch > selectionBounds.maxPitch,
    )
    emitChange({ ...normalized, notes: nextNotes })
  }

  const pasteSelection = () => {
    if (readOnly) return
    const clipboard = sharedMidiClipboard
    if (!clipboard || !clipboard.notes.length) return
    const anchorStep = cursor?.step ?? selectionBounds?.minStep ?? 0
    const anchorPitch = cursor?.pitch ?? selectionBounds?.minPitch ?? MIDI_BASE
    const nextNotes = [...notes]
    clipboard.notes.forEach((note) => {
      const step = Math.min(Math.max(anchorStep + note.step, 0), steps - 1)
      const pitch = Math.min(
        Math.max(anchorPitch + note.pitch, MIDI_BASE),
        MIDI_BASE + MIDI_RANGE - 1,
      )
      const length = Math.max(1, parseInt(note.length, 10) || 1)
      const nextLength = Math.min(length, steps - step)
      nextNotes.push({ step, pitch, length: nextLength, velocity: note.velocity || 96 })
    })
    nextNotes.sort((a, b) => (a.step - b.step) || (a.pitch - b.pitch))
    emitChange({ ...normalized, notes: nextNotes })
  }

  useEffect(() => {
    if (readOnly) return undefined
    const handler = (event) => {
      if (!(event.metaKey || event.ctrlKey)) return
      const key = event.key.toLowerCase()
      if (key === 'c') {
        event.preventDefault()
        copySelection()
      } else if (key === 'x') {
        event.preventDefault()
        cutSelection()
      } else if (key === 'v') {
        event.preventDefault()
        pasteSelection()
      }
    }
    window.addEventListener('keydown', handler)
    return () => window.removeEventListener('keydown', handler)
  }, [readOnly, selectionBox, cursor, notes, steps])

  const play = () => {
    playMidiData({ ...normalized, tempo }, instrument)
  }

  return (
    <div className={`wpss-midi ${readOnly ? 'wpss-midi--readonly' : ''}`}>
      <div className="wpss-midi__header">
        <strong>MIDI</strong>
        <div className="wpss-midi__controls">
          {readOnly ? null : (
            <button
              type="button"
              className={`button button-small ${selectMode ? 'is-active' : ''}`}
              onClick={() => {
                setSelectMode((prev) => !prev)
                setSelectionBox(null)
                setSelectionPreview(null)
                selectionRef.current = null
                moveRef.current = null
              }}
            >
              {selectMode ? 'Salir de selección' : 'Seleccionar'}
            </button>
          )}
          {readOnly ? null : (
            <button type="button" className="button button-small" onClick={copySelection} disabled={!hasSelection}>
              Copiar
            </button>
          )}
          {readOnly ? null : (
            <button type="button" className="button button-small" onClick={cutSelection} disabled={!hasSelection}>
              Cortar
            </button>
          )}
          {readOnly ? null : (
            <button
              type="button"
              className="button button-small"
              onClick={pasteSelection}
            >
              Pegar
            </button>
          )}
          <label className="wpss-midi__tempo">
            <span>Tempo</span>
            {readOnly ? (
              <span className="wpss-midi__tempo-value">{tempo} bpm</span>
            ) : (
              <input
                type="number"
                min="40"
                max="240"
                value={tempoInput}
                onChange={handleTempoChange}
                onBlur={handleTempoBlur}
              />
            )}
          </label>
          {readOnly ? null : (
            <label className="wpss-midi__tempo">
              <span>Pasos</span>
              <input
                type="number"
                min="4"
                max="128"
                value={stepsInput}
                onChange={handleStepsChange}
                onBlur={handleStepsBlur}
              />
            </label>
          )}
          {readOnly ? null : (
            <button type="button" className="button button-small" onClick={handleExtendSteps}>
              +16 pasos
            </button>
          )}
          <button type="button" className="button button-small" onClick={play}>
            Reproducir
          </button>
          {readOnly ? null : (
            <button type="button" className="button button-small" onClick={handleClear}>
              Limpiar
            </button>
          )}
          {onRemove && !readOnly ? (
            <button type="button" className="button button-link-delete" onClick={onRemove}>
              Quitar MIDI
            </button>
          ) : null}
        </div>
      </div>
      <div
        className={`wpss-midi__grid ${selectMode ? 'is-selecting' : ''}`}
        role="grid"
        aria-label="Piano roll MIDI"
        style={{ '--wpss-midi-steps': steps }}
        onMouseLeave={() => {
          setHoverCell(null)
          finalizeDrag()
        }}
      >
        {visiblePitches
          .map((pitch) => ({
            pitch,
            label: pitchToLabel(pitch),
          }))
          .reverse()
          .map((row) => (
            <div key={row.pitch} className="wpss-midi__row" role="row">
              <span className="wpss-midi__label" role="rowheader">
                {row.label}
              </span>
              {Array.from({ length: steps }).map((_, step) => {
                const info = noteSet.get(`${step}:${row.pitch}`)
                const active = !!info
                const status = info?.status
                const velocity = info?.velocity || 0
                const isAccent = status === 'head' && velocity >= 120
                return (
                  <button
                    type="button"
                    key={`${row.pitch}-${step}`}
                    className={`wpss-midi__cell ${active ? 'is-active' : ''} ${
                      status === 'sustain' ? 'is-sustain' : ''
                    } ${isAccent ? 'is-accent' : ''}`}
                    aria-pressed={active}
                    onMouseDown={(event) => handleCellMouseDown(step, row.pitch, event)}
                    onMouseEnter={() => handleCellMouseEnter(step, row.pitch)}
                    onContextMenu={(event) => {
                      event.preventDefault()
                      handleCellMouseDown(step, row.pitch, event)
                    }}
                    disabled={readOnly}
                    data-selected={isCellSelected(step, row.pitch) ? 'true' : 'false'}
                  >
                    {!readOnly && hoverCell?.step === step && hoverCell?.pitch === row.pitch ? (
                      <span className="wpss-midi__cell-label">{pitchToLabel(row.pitch)}</span>
                    ) : null}
                  </button>
                )
              })}
            </div>
          ))}
      </div>
    </div>
  )
}
