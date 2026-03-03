import { useEffect, useMemo, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'
import { createEmptySong } from '../state.js'
import { normalizeSectionsFromApi, normalizeStructureFromApi, normalizeVersesFromApi } from '../utils.js'
import ReadingView from './ReadingView.jsx'
import Editor from './Editor.jsx'

export default function PublicReader() {
  const { state, dispatch, api, wpData } = useAppState()
  const [filters, setFilters] = useState({
    tonica: '',
    con_prestamos: '',
    con_modulaciones: '',
    coleccion: '',
  })
  const [collections, setCollections] = useState([])
  const canManage = !!wpData?.canManage
  const currentUserId = wpData?.currentUserId || 0
  const [showDebugIds, setShowDebugIds] = useState(false)
  const isOwnSong = (song) => Number(song?.autor_id) === Number(currentUserId)
  const selectedSong = state.selectedSongId
    ? state.songs.find((song) => Number(song.id) === Number(state.selectedSongId))
    : null
  const canEditSelected = canManage && isOwnSong(selectedSong) && !!state.selectedSongId

  const canDeleteSong = (song) => canManage && isOwnSong(song)

  const tonicas = useMemo(() => wpData?.tonicas || [], [wpData])

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
    let mounted = true
    api
      .listPublicCollections()
      .then((response) => {
        if (!mounted) return
        const items = Array.isArray(response.data) ? response.data : []
        setCollections(items)
      })
      .catch(() => {})

    return () => {
      mounted = false
    }
  }, [api])

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

  return (
    <div className="wpss-public-reader__layout">
      {state.activeTab === 'editor' && canManage ? (
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
                  ? () => dispatch({ type: 'SET_STATE', payload: { activeTab: 'editor' } })
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
                {canManage ? (
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
            {canManage ? (
              <div className="wpss-panel__actions">
                <button type="button" className="button button-primary" onClick={handleNewSong}>
                  {wpData?.strings?.newSong || 'Nueva canción'}
                </button>
              </div>
            ) : null}
          </header>
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
              {state.songs.map((song) => (
                <li key={song.id}>
                  <div className="wpss-public-reader__song-row">
                    <button
                      type="button"
                      className={`wpss-public-reader__song ${song.id === state.selectedSongId ? 'is-active' : ''}`}
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
                    {canManage && isOwnSong(song) ? (
                      <button
                        type="button"
                        className="button button-small"
                        onClick={() => handleEditSong(song.id)}
                      >
                        {wpData?.strings?.editorView || 'Editar'}
                      </button>
                    ) : null}
                    {canManage && !isOwnSong(song) ? (
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
                </li>
              ))}
            </ul>
          )}
        </section>
      )}
    </div>
  )
}
