import { useMemo, useState } from 'react'
import {
  REHEARSAL_STATUS_OPTIONS,
  TRANSCRIPTION_STATUS_OPTIONS,
} from '../songStatus.js'

const FILTER_TABS = [
  { id: 'harmonia', label: 'Información armónica' },
  { id: 'catalogo', label: 'Colecciones y tags' },
  { id: 'estado', label: 'Estado' },
]

export default function SongFiltersPanel({
  filters,
  onChangeFilters,
  onResetFilters,
  tonicas = [],
  collections = [],
  tags = [],
  labels = {},
}) {
  const [activeTab, setActiveTab] = useState('harmonia')
  const safeFilters = filters || {}
  const tabOptions = useMemo(() => FILTER_TABS, [])

  const setFilter = (key, value) => {
    onChangeFilters?.({ ...safeFilters, [key]: value })
  }

  return (
    <section className="wpss-filter-deck">
      <div className="wpss-filter-deck__hero">
        <label className="wpss-filter-deck__search">
          <span>{labels.searchLabel || 'Buscar'}</span>
          <input
            type="search"
            value={safeFilters.search || ''}
            placeholder={labels.searchPlaceholder || 'Título, artista o transcriptor'}
            onChange={(event) => setFilter('search', event.target.value)}
          />
        </label>
        <button className="button button-secondary wpss-filter-deck__reset" type="button" onClick={onResetFilters}>
          {labels.clearLabel || 'Limpiar filtros'}
        </button>
      </div>

      <div className="wpss-filter-deck__tabs" role="tablist" aria-label="Filtros">
        {tabOptions.map((tab) => (
          <button
            key={tab.id}
            type="button"
            role="tab"
            aria-selected={activeTab === tab.id}
            className={`wpss-filter-deck__tab ${activeTab === tab.id ? 'is-active' : ''}`}
            onClick={() => setActiveTab(tab.id)}
          >
            {tab.label}
          </button>
        ))}
      </div>

      <div className="wpss-filter-deck__panel">
        {activeTab === 'harmonia' ? (
          <div className="wpss-filter-deck__grid wpss-filter-deck__grid--triple">
            <label className="wpss-field">
              <span>{labels.tonicaLabel || 'Tónica'}</span>
              <select value={safeFilters.tonica || ''} onChange={(event) => setFilter('tonica', event.target.value)}>
                <option value="">{labels.emptyDashLabel || '—'}</option>
                {tonicas.map((tonica) => (
                  <option key={tonica} value={tonica}>
                    {tonica}
                  </option>
                ))}
              </select>
            </label>
            <label className="wpss-field">
              <span>{labels.loansLabel || 'Préstamos'}</span>
              <select value={safeFilters.con_prestamos || ''} onChange={(event) => setFilter('con_prestamos', event.target.value)}>
                <option value="">{labels.emptyDashLabel || '—'}</option>
                <option value="1">Con préstamos</option>
                <option value="0">Sin préstamos</option>
              </select>
            </label>
            <label className="wpss-field">
              <span>{labels.modsLabel || 'Modulaciones'}</span>
              <select value={safeFilters.con_modulaciones || ''} onChange={(event) => setFilter('con_modulaciones', event.target.value)}>
                <option value="">{labels.emptyDashLabel || '—'}</option>
                <option value="1">Con modulaciones</option>
                <option value="0">Sin modulaciones</option>
              </select>
            </label>
          </div>
        ) : null}

        {activeTab === 'catalogo' ? (
          <div className="wpss-filter-deck__grid wpss-filter-deck__grid--double">
            <label className="wpss-field">
              <span>{labels.collectionLabel || 'Colección'}</span>
              <select value={safeFilters.coleccion || ''} onChange={(event) => setFilter('coleccion', event.target.value)}>
                <option value="">{labels.collectionAllLabel || 'Todas'}</option>
                {collections.map((collection) => (
                  <option key={collection.id} value={collection.id}>
                    {collection.nombre}
                  </option>
                ))}
              </select>
            </label>
            <label className="wpss-field">
              <span>{labels.tagLabel || 'Tag'}</span>
              <select value={safeFilters.tag || ''} onChange={(event) => setFilter('tag', event.target.value)}>
                <option value="">{labels.tagAllLabel || 'Todos'}</option>
                {tags.map((tag) => (
                  <option key={tag.id || tag.slug} value={tag.slug}>
                    {tag.name}
                  </option>
                ))}
              </select>
            </label>
          </div>
        ) : null}

        {activeTab === 'estado' ? (
          <div className="wpss-filter-deck__grid wpss-filter-deck__grid--double">
            <label className="wpss-field">
              <span>{labels.transcriptionLabel || 'Estado de la canción'}</span>
              <select
                value={safeFilters.estado_transcripcion || ''}
                onChange={(event) => setFilter('estado_transcripcion', event.target.value)}
              >
                <option value="">{labels.allStatusesLabel || 'Todos'}</option>
                {TRANSCRIPTION_STATUS_OPTIONS.map((option) => (
                  <option key={option.id} value={option.id}>
                    {option.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="wpss-field">
              <span>{labels.rehearsalLabel || 'Estado de ensayo'}</span>
              <select
                value={safeFilters.estado_ensayo || ''}
                onChange={(event) => setFilter('estado_ensayo', event.target.value)}
              >
                <option value="">{labels.allStatusesLabel || 'Todos'}</option>
                {REHEARSAL_STATUS_OPTIONS.map((option) => (
                  <option key={option.id} value={option.id}>
                    {option.label}
                  </option>
                ))}
              </select>
            </label>
          </div>
        ) : null}
      </div>
    </section>
  )
}
