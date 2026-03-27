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
import ChordLibrary from './components/ChordLibrary.jsx'

const normalizeLoadedTag = (tag) => {
  if (tag === null || typeof tag === 'undefined') return null

  if (typeof tag === 'object') {
    const id = Number(tag?.id ?? tag?.term_id ?? tag?.termId)
    const name = String(tag?.name || tag?.nombre || tag?.label || tag?.slug || '').trim()
    if (!name && !Number.isInteger(id)) {
      return null
    }
    return {
      id: Number.isInteger(id) ? id : null,
      name: name || `Tag ${id}`,
      slug: String(tag?.slug || name || '').trim().toLowerCase(),
    }
  }

  const raw = String(tag).trim()
  if (!raw) return null
  const id = Number(raw)
  if (Number.isInteger(id) && id > 0) {
    return { id, name: `Tag ${id}`, slug: '' }
  }
  return { id: null, name: raw, slug: raw.toLowerCase() }
}

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
          const songFromList = Array.isArray(state.songs)
            ? state.songs.find((item) => Number(item?.id) === Number(selectedSongId))
            : null
          const resolvedTagsRaw = Array.isArray(song?.tags) && song.tags.length
            ? song.tags
            : Array.isArray(song?.item?.tags) && song.item.tags.length
              ? song.item.tags
              : Array.isArray(selectedSong?.tags) && selectedSong.tags.length
                ? selectedSong.tags
            : Array.isArray(songFromList?.tags)
              ? songFromList.tags
              : []
          const resolvedTags = resolvedTagsRaw
            .map((tag) => normalizeLoadedTag(tag))
            .filter(Boolean)
          const bpmDefault = Number.isInteger(parseInt(song.bpm, 10)) ? parseInt(song.bpm, 10) : 120
          const secciones = normalizeSectionsFromApi(song.secciones, bpmDefault)
          const estructura = normalizeStructureFromApi(song.estructura || [], secciones)

          const normalizedSong = {
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
            tags: resolvedTags,
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
