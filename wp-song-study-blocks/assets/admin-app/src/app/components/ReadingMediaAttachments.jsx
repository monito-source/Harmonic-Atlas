import { useMemo } from 'react'

function formatDuration(value) {
  const total = Math.max(0, Math.round(Number(value) || 0))
  const minutes = Math.floor(total / 60)
  const seconds = total % 60
  return `${minutes}:${String(seconds).padStart(2, '0')}`
}

function normalizeList(items) {
  return Array.isArray(items) ? items.filter((item) => item && typeof item === 'object') : []
}

export function getSongLevelAttachments(song) {
  return normalizeList(song?.adjuntos).filter((item) => item.anchor_type === 'song')
}

export function getSectionLevelAttachments(song, sectionId) {
  return normalizeList(song?.adjuntos).filter(
    (item) => item.anchor_type === 'section' && String(item.section_id || '') === String(sectionId || ''),
  )
}

export function getVerseLevelAttachments(song, verseIndex) {
  return normalizeList(song?.adjuntos).filter(
    (item) => item.anchor_type === 'verse' && Number(item.verse_index) === Number(verseIndex),
  )
}

export function getSegmentLevelAttachments(song, verseIndex) {
  return normalizeList(song?.adjuntos).filter(
    (item) => item.anchor_type === 'segment' && Number(item.verse_index) === Number(verseIndex),
  )
}

export default function ReadingMediaAttachments({
  attachments = [],
  title = '',
  emptyLabel = '',
  compact = false,
  groupedBySegment = false,
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
    return emptyLabel ? <p className="wpss-reading-media__empty">{emptyLabel}</p> : null
  }

  if (groupedBySegment) {
    return (
      <div className={`wpss-reading-media ${compact ? 'is-compact' : ''}`}>
        {title ? <h5 className="wpss-reading-media__title">{title}</h5> : null}
        {groupedSegments.map(([segmentIndex, items]) => (
          <div key={`segment-group-${segmentIndex}`} className="wpss-reading-media__group">
            <div className="wpss-reading-media__group-label">{`Fragmento ${segmentIndex + 1}`}</div>
            <div className="wpss-reading-media__grid">
              {items.map((item) => (
                <ReadingAttachmentCard key={item.id} attachment={item} compact={compact} />
              ))}
            </div>
          </div>
        ))}
      </div>
    )
  }

  return (
    <div className={`wpss-reading-media ${compact ? 'is-compact' : ''}`}>
      {title ? <h5 className="wpss-reading-media__title">{title}</h5> : null}
      <div className="wpss-reading-media__grid">
        {safeAttachments.map((item) => (
          <ReadingAttachmentCard key={item.id} attachment={item} compact={compact} />
        ))}
      </div>
    </div>
  )
}

function ReadingAttachmentCard({ attachment, compact = false }) {
  const label = attachment?.title || attachment?.file_name || 'Adjunto'
  const isPhoto = attachment?.type === 'photo'
  const meta = [
    isPhoto ? 'Foto' : 'Audio',
    attachment?.owner_user_name ? `Por ${attachment.owner_user_name}` : '',
    attachment?.duration_seconds ? formatDuration(attachment.duration_seconds) : '',
  ]
    .filter(Boolean)
    .join(' · ')

  return (
    <article className={`wpss-reading-media__card ${compact ? 'is-compact' : ''}`}>
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
    </article>
  )
}
