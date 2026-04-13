import { useCallback, useEffect, useRef, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'
import {
  REHEARSAL_STATUS_OPTIONS,
  TRANSCRIPTION_STATUS_OPTIONS,
  REHEARSAL_STATUS_LABELS,
  TRANSCRIPTION_STATUS_LABELS,
  getStatusLabel,
} from '../songStatus.js'
import SongFiltersPanel from './SongFiltersPanel.jsx'
import { isDriveOperational } from '../driveStatus.js'

const SONG_REFRESH_INTERVAL_MS = 30000

const formatCollectionAssignment = (collection) => {
  if (!collection || typeof collection !== 'object') return ''
  const assignedBy = collection.assigned_by_user_name || ''
  if (collection.assigned_by_author) return assignedBy ? `Transcriptor: ${assignedBy}` : 'Transcriptor'
  return assignedBy ? `Asignó: ${assignedBy}` : ''
}

export default function SongList({ onSelectSong, onNewSong }) {
  const { state, dispatch, api, wpData } = useAppState()
  const [statusSavingMap, setStatusSavingMap] = useState({})
  const [listRefreshing, setListRefreshing] = useState(false)
  const listRefreshInFlightRef = useRef(false)
  const songsRef = useRef(state.songs)
  const paginationRef = useRef(state.pagination)
  const currentUserId = wpData?.currentUserId || 0
  const filters = state.filters || {}
  const availableCollections = Array.isArray(state.collections?.items) ? state.collections.items : []
  const availableTags = Array.isArray(state.songTags) ? state.songTags : []
  const driveStatus = wpData?.googleDriveStatus || {}
  const driveReady = isDriveOperational(driveStatus)
  const isOwnSong = (song) => Number(song?.autor_id) === Number(currentUserId)
  const canDeleteSong = (song) => isOwnSong(song)
  const handleOpen = (song, targetTab) => {
    const songId = Number(song?.id || 0)
    if (!songId) {
      return
    }
    onSelectSong(song)
    if (targetTab) {
      dispatch({ type: 'SET_STATE', payload: { activeTab: targetTab } })
    }
  }
  const handleReversionSong = (song, event) => {
    event.stopPropagation()
    if (!song?.id) return

    let preserveMedia = false
    if (driveReady) {
      preserveMedia = window.confirm(
        'Tienes Google Drive vinculado. ¿Quieres conservar y copiar los audios/fotos a tu propio Drive en esta reversión?',
      )
    } else {
      const proceedWithoutMedia = window.confirm(
        'No tienes Google Drive vinculado. Esta reversión se creará sin adjuntos de audio o foto. ¿Quieres continuar?',
      )
      if (!proceedWithoutMedia) {
        return
      }
    }

    api
      .reversionSong(song.id, { preserve_media: preserveMedia })
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

        const nextSongs = clonedSong
          ? [clonedSong].concat(state.songs.filter((item) => Number(item.id) !== clonedId))
          : state.songs

        dispatch({
          type: 'SET_STATE',
          payload: {
            songs: nextSongs,
            selectedSongId: clonedId,
            feedback: {
              message: body?.message || 'Reversión creada correctamente.',
              type: 'success',
            },
            error: null,
            pagination: {
              ...state.pagination,
              totalItems: clonedSong ? state.pagination.totalItems + 1 : state.pagination.totalItems,
            },
          },
        })

        handleOpen(clonedSong || { id: clonedId }, 'editor')
      })
      .catch((error) => {
        const message = error?.payload?.message || 'No fue posible crear la reversión.'
      dispatch({ type: 'SET_STATE', payload: { error: message } })
      })
  }

  const markStatusSaving = (songId, statusType, saving) => {
    const key = `${songId}:${statusType}`
    setStatusSavingMap((prev) => {
      if (saving) {
        return { ...prev, [key]: true }
      }
      const next = { ...prev }
      delete next[key]
      return next
    })
  }

  const isStatusSaving = (songId, statusType) => !!statusSavingMap[`${songId}:${statusType}`]

  useEffect(() => {
    songsRef.current = state.songs
    paginationRef.current = state.pagination
  }, [state.pagination, state.songs])

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
    event.stopPropagation()
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
    event.stopPropagation()
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

  useEffect(() => {
    if (!availableCollections.length) {
      api.listCollections().then((response) => {
        const items = Array.isArray(response?.data) ? response.data : []
        dispatch({ type: 'SET_STATE', payload: { collections: { ...state.collections, items } } })
      }).catch(() => {})
    }
    if (!availableTags.length) {
      api.listSongTags().then((response) => {
        dispatch({ type: 'SET_STATE', payload: { songTags: Array.isArray(response?.data) ? response.data : [] } })
      }).catch(() => {})
    }
  }, [api, availableCollections.length, availableTags.length, dispatch, state.collections])

  const refreshSongList = useCallback(async ({ showLoading = false, manual = false } = {}) => {
    if (listRefreshInFlightRef.current) {
      return
    }

    listRefreshInFlightRef.current = true
    if (showLoading) {
      dispatch({ type: 'SET_STATE', payload: { listLoading: true } })
    } else {
      setListRefreshing(true)
    }

    try {
      const currentPagination = paginationRef.current || {}
      const response = await api.listSongs({ page: currentPagination.page || 1, per_page: 20, ...filters })
      const items = Array.isArray(response.data) ? response.data : []
      const totalItems = parseInt(response.headers.get('X-WP-Total'), 10) || items.length
      const totalPages = parseInt(response.headers.get('X-WP-TotalPages'), 10) || 1
      const currentSongsSnapshot = JSON.stringify(Array.isArray(songsRef.current) ? songsRef.current : [])
      const nextSongsSnapshot = JSON.stringify(items)
      const paginationChanged =
        Number(currentPagination.totalItems || 0) !== totalItems
        || Number(currentPagination.totalPages || 0) !== totalPages

      if (currentSongsSnapshot !== nextSongsSnapshot || paginationChanged) {
        dispatch({
          type: 'SET_STATE',
          payload: {
            songs: items,
            pagination: { ...currentPagination, totalItems, totalPages },
          },
        })
      }
    } catch {
      if (manual || showLoading) {
        dispatch({
          type: 'SET_STATE',
          payload: { error: wpData?.strings?.loadSongsError || 'No fue posible cargar canciones.' },
        })
      }
    } finally {
      listRefreshInFlightRef.current = false
      if (showLoading) {
        dispatch({ type: 'SET_STATE', payload: { listLoading: false } })
      } else {
        setListRefreshing(false)
      }
    }
  }, [api, dispatch, filters, wpData])

  useEffect(() => {
    refreshSongList({ showLoading: true })
  }, [filters, refreshSongList, state.pagination.page])

  useEffect(() => {
    const intervalId = window.setInterval(() => {
      if (typeof document !== 'undefined' && document.visibilityState === 'hidden') {
        return
      }
      refreshSongList()
    }, SONG_REFRESH_INTERVAL_MS)

    return () => window.clearInterval(intervalId)
  }, [refreshSongList])

  return (
    <section className="wpss-panel wpss-panel--list">
      <header className="wpss-panel__header">
        <div>
          <h1>{wpData?.strings?.filtersTitle || 'Canciones registradas'}</h1>
          <p className="wpss-panel__meta">{state.pagination.totalItems} registros · React activo</p>
        </div>
        <div className="wpss-panel__actions">
          <button
            className="button button-secondary"
            type="button"
            onClick={() => refreshSongList({ manual: true })}
            disabled={state.listLoading || listRefreshing}
          >
            {state.listLoading || listRefreshing ? 'Refrescando…' : 'Refrescar'}
          </button>
          <button className="button button-secondary" type="button" onClick={onNewSong}>
            {wpData?.strings?.newSong || 'Nueva canción'}
          </button>
        </div>
      </header>

      <SongFiltersPanel
        filters={filters}
        tonicas={wpData?.tonicas || []}
        collections={availableCollections}
        tags={availableTags}
        labels={{
          searchLabel: 'Buscar',
          searchPlaceholder: 'Título, artista o transcriptor',
          tonicaLabel: 'Tónica',
          loansLabel: 'Préstamos',
          modsLabel: 'Modulaciones',
          collectionLabel: 'Colección',
          tagLabel: 'Tag',
          collectionAllLabel: 'Todas',
          tagAllLabel: 'Todos',
          transcriptionLabel: 'Estado de la canción',
          rehearsalLabel: 'Estado de ensayo',
        }}
        onChangeFilters={(nextFilters) =>
          dispatch({
            type: 'SET_STATE',
            payload: {
              filters: nextFilters,
              pagination: { ...state.pagination, page: 1 },
            },
          })}
        onResetFilters={() =>
          dispatch({
            type: 'SET_STATE',
            payload: {
              filters: {
                search: '',
                tonica: '',
                con_prestamos: '',
                con_modulaciones: '',
                coleccion: '',
                tag: '',
                estado_transcripcion: '',
                estado_ensayo: '',
              },
              pagination: { ...state.pagination, page: 1 },
            },
          })}
      />

      {state.listLoading ? (
        <p className="wpss-loading">Cargando canciones…</p>
      ) : (
        <div className="wpss-table-wrapper">
          <table className="widefat">
            <thead>
              <tr>
                <th>Canción</th>
                <th>Secciones</th>
                <th>MIDI</th>
                <th>Versos</th>
                <th>Instrumentales</th>
                <th>Préstamos</th>
                <th>Modulaciones</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              {state.songs.map((song) => (
                <tr
                  key={song.id}
                  className={song.id === state.selectedSongId ? 'is-active' : ''}
                  onClick={() => handleOpen(song, isOwnSong(song) ? 'editor' : 'reading')}
                >
                  <td className="wpss-col-title">
                    <strong>{song.titulo}</strong>
                    <span className="wpss-sub">
                      {song.tonica || song.tonalidad || '—'}
                      {song.campo_armonico ? ` · ${song.campo_armonico}` : ''}
                    </span>
                    <span className="wpss-sub">
                      Autor: {song.autor_nombre || (song.autor_id ? `Usuario ${song.autor_id}` : '—')}
                    </span>
                    <span className="wpss-sub">
                      Transcripción: {getStatusLabel(TRANSCRIPTION_STATUS_LABELS, song.estado_transcripcion)}
                    </span>
                    <span className="wpss-sub">
                      Ensayo (yo): {getStatusLabel(REHEARSAL_STATUS_LABELS, song.estado_ensayo)}
                    </span>
                    {Array.isArray(song.tags) && song.tags.length ? (
                      <span className="wpss-sub">
                        Tags: {song.tags.map((tag) => tag.name).join(' · ')}
                      </span>
                    ) : null}
                    {Array.isArray(song.colecciones) && song.colecciones.length ? (
                      <span className="wpss-sub">
                        Repertorios: {song.colecciones.map((collection) => {
                          const detail = formatCollectionAssignment(collection)
                          return detail ? `${collection.nombre} (${detail})` : collection.nombre
                        }).join(' · ')}
                      </span>
                    ) : null}
                    {song.es_reversion ? (
                      <span className="wpss-sub">
                        Reversión de {song.reversion_origen_titulo || `#${song.reversion_origen_id || '—'}`}
                        {song.reversion_autor_origen_nombre
                          ? ` · Transcripción original: ${song.reversion_autor_origen_nombre}`
                          : ''}
                      </span>
                    ) : null}
                  </td>
                  <td>{song.conteo_secciones || 0}</td>
                  <td>{song.conteo_midi || 0}</td>
                  <td>{song.conteo_versos_normales ?? song.conteo_versos ?? 0}</td>
                  <td>{song.conteo_versos_instrumentales || 0}</td>
                  <td>{song.tiene_prestamos ? 'Sí' : 'No'}</td>
                  <td>{song.tiene_modulaciones ? 'Sí' : 'No'}</td>
                  <td>
                    <div className="wpss-table-actions">
                      {isOwnSong(song) ? (
                        <button
                          type="button"
                          className="button button-small"
                          onClick={(event) => {
                            event.stopPropagation()
                            handleOpen(song, 'editor')
                          }}
                        >
                          Editar
                        </button>
                      ) : (
                        <button
                          type="button"
                          className="button button-small"
                          onClick={(event) => handleReversionSong(song, event)}
                        >
                          Reversionar
                        </button>
                      )}
                      <button
                        type="button"
                        className="button button-small button-secondary"
                        onClick={(event) => {
                          event.stopPropagation()
                          handleOpen(song, 'reading')
                        }}
                      >
                        Leer
                      </button>
                    </div>
                    <div className="wpss-table-statuses">
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
                    {canDeleteSong(song) ? (
                      <button
                        type="button"
                        className="button button-small button-danger"
                        onClick={(event) => {
                          event.stopPropagation()
                          const confirmed = window.confirm(
                            `¿Eliminar "${song.titulo || 'esta canción'}"? Esta acción no se puede deshacer.`,
                          )
                          if (!confirmed) return
                          api
                            .deleteSong(song.id)
                            .then(() => {
                              dispatch({
                                type: 'SET_STATE',
                                payload: {
                                  songs: state.songs.filter(
                                    (item) => Number(item.id) !== Number(song.id),
                                  ),
                                  selectedSongId:
                                    Number(state.selectedSongId) === Number(song.id)
                                      ? null
                                      : state.selectedSongId,
                                },
                              })
                            })
                            .catch((error) => {
                              const message =
                                error?.payload?.message || 'No fue posible eliminar la canción.'
                              dispatch({ type: 'SET_STATE', payload: { error: message } })
                            })
                        }}
                      >
                        Eliminar
                      </button>
                    ) : null}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  )
}
