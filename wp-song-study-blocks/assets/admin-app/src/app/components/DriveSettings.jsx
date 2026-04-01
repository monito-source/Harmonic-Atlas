import { useEffect, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'

function Spinner({ label = 'Cargando' }) {
  return <span className="wpss-inline-spinner" aria-label={label} />
}

export default function DriveSettings() {
  const { api, dispatch, wpData } = useAppState()
  const [status, setStatus] = useState(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [disconnecting, setDisconnecting] = useState(false)
  const [connecting, setConnecting] = useState(false)
  const [folderUrl, setFolderUrl] = useState('')
  const [folderName, setFolderName] = useState('')
  const [error, setError] = useState(null)
  const searchParams = typeof window !== 'undefined' ? new URLSearchParams(window.location.search) : null
  const statusParam = searchParams?.get('wpss_drive_status') || ''
  const errorParam = searchParams?.get('wpss_drive_error') || ''
  const errorMessages = {
    nonce: 'El enlace de conexión expiró. Vuelve a pulsar "Conectar Google Drive".',
    user: 'La conexión solo puede iniciarse para el usuario que tiene la sesión activa.',
    config: 'Faltan credenciales OAuth válidas para este usuario.',
    state: 'La autorización de Google Drive no pudo validarse correctamente.',
    code: 'Google no devolvió un código de autorización.',
    token: 'No fue posible completar el intercambio de tokens con Google Drive.',
  }

  useEffect(() => {
    api
      .getGoogleDriveStatus()
      .then((response) => {
        const next = response?.data && typeof response.data === 'object' ? response.data : {}
        setStatus(next)
        setFolderUrl(next?.folder_url || '')
        setFolderName(next?.folder_name || '')
      })
      .catch((requestError) => {
        setError(requestError?.payload?.message || 'No fue posible obtener el estado de Google Drive.')
      })
      .finally(() => {
        setLoading(false)
      })
  }, [api])

  useEffect(() => {
    if (!errorParam || error) {
      return
    }

    setError(errorMessages[errorParam] || 'No fue posible completar la conexión con Google Drive.')
  }, [error, errorParam])

  const handleSave = () => {
    setSaving(true)
    setError(null)
    api
      .saveGoogleDriveSettings({ folder_url: folderUrl, folder_name: folderName })
      .then((response) => {
        const next = response?.data && typeof response.data === 'object' ? response.data : {}
        setStatus(next)
        setFolderUrl(next?.folder_url || '')
        setFolderName(next?.folder_name || '')
        dispatch({
          type: 'SET_STATE',
          payload: { feedback: { message: 'Configuración de Drive guardada.', type: 'success' }, error: null },
        })
      })
      .catch((requestError) => {
        const message = requestError?.payload?.message || 'No fue posible guardar la configuración de Drive.'
        setError(message)
        dispatch({ type: 'SET_STATE', payload: { error: message } })
      })
      .finally(() => {
        setSaving(false)
      })
  }

  const handleDisconnect = () => {
    const confirmed = window.confirm('¿Desconectar tu cuenta de Google Drive?')
    if (!confirmed) return

    setDisconnecting(true)
    setError(null)
    api
      .disconnectGoogleDrive()
      .then((response) => {
        const next = response?.data?.status && typeof response.data.status === 'object' ? response.data.status : {}
        setStatus(next)
        setFolderUrl('')
        setFolderName('')
        dispatch({
          type: 'SET_STATE',
          payload: { feedback: { message: 'Google Drive desconectado.', type: 'success' }, error: null },
        })
      })
      .catch((requestError) => {
        const message = requestError?.payload?.message || 'No fue posible desconectar Google Drive.'
        setError(message)
        dispatch({ type: 'SET_STATE', payload: { error: message } })
      })
      .finally(() => {
        setDisconnecting(false)
      })
  }

  if (loading) {
    return (
      <section className="wpss-collections">
        <p className="wpss-collections__hint wpss-drive-status-line">
          <Spinner label="Cargando estado de Drive" />
          <span>Cargando estado de Drive…</span>
        </p>
      </section>
    )
  }

  return (
    <section className="wpss-collections">
      <div className="wpss-collections__editor">
        <h2>Mi Google Drive</h2>
        {error ? <p className="wpss-error">{error}</p> : null}
        <p className="wpss-collections__hint">
          OAuth para este usuario: {status?.configured ? 'Configurado' : 'Faltan Client ID / Secret'}
        </p>
        <p className="wpss-collections__hint">
          Fuente activa: {status?.credentials_source === 'user' ? 'Credenciales propias del usuario' : 'Credenciales globales del plugin'}
          {' · '}
          <a href={wpData?.adminUrls?.profilePage || '#'}>Abrir perfil de usuario</a>
        </p>
        <p className="wpss-collections__hint">
          Redirect URI: <code>{status?.redirect_uri || 'No disponible'}</code>
        </p>
        <p className="wpss-collections__hint">
          Authorized JavaScript origin: <code>{status?.authorized_origin || 'No disponible'}</code>
        </p>
        {status?.last_error?.message ? (
          <p className="wpss-error">
            Último error OAuth: {status.last_error.message}
          </p>
        ) : null}
        <p className="wpss-collections__hint">
          Tokens guardados: access {status?.has_access_token ? 'sí' : 'no'} · refresh {status?.has_refresh_token ? 'sí' : 'no'}
        </p>
        {statusParam === 'connected' ? (
          <p className="wpss-feedback">Google Drive conectado correctamente.</p>
        ) : null}

        {status?.connected ? (
          <>
            <p className="wpss-collections__hint">
              Cuenta conectada: <strong>{status?.account_email || 'Sin email disponible'}</strong>
            </p>
            <div className="wpss-field-group">
              <label className="wpss-field">
                <span>URL o ID de carpeta destino</span>
                <input
                  type="text"
                  value={folderUrl}
                  onChange={(event) => setFolderUrl(event.target.value)}
                  placeholder="https://drive.google.com/drive/folders/..."
                  disabled={saving || disconnecting}
                />
              </label>
              <label className="wpss-field">
                <span>Nombre visible de carpeta</span>
                <input
                  type="text"
                  value={folderName}
                  onChange={(event) => setFolderName(event.target.value)}
                  placeholder="HarmonyAtlas"
                  disabled={saving || disconnecting}
                />
              </label>
            </div>
            <div className="wpss-collections__actions">
              <button type="button" className="button button-primary" onClick={handleSave} disabled={saving || disconnecting}>
                {saving ? (
                  <span className="wpss-inline-action-label"><Spinner label="Guardando configuración de Drive" /><span>Guardando…</span></span>
                ) : 'Guardar configuración'}
              </button>
              <button type="button" className="button button-secondary" onClick={handleDisconnect} disabled={saving || disconnecting}>
                {disconnecting ? (
                  <span className="wpss-inline-action-label"><Spinner label="Desconectando Google Drive" /><span>Desconectando…</span></span>
                ) : 'Desconectar'}
              </button>
            </div>
          </>
        ) : (
          <>
            <p className="wpss-empty">
              Conecta tu Google Drive para que los audios y fotos de tus canciones se almacenen fuera del servidor.
            </p>
            <a
              className={`button button-primary ${!status?.configured ? 'disabled' : ''}`}
              href={status?.configured ? status?.connect_url || '#' : '#'}
              aria-disabled={!status?.configured}
              onClick={() => {
                if (status?.configured) {
                  setConnecting(true)
                }
              }}
            >
              {connecting ? (
                <span className="wpss-inline-action-label"><Spinner label="Abriendo autorización de Google Drive" /><span>Abriendo Google…</span></span>
              ) : 'Conectar Google Drive'}
            </a>
            {connecting ? (
              <p className="wpss-collections__hint wpss-drive-status-line">
                <Spinner label="Redirigiendo a Google" />
                <span>Abriendo la autorización de Google Drive…</span>
              </p>
            ) : null}
          </>
        )}
      </div>
    </section>
  )
}
