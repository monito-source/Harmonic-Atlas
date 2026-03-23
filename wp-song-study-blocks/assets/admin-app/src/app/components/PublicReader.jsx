import { useCallback, useEffect, useMemo, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'
import { createEmptySong } from '../state.js'
import { normalizeSectionsFromApi, normalizeStructureFromApi, normalizeVersesFromApi } from '../utils.js'
import {
  REHEARSAL_STATUS_OPTIONS,
  TRANSCRIPTION_STATUS_OPTIONS,
  REHEARSAL_STATUS_LABELS,
  TRANSCRIPTION_STATUS_LABELS,
  getStatusLabel,
} from '../songStatus.js'
import ReadingView from './ReadingView.jsx'
import Editor from './Editor.jsx'
import CollectionsManager from './CollectionsManager.jsx'

function formatCollectionAssignment(collection) {
  if (!collection || typeof collection !== 'object') return ''
  const assignedBy = collection.assigned_by_user_name || ''
  if (collection.assigned_by_author) {
    return assignedBy ? `Transcriptor: ${assignedBy}` : 'Transcriptor'
  }
  return assignedBy ? `Asignó: ${assignedBy}` : ''
}

function createAssignRow(defaultAuthorId = 0) {
  return {
    id: `assign-${Date.now()}-${Math.floor(Math.random() * 100000)}`,
    titulo: '',
    artista: '',
    autor_id: defaultAuthorId ? String(defaultAuthorId) : '',
  }
}

export default function PublicReader() {
  const { state, dispatch, api, wpData } = useAppState()
  const [filters, setFilters] = useState({
    tonica: '',
    con_prestamos: '',
    con_modulaciones: '',
    coleccion: '',
    tag: '',
  })
  const [collections, setCollections] = useState([])
  const [tags, setTags] = useState([])
  const [listTab, setListTab] = useState('songs')
  const [colleagues, setColleagues] = useState([])
  const [colleaguesLoading, setColleaguesLoading] = useState(false)
  const [assigning, setAssigning] = useState(false)
  const [assignRows, setAssignRows] = useState(() => [createAssignRow(wpData?.currentUserId || 0)])
  const [statusSavingMap, setStatusSavingMap] = useState({})
  const canManage = !!wpData?.canManage
  const isAdmin = !!wpData?.isAdmin
  const canViewSongbook = wpData?.canRead !== undefined ? !!wpData.canRead : canManage || isAdmin
  const currentUserId = wpData?.currentUserId || 0
  const [showDebugIds, setShowDebugIds] = useState(false)
  const isOwnSong = (song) => Number(song?.autor_id) === Number(currentUserId)
  const selectedSong = state.selectedSongId
    ? state.songs.find((song) => Number(song.id) === Number(state.selectedSongId))
    : null
  const canManageSong = (song) => !!song && (isAdmin || isOwnSong(song))
  const canReversionSong = (song) => !!song && canViewSongbook && (isAdmin || !isOwnSong(song))
  const isCreatingNewSong = state.activeTab === 'editor' && !state.selectedSongId
  const canEditSelected = isCreatingNewSong || (canManageSong(selectedSong) && !!state.selectedSongId)

  const canDeleteSong = (song) => canManageSong(song)

  const markStatusSaving = (songId, type, saving) => {
    const key = `${songId}:${type}`
    setStatusSavingMap((prev) => {
      if (saving) {
        return { ...prev, [key]: true }
      }
      const next = { ...prev }
      delete next[key]
      return next
    })
  }

  const isStatusSaving = (songId, type) => !!statusSavingMap[`${songId}:${type}`]

  const patchSongState = (songId, patch) => {
    dispatch({
      type: 'SET_STATE',
      payload: {
        songs: state.songs.map((song) =>
          Number(song.id) === Number(songId) ? { ...song, ...patch } : song,
        ),
        editingSong:
          Number(state.editingSong?.id) === Number(songId)
            ? { ...state.editingSong, ...patch }
            : state.editingSong,
      },
    })
  }

  const handleTranscriptionStatusChange = (song, nextStatus, event) => {
    if (event?.stopPropagation) event.stopPropagation()
    if (!song?.id || !isOwnSong(song)) return
    markStatusSaving(song.id, 'transcription', true)
    api
      .setSongTranscriptionStatus(song.id, nextStatus)
      .then((response) => {
        const body = response?.data || {}
        const nextSong = body.song && typeof body.song === 'object' ? body.song : null
        patchSongState(
          song.id,
          nextSong || {
            estado_transcripcion: body.estado_transcripcion || nextStatus,
            estado_transcripcion_label:
              body.estado_transcripcion_label
              || getStatusLabel(TRANSCRIPTION_STATUS_LABELS, body.estado_transcripcion || nextStatus),
          },
        )
      })
      .catch((error) => {
        const message =
          error?.payload?.message || 'No fue posible actualizar el estado de transcripción.'
        dispatch({ type: 'SET_STATE', payload: { error: message } })
      })
      .finally(() => {
        markStatusSaving(song.id, 'transcription', false)
      })
  }

  const handleRehearsalStatusChange = (song, nextStatus, event) => {
    if (event?.stopPropagation) event.stopPropagation()
    if (!song?.id) return
    markStatusSaving(song.id, 'rehearsal', true)
    api
      .setSongRehearsalStatus(song.id, nextStatus)
      .then((response) => {
        const body = response?.data || {}
        const nextSong = body.song && typeof body.song === 'object' ? body.song : null
        patchSongState(
          song.id,
          nextSong || {
            estado_ensayo: body.estado_ensayo || nextStatus,
            estado_ensayo_label:
              body.estado_ensayo_label
              || getStatusLabel(REHEARSAL_STATUS_LABELS, body.estado_ensayo || nextStatus),
          },
        )
      })
      .catch((error) => {
        const message = error?.payload?.message || 'No fue posible actualizar tu estado de ensayo.'
        dispatch({ type: 'SET_STATE', payload: { error: message } })
      })
      .finally(() => {
        markStatusSaving(song.id, 'rehearsal', false)
      })
  }

  const tonicas = useMemo(() => wpData?.tonicas || [], [wpData])
  const handleCollectionsChanged = useCallback((items) => {
    const normalized = Array.isArray(items) ? items : []
    setCollections(normalized)
    setFilters((prev) => {
      if (!prev.coleccion) return prev
      const exists = normalized.some((collection) => String(collection.id) === String(prev.coleccion))
      return exists ? prev : { ...prev, coleccion: '' }
    })
  }, [])
  const loadCollections = useCallback(() => {
    api
      .listPublicCollections()
      .then((response) => {
        const items = Array.isArray(response.data) ? response.data : []
        handleCollectionsChanged(items)
      })
      .catch(() => {})
  }, [api, handleCollectionsChanged])

  useEffect(() => {
    let mounted = true
    dispatch({ type: 'SET_STATE', payload: { listLoading: true } })

    api
      .listPublicSongs({ page: 1, per_page: 100, ...filters })
      .then((response) => {
        if (!mounted) return
        const items = Array.isArray(response.data) ? response.data : []
        dispatch({ type: 'SET_STATE', payload: { songs: items } })
      })
      .catch((error) => {
        if (!mounted) return
        const deniedMessage =
          'Esta pagina no es un producto ni de uso publico. Su acceso es personal y privado para musicos autorizados.'
        const fallbackMessage = wpData?.strings?.loadSongsError || 'No fue posible cargar canciones.'
        const message = error?.status === 403 ? deniedMessage : fallbackMessage
        dispatch({
          type: 'SET_STATE',
          payload: { error: message },
        })
      })
      .finally(() => {
        if (!mounted) return
        dispatch({ type: 'SET_STATE', payload: { listLoading: false } })
      })

    return () => {
      mounted = false
    }
  }, [api, dispatch, filters, wpData])

  useEffect(() => {
    loadCollections()
  }, [loadCollections])

  useEffect(() => {
    api
      .listSongTags()
      .then((response) => {
        setTags(Array.isArray(response.data) ? response.data : [])
      })
      .catch(() => {
        setTags([])
      })
  }, [api])

  useEffect(() => {
    if (!canViewSongbook) return undefined
    let mounted = true
    setColleaguesLoading(true)
    api
      .listColleagues()
      .then((response) => {
        if (!mounted) return
        const items = Array.isArray(response.data) ? response.data : []
        setColleagues(items)
        setAssignRows((prev) =>
          prev.map((row) => {
            if (row.autor_id) return row
            const fallbackId = items[0]?.id || currentUserId || ''
            return { ...row, autor_id: fallbackId ? String(fallbackId) : '' }
          }),
        )
      })
      .catch(() => {
        if (!mounted) return
        setColleagues([])
      })
      .finally(() => {
        if (!mounted) return
        setColleaguesLoading(false)
      })

    return () => {
      mounted = false
    }
  }, [api, canViewSongbook, currentUserId])

  useEffect(() => {
    if (wpData?.initialSongId && !state.selectedSongId && !state.songLoading) {
      handleSelectSong(wpData.initialSongId)
    }
  }, [state.selectedSongId, state.songLoading, wpData])

  const handleNewSong = () => {
    dispatch({
      type: 'SET_STATE',
      payload: {
        editingSong: createEmptySong(),
        selectedSongId: null,
        activeTab: 'editor',
        error: null,
        feedback: null,
      },
    })
  }

  const handleEditSong = (id) => {
    if (!id) return
    dispatch({ type: 'SET_STATE', payload: { songLoading: true, selectedSongId: id, error: null } })

    api
      .getSong(id)
      .then((response) => {
        const song = response.data || {}
        const bpmDefault = Number.isInteger(parseInt(song.bpm, 10)) ? parseInt(song.bpm, 10) : 120
        const secciones = normalizeSectionsFromApi(song.secciones, bpmDefault)
        const estructura = normalizeStructureFromApi(song.estructura || [], secciones)

        dispatch({
          type: 'SET_STATE',
          payload: {
            editingSong: {
              ...createEmptySong(),
              id: song.id,
              autor_id: song.autor_id || null,
              autor_nombre: song.autor_nombre || '',
              es_reversion: !!song.es_reversion,
              reversion_origen_id: song.reversion_origen_id || null,
              reversion_origen_titulo: song.reversion_origen_titulo || '',
              reversion_raiz_id: song.reversion_raiz_id || null,
              reversion_raiz_titulo: song.reversion_raiz_titulo || '',
              reversion_autor_origen_id: song.reversion_autor_origen_id || null,
              reversion_autor_origen_nombre: song.reversion_autor_origen_nombre || '',
              estado_transcripcion: song.estado_transcripcion || 'sin_iniciar',
              estado_transcripcion_label: song.estado_transcripcion_label || 'Sin iniciar',
              estado_ensayo: song.estado_ensayo || 'sin_ensayar',
              estado_ensayo_label: song.estado_ensayo_label || 'No ensayada',
              titulo: song.titulo || '',
              tonica: song.tonica || song.tonalidad || '',
              campo_armonico: song.campo_armonico || '',
              campo_armonico_predominante: song.campo_armonico_predominante || '',
              ficha_autores: song.ficha_autores || '',
              ficha_anio: song.ficha_anio || '',
              ficha_pais: song.ficha_pais || '',
              ficha_estado_legal: song.ficha_estado_legal || '',
              ficha_licencia: song.ficha_licencia || '',
              ficha_fuente_verificacion: song.ficha_fuente_verificacion || '',
              ficha_incompleta: !!song.ficha_incompleta,
              ficha_incompleta_motivo: song.ficha_incompleta_motivo || '',
              prestamos: Array.isArray(song.prestamos) ? song.prestamos : [],
              modulaciones: Array.isArray(song.modulaciones) ? song.modulaciones : [],
              versos: normalizeVersesFromApi(song.versos, bpmDefault),
              secciones,
              estructura,
              estructuraPersonalizada: true,
              tiene_prestamos: !!song.tiene_prestamos,
              tiene_modulaciones: !!song.tiene_modulaciones,
              colecciones: Array.isArray(song.colecciones) ? song.colecciones : [],
            },
            songLoading: false,
            activeTab: 'editor',
          },
        })
      })
      .catch((error) => {
        const payloadMessage = error?.payload?.message
        const deniedMessage = payloadMessage || 'No puedes editar canciones de otros usuarios.'
        const fallbackMessage =
          wpData?.strings?.loadSongError || 'No fue posible cargar la canción seleccionada.'
        const message = error?.status === 403 ? deniedMessage : fallbackMessage
        dispatch({
          type: 'SET_STATE',
          payload: {
            songLoading: false,
            error: message,
          },
        })
      })
  }

  const handleDeleteSong = (song) => {
    if (!song?.id) return
    const confirmed = window.confirm(`¿Eliminar "${song.titulo || 'esta canción'}"? Esta acción no se puede deshacer.`)
    if (!confirmed) return

    api
      .deleteSong(song.id)
      .then(() => {
        dispatch({
          type: 'SET_STATE',
          payload: {
            songs: state.songs.filter((item) => Number(item.id) !== Number(song.id)),
            selectedSongId:
              Number(state.selectedSongId) === Number(song.id) ? null : state.selectedSongId,
          },
        })
      })
      .catch((error) => {
        const message =
          error?.payload?.message || 'No fue posible eliminar la canción.'
        dispatch({
          type: 'SET_STATE',
          payload: { error: message },
        })
      })
  }

  const handleReversionSong = (song) => {
    if (!song?.id) return

    api
      .reversionSong(song.id)
      .then((response) => {
        const body = response?.data || {}
        const clonedSong = body.song && typeof body.song === 'object' ? body.song : null
        const clonedId = Number(body.id || clonedSong?.id || 0)
        if (!clonedId) {
          dispatch({
            type: 'SET_STATE',
            payload: { error: body?.message || 'No fue posible crear la reversión.' },
          })
          return
        }

        dispatch({
          type: 'SET_STATE',
          payload: {
            songs: clonedSong
              ? [clonedSong].concat(state.songs.filter((item) => Number(item.id) !== clonedId))
              : state.songs,
            selectedSongId: clonedId,
            feedback: { message: body?.message || 'Reversión creada correctamente.', type: 'success' },
            error: null,
          },
        })

        handleEditSong(clonedId)
      })
      .catch((error) => {
        const message = error?.payload?.message || 'No fue posible crear la reversión.'
        dispatch({
          type: 'SET_STATE',
          payload: { error: message },
        })
      })
  }

  const handleSelectSong = (id) => {
    if (!id) return
    dispatch({ type: 'SET_STATE', payload: { songLoading: true, selectedSongId: id, error: null } })

    api
      .getPublicSong(id)
      .then((response) => {
        const song = response.data || {}
        const bpmDefault = Number.isInteger(parseInt(song.bpm, 10)) ? parseInt(song.bpm, 10) : 120
        const secciones = normalizeSectionsFromApi(song.secciones, bpmDefault)
        const estructura = normalizeStructureFromApi(song.estructura || [], secciones)

        dispatch({
          type: 'SET_STATE',
          payload: {
            editingSong: {
              ...createEmptySong(),
              id: song.id,
              autor_id: song.autor_id || null,
              autor_nombre: song.autor_nombre || '',
              es_reversion: !!song.es_reversion,
              reversion_origen_id: song.reversion_origen_id || null,
              reversion_origen_titulo: song.reversion_origen_titulo || '',
              reversion_raiz_id: song.reversion_raiz_id || null,
              reversion_raiz_titulo: song.reversion_raiz_titulo || '',
              reversion_autor_origen_id: song.reversion_autor_origen_id || null,
              reversion_autor_origen_nombre: song.reversion_autor_origen_nombre || '',
              estado_transcripcion: song.estado_transcripcion || 'sin_iniciar',
              estado_transcripcion_label: song.estado_transcripcion_label || 'Sin iniciar',
              estado_ensayo: song.estado_ensayo || 'sin_ensayar',
              estado_ensayo_label: song.estado_ensayo_label || 'No ensayada',
              titulo: song.titulo || '',
              tonica: song.tonica || song.tonalidad || '',
              campo_armonico: song.campo_armonico || '',
              campo_armonico_predominante: song.campo_armonico_predominante || '',
              versos: normalizeVersesFromApi(song.versos, bpmDefault),
              secciones,
              estructura,
              estructuraPersonalizada: true,
            },
            songLoading: false,
            activeTab: 'reading',
          },
        })
      })
      .catch((error) => {
        const deniedMessage =
          'Esta pagina no es un producto ni de uso publico. Su acceso es personal y privado para musicos autorizados.'
        const fallbackMessage = wpData?.strings?.loadSongError || 'No fue posible cargar la canción seleccionada.'
        const message = error?.status === 403 ? deniedMessage : fallbackMessage
        dispatch({
          type: 'SET_STATE',
          payload: {
            songLoading: false,
            error: message,
          },
        })
      })
  }

  const addAssignRow = () => {
    const fallbackId = colleagues[0]?.id || currentUserId || 0
    setAssignRows((prev) => prev.concat(createAssignRow(fallbackId)))
  }

  const removeAssignRow = (rowId) => {
    setAssignRows((prev) => {
      const next = prev.filter((row) => row.id !== rowId)
      if (next.length) return next
      const fallbackId = colleagues[0]?.id || currentUserId || 0
      return [createAssignRow(fallbackId)]
    })
  }

  const updateAssignRow = (rowId, field, value) => {
    setAssignRows((prev) =>
      prev.map((row) => (row.id === rowId ? { ...row, [field]: value } : row)),
    )
  }

  const handleAssignRepertoire = () => {
    const payloadItems = assignRows
      .map((row) => ({
        titulo: (row.titulo || '').trim(),
        artista: (row.artista || '').trim(),
        autor_id: Number(row.autor_id) || 0,
      }))
      .filter((row) => row.titulo)

    if (!payloadItems.length) {
      dispatch({
        type: 'SET_STATE',
        payload: { error: 'Agrega al menos una canción con título para asignar.' },
      })
      return
    }

    setAssigning(true)
    api
      .assignRepertoire(payloadItems)
      .then((response) => {
        const body = response?.data || {}
        const created = Array.isArray(body.created) ? body.created : []
        const message = body?.message || 'Repertorio asignado correctamente.'
        const createdIds = new Set(created.map((song) => Number(song.id)))
        dispatch({
          type: 'SET_STATE',
          payload: {
            songs: created.concat(state.songs.filter((song) => !createdIds.has(Number(song.id)))),
            feedback: {
              message,
              type: body?.errors?.length ? 'warning' : 'success',
            },
            error:
              Array.isArray(body?.errors) && body.errors.length
                ? body.errors.map((error) => error?.message).filter(Boolean).join(' | ')
                : null,
          },
        })
        setAssignRows([createAssignRow(colleagues[0]?.id || currentUserId || 0)])
        setListTab('songs')
      })
      .catch((error) => {
        const message = error?.payload?.message || 'No fue posible asignar el repertorio.'
        dispatch({ type: 'SET_STATE', payload: { error: message } })
      })
      .finally(() => {
        setAssigning(false)
      })
  }

  return (
    <div className="wpss-public-reader__layout">
      {state.activeTab === 'editor' && canEditSelected ? (
        <section className="wpss-public-reader__reading wpss-panel wpss-public-editor">
          {state.songLoading ? (
            <p className="wpss-loading">Cargando canción…</p>
          ) : (
            <Editor
              onShowList={() =>
                dispatch({
                  type: 'SET_STATE',
                  payload: { activeTab: 'reading', selectedSongId: null },
                })
              }
            />
          )}
        </section>
      ) : state.selectedSongId ? (
        <section className="wpss-public-reader__reading wpss-panel">
          {state.songLoading ? (
            <p className="wpss-loading">Cargando canción…</p>
          ) : (
            <ReadingView
              exitLabel={wpData?.strings?.readingExit || 'Volver a la lista'}
              onExit={() => dispatch({ type: 'SET_STATE', payload: { selectedSongId: null } })}
              onEdit={
                canEditSelected
                  ? () => handleEditSong(state.selectedSongId)
                  : null
              }
            />
          )}
        </section>
      ) : (
        <section className="wpss-public-reader__list wpss-panel">
          <header className="wpss-panel__header">
            <div>
              <h1>{wpData?.strings?.filtersTitle || 'Canciones disponibles'}</h1>
              <p className="wpss-panel__meta">
                {state.songs.length} canciones
                {canViewSongbook ? (
                  <button
                    type="button"
                    className="button button-link wpss-public-reader__debug-toggle"
                    onClick={() => setShowDebugIds((prev) => !prev)}
                  >
                    {showDebugIds ? 'Ocultar IDs' : 'Ver IDs'}
                  </button>
                ) : null}
              </p>
            </div>
            {canViewSongbook ? (
              <div className="wpss-panel__actions">
                <button type="button" className="button button-primary" onClick={handleNewSong}>
                  {wpData?.strings?.newSong || 'Nueva canción'}
                </button>
              </div>
            ) : null}
          </header>
          {canViewSongbook ? (
            <div className="wpss-tab-nav wpss-public-reader__tabs">
              <button
                type="button"
                className={`button ${listTab === 'songs' ? 'is-active' : ''}`}
                onClick={() => setListTab('songs')}
              >
                Ver canciones
              </button>
              <button
                type="button"
                className={`button ${listTab === 'assign' ? 'is-active' : ''}`}
                onClick={() => setListTab('assign')}
              >
                Incrustar y asignar repertorio
              </button>
              <button
                type="button"
                className={`button ${listTab === 'collections' ? 'is-active' : ''}`}
                onClick={() => setListTab('collections')}
              >
                Colecciones
              </button>
            </div>
          ) : null}
          {listTab === 'assign' && canViewSongbook ? (
            <section className="wpss-public-reader__assign">
              <p className="wpss-panel__meta">
                Escribe el repertorio pendiente y asigna cada canción al colega que la transcribirá.
              </p>
              {colleaguesLoading ? <p className="wpss-loading">Cargando colegas musicales…</p> : null}
              <div className="wpss-public-reader__assign-list">
                {assignRows.map((row, index) => (
                  <div className="wpss-public-reader__assign-row" key={row.id}>
                    <label>
                      <span>Título</span>
                      <input
                        type="text"
                        value={row.titulo}
                        onChange={(event) => updateAssignRow(row.id, 'titulo', event.target.value)}
                        placeholder={`Canción ${index + 1}`}
                      />
                    </label>
                    <label>
                      <span>Artista</span>
                      <input
                        type="text"
                        value={row.artista}
                        onChange={(event) => updateAssignRow(row.id, 'artista', event.target.value)}
                        placeholder="Artista / referencia"
                      />
                    </label>
                    <label>
                      <span>Colega asignado</span>
                      <select
                        value={row.autor_id}
                        onChange={(event) => updateAssignRow(row.id, 'autor_id', event.target.value)}
                      >
                        <option value="">Seleccionar</option>
                        {colleagues.map((colleague) => (
                          <option key={colleague.id} value={colleague.id}>
                            {colleague.nombre}
                          </option>
                        ))}
                      </select>
                    </label>
                    <button
                      type="button"
                      className="button button-small button-danger"
                      onClick={() => removeAssignRow(row.id)}
                    >
                      Quitar
                    </button>
                  </div>
                ))}
              </div>
              <div className="wpss-public-reader__assign-actions">
                <button type="button" className="button button-secondary" onClick={addAssignRow}>
                  + Añadir canción
                </button>
                <button
                  type="button"
                  className="button button-primary"
                  onClick={handleAssignRepertoire}
                  disabled={assigning || colleaguesLoading}
                >
                  {assigning ? 'Asignando…' : 'Crear y asignar repertorio'}
                </button>
              </div>
            </section>
          ) : listTab === 'collections' && canViewSongbook ? (
            <CollectionsManager
              colleagues={colleagues}
              colleaguesLoading={colleaguesLoading}
              onCollectionsChanged={handleCollectionsChanged}
            />
          ) : (
            <>
              <div className="wpss-filters">
                <label>
                  <span>{wpData?.strings?.filtersTonica || 'Tónica'}</span>
                  <select
                    value={filters.tonica}
                    onChange={(event) => setFilters((prev) => ({ ...prev, tonica: event.target.value }))}
                  >
                    <option value="">{'—'}</option>
                    {tonicas.map((tonica) => (
                      <option key={tonica} value={tonica}>
                        {tonica}
                      </option>
                    ))}
                  </select>
                </label>
                <label>
                  <span>{wpData?.strings?.filtersLoans || 'Préstamos'}</span>
                  <select
                    value={filters.con_prestamos}
                    onChange={(event) => setFilters((prev) => ({ ...prev, con_prestamos: event.target.value }))}
                  >
                    <option value="">{'—'}</option>
                    <option value="1">Con préstamos</option>
                    <option value="0">Sin préstamos</option>
                  </select>
                </label>
                <label>
                  <span>{wpData?.strings?.filtersMods || 'Modulaciones'}</span>
                  <select
                    value={filters.con_modulaciones}
                    onChange={(event) => setFilters((prev) => ({ ...prev, con_modulaciones: event.target.value }))}
                  >
                    <option value="">{'—'}</option>
                    <option value="1">Con modulaciones</option>
                    <option value="0">Sin modulaciones</option>
                  </select>
                </label>
                <label className="wpss-filter--collection">
                  <span>{wpData?.strings?.filtersCollection || 'Colección'}</span>
                  <select
                    value={filters.coleccion}
                    onChange={(event) => setFilters((prev) => ({ ...prev, coleccion: event.target.value }))}
                  >
                    <option value="">{'Todas'}</option>
                    {collections.map((collection) => (
                      <option key={collection.id} value={collection.id}>
                        {collection.nombre}
                      </option>
                    ))}
                  </select>
                </label>
                <label>
                  <span>Tag</span>
                  <select
                    value={filters.tag}
                    onChange={(event) => setFilters((prev) => ({ ...prev, tag: event.target.value }))}
                  >
                    <option value="">Todos</option>
                    {tags.map((tag) => (
                      <option key={tag.id || tag.slug} value={tag.slug}>
                        {tag.name}
                      </option>
                    ))}
                  </select>
                </label>
                <div className="wpss-filters__actions">
                  <button
                    type="button"
                    className="button button-secondary"
                    onClick={() =>
                      setFilters({
                        tonica: '',
                        con_prestamos: '',
                        con_modulaciones: '',
                        coleccion: '',
                        tag: '',
                      })
                    }
                  >
                    {wpData?.strings?.filtersClear || 'Limpiar filtros'}
                  </button>
                </div>
              </div>
              {state.listLoading ? (
                <p className="wpss-loading">Cargando canciones…</p>
              ) : (
                <ul className="wpss-public-reader__songs">
                  {state.songs.map((song) => {
                    const transcriptionStatus = song.estado_transcripcion || 'sin_iniciar'
                    const transcriptionLabel = getStatusLabel(
                      TRANSCRIPTION_STATUS_LABELS,
                      transcriptionStatus,
                    )
                    const rehearsalLabel = getStatusLabel(REHEARSAL_STATUS_LABELS, song.estado_ensayo)
                    const transcriptionBadge =
                      transcriptionStatus === 'verificada'
                        ? `★ ${transcriptionLabel}`
                        : transcriptionLabel

                    return (
                      <li key={song.id}>
                        <div
                          className={`wpss-public-reader__song-row is-status-${transcriptionStatus} ${
                            song.id === state.selectedSongId ? 'is-active' : ''
                          }`}
                        >
                          <button
                            type="button"
                            className="wpss-public-reader__song"
                            onClick={() => handleSelectSong(song.id)}
                          >
                            <strong>{song.titulo}</strong>
                            <span>
                              {song.tonica || song.tonalidad || '—'}
                              {song.campo_armonico ? ` · ${song.campo_armonico}` : ''}
                            </span>
                            <span className="wpss-public-reader__song-meta">
                              Autor: {song.autor_nombre || (song.autor_id ? `Usuario ${song.autor_id}` : '—')}
                            </span>
                            <span className={`wpss-public-reader__status-pill is-${transcriptionStatus}`}>
                              {transcriptionBadge}
                            </span>
                            <span className="wpss-public-reader__song-meta">
                              Ensayo (yo): {rehearsalLabel}
                            </span>
                            {Array.isArray(song.tags) && song.tags.length ? (
                              <span className="wpss-public-reader__song-meta">
                                Tags: {song.tags.map((tag) => tag.name).join(' · ')}
                              </span>
                            ) : null}
                            {Array.isArray(song.colecciones) && song.colecciones.length ? (
                              <span className="wpss-public-reader__song-meta">
                                Repertorios: {song.colecciones.map((collection) => {
                                  const details = formatCollectionAssignment(collection)
                                  return details
                                    ? `${collection.nombre} (${details})`
                                    : collection.nombre
                                }).join(' · ')}
                              </span>
                            ) : null}
                            {song.es_reversion ? (
                              <span className="wpss-public-reader__song-meta">
                                Reversión de {song.reversion_origen_titulo || `#${song.reversion_origen_id || '—'}`}
                                {song.reversion_autor_origen_nombre
                                  ? ` · Original: ${song.reversion_autor_origen_nombre}`
                                  : ''}
                              </span>
                            ) : null}
                            {showDebugIds ? (
                              <span className="wpss-public-reader__song-meta">
                                ID canción {song.id} · Autor {song.autor_id || '—'} · Yo {currentUserId || '—'}
                              </span>
                            ) : null}
                          </button>
                          <div className="wpss-public-reader__song-actions">
                            {canManageSong(song) ? (
                              <button
                                type="button"
                                className="button button-small"
                                onClick={() => handleEditSong(song.id)}
                              >
                                {wpData?.strings?.editorView || 'Editar'}
                              </button>
                            ) : null}
                            {canReversionSong(song) ? (
                              <button
                                type="button"
                                className="button button-small button-secondary"
                                onClick={() => handleReversionSong(song)}
                              >
                                Reversionar
                              </button>
                            ) : null}
                            {canDeleteSong(song) ? (
                              <button
                                type="button"
                                className="button button-small button-danger"
                                onClick={() => handleDeleteSong(song)}
                              >
                                Eliminar
                              </button>
                            ) : null}
                          </div>
                          {canViewSongbook ? (
                            <div className="wpss-public-reader__song-status-controls">
                              {isOwnSong(song) ? (
                                <label>
                                  <span>Transcripción</span>
                                  <select
                                    value={song.estado_transcripcion || 'sin_iniciar'}
                                    disabled={isStatusSaving(song.id, 'transcription')}
                                    onClick={(event) => event.stopPropagation()}
                                    onChange={(event) =>
                                      handleTranscriptionStatusChange(song, event.target.value, event)
                                    }
                                  >
                                    {TRANSCRIPTION_STATUS_OPTIONS.map((option) => (
                                      <option key={option.id} value={option.id}>
                                        {option.label}
                                      </option>
                                    ))}
                                  </select>
                                </label>
                              ) : null}
                              <label>
                                <span>Ensayo (yo)</span>
                                <select
                                  value={song.estado_ensayo || 'sin_ensayar'}
                                  disabled={isStatusSaving(song.id, 'rehearsal')}
                                  onClick={(event) => event.stopPropagation()}
                                  onChange={(event) =>
                                    handleRehearsalStatusChange(song, event.target.value, event)
                                  }
                                >
                                  {REHEARSAL_STATUS_OPTIONS.map((option) => (
                                    <option key={option.id} value={option.id}>
                                      {option.label}
                                    </option>
                                  ))}
                                </select>
                              </label>
                            </div>
                          ) : null}
                        </div>
                      </li>
                    )
                  })}
                </ul>
              )}
            </>
          )}
        </section>
      )}
    </div>
  )
}
