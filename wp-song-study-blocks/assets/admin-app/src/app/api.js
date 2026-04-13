export function createApi(wpData) {
  const baseUrl = wpData?.restUrl || ''
  const publicBaseUrl = wpData?.publicRestUrl || baseUrl
  const wpRestNonce = wpData?.wpRestNonce || ''
  const wpssNonce = wpData?.wpssNonce || ''

  async function request(path, options = {}) {
    return requestWithBase(baseUrl, path, options)
  }

  async function requestPublic(path, options = {}) {
    return requestWithBase(publicBaseUrl, path, options)
  }

  async function requestWithBase(root, path, options = {}) {
    const { method = 'GET', body = null, asJson = true } = options

    const headers = {
      'X-WP-Nonce': wpRestNonce,
      'X-WPSS-Nonce': wpssNonce,
    }

    const config = {
      method,
      credentials: 'same-origin',
      headers,
    }

    if (null !== body) {
      if (asJson) {
        config.body = JSON.stringify(body)
        config.headers['Content-Type'] = 'application/json'
      } else {
        config.body = body
      }
    }

    const response = await fetch(`${root}${path}`, config)
    const payload = await parseResponse(response)

    if (!response.ok) {
      const error = new Error(`REST ${response.status}`)
      error.status = response.status
      error.payload = payload
      throw error
    }

    return {
      data: payload,
      headers: response.headers,
    }
  }

  async function parseResponse(response) {
    const text = await response.text()
    if (!text) {
      return null
    }

    try {
      return JSON.parse(text)
    } catch {
      return text
    }
  }

  function buildAdminPostUrl(params = {}) {
    const adminPostUrl = wpData?.adminPostUrl || ''
    const query = new URLSearchParams()

    Object.entries(params || {}).forEach(([key, value]) => {
      if (value === null || typeof value === 'undefined' || value === '') {
        return
      }
      query.set(key, String(value))
    })

    return `${adminPostUrl}?${query.toString()}`
  }

  return {
    listSongs(overrides = {}) {
      const params = new URLSearchParams()
      const page = overrides.page ? overrides.page : 1
      const perPage = overrides.per_page ? overrides.per_page : 20

      params.set('page', page)
      params.set('per_page', perPage)

      Object.keys(overrides).forEach((key) => {
        if (['page', 'per_page'].includes(key)) {
          return
        }

        const value = overrides[key]
        if (null === value || 'undefined' === typeof value) {
          return
        }

        if ('' === value) {
          params.delete(key)
          return
        }

        params.set(key, value)
      })

      return request(`canciones?${params.toString()}`)
    },
    listPublicSongs(overrides = {}) {
      const params = new URLSearchParams()
      const page = overrides.page ? overrides.page : 1
      const perPage = overrides.per_page ? overrides.per_page : 20

      params.set('page', page)
      params.set('per_page', perPage)

      Object.keys(overrides).forEach((key) => {
        if (['page', 'per_page'].includes(key)) {
          return
        }

        const value = overrides[key]
        if (null === value || 'undefined' === typeof value) {
          return
        }

        if ('' === value) {
          params.delete(key)
          return
        }

        params.set(key, value)
      })

      return requestPublic(`public/canciones?${params.toString()}`)
    },
    listPublicCollections() {
      return requestPublic('public/colecciones')
    },
    getSong(id) {
      return request(`cancion/${id}`)
    },
    deleteSong(id) {
      return request(`cancion/${id}`, { method: 'DELETE' })
    },
    reversionSong(id, payload = {}) {
      return request(`cancion/${id}/reversion`, { method: 'POST', body: payload })
    },
    setSongTranscriptionStatus(id, estado) {
      return request(`cancion/${id}/estado-transcripcion`, {
        method: 'POST',
        body: { estado_transcripcion: estado },
      })
    },
    setSongRehearsalStatus(id, estado) {
      return request(`cancion/${id}/estado-ensayo`, {
        method: 'POST',
        body: { estado_ensayo: estado },
      })
    },
    getPublicSong(id) {
      return requestPublic(`public/cancion/${id}`)
    },
    saveSong(payload) {
      return request('cancion', { method: 'POST', body: payload })
    },
    listColleagues() {
      return request('colegas-musicales')
    },
    listProjects() {
      return request('proyectos')
    },
    assignRepertoire(items) {
      return request('repertorio-asignaciones', { method: 'POST', body: { items } })
    },
    listCampos() {
      return request('campos-armonicos')
    },
    saveCampos(campos) {
      return request('campos-armonicos', { method: 'POST', body: { campos } })
    },
    listChords() {
      return request('acordes')
    },
    saveChords(acordes) {
      return request('acordes', { method: 'POST', body: { acordes } })
    },
    getChordsConfig() {
      return request('acordes-config')
    },
    saveChordsConfig(config) {
      return request('acordes-config', { method: 'POST', body: config })
    },
    listCollections() {
      return request('colecciones')
    },
    getCollection(id) {
      return request(`coleccion/${id}`)
    },
    saveCollection(payload) {
      return request('coleccion', { method: 'POST', body: payload })
    },
    deleteCollection(id) {
      return request(`coleccion/${id}`, { method: 'DELETE' })
    },
    duplicateCollection(id) {
      return request(`coleccion/${id}/duplicar`, { method: 'POST' })
    },
    listSongTags() {
      return request('tags-cancion')
    },
    listGroups() {
      return request('agrupaciones-musicales')
    },
    getGroup(id) {
      return request(`agrupacion-musical/${id}`)
    },
    saveGroup(payload) {
      return request('agrupacion-musical', { method: 'POST', body: payload })
    },
    deleteGroup(id) {
      return request(`agrupacion-musical/${id}`, { method: 'DELETE' })
    },
    getGoogleDriveStatus() {
      return request('mi/google-drive')
    },
    saveGoogleDriveSettings(payload) {
      return request('mi/google-drive', { method: 'POST', body: payload })
    },
    disconnectGoogleDrive() {
      return request('mi/google-drive/disconnect', { method: 'POST', body: {} })
    },
    uploadSongAttachment(formData) {
      return request('google-drive/upload', { method: 'POST', body: formData, asJson: false })
    },
    updateSongAttachment(songId, attachmentId, payload) {
      return request(`media/attachment/${songId}/${attachmentId}`, { method: 'POST', body: payload })
    },
    unlinkSongAttachment(songId, attachmentId) {
      return request(`media/attachment/${songId}/${attachmentId}/unlink`, { method: 'POST', body: {} })
    },
    deleteSongAttachment(songId, attachmentId) {
      return request(`media/attachment/${songId}/${attachmentId}`, { method: 'DELETE' })
    },
    importSongPackage(formData) {
      return request('canciones/import', { method: 'POST', body: formData, asJson: false })
    },
    buildSongExportUrl(params = {}) {
      return buildAdminPostUrl({
        action: 'wpss_song_export',
        _wpnonce: wpData?.songExportNonce || '',
        ...params,
      })
    },
  }
}
