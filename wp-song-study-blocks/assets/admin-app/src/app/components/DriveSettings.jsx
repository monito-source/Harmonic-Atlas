import { useEffect, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'
import { getDriveHealth, getDriveWarningText, hasRequiredDriveScope, isDriveOperational } from '../driveStatus.js'

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
  const [refreshing, setRefreshing] = useState(false)
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

  const applyStatus = (nextStatus) => {
    const next = nextStatus && typeof nextStatus === 'object' ? nextStatus : {}
    setStatus(next)
    setFolderUrl(next?.folder_url || '')
    setFolderName(next?.folder_name || '')
  }

  useEffect(() => {
    api
      .getGoogleDriveStatus()
      .then((response) => {
        applyStatus(response?.data)
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
        applyStatus(response?.data)
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

  const handleRefreshStatus = () => {
    setRefreshing(true)
    setError(null)
    api
      .getGoogleDriveStatus()
      .then((response) => {
        applyStatus(response?.data)
      })
      .catch((requestError) => {
        setError(requestError?.payload?.message || 'No fue posible actualizar el diagnóstico de Google Drive.')
      })
      .finally(() => {
        setRefreshing(false)
      })
  }

  const health = getDriveHealth(status)
  const driveOperational = isDriveOperational(status)
  const reconnectRequired = !!status?.connected && !hasRequiredDriveScope(status)
  const warningText = getDriveWarningText(status)

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
        <p className="wpss-collections__hint">
          Estado operativo: <strong>{driveOperational ? 'Listo' : 'Requiere atención'}</strong>
        </p>
        {statusParam === 'connected' ? (
          <p className="wpss-feedback">Google Drive conectado correctamente.</p>
        ) : null}

        {health ? (
          <div className={`wpss-drive-health is-${health.severity || 'warning'}`}>
            <div className="wpss-drive-health__header">
              <div>
                <strong>Diagnóstico operativo</strong>
                <p className="wpss-collections__hint">{health.summary || 'Sin resumen disponible.'}</p>
              </div>
              <button
                type="button"
                className="button button-secondary"
                onClick={handleRefreshStatus}
                disabled={refreshing || saving || disconnecting}
              >
                {refreshing ? (
                  <span className="wpss-inline-action-label"><Spinner label="Actualizando diagnóstico de Drive" /><span>Actualizando…</span></span>
                ) : 'Actualizar diagnóstico'}
              </button>
            </div>

            {health.recommended_action ? (
              <p className="wpss-drive-health__message">{health.recommended_action}</p>
            ) : null}

            <div className="wpss-drive-health__grid">
              {(Array.isArray(health.checks) ? health.checks : []).map((check) => (
                <div key={check.id || check.label} className={`wpss-drive-check is-${check.status || 'warning'}`}>
                  <strong>{check.label || check.id}</strong>
                  <span>{check.message || 'Sin detalle.'}</span>
                </div>
              ))}
            </div>

            <div className="wpss-drive-health__columns">
              <div>
                <strong>Scopes requeridos</strong>
                <ul className="wpss-drive-list">
                  {(Array.isArray(status?.required_scopes) ? status.required_scopes : []).map((scope) => (
                    <li key={`required-${scope}`}><code>{scope}</code></li>
                  ))}
                </ul>
              </div>
              <div>
                <strong>Scopes concedidos</strong>
                <ul className="wpss-drive-list">
                  {(Array.isArray(status?.granted_scopes) ? status.granted_scopes : []).length ? (
                    status.granted_scopes.map((scope) => (
                      <li key={`granted-${scope}`}><code>{scope}</code></li>
                    ))
                  ) : (
                    <li>Sin scopes registrados todavía.</li>
                  )}
                </ul>
              </div>
            </div>

            <div>
              <strong>Capacidades operativas</strong>
              <div className="wpss-drive-health__grid">
                {(Array.isArray(health.capabilities) ? health.capabilities : []).map((capability) => (
                  <div key={capability.id || capability.label} className={`wpss-drive-check is-${capability.ready ? 'pass' : 'error'}`}>
                    <strong>{capability.label || capability.id}</strong>
                    <span>{capability.ready ? 'Lista.' : 'Bloqueada por scopes insuficientes.'}</span>
                  </div>
                ))}
              </div>
            </div>
          </div>
        ) : null}

        {status?.connected ? (
          <>
            <p className="wpss-collections__hint">
              Cuenta conectada: <strong>{status?.account_email || 'Sin email disponible'}</strong>
            </p>
            {!driveOperational ? (
              <p className="wpss-error">{warningText}</p>
            ) : null}
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
              <a
                className={`button button-secondary ${!status?.configured ? 'disabled' : ''}`}
                href={status?.configured ? status?.connect_url || '#' : '#'}
                aria-disabled={!status?.configured}
                onClick={() => {
                  if (status?.configured) {
                    setConnecting(true)
                  }
                }}
              >
                {reconnectRequired ? 'Reconectar con permisos completos' : 'Reconectar Google Drive'}
              </a>
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
