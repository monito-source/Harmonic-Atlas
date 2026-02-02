import { useEffect, useMemo, useRef, useState } from 'react'
import { MIDI_DEFAULTS, normalizeMidiData } from '../utils.js'

const NOTE_NAMES = ['C', 'C#', 'D', 'D#', 'E', 'F', 'F#', 'G', 'G#', 'A', 'A#', 'B']
const MIDI_BASE = 48
const MIDI_RANGE = 36
let sharedAudioContext = null
let sharedMidiClipboard = null
let activePlayback = null
let activePlaybackKey = null
let activePlaybackTimeout = null
let activePlaybackOnStop = null

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

function createPlaybackController() {
  const controller = {
    stopped: false,
    durationMs: 0,
    oscillators: new Set(),
    timeouts: new Set(),
    addTimeout(id) {
      this.timeouts.add(id)
    },
    addOscillator(osc) {
      this.oscillators.add(osc)
      osc.addEventListener('ended', () => {
        this.oscillators.delete(osc)
      })
    },
    registerDuration(ms) {
      if (ms > this.durationMs) {
        this.durationMs = ms
      }
    },
    stop() {
      if (this.stopped) return
      this.stopped = true
      this.timeouts.forEach((id) => clearTimeout(id))
      this.timeouts.clear()
      this.oscillators.forEach((osc) => {
        try {
          osc.stop()
        } catch {
          // Ignore already-stopped oscillators.
        }
      })
      this.oscillators.clear()
    },
  }
  return controller
}

function getMidiDurationMs(midi, repeat = 1, defaultTempo = MIDI_DEFAULTS.tempo) {
  const normalized = normalizeMidiData(midi, defaultTempo)
  if (!normalized) {
    return 0
  }
  const repeatCount = Math.min(Math.max(parseInt(repeat, 10) || 1, 1), 32)
  const stepDuration = 60 / normalized.tempo / 4
  const loopDuration = normalized.steps * stepDuration
  return Math.max(0, loopDuration * repeatCount * 1000 + 60)
}

function stopActivePlayback() {
  if (activePlaybackTimeout) {
    clearTimeout(activePlaybackTimeout)
    activePlaybackTimeout = null
  }
  if (activePlayback) {
    activePlayback.stop()
  }
  if (activePlaybackOnStop) {
    activePlaybackOnStop()
  }
  activePlayback = null
  activePlaybackKey = null
  activePlaybackOnStop = null
}

export function togglePlayback(key, startFn, onStop = null) {
  if (activePlayback) {
    if (activePlaybackKey === key) {
      stopActivePlayback()
      return { playing: false }
    }
    stopActivePlayback()
  }
  const controller = startFn()
  if (!controller) {
    return { playing: false }
  }
  activePlayback = controller
  activePlaybackKey = key
  activePlaybackOnStop = onStop
  if (controller.durationMs > 0) {
    activePlaybackTimeout = window.setTimeout(() => {
      stopActivePlayback()
    }, controller.durationMs + 80)
    controller.addTimeout(activePlaybackTimeout)
  }
  return { playing: true, controller }
}

function getClipRepeat(clip, repeatsEnabled) {
  if (!repeatsEnabled) return 1
  const repeatRaw = parseInt(clip?.repeat, 10)
  return Number.isInteger(repeatRaw) && repeatRaw > 0 ? Math.min(repeatRaw, 32) : 1
}

function getGroupRepeat(group, repeatsEnabled) {
  if (!repeatsEnabled) return 1
  const controller =
    group.find((clip) => clip?.link_id && clip?.clip_id && clip.link_id === clip.clip_id)
    || group[0]
  return getClipRepeat(controller, true)
}

export function buildMidiClipGroups(clips, linkedPlayback = true) {
  if (!Array.isArray(clips) || !clips.length) {
    return []
  }
  const hasMidi = (clip) => clip && typeof clip === 'object' && clip.midi
  const available = clips.filter(hasMidi)
  if (!available.length) {
    return []
  }
  if (!linkedPlayback) {
    return available.map((clip) => [clip])
  }
  const groups = []
  const seen = new Set()
  available.forEach((clip) => {
    const linkId = clip?.link_id ? String(clip.link_id) : ''
    if (linkId) {
      if (seen.has(linkId)) return
      const grouped = available.filter((item) => item?.link_id === linkId)
      if (grouped.length) {
        groups.push(grouped)
        seen.add(linkId)
      }
    } else {
      groups.push([clip])
    }
  })
  return groups
}

export function playMidiClipGroupsSequence(
  steps,
  { defaultTempo = MIDI_DEFAULTS.tempo, repeatsEnabled = true, onStepStart = null } = {},
) {
  if (!Array.isArray(steps) || !steps.length) {
    return null
  }
  const controller = createPlaybackController()
  let offsetMs = 0
  steps.forEach((step) => {
    const group = Array.isArray(step) ? step : step?.clips
    if (!Array.isArray(group) || !group.length) {
      return
    }
    const groupRepeat = getGroupRepeat(group, repeatsEnabled)
    const durations = group.map((clip) => getMidiDurationMs(clip?.midi, groupRepeat, defaultTempo))
    const groupDuration = Math.max(0, ...durations)
    const timeoutId = window.setTimeout(() => {
      if (controller.stopped) return
      if (onStepStart && step?.meta) {
        onStepStart(step.meta)
      }
      group.forEach((clip) => {
        playMidiData(clip?.midi, clip?.instrument || 'basic', groupRepeat, {
          controller,
          defaultTempo,
        })
      })
    }, offsetMs)
    controller.addTimeout(timeoutId)
    offsetMs += groupDuration
  })
  controller.durationMs = offsetMs
  return controller
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

export function playMidiData(midi, instrument = 'basic', repeat = 1, options = {}) {
  const defaultTempo = options.defaultTempo ?? MIDI_DEFAULTS.tempo
  const controller = options.controller || createPlaybackController()
  const normalized = normalizeMidiData(midi, defaultTempo)
  if (!normalized) {
    return controller
  }
  const repeatCount = Math.min(Math.max(parseInt(repeat, 10) || 1, 1), 32)
  const durationMs = getMidiDurationMs(normalized, repeatCount, defaultTempo)
  controller.registerDuration(durationMs)
  if (!normalized.notes.length) {
    return controller
  }
  const settings = INSTRUMENT_SETTINGS[instrument] || INSTRUMENT_SETTINGS.basic
  const scheduleId = window.setTimeout(() => {
    if (controller.stopped) return
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
        controller.addOscillator(osc)
        osc.start(start)
        osc.stop(start + duration)
      })
    }
  }, 0)
  controller.addTimeout(scheduleId)
  return controller
}

export default function MidiSketch({
  midi,
  onChange,
  onRemove,
  readOnly = false,
  showOnlyActiveRows = false,
  compactRows = false,
  allowRowToggle = false,
  rowPadding = 6,
  rangePresets = [],
  defaultRange = '',
  lockRange = false,
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
  const [tempoInput, setTempoInput] = useState('')
  const [stepsInput, setStepsInput] = useState('')
  const rafRef = useRef(null)
  const pendingRef = useRef({
    selectionBox: undefined,
    selectionPreview: undefined,
    dragNote: undefined,
  })
  const lastHoverRef = useRef(null)
  const [useCompactRows, setUseCompactRows] = useState(() => !!compactRows)
  const [activeRangeId, setActiveRangeId] = useState(() => String(defaultRange || ''))

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
    if (dragNote && !dragNote.removing) {
      set.add(dragNote.pitch)
    }
    return set
  }, [notes, dragNote])
  const pitchBounds = useMemo(() => {
    if (!activePitchSet.size) {
      return null
    }
    let min = null
    let max = null
    activePitchSet.forEach((pitch) => {
      if (min === null || pitch < min) {
        min = pitch
      }
      if (max === null || pitch > max) {
        max = pitch
      }
    })
    if (min === null || max === null) {
      return null
    }
    return { min, max }
  }, [activePitchSet])
  const rangeConfig = useMemo(() => {
    if (!Array.isArray(rangePresets) || !rangePresets.length) {
      return null
    }
    const found = rangePresets.find((preset) => String(preset.id) === String(activeRangeId))
    if (found) {
      return found
    }
    return rangePresets[0]
  }, [rangePresets, activeRangeId])

  const visiblePitches = useMemo(() => {
    if (!showOnlyActiveRows) {
      if (rangeConfig) {
        const minPitch = Math.max(MIDI_BASE, Math.min(127, parseInt(rangeConfig.min, 10)))
        const maxPitch = Math.max(MIDI_BASE, Math.min(127, parseInt(rangeConfig.max, 10)))
        if (!Number.isInteger(minPitch) || !Number.isInteger(maxPitch)) {
          return pitches
        }
        if (minPitch > maxPitch) {
          return pitches
        }
        return pitches.filter((pitch) => pitch >= minPitch && pitch <= maxPitch)
      }
      if (!useCompactRows || !pitchBounds) {
        return pitches
      }
      const minPitch = Math.max(MIDI_BASE, pitchBounds.min - rowPadding)
      const maxPitch = Math.min(MIDI_BASE + MIDI_RANGE - 1, pitchBounds.max + rowPadding)
      return pitches.filter((pitch) => pitch >= minPitch && pitch <= maxPitch)
    }
    return pitches.filter((pitch) => activePitchSet.has(pitch))
  }, [pitches, showOnlyActiveRows, activePitchSet, useCompactRows, pitchBounds, rowPadding, rangeConfig])

  const emitChange = (next) => {
    if (readOnly) {
      return
    }
    if (onChange) {
      onChange(next)
    }
  }

  const scheduleState = (patch) => {
    pendingRef.current = { ...pendingRef.current, ...patch }
    if (rafRef.current) return
    rafRef.current = window.requestAnimationFrame(() => {
      rafRef.current = null
      const pending = pendingRef.current
      pendingRef.current = {
        selectionBox: undefined,
        selectionPreview: undefined,
        dragNote: undefined,
      }
      if (pending.selectionBox !== undefined) {
        setSelectionBox(pending.selectionBox)
      }
      if (pending.selectionPreview !== undefined) {
        setSelectionPreview(pending.selectionPreview)
      }
      if (pending.dragNote !== undefined) {
        setDragNote(pending.dragNote)
      }
    })
  }

  useEffect(() => {
    if (readOnly) return
    setTempoInput(Number.isInteger(parseInt(tempo, 10)) ? String(tempo) : '')
    setStepsInput(Number.isInteger(parseInt(steps, 10)) ? String(steps) : '')
  }, [tempo, steps, readOnly])

  useEffect(() => {
    setUseCompactRows(!!compactRows)
  }, [compactRows])

  useEffect(() => {
    setActiveRangeId(String(defaultRange || ''))
  }, [defaultRange])

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
    if (selectionRef.current) {
      const next = { ...selectionRef.current, endStep: step, endPitch: pitch }
      selectionRef.current = next
      scheduleState({ selectionBox: next, selectionPreview: null })
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
      scheduleState({ selectionPreview: { startStep, endStep, startPitch, endPitch } })
      return
    }
    if (!dragRef.current) return
    if (dragRef.current.pitch !== pitch) return
    if (dragRef.current.endStep === step) return
    const next = { ...dragRef.current, endStep: step, removing: false }
    dragRef.current = next
    scheduleState({ dragNote: next })
  }

  const getCellFromEvent = (event) => {
    if (!event?.target) return null
    const target = event.target.closest('[data-step][data-pitch]')
    if (!target) return null
    const step = parseInt(target.dataset.step, 10)
    const pitch = parseInt(target.dataset.pitch, 10)
    if (!Number.isInteger(step) || !Number.isInteger(pitch)) return null
    return { step, pitch }
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

  useEffect(() => {
    return () => {
      if (rafRef.current) {
        window.cancelAnimationFrame(rafRef.current)
      }
    }
  }, [])

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

  const [isPlaying, setIsPlaying] = useState(false)
  const playbackKeyRef = useRef(`midi-sketch-${Math.random().toString(36).slice(2, 8)}`)

  const play = () => {
    const result = togglePlayback(
      playbackKeyRef.current,
      () => playMidiData({ ...normalized, tempo }, instrument),
      () => setIsPlaying(false),
    )
    setIsPlaying(result.playing)
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
          {!readOnly && allowRowToggle ? (
            <button
              type="button"
              className={`button button-small ${useCompactRows ? 'is-active' : ''}`}
              onClick={() => setUseCompactRows((prev) => !prev)}
            >
              {useCompactRows ? 'Mostrar todas' : 'Solo activas'}
            </button>
          ) : null}
          {!readOnly && Array.isArray(rangePresets) && rangePresets.length ? (
            <div className="wpss-midi__ranges" role="group" aria-label="Rango MIDI">
              {rangePresets.map((preset) => {
                const id = String(preset.id)
                const isActive = String(activeRangeId) === id
                return (
                  <button
                    key={id}
                    type="button"
                    className={`button button-small ${isActive ? 'is-active' : ''}`}
                    onClick={() => {
                      if (lockRange) {
                        setActiveRangeId(id)
                        return
                      }
                      setActiveRangeId(id)
                    }}
                  >
                    {preset.label || id}
                  </button>
                )
              })}
            </div>
          ) : null}
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
            {isPlaying ? 'Detener' : 'Reproducir'}
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
      <details className="wpss-midi__details" open>
        <summary>{readOnly ? 'Ver grilla MIDI' : 'Editar grilla MIDI'}</summary>
        <div
          className={`wpss-midi__grid ${selectMode ? 'is-selecting' : ''}`}
          role="grid"
          aria-label="Piano roll MIDI"
          style={{ '--wpss-midi-steps': steps }}
          onPointerDown={(event) => {
            if (readOnly) return
            const cell = getCellFromEvent(event)
            if (!cell) return
            handleCellMouseDown(cell.step, cell.pitch, event)
          }}
          onPointerMove={(event) => {
            if (readOnly) return
            if (!selectionRef.current && !moveRef.current && !dragRef.current) return
            const cell = getCellFromEvent(event)
            if (!cell) return
            const key = `${cell.step}:${cell.pitch}`
            if (lastHoverRef.current === key) return
            lastHoverRef.current = key
            handleCellMouseEnter(cell.step, cell.pitch)
          }}
          onContextMenu={(event) => {
            if (readOnly) return
            const cell = getCellFromEvent(event)
            if (!cell) return
            event.preventDefault()
            handleCellMouseDown(cell.step, cell.pitch, event)
          }}
          onMouseLeave={() => {
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
                      disabled={readOnly}
                      data-selected={isCellSelected(step, row.pitch) ? 'true' : 'false'}
                      data-label={row.label}
                      data-step={step}
                      data-pitch={row.pitch}
                    >
                    </button>
                  )
                })}
              </div>
            ))}
        </div>
      </details>
    </div>
  )
}
