import { useEffect, useMemo, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'

function formatSeconds(value) {
  const total = Math.max(0, Math.round(Number(value) || 0))
  const minutes = Math.floor(total / 60)
  const seconds = total % 60
  return `${minutes}:${String(seconds).padStart(2, '0')}`
}

function normalizePermissions(settings) {
  const source = settings && typeof settings === 'object' ? settings : {}
  return {
    visibility_mode: ['private', 'public', 'groups', 'users'].includes(String(source.visibility_mode || ''))
      ? String(source.visibility_mode)
      : 'private',
    visibility_group_ids: Array.isArray(source.visibility_group_ids)
      ? source.visibility_group_ids.map((value) => Number(value)).filter((value) => Number.isFinite(value) && value > 0)
      : [],
    visibility_user_ids: Array.isArray(source.visibility_user_ids)
      ? source.visibility_user_ids.map((value) => Number(value)).filter((value) => Number.isFinite(value) && value > 0)
      : [],
  }
}

function normalizeAttachmentDraft(attachment) {
  if (!attachment || typeof attachment !== 'object') {
    return null
  }

  return {
    ...attachment,
    verse_index: Number(attachment.verse_index) || 0,
    segment_index: Number(attachment.segment_index) || 0,
  }
}

export default function SongMediaManager({ song, onChangeSong, onRequestAutosave, showPermissions = true }) {
  const { api, dispatch, wpData } = useAppState()
  const [groups, setGroups] = useState([])
  const [colleagues, setColleagues] = useState([])
  const [loadingContext, setLoadingContext] = useState(true)
  const [attachmentDrafts, setAttachmentDrafts] = useState({})
  const [savingAttachmentId, setSavingAttachmentId] = useState(null)
  const [unlinkingAttachmentId, setUnlinkingAttachmentId] = useState(null)
  const [deletingDriveAttachmentId, setDeletingDriveAttachmentId] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    if (!showPermissions) {
      setGroups([])
      setColleagues([])
      setLoadingContext(false)
      return
    }

    setLoadingContext(true)
    Promise.allSettled([api.listGroups(), api.listColleagues()]).then((results) => {
      const [groupsResult, colleaguesResult] = results
      setGroups(groupsResult.status === 'fulfilled' && Array.isArray(groupsResult.value?.data) ? groupsResult.value.data : [])
      setColleagues(colleaguesResult.status === 'fulfilled' && Array.isArray(colleaguesResult.value?.data) ? colleaguesResult.value.data : [])
      setLoadingContext(false)
    })
  }, [api, showPermissions])

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

  const permissions = useMemo(
    () => normalizePermissions(song?.adjuntos_permisos),
    [song?.adjuntos_permisos],
  )

  const availableVerses = useMemo(() => (Array.isArray(song?.versos) ? song.versos : []), [song?.versos])
  const availableSections = useMemo(() => (Array.isArray(song?.secciones) ? song.secciones : []), [song?.secciones])
  const attachments = useMemo(() => (Array.isArray(song?.adjuntos) ? song.adjuntos : []), [song?.adjuntos])
  const explicitUsers = useMemo(() => {
    const currentUserId = Number(wpData?.currentUserId || 0)
    return colleagues.filter((user) => Number(user?.id) !== currentUserId)
  }, [colleagues, wpData])

  const updatePermissions = (nextPermissions) => {
    onChangeSong({ ...song, adjuntos_permisos: normalizePermissions(nextPermissions) })
    onRequestAutosave?.()
  }

  const togglePermissionValue = (key, value, checked) => {
    const current = Array.isArray(permissions[key]) ? [...permissions[key]] : []
    const numericValue = Number(value)
    if (checked) {
      if (!current.includes(numericValue)) current.push(numericValue)
    } else {
      const index = current.indexOf(numericValue)
      if (index >= 0) current.splice(index, 1)
    }
    updatePermissions({ ...permissions, [key]: current })
  }

  const updateAttachmentDraft = (attachmentId, patch) => {
    setAttachmentDrafts((prev) => ({
      ...prev,
      [attachmentId]: normalizeAttachmentDraft({ ...(prev[attachmentId] || {}), ...patch }),
    }))
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
        duration_seconds: Number(attachment.duration_seconds) || 0,
      })
      const nextAttachments = Array.isArray(response?.data?.adjuntos) ? response.data.adjuntos : []
      onChangeSong({ ...song, adjuntos: nextAttachments })
      dispatch({ type: 'SET_STATE', payload: { feedback: { message: response?.data?.message || 'Adjunto actualizado.', type: 'success' }, error: null } })
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
      const nextAttachments = Array.isArray(response?.data?.adjuntos) ? response.data.adjuntos : []
      onChangeSong({ ...song, adjuntos: nextAttachments })
      dispatch({ type: 'SET_STATE', payload: { feedback: { message: response?.data?.message || 'Adjunto quitado de la canción.', type: 'success' }, error: null } })
    } catch (requestError) {
      const message = requestError?.payload?.message || 'No fue posible quitar el adjunto.'
      setError(message)
      dispatch({ type: 'SET_STATE', payload: { error: message } })
    } finally {
      setUnlinkingAttachmentId(null)
    }
  }

  const handleDeleteFromDrive = async (attachment) => {
    if (!song?.id || !attachment?.id) return
    const confirmed = window.confirm(`¿Eliminar definitivamente "${attachment.title || attachment.file_name || attachment.id}" del Google Drive?`)
    if (!confirmed) return

    setDeletingDriveAttachmentId(attachment.id)
    setError(null)
    try {
      const response = await api.deleteSongAttachment(song.id, attachment.id)
      const nextAttachments = Array.isArray(response?.data?.adjuntos) ? response.data.adjuntos : []
      onChangeSong({ ...song, adjuntos: nextAttachments })
      dispatch({ type: 'SET_STATE', payload: { feedback: { message: response?.data?.message || 'Adjunto eliminado del Drive.', type: 'success' }, error: null } })
    } catch (requestError) {
      const message = requestError?.payload?.message || 'No fue posible eliminar el adjunto del Drive.'
      setError(message)
      dispatch({ type: 'SET_STATE', payload: { error: message } })
    } finally {
      setDeletingDriveAttachmentId(null)
    }
  }

  return (
    <details className="wpss-section wpss-section--collapsible wpss-section--nested" open>
      <summary>
        <span>{showPermissions ? 'Adjuntos y permisos' : 'Administrar adjuntos'}</span>
      </summary>
      {showPermissions && loadingContext ? <p className="wpss-collections__hint">Cargando permisos multimedia…</p> : null}
      {error ? <p className="wpss-error">{error}</p> : null}

      {showPermissions ? (
        <>
          <div className="wpss-field-group">
            <label className="wpss-field">
              <span>Permisos multimedia de la canción</span>
              <select
                value={permissions.visibility_mode}
                onChange={(event) =>
                  updatePermissions({
                    ...permissions,
                    visibility_mode: event.target.value,
                    visibility_group_ids: event.target.value === 'groups' ? permissions.visibility_group_ids : [],
                    visibility_user_ids: event.target.value === 'users' ? permissions.visibility_user_ids : [],
                  })}
              >
                <option value="private">Privado</option>
                <option value="public">Para todo el cancionero</option>
                <option value="groups">Solo agrupaciones musicales</option>
                <option value="users">Solo usuarios específicos</option>
              </select>
            </label>
          </div>

          {permissions.visibility_mode === 'groups' ? (
            <div className="wpss-collections__shared-list">
              {groups.length ? groups.map((group) => {
                const groupId = Number(group?.id)
                const checked = permissions.visibility_group_ids.includes(groupId)
                return (
                  <label key={groupId} className="wpss-collections__shared-item">
                    <input type="checkbox" checked={checked} onChange={(event) => togglePermissionValue('visibility_group_ids', groupId, event.target.checked)} />
                    <span>{group?.nombre || `Agrupación ${groupId}`}</span>
                  </label>
                )
              }) : <span>No hay agrupaciones disponibles todavía.</span>}
            </div>
          ) : null}

          {permissions.visibility_mode === 'users' ? (
            <div className="wpss-collections__shared-list">
              {explicitUsers.length ? explicitUsers.map((user) => {
                const userId = Number(user?.id)
                const checked = permissions.visibility_user_ids.includes(userId)
                return (
                  <label key={userId} className="wpss-collections__shared-item">
                    <input type="checkbox" checked={checked} onChange={(event) => togglePermissionValue('visibility_user_ids', userId, event.target.checked)} />
                    <span>{user?.nombre || `Usuario ${userId}`}</span>
                  </label>
                )
              }) : <span>No hay usuarios disponibles todavía.</span>}
            </div>
          ) : null}

          <p className="wpss-collections__hint">
            Esta política se aplica a todos los audios y fotos de la canción.
          </p>
        </>
      ) : null}

      <div className="wpss-field-group">
        <label className="wpss-field">
          <span>Adjuntos ya cargados</span>
          {attachments.length ? (
            <div style={{ display: 'grid', gap: '1rem' }}>
              {attachments.map((attachment) => {
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
                          {attachmentDraft.type === 'photo' ? 'Foto' : 'Audio'}
                          {attachmentDraft.duration_seconds ? ` · ${formatSeconds(attachmentDraft.duration_seconds)}` : ''}
                        </div>
                      </div>
                      <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap', justifyContent: 'flex-end' }}>
                        {canManage ? (
                          <>
                            <button type="button" className="button button-small button-primary" onClick={() => handleSaveAttachment(attachment.id)} disabled={savingAttachmentId === attachment.id}>
                              {savingAttachmentId === attachment.id ? 'Guardando…' : 'Guardar cambios'}
                            </button>
                            <button type="button" className="button button-small" onClick={() => handleRemoveAttachment(attachment.id)} disabled={unlinkingAttachmentId === attachment.id}>
                              {unlinkingAttachmentId === attachment.id ? 'Quitando…' : 'Quitar'}
                            </button>
                          </>
                        ) : null}
                        {canDeleteFile ? (
                          <button type="button" className="button button-small button-secondary" onClick={() => handleDeleteFromDrive(attachment)} disabled={deletingDriveAttachmentId === attachment.id}>
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
                          onChange={(event) => updateAttachmentDraft(attachment.id, { title: event.target.value })}
                        />
                      </label>
                      <label className="wpss-field">
                        <span>Ubicar en</span>
                        <select
                          value={attachmentDraft.anchor_type || 'song'}
                          disabled={!canManage}
                          onChange={(event) => updateAttachmentDraft(attachment.id, {
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
                    </div>

                    {attachmentDraft.anchor_type === 'section' || attachmentDraft.anchor_type === 'segment' ? (
                      <div className="wpss-field-group">
                        <label className="wpss-field">
                          <span>Sección</span>
                          <select
                            value={attachmentDraft.section_id || ''}
                            disabled={!canManage}
                            onChange={(event) => updateAttachmentDraft(attachment.id, { section_id: event.target.value })}
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
                            onChange={(event) => updateAttachmentDraft(attachment.id, { verse_index: Number(event.target.value) || 0 })}
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
                              onChange={(event) => updateAttachmentDraft(attachment.id, { segment_index: Number(event.target.value) || 0 })}
                            >
                              {Array.from({ length: Math.max(1, segmentCount) }, (_, index) => index).map((index) => (
                                <option key={`att-segment-${attachment.id}-${index}`} value={index}>Segmento {index + 1}</option>
                              ))}
                            </select>
                          </label>
                        ) : null}
                      </div>
                    ) : null}

                    {attachmentDraft.type === 'photo' ? (
                      <img src={attachmentDraft.stream_url} alt={attachmentDraft.title || attachmentDraft.file_name || 'Adjunto'} style={{ marginTop: '0.75rem', maxWidth: '100%', borderRadius: '8px' }} />
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
    </details>
  )
}
