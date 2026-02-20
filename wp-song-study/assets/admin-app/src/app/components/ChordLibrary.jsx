import { useCallback, useMemo, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'

const INSTRUMENTS = [
  { id: 'guitar', label: 'Guitarra' },
  { id: 'piano', label: 'Piano' },
]

const OTHER_QUALITY = { value: 'other', label: 'Otro' }

const ROOT_OPTIONS = ['C', 'C#', 'Db', 'D', 'D#', 'Eb', 'E', 'F', 'F#', 'Gb', 'G', 'G#', 'Ab', 'A', 'A#', 'Bb', 'B']

const getQualitiesForVoices = (config, voices) => {
  const list = config?.qualities?.[voices] || []
  return Array.isArray(list) ? list : []
}

const getParadigms = (config) => {
  const list = config?.paradigms || []
  return Array.isArray(list) ? list : []
}

const createEmptyChord = () => ({
  id: `acorde-${Date.now()}-${Math.floor(Math.random() * 10000)}`,
  name: '',
  aliases: [],
  root_base: '',
  enarmonics: [],
  quality: '',
  quality_other: '',
  voices: 1,
  notes: [],
  voicing: [],
  relations: [],
  paradigm: '',
  evolution: [],
  diagrams: {},
})

const createEmptyShape = (instrument) => {
  if (instrument === 'guitar') {
    return { label: '', baseFret: 1, frets: [], notes: [] }
  }
  return { label: '', notes: [] }
}

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

export default function ChordLibrary() {
  const { state, dispatch, api, wpData } = useAppState()
  const chordsState = state.chords || { library: [], draft: [] }
  const chords = Array.isArray(chordsState.draft) ? chordsState.draft : []
  const camposLibrary = Array.isArray(state.campos?.library) ? state.campos.library : []
  const chordsConfigState = state.chordsConfig || { library: {}, draft: {} }
  const chordsConfig = chordsConfigState.draft || {}
  const isAdmin = !!wpData?.isAdmin
  const [filterRoot, setFilterRoot] = useState('')
  const [filterVoices, setFilterVoices] = useState('')
  const [filterParadigm, setFilterParadigm] = useState('')

  const updateChordsState = useCallback(
    (payload) => {
      dispatch({
        type: 'SET_STATE',
        payload: { chords: { ...chordsState, ...payload } },
      })
    },
    [dispatch, chordsState],
  )

  const setDraft = useCallback(
    (nextDraft) => {
      updateChordsState({ draft: nextDraft })
    },
    [updateChordsState],
  )

  const refreshLibrary = useCallback(() => {
    updateChordsState({ saving: true, error: null, feedback: null })
    api
      .listChords()
      .then((response) => {
        const items = Array.isArray(response.data) ? response.data : []
        updateChordsState({ library: items, draft: JSON.parse(JSON.stringify(items)), saving: false })
      })
      .catch(() => {
        updateChordsState({
          saving: false,
          error: wpData?.strings?.chordsError || 'No fue posible cargar los acordes.',
        })
      })
  }, [api, updateChordsState, wpData])

  const updateConfigState = useCallback(
    (payload) => {
      dispatch({
        type: 'SET_STATE',
        payload: { chordsConfig: { ...chordsConfigState, ...payload } },
      })
    },
    [dispatch, chordsConfigState],
  )

  const handleSaveConfig = useCallback(() => {
    updateConfigState({ saving: true, error: null, feedback: null })
    api
      .saveChordsConfig(chordsConfig)
      .then((response) => {
        const config = response.data?.config || chordsConfig
        updateConfigState({
          saving: false,
          library: config,
          draft: JSON.parse(JSON.stringify(config)),
          feedback: 'Configuración guardada.',
        })
      })
      .catch(() => {
        updateConfigState({
          saving: false,
          error: 'No fue posible guardar la configuración.',
        })
      })
  }, [api, chordsConfig, updateConfigState])

  const handleConfigParadigmChange = (index, patch) => {
    const paradigms = Array.isArray(chordsConfig?.paradigms) ? chordsConfig.paradigms : []
    const next = paradigms.map((item, idx) => (idx === index ? { ...item, ...patch } : item))
    updateConfigState({ draft: { ...chordsConfig, paradigms: next } })
  }

  const handleAddParadigm = () => {
    const paradigms = Array.isArray(chordsConfig?.paradigms) ? chordsConfig.paradigms : []
    updateConfigState({
      draft: {
        ...chordsConfig,
        paradigms: [...paradigms, { id: '', label: '' }],
      },
    })
  }

  const handleRemoveParadigm = (index) => {
    const paradigms = Array.isArray(chordsConfig?.paradigms) ? chordsConfig.paradigms : []
    updateConfigState({
      draft: {
        ...chordsConfig,
        paradigms: paradigms.filter((_, idx) => idx !== index),
      },
    })
  }

  const handleQualityChange = (voices, index, patch) => {
    const qualities = { ...(chordsConfig?.qualities || {}) }
    const list = Array.isArray(qualities[voices]) ? qualities[voices] : []
    const next = list.map((item, idx) => (idx === index ? { ...item, ...patch } : item))
    qualities[voices] = next
    updateConfigState({ draft: { ...chordsConfig, qualities } })
  }

  const handleAddQuality = (voices) => {
    const qualities = { ...(chordsConfig?.qualities || {}) }
    const list = Array.isArray(qualities[voices]) ? qualities[voices] : []
    qualities[voices] = [...list, { value: '', label: '' }]
    updateConfigState({ draft: { ...chordsConfig, qualities } })
  }

  const handleRemoveQuality = (voices, index) => {
    const qualities = { ...(chordsConfig?.qualities || {}) }
    const list = Array.isArray(qualities[voices]) ? qualities[voices] : []
    qualities[voices] = list.filter((_, idx) => idx !== index)
    updateConfigState({ draft: { ...chordsConfig, qualities } })
  }

  const handleSave = useCallback(() => {
    updateChordsState({ saving: true, error: null, feedback: null })
    api
      .saveChords(chords)
      .then((response) => {
        const items = Array.isArray(response.data?.acordes) ? response.data.acordes : chords
        updateChordsState({
          saving: false,
          library: items,
          draft: JSON.parse(JSON.stringify(items)),
          feedback: wpData?.strings?.chordsSaved || 'Acordes actualizados.',
        })
      })
      .catch(() => {
        updateChordsState({
          saving: false,
          error: wpData?.strings?.chordsError || 'No fue posible guardar los acordes.',
        })
      })
  }, [api, chords, updateChordsState, wpData])

  const handleAdd = () => {
    setDraft([...chords, createEmptyChord()])
  }

  const handleRemove = (index) => {
    const next = chords.filter((_, idx) => idx !== index)
    setDraft(next)
  }

  const updateChord = (index, updater) => {
    const next = chords.map((item, idx) => (idx === index ? updater(item) : item))
    setDraft(next)
  }

  const handleDiagramChange = (index, instrument, shapeIndex, shapePatch) => {
    updateChord(index, (item) => {
      const diagrams = item?.diagrams && typeof item.diagrams === 'object' ? item.diagrams : {}
      const currentShapes = Array.isArray(diagrams[instrument]) ? diagrams[instrument] : []
      const nextShapes = currentShapes.map((shape, idx) =>
        idx === shapeIndex ? { ...shape, ...shapePatch } : shape,
      )
      return {
        ...item,
        diagrams: { ...diagrams, [instrument]: nextShapes },
      }
    })
  }

  const handleAddDiagram = (index, instrument) => {
    updateChord(index, (item) => {
      const diagrams = item?.diagrams && typeof item.diagrams === 'object' ? item.diagrams : {}
      const currentShapes = Array.isArray(diagrams[instrument]) ? diagrams[instrument] : []
      return {
        ...item,
        diagrams: { ...diagrams, [instrument]: [...currentShapes, createEmptyShape(instrument)] },
      }
    })
  }

  const handleRemoveDiagram = (index, instrument, shapeIndex) => {
    updateChord(index, (item) => {
      const diagrams = item?.diagrams && typeof item.diagrams === 'object' ? item.diagrams : {}
      const currentShapes = Array.isArray(diagrams[instrument]) ? diagrams[instrument] : []
      const nextShapes = currentShapes.filter((_, idx) => idx !== shapeIndex)
      return {
        ...item,
        diagrams: { ...diagrams, [instrument]: nextShapes },
      }
    })
  }

  const handleRelationChange = (index, relationIndex, patch) => {
    updateChord(index, (item) => {
      const relations = Array.isArray(item.relations) ? item.relations : []
      const next = relations.map((relation, idx) => (idx === relationIndex ? { ...relation, ...patch } : relation))
      return { ...item, relations: next }
    })
  }

  const handleAddRelation = (index) => {
    updateChord(index, (item) => {
      const relations = Array.isArray(item.relations) ? item.relations : []
      return { ...item, relations: [...relations, { campo: '', grado: '', case: 'original' }] }
    })
  }

  const handleRemoveRelation = (index, relationIndex) => {
    updateChord(index, (item) => {
      const relations = Array.isArray(item.relations) ? item.relations : []
      return { ...item, relations: relations.filter((_, idx) => idx !== relationIndex) }
    })
  }

  const emptyState = chords.length === 0
  const canEdit = isAdmin
  const headerLabel = wpData?.strings?.chordsView || 'Acordes'
  const filteredChords = useMemo(() => {
    return chords
      .map((chord, index) => ({ chord, index }))
      .filter(({ chord }) => {
      if (filterRoot && chord.root_base !== filterRoot) {
        return false
      }
      if (filterVoices) {
        const voices = Number.isInteger(chord.voices) ? chord.voices : parseInt(chord.voices, 10)
        if (parseInt(filterVoices, 10) !== voices) {
          return false
        }
      }
      if (filterParadigm && chord.paradigm !== filterParadigm) {
        return false
      }
      return true
    })
  }, [chords, filterRoot, filterVoices, filterParadigm])

  return (
    <section className="wpss-panel wpss-panel--editor">
      <header className="wpss-panel__header">
        <div>
          <h1>{headerLabel}</h1>
          <p className="wpss-panel__meta">
            {chords.length} registros
          </p>
        </div>
        <div className="wpss-panel__actions">
          <button type="button" className="button button-secondary" onClick={refreshLibrary}>
            Actualizar
          </button>
          <button
            type="button"
            className="button button-primary"
            onClick={handleSave}
            disabled={!canEdit || chordsState.saving}
          >
            {chordsState.saving ? 'Guardando…' : 'Guardar cambios'}
          </button>
        </div>
      </header>

      {chordsState.feedback ? <p className="wpss-feedback">{chordsState.feedback}</p> : null}
      {chordsState.error ? <p className="wpss-error">{chordsState.error}</p> : null}

      {!canEdit ? (
        <p className="wpss-empty">No tienes permisos para administrar acordes.</p>
      ) : emptyState ? (
        <p className="wpss-empty">{wpData?.strings?.chordsEmpty || 'Aún no hay acordes registrados.'}</p>
      ) : null}

      {canEdit ? (
        <div className="wpss-chords">
          <div className="wpss-chord-config wpss-card">
            <div className="wpss-card__header">
              <strong>Configuración de acordes</strong>
              <button
                type="button"
                className="button button-primary"
                onClick={handleSaveConfig}
                disabled={chordsConfigState.saving}
              >
                {chordsConfigState.saving ? 'Guardando…' : 'Guardar configuración'}
              </button>
            </div>
            <div className="wpss-card__body">
              <div className="wpss-chord-config__section">
                <div className="wpss-chord-diagrams__header">
                  <strong>Paradigmas</strong>
                  <button type="button" className="button button-small" onClick={handleAddParadigm}>
                    Añadir paradigma
                  </button>
                </div>
                {(getParadigms(chordsConfig)).length ? (
                  <div className="wpss-chord-config__list">
                    {getParadigms(chordsConfig).map((item, index) => (
                      <div key={`paradigm-${index}`} className="wpss-chord-config__row">
                        <input
                          type="text"
                          placeholder="id"
                          value={item.id || ''}
                          onChange={(event) => handleConfigParadigmChange(index, { id: event.target.value })}
                        />
                        <input
                          type="text"
                          placeholder="Etiqueta"
                          value={item.label || ''}
                          onChange={(event) => handleConfigParadigmChange(index, { label: event.target.value })}
                        />
                        <button
                          type="button"
                          className="button button-small button-link-delete"
                          onClick={() => handleRemoveParadigm(index)}
                        >
                          Quitar
                        </button>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="wpss-empty">Sin paradigmas configurados.</p>
                )}
              </div>
              <div className="wpss-chord-config__section">
                <strong>Qualities por voces</strong>
                {[1, 2, 3, 4, 5, 6].map((voices) => (
                  <div key={`qualities-${voices}`} className="wpss-chord-config__group">
                    <div className="wpss-chord-diagrams__header">
                      <span>{voices} voces {voices >= 5 ? '(Extendidos)' : ''}</span>
                      <button
                        type="button"
                        className="button button-small"
                        onClick={() => handleAddQuality(voices)}
                      >
                        Añadir quality
                      </button>
                    </div>
                    {getQualitiesForVoices(chordsConfig, voices).length ? (
                      <div className="wpss-chord-config__list">
                        {getQualitiesForVoices(chordsConfig, voices).map((item, index) => (
                          <div key={`quality-${voices}-${index}`} className="wpss-chord-config__row">
                            <input
                              type="text"
                              placeholder="value"
                              value={item.value || ''}
                              onChange={(event) =>
                                handleQualityChange(voices, index, { value: event.target.value })
                              }
                            />
                            <input
                              type="text"
                              placeholder="Etiqueta"
                              value={item.label || ''}
                              onChange={(event) =>
                                handleQualityChange(voices, index, { label: event.target.value })
                              }
                            />
                            <button
                              type="button"
                              className="button button-small button-link-delete"
                              onClick={() => handleRemoveQuality(voices, index)}
                            >
                              Quitar
                            </button>
                          </div>
                        ))}
                      </div>
                    ) : (
                      <p className="wpss-empty">Sin qualities para {voices} voces.</p>
                    )}
                  </div>
                ))}
              </div>
            </div>
          </div>

          <div className="wpss-chord-filters">
            <label className="wpss-field">
              <span>Nota base</span>
              <select value={filterRoot} onChange={(event) => setFilterRoot(event.target.value)}>
                <option value="">Todas</option>
                {ROOT_OPTIONS.map((note) => (
                  <option key={`root-${note}`} value={note}>
                    {note}
                  </option>
                ))}
              </select>
            </label>
            <label className="wpss-field">
              <span>Voces</span>
              <select value={filterVoices} onChange={(event) => setFilterVoices(event.target.value)}>
                <option value="">Todas</option>
                {[1, 2, 3, 4, 5, 6].map((value) => (
                  <option key={`voices-${value}`} value={value}>
                    {value}
                  </option>
                ))}
              </select>
            </label>
            <label className="wpss-field">
              <span>Paradigma</span>
              <select value={filterParadigm} onChange={(event) => setFilterParadigm(event.target.value)}>
                <option value="">Todos</option>
                {getParadigms(chordsConfig).map((option) => (
                  <option key={`paradigm-${option.id}`} value={option.id}>
                    {option.label}
                  </option>
                ))}
              </select>
            </label>
            <button
              type="button"
              className="button button-secondary"
              onClick={() => {
                setFilterRoot('')
                setFilterVoices('')
                setFilterParadigm('')
              }}
            >
              Limpiar filtros
            </button>
          </div>
          {filteredChords.map(({ chord, index }) => (
            <div key={chord.id || `chord-${index}`} className="wpss-card wpss-chord-card">
              <div className="wpss-card__header">
                <strong>{chord.name || `Acorde ${index + 1}`}</strong>
                <button
                  type="button"
                  className="button button-link-delete"
                  onClick={() => handleRemove(index)}
                >
                  {wpData?.strings?.chordsRemove || 'Eliminar'}
                </button>
              </div>
              <div className="wpss-card__body">
                <div className="wpss-chord-step">
                  <strong>1. Nota base</strong>
                </div>
                <div className="wpss-field-group">
                  <label className="wpss-field">
                    <span>Nombre principal</span>
                    <input
                      type="text"
                      value={chord.name || ''}
                      onChange={(event) =>
                        updateChord(index, (item) => ({ ...item, name: event.target.value }))
                      }
                    />
                  </label>
                  <label className="wpss-field">
                    <span>Alias (separados por coma)</span>
                    <input
                      type="text"
                      value={formatList(chord.aliases)}
                      onChange={(event) =>
                        updateChord(index, (item) => ({ ...item, aliases: parseList(event.target.value) }))
                      }
                    />
                  </label>
                </div>
                <div className="wpss-field-group">
                  <label className="wpss-field">
                    <span>Nota base</span>
                    <select
                      value={chord.root_base || ''}
                      onChange={(event) =>
                        updateChord(index, (item) => ({ ...item, root_base: event.target.value }))
                      }
                    >
                      <option value="">Seleccionar</option>
                      {ROOT_OPTIONS.map((note) => (
                        <option key={note} value={note}>
                          {note}
                        </option>
                      ))}
                    </select>
                  </label>
                  <label className="wpss-field">
                    <span>Enarmónicos (separados por coma)</span>
                    <input
                      type="text"
                      value={formatList(chord.enarmonics)}
                      onChange={(event) =>
                        updateChord(index, (item) => ({
                          ...item,
                          enarmonics: parseList(event.target.value),
                        }))
                      }
                    />
                  </label>
                  <label className="wpss-field">
                    <span>Voces</span>
                    <input
                      type="number"
                      min="1"
                      max="6"
                      value={chord.voices || 1}
                      onChange={(event) =>
                        updateChord(index, (item) => ({
                          ...item,
                          voices: parseInt(event.target.value, 10) || 1,
                        }))
                      }
                    />
                  </label>
                </div>
                <div className="wpss-chord-step">
                  <strong>2. Voces y paradigma</strong>
                  {Number(chord.voices || 0) >= 5 ? <span>Extendidos</span> : null}
                </div>
                <div className="wpss-field-group">
                  <label className="wpss-field">
                    <span>Voicing (con repeticiones)</span>
                    <input
                      type="text"
                      value={formatList(chord.voicing)}
                      onChange={(event) =>
                        updateChord(index, (item) => ({
                          ...item,
                          voicing: parseList(event.target.value),
                        }))
                      }
                    />
                  </label>
                  <label className="wpss-field">
                    <span>Notas únicas</span>
                    <input
                      type="text"
                      value={formatList(chord.notes)}
                      onChange={(event) =>
                        updateChord(index, (item) => ({ ...item, notes: parseList(event.target.value) }))
                      }
                    />
                  </label>
                </div>
                <div className="wpss-field-group">
                  <label className="wpss-field">
                    <span>Paradigma</span>
                    <select
                      value={chord.paradigm || ''}
                      onChange={(event) =>
                        updateChord(index, (item) => ({
                          ...item,
                          paradigm: event.target.value,
                        }))
                      }
                    >
                      <option value="">Seleccionar</option>
                      {getParadigms(chordsConfig).map((option) => (
                        <option key={option.id} value={option.id}>
                          {option.label}
                        </option>
                      ))}
                    </select>
                  </label>
                  <label className="wpss-field">
                    <span>Quality</span>
                    <select
                      value={chord.quality || ''}
                      onChange={(event) =>
                        updateChord(index, (item) => ({
                          ...item,
                          quality: event.target.value,
                        }))
                      }
                    >
                      <option value="">Sin definir</option>
                      {getQualitiesForVoices(chordsConfig, Number(chord.voices || 0)).map((option) => (
                        <option key={option.value} value={option.value}>
                          {option.label}
                        </option>
                      ))}
                      <option value={OTHER_QUALITY.value}>{OTHER_QUALITY.label}</option>
                    </select>
                  </label>
                  {chord.quality === 'other' ? (
                    <label className="wpss-field">
                      <span>Quality personalizada</span>
                      <input
                        type="text"
                        value={chord.quality_other || ''}
                        onChange={(event) =>
                          updateChord(index, (item) => ({
                            ...item,
                            quality_other: event.target.value,
                          }))
                        }
                      />
                    </label>
                  ) : null}
                  <label className="wpss-field">
                    <span>Camino evolutivo (IDs separados por coma)</span>
                    <input
                      type="text"
                      value={formatList(chord.evolution)}
                      onChange={(event) =>
                        updateChord(index, (item) => ({
                          ...item,
                          evolution: parseList(event.target.value),
                        }))
                      }
                    />
                  </label>
                </div>

                <div className="wpss-chord-relations">
                  <div className="wpss-chord-diagrams__header">
                    <strong>Interpretaciones por campo armónico</strong>
                    <button
                      type="button"
                      className="button button-small"
                      onClick={() => handleAddRelation(index)}
                    >
                      Añadir interpretación
                    </button>
                  </div>
                  {Array.isArray(chord.relations) && chord.relations.length ? (
                    <div className="wpss-chord-relations__list">
                      {chord.relations.map((relation, relationIndex) => (
                        <div key={`relation-${relationIndex}`} className="wpss-chord-relation">
                          <div className="wpss-field-group">
                            <label className="wpss-field">
                              <span>Campo armónico</span>
                              <select
                                value={relation.campo || ''}
                                onChange={(event) =>
                                  handleRelationChange(index, relationIndex, { campo: event.target.value })
                                }
                              >
                                <option value="">Seleccionar</option>
                                {camposLibrary.map((campo) => (
                                  <option key={campo.slug} value={campo.slug}>
                                    {campo.nombre || campo.slug}
                                  </option>
                                ))}
                              </select>
                            </label>
                            <label className="wpss-field">
                              <span>Grado (romanos)</span>
                              <input
                                type="text"
                                value={relation.grado || ''}
                                onChange={(event) =>
                                  handleRelationChange(index, relationIndex, { grado: event.target.value })
                                }
                              />
                            </label>
                            <label className="wpss-field">
                              <span>Mayúsculas</span>
                              <select
                                value={relation.case || 'original'}
                                onChange={(event) =>
                                  handleRelationChange(index, relationIndex, { case: event.target.value })
                                }
                              >
                                <option value="original">Respetar</option>
                                <option value="upper">Forzar mayúscula</option>
                                <option value="lower">Forzar minúscula</option>
                              </select>
                            </label>
                          </div>
                          <button
                            type="button"
                            className="button button-small button-link-delete"
                            onClick={() => handleRemoveRelation(index, relationIndex)}
                          >
                            Quitar
                          </button>
                        </div>
                      ))}
                    </div>
                  ) : (
                    <p className="wpss-empty">Sin interpretaciones registradas.</p>
                  )}
                </div>

                <div className="wpss-chord-diagrams">
                  <strong>Diagramas</strong>
                  {INSTRUMENTS.map((instrument) => {
                    const diagrams = chord?.diagrams?.[instrument.id]
                    const shapes = Array.isArray(diagrams) ? diagrams : []
                    return (
                      <div key={`${chord.id}-${instrument.id}`} className="wpss-chord-diagrams__group">
                        <div className="wpss-chord-diagrams__header">
                          <span>{instrument.label}</span>
                          <button
                            type="button"
                            className="button button-small"
                            onClick={() => handleAddDiagram(index, instrument.id)}
                          >
                            Añadir forma
                          </button>
                        </div>
                        {shapes.length ? (
                          <div className="wpss-chord-diagrams__list">
                            {shapes.map((shape, shapeIndex) => (
                              <div key={`shape-${shapeIndex}`} className="wpss-chord-diagram">
                                <div className="wpss-field-group">
                                  <label className="wpss-field">
                                    <span>Etiqueta</span>
                                    <input
                                      type="text"
                                      value={shape?.label || ''}
                                      onChange={(event) =>
                                        handleDiagramChange(index, instrument.id, shapeIndex, {
                                          label: event.target.value,
                                        })
                                      }
                                    />
                                  </label>
                                  {instrument.id === 'guitar' ? (
                                    <label className="wpss-field">
                                      <span>Traste base</span>
                                      <input
                                        type="number"
                                        min="1"
                                        value={shape?.baseFret || 1}
                                        onChange={(event) =>
                                          handleDiagramChange(index, instrument.id, shapeIndex, {
                                            baseFret: parseInt(event.target.value, 10) || 1,
                                          })
                                        }
                                      />
                                    </label>
                                  ) : null}
                                  <label className="wpss-field">
                                    <span>
                                      {instrument.id === 'guitar'
                                        ? 'Cuerdas (E,A,D,G,B,e)'
                                        : 'Notas (C4,E4,G4)'}
                                    </span>
                                    <input
                                      type="text"
                                      value={
                                        instrument.id === 'guitar'
                                          ? formatFrets(shape?.frets)
                                          : formatList(shape?.notes)
                                      }
                                      onChange={(event) =>
                                        handleDiagramChange(index, instrument.id, shapeIndex,
                                          instrument.id === 'guitar'
                                            ? { frets: parseFrets(event.target.value) }
                                            : { notes: parseList(event.target.value) },
                                        )
                                      }
                                    />
                                  </label>
                                </div>
                                <button
                                  type="button"
                                  className="button button-small button-link-delete"
                                  onClick={() => handleRemoveDiagram(index, instrument.id, shapeIndex)}
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
            </div>
          ))}
        </div>
      ) : null}

      {canEdit ? (
        <div className="wpss-section__actions">
          <button type="button" className="button button-secondary" onClick={handleAdd}>
            {wpData?.strings?.chordsAdd || 'Añadir acorde'}
          </button>
        </div>
      ) : null}
    </section>
  )
}
