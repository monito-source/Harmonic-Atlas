import { useEffect, useMemo, useRef, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'

function formatSeconds(value) {
  const total = Math.max(0, Math.round(Number(value) || 0))
  const minutes = Math.floor(total / 60)
  const seconds = total % 60
  return `${minutes}:${String(seconds).padStart(2, '0')}`
}

function normalizeAttachmentDraft(attachment) {
  if (!attachment || typeof attachment !== 'object') {
    return null
  }

  return {
    ...attachment,
    visibility_group_ids: Array.isArray(attachment.visibility_group_ids)
      ? attachment.visibility_group_ids.map((value) => Number(value)).filter((value) => Number.isFinite(value) && value > 0)
      : [],
    visibility_user_ids: Array.isArray(attachment.visibility_user_ids)
      ? attachment.visibility_user_ids.map((value) => Number(value)).filter((value) => Number.isFinite(value) && value > 0)
      : [],
  }
}

function buildTargetKey(anchorType, sectionId = '', verseIndex = 0, segmentIndex = 0) {
  return [anchorType || 'song', sectionId || '', Number(verseIndex) || 0, Number(segmentIndex) || 0].join(':')
}

function attachmentMatchesTarget(attachment, target) {
  if (!attachment || !target) return false
  if (String(attachment.anchor_type || 'song') !== String(target.anchor_type || 'song')) return false
  if (String(target.anchor_type) === 'section') {
    return String(attachment.section_id || '') === String(target.section_id || '')
  }
  if (String(target.anchor_type) === 'verse') {
    return Number(attachment.verse_index) === Number(target.verse_index)
  }
  if (String(target.anchor_type) === 'segment') {
    return (
      Number(attachment.verse_index) === Number(target.verse_index)
      && Number(attachment.segment_index) === Number(target.segment_index)
    )
  }
  return true
}

function describeTarget(target, sections = [], verses = []) {
  if (!target || typeof target !== 'object') {
    return 'Canción completa'
  }

  if (target.anchor_type === 'section') {
    const section = sections.find((item) => String(item?.id || '') === String(target.section_id || ''))
    return section?.nombre || `Sección ${target.section_id || ''}`.trim()
  }

  if (target.anchor_type === 'verse') {
    const verse = verses[Number(target.verse_index) || 0]
    return `Verso ${Number(verse?.orden || Number(target.verse_index) + 1)}`
  }

  if (target.anchor_type === 'segment') {
    const verse = verses[Number(target.verse_index) || 0]
    const verseLabel = Number(verse?.orden || Number(target.verse_index) + 1)
    return `Fragmento ${Number(target.segment_index) + 1} · Verso ${verseLabel}`
  }

  return 'Canción completa'
}

function createTargetDraftPatch(target) {
  return {
    anchor_type: target.anchor_type,
    section_id: target.anchor_type === 'section' || target.anchor_type === 'segment' ? target.section_id || '' : '',
    verse_index: target.anchor_type === 'verse' || target.anchor_type === 'segment' ? Number(target.verse_index) || 0 : 0,
    segment_index: target.anchor_type === 'segment' ? Number(target.segment_index) || 0 : 0,
  }
}

export default function SongMediaPanel({
  song,
  onChangeSong,
  requestedTarget = null,
  onDismiss = null,
}) {
  const { api, dispatch, wpData } = useAppState()
  const mediaRecorderRef = useRef(null)
  const recordedChunksRef = useRef([])
  const panelRef = useRef(null)
  const titleInputRef = useRef(null)
  const [driveStatus, setDriveStatus] = useState(null)
  const [groups, setGroups] = useState([])
  const [colleagues, setColleagues] = useState([])
  const [loadingContext, setLoadingContext] = useState(true)
  const [uploading, setUploading] = useState(false)
  const [savingAttachmentId, setSavingAttachmentId] = useState(null)
  const [unlinkingAttachmentId, setUnlinkingAttachmentId] = useState(null)
  const [deletingDriveAttachmentId, setDeletingDriveAttachmentId] = useState(null)
  const [attachmentDrafts, setAttachmentDrafts] = useState({})
  const [recording, setRecording] = useState(false)
  const [selectedFile, setSelectedFile] = useState(null)
  const [recordedBlob, setRecordedBlob] = useState(null)
  const [durationSeconds, setDurationSeconds] = useState(0)
  const [error, setError] = useState(null)
  const [draft, setDraft] = useState({
    title: '',
    type: 'audio',
    source_kind: 'import',
    anchor_type: 'song',
    section_id: '',
    verse_index: 0,
    segment_index: 0,
    visibility_mode: 'private',
    visibility_group_ids: [],
    visibility_user_ids: [],
  })

  useEffect(() => {
    setLoadingContext(true)
    Promise.allSettled([
      api.getGoogleDriveStatus(),
      api.listGroups(),
      api.listColleagues(),
    ]).then((results) => {
      const [driveResult, groupsResult, colleaguesResult] = results
      setDriveStatus(driveResult.status === 'fulfilled' ? driveResult.value?.data || {} : {})
      setGroups(groupsResult.status === 'fulfilled' && Array.isArray(groupsResult.value?.data) ? groupsResult.value.data : [])
      setColleagues(colleaguesResult.status === 'fulfilled' && Array.isArray(colleaguesResult.value?.data) ? colleaguesResult.value.data : [])
      setLoadingContext(false)
    })
  }, [api])

  useEffect(() => {
    const source = selectedFile || recordedBlob
    if (!source || draft.type !== 'audio' || typeof Audio === 'undefined') {
      if (!source) setDurationSeconds(0)
      return undefined
    }

    const objectUrl = URL.createObjectURL(source)
    const audio = new Audio()
    const handleLoaded = () => {
      setDurationSeconds(Number.isFinite(audio.duration) ? audio.duration : 0)
      URL.revokeObjectURL(objectUrl)
    }
    const handleError = () => {
      setDurationSeconds(0)
      URL.revokeObjectURL(objectUrl)
    }

    audio.addEventListener('loadedmetadata', handleLoaded)
    audio.addEventListener('error', handleError)
    audio.src = objectUrl

    return () => {
      audio.removeEventListener('loadedmetadata', handleLoaded)
      audio.removeEventListener('error', handleError)
      URL.revokeObjectURL(objectUrl)
    }
  }, [selectedFile, recordedBlob, draft.type])

  useEffect(() => {
    const attachments = Array.isArray(song?.adjuntos) ? song.adjuntos : []
    setAttachmentDrafts((prev) => {
      const next = {}
      attachments.forEach((attachment) => {
        if (!attachment?.id) return
        next[attachment.id] = normalizeAttachmentDraft(attachment) || prev[attachment.id] || attachment
      })
      return next
    })
  }, [song?.adjuntos])

  useEffect(() => {
    if (!requestedTarget?.requestId) {
      return
    }

    setError(null)
    setSelectedFile(null)
    setRecordedBlob(null)
    setDurationSeconds(0)
    setDraft((prev) => ({
      ...prev,
      title: '',
      source_kind: 'import',
      type: requestedTarget.type === 'photo' ? 'photo' : 'audio',
      ...createTargetDraftPatch(requestedTarget),
    }))

    if (typeof window !== 'undefined') {
      window.requestAnimationFrame(() => {
        panelRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' })
        titleInputRef.current?.focus()
      })
    }
  }, [requestedTarget])

  useEffect(() => {
    if (!requestedTarget?.requestId) {
      return undefined
    }

    const handleKeyDown = (event) => {
      if (event.key === 'Escape') {
        onDismiss?.()
      }
    }

    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [onDismiss, requestedTarget])

  const availableVerses = useMemo(() => (Array.isArray(song?.versos) ? song.versos : []), [song?.versos])
  const availableSections = useMemo(() => (Array.isArray(song?.secciones) ? song.secciones : []), [song?.secciones])
  const allAttachments = useMemo(() => (Array.isArray(song?.adjuntos) ? song.adjuntos : []), [song?.adjuntos])
  const segmentOptions = useMemo(() => {
    const verse = availableVerses[Number(draft.verse_index) || 0]
    const count = Array.isArray(verse?.segmentos) ? verse.segmentos.length : 0
    return Array.from({ length: Math.max(1, count) }, (_, index) => index)
  }, [availableVerses, draft.verse_index])

  const explicitUsers = useMemo(() => {
    const currentUserId = Number(wpData?.currentUserId || 0)
    return colleagues.filter((user) => Number(user?.id) !== currentUserId)
  }, [colleagues, wpData])

  const currentTarget = useMemo(
    () => ({
      anchor_type: draft.anchor_type || 'song',
      section_id: draft.section_id || '',
      verse_index: Number(draft.verse_index) || 0,
      segment_index: Number(draft.segment_index) || 0,
    }),
    [draft.anchor_type, draft.section_id, draft.verse_index, draft.segment_index],
  )

  const targetOptions = useMemo(() => {
    const targets = [
      {
        key: buildTargetKey('song'),
        anchor_type: 'song',
        label: 'Canción completa',
        meta: 'Visible al inicio de la lectura',
      },
    ]

    availableSections.forEach((section) => {
      const sectionId = String(section?.id || '')
      if (!sectionId) return
      targets.push({
        key: buildTargetKey('section', sectionId),
        anchor_type: 'section',
        section_id: sectionId,
        label: section?.nombre || `Sección ${sectionId}`,
        meta: 'Adjunto de sección',
      })
    })

    availableVerses.forEach((verse, verseIndex) => {
      const verseLabel = Number(verse?.orden || verseIndex + 1)
      targets.push({
        key: buildTargetKey('verse', '', verseIndex),
        anchor_type: 'verse',
        verse_index: verseIndex,
        label: `Verso ${verseLabel}`,
        meta: 'Adjunto de verso',
      })

      const segments = Array.isArray(verse?.segmentos) ? verse.segmentos : []
      segments.forEach((segment, segmentIndex) => {
        const preview = String(segment?.texto || '').replace(/\s+/g, ' ').trim()
        targets.push({
          key: buildTargetKey('segment', '', verseIndex, segmentIndex),
          anchor_type: 'segment',
          verse_index: verseIndex,
          segment_index: segmentIndex,
          label: `Fragmento ${segmentIndex + 1} · Verso ${verseLabel}`,
          meta: preview ? preview.slice(0, 52) : 'Adjunto de fragmento',
        })
      })
    })

    return targets.map((target) => ({
      ...target,
      count: allAttachments.filter((attachment) => attachmentMatchesTarget(attachment, target)).length,
    }))
  }, [allAttachments, availableSections, availableVerses])

  const currentTargetAttachments = useMemo(
    () => allAttachments.filter((attachment) => attachmentMatchesTarget(attachment, currentTarget)),
    [allAttachments, currentTarget],
  )

  const handleToggleValue = (key, value, checked) => {
    setDraft((prev) => {
      const current = Array.isArray(prev[key]) ? [...prev[key]] : []
      const numericValue = Number(value)
      if (checked) {
        if (!current.includes(numericValue)) current.push(numericValue)
      } else {
        const index = current.indexOf(numericValue)
        if (index >= 0) current.splice(index, 1)
      }
      return { ...prev, [key]: current }
    })
  }

  const resetUploadDraft = () => {
    setSelectedFile(null)
    setRecordedBlob(null)
    setDurationSeconds(0)
    setDraft((prev) => ({ ...prev, title: '', source_kind: 'import' }))
  }

  const startRecording = async () => {
    setError(null)
    if (typeof navigator === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
      setError('Tu navegador no soporta grabación de audio en esta interfaz.')
      return
    }

    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      const mediaRecorder = new MediaRecorder(stream)
      recordedChunksRef.current = []
      mediaRecorderRef.current = mediaRecorder
      mediaRecorder.ondataavailable = (event) => {
        if (event.data?.size) recordedChunksRef.current.push(event.data)
      }
      mediaRecorder.onstop = () => {
        stream.getTracks().forEach((track) => track.stop())
        const blob = new Blob(recordedChunksRef.current, { type: mediaRecorder.mimeType || 'audio/webm' })
        setRecordedBlob(blob)
        setSelectedFile(null)
        setDraft((prev) => ({ ...prev, source_kind: 'recording' }))
      }
      mediaRecorder.start()
      setRecording(true)
    } catch {
      setError('No fue posible iniciar la grabación de audio.')
    }
  }

  const stopRecording = () => {
    if (mediaRecorderRef.current && mediaRecorderRef.current.state !== 'inactive') {
      mediaRecorderRef.current.stop()
    }
    setRecording(false)
  }

  const handleFileChange = (event) => {
    const file = event.target.files?.[0] || null
    setSelectedFile(file)
    setRecordedBlob(null)
    setDurationSeconds(0)
    setDraft((prev) => ({ ...prev, source_kind: 'import' }))
  }

  const updateAttachmentDraft = (attachmentId, updater) => {
    setAttachmentDrafts((prev) => {
      const currentAttachment = normalizeAttachmentDraft(prev[attachmentId]) || {}
      const nextAttachment = typeof updater === 'function'
        ? updater(currentAttachment)
        : { ...currentAttachment, ...updater }
      return { ...prev, [attachmentId]: normalizeAttachmentDraft(nextAttachment) || nextAttachment }
    })
  }

  const handleAttachmentToggleValue = (attachmentId, key, value, checked) => {
    updateAttachmentDraft(attachmentId, (attachment) => {
      const current = Array.isArray(attachment?.[key]) ? [...attachment[key]] : []
      const numericValue = Number(value)
      if (checked) {
        if (!current.includes(numericValue)) current.push(numericValue)
      } else {
        const index = current.indexOf(numericValue)
        if (index >= 0) current.splice(index, 1)
      }
      return { ...attachment, [key]: current }
    })
  }

  const handleSaveAttachment = async (attachmentId) => {
    if (!song?.id || !attachmentId) return

    const attachment = normalizeAttachmentDraft(attachmentDrafts[attachmentId])
    if (!attachment) return

    setSavingAttachmentId(attachmentId)
    setError(null)

    try {
      const response = await api.updateSongAttachment(song.id, attachmentId, {
        title: attachment.title || '',
        type: attachment.type || 'audio',
        source_kind: attachment.source_kind || 'import',
        anchor_type: attachment.anchor_type || 'song',
        section_id: attachment.anchor_type === 'section' || attachment.anchor_type === 'segment' ? (attachment.section_id || '') : '',
        verse_index: attachment.anchor_type === 'verse' || attachment.anchor_type === 'segment' ? Number(attachment.verse_index) || 0 : 0,
        segment_index: attachment.anchor_type === 'segment' ? Number(attachment.segment_index) || 0 : 0,
        visibility_mode: attachment.visibility_mode || 'private',
        visibility_group_ids: attachment.visibility_group_ids || [],
        visibility_user_ids: attachment.visibility_user_ids || [],
        duration_seconds: Number(attachment.duration_seconds) || 0,
      })
      const attachments = Array.isArray(response?.data?.adjuntos) ? response.data.adjuntos : []
      onChangeSong({ ...song, adjuntos: attachments })
      dispatch({
        type: 'SET_STATE',
        payload: {
          feedback: { message: response?.data?.message || 'Adjunto actualizado.', type: 'success' },
          error: null,
        },
      })
    } catch (requestError) {
      const message = requestError?.payload?.message || 'No fue posible actualizar el adjunto.'
      setError(message)
      dispatch({ type: 'SET_STATE', payload: { error: message } })
    } finally {
      setSavingAttachmentId(null)
    }
  }

  const handleRemoveAttachment = async (attachmentId) => {
    if (!song?.id || !attachmentId) return

    setUnlinkingAttachmentId(attachmentId)
    setError(null)

    try {
      const response = await api.unlinkSongAttachment(song.id, attachmentId)
      const attachments = Array.isArray(response?.data?.adjuntos) ? response.data.adjuntos : []
      onChangeSong({ ...song, adjuntos: attachments })
      dispatch({
        type: 'SET_STATE',
        payload: {
          feedback: { message: response?.data?.message || 'Adjunto quitado de la canción.', type: 'success' },
          error: null,
        },
      })
    } catch (requestError) {
      const message = requestError?.payload?.message || 'No fue posible quitar el adjunto de la canción.'
      setError(message)
      dispatch({ type: 'SET_STATE', payload: { error: message } })
    } finally {
      setUnlinkingAttachmentId(null)
    }
  }

  const handleDeleteFromDrive = async (attachment) => {
    const attachmentId = attachment?.id
    if (!song?.id || !attachmentId) return

    const confirmed = window.confirm(
      `¿Eliminar definitivamente "${attachment.title || attachment.file_name || attachment.id}" del Google Drive? Esta acción no se puede deshacer.`,
    )
    if (!confirmed) return

    setDeletingDriveAttachmentId(attachmentId)
    setError(null)
    try {
      const response = await api.deleteSongAttachment(song.id, attachmentId)
      const attachments = Array.isArray(response?.data?.adjuntos) ? response.data.adjuntos : []
      onChangeSong({ ...song, adjuntos: attachments })
      dispatch({
        type: 'SET_STATE',
        payload: {
          feedback: { message: response?.data?.message || 'Adjunto eliminado del Drive.', type: 'success' },
          error: null,
        },
      })
    } catch (requestError) {
      const message = requestError?.payload?.message || 'No fue posible eliminar el adjunto del Google Drive.'
      setError(message)
      dispatch({ type: 'SET_STATE', payload: { error: message } })
    } finally {
      setDeletingDriveAttachmentId(null)
    }
  }

  const handleUpload = async () => {
    if (!song?.id) {
      setError('Primero guarda la canción para poder subir audios o fotos.')
      return
    }
    if (!driveStatus?.connected) {
      setError('Conecta tu Google Drive antes de subir adjuntos.')
      return
    }

    const sourceBlob = recordedBlob
    const sourceFile = selectedFile
    if (!sourceBlob && !sourceFile) {
      setError('Selecciona un archivo o graba un audio antes de subir.')
      return
    }

    setUploading(true)
    setError(null)

    try {
      let fileToUpload = sourceFile
      if (!fileToUpload && sourceBlob) {
        const extension = draft.type === 'photo' ? 'png' : 'webm'
        fileToUpload = new File([sourceBlob], `${draft.title || 'adjunto'}.${extension}`, { type: sourceBlob.type || (draft.type === 'photo' ? 'image/png' : 'audio/webm') })
      }

      const formData = new FormData()
      formData.append('song_id', String(song.id))
      formData.append('title', draft.title || fileToUpload?.name || '')
      formData.append('type', draft.type)
      formData.append('source_kind', draft.source_kind)
      formData.append('anchor_type', draft.anchor_type)
      formData.append('section_id', draft.anchor_type === 'section' || draft.anchor_type === 'segment' ? (draft.section_id || '') : '')
      formData.append('verse_index', String(draft.anchor_type === 'verse' || draft.anchor_type === 'segment' ? draft.verse_index : 0))
      formData.append('segment_index', String(draft.anchor_type === 'segment' ? draft.segment_index : 0))
      formData.append('visibility_mode', draft.visibility_mode)
      formData.append('visibility_group_ids', JSON.stringify(draft.visibility_group_ids || []))
      formData.append('visibility_user_ids', JSON.stringify(draft.visibility_user_ids || []))
      formData.append('duration_seconds', String(durationSeconds || 0))
      formData.append('file', fileToUpload)

      const response = await api.uploadSongAttachment(formData)
      const attachments = Array.isArray(response?.data?.adjuntos) ? response.data.adjuntos : []
      onChangeSong({ ...song, adjuntos: attachments })
      dispatch({
        type: 'SET_STATE',
        payload: { feedback: { message: 'Adjunto subido a Google Drive.', type: 'success' }, error: null },
      })
      resetUploadDraft()
    } catch (requestError) {
      const message = requestError?.payload?.message || 'No fue posible subir el adjunto a Google Drive.'
      setError(message)
      dispatch({ type: 'SET_STATE', payload: { error: message } })
    } finally {
      setUploading(false)
    }
  }

  return (
    <details ref={panelRef} className="wpss-section wpss-section--collapsible wpss-section--nested" open>
      <summary>
        <span>Audios y fotos</span>
      </summary>
      {loadingContext ? <p className="wpss-collections__hint">Cargando contexto multimedia…</p> : null}
      {error ? <p className="wpss-error">{error}</p> : null}
      {requestedTarget?.requestId ? (
        <p className="wpss-media-shortcut-banner">
          Destino activo: {describeTarget(requestedTarget, availableSections, availableVerses)}
          {' · '}
          {requestedTarget.type === 'photo' ? 'Foto' : 'Audio'}
        </p>
      ) : null}
      <p className="wpss-collections__hint">
        Drive: {driveStatus?.connected ? `Conectado como ${driveStatus.account_email || 'usuario activo'}` : 'No conectado'}
        {' · '}
        <a href={wpData?.adminUrls?.drivePage || '#'}>Abrir Mi Drive</a>
      </p>

      <div className="wpss-media-anchor-picker">
        <div className="wpss-media-anchor-picker__header">
          <strong>Insertar adjunto en</strong>
          <span>{describeTarget(currentTarget, availableSections, availableVerses)}</span>
        </div>
        <div className="wpss-media-anchor-picker__grid">
          {targetOptions.map((target) => {
            const isActive = target.key === buildTargetKey(
              currentTarget.anchor_type,
              currentTarget.section_id,
              currentTarget.verse_index,
              currentTarget.segment_index,
            )
            return (
              <button
                key={target.key}
                type="button"
                className={`wpss-media-anchor-picker__item ${isActive ? 'is-active' : ''}`}
                onClick={() => setDraft((prev) => ({ ...prev, ...createTargetDraftPatch(target) }))}
              >
                <strong>{target.label}</strong>
                <span>{target.meta}</span>
                <em>{target.count ? `${target.count} adjunto(s)` : 'Sin adjuntos aún'}</em>
              </button>
            )
          })}
        </div>
        {currentTargetAttachments.length ? (
          <p className="wpss-collections__hint">
            Ya hay {currentTargetAttachments.length} adjunto(s) en este punto de la canción.
          </p>
        ) : null}
      </div>

      <div className="wpss-field-group">
        <label className="wpss-field">
          <span>Título del adjunto</span>
          <input
            ref={titleInputRef}
            type="text"
            value={draft.title}
            onChange={(event) => setDraft((prev) => ({ ...prev, title: event.target.value }))}
          />
        </label>
        <label className="wpss-field">
          <span>Tipo</span>
          <select value={draft.type} onChange={(event) => setDraft((prev) => ({ ...prev, type: event.target.value }))}>
            <option value="audio">Audio</option>
            <option value="photo">Foto</option>
          </select>
        </label>
        <label className="wpss-field">
          <span>Ubicar en</span>
          <select value={draft.anchor_type} onChange={(event) => setDraft((prev) => ({ ...prev, anchor_type: event.target.value }))}>
            <option value="song">Canción</option>
            <option value="section">Sección</option>
            <option value="verse">Verso</option>
            <option value="segment">Fragmento</option>
          </select>
        </label>
      </div>

      {draft.anchor_type === 'section' || draft.anchor_type === 'segment' ? (
        <div className="wpss-field-group">
          <label className="wpss-field">
            <span>Sección</span>
            <select value={draft.section_id} onChange={(event) => setDraft((prev) => ({ ...prev, section_id: event.target.value }))}>
              <option value="">Selecciona una sección</option>
              {availableSections.map((section) => (
                <option key={section.id} value={section.id}>{section.nombre || section.id}</option>
              ))}
            </select>
          </label>
        </div>
      ) : null}

      {draft.anchor_type === 'verse' || draft.anchor_type === 'segment' ? (
        <div className="wpss-field-group">
          <label className="wpss-field">
            <span>Verso</span>
            <select value={draft.verse_index} onChange={(event) => setDraft((prev) => ({ ...prev, verse_index: Number(event.target.value) || 0 }))}>
              {availableVerses.map((verse, index) => (
                <option key={`verse-${index}`} value={index}>Verso {Number(verse?.orden || index + 1)}</option>
              ))}
            </select>
          </label>
          {draft.anchor_type === 'segment' ? (
            <label className="wpss-field">
              <span>Fragmento</span>
              <select value={draft.segment_index} onChange={(event) => setDraft((prev) => ({ ...prev, segment_index: Number(event.target.value) || 0 }))}>
                {segmentOptions.map((index) => (
                  <option key={`segment-${index}`} value={index}>Segmento {index + 1}</option>
                ))}
              </select>
            </label>
          ) : null}
        </div>
      ) : null}

      <div className="wpss-field-group">
        <label className="wpss-field">
          <span>Visibilidad</span>
          <select value={draft.visibility_mode} onChange={(event) => setDraft((prev) => ({ ...prev, visibility_mode: event.target.value }))}>
            <option value="private">Mismo acceso que la canción</option>
            <option value="public">Para todo el cancionero</option>
            <option value="groups">Solo agrupaciones musicales</option>
            <option value="users">Solo usuarios específicos</option>
          </select>
        </label>
      </div>

      {draft.visibility_mode === 'groups' ? (
        <div className="wpss-collections__shared-list">
          {groups.length ? groups.map((group) => {
            const groupId = Number(group?.id)
            const checked = (draft.visibility_group_ids || []).includes(groupId)
            return (
              <label key={groupId} className="wpss-collections__shared-item">
                <input type="checkbox" checked={checked} onChange={(event) => handleToggleValue('visibility_group_ids', groupId, event.target.checked)} />
                <span>{group?.nombre || `Agrupación ${groupId}`}</span>
              </label>
            )
          }) : <span>No hay agrupaciones disponibles todavía.</span>}
        </div>
      ) : null}

      {draft.visibility_mode === 'users' ? (
        <div className="wpss-collections__shared-list">
          {explicitUsers.length ? explicitUsers.map((user) => {
            const userId = Number(user?.id)
            const checked = (draft.visibility_user_ids || []).includes(userId)
            return (
              <label key={userId} className="wpss-collections__shared-item">
                <input type="checkbox" checked={checked} onChange={(event) => handleToggleValue('visibility_user_ids', userId, event.target.checked)} />
                <span>{user?.nombre || `Usuario ${userId}`}</span>
              </label>
            )
          }) : <span>No hay usuarios disponibles todavía.</span>}
        </div>
      ) : null}

      <div className="wpss-field-group">
        <label className="wpss-field">
          <span>Importar archivo</span>
          <input type="file" accept={draft.type === 'photo' ? 'image/*' : 'audio/*'} onChange={handleFileChange} />
        </label>
        {draft.type === 'audio' ? (
          <div className="wpss-field">
            <span>Grabar audio</span>
            <div className="wpss-collections__actions">
              {!recording ? (
                <button type="button" className="button button-secondary" onClick={startRecording}>Grabar</button>
              ) : (
                <button type="button" className="button button-secondary" onClick={stopRecording}>Detener</button>
              )}
            </div>
          </div>
        ) : null}
      </div>

      {(selectedFile || recordedBlob) ? (
        <p className="wpss-collections__hint">
          Listo para subir: {selectedFile?.name || 'Grabación nueva'}
          {durationSeconds ? ` · ${formatSeconds(durationSeconds)}` : ''}
          {` · ${describeTarget(currentTarget, availableSections, availableVerses)}`}
        </p>
      ) : null}

      <div className="wpss-collections__actions">
        <button type="button" className="button button-primary" onClick={handleUpload} disabled={uploading || !song?.id}>
          {uploading ? 'Subiendo…' : 'Subir a Drive'}
        </button>
      </div>

      <div className="wpss-field-group">
        <label className="wpss-field">
          <span>Adjuntos guardados</span>
          {Array.isArray(song?.adjuntos) && song.adjuntos.length ? (
            <div style={{ display: 'grid', gap: '1rem' }}>
              {song.adjuntos.map((attachment) => {
                const attachmentDraft = normalizeAttachmentDraft(attachmentDrafts[attachment.id]) || normalizeAttachmentDraft(attachment) || attachment
                const canManage = !!attachment?.can_manage
                const canDeleteFile = !!attachment?.can_delete_file
                const verseIndex = Number(attachmentDraft.verse_index) || 0
                const segmentCount = Array.isArray(availableVerses[verseIndex]?.segmentos) ? availableVerses[verseIndex].segmentos.length : 0

                return (
                  <article key={attachment.id} style={{ border: '1px solid #d5d9df', borderRadius: '8px', padding: '0.9rem', background: '#fff' }}>
                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: '1rem', alignItems: 'center' }}>
                      <div>
                        <strong>{attachmentDraft.title || attachmentDraft.file_name || attachmentDraft.id}</strong>
                        <div style={{ fontSize: '0.9em', color: '#5b6472' }}>
                          {attachmentDraft.type === 'photo' ? 'Foto' : 'Audio'} · {attachmentDraft.visibility_mode}
                          {attachmentDraft.duration_seconds ? ` · ${formatSeconds(attachmentDraft.duration_seconds)}` : ''}
                        </div>
                        {!canManage ? (
                          <div style={{ fontSize: '0.85em', color: '#7a5c00', marginTop: '0.3rem' }}>
                            Solo lectura. Puedes reproducirlo, pero no modificar su configuración.
                          </div>
                        ) : null}
                      </div>
                      <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap', justifyContent: 'flex-end' }}>
                        {canManage ? (
                          <>
                            <button
                              type="button"
                              className="button button-small button-primary"
                              onClick={() => handleSaveAttachment(attachment.id)}
                              disabled={savingAttachmentId === attachment.id}
                            >
                              {savingAttachmentId === attachment.id ? 'Guardando…' : 'Guardar cambios'}
                            </button>
                            <button
                              type="button"
                              className="button button-small"
                              onClick={() => handleRemoveAttachment(attachment.id)}
                              disabled={unlinkingAttachmentId === attachment.id}
                            >
                              {unlinkingAttachmentId === attachment.id ? 'Quitando…' : 'Quitar'}
                            </button>
                          </>
                        ) : null}
                        {canDeleteFile ? (
                          <button
                            type="button"
                            className="button button-small button-secondary"
                            onClick={() => handleDeleteFromDrive(attachment)}
                            disabled={deletingDriveAttachmentId === attachment.id}
                          >
                            {deletingDriveAttachmentId === attachment.id ? 'Eliminando…' : 'Eliminar de Drive'}
                          </button>
                        ) : null}
                      </div>
                    </div>
                    <div className="wpss-field-group" style={{ marginTop: '0.8rem' }}>
                      <label className="wpss-field">
                        <span>Título</span>
                        <input
                          type="text"
                          value={attachmentDraft.title || ''}
                          disabled={!canManage}
                          onChange={(event) => updateAttachmentDraft(attachment.id, { ...attachmentDraft, title: event.target.value })}
                        />
                      </label>
                      <label className="wpss-field">
                        <span>Ubicar en</span>
                        <select
                          value={attachmentDraft.anchor_type || 'song'}
                          disabled={!canManage}
                          onChange={(event) => updateAttachmentDraft(attachment.id, {
                            ...attachmentDraft,
                            anchor_type: event.target.value,
                            section_id: event.target.value === 'section' || event.target.value === 'segment' ? attachmentDraft.section_id || '' : '',
                            verse_index: event.target.value === 'verse' || event.target.value === 'segment' ? Number(attachmentDraft.verse_index) || 0 : 0,
                            segment_index: event.target.value === 'segment' ? Number(attachmentDraft.segment_index) || 0 : 0,
                          })}
                        >
                          <option value="song">Canción</option>
                          <option value="section">Sección</option>
                          <option value="verse">Verso</option>
                          <option value="segment">Fragmento</option>
                        </select>
                      </label>
                      <label className="wpss-field">
                        <span>Visibilidad</span>
                        <select
                          value={attachmentDraft.visibility_mode || 'private'}
                          disabled={!canManage}
                          onChange={(event) => updateAttachmentDraft(attachment.id, { ...attachmentDraft, visibility_mode: event.target.value })}
                        >
                          <option value="private">Mismo acceso que la canción</option>
                          <option value="public">Para todo el cancionero</option>
                          <option value="groups">Solo agrupaciones</option>
                          <option value="users">Solo usuarios</option>
                        </select>
                      </label>
                    </div>
                    {attachmentDraft.anchor_type === 'section' || attachmentDraft.anchor_type === 'segment' ? (
                      <div className="wpss-field-group">
                        <label className="wpss-field">
                          <span>Sección</span>
                          <select
                            value={attachmentDraft.section_id || ''}
                            disabled={!canManage}
                            onChange={(event) => updateAttachmentDraft(attachment.id, { ...attachmentDraft, section_id: event.target.value })}
                          >
                            <option value="">Selecciona una sección</option>
                            {availableSections.map((section) => (
                              <option key={`att-sec-${attachment.id}-${section.id}`} value={section.id}>{section.nombre || section.id}</option>
                            ))}
                          </select>
                        </label>
                      </div>
                    ) : null}
                    {attachmentDraft.anchor_type === 'verse' || attachmentDraft.anchor_type === 'segment' ? (
                      <div className="wpss-field-group">
                        <label className="wpss-field">
                          <span>Verso</span>
                          <select
                            value={verseIndex}
                            disabled={!canManage}
                            onChange={(event) => updateAttachmentDraft(attachment.id, { ...attachmentDraft, verse_index: Number(event.target.value) || 0 })}
                          >
                            {availableVerses.map((verse, index) => (
                              <option key={`att-verse-${attachment.id}-${index}`} value={index}>Verso {Number(verse?.orden || index + 1)}</option>
                            ))}
                          </select>
                        </label>
                        {attachmentDraft.anchor_type === 'segment' ? (
                          <label className="wpss-field">
                            <span>Fragmento</span>
                            <select
                              value={Number(attachmentDraft.segment_index) || 0}
                              disabled={!canManage}
                              onChange={(event) => updateAttachmentDraft(attachment.id, { ...attachmentDraft, segment_index: Number(event.target.value) || 0 })}
                            >
                              {Array.from({ length: Math.max(1, segmentCount) }, (_, index) => index).map((index) => (
                                <option key={`att-segment-${attachment.id}-${index}`} value={index}>Segmento {index + 1}</option>
                              ))}
                            </select>
                          </label>
                        ) : null}
                      </div>
                    ) : null}
                    {attachmentDraft.visibility_mode === 'groups' ? (
                      <div className="wpss-collections__shared-list" style={{ marginTop: '0.7rem' }}>
                        {groups.map((group) => {
                          const groupId = Number(group?.id)
                          const checked = Array.isArray(attachmentDraft.visibility_group_ids) && attachmentDraft.visibility_group_ids.includes(groupId)
                          return (
                            <label key={`att-group-${attachment.id}-${groupId}`} className="wpss-collections__shared-item">
                              <input
                                type="checkbox"
                                checked={checked}
                                disabled={!canManage}
                                onChange={(event) => handleAttachmentToggleValue(attachment.id, 'visibility_group_ids', groupId, event.target.checked)}
                              />
                              <span>{group?.nombre || `Agrupación ${groupId}`}</span>
                            </label>
                          )
                        })}
                      </div>
                    ) : null}
                    {attachmentDraft.visibility_mode === 'users' ? (
                      <div className="wpss-collections__shared-list" style={{ marginTop: '0.7rem' }}>
                        {explicitUsers.map((user) => {
                          const userId = Number(user?.id)
                          const checked = Array.isArray(attachmentDraft.visibility_user_ids) && attachmentDraft.visibility_user_ids.includes(userId)
                          return (
                            <label key={`att-user-${attachment.id}-${userId}`} className="wpss-collections__shared-item">
                              <input
                                type="checkbox"
                                checked={checked}
                                disabled={!canManage}
                                onChange={(event) => handleAttachmentToggleValue(attachment.id, 'visibility_user_ids', userId, event.target.checked)}
                              />
                              <span>{user?.nombre || `Usuario ${userId}`}</span>
                            </label>
                          )
                        })}
                      </div>
                    ) : null}
                    {attachmentDraft.type === 'photo' ? (
                      <img
                        src={attachmentDraft.stream_url}
                        alt={attachmentDraft.title || attachmentDraft.file_name || 'Adjunto'}
                        style={{ marginTop: '0.75rem', maxWidth: '100%', borderRadius: '8px' }}
                      />
                    ) : (
                      <audio controls preload="none" src={attachmentDraft.stream_url} style={{ marginTop: '0.75rem', width: '100%' }} />
                    )}
                  </article>
                )
              })}
            </div>
          ) : (
            <span>Todavía no hay audios o fotos asociados a esta canción.</span>
          )}
        </label>
      </div>
      <p className="wpss-collections__hint">
        Quitar solo desvincula el adjunto de la canción. Eliminar de Drive borra también el archivo físico del Google Drive del propietario.
      </p>
    </details>
  )
}
