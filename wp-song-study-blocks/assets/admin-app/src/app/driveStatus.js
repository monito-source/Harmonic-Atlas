export function hasRequiredDriveScope(status) {
  if (!status || typeof status !== 'object') {
    return false
  }
  return status.has_required_scope !== false
}

export function getDriveHealth(status) {
  if (!status || typeof status !== 'object' || !status.health || typeof status.health !== 'object') {
    return null
  }
  return status.health
}

export function isDriveOperational(status) {
  if (!status || typeof status !== 'object') {
    return false
  }
  if (!status.configured || !status.connected) {
    return false
  }
  if (!hasRequiredDriveScope(status)) {
    return false
  }
  const health = getDriveHealth(status)
  if (health?.operations_ready && typeof health.operations_ready === 'object') {
    return Object.values(health.operations_ready).some(Boolean)
  }
  return true
}

export function getDriveWarningText(status) {
  if (!status || typeof status !== 'object') {
    return 'Primero debes configurar y vincular tu Google Drive personal.'
  }
  if (!status.configured) {
    return 'Primero debes configurar y vincular tu Google Drive personal.'
  }
  if (!status.connected) {
    return 'Tu cuenta todavía no está conectada a Google Drive.'
  }
  if (status.has_required_scope === false) {
    return 'Tu conexión actual de Google Drive no tiene los permisos operativos completos. Debes reconectarla.'
  }
  const health = getDriveHealth(status)
  if (health?.ok === false && health?.recommended_action) {
    return health.recommended_action
  }
  return 'Google Drive no está listo para operar adjuntos en este momento.'
}
