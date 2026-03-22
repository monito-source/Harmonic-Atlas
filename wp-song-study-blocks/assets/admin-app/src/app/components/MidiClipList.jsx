import MidiSketch, { createDefaultMidi, playMidiData, togglePlayback } from './MidiSketch.jsx'
import { useEffect, useRef, useState } from 'react'

const INSTRUMENT_OPTIONS = [
  { value: 'basic', label: 'Basico' },
  { value: 'piano', label: 'Piano' },
  { value: 'guitar', label: 'Guitarra' },
  { value: 'voice', label: 'Voz' },
]

function hasMidiNotes(clip) {
  return Array.isArray(clip?.midi?.notes) && clip.midi.notes.length
}

function createClipId() {
  return `clip-${Date.now()}-${Math.floor(Math.random() * 100000)}`
}

function getClipId(clip) {
  const raw = clip?.clip_id
  if (raw === null || raw === undefined) return ''
  const trimmed = String(raw).trim()
  return trimmed ? trimmed.slice(0, 32) : ''
}

function getClipLinkId(clip) {
  const raw = clip?.link_id
  if (raw === null || raw === undefined) return ''
  const trimmed = String(raw).trim()
  return trimmed ? trimmed.slice(0, 32) : ''
}

function isLinkController(clip) {
  const clipId = getClipId(clip)
  const linkId = getClipLinkId(clip)
  return clipId && linkId && clipId === linkId
}

function getClipRepeat(clip) {
  const repeatRaw = parseInt(clip?.repeat, 10)
  return Number.isInteger(repeatRaw) && repeatRaw > 0 ? Math.min(repeatRaw, 32) : 1
}

export default function MidiClipList({
  clips,
  onChange,
  readOnly = false,
  showOnlyActiveRows = false,
  compactRows = false,
  allowRowToggle = false,
  rowPadding = 6,
  rangePresets = [],
  defaultRange = '',
  lockRange = false,
  emptyLabel = 'Añadir MIDI',
  defaultTempo = 120,
  repeatsEnabled = true,
  linkedPlayback = false,
}) {
  const safeClips = Array.isArray(clips) ? clips : []
  const visibleClips = readOnly ? safeClips.filter(hasMidiNotes) : safeClips
  const playbackKeyRef = useRef(`clips-${Math.random().toString(36).slice(2, 8)}`)
  const [playingKey, setPlayingKey] = useState(null)

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
    next.push({
      name,
      instrument: 'basic',
      repeat: 1,
      midi: createDefaultMidi(defaultTempo),
      clip_id: createClipId(),
    })
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

  useEffect(() => {
    if (readOnly) return
    if (!safeClips.length) return
    const needsId = safeClips.some((clip) => !getClipId(clip))
    if (!needsId) return
    const next = safeClips.map((clip) => {
      if (getClipId(clip)) return clip
      return { ...clip, clip_id: createClipId() }
    })
    updateClips(next)
  }, [readOnly, safeClips])

  useEffect(() => {
    if (readOnly) return
    if (!safeClips.length) return
    const groups = new Map()
    safeClips.forEach((clip, index) => {
      const linkId = getClipLinkId(clip)
      if (!linkId) return
      if (!groups.has(linkId)) {
        groups.set(linkId, [])
      }
      groups.get(linkId).push({ clip, index })
    })
    if (!groups.size) return
    let changed = false
    const next = [...safeClips]
    groups.forEach((items, linkId) => {
      const controller = items.find((item) => getClipId(item.clip) === linkId) || items[0]
      const controllerId = getClipId(controller.clip)
      if (!controllerId) return
      items.forEach((item) => {
        const current = getClipLinkId(item.clip)
        if (current !== controllerId) {
          next[item.index] = { ...item.clip, link_id: controllerId }
          changed = true
        }
      })
      if (!getClipLinkId(controller.clip) || getClipLinkId(controller.clip) !== controllerId) {
        next[controller.index] = { ...controller.clip, link_id: controllerId }
        changed = true
      }
    })
    if (changed) {
      updateClips(next)
    }
  }, [readOnly, safeClips])

  const handleLinkToClip = (index, targetIndex) => {
    if (readOnly) return
    const target = safeClips[targetIndex]
    const source = safeClips[index]
    if (!target || !source || index === targetIndex) return
    const controllerId = getClipId(source) || createClipId()
    const next = safeClips.map((clip, clipIndex) => {
      if (clipIndex === index) {
        return { ...clip, clip_id: controllerId, link_id: controllerId }
      }
      if (clipIndex === targetIndex) {
        return { ...clip, link_id: controllerId }
      }
      return clip
    })
    updateClips(next)
  }

  const handleNewLink = (index) => {
    if (readOnly) return
    const controllerId = getClipId(safeClips[index]) || createClipId()
    const next = safeClips.map((clip, clipIndex) => {
      if (clipIndex !== index) return clip
      return { ...clip, clip_id: controllerId, link_id: controllerId }
    })
    updateClips(next)
  }

  const handleClearLink = (index) => {
    if (readOnly) return
    const target = safeClips[index]
    if (!target) return
    const linkId = getClipLinkId(target)
    const clearGroup = linkId && isLinkController(target)
    const next = safeClips.map((clip, clipIndex) => {
      if (!clearGroup && clipIndex !== index) return clip
      if (clearGroup && getClipLinkId(clip) !== linkId) return clip
      const { link_id, ...rest } = clip || {}
      return rest
    })
    updateClips(next)
  }

  const getEffectiveMidi = (clip) => {
    const midi = clip?.midi || null
    if (!midi) return null
    return !Number.isInteger(parseInt(midi.tempo, 10)) ? { ...midi, tempo: defaultTempo } : midi
  }

  const playClip = (clip, overrideRepeat = null, controller = null) => {
    const effectiveMidi = getEffectiveMidi(clip)
    if (!effectiveMidi) return
    const instrument = clip?.instrument || 'basic'
    const repeat = overrideRepeat !== null ? overrideRepeat : getClipRepeat(clip)
    return playMidiData(effectiveMidi, instrument, repeatsEnabled ? repeat : 1, {
      controller,
      defaultTempo,
    })
  }

  const playLinkedClips = (linkId) => {
    const controller =
      safeClips.find((clip) => getClipId(clip) && getClipId(clip) === linkId)
      || safeClips.find((clip) => getClipLinkId(clip) === linkId)
    const controllerRepeat = controller ? getClipRepeat(controller) : 1
    let playbackController = null
    safeClips.forEach((clip) => {
      if (!hasMidiNotes(clip)) return
      if (getClipLinkId(clip) !== linkId) return
      if (!playbackController) {
        playbackController = playClip(clip, controllerRepeat)
      } else {
        playClip(clip, controllerRepeat, playbackController)
      }
    })
    return playbackController
  }

  const handlePlay = (clip) => {
    const key = `${playbackKeyRef.current}-${safeClips.indexOf(clip)}`
    const result = togglePlayback(
      key,
      () => {
        if (!linkedPlayback) {
          return playClip(clip)
        }
        const linkId = getClipLinkId(clip)
        if (!linkId) {
          return playClip(clip)
        }
        return playLinkedClips(linkId)
      },
      () => setPlayingKey(null),
    )
    setPlayingKey(result.playing ? key : null)
  }

  return (
    <div className="wpss-midi-clips">
      {visibleClips.map((clip, index) => {
        const name = clip?.name ? String(clip.name) : `MIDI ${index + 1}`
        const instrument = clip?.instrument || 'basic'
        const repeat = getClipRepeat(clip)
        const clipId = getClipId(clip)
        const linkId = getClipLinkId(clip)
        const isController = isLinkController(clip)
        const playbackKey = `${playbackKeyRef.current}-${index}`
        const isPlaying = playingKey === playbackKey
        const controllerClip =
          linkId
            ? safeClips.find((item) => getClipId(item) === linkId)
              || safeClips.find((item) => getClipLinkId(item) === linkId)
            : null
        const controllerName = controllerClip?.name
          ? String(controllerClip.name)
          : controllerClip
            ? `MIDI ${safeClips.indexOf(controllerClip) + 1}`
            : ''
        const effectiveMidi = getEffectiveMidi(clip)

        return (
          <div key={`clip-${index}`} className="wpss-midi-clip">
            <div className="wpss-midi-clip__header">
              {readOnly ? (
                <>
                  <span className="wpss-midi-clip__title">{name}</span>
                  {linkId ? (
                    <span className="wpss-midi-clip__link">
                      {isController ? 'Controlador' : `Vinculado a ${controllerName || linkId}`}
                    </span>
                  ) : null}
                  <button
                    type="button"
                    className="button button-small"
                    onClick={() => handlePlay(clip)}
                  >
                    {isPlaying ? 'Detener' : 'Reproducir'}
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
            {readOnly ? null : (
              <details className="wpss-midi-clip__advanced">
                <summary>Opciones avanzadas</summary>
                <div className="wpss-midi-clip__advanced-body">
                  <label className="wpss-midi-clip__field">
                    <span>ID MIDI</span>
                    <input type="text" value={clipId} readOnly />
                  </label>
                  {linkId && !isController ? (
                    <div className="wpss-midi-clip__linked-info">
                      <strong>Vinculado a:</strong> {controllerName || linkId}
                    </div>
                  ) : null}
                  {!linkId || isController ? (
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
                  ) : null}
                  {!linkId || isController ? (
                    <label className="wpss-midi-clip__field">
                      <span>Vincular con</span>
                      <select
                        value=""
                        onChange={(event) => {
                          const targetIndex = parseInt(event.target.value, 10)
                          if (Number.isInteger(targetIndex)) {
                            handleLinkToClip(index, targetIndex)
                          }
                          event.target.value = ''
                        }}
                      >
                        <option value="">Selecciona un MIDI</option>
                        {safeClips.map((option, optionIndex) => {
                          if (optionIndex === index) return null
                          const optionName = option?.name ? String(option.name) : `MIDI ${optionIndex + 1}`
                          return (
                            <option key={`link-${optionIndex}`} value={optionIndex}>
                              {optionName}
                            </option>
                          )
                        })}
                      </select>
                    </label>
                  ) : null}
                  {!linkId || isController ? (
                    <div className="wpss-midi-clip__field">
                      <span>Acciones</span>
                      <div className="wpss-midi-clip__actions">
                        <button
                          type="button"
                          className="button button-small"
                          onClick={() => handleNewLink(index)}
                        >
                          Nuevo vinculo
                        </button>
                        {linkId ? (
                          <button
                            type="button"
                            className="button button-small"
                            onClick={() => handleClearLink(index)}
                          >
                            Quitar vinculo
                          </button>
                        ) : null}
                      </div>
                    </div>
                  ) : null}
                  {linkId && isController ? (
                    <div className="wpss-midi-clip__linked-info">
                      <strong>Controla:</strong>{' '}
                      {safeClips
                        .map((item, itemIndex) => {
                          if (itemIndex === index) return null
                          if (getClipLinkId(item) !== linkId) return null
                          return item?.name ? String(item.name) : `MIDI ${itemIndex + 1}`
                        })
                        .filter(Boolean)
                        .join(', ') || 'Sin vinculados'}
                    </div>
                  ) : null}
                </div>
              </details>
            )}
            {readOnly ? (
              <details className="wpss-midi-clip__details">
                <summary>Ver notas</summary>
                <MidiSketch
                  midi={effectiveMidi}
                  readOnly
                  showOnlyActiveRows={showOnlyActiveRows}
                  compactRows={compactRows}
                  allowRowToggle={allowRowToggle}
                  rowPadding={rowPadding}
                  rangePresets={rangePresets}
                  defaultRange={defaultRange}
                  lockRange={lockRange}
                  instrument={instrument}
                />
              </details>
            ) : (
              <MidiSketch
                midi={effectiveMidi}
                onChange={(nextMidi) => handleUpdate(index, { midi: nextMidi })}
                showOnlyActiveRows={showOnlyActiveRows}
                compactRows={compactRows}
                allowRowToggle={allowRowToggle}
                rowPadding={rowPadding}
                rangePresets={rangePresets}
                defaultRange={defaultRange}
                lockRange={lockRange}
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
