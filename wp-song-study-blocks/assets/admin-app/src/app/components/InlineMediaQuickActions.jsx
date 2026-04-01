import { useEffect, useRef, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'

function Spinner({ label = 'Cargando' }) {
  return <span className="wpss-inline-spinner" aria-label={label} />
}

function getActionLabel(mode, uploadingMode) {
  const labels = {
    importAudio: 'Adjuntar audio',
    recordAudio: 'Grabar audio',
    importPhoto: 'Adjuntar foto',
    capturePhoto: 'Tomar foto',
  }

  if (uploadingMode === mode) {
    return 'Subiendo…'
  }

  return labels[mode] || 'Adjuntar'
}

function buildCapturedFile(blob, mode) {
  const isPhoto = mode === 'capturePhoto'
  const mimeType = blob?.type || (isPhoto ? 'image/jpeg' : 'audio/webm')
  const extension = isPhoto ? 'jpg' : 'webm'
  const stamp = new Date().toISOString().replace(/[:.]/g, '-')
  return new File([blob], `${isPhoto ? 'foto' : 'audio'}-${stamp}.${extension}`, { type: mimeType })
}

export default function InlineMediaQuickActions({ target, onUpload }) {
  const { api, wpData, dispatch } = useAppState()
  const importAudioRef = useRef(null)
  const importPhotoRef = useRef(null)
  const videoRef = useRef(null)
  const mediaRecorderRef = useRef(null)
  const streamRef = useRef(null)
  const recordedChunksRef = useRef([])

  const [uploadingMode, setUploadingMode] = useState(null)
  const [captureMode, setCaptureMode] = useState(null)
  const [captureError, setCaptureError] = useState(null)
  const [isRecording, setIsRecording] = useState(false)
  const [audioReady, setAudioReady] = useState(false)
  const [cameraReady, setCameraReady] = useState(false)
  const [driveStatus, setDriveStatus] = useState(() => (
    wpData?.googleDriveStatus && typeof wpData.googleDriveStatus === 'object'
      ? wpData.googleDriveStatus
      : { configured: false, connected: false }
  ))
  const [isCheckingDrive, setIsCheckingDrive] = useState(false)
  const [showDriveWarning, setShowDriveWarning] = useState(false)

  const driveReady = !!driveStatus?.configured && !!driveStatus?.connected
  const profileUrl = wpData?.adminUrls?.profilePage || '#'
  const drivePageUrl = wpData?.adminUrls?.drivePage || '#'
  const connectUrl = driveStatus?.connect_url || drivePageUrl
  const driveWarningText = driveStatus?.configured
    ? 'Tu cuenta todavía no está conectada a Google Drive.'
    : 'Primero debes configurar y vincular tu Google Drive personal.'

  const stopStream = () => {
    if (streamRef.current) {
      streamRef.current.getTracks().forEach((track) => track.stop())
      streamRef.current = null
    }
    if (videoRef.current) {
      videoRef.current.srcObject = null
    }
    mediaRecorderRef.current = null
    recordedChunksRef.current = []
    setIsRecording(false)
    setAudioReady(false)
    setCameraReady(false)
  }

  const closeCapture = () => {
    stopStream()
    setCaptureMode(null)
    setCaptureError(null)
  }

  const refreshDriveStatus = async () => {
    try {
      const response = await api.getGoogleDriveStatus()
      const nextStatus = response?.data && typeof response.data === 'object'
        ? response.data
        : { configured: false, connected: false }
      setDriveStatus(nextStatus)
      return nextStatus
    } catch {
      return driveStatus
    }
  }

  const ensureDriveReady = async () => {
    if (driveReady) {
      setShowDriveWarning(false)
      return true
    }

    setIsCheckingDrive(true)
    const nextStatus = await refreshDriveStatus()
    setIsCheckingDrive(false)

    if (nextStatus?.configured && nextStatus?.connected) {
      setShowDriveWarning(false)
      return true
    }

    setShowDriveWarning(true)
    dispatch({
      type: 'SET_STATE',
      payload: {
        error: nextStatus?.configured
          ? 'Debes terminar de conectar tu Google Drive antes de adjuntar audios o fotos.'
          : 'Debes configurar tu Google Drive personal en tu perfil antes de adjuntar audios o fotos.',
      },
    })
    return false
  }

  useEffect(() => {
    if (!captureMode) {
      return undefined
    }

    let cancelled = false
    setCaptureError(null)

    const startCapture = async () => {
      try {
        if (captureMode === 'recordAudio') {
          if (!navigator.mediaDevices?.getUserMedia || typeof MediaRecorder === 'undefined') {
            throw new Error('Tu navegador no permite grabar audio desde esta interfaz.')
          }
          const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
          if (cancelled) {
            stream.getTracks().forEach((track) => track.stop())
            return
          }
          streamRef.current = stream
          setAudioReady(true)
        }

        if (captureMode === 'capturePhoto') {
          if (!navigator.mediaDevices?.getUserMedia) {
            throw new Error('Tu navegador no permite usar la cámara desde esta interfaz.')
          }
          const stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' } },
            audio: false,
          })
          if (cancelled) {
            stream.getTracks().forEach((track) => track.stop())
            return
          }
          streamRef.current = stream
          if (videoRef.current) {
            videoRef.current.srcObject = stream
            await videoRef.current.play().catch(() => {})
            setCameraReady(true)
          }
        }
      } catch (error) {
        setCaptureError(error?.message || 'No fue posible iniciar la captura.')
      }
    }

    startCapture()
    return () => {
      cancelled = true
      stopStream()
    }
  }, [captureMode])

  const handleImportFile = async (mode, event) => {
    const file = event.target.files?.[0] || null
    event.target.value = ''
    const allowed = await ensureDriveReady()
    if (!allowed) {
      return
    }
    if (!file || !onUpload) {
      return
    }

    setUploadingMode(mode)
    try {
      await onUpload(target, mode, file)
    } finally {
      setUploadingMode(null)
    }
  }

  const startAudioRecording = () => {
    if (!streamRef.current) {
      setCaptureError('No fue posible acceder al micrófono.')
      return
    }

    try {
      setCaptureError(null)
      const mediaRecorder = new MediaRecorder(streamRef.current)
      recordedChunksRef.current = []
      mediaRecorderRef.current = mediaRecorder
      mediaRecorder.ondataavailable = (event) => {
        if (event.data?.size) {
          recordedChunksRef.current.push(event.data)
        }
      }
      mediaRecorder.start()
      setIsRecording(true)
    } catch {
      setCaptureError('No fue posible iniciar la grabación.')
    }
  }

  const stopAudioRecordingAndUpload = () =>
    new Promise((resolve) => {
      const recorder = mediaRecorderRef.current
      if (!recorder || recorder.state === 'inactive') {
        resolve()
        return
      }

      recorder.onstop = async () => {
        try {
          const blob = new Blob(recordedChunksRef.current, { type: recorder.mimeType || 'audio/webm' })
          const file = buildCapturedFile(blob, 'recordAudio')
          setUploadingMode('recordAudio')
          await onUpload?.(target, 'recordAudio', file)
          closeCapture()
        } catch (error) {
          setCaptureError(error?.message || 'No fue posible subir el audio grabado.')
        } finally {
          setUploadingMode(null)
          resolve()
        }
      }

      recorder.stop()
      setIsRecording(false)
    })

  const capturePhotoAndUpload = async () => {
    const video = videoRef.current
    if (!video || !video.videoWidth || !video.videoHeight) {
      setCaptureError('La cámara todavía no está lista para capturar.')
      return
    }

    const canvas = document.createElement('canvas')
    canvas.width = video.videoWidth
    canvas.height = video.videoHeight
    const context = canvas.getContext('2d')
    if (!context) {
      setCaptureError('No fue posible procesar la imagen capturada.')
      return
    }

    context.drawImage(video, 0, 0, canvas.width, canvas.height)

    setUploadingMode('capturePhoto')
    canvas.toBlob(async (blob) => {
      if (!blob) {
        setCaptureError('No fue posible generar la fotografía.')
        setUploadingMode(null)
        return
      }
      try {
        const file = buildCapturedFile(blob, 'capturePhoto')
        await onUpload?.(target, 'capturePhoto', file)
        closeCapture()
      } catch (error) {
        setCaptureError(error?.message || 'No fue posible subir la fotografía.')
      } finally {
        setUploadingMode(null)
      }
    }, 'image/jpeg', 0.92)
  }

  const disabled = !!uploadingMode
  const renderActionContent = (mode) => (
    <span className="wpss-inline-action-label">
      {uploadingMode === mode ? <Spinner label="Subiendo a Google Drive" /> : null}
      <span>{getActionLabel(mode, uploadingMode)}</span>
    </span>
  )

  const openImportAudio = async () => {
    if (await ensureDriveReady()) {
      importAudioRef.current?.click()
    }
  }
  const openImportPhoto = async () => {
    if (await ensureDriveReady()) {
      importPhotoRef.current?.click()
    }
  }
  const openRecordAudio = async () => {
    if (await ensureDriveReady()) {
      setCaptureMode('recordAudio')
    }
  }
  const openCapturePhoto = async () => {
    if (await ensureDriveReady()) {
      setCaptureMode('capturePhoto')
    }
  }

  return (
    <>
      <input
        ref={importAudioRef}
        type="file"
        accept="audio/*"
        hidden
        onChange={(event) => handleImportFile('importAudio', event)}
      />
      <input
        ref={importPhotoRef}
        type="file"
        accept="image/*"
        hidden
        onChange={(event) => handleImportFile('importPhoto', event)}
      />
      <button type="button" className="button button-small" onClick={openImportAudio} disabled={disabled || isCheckingDrive}>
        {renderActionContent('importAudio')}
      </button>
      <button type="button" className="button button-small" onClick={openRecordAudio} disabled={disabled || isCheckingDrive}>
        {renderActionContent('recordAudio')}
      </button>
      <button type="button" className="button button-small" onClick={openImportPhoto} disabled={disabled || isCheckingDrive}>
        {renderActionContent('importPhoto')}
      </button>
      <button type="button" className="button button-small" onClick={openCapturePhoto} disabled={disabled || isCheckingDrive}>
        {renderActionContent('capturePhoto')}
      </button>

      {isCheckingDrive ? (
        <p className="wpss-inline-drive-status">
          <Spinner label="Verificando Google Drive" />
          <span>Verificando tu conexión con Google Drive…</span>
        </p>
      ) : null}

      {showDriveWarning && !driveReady ? (
        <div className="wpss-inline-drive-warning" role="alert">
          <strong>Vincula tu Google Drive para usar audios y fotos.</strong>
          <p>{driveWarningText}</p>
          <div className="wpss-inline-drive-warning__actions">
            <a className="button button-small button-secondary" href={profileUrl}>
              Abrir perfil
            </a>
            <a className="button button-small button-secondary" href={drivePageUrl}>
              Abrir Mi Drive
            </a>
            <a className="button button-small" href={connectUrl}>
              Conectar Google Drive
            </a>
          </div>
        </div>
      ) : null}

      {captureMode ? (
        <div className="wpss-inline-capture" role="dialog" aria-modal="true">
          <button type="button" className="wpss-inline-capture__backdrop" aria-label="Cerrar" onClick={closeCapture} />
          <div className="wpss-inline-capture__panel">
            <div className="wpss-inline-capture__header">
              <strong>{captureMode === 'recordAudio' ? 'Grabar audio' : 'Tomar foto'}</strong>
              <button type="button" className="button button-small" onClick={closeCapture}>
                Cerrar
              </button>
            </div>
            {captureError ? <p className="wpss-error">{captureError}</p> : null}
            {captureMode === 'recordAudio' ? (
              <div className="wpss-inline-capture__body">
                <p className="wpss-collections__hint">
                  {uploadingMode === 'recordAudio' ? (
                    <>
                      <Spinner label="Subiendo audio a Google Drive" /> Subiendo audio a Google Drive…
                    </>
                  ) : isRecording
                    ? 'Grabando desde el micrófono…'
                    : audioReady
                      ? 'Micrófono listo. Inicia la grabación cuando quieras.'
                      : 'Solicitando acceso al micrófono…'}
                </p>
                <div className="wpss-collections__actions">
                  {!isRecording ? (
                    <button type="button" className="button button-primary" onClick={startAudioRecording} disabled={disabled || !audioReady}>
                      Iniciar grabación
                    </button>
                  ) : (
                    <button type="button" className="button button-primary" onClick={stopAudioRecordingAndUpload} disabled={disabled}>
                      Detener y subir
                    </button>
                  )}
                </div>
              </div>
            ) : (
              <div className="wpss-inline-capture__body">
                <video ref={videoRef} className="wpss-inline-capture__video" autoPlay playsInline muted />
                {uploadingMode === 'capturePhoto' ? (
                  <p className="wpss-collections__hint">
                    <Spinner label="Subiendo foto a Google Drive" /> Subiendo foto a Google Drive…
                  </p>
                ) : null}
                <div className="wpss-collections__actions">
                  <button type="button" className="button button-primary" onClick={capturePhotoAndUpload} disabled={disabled || !cameraReady}>
                    Capturar y subir
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      ) : null}
    </>
  )
}
