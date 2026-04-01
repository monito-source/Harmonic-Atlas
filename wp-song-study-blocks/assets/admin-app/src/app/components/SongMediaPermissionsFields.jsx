import { useEffect, useMemo, useState } from 'react'
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

export default function SongMediaPermissionsFields({ song, onChangeSong, onRequestAutosave }) {
  const { api, wpData } = useAppState()
  const [groups, setGroups] = useState([])
  const [colleagues, setColleagues] = useState([])

  useEffect(() => {
    Promise.allSettled([api.listGroups(), api.listColleagues()]).then((results) => {
      const [groupsResult, colleaguesResult] = results
      setGroups(groupsResult.status === 'fulfilled' && Array.isArray(groupsResult.value?.data) ? groupsResult.value.data : [])
      setColleagues(colleaguesResult.status === 'fulfilled' && Array.isArray(colleaguesResult.value?.data) ? colleaguesResult.value.data : [])
    })
  }, [api])

  const permissions = useMemo(() => normalizePermissions(song?.adjuntos_permisos), [song?.adjuntos_permisos])
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

  return (
    <>
      <div className="wpss-field-group">
        <label className="wpss-field">
          <span>Permisos de audios y fotos</span>
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
          <small className="wpss-tags-help">Esta política se aplica a todos los adjuntos multimedia de la canción.</small>
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
