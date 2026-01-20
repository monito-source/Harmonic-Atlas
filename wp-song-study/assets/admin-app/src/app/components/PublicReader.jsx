import { useEffect, useMemo, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'
import { createEmptySong } from '../state.js'
import { normalizeSectionsFromApi, normalizeStructureFromApi, normalizeVersesFromApi } from '../utils.js'
import ReadingView from './ReadingView.jsx'

export default function PublicReader() {
  const { state, dispatch, api, wpData } = useAppState()
  const [filters, setFilters] = useState({
    tonica: '',
    con_prestamos: '',
    con_modulaciones: '',
    coleccion: '',
  })
  const [collections, setCollections] = useState([])

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

  const handleSelectSong = (id) => {
    if (!id) return
    dispatch({ type: 'SET_STATE', payload: { songLoading: true, selectedSongId: id, error: null } })

    api
      .getPublicSong(id)
      .then((response) => {
        const song = response.data || {}
        const secciones = normalizeSectionsFromApi(song.secciones)
        const estructura = normalizeStructureFromApi(song.estructura || [], secciones)

        dispatch({
          type: 'SET_STATE',
          payload: {
            editingSong: {
              ...createEmptySong(),
              id: song.id,
              titulo: song.titulo || '',
              tonica: song.tonica || song.tonalidad || '',
              campo_armonico: song.campo_armonico || '',
              campo_armonico_predominante: song.campo_armonico_predominante || '',
              versos: normalizeVersesFromApi(song.versos),
              secciones,
              estructura,
              estructuraPersonalizada: true,
            },
            songLoading: false,
            activeTab: 'reading',
          },
        })
      })
      .catch(() => {
        dispatch({
          type: 'SET_STATE',
          payload: {
            songLoading: false,
            error: wpData?.strings?.loadSongError || 'No fue posible cargar la canción seleccionada.',
          },
        })
      })
  }

  return (
    <div className="wpss-public-reader__layout">
      <section className="wpss-public-reader__list wpss-panel">
        <header className="wpss-panel__header">
          <div>
            <h1>{wpData?.strings?.filtersTitle || 'Canciones disponibles'}</h1>
            <p className="wpss-panel__meta">{state.songs.length} canciones</p>
          </div>
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
                </button>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section className="wpss-public-reader__reading wpss-panel">
        {state.songLoading ? (
          <p className="wpss-loading">Cargando canción…</p>
        ) : state.selectedSongId ? (
          <ReadingView
            exitLabel={wpData?.strings?.readingExit || 'Volver a la lista'}
            onExit={() => dispatch({ type: 'SET_STATE', payload: { selectedSongId: null } })}
          />
        ) : (
          <p className="wpss-empty">Selecciona una canción para verla aquí.</p>
        )}
      </section>
    </div>
  )
}
