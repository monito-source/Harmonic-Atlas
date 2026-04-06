import { useEffect, useMemo, useRef, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'

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

function getVisibilityLabel(mode) {
  if (mode === 'public') return 'Para todo el cancionero'
  if (mode === 'groups') return 'Solo agrupaciones musicales'
  if (mode === 'users') return 'Solo usuarios específicos'
  return 'Mismo acceso que la canción'
}

export default function SongMediaPermissionsFields({ song, onChangeSong, onRequestAutosave }) {
  const { api, wpData } = useAppState()
  const [groups, setGroups] = useState([])
  const [colleagues, setColleagues] = useState([])
  const persistTimeoutRef = useRef(null)
  const [draftPermissions, setDraftPermissions] = useState(() => normalizePermissions(song?.adjuntos_permisos))

  useEffect(() => {
    Promise.allSettled([api.listGroups(), api.listColleagues()]).then((results) => {
      const [groupsResult, colleaguesResult] = results
      setGroups(groupsResult.status === 'fulfilled' && Array.isArray(groupsResult.value?.data) ? groupsResult.value.data : [])
      setColleagues(colleaguesResult.status === 'fulfilled' && Array.isArray(colleaguesResult.value?.data) ? colleaguesResult.value.data : [])
    })
  }, [api])

  useEffect(() => () => {
    if (persistTimeoutRef.current) {
      clearTimeout(persistTimeoutRef.current)
    }
  }, [])

  useEffect(() => {
    setDraftPermissions(normalizePermissions(song?.adjuntos_permisos))
  }, [song?.adjuntos_permisos])

  const permissions = useMemo(() => normalizePermissions(draftPermissions), [draftPermissions])
  const explicitUsers = useMemo(() => {
    const currentUserId = Number(wpData?.currentUserId || 0)
    return colleagues.filter((user) => Number(user?.id) !== currentUserId)
  }, [colleagues, wpData])

  const updatePermissions = (nextPermissions) => {
    const normalizedPermissions = normalizePermissions(nextPermissions)
    setDraftPermissions(normalizedPermissions)
    const nextSong = { ...song, adjuntos_permisos: normalizedPermissions }
    onChangeSong(nextSong)
    if (persistTimeoutRef.current) {
      clearTimeout(persistTimeoutRef.current)
    }
    persistTimeoutRef.current = setTimeout(() => {
      persistTimeoutRef.current = null
      onRequestAutosave?.()
    }, 180)
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

  return (
    <>
      <div className="wpss-field-group">
        <label className="wpss-field">
          <span>Permisos de audios y fotos</span>
          <strong>{`Modo actual: ${getVisibilityLabel(permissions.visibility_mode)}`}</strong>
          <select
            key={`media-visibility-${permissions.visibility_mode}`}
            value={permissions.visibility_mode}
            onChange={(event) =>
              updatePermissions({
                ...permissions,
                visibility_mode: event.target.value,
                visibility_group_ids: event.target.value === 'groups' ? permissions.visibility_group_ids : [],
                visibility_user_ids: event.target.value === 'users' ? permissions.visibility_user_ids : [],
              })}
          >
            <option value="private">Mismo acceso que la canción</option>
            <option value="public">Para todo el cancionero</option>
            <option value="groups">Solo agrupaciones musicales</option>
            <option value="users">Solo usuarios específicos</option>
          </select>
          <small className="wpss-tags-help">
            Esta política gobierna los audios y fotos que el transcriptor o admin adjunta como material general de la canción. No afecta los audios del área de ensayos.
          </small>
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

    </>
  )
}
