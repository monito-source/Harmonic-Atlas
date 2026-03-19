export const TRANSCRIPTION_STATUS_OPTIONS = [
  { id: 'sin_iniciar', label: 'Sin iniciar' },
  { id: 'incompleta', label: 'Incompleta' },
  { id: 'completada', label: 'Completada' },
  { id: 'verificada', label: 'Verificada' },
  { id: 'necesita_cambios', label: 'Necesita cambios' },
]

export const REHEARSAL_STATUS_OPTIONS = [
  { id: 'sin_ensayar', label: 'No ensayada' },
  { id: 'leida', label: 'Ya la leí' },
  { id: 'ensayando', label: 'Ensayando' },
  { id: 'ensayada', label: 'Ensayada' },
  { id: 'dominada', label: 'Dominada' },
  { id: 'aprendida', label: 'Aprendida' },
]

export const TRANSCRIPTION_STATUS_LABELS = TRANSCRIPTION_STATUS_OPTIONS.reduce((acc, option) => {
  acc[option.id] = option.label
  return acc
}, {})

export const REHEARSAL_STATUS_LABELS = REHEARSAL_STATUS_OPTIONS.reduce((acc, option) => {
  acc[option.id] = option.label
  return acc
}, {})

export function getStatusLabel(labelsMap, statusId, fallback = '—') {
  if (!statusId) return fallback
  return labelsMap[statusId] || fallback
}
