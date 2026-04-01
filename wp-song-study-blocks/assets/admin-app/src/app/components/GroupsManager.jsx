import { useCallback, useEffect, useMemo, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'

function createGroupDraft() {
  return {
    id: null,
    nombre: '',
    descripcion: '',
    miembros: [],
    owner_id: 0,
    owner_nombre: '',
    can_edit: true,
  }
}

function normalizeGroup(input) {
  const base = createGroupDraft()
  const next = input && typeof input === 'object' ? { ...base, ...input } : base
  const members = Array.isArray(next.miembros)
    ? next.miembros
        .map((member) => ({
          user_id: Number(member?.user_id || member?.id || 0),
          nombre: String(member?.nombre || ''),
          role: String(member?.role || 'contribuidor'),
          role_label: String(member?.role_label || member?.role || 'contribuidor'),
        }))
        .filter((member) => Number.isInteger(member.user_id) && member.user_id > 0)
    : []

  return {
    ...next,
    id: next.id ? Number(next.id) : null,
    owner_id: next.owner_id ? Number(next.owner_id) : 0,
    owner_nombre: String(next.owner_nombre || ''),
    nombre: String(next.nombre || ''),
    descripcion: String(next.descripcion || ''),
    miembros: members,
    can_edit: next.can_edit !== false,
  }
}

export default function GroupsManager() {
  const { api, dispatch, wpData } = useAppState()
  const currentUserId = Number(wpData?.currentUserId || 0)
  const [groups, setGroups] = useState([])
  const [colleagues, setColleagues] = useState([])
  const [loading, setLoading] = useState(false)
  const [colleaguesLoading, setColleaguesLoading] = useState(false)
  const [detailLoading, setDetailLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [error, setError] = useState(null)
  const [activeId, setActiveId] = useState(null)
  const [isNewDraft, setIsNewDraft] = useState(false)
  const [draft, setDraft] = useState(createGroupDraft())

  const availableColleagues = useMemo(
    () => colleagues.filter((user) => Number(user?.id) !== currentUserId),
    [colleagues, currentUserId],
  )

  const refreshGroups = useCallback(
    (preferredId = null) => {
      setLoading(true)
      setError(null)
      api
        .listGroups()
        .then((response) => {
          const items = Array.isArray(response?.data) ? response.data : []
          setGroups(items)
          if (preferredId && items.some((item) => Number(item?.id) === Number(preferredId))) {
            setActiveId(Number(preferredId))
            setIsNewDraft(false)
            return
          }
          setActiveId((prev) => {
            if (prev && items.some((item) => Number(item?.id) === Number(prev))) return prev
            return items[0]?.id ? Number(items[0].id) : null
          })
        })
        .catch((requestError) => {
          setError(requestError?.payload?.message || 'No fue posible obtener las agrupaciones musicales.')
        })
        .finally(() => {
          setLoading(false)
        })
    },
    [api],
  )

  useEffect(() => {
    refreshGroups()
    setColleaguesLoading(true)
    api
      .listColleagues()
      .then((response) => {
        setColleagues(Array.isArray(response?.data) ? response.data : [])
      })
      .catch(() => {
        setColleagues([])
      })
      .finally(() => {
        setColleaguesLoading(false)
      })
  }, [api, refreshGroups])

  useEffect(() => {
    if (isNewDraft || !activeId) return
    setDetailLoading(true)
    setError(null)
    api
      .getGroup(activeId)
      .then((response) => {
        setDraft(normalizeGroup(response?.data))
      })
      .catch((requestError) => {
        setError(requestError?.payload?.message || 'No fue posible cargar la agrupación musical.')
      })
      .finally(() => {
        setDetailLoading(false)
      })
  }, [activeId, api, isNewDraft])

  const updateDraft = (updater) => {
    setDraft((prev) => normalizeGroup(typeof updater === 'function' ? updater(prev) : updater))
  }

  const startNewGroup = () => {
    setIsNewDraft(true)
    setActiveId(null)
    setError(null)
    setDraft(createGroupDraft())
  }

  const handleSelectGroup = (id) => {
    setIsNewDraft(false)
    setActiveId(Number(id))
    setError(null)
  }

  const handleToggleMember = (user, checked) => {
    const userId = Number(user?.id)
    if (!Number.isInteger(userId) || userId <= 0) return
    updateDraft((prev) => {
      const members = Array.isArray(prev.miembros) ? [...prev.miembros] : []
      const index = members.findIndex((member) => Number(member?.user_id) === userId)
      if (checked && index < 0) {
        members.push({ user_id: userId, nombre: user?.nombre || '', role: 'contribuidor', role_label: 'Contribuidor' })
      }
      if (!checked && index >= 0) {
        members.splice(index, 1)
      }
      return { ...prev, miembros: members }
    })
  }

  const handleChangeMemberRole = (userId, role) => {
    updateDraft((prev) => ({
      ...prev,
      miembros: prev.miembros.map((member) => (
        Number(member?.user_id) === Number(userId)
          ? { ...member, role, role_label: role }
          : member
      )),
    }))
  }

  const handleSave = () => {
    const payload = {
      id: draft.id || null,
      nombre: draft.nombre.trim(),
      descripcion: draft.descripcion || '',
      miembros: Array.isArray(draft.miembros)
        ? draft.miembros.map((member) => ({ user_id: member.user_id, role: member.role }))
        : [],
    }

    if (!payload.nombre) {
      setError('El nombre de la agrupación es obligatorio.')
      return
    }

    setSaving(true)
    setError(null)
    api
      .saveGroup(payload)
      .then((response) => {
        const next = normalizeGroup(response?.data)
        setDraft(next)
        setActiveId(next.id || null)
        setIsNewDraft(false)
        dispatch({
          type: 'SET_STATE',
          payload: { feedback: { message: 'Agrupación musical guardada.', type: 'success' }, error: null },
        })
        refreshGroups(next.id || null)
      })
      .catch((requestError) => {
        const message = requestError?.payload?.message || 'No fue posible guardar la agrupación musical.'
        setError(message)
        dispatch({ type: 'SET_STATE', payload: { error: message } })
      })
      .finally(() => {
        setSaving(false)
      })
  }

  const handleDelete = () => {
    if (!draft.id) return
    const confirmed = window.confirm('¿Eliminar la agrupación musical seleccionada?')
    if (!confirmed) return

    setDeleting(true)
    setError(null)
    api
      .deleteGroup(draft.id)
      .then(() => {
        setDraft(createGroupDraft())
        setActiveId(null)
        setIsNewDraft(false)
        dispatch({
          type: 'SET_STATE',
          payload: { feedback: { message: 'Agrupación musical eliminada.', type: 'success' }, error: null },
        })
        refreshGroups()
      })
      .catch((requestError) => {
        const message = requestError?.payload?.message || 'No fue posible eliminar la agrupación musical.'
        setError(message)
        dispatch({ type: 'SET_STATE', payload: { error: message } })
      })
      .finally(() => {
        setDeleting(false)
      })
  }

  const memberIds = new Set((draft.miembros || []).map((member) => Number(member?.user_id)))
  const canEdit = draft?.can_edit !== false

  return (
    <section className="wpss-collections">
      <aside className="wpss-collections__sidebar">
        <div className="wpss-collections__sidebar-header">
          <strong>Agrupaciones</strong>
          <div>
            <button type="button" className="button button-small" onClick={startNewGroup}>Nueva</button>
            <button type="button" className="button button-small button-secondary" onClick={() => refreshGroups()} disabled={loading}>Actualizar</button>
          </div>
        </div>
        {loading ? <p className="wpss-collections__hint">Cargando agrupaciones…</p> : null}
        <ul className="wpss-collections__list">
          {groups.map((group) => (
            <li key={group.id} className={!isNewDraft && Number(activeId) === Number(group.id) ? 'is-active' : ''}>
              <button type="button" className="wpss-collections__item" onClick={() => handleSelectGroup(group.id)}>
                <span>{group.nombre}</span>
                <span className="wpss-collections__badge">{group.members_count || 0}</span>
              </button>
            </li>
          ))}
          {!groups.length && !loading ? <li className="wpss-collections__hint">Aún no hay agrupaciones.</li> : null}
        </ul>
      </aside>

      <div className={`wpss-collections__editor ${detailLoading ? 'is-loading' : ''}`}>
        {error ? <p className="wpss-error">{error}</p> : null}
        {!isNewDraft && !activeId && !groups.length ? (
          <p className="wpss-empty">Crea tu primera agrupación musical para controlar acceso a audios y fotos.</p>
        ) : (
          <>
            <div className="wpss-field-group">
              <label className="wpss-field">
                <span>Nombre</span>
                <input
                  type="text"
                  value={draft.nombre}
                  onChange={(event) => updateDraft((prev) => ({ ...prev, nombre: event.target.value }))}
                  disabled={!canEdit || saving || deleting}
                />
              </label>
              <label className="wpss-field">
                <span>Descripción</span>
                <textarea
                  rows={3}
                  value={draft.descripcion}
                  onChange={(event) => updateDraft((prev) => ({ ...prev, descripcion: event.target.value }))}
                  disabled={!canEdit || saving || deleting}
                />
              </label>
            </div>

            <p className="wpss-collections__hint">
              Propietario: {draft.owner_nombre || (draft.owner_id ? `Usuario ${draft.owner_id}` : 'Pendiente')}
            </p>
            {!canEdit ? <p className="wpss-collections__hint">Tienes acceso a esta agrupación en modo lectura.</p> : null}

            <div className="wpss-collections__shared">
              <strong>Miembros y roles</strong>
              {colleaguesLoading ? (
                <p className="wpss-collections__hint">Cargando colegas musicales…</p>
              ) : (
                <div className="wpss-collections__shared-list">
                  {availableColleagues.map((user) => {
                    const userId = Number(user?.id)
                    const isMember = memberIds.has(userId)
                    const currentRole = draft.miembros.find((member) => Number(member?.user_id) === userId)?.role || 'contribuidor'
                    return (
                      <div key={userId} className="wpss-collections__shared-item" style={{ display: 'grid', gridTemplateColumns: '1fr auto', gap: '0.75rem', alignItems: 'center' }}>
                        <label>
                          <input
                            type="checkbox"
                            checked={isMember}
                            onChange={(event) => handleToggleMember(user, event.target.checked)}
                            disabled={!canEdit || saving || deleting}
                          />
                          <span style={{ marginLeft: '0.5rem' }}>{user?.nombre || `Usuario ${userId}`}</span>
                        </label>
                        <select
                          value={currentRole}
                          onChange={(event) => handleChangeMemberRole(userId, event.target.value)}
                          disabled={!isMember || !canEdit || saving || deleting}
                        >
                          <option value="admin">Admin</option>
                          <option value="colega">Colega musical</option>
                          <option value="contribuidor">Contribuidor</option>
                        </select>
                      </div>
                    )
                  })}
                </div>
              )}
            </div>

            <div className="wpss-collections__actions">
              <button type="button" className="button button-primary" onClick={handleSave} disabled={!canEdit || saving || deleting}>
                {saving ? 'Guardando…' : 'Guardar agrupación'}
              </button>
              {draft.id ? (
                <button type="button" className="button button-secondary" onClick={handleDelete} disabled={!canEdit || saving || deleting}>
                  {deleting ? 'Eliminando…' : 'Eliminar agrupación'}
                </button>
              ) : null}
            </div>
          </>
        )}
      </div>
    </section>
  )
}
