import { useCallback, useEffect, useMemo, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'

function createCollectionDraft() {
  return {
    id: null,
    nombre: '',
    descripcion: '',
    orden: [],
    items: [],
    compartida_con: [],
    owner_id: 0,
    owner_nombre: '',
    can_edit: true,
    is_owner: true,
    items_count: 0,
  }
}

function normalizeCollectionDraft(input, catalogMap) {
  const base = createCollectionDraft()
  const next = input && typeof input === 'object' ? { ...base, ...input } : base

  const order = []
  const seen = new Set()

  if (Array.isArray(next.orden)) {
    next.orden.forEach((value) => {
      const id = Number(value)
      if (!Number.isInteger(id) || id <= 0 || seen.has(id)) return
      seen.add(id)
      order.push(id)
    })
  }

  if (Array.isArray(next.items)) {
    next.items.forEach((item) => {
      const id = Number(item?.id)
      if (!Number.isInteger(id) || id <= 0 || seen.has(id)) return
      seen.add(id)
      order.push(id)
    })
  }

  const titleById = new Map()
  if (Array.isArray(next.items)) {
    next.items.forEach((item) => {
      const id = Number(item?.id)
      if (!Number.isInteger(id) || id <= 0) return
      if (item?.titulo) {
        titleById.set(id, String(item.titulo))
      }
    })
  }

  const detailById = new Map()
  if (Array.isArray(next.items)) {
    next.items.forEach((item) => {
      const id = Number(item?.id)
      if (!Number.isInteger(id) || id <= 0) return
      detailById.set(id, item)
    })
  }

  const items = order.map((id) => {
    const detail = detailById.get(id) || {}
    return {
      id,
      titulo: titleById.get(id) || catalogMap.get(id) || `Canción #${id}`,
      assigned_by_user_id: Number(detail?.assigned_by_user_id) || 0,
      assigned_by_user_name: detail?.assigned_by_user_name || '',
      assigned_by_author: !!detail?.assigned_by_author,
      assigned_at: detail?.assigned_at || '',
    }
  })

  const shared = []
  const sharedSeen = new Set()
  if (Array.isArray(next.compartida_con)) {
    next.compartida_con.forEach((value) => {
      const id = Number(value)
      if (!Number.isInteger(id) || id <= 0 || sharedSeen.has(id)) return
      sharedSeen.add(id)
      shared.push(id)
    })
  }

  return {
    ...next,
    nombre: String(next.nombre || ''),
    descripcion: String(next.descripcion || ''),
    orden: order,
    items,
    compartida_con: shared,
    id: next.id ? Number(next.id) : null,
    owner_id: next.owner_id ? Number(next.owner_id) : 0,
    owner_nombre: String(next.owner_nombre || ''),
    can_edit: next.can_edit !== false,
    is_owner: !!next.is_owner,
    items_count: order.length,
  }
}

export default function CollectionsManager({
  colleagues = [],
  colleaguesLoading = false,
  onCollectionsChanged,
}) {
  const { api, dispatch, wpData } = useAppState()
  const currentUserId = wpData?.currentUserId || 0
  const [collections, setCollections] = useState([])
  const [loading, setLoading] = useState(false)
  const [catalog, setCatalog] = useState([])
  const [catalogLoading, setCatalogLoading] = useState(false)
  const [activeId, setActiveId] = useState(null)
  const [isNewDraft, setIsNewDraft] = useState(false)
  const [detailLoading, setDetailLoading] = useState(false)
  const [draft, setDraft] = useState(() => createCollectionDraft())
  const [saving, setSaving] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [error, setError] = useState(null)
  const [addSongId, setAddSongId] = useState('')

  const catalogMap = useMemo(() => {
    const map = new Map()
    catalog.forEach((song) => {
      const id = Number(song?.id)
      if (!Number.isInteger(id) || id <= 0) return
      map.set(id, song?.titulo ? String(song.titulo) : `Canción #${id}`)
    })
    return map
  }, [catalog])

  const availableColleagues = useMemo(
    () =>
      Array.isArray(colleagues)
        ? colleagues.filter((colleague) => Number(colleague?.id) !== Number(currentUserId))
        : [],
    [colleagues, currentUserId],
  )

  const refreshCollections = useCallback(
    (preferredId = null) => {
      setLoading(true)
      setError(null)

      api
        .listCollections()
        .then((response) => {
          const items = Array.isArray(response?.data) ? response.data : []
          setCollections(items)
          if (typeof onCollectionsChanged === 'function') {
            onCollectionsChanged(items)
          }

          const hasPreferred =
            preferredId && items.some((item) => Number(item?.id) === Number(preferredId))
          if (hasPreferred) {
            setIsNewDraft(false)
            setActiveId(Number(preferredId))
            return
          }

          setActiveId((prev) => {
            if (prev && items.some((item) => Number(item?.id) === Number(prev))) {
              return prev
            }
            return items[0]?.id ? Number(items[0].id) : null
          })

          if (!items.length) {
            setDraft(createCollectionDraft())
            setIsNewDraft(false)
          }
        })
        .catch((requestError) => {
          const message = requestError?.payload?.message || 'No fue posible obtener las colecciones.'
          setError(message)
        })
        .finally(() => {
          setLoading(false)
        })
    },
    [api, onCollectionsChanged],
  )

  const refreshCatalog = useCallback(() => {
    setCatalogLoading(true)
    api
      .listPublicSongs({ page: 1, per_page: 100 })
      .then((response) => {
        const items = Array.isArray(response?.data) ? response.data : []
        setCatalog(items)
      })
      .catch(() => {
        setCatalog([])
      })
      .finally(() => {
        setCatalogLoading(false)
      })
  }, [api])

  useEffect(() => {
    refreshCollections()
    refreshCatalog()
  }, [refreshCollections, refreshCatalog])

  useEffect(() => {
    if (isNewDraft || !activeId) {
      return
    }

    setDetailLoading(true)
    setError(null)
    api
      .getCollection(activeId)
      .then((response) => {
        const raw = response?.data && typeof response.data === 'object' ? response.data : {}
        setDraft(normalizeCollectionDraft(raw, catalogMap))
      })
      .catch((requestError) => {
        const message =
          requestError?.payload?.message || 'No fue posible cargar el detalle de la colección.'
        setError(message)
      })
      .finally(() => {
        setDetailLoading(false)
      })
  }, [activeId, api, catalogMap, isNewDraft])

  const updateDraft = useCallback(
    (updater) => {
      setDraft((prev) => {
        const next = typeof updater === 'function' ? updater(prev) : updater
        return normalizeCollectionDraft(next, catalogMap)
      })
    },
    [catalogMap],
  )

  const startNewCollection = () => {
    setIsNewDraft(true)
    setActiveId(null)
    setAddSongId('')
    setError(null)
    setDraft(createCollectionDraft())
  }

  const handleSelectCollection = (id) => {
    setIsNewDraft(false)
    setActiveId(Number(id))
    setAddSongId('')
    setError(null)
  }

  const handleAddSong = () => {
    const songId = Number(addSongId)
    if (!Number.isInteger(songId) || songId <= 0) {
      return
    }
    updateDraft((prev) => ({
      ...prev,
      orden: prev.orden.concat(songId),
    }))
    setAddSongId('')
  }

  const handleMoveSong = (songId, direction) => {
    updateDraft((prev) => {
      const currentOrder = Array.isArray(prev.orden) ? [...prev.orden] : []
      const index = currentOrder.findIndex((id) => Number(id) === Number(songId))
      if (index < 0) return prev
      const target = direction === 'up' ? index - 1 : index + 1
      if (target < 0 || target >= currentOrder.length) return prev
      ;[currentOrder[index], currentOrder[target]] = [currentOrder[target], currentOrder[index]]
      return { ...prev, orden: currentOrder }
    })
  }

  const handleRemoveSong = (songId) => {
    updateDraft((prev) => ({
      ...prev,
      orden: prev.orden.filter((id) => Number(id) !== Number(songId)),
    }))
  }

  const handleToggleSharedUser = (userId, checked) => {
    const normalizedId = Number(userId)
    if (!Number.isInteger(normalizedId) || normalizedId <= 0) return
    updateDraft((prev) => {
      const current = Array.isArray(prev.compartida_con) ? [...prev.compartida_con] : []
      if (checked) {
        if (!current.includes(normalizedId)) {
          current.push(normalizedId)
        }
      } else {
        const index = current.indexOf(normalizedId)
        if (index >= 0) current.splice(index, 1)
      }
      return { ...prev, compartida_con: current }
    })
  }

  const handleSave = () => {
    const payload = {
      id: draft.id || null,
      nombre: (draft.nombre || '').trim(),
      descripcion: draft.descripcion || '',
      orden: Array.isArray(draft.orden) ? draft.orden : [],
      compartida_con: Array.isArray(draft.compartida_con) ? draft.compartida_con : [],
    }

    if (!payload.nombre) {
      setError('El nombre de la colección es obligatorio.')
      return
    }

    setSaving(true)
    setError(null)
    api
      .saveCollection(payload)
      .then((response) => {
        const raw = response?.data && typeof response.data === 'object' ? response.data : {}
        const normalized = normalizeCollectionDraft(raw, catalogMap)
        setDraft(normalized)
        setIsNewDraft(false)
        setActiveId(normalized.id || null)
        dispatch({
          type: 'SET_STATE',
          payload: {
            feedback: { message: 'Colección guardada.', type: 'success' },
            error: null,
          },
        })
        refreshCollections(normalized.id || null)
      })
      .catch((requestError) => {
        const message = requestError?.payload?.message || 'No fue posible guardar la colección.'
        setError(message)
        dispatch({ type: 'SET_STATE', payload: { error: message } })
      })
      .finally(() => {
        setSaving(false)
      })
  }

  const handleDelete = () => {
    if (!draft.id) return
    const confirmed = window.confirm('¿Eliminar la colección seleccionada?')
    if (!confirmed) return

    setDeleting(true)
    setError(null)
    api
      .deleteCollection(draft.id)
      .then(() => {
        dispatch({
          type: 'SET_STATE',
          payload: {
            feedback: { message: 'Colección eliminada.', type: 'success' },
            error: null,
          },
        })
        setDraft(createCollectionDraft())
        setActiveId(null)
        setIsNewDraft(false)
        refreshCollections()
      })
      .catch((requestError) => {
        const message = requestError?.payload?.message || 'No fue posible eliminar la colección.'
        setError(message)
        dispatch({ type: 'SET_STATE', payload: { error: message } })
      })
      .finally(() => {
        setDeleting(false)
      })
  }

  const draftSongIds = new Set(Array.isArray(draft.orden) ? draft.orden.map((value) => Number(value)) : [])
  const availableSongs = catalog.filter((song) => !draftSongIds.has(Number(song?.id)))
  const canEdit = draft?.can_edit !== false

  return (
    <section className="wpss-collections">
      <aside className="wpss-collections__sidebar">
        <div className="wpss-collections__sidebar-header">
          <strong>Colecciones</strong>
          <div>
            <button type="button" className="button button-small" onClick={startNewCollection}>
              Nueva
            </button>
            <button
              type="button"
              className="button button-small button-secondary"
              onClick={() => refreshCollections()}
              disabled={loading}
            >
              Actualizar
            </button>
          </div>
        </div>
        {loading ? <p className="wpss-collections__hint">Cargando colecciones…</p> : null}
        <ul className="wpss-collections__list">
          {collections.map((collection) => (
            <li
              key={collection.id}
              className={
                !isNewDraft && Number(activeId) === Number(collection.id)
                  ? 'is-active'
                  : ''
              }
            >
              <button
                type="button"
                className="wpss-collections__item"
                onClick={() => handleSelectCollection(collection.id)}
              >
                <span>{collection.nombre}</span>
                <span className="wpss-collections__badge">{collection.items_count || 0}</span>
              </button>
            </li>
          ))}
          {!collections.length && !loading ? (
            <li className="wpss-collections__hint">Aún no hay colecciones.</li>
          ) : null}
        </ul>
      </aside>

      <div className={`wpss-collections__editor ${detailLoading ? 'is-loading' : ''}`}>
        {error ? <p className="wpss-error">{error}</p> : null}
        {!isNewDraft && !activeId && !collections.length ? (
          <p className="wpss-empty">Crea tu primera colección para organizar canciones.</p>
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
                  maxLength={128}
                />
              </label>
              <label className="wpss-field">
                <span>Descripción</span>
                <textarea
                  rows={3}
                  value={draft.descripcion}
                  onChange={(event) =>
                    updateDraft((prev) => ({ ...prev, descripcion: event.target.value }))
                  }
                  disabled={!canEdit || saving || deleting}
                />
              </label>
            </div>

            <p className="wpss-collections__hint">
              Propietario:{' '}
              {draft.owner_nombre
                ? draft.owner_nombre
                : draft.owner_id
                  ? `Usuario ${draft.owner_id}`
                  : 'Sin propietario'}
            </p>
            {!canEdit ? (
              <p className="wpss-collections__hint">
                Esta colección está compartida contigo en modo lectura.
              </p>
            ) : null}

            <div className="wpss-collections__shared">
              <strong>Compartir con colegas</strong>
              {colleaguesLoading ? (
                <p className="wpss-collections__hint">Cargando colegas musicales…</p>
              ) : (
                <div className="wpss-collections__shared-list">
                  {availableColleagues.map((colleague) => {
                    const colleagueId = Number(colleague?.id)
                    const checked = draft.compartida_con.includes(colleagueId)
                    return (
                      <label key={colleagueId} className="wpss-collections__shared-item">
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={(event) =>
                            handleToggleSharedUser(colleagueId, event.target.checked)
                          }
                          disabled={!canEdit || saving || deleting}
                        />
                        <span>{colleague?.nombre || `Usuario ${colleagueId}`}</span>
                      </label>
                    )
                  })}
                  {!availableColleagues.length ? (
                    <p className="wpss-collections__hint">No hay colegas disponibles para compartir.</p>
                  ) : null}
                </div>
              )}
            </div>

            <div className="wpss-collections__songs">
              <strong>Canciones</strong>
              <div className="wpss-collections__add">
                <select
                  value={addSongId}
                  onChange={(event) => setAddSongId(event.target.value)}
                  disabled={!canEdit || saving || deleting || catalogLoading}
                >
                  <option value="">Selecciona una canción</option>
                  {availableSongs.map((song) => (
                    <option key={song.id} value={song.id}>
                      {song.titulo}
                    </option>
                  ))}
                </select>
                <button
                  type="button"
                  className="button button-secondary"
                  onClick={handleAddSong}
                  disabled={!canEdit || !addSongId || saving || deleting}
                >
                  Añadir
                </button>
              </div>
              {catalogLoading ? (
                <p className="wpss-collections__hint">Cargando canciones…</p>
              ) : (
                <p className="wpss-collections__hint">
                  Catálogo limitado a 100 canciones por carga.
                </p>
              )}
              <ul className="wpss-collection-songs">
                {draft.items.map((item, index) => (
                  <li key={`${item.id}-${index}`}>
                    <span className="wpss-collection-songs__label">{item.titulo}{item.assigned_by_user_name ? ` · ${item.assigned_by_author ? 'Transcriptor' : 'Asignó'}: ${item.assigned_by_user_name}` : ""}</span>
                    <div className="wpss-collection-songs__actions">
                      <button
                        type="button"
                        className="button button-small"
                        onClick={() => handleMoveSong(item.id, 'up')}
                        disabled={!canEdit || index === 0 || saving || deleting}
                      >
                        ↑
                      </button>
                      <button
                        type="button"
                        className="button button-small"
                        onClick={() => handleMoveSong(item.id, 'down')}
                        disabled={!canEdit || index === draft.items.length - 1 || saving || deleting}
                      >
                        ↓
                      </button>
                      <button
                        type="button"
                        className="button button-small button-link-delete"
                        onClick={() => handleRemoveSong(item.id)}
                        disabled={!canEdit || saving || deleting}
                      >
                        Quitar
                      </button>
                    </div>
                  </li>
                ))}
                {!draft.items.length ? (
                  <li className="wpss-empty">La colección no tiene canciones.</li>
                ) : null}
              </ul>
            </div>

            <div className="wpss-collections__actions">
              <button
                type="button"
                className="button button-primary"
                onClick={handleSave}
                disabled={!canEdit || saving || deleting}
              >
                {saving ? 'Guardando…' : 'Guardar colección'}
              </button>
              {draft.id ? (
                <button
                  type="button"
                  className="button button-danger"
                  onClick={handleDelete}
                  disabled={!canEdit || saving || deleting}
                >
                  {deleting ? 'Eliminando…' : 'Eliminar colección'}
                </button>
              ) : null}
            </div>
          </>
        )}
      </div>
    </section>
  )
}
