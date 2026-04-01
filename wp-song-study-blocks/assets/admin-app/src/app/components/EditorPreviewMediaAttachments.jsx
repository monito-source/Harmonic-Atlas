import { useMemo } from 'react'

function Spinner({ label = 'Procesando' }) {
  return <span className="wpss-inline-spinner" aria-label={label} />
}

function formatDuration(value) {
  const total = Math.max(0, Math.round(Number(value) || 0))
  const minutes = Math.floor(total / 60)
  const seconds = total % 60
  return `${minutes}:${String(seconds).padStart(2, '0')}`
}

function normalizeList(items) {
  return Array.isArray(items) ? items.filter((item) => item && typeof item === 'object') : []
}

export default function EditorPreviewMediaAttachments({
  attachments = [],
  title = '',
  compact = false,
  groupedBySegment = false,
  onRename = null,
  onUnlink = null,
  onDelete = null,
  pendingActionById = {},
}) {
  const safeAttachments = normalizeList(attachments)

  const groupedSegments = useMemo(() => {
    if (!groupedBySegment) return []
    const map = new Map()
    safeAttachments.forEach((item) => {
      const key = Number(item?.segment_index) || 0
      if (!map.has(key)) {
        map.set(key, [])
      }
      map.get(key).push(item)
    })
    return Array.from(map.entries()).sort((a, b) => a[0] - b[0])
  }, [groupedBySegment, safeAttachments])

  if (!safeAttachments.length) {
    return null
  }

  if (groupedBySegment) {
    return (
      <div className={`wpss-reading-media wpss-preview-media ${compact ? 'is-compact' : ''}`}>
        {title ? <h5 className="wpss-reading-media__title">{title}</h5> : null}
        {groupedSegments.map(([segmentIndex, items]) => (
          <div key={`preview-segment-group-${segmentIndex}`} className="wpss-reading-media__group">
            <div className="wpss-reading-media__group-label">{`Fragmento ${segmentIndex + 1}`}</div>
            <div className="wpss-reading-media__grid">
              {items.map((item) => (
                <PreviewAttachmentCard
                  key={item.id}
                  attachment={item}
                  compact={compact}
                  onRename={onRename}
                  onUnlink={onUnlink}
                  onDelete={onDelete}
                  pendingAction={pendingActionById?.[item.id] || ''}
                />
              ))}
            </div>
          </div>
        ))}
      </div>
    )
  }

  return (
    <div className={`wpss-reading-media wpss-preview-media ${compact ? 'is-compact' : ''}`}>
      {title ? <h5 className="wpss-reading-media__title">{title}</h5> : null}
      <div className="wpss-reading-media__grid">
        {safeAttachments.map((item) => (
          <PreviewAttachmentCard
            key={item.id}
            attachment={item}
            compact={compact}
            onRename={onRename}
            onUnlink={onUnlink}
            onDelete={onDelete}
            pendingAction={pendingActionById?.[item.id] || ''}
          />
        ))}
      </div>
    </div>
  )
}

function PreviewAttachmentCard({ attachment, compact = false, onRename, onUnlink, onDelete, pendingAction = '' }) {
  const label = attachment?.title || attachment?.file_name || 'Adjunto'
  const isPhoto = attachment?.type === 'photo'
  const isBusy = !!pendingAction
  const meta = [
    isPhoto ? 'Foto' : 'Audio',
    attachment?.owner_user_name ? `Por ${attachment.owner_user_name}` : '',
    attachment?.duration_seconds ? formatDuration(attachment.duration_seconds) : '',
  ]
    .filter(Boolean)
    .join(' · ')

  return (
    <article
      className={`wpss-reading-media__card wpss-preview-media__card ${compact ? 'is-compact' : ''}`}
      onClick={(event) => event.stopPropagation()}
      onPointerDown={(event) => event.stopPropagation()}
    >
      <div className="wpss-reading-media__card-head">
        <strong>{label}</strong>
        {meta ? <span>{meta}</span> : null}
      </div>
      {isPhoto ? (
        <a href={attachment?.stream_url || '#'} target="_blank" rel="noreferrer">
          <img
            className="wpss-reading-media__image"
            src={attachment?.stream_url || ''}
            alt={label}
            loading="lazy"
          />
        </a>
      ) : (
        <audio className="wpss-reading-media__audio" controls preload="none" src={attachment?.stream_url || ''} />
      )}
      {isBusy ? (
        <p className="wpss-preview-media__status">
          <Spinner label={`Procesando ${pendingAction}`} />
          <span>{pendingAction}</span>
        </p>
      ) : null}
      {(attachment?.can_manage || attachment?.can_delete_file) ? (
        <div className="wpss-preview-media__actions">
          {attachment?.can_manage && typeof onRename === 'function' ? (
            <button type="button" className="button button-small" onClick={() => onRename(attachment)} disabled={isBusy}>
              Renombrar
            </button>
          ) : null}
          {attachment?.can_manage ? (
            <button type="button" className="button button-small" onClick={() => onUnlink?.(attachment)} disabled={isBusy}>
              Quitar
            </button>
          ) : null}
          {attachment?.can_delete_file ? (
            <button type="button" className="button button-small button-secondary" onClick={() => onDelete?.(attachment)} disabled={isBusy}>
              Eliminar de Drive
            </button>
          ) : null}
        </div>
      ) : null}
    </article>
  )
}
