import { useEffect, useMemo, useRef, useState } from 'react'

function Spinner({ label = 'Procesando' }) {
  return <span className="wpss-inline-spinner" aria-label={label} />
}

function formatDuration(value) {
  const total = Math.max(0, Math.round(Number(value) || 0))
  const minutes = Math.floor(total / 60)
  const seconds = total % 60
  return `${minutes}:${String(seconds).padStart(2, '0')}`
}

function PlayIcon() {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true">
      <path d="M5 3.2 12.4 8 5 12.8V3.2Z" fill="currentColor" />
    </svg>
  )
}

function PauseIcon() {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true">
      <path d="M4 3h3v10H4zm5 0h3v10H9z" fill="currentColor" />
    </svg>
  )
}

function BackwardIcon() {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true">
      <path d="M7.2 3.4 1.8 8l5.4 4.6V3.4Zm6.2 0L8 8l5.4 4.6V3.4Z" fill="currentColor" />
    </svg>
  )
}

function ForwardIcon() {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true">
      <path d="M8.8 3.4 14.2 8l-5.4 4.6V3.4Zm-6.2 0L8 8l-5.4 4.6V3.4Z" fill="currentColor" />
    </svg>
  )
}

function MoreIcon() {
  return (
    <svg viewBox="0 0 16 16" aria-hidden="true">
      <circle cx="3" cy="8" r="1.3" fill="currentColor" />
      <circle cx="8" cy="8" r="1.3" fill="currentColor" />
      <circle cx="13" cy="8" r="1.3" fill="currentColor" />
    </svg>
  )
}

function formatCreatedAt(value) {
  if (!value) return ''
  const normalized = String(value).replace(' ', 'T')
  const date = new Date(normalized)
  if (Number.isNaN(date.getTime())) return ''
  return date.toLocaleString('es-MX', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function normalizeList(items) {
  return Array.isArray(items) ? items.filter((item) => item && typeof item === 'object') : []
}

export function isRehearsalAttachment(item) {
  return item?.attachment_role === 'rehearsal'
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
  minimal = false,
  groupedBySegment = false,
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
    return emptyLabel ? <p className="wpss-reading-media__empty">{emptyLabel}</p> : null
  }

  if (groupedBySegment) {
    return (
      <div className={`wpss-reading-media ${compact ? 'is-compact' : ''} ${minimal ? 'is-minimal' : ''}`.trim()}>
        {title ? <h5 className="wpss-reading-media__title">{title}</h5> : null}
        {groupedSegments.map(([segmentIndex, items]) => (
          <div key={`segment-group-${segmentIndex}`} className="wpss-reading-media__group">
            <div className="wpss-reading-media__group-label">{`Fragmento ${segmentIndex + 1}`}</div>
            <div className="wpss-reading-media__grid">
              {items.map((item) => (
                <ReadingAttachmentCard
                  key={item.id}
                  attachment={item}
                  compact={compact}
                  minimal={minimal}
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
    <div className={`wpss-reading-media ${compact ? 'is-compact' : ''} ${minimal ? 'is-minimal' : ''}`.trim()}>
      {title ? <h5 className="wpss-reading-media__title">{title}</h5> : null}
      <div className="wpss-reading-media__grid">
        {safeAttachments.map((item) => (
          <ReadingAttachmentCard
            key={item.id}
            attachment={item}
            compact={compact}
            minimal={minimal}
            onDelete={onDelete}
            pendingAction={pendingActionById?.[item.id] || ''}
          />
        ))}
      </div>
    </div>
  )
}

function ReadingAttachmentCard({ attachment, compact = false, minimal = false, onDelete = null, pendingAction = '' }) {
  const label = attachment?.title || attachment?.file_name || 'Adjunto'
  const isPhoto = attachment?.type === 'photo'
  const isBusy = !!pendingAction
  const projectLabel = Array.isArray(attachment?.projects) && attachment.projects.length
    ? attachment.projects.map((project) => project?.titulo).filter(Boolean).join(' · ')
    : ''
  const sourceLabel = attachment?.source_kind === 'recording'
    ? 'Grabado'
    : attachment?.source_kind === 'import'
      ? 'Adjuntado'
      : ''
  const createdLabel = formatCreatedAt(attachment?.created_at)
  const meta = [
    isPhoto ? 'Foto' : 'Audio',
    sourceLabel,
    attachment?.owner_user_name ? `Por ${attachment.owner_user_name}` : '',
    projectLabel ? `Proyecto: ${projectLabel}` : '',
    attachment?.duration_seconds ? formatDuration(attachment.duration_seconds) : '',
    createdLabel,
  ]
    .filter(Boolean)
    .join(' · ')

  return (
    <article className={`wpss-reading-media__card ${compact ? 'is-compact' : ''} ${minimal ? 'is-minimal' : ''}`.trim()}>
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
        <AudioAttachmentPlayer attachment={attachment} minimal={minimal} />
      )}
      {isBusy ? (
        <p className="wpss-preview-media__status">
          <Spinner label={`Procesando ${pendingAction}`} />
          <span>{pendingAction}</span>
        </p>
      ) : null}
      {attachment?.can_delete_file && typeof onDelete === 'function' ? (
        <div className="wpss-reading-media__actions">
          <button type="button" className="button button-small button-secondary" onClick={() => onDelete(attachment)} disabled={isBusy}>
            {isBusy ? 'Eliminando...' : 'Eliminar'}
          </button>
        </div>
      ) : null}
    </article>
  )
}

function AudioAttachmentPlayer({ attachment, minimal = false }) {
  const audioRef = useRef(null)
  const menuRef = useRef(null)
  const [isPlaying, setIsPlaying] = useState(false)
  const [duration, setDuration] = useState(Number(attachment?.duration_seconds) || 0)
  const [currentTime, setCurrentTime] = useState(0)
  const [seekValue, setSeekValue] = useState(0)
  const [isSeeking, setIsSeeking] = useState(false)
  const [playbackRate, setPlaybackRate] = useState(1)
  const speedOptions = [0.75, 1, 1.25, 1.5, 2]

  useEffect(() => {
    setDuration(Number(attachment?.duration_seconds) || 0)
    setCurrentTime(0)
    setSeekValue(0)
    setIsPlaying(false)
    setIsSeeking(false)
    setPlaybackRate(1)
  }, [attachment?.id, attachment?.duration_seconds])

  useEffect(() => {
    const audio = audioRef.current
    if (!audio) {
      return
    }
    audio.playbackRate = playbackRate
  }, [playbackRate])

  const resolvedDuration = duration > 0 ? duration : 0
  const progressMax = resolvedDuration > 0 ? resolvedDuration : 0
  const visualCurrentTime = isSeeking ? seekValue : currentTime

  useEffect(() => {
    if (!isSeeking) {
      return undefined
    }
    const finishSeeking = () => {
      setIsSeeking(false)
      syncCurrentTime()
    }
    window.addEventListener('pointerup', finishSeeking)
    window.addEventListener('pointercancel', finishSeeking)
    return () => {
      window.removeEventListener('pointerup', finishSeeking)
      window.removeEventListener('pointercancel', finishSeeking)
    }
  }, [isSeeking])

  const syncCurrentTime = () => {
    const audio = audioRef.current
    if (!audio) {
      return
    }
    const nextTime = Number.isFinite(audio.currentTime) ? audio.currentTime : 0
    setCurrentTime(nextTime)
    if (!isSeeking) {
      setSeekValue(nextTime)
    }
  }

  const handleLoadedMetadata = () => {
    const audio = audioRef.current
    if (!audio) {
      return
    }
    const nextDuration = Number.isFinite(audio.duration) ? audio.duration : 0
    setDuration(nextDuration)
    syncCurrentTime()
  }

  const handleTogglePlayback = async () => {
    const audio = audioRef.current
    if (!audio) {
      return
    }
    if (!audio.paused) {
      audio.pause()
      return
    }
    try {
      await audio.play()
    } catch {
      setIsPlaying(false)
    }
  }

  const handleSkip = (delta) => {
    const audio = audioRef.current
    if (!audio) {
      return
    }
    const nextTime = Math.min(
      Math.max((Number.isFinite(audio.currentTime) ? audio.currentTime : 0) + delta, 0),
      resolvedDuration || Number.MAX_SAFE_INTEGER,
    )
    audio.currentTime = nextTime
    setCurrentTime(nextTime)
    setSeekValue(nextTime)
  }

  const applySeekValue = (rawValue) => {
    const nextValue = Math.max(0, Number(rawValue) || 0)
    const audio = audioRef.current
    setSeekValue(nextValue)
    setCurrentTime(nextValue)
    if (audio) {
      audio.currentTime = nextValue
    }
  }

  const closeMenu = () => {
    const menu = menuRef.current
    if (menu && menu.hasAttribute('open')) {
      menu.removeAttribute('open')
    }
  }

  const handlePlaybackRateChange = (nextRate) => {
    setPlaybackRate(nextRate)
    closeMenu()
  }

  return (
    <div className={`wpss-audio-player ${minimal ? 'is-minimal' : ''}`.trim()}>
      <audio
        ref={audioRef}
        className="wpss-audio-player__element"
        preload="metadata"
        src={attachment?.stream_url || ''}
        onLoadedMetadata={handleLoadedMetadata}
        onTimeUpdate={syncCurrentTime}
        onDurationChange={handleLoadedMetadata}
        onPlay={() => setIsPlaying(true)}
        onPause={() => setIsPlaying(false)}
        onEnded={() => {
          setIsPlaying(false)
          setCurrentTime(0)
          setSeekValue(0)
        }}
      />
      <div className="wpss-audio-player__controls">
        <button
          type="button"
          className="wpss-audio-player__button"
          onClick={() => handleSkip(-5)}
          aria-label="Retroceder 5 segundos"
        >
          <BackwardIcon />
          <span>5</span>
        </button>
        <button
          type="button"
          className="wpss-audio-player__button is-primary"
          onClick={handleTogglePlayback}
          aria-label={isPlaying ? 'Pausar audio' : 'Reproducir audio'}
        >
          {isPlaying ? <PauseIcon /> : <PlayIcon />}
        </button>
        <button
          type="button"
          className="wpss-audio-player__button"
          onClick={() => handleSkip(5)}
          aria-label="Adelantar 5 segundos"
        >
          <ForwardIcon />
          <span>5</span>
        </button>
        {!minimal ? (
          <>
            <div className="wpss-audio-player__timeline">
              <input
                type="range"
                min="0"
                max={String(progressMax || 0)}
                step="0.1"
                value={String(Math.min(visualCurrentTime, progressMax || visualCurrentTime))}
                onPointerDown={() => setIsSeeking(true)}
                onChange={(event) => applySeekValue(event.target.value)}
                onInput={(event) => applySeekValue(event.currentTarget.value)}
                onPointerUp={() => {
                  setIsSeeking(false)
                  syncCurrentTime()
                }}
                onKeyUp={(event) => {
                  applySeekValue(event.currentTarget.value)
                  syncCurrentTime()
                }}
                aria-label="Mover reproducción"
                disabled={!progressMax}
              />
              <div className="wpss-audio-player__time">
                <span>{formatDuration(visualCurrentTime)}</span>
                <span>{formatDuration(resolvedDuration)}</span>
              </div>
            </div>
            <details ref={menuRef} className="wpss-audio-player__menu">
              <summary aria-label="Más opciones de audio">
                <MoreIcon />
              </summary>
              <div className="wpss-audio-player__menu-panel">
                <div className="wpss-audio-player__menu-group">
                  <strong>Velocidad</strong>
                  <div className="wpss-audio-player__speed-list">
                    {speedOptions.map((speed) => (
                      <button
                        key={`speed-${speed}`}
                        type="button"
                        className={`wpss-audio-player__speed-button ${playbackRate === speed ? 'is-active' : ''}`}
                        onClick={() => handlePlaybackRateChange(speed)}
                      >
                        {`${speed}x`}
                      </button>
                    ))}
                  </div>
                </div>
                <a
                  className="wpss-audio-player__menu-link"
                  href={attachment?.stream_url || '#'}
                  download={attachment?.file_name || attachment?.title || 'adjunto'}
                  onClick={closeMenu}
                >
                  Descargar
                </a>
              </div>
            </details>
          </>
        ) : (
          <>
            <span className="wpss-audio-player__time wpss-audio-player__time--minimal">
              {`${formatDuration(visualCurrentTime)} / ${formatDuration(resolvedDuration)}`}
            </span>
            <details ref={menuRef} className="wpss-audio-player__menu">
              <summary aria-label="Más opciones de audio">
                <MoreIcon />
              </summary>
              <div className="wpss-audio-player__menu-panel">
                <div className="wpss-audio-player__menu-group">
                  <strong>Velocidad</strong>
                  <div className="wpss-audio-player__speed-list">
                    {speedOptions.map((speed) => (
                      <button
                        key={`speed-minimal-${speed}`}
                        type="button"
                        className={`wpss-audio-player__speed-button ${playbackRate === speed ? 'is-active' : ''}`}
                        onClick={() => handlePlaybackRateChange(speed)}
                      >
                        {`${speed}x`}
                      </button>
                    ))}
                  </div>
                </div>
                <a
                  className="wpss-audio-player__menu-link"
                  href={attachment?.stream_url || '#'}
                  download={attachment?.file_name || attachment?.title || 'adjunto'}
                  onClick={closeMenu}
                >
                  Descargar
                </a>
              </div>
            </details>
          </>
        )}
      </div>
    </div>
  )
}
