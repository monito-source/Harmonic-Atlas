import { useEffect, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'
import {
  REHEARSAL_STATUS_OPTIONS,
  TRANSCRIPTION_STATUS_OPTIONS,
  REHEARSAL_STATUS_LABELS,
  TRANSCRIPTION_STATUS_LABELS,
  getStatusLabel,
} from '../songStatus.js'

export default function SongList({ onSelectSong, onNewSong }) {
  const { state, dispatch, api, wpData } = useAppState()
  const [statusSavingMap, setStatusSavingMap] = useState({})
  const currentUserId = wpData?.currentUserId || 0
  const isOwnSong = (song) => Number(song?.autor_id) === Number(currentUserId)
  const canDeleteSong = (song) => isOwnSong(song)
  const handleOpen = (songId, targetTab) => {
    onSelectSong(songId)
    if (targetTab) {
      dispatch({ type: 'SET_STATE', payload: { activeTab: targetTab } })
    }
  }
  const handleReversionSong = (song, event) => {
    event.stopPropagation()
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

        handleOpen(clonedId, 'editor')
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
    let mounted = true
    dispatch({ type: 'SET_STATE', payload: { listLoading: true } })

    api
      .listSongs({ page: state.pagination.page, per_page: 20 })
      .then((response) => {
        if (!mounted) return
        const items = Array.isArray(response.data) ? response.data : []
        const totalItems = parseInt(response.headers.get('X-WP-Total'), 10) || items.length
        const totalPages = parseInt(response.headers.get('X-WP-TotalPages'), 10) || 1

        dispatch({
          type: 'SET_STATE',
          payload: {
            songs: items,
            pagination: { ...state.pagination, totalItems, totalPages },
          },
        })
      })
      .catch(() => {
        if (!mounted) return
        dispatch({
          type: 'SET_STATE',
          payload: { error: wpData?.strings?.loadSongsError || 'No fue posible cargar canciones.' },
        })
      })
      .finally(() => {
        if (!mounted) return
        dispatch({ type: 'SET_STATE', payload: { listLoading: false } })
      })

    return () => {
      mounted = false
    }
  }, [api, dispatch, state.pagination.page, wpData])

  return (
    <section className="wpss-panel wpss-panel--list">
      <header className="wpss-panel__header">
        <div>
          <h1>{wpData?.strings?.filtersTitle || 'Canciones registradas'}</h1>
          <p className="wpss-panel__meta">{state.pagination.totalItems} registros · React activo</p>
        </div>
        <div className="wpss-panel__actions">
          <button className="button button-secondary" type="button" onClick={onNewSong}>
            {wpData?.strings?.newSong || 'Nueva canción'}
          </button>
        </div>
      </header>

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
                  onClick={() => handleOpen(song.id, isOwnSong(song) ? 'editor' : 'reading')}
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
                            handleOpen(song.id, 'editor')
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
                          handleOpen(song.id, 'reading')
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
