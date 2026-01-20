import { useCallback, useEffect } from 'react'
import { useAppState } from './StateProvider.jsx'
import { createEmptySong } from './state.js'
import {
  normalizeSectionsFromApi,
  normalizeStructureFromApi,
  normalizeVersesFromApi,
} from './utils.js'
import SongList from './components/SongList.jsx'
import Editor from './components/Editor.jsx'
import ReadingView from './components/ReadingView.jsx'
import PublicReader from './components/PublicReader.jsx'

export default function AppShell() {
  const { state, dispatch, api, wpData } = useAppState()

  const handleNewSong = useCallback(() => {
    dispatch({
      type: 'SET_STATE',
      payload: {
        selectedSongId: null,
        editingSong: createEmptySong(),
        songLoading: false,
        feedback: null,
        error: null,
      },
    })
  }, [dispatch])

  const loadSong = useCallback(
    (id) => {
      if (!id || state.songLoading) {
        return
      }

      dispatch({
        type: 'SET_STATE',
        payload: { songLoading: true, selectedSongId: id, feedback: null, error: null },
      })

      api
        .getSong(id)
        .then((response) => {
          const song = response.data || {}
          const secciones = normalizeSectionsFromApi(song.secciones)
          const estructura = normalizeStructureFromApi(song.estructura || [], secciones)

          const normalizedSong = {
            ...createEmptySong(),
            id: song.id,
            titulo: song.titulo || '',
            tonica: song.tonica || song.tonalidad || '',
            campo_armonico: song.campo_armonico || '',
            campo_armonico_predominante: song.campo_armonico_predominante || '',
            prestamos: Array.isArray(song.prestamos) ? song.prestamos : [],
            modulaciones: Array.isArray(song.modulaciones) ? song.modulaciones : [],
            versos: normalizeVersesFromApi(song.versos),
            secciones,
            estructura,
            tiene_prestamos: !!song.tiene_prestamos,
            tiene_modulaciones: !!song.tiene_modulaciones,
            colecciones: Array.isArray(song.colecciones) ? song.colecciones : [],
            estructuraPersonalizada: true,
          }

          dispatch({
            type: 'SET_STATE',
            payload: {
              editingSong: normalizedSong,
              selectedSongId: song.id,
              songLoading: false,
              feedback: wpData?.strings?.songLoaded
                ? { message: wpData.strings.songLoaded, type: 'success' }
                : null,
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
    },
    [api, dispatch, state.songLoading, wpData],
  )

  useEffect(() => {
    if ('new' === state.view) {
      handleNewSong()
    }
  }, [handleNewSong, state.view])

  return (
    <div className="wpss-app-layout">
      {state.view === 'public' ? (
        <PublicReader />
      ) : (
        <>
          <SongList onSelectSong={loadSong} onNewSong={handleNewSong} />
          {state.activeTab === 'reading' ? <ReadingView /> : <Editor />}
        </>
      )}
    </div>
  )
}
