import { useEffect } from 'react'
import { useAppState } from '../StateProvider.jsx'

export default function SongList({ onSelectSong, onNewSong }) {
  const { state, dispatch, api, wpData } = useAppState()
  const isAdmin = !!wpData?.isAdmin
  const currentUserId = wpData?.currentUserId || 0
  const canDeleteSong = (song) =>
    isAdmin || Number(song?.autor_id) === Number(currentUserId)
  const handleOpen = (songId, targetTab) => {
    onSelectSong(songId)
    if (targetTab) {
      dispatch({ type: 'SET_STATE', payload: { activeTab: targetTab } })
    }
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
                  onClick={() => handleOpen(song.id, 'editor')}
                >
                  <td className="wpss-col-title">
                    <strong>{song.titulo}</strong>
                    <span className="wpss-sub">
                      {song.tonica || song.tonalidad || '—'}
                      {song.campo_armonico ? ` · ${song.campo_armonico}` : ''}
                    </span>
                  </td>
                  <td>{song.conteo_secciones || 0}</td>
                  <td>{song.conteo_midi || 0}</td>
                  <td>{song.conteo_versos_normales ?? song.conteo_versos ?? 0}</td>
                  <td>{song.conteo_versos_instrumentales || 0}</td>
                  <td>{song.tiene_prestamos ? 'Sí' : 'No'}</td>
                  <td>{song.tiene_modulaciones ? 'Sí' : 'No'}</td>
                  <td>
                    <div className="wpss-table-actions">
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
