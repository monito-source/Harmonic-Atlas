import { useCallback, useEffect, useState } from 'react'
import { useAppState } from './StateProvider.jsx'
import { createEmptySong } from './state.js'
import { mapSongToEditingSong } from './songHydration.js'
import SongList from './components/SongList.jsx'
import Editor from './components/Editor.jsx'
import ReadingView from './components/ReadingView.jsx'
import PublicReader from './components/PublicReader.jsx'
import ChordLibrary from './components/ChordLibrary.jsx'
import GroupsManager from './components/GroupsManager.jsx'
import ProjectRehearsalsManager from './components/ProjectRehearsalsManager.jsx'
import DriveSettings from './components/DriveSettings.jsx'
import SongImportExport from './components/SongImportExport.jsx'

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
    (songInput) => {
      const selectedSongId = typeof songInput === 'object' ? Number(songInput?.id) : Number(songInput)
      const selectedSong = typeof songInput === 'object' ? songInput : null
      if (!selectedSongId || state.songLoading) {
        return
      }

      setShowSongList(false)
      dispatch({
        type: 'SET_STATE',
        payload: { songLoading: true, selectedSongId: selectedSongId, feedback: null, error: null },
      })

      api
        .getSong(selectedSongId)
        .then((response) => {
          const song = response.data || {}
          const normalizedSong = mapSongToEditingSong(song)

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
    [api, dispatch, state.songLoading, state.songs, wpData],
  )

  useEffect(() => {
    if ('new' === state.view) {
      handleNewSong()
    }
  }, [handleNewSong, state.view])

  useEffect(() => {
    if (typeof document === 'undefined') {
      return undefined
    }

    const handlePointerDown = (event) => {
      const target = event.target
      if (!(target instanceof Element)) {
        return
      }

      document.querySelectorAll('details.wpss-action-menu[open]').forEach((menu) => {
        if (menu.contains(target)) {
          return
        }
        menu.removeAttribute('open')
      })
    }

    document.addEventListener('pointerdown', handlePointerDown)
    return () => {
      document.removeEventListener('pointerdown', handlePointerDown)
    }
  }, [])

  return (
    <div className="wpss-app-layout">
      {state.view === 'public' ? (
        <PublicReader />
      ) : state.view === 'chords' ? (
        <ChordLibrary />
      ) : state.view === 'groups' ? (
        <GroupsManager />
      ) : state.view === 'project-rehearsals' ? (
        <ProjectRehearsalsManager />
      ) : state.view === 'drive' ? (
        <DriveSettings />
      ) : state.view === 'import-export' ? (
        <SongImportExport />
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
