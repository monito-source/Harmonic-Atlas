import { createEmptySong } from './state.js'
import {
  normalizeSectionsFromApi,
  normalizeStructureFromApi,
  normalizeVersesFromApi,
} from './utils.js'

export function normalizeLoadedTag(tag) {
  if (tag === null || typeof tag === 'undefined') return null

  if (typeof tag === 'object') {
    const id = Number(tag?.id ?? tag?.term_id ?? tag?.termId)
    const name = String(tag?.name || tag?.nombre || tag?.label || tag?.slug || '').trim()
    if (!name && !Number.isInteger(id)) {
      return null
    }
    return {
      id: Number.isInteger(id) ? id : null,
      name: name || `Tag ${id}`,
      slug: String(tag?.slug || name || '').trim().toLowerCase(),
    }
  }

  const raw = String(tag).trim()
  if (!raw) return null
  const id = Number(raw)
  if (Number.isInteger(id) && id > 0) {
    return { id, name: `Tag ${id}`, slug: '' }
  }
  return { id: null, name: raw, slug: raw.toLowerCase() }
}

export function mapSongToEditingSong(song) {
  const bpmDefault = Number.isInteger(parseInt(song?.bpm, 10)) ? parseInt(song.bpm, 10) : 120
  const secciones = normalizeSectionsFromApi(song?.secciones, bpmDefault)
  const estructura = normalizeStructureFromApi(song?.estructura || [], secciones)
  const resolvedAttachmentPermissions =
    song?.adjuntos_permisos && typeof song.adjuntos_permisos === 'object'
      ? song.adjuntos_permisos
      : song?.item?.adjuntos_permisos && typeof song.item.adjuntos_permisos === 'object'
        ? song.item.adjuntos_permisos
        : {
            visibility_mode: 'private',
            visibility_group_ids: [],
            visibility_user_ids: [],
          }
  const rawTags = Array.isArray(song?.tags) && song.tags.length
    ? song.tags
    : Array.isArray(song?.item?.tags)
      ? song.item.tags
      : []
  const tags = rawTags.map((tag) => normalizeLoadedTag(tag)).filter(Boolean)

  return {
    ...createEmptySong(),
    id: song?.id,
    autor_id: song?.autor_id || null,
    autor_nombre: song?.autor_nombre || '',
    es_reversion: !!song?.es_reversion,
    reversion_origen_id: song?.reversion_origen_id || null,
    reversion_origen_titulo: song?.reversion_origen_titulo || '',
    reversion_raiz_id: song?.reversion_raiz_id || null,
    reversion_raiz_titulo: song?.reversion_raiz_titulo || '',
    reversion_autor_origen_id: song?.reversion_autor_origen_id || null,
    reversion_autor_origen_nombre: song?.reversion_autor_origen_nombre || '',
    estado_transcripcion: song?.estado_transcripcion || 'sin_iniciar',
    estado_transcripcion_label: song?.estado_transcripcion_label || 'Sin iniciar',
    estado_ensayo: song?.estado_ensayo || 'sin_ensayar',
    estado_ensayo_label: song?.estado_ensayo_label || 'No ensayada',
    titulo: song?.titulo || '',
    bpm: bpmDefault,
    tonica: song?.tonica || song?.tonalidad || '',
    campo_armonico: song?.campo_armonico || '',
    campo_armonico_predominante: song?.campo_armonico_predominante || '',
    ficha_autores: song?.ficha_autores || '',
    ficha_anio: song?.ficha_anio || '',
    ficha_pais: song?.ficha_pais || '',
    ficha_estado_legal: song?.ficha_estado_legal || '',
    ficha_licencia: song?.ficha_licencia || '',
    ficha_fuente_verificacion: song?.ficha_fuente_verificacion || '',
    ficha_incompleta: !!song?.ficha_incompleta,
    ficha_incompleta_motivo: song?.ficha_incompleta_motivo || '',
    visibility_mode: song?.visibility_mode || 'private',
    visibility_project_ids: Array.isArray(song?.visibility_project_ids) ? song.visibility_project_ids : [],
    visibility_projects: Array.isArray(song?.visibility_projects) ? song.visibility_projects : [],
    visibility_group_ids: Array.isArray(song?.visibility_group_ids) ? song.visibility_group_ids : [],
    visibility_groups: Array.isArray(song?.visibility_groups) ? song.visibility_groups : [],
    visibility_user_ids: Array.isArray(song?.visibility_user_ids) ? song.visibility_user_ids : [],
    visibility_users: Array.isArray(song?.visibility_users) ? song.visibility_users : [],
    rehearsal_project_ids: Array.isArray(song?.rehearsal_project_ids) ? song.rehearsal_project_ids : [],
    rehearsal_projects: Array.isArray(song?.rehearsal_projects) ? song.rehearsal_projects : [],
    can_upload_rehearsals: !!song?.can_upload_rehearsals,
    prestamos: Array.isArray(song?.prestamos) ? song.prestamos : [],
    modulaciones: Array.isArray(song?.modulaciones) ? song.modulaciones : [],
    versos: normalizeVersesFromApi(song?.versos, bpmDefault),
    secciones,
    estructura,
    estructuraPersonalizada: true,
    tiene_prestamos: !!song?.tiene_prestamos,
    tiene_modulaciones: !!song?.tiene_modulaciones,
    colecciones: Array.isArray(song?.colecciones) ? song.colecciones : [],
    adjuntos: Array.isArray(song?.adjuntos) ? song.adjuntos : [],
    adjuntos_permisos: resolvedAttachmentPermissions,
    tags,
  }
}

export function upsertSongInList(songs, incomingSong) {
  const items = Array.isArray(songs) ? songs : []
  const incomingId = Number(incomingSong?.id || 0)
  if (!incomingId) {
    return items
  }
  let found = false
  const nextItems = items.map((item) => {
    if (Number(item?.id || 0) !== incomingId) {
      return item
    }
    found = true
    return { ...item, ...incomingSong }
  })
  return found ? nextItems : [incomingSong, ...nextItems]
}
