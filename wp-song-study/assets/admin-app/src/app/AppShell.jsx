import { useCallback, useEffect, useState } from 'react'
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
  const [showSongList, setShowSongList] = useState(true)

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
    setShowSongList(false)
  }, [dispatch])

  const loadSong = useCallback(
    (id) => {
      if (!id || state.songLoading) {
        return
      }

      setShowSongList(false)
      dispatch({
        type: 'SET_STATE',
        payload: { songLoading: true, selectedSongId: id, feedback: null, error: null },
      })

      api
        .getSong(id)
        .then((response) => {
          const song = response.data || {}
          const bpmDefault = Number.isInteger(parseInt(song.bpm, 10)) ? parseInt(song.bpm, 10) : 120
          const secciones = normalizeSectionsFromApi(song.secciones, bpmDefault)
          const estructura = normalizeStructureFromApi(song.estructura || [], secciones)

          const normalizedSong = {
            ...createEmptySong(),
            id: song.id,
            autor_id: song.autor_id || null,
            titulo: song.titulo || '',
            bpm: bpmDefault,
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
          {showSongList ? (
            <SongList onSelectSong={loadSong} onNewSong={handleNewSong} />
          ) : state.activeTab === 'reading' ? (
            <ReadingView onShowList={() => setShowSongList(true)} />
          ) : (
            <Editor onShowList={() => setShowSongList(true)} />
          )}
        </>
      )}
    </div>
  )
}
