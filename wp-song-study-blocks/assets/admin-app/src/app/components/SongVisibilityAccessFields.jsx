import { useEffect, useMemo, useRef, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'

function normalizeVisibility(song) {
  const source = song && typeof song === 'object' ? song : {}
  const mode = ['public', 'private', 'project', 'groups', 'users'].includes(String(source.visibility_mode || ''))
    ? String(source.visibility_mode)
    : 'private'

  return {
    visibility_mode: mode,
    visibility_project_ids: Array.isArray(source.visibility_project_ids)
      ? source.visibility_project_ids.map((value) => Number(value)).filter((value) => Number.isFinite(value) && value > 0)
      : [],
    visibility_group_ids: Array.isArray(source.visibility_group_ids)
      ? source.visibility_group_ids.map((value) => Number(value)).filter((value) => Number.isFinite(value) && value > 0)
      : [],
    visibility_user_ids: Array.isArray(source.visibility_user_ids)
      ? source.visibility_user_ids.map((value) => Number(value)).filter((value) => Number.isFinite(value) && value > 0)
      : [],
  }
}

function getVisibilityLabel(mode) {
  if (mode === 'public') return 'Pública'
  if (mode === 'groups') return 'Solo agrupaciones musicales'
  if (mode === 'users') return 'Solo usuarios específicos'
  if (mode === 'project') return 'Solo proyectos seleccionados'
  return 'Privada para cancionero autenticado'
}

export default function SongVisibilityAccessFields({
  song,
  availableProjects = [],
  onChangeSong,
  onRequestAutosave,
}) {
  const { api, wpData } = useAppState()
  const [groups, setGroups] = useState([])
  const [colleagues, setColleagues] = useState([])
  const persistTimeoutRef = useRef(null)
  const [draftVisibility, setDraftVisibility] = useState(() => normalizeVisibility(song))

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
    setDraftVisibility(normalizeVisibility(song))
  }, [
    song?.id,
    song?.visibility_group_ids,
    song?.visibility_mode,
    song?.visibility_project_ids,
    song?.visibility_user_ids,
  ])

  const visibility = useMemo(() => normalizeVisibility(draftVisibility), [draftVisibility])
  const explicitUsers = useMemo(() => {
    const currentUserId = Number(wpData?.currentUserId || 0)
    return colleagues.filter((user) => Number(user?.id) !== currentUserId)
  }, [colleagues, wpData])

  const pushVisibility = (nextVisibility) => {
    const normalized = normalizeVisibility(nextVisibility)
    setDraftVisibility(normalized)
    onChangeSong({
      ...song,
      ...normalized,
    })
    if (persistTimeoutRef.current) {
      clearTimeout(persistTimeoutRef.current)
    }
    persistTimeoutRef.current = setTimeout(() => {
      persistTimeoutRef.current = null
      onRequestAutosave?.()
    }, 180)
  }

  const toggleListValue = (key, value, checked) => {
    const current = Array.isArray(visibility[key]) ? [...visibility[key]] : []
    const numericValue = Number(value)
    if (checked) {
      if (!current.includes(numericValue)) current.push(numericValue)
    } else {
      const index = current.indexOf(numericValue)
      if (index >= 0) current.splice(index, 1)
    }
    pushVisibility({ ...visibility, [key]: current })
  }

  return (
    <>
      <div className="wpss-field-group">
        <label className="wpss-field">
          <span>Privacidad de la canción</span>
          <strong>{`Modo actual: ${getVisibilityLabel(visibility.visibility_mode)}`}</strong>
          <select
            value={visibility.visibility_mode}
            onChange={(event) => {
              const nextMode = event.target.value || 'private'
              pushVisibility({
                visibility_mode: nextMode,
                visibility_project_ids: nextMode === 'project' ? visibility.visibility_project_ids : [],
                visibility_group_ids: nextMode === 'groups' ? visibility.visibility_group_ids : [],
                visibility_user_ids: nextMode === 'users' ? visibility.visibility_user_ids : [],
              })
            }}
          >
            <option value="private">Privada para cancionero autenticado</option>
            <option value="public">Pública</option>
            <option value="groups">Solo agrupaciones musicales</option>
            <option value="users">Solo usuarios específicos</option>
            <option value="project">Solo proyectos seleccionados</option>
          </select>
          <small className="wpss-tags-help">
            Esta política controla quién puede ver la canción en el listado y abrir su lectura. La edición sigue dependiendo de autoría o capacidades de administración.
          </small>
        </label>
      </div>

      {visibility.visibility_mode === 'groups' ? (
        <div className="wpss-collections__shared-list">
          {groups.length ? groups.map((group) => {
            const groupId = Number(group?.id)
            const checked = visibility.visibility_group_ids.includes(groupId)
            return (
              <label key={groupId} className="wpss-collections__shared-item">
                <input type="checkbox" checked={checked} onChange={(event) => toggleListValue('visibility_group_ids', groupId, event.target.checked)} />
                <span>{group?.nombre || `Agrupación ${groupId}`}</span>
              </label>
            )
          }) : <span>No hay agrupaciones disponibles todavía.</span>}
        </div>
      ) : null}

      {visibility.visibility_mode === 'users' ? (
        <div className="wpss-collections__shared-list">
          {explicitUsers.length ? explicitUsers.map((user) => {
            const userId = Number(user?.id)
            const checked = visibility.visibility_user_ids.includes(userId)
            return (
              <label key={userId} className="wpss-collections__shared-item">
                <input type="checkbox" checked={checked} onChange={(event) => toggleListValue('visibility_user_ids', userId, event.target.checked)} />
                <span>{user?.nombre || `Usuario ${userId}`}</span>
              </label>
            )
          }) : <span>No hay usuarios disponibles todavía.</span>}
        </div>
      ) : null}

      {visibility.visibility_mode === 'project' ? (
        <div className="wpss-collections__shared-list">
          {availableProjects.length ? availableProjects.map((project) => {
            const projectId = Number(project?.id)
            const checked = visibility.visibility_project_ids.includes(projectId)
            return (
              <label key={projectId} className="wpss-collections__shared-item">
                <input type="checkbox" checked={checked} onChange={(event) => toggleListValue('visibility_project_ids', projectId, event.target.checked)} />
                <span>
                  <strong>{project?.titulo || `Proyecto #${projectId}`}</strong>
                  {Array.isArray(project?.colaboradores) && project.colaboradores.length ? (
                    <small>{` · ${project.colaboradores.map((item) => item.nombre).join(' · ')}`}</small>
                  ) : (
                    <small> · Sin colaboradores asignados</small>
                  )}
                </span>
              </label>
            )
          }) : <span>No hay proyectos disponibles todavía.</span>}
        </div>
      ) : null}
    </>
  )
}
