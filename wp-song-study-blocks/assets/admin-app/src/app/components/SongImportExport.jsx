import { useEffect, useMemo, useRef, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'

const SONGS_PER_PAGE = 25

function submitExportForm({ adminPostUrl, nonce, scope, songIds, includeAttachments }) {
  if (typeof document === 'undefined' || !adminPostUrl) {
    return
  }

  const form = document.createElement('form')
  form.method = 'POST'
  form.action = adminPostUrl
  form.style.display = 'none'

  const fields = {
    action: 'wpss_song_export',
    _wpnonce: nonce || '',
    scope,
    song_ids: songIds.join(','),
    include_attachments: includeAttachments ? '1' : '0',
  }

  Object.entries(fields).forEach(([name, value]) => {
    const input = document.createElement('input')
    input.type = 'hidden'
    input.name = name
    input.value = String(value)
    form.appendChild(input)
  })

  document.body.appendChild(form)
  form.submit()
  window.setTimeout(() => {
    form.remove()
  }, 1000)
}

function formatWarnings(warnings) {
  if (!Array.isArray(warnings) || !warnings.length) return null
  return warnings
    .map((warning) => warning?.message || '')
    .filter(Boolean)
    .slice(0, 4)
    .join(' · ')
}

export default function SongImportExport() {
  const { api, wpData } = useAppState()
  const [songs, setSongs] = useState([])
  const [pagination, setPagination] = useState({ page: 1, totalPages: 1, totalItems: 0 })
  const [loadingSongs, setLoadingSongs] = useState(true)
  const [songsError, setSongsError] = useState(null)
  const [searchDraft, setSearchDraft] = useState('')
  const [search, setSearch] = useState('')
  const [selectedIds, setSelectedIds] = useState([])
  const [includeAttachments, setIncludeAttachments] = useState(true)
  const [exportAll, setExportAll] = useState(false)
  const [importFile, setImportFile] = useState(null)
  const [restoreAttachments, setRestoreAttachments] = useState(true)
  const [restoreCollections, setRestoreCollections] = useState(true)
  const [restoreVisibility, setRestoreVisibility] = useState(true)
  const [restoreStatuses, setRestoreStatuses] = useState(true)
  const [restoreExtraMeta, setRestoreExtraMeta] = useState(true)
  const [importing, setImporting] = useState(false)
  const [importError, setImportError] = useState(null)
  const [importReport, setImportReport] = useState(null)
  const fileInputRef = useRef(null)

  useEffect(() => {
    const timer = window.setTimeout(() => {
      setSearch(searchDraft.trim())
      setPagination((prev) => ({ ...prev, page: 1 }))
    }, 220)
    return () => window.clearTimeout(timer)
  }, [searchDraft])

  useEffect(() => {
    let cancelled = false
    setLoadingSongs(true)
    setSongsError(null)

    api
      .listSongs({
        page: pagination.page,
        per_page: SONGS_PER_PAGE,
        search,
      })
      .then((response) => {
        if (cancelled) return
        const nextSongs = Array.isArray(response?.data) ? response.data : []
        const totalItems = Number.parseInt(response?.headers?.get('X-WP-Total') || '', 10) || nextSongs.length
        const totalPages = Number.parseInt(response?.headers?.get('X-WP-TotalPages') || '', 10) || 1
        if ((pagination.page || 1) > totalPages) {
          setPagination((prev) => ({ ...prev, page: totalPages }))
          return
        }
        setSongs(nextSongs)
        setPagination((prev) => ({
          page: prev.page || 1,
          totalPages,
          totalItems,
        }))
      })
      .catch((error) => {
        if (cancelled) return
        setSongsError(error?.payload?.message || 'No fue posible cargar el catálogo de canciones.')
      })
      .finally(() => {
        if (!cancelled) {
          setLoadingSongs(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [api, pagination.page, search])

  const selectedOnPage = useMemo(
    () => songs.filter((song) => selectedIds.includes(Number(song?.id || 0))).length,
    [selectedIds, songs],
  )

  const selectedCount = selectedIds.length

  const toggleSong = (songId) => {
    const numericId = Number(songId || 0)
    if (!numericId) return
    setSelectedIds((prev) =>
      prev.includes(numericId) ? prev.filter((id) => id !== numericId) : prev.concat(numericId),
    )
  }

  const toggleCurrentPage = () => {
    const pageIds = songs.map((song) => Number(song?.id || 0)).filter(Boolean)
    const allSelected = pageIds.length > 0 && pageIds.every((id) => selectedIds.includes(id))
    setSelectedIds((prev) => {
      if (allSelected) {
        return prev.filter((id) => !pageIds.includes(id))
      }
      return Array.from(new Set(prev.concat(pageIds)))
    })
  }

  const handleExport = () => {
    if (!exportAll && !selectedCount) {
      setSongsError('Selecciona al menos una canción o exporta todo el catálogo accesible.')
      return
    }

    setSongsError(null)
    submitExportForm({
      adminPostUrl: wpData?.adminPostUrl || '',
      nonce: wpData?.songExportNonce || '',
      scope: exportAll ? 'all' : 'selection',
      songIds: exportAll ? [] : selectedIds,
      includeAttachments,
    })
  }

  const handleImport = () => {
    if (!importFile) {
      setImportError('Selecciona un archivo .zip o .json antes de importar.')
      return
    }

    const formData = new FormData()
    formData.append('package', importFile)
    formData.append('restore_attachments', restoreAttachments ? '1' : '')
    formData.append('restore_collections', restoreCollections ? '1' : '')
    formData.append('restore_visibility', restoreVisibility ? '1' : '')
    formData.append('restore_statuses', restoreStatuses ? '1' : '')
    formData.append('restore_extra_meta', restoreExtraMeta ? '1' : '')

    setImporting(true)
    setImportError(null)
    setImportReport(null)

    api
      .importSongPackage(formData)
      .then((response) => {
        setImportReport(response?.data || null)
        setImportFile(null)
        if (fileInputRef.current) {
          fileInputRef.current.value = ''
        }
      })
      .catch((error) => {
        setImportError(error?.payload?.message || 'No fue posible importar el paquete.')
      })
      .finally(() => {
        setImporting(false)
      })
  }

  return (
    <section className="wpss-import-export">
      <header className="wpss-import-export__hero">
        <div>
          <h2>Importar y exportar canciones</h2>
          <p className="wpss-import-export__lead">
            El paquete rescata payload completo de canción, meta complementaria, colecciones, tags,
            visibilidad, estados y adjuntos cuando el archivo puede leerse desde Drive.
          </p>
        </div>
        <div className="wpss-import-export__status">
          <strong>Submenú operativo</strong>
          <span>Uso recomendado: exportar en ZIP con adjuntos y reimportar desde esta misma pantalla.</span>
        </div>
      </header>

      <div className="wpss-import-export__grid">
        <section className="wpss-import-export__card">
          <div className="wpss-import-export__card-head">
            <div>
              <h3>Exportar canciones</h3>
              <p>
                Selecciona canciones individuales o exporta todo el catálogo accesible. El ZIP incluye
                `manifest.json` y, cuando es posible, los binarios de adjuntos.
              </p>
            </div>
            <button type="button" className="button button-primary" onClick={handleExport}>
              Exportar ahora
            </button>
          </div>

          <div className="wpss-import-export__options">
            <label className="wpss-import-export__check">
              <input
                type="checkbox"
                checked={exportAll}
                onChange={(event) => setExportAll(event.target.checked)}
              />
              <span>Exportar todo el catálogo accesible</span>
            </label>
            <label className="wpss-import-export__check">
              <input
                type="checkbox"
                checked={includeAttachments}
                onChange={(event) => setIncludeAttachments(event.target.checked)}
              />
              <span>Incluir adjuntos cuando puedan descargarse desde Drive</span>
            </label>
          </div>

          <div className="wpss-import-export__toolbar">
            <label className="wpss-field">
              <span>Buscar canciones</span>
              <input
                type="search"
                value={searchDraft}
                onChange={(event) => setSearchDraft(event.target.value)}
                placeholder="Título, autor o tonalidad"
              />
            </label>
            <div className="wpss-import-export__toolbar-meta">
              <span>{selectedCount} seleccionadas</span>
              <button type="button" className="button button-secondary" onClick={toggleCurrentPage}>
                {songs.length && selectedOnPage === songs.length ? 'Deseleccionar página' : 'Seleccionar página'}
              </button>
            </div>
          </div>

          {songsError ? <p className="wpss-error">{songsError}</p> : null}

          <div className="wpss-import-export__catalog">
            {loadingSongs ? (
              <p className="wpss-collections__hint">Cargando canciones…</p>
            ) : songs.length ? (
              songs.map((song) => {
                const songId = Number(song?.id || 0)
                const checked = selectedIds.includes(songId)
                return (
                  <label key={songId} className={`wpss-import-export__song ${checked ? 'is-selected' : ''}`}>
                    <input
                      type="checkbox"
                      checked={checked}
                      onChange={() => toggleSong(songId)}
                      disabled={exportAll}
                    />
                    <div>
                      <strong>{song?.titulo || `Canción ${songId}`}</strong>
                      <span>
                        {song?.autor_nombre || 'Sin autor'} · {song?.tonica || 'Sin tónica'} ·{' '}
                        {song?.campo_armonico || 'Sin campo'}
                      </span>
                    </div>
                  </label>
                )
              })
            ) : (
              <p className="wpss-collections__hint">No hay canciones que coincidan con la búsqueda actual.</p>
            )}
          </div>

          <div className="wpss-import-export__pager">
            <button
              type="button"
              className="button button-secondary"
              onClick={() => setPagination((prev) => ({ ...prev, page: Math.max(1, prev.page - 1) }))}
              disabled={pagination.page <= 1 || loadingSongs}
            >
              Anterior
            </button>
            <span>
              Página {pagination.page} de {pagination.totalPages} · {pagination.totalItems} canciones
            </span>
            <button
              type="button"
              className="button button-secondary"
              onClick={() =>
                setPagination((prev) => ({
                  ...prev,
                  page: Math.min(prev.totalPages || 1, prev.page + 1),
                }))
              }
              disabled={pagination.page >= pagination.totalPages || loadingSongs}
            >
              Siguiente
            </button>
          </div>
        </section>

        <section className="wpss-import-export__card">
          <div className="wpss-import-export__card-head">
            <div>
              <h3>Importar paquete</h3>
              <p>
                La importación recrea la canción vía el guardado canónico del plugin y conserva
                procedencia, warnings y snapshots de lo que no pueda rehidratarse exactamente.
              </p>
            </div>
            <button type="button" className="button button-primary" onClick={handleImport} disabled={importing}>
              {importing ? 'Importando…' : 'Importar paquete'}
            </button>
          </div>

          <div className="wpss-import-export__upload">
            <label className="wpss-field">
              <span>Archivo de paquete</span>
              <input
                ref={fileInputRef}
                type="file"
                accept=".zip,.json,application/zip,application/json"
                onChange={(event) => setImportFile(event.target.files?.[0] || null)}
              />
            </label>
            <div className="wpss-import-export__options">
              <label className="wpss-import-export__check">
                <input
                  type="checkbox"
                  checked={restoreAttachments}
                  onChange={(event) => setRestoreAttachments(event.target.checked)}
                />
                <span>Restaurar adjuntos en el Drive del usuario actual</span>
              </label>
              <label className="wpss-import-export__check">
                <input
                  type="checkbox"
                  checked={restoreCollections}
                  onChange={(event) => setRestoreCollections(event.target.checked)}
                />
                <span>Restaurar colecciones y asignaciones</span>
              </label>
              <label className="wpss-import-export__check">
                <input
                  type="checkbox"
                  checked={restoreVisibility}
                  onChange={(event) => setRestoreVisibility(event.target.checked)}
                />
                <span>Restaurar restricciones de visibilidad y proyectos de ensayo cuando existan</span>
              </label>
              <label className="wpss-import-export__check">
                <input
                  type="checkbox"
                  checked={restoreStatuses}
                  onChange={(event) => setRestoreStatuses(event.target.checked)}
                />
                <span>Restaurar estados de transcripción y ensayo</span>
              </label>
              <label className="wpss-import-export__check">
                <input
                  type="checkbox"
                  checked={restoreExtraMeta}
                  onChange={(event) => setRestoreExtraMeta(event.target.checked)}
                />
                <span>Conservar meta adicional no cubierta por el guardado estándar</span>
              </label>
            </div>
          </div>

          {importError ? <p className="wpss-error">{importError}</p> : null}

          {importReport ? (
            <div className="wpss-import-export__report">
              <div className="wpss-import-export__report-summary">
                <strong>
                  {importReport?.songs_imported || 0} importadas · {importReport?.songs_failed || 0} fallidas
                </strong>
                <span>{importReport?.attachments_restored || 0} adjuntos restaurados</span>
              </div>

              {Array.isArray(importReport?.items) && importReport.items.length ? (
                <div className="wpss-import-export__report-list">
                  {importReport.items.map((item, index) => (
                    <article key={`${item?.id || item?.source_title || 'item'}-${index}`} className={`wpss-import-export__report-item ${item?.ok ? 'is-ok' : 'is-error'}`}>
                      <strong>{item?.title || item?.source_title || `Canción ${index + 1}`}</strong>
                      <span>{item?.message || (item?.ok ? 'Importada.' : 'Falló la importación.')}</span>
                      {formatWarnings(item?.warnings) ? (
                        <small>{formatWarnings(item.warnings)}</small>
                      ) : null}
                    </article>
                  ))}
                </div>
              ) : null}
            </div>
          ) : (
            <p className="wpss-collections__hint">
              La canción importada queda como un nuevo registro. La procedencia se guarda en meta interna
              para auditoría y rescate futuro.
            </p>
          )}
        </section>
      </div>
    </section>
  )
}
