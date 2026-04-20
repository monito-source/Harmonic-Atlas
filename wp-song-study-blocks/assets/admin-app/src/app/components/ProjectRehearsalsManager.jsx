import { useCallback, useEffect, useMemo, useState } from 'react'
import { useAppState } from '../StateProvider.jsx'

const DAY_OPTIONS = [
  { value: 'monday', label: 'Lunes' },
  { value: 'tuesday', label: 'Martes' },
  { value: 'wednesday', label: 'Miércoles' },
  { value: 'thursday', label: 'Jueves' },
  { value: 'friday', label: 'Viernes' },
  { value: 'saturday', label: 'Sábado' },
  { value: 'sunday', label: 'Domingo' },
]

const DAY_LABELS = Object.fromEntries(DAY_OPTIONS.map((option) => [option.value, option.label]))

const SESSION_STATUS_OPTIONS = [
  { value: 'proposed', label: 'Propuesta' },
  { value: 'voting', label: 'En votación' },
  { value: 'confirmed', label: 'Confirmado' },
  { value: 'completed', label: 'Realizado' },
  { value: 'cancelled', label: 'Cancelado' },
]

const SESSION_STATUS_LABELS = Object.fromEntries(SESSION_STATUS_OPTIONS.map((option) => [option.value, option.label]))

const ATTENDANCE_STATUS_OPTIONS = [
  { value: 'pending', label: 'Pendiente' },
  { value: 'confirmed', label: 'Confirmado' },
  { value: 'attended', label: 'Asistió' },
  { value: 'late', label: 'Llegó tarde' },
  { value: 'absent', label: 'No asistió' },
  { value: 'excused', label: 'Justificado' },
]

const ATTENDANCE_STATUS_LABELS = Object.fromEntries(ATTENDANCE_STATUS_OPTIONS.map((option) => [option.value, option.label]))

const VOTE_OPTIONS = [
  { value: 'pending', label: 'Sin votar' },
  { value: 'yes', label: 'Sí' },
  { value: 'maybe', label: 'Tal vez' },
  { value: 'no', label: 'No' },
]

const VOTE_LABELS = Object.fromEntries(VOTE_OPTIONS.map((option) => [option.value, option.label]))

function createRange(prefix = 'slot') {
  return {
    id: `${prefix}-${Date.now()}-${Math.floor(Math.random() * 100000)}`,
    day: 'monday',
    start: '19:00',
    end: '21:00',
  }
}

function normalizeTime(value, fallback = '') {
  return /^\d{2}:\d{2}$/.test(String(value || '')) ? String(value) : fallback
}

function timeToMinutes(value) {
  const safeValue = normalizeTime(value)
  if (!safeValue) return -1
  const [hours, minutes] = safeValue.split(':').map((item) => Number(item || 0))
  return (hours * 60) + minutes
}

function normalizeRange(slot, prefix = 'slot') {
  const day = DAY_LABELS[String(slot?.day || '')] ? String(slot.day) : 'monday'
  const start = normalizeTime(slot?.start, '19:00')
  const end = normalizeTime(slot?.end, '21:00')

  return {
    id: String(slot?.id || `${prefix}-${Date.now()}-${Math.floor(Math.random() * 100000)}`),
    day,
    start,
    end: timeToMinutes(end) > timeToMinutes(start) ? end : start,
  }
}

function buildMemberDirectory(collaborators, seedEntries = []) {
  const directory = new Map()

  ;(Array.isArray(collaborators) ? collaborators : []).forEach((member) => {
    const userId = Number(member?.id || member?.user_id || 0)
    if (!Number.isInteger(userId) || userId <= 0) return

    directory.set(userId, {
      id: userId,
      nombre: String(member?.nombre || ''),
    })
  })

  ;(Array.isArray(seedEntries) ? seedEntries : []).forEach((entry) => {
    const userId = Number(entry?.user_id || entry?.id || 0)
    if (!Number.isInteger(userId) || userId <= 0 || directory.has(userId)) return

    directory.set(userId, {
      id: userId,
      nombre: String(entry?.nombre || ''),
    })
  })

  return Array.from(directory.values()).sort((left, right) => String(left?.nombre || '').localeCompare(String(right?.nombre || '')))
}

function buildAttendance(attendance, collaborators) {
  const map = new Map(
    (Array.isArray(attendance) ? attendance : [])
      .map((item) => [
        Number(item?.user_id || 0),
        {
          user_id: Number(item?.user_id || 0),
          nombre: String(item?.nombre || ''),
          status: ATTENDANCE_STATUS_LABELS[String(item?.status || '')] ? String(item.status) : 'pending',
          comment: String(item?.comment || ''),
        },
      ])
      .filter(([userId]) => Number.isInteger(userId) && userId > 0),
  )

  return buildMemberDirectory(collaborators, attendance)
    .map((member) => {
      const userId = Number(member?.id || member?.user_id || 0)
      const existing = map.get(userId)
      return {
        user_id: userId,
        nombre: String(member?.nombre || existing?.nombre || ''),
        status: existing?.status || 'pending',
        comment: existing?.comment || '',
      }
    })
    .filter((item) => Number.isInteger(item.user_id) && item.user_id > 0)
}

function buildVotes(votes, collaborators) {
  const map = new Map(
    (Array.isArray(votes) ? votes : [])
      .map((item) => [
        Number(item?.user_id || 0),
        {
          user_id: Number(item?.user_id || 0),
          nombre: String(item?.nombre || ''),
          vote: VOTE_LABELS[String(item?.vote || '')] ? String(item.vote) : 'pending',
          comment: String(item?.comment || ''),
        },
      ])
      .filter(([userId]) => Number.isInteger(userId) && userId > 0),
  )

  return buildMemberDirectory(collaborators, votes)
    .map((member) => {
      const userId = Number(member?.id || member?.user_id || 0)
      const existing = map.get(userId)
      return {
        user_id: userId,
        nombre: String(member?.nombre || existing?.nombre || ''),
        vote: existing?.vote || 'pending',
        comment: existing?.comment || '',
      }
    })
    .filter((item) => Number.isInteger(item.user_id) && item.user_id > 0)
}

function normalizeAvailability(availability, collaborators) {
  const map = new Map(
    (Array.isArray(availability) ? availability : [])
      .map((item) => [Number(item?.user_id || 0), item])
      .filter(([userId]) => Number.isInteger(userId) && userId > 0),
  )

  return buildMemberDirectory(collaborators, availability)
    .map((member) => {
      const userId = Number(member?.id || member?.user_id || 0)
      const source = map.get(userId)
      return {
        user_id: userId,
        nombre: String(member?.nombre || source?.nombre || ''),
        notes: String(source?.notes || ''),
        updated_at: String(source?.updated_at || ''),
        blocked_days: Array.isArray(source?.blocked_days)
          ? source.blocked_days.filter((day) => DAY_LABELS[String(day || '')])
          : [],
        slots: Array.isArray(source?.slots) && source.slots.length
          ? source.slots.map((slot) => normalizeRange(slot, 'slot'))
          : [],
        unavailable_slots: Array.isArray(source?.unavailable_slots) && source.unavailable_slots.length
          ? source.unavailable_slots.map((slot) => normalizeRange(slot, 'unavailable'))
          : [],
      }
    })
    .filter((item) => Number.isInteger(item.user_id) && item.user_id > 0)
}

function computeConsensus(votes) {
  return Array.isArray(votes) && votes.length > 0 && votes.every((vote) => vote.vote === 'yes')
}

function createSession(collaborators, seed = {}) {
  const votes = buildVotes(seed?.votes, collaborators)
  const status = SESSION_STATUS_LABELS[String(seed?.status || '')] ? String(seed.status) : 'proposed'
  const consensusReached = seed?.consensus_reached === true || computeConsensus(votes)
  const calendarSource = seed?.calendar && typeof seed.calendar === 'object' ? seed.calendar : {}

  return {
    id: String(seed?.id || `session-${Date.now()}-${Math.floor(Math.random() * 100000)}`),
    scheduled_for: /^\d{4}-\d{2}-\d{2}$/.test(String(seed?.scheduled_for || '')) ? String(seed.scheduled_for) : '',
    start_time: normalizeTime(seed?.start_time, '19:00'),
    end_time: normalizeTime(seed?.end_time, '21:00'),
    location: String(seed?.location || ''),
    status: consensusReached && ['proposed', 'voting'].includes(status) ? 'confirmed' : status,
    focus: String(seed?.focus || ''),
    reviewed_items: Array.isArray(seed?.reviewed_items)
      ? seed.reviewed_items.map((item) => String(item || '').trim()).filter(Boolean)
      : [],
    notes: String(seed?.notes || ''),
    attendance: buildAttendance(seed?.attendance, collaborators),
    votes,
    consensus_reached: consensusReached,
    calendar: {
      google_calendar_url: String(calendarSource.google_calendar_url || ''),
      ready: !!calendarSource.ready,
      can_sync: !!calendarSource.can_sync,
      has_event: !!calendarSource.has_event,
      event_id: String(calendarSource.event_id || ''),
      html_link: String(calendarSource.html_link || ''),
      synced_at: String(calendarSource.synced_at || ''),
      sync_status: String(calendarSource.sync_status || ''),
      sync_error: String(calendarSource.sync_error || ''),
    },
  }
}

function normalizeProjectPayload(payload) {
  const project = payload?.project && typeof payload.project === 'object' ? payload.project : {}
  const collaborators = Array.isArray(project?.colaboradores)
    ? project.colaboradores
        .map((item) => ({
          id: Number(item?.id || 0),
          nombre: String(item?.nombre || ''),
        }))
        .filter((item) => Number.isInteger(item.id) && item.id > 0)
    : []

  const sessions = Array.isArray(payload?.sessions)
    ? payload.sessions.map((session) => createSession(collaborators, session))
    : []

  return {
    project: {
      id: Number(project?.id || 0),
      titulo: String(project?.titulo || ''),
      colaboradores: collaborators,
      can_manage_rehearsals: project?.can_manage_rehearsals !== false,
      google_calendar: project?.google_calendar && typeof project.google_calendar === 'object'
        ? {
            available: project.google_calendar.available !== false,
            configured: !!project.google_calendar.configured,
            connected: !!project.google_calendar.connected,
            ready: !!project.google_calendar.ready,
            has_access_token: !!project.google_calendar.has_access_token,
            has_refresh_token: !!project.google_calendar.has_refresh_token,
            account_email: String(project.google_calendar.account_email || ''),
            connect_url: String(project.google_calendar.connect_url || ''),
            oauth_settings_url: String(project.google_calendar.oauth_settings_url || ''),
            oauth_settings_label: String(project.google_calendar.oauth_settings_label || ''),
            redirect_uri: String(project.google_calendar.redirect_uri || ''),
            authorized_origin: String(project.google_calendar.authorized_origin || ''),
            credentials_source: String(project.google_calendar.credentials_source || ''),
            client_id_hint: String(project.google_calendar.client_id_hint || ''),
            required_scopes: Array.isArray(project.google_calendar.required_scopes) ? project.google_calendar.required_scopes : [],
            required_scope_labels: Array.isArray(project.google_calendar.required_scope_labels) ? project.google_calendar.required_scope_labels : [],
            granted_scopes: Array.isArray(project.google_calendar.granted_scopes) ? project.google_calendar.granted_scopes : [],
            granted_scope_labels: Array.isArray(project.google_calendar.granted_scope_labels) ? project.google_calendar.granted_scope_labels : [],
            missing_scopes: Array.isArray(project.google_calendar.missing_scopes) ? project.google_calendar.missing_scopes : [],
            missing_scope_labels: Array.isArray(project.google_calendar.missing_scope_labels) ? project.google_calendar.missing_scope_labels : [],
            last_error: String(project.google_calendar.last_error || ''),
            reconnect_reason: String(project.google_calendar.reconnect_reason || ''),
            calendar_probe_ok: !!project.google_calendar.calendar_probe_ok,
            calendar_probe_message: String(project.google_calendar.calendar_probe_message || ''),
            status_message: String(project.google_calendar.status_message || ''),
          }
        : {
            available: false,
            configured: false,
            connected: false,
            ready: false,
            has_access_token: false,
            has_refresh_token: false,
            account_email: '',
            connect_url: '',
            oauth_settings_url: '',
            oauth_settings_label: '',
            redirect_uri: '',
            authorized_origin: '',
            credentials_source: '',
            client_id_hint: '',
            required_scopes: [],
            required_scope_labels: [],
            granted_scopes: [],
            granted_scope_labels: [],
            missing_scopes: [],
            missing_scope_labels: [],
            last_error: '',
            reconnect_reason: '',
            calendar_probe_ok: false,
            calendar_probe_message: '',
            status_message: '',
          },
    },
    availability: normalizeAvailability(payload?.availability, collaborators),
    sessions,
    summary: payload?.summary && typeof payload.summary === 'object' ? payload.summary : {},
    recommended_slots: Array.isArray(payload?.recommended_slots) ? payload.recommended_slots : [],
    google_calendar_sync: Array.isArray(payload?.google_calendar_sync) ? payload.google_calendar_sync : [],
    updated_at_gmt: String(payload?.updated_at_gmt || ''),
    updated_by: Number(payload?.updated_by || 0),
  }
}

function getDurationMinutes(start, end) {
  const startMinutes = timeToMinutes(start)
  const endMinutes = timeToMinutes(end)
  if (startMinutes < 0 || endMinutes <= startMinutes) return 0
  return endMinutes - startMinutes
}

function computeRecommendedSlots(availability) {
  const minimumAttendance = Array.isArray(availability) && availability.length > 1 ? 2 : 1
  const recommendations = []

  DAY_OPTIONS.forEach((dayOption, dayIndex) => {
    const boundaries = new Set()
    const memberAvailability = []

    ;(Array.isArray(availability) ? availability : []).forEach((member) => {
      const blockedDays = Array.isArray(member?.blocked_days) ? member.blocked_days : []
      if (blockedDays.includes(dayOption.value)) return

      const availableRanges = []
      const unavailableRanges = []

      ;(Array.isArray(member?.slots) ? member.slots : []).forEach((slot) => {
        if (slot?.day !== dayOption.value) return
        const start = timeToMinutes(slot?.start)
        const end = timeToMinutes(slot?.end)
        if (start < 0 || end <= start) return
        boundaries.add(start)
        boundaries.add(end)
        availableRanges.push({ start, end })
      })

      ;(Array.isArray(member?.unavailable_slots) ? member.unavailable_slots : []).forEach((slot) => {
        if (slot?.day !== dayOption.value) return
        const start = timeToMinutes(slot?.start)
        const end = timeToMinutes(slot?.end)
        if (start < 0 || end <= start) return
        boundaries.add(start)
        boundaries.add(end)
        unavailableRanges.push({ start, end })
      })

      memberAvailability.push({
        user_id: Number(member?.user_id || 0),
        nombre: String(member?.nombre || ''),
        availableRanges,
        unavailableRanges,
      })
    })

    const orderedBoundaries = Array.from(boundaries).sort((left, right) => left - right)
    let previous = null

    for (let index = 0; index < orderedBoundaries.length - 1; index += 1) {
      const segmentStart = orderedBoundaries[index]
      const segmentEnd = orderedBoundaries[index + 1]
      if (segmentEnd <= segmentStart) continue

      const members = memberAvailability
        .filter((member) => {
          const isWithinAvailable = member.availableRanges.some((range) => range.start <= segmentStart && range.end >= segmentEnd)
          if (!isWithinAvailable) return false

          const isBlocked = member.unavailableRanges.some((range) => range.start < segmentEnd && range.end > segmentStart)
          return !isBlocked
        })
        .map((member) => ({ user_id: member.user_id, nombre: member.nombre }))
        .filter((item) => Number.isInteger(item.user_id) && item.user_id > 0)
        .sort((left, right) => left.user_id - right.user_id)

      if (members.length < minimumAttendance) {
        previous = null
        continue
      }

      const memberIds = members.map((member) => member.user_id)
      if (
        previous
        && previous.day === dayOption.value
        && previous.end_minutes === segmentStart
        && previous.member_ids.join(',') === memberIds.join(',')
      ) {
        previous.end_minutes = segmentEnd
        previous.duration_minutes = segmentEnd - previous.start_minutes
        recommendations[recommendations.length - 1] = previous
        continue
      }

      previous = {
        day: dayOption.value,
        day_index: dayIndex,
        start_minutes: segmentStart,
        end_minutes: segmentEnd,
        duration_minutes: segmentEnd - segmentStart,
        member_count: members.length,
        member_ids: memberIds,
        member_names: members.map((member) => member.nombre),
      }

      recommendations.push(previous)
    }
  })

  return recommendations
    .sort((left, right) => {
      if (left.member_count !== right.member_count) return right.member_count - left.member_count
      if (left.duration_minutes !== right.duration_minutes) return right.duration_minutes - left.duration_minutes
      if (left.day_index !== right.day_index) return left.day_index - right.day_index
      return left.start_minutes - right.start_minutes
    })
    .slice(0, 8)
    .map((item) => ({
      day: item.day,
      start: `${String(Math.floor(item.start_minutes / 60)).padStart(2, '0')}:${String(item.start_minutes % 60).padStart(2, '0')}`,
      end: `${String(Math.floor(item.end_minutes / 60)).padStart(2, '0')}:${String(item.end_minutes % 60).padStart(2, '0')}`,
      duration_minutes: item.duration_minutes,
      member_count: item.member_count,
      member_ids: item.member_ids,
      member_names: item.member_names,
    }))
}

function computeSummary(collaborators, availability, sessions) {
  const now = new Date()
  const nextSession = (Array.isArray(sessions) ? sessions : [])
    .filter((session) => session?.status === 'confirmed' && session?.scheduled_for)
    .map((session) => ({
      ...session,
      stamp: new Date(`${session.scheduled_for}T${session.start_time || '00:00'}:00`),
    }))
    .filter((session) => Number.isFinite(session.stamp.getTime()) && session.stamp >= now)
    .sort((left, right) => left.stamp.getTime() - right.stamp.getTime())[0]

  return {
    total_members: Array.isArray(collaborators) ? collaborators.length : 0,
    members_with_availability: (Array.isArray(availability) ? availability : []).filter((item) => Array.isArray(item?.slots) && item.slots.length).length,
    sessions_total: Array.isArray(sessions) ? sessions.length : 0,
    proposal_sessions: (Array.isArray(sessions) ? sessions : []).filter((session) => ['proposed', 'voting'].includes(session?.status)).length,
    confirmed_sessions: (Array.isArray(sessions) ? sessions : []).filter((session) => session?.status === 'confirmed').length,
    completed_sessions: (Array.isArray(sessions) ? sessions : []).filter((session) => session?.status === 'completed').length,
    next_session_label: nextSession
      ? `${nextSession.scheduled_for}${nextSession.start_time ? ` · ${nextSession.start_time}` : ''}`
      : 'Sin fecha confirmada',
  }
}

function computeAttendanceLeaderboard(collaborators, sessions) {
  return (Array.isArray(collaborators) ? collaborators : [])
    .map((member) => {
      let attended = 0
      let completed = 0

      ;(Array.isArray(sessions) ? sessions : []).forEach((session) => {
        if (session?.status !== 'completed') return
        const attendance = Array.isArray(session?.attendance) ? session.attendance : []
        const memberAttendance = attendance.find((item) => Number(item?.user_id) === Number(member.id))
        if (!memberAttendance) return
        completed += 1
        if (['attended', 'late'].includes(memberAttendance.status)) attended += 1
      })

      return {
        user_id: Number(member.id || 0),
        nombre: String(member.nombre || ''),
        attended,
        completed,
        rate: completed > 0 ? Math.round((attended / completed) * 100) : null,
      }
    })
    .sort((left, right) => {
      if ((right.rate || -1) !== (left.rate || -1)) return (right.rate || -1) - (left.rate || -1)
      return left.nombre.localeCompare(right.nombre, 'es')
    })
}

function formatSlotLabel(slot) {
  return `${DAY_LABELS[slot?.day] || 'Horario'} · ${slot?.start || '--:--'}–${slot?.end || '--:--'}`
}

function getNextDateForDay(day) {
  const dayMap = {
    sunday: 0,
    monday: 1,
    tuesday: 2,
    wednesday: 3,
    thursday: 4,
    friday: 5,
    saturday: 6,
  }

  const target = dayMap[String(day || '')]
  if (typeof target !== 'number') return ''

  const base = new Date()
  const current = base.getDay()
  let delta = target - current
  if (delta <= 0) delta += 7
  base.setDate(base.getDate() + delta)

  return [
    base.getFullYear(),
    String(base.getMonth() + 1).padStart(2, '0'),
    String(base.getDate()).padStart(2, '0'),
  ].join('-')
}

function sortSessionsByDate(items) {
  return [...items].sort((left, right) => {
    const leftStamp = new Date(`${left.scheduled_for || '2999-12-31'}T${left.start_time || '00:00'}:00`).getTime()
    const rightStamp = new Date(`${right.scheduled_for || '2999-12-31'}T${right.start_time || '00:00'}:00`).getTime()
    return leftStamp - rightStamp
  })
}

function renderVoteSummary(votes) {
  const counts = { yes: 0, maybe: 0, no: 0, pending: 0 }
  ;(Array.isArray(votes) ? votes : []).forEach((vote) => {
    const key = VOTE_LABELS[String(vote?.vote || '')] ? vote.vote : 'pending'
    counts[key] += 1
  })
  return `Sí ${counts.yes} · Tal vez ${counts.maybe} · No ${counts.no} · Pendientes ${counts.pending}`
}

function getCalendarActionLabel(session) {
  if (session?.status === 'cancelled') return 'Notificar cancelación en Google Calendar'
  if (session?.calendar?.has_event) return 'Actualizar en Google Calendar'
  return 'Crear en Google Calendar'
}

function renderSyncStatus(session) {
  const status = String(session?.calendar?.sync_status || '')
  if (status === 'synced') return 'Sincronizado con Google Calendar'
  if (status === 'cancelled') return 'Cancelación enviada a Google Calendar'
  if (status === 'error') return 'Última sincronización con error'
  return ''
}

export default function ProjectRehearsalsManager() {
  const { api } = useAppState()
  const [projects, setProjects] = useState([])
  const [loading, setLoading] = useState(false)
  const [detailLoading, setDetailLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [calendarSyncingId, setCalendarSyncingId] = useState(null)
  const [error, setError] = useState(null)
  const [notice, setNotice] = useState(null)
  const [activeProjectId, setActiveProjectId] = useState(null)
  const [draft, setDraft] = useState(null)
  const [activeCalendarSessionId, setActiveCalendarSessionId] = useState(null)
  const [activeLogbookSessionId, setActiveLogbookSessionId] = useState(null)

  const refreshProjects = useCallback(
    (preferredId = null) => {
      setLoading(true)
      setError(null)

      api
        .listProjects()
        .then((response) => {
          const items = Array.isArray(response?.data) ? response.data : []
          setProjects(items)
          if (!items.length) {
            setDraft(null)
            setActiveCalendarSessionId(null)
            setActiveLogbookSessionId(null)
          }
          setActiveProjectId((previous) => {
            const nextPreferred = preferredId || previous
            if (nextPreferred && items.some((item) => Number(item?.id) === Number(nextPreferred))) {
              return Number(nextPreferred)
            }
            return items[0]?.id ? Number(items[0].id) : null
          })
        })
        .catch((requestError) => {
          setError(requestError?.payload?.message || 'No fue posible cargar los proyectos.')
        })
        .finally(() => {
          setLoading(false)
        })
    },
    [api],
  )

  const loadProject = useCallback(
    (projectId) => {
      if (!projectId) {
        setDraft(null)
        return
      }

      setDetailLoading(true)
      setError(null)
      setNotice(null)

      api
        .getProjectRehearsals(projectId)
        .then((response) => {
          const nextDraft = normalizeProjectPayload(response?.data)
          setDraft(nextDraft)
          const calendarItems = nextDraft.sessions.filter((session) => session.status !== 'completed')
          const logbookItems = nextDraft.sessions.filter((session) => session.status === 'completed')
          setActiveCalendarSessionId((previous) => {
            if (previous && calendarItems.some((session) => session.id === previous)) return previous
            return calendarItems[0]?.id || null
          })
          setActiveLogbookSessionId((previous) => {
            if (previous && logbookItems.some((session) => session.id === previous)) return previous
            return logbookItems[0]?.id || null
          })
        })
        .catch((requestError) => {
          setDraft(null)
          setActiveCalendarSessionId(null)
          setActiveLogbookSessionId(null)
          setError(requestError?.payload?.message || 'No fue posible cargar la herramienta de ensayos del proyecto.')
        })
        .finally(() => {
          setDetailLoading(false)
        })
    },
    [api],
  )

  useEffect(() => {
    const timeoutId = window.setTimeout(() => {
      refreshProjects()
    }, 0)

    return () => {
      window.clearTimeout(timeoutId)
    }
  }, [refreshProjects])

  useEffect(() => {
    if (!activeProjectId) return undefined

    const timeoutId = window.setTimeout(() => {
      loadProject(activeProjectId)
    }, 0)

    return () => {
      window.clearTimeout(timeoutId)
    }
  }, [activeProjectId, loadProject])

  const collaborators = useMemo(
    () => (Array.isArray(draft?.project?.colaboradores) ? draft.project.colaboradores : []),
    [draft],
  )
  const availability = useMemo(
    () => (Array.isArray(draft?.availability) ? draft.availability : []),
    [draft],
  )
  const sessions = useMemo(
    () => (Array.isArray(draft?.sessions) ? draft.sessions : []),
    [draft],
  )
  const googleCalendar = useMemo(
    () => (draft?.project?.google_calendar && typeof draft.project.google_calendar === 'object' ? draft.project.google_calendar : {}),
    [draft],
  )

  const recommendedSlots = useMemo(() => computeRecommendedSlots(availability), [availability])
  const summary = useMemo(() => computeSummary(collaborators, availability, sessions), [availability, collaborators, sessions])
  const attendanceLeaderboard = useMemo(
    () => computeAttendanceLeaderboard(collaborators, sessions),
    [collaborators, sessions],
  )
  const calendarSessions = useMemo(
    () => sortSessionsByDate(sessions.filter((session) => session.status !== 'completed')),
    [sessions],
  )
  const logbookSessions = useMemo(
    () => sortSessionsByDate(sessions.filter((session) => session.status === 'completed')),
    [sessions],
  )
  const activeCalendarSession = useMemo(
    () => calendarSessions.find((session) => session.id === activeCalendarSessionId) || null,
    [activeCalendarSessionId, calendarSessions],
  )
  const activeLogbookSession = useMemo(
    () => logbookSessions.find((session) => session.id === activeLogbookSessionId) || null,
    [activeLogbookSessionId, logbookSessions],
  )

  const updateDraft = (updater) => {
    setDraft((previous) => {
      if (!previous) return previous
      return typeof updater === 'function' ? updater(previous) : updater
    })
  }

  const updateAvailabilityEntry = (userId, updater) => {
    updateDraft((previous) => ({
      ...previous,
      availability: previous.availability.map((item) => (
        Number(item.user_id) === Number(userId)
          ? typeof updater === 'function'
            ? updater(item)
            : updater
          : item
      )),
    }))
  }

  const addAvailabilityRange = (userId, key) => {
    updateAvailabilityEntry(userId, (entry) => ({
      ...entry,
      [key]: [...entry[key], createRange(key === 'slots' ? 'slot' : 'unavailable')],
    }))
  }

  const updateAvailabilityRangeField = (userId, key, rangeId, field, value) => {
    updateAvailabilityEntry(userId, (entry) => ({
      ...entry,
      [key]: entry[key].map((slot) => (slot.id === rangeId ? { ...slot, [field]: value } : slot)),
    }))
  }

  const removeAvailabilityRange = (userId, key, rangeId) => {
    updateAvailabilityEntry(userId, (entry) => ({
      ...entry,
      [key]: entry[key].filter((slot) => slot.id !== rangeId),
    }))
  }

  const toggleBlockedDay = (userId, day, checked) => {
    updateAvailabilityEntry(userId, (entry) => {
      const blockedDays = new Set(Array.isArray(entry.blocked_days) ? entry.blocked_days : [])
      if (checked) blockedDays.add(day)
      else blockedDays.delete(day)

      return {
        ...entry,
        blocked_days: DAY_OPTIONS.map((option) => option.value).filter((value) => blockedDays.has(value)),
        slots: checked ? entry.slots.filter((slot) => slot.day !== day) : entry.slots,
        unavailable_slots: checked ? entry.unavailable_slots.filter((slot) => slot.day !== day) : entry.unavailable_slots,
      }
    })
  }

  const updateSession = (sessionId, updater) => {
    updateDraft((previous) => ({
      ...previous,
      sessions: previous.sessions.map((session) => {
        if (session.id !== sessionId) return session
        return createSession(collaborators, typeof updater === 'function' ? updater(session) : updater)
      }),
    }))
  }

  const handleNewSession = (seed = {}) => {
    const nextSession = createSession(collaborators, seed)
    updateDraft((previous) => ({
      ...previous,
      sessions: sortSessionsByDate([...previous.sessions, nextSession]),
    }))

    if (nextSession.status === 'completed') {
      setActiveLogbookSessionId(nextSession.id)
    } else {
      setActiveCalendarSessionId(nextSession.id)
    }
  }

  const handleRemoveSession = (sessionId) => {
    const confirmed = window.confirm('¿Eliminar este ensayo de la herramienta?')
    if (!confirmed) return

    const target = sessions.find((session) => session.id === sessionId)
    const remainingSessions = sessions.filter((session) => session.id !== sessionId)

    updateDraft((previous) => ({
      ...previous,
      sessions: remainingSessions,
    }))

    if (target?.status === 'completed') {
      const remainingLogbook = remainingSessions.filter((session) => session.status === 'completed')
      setActiveLogbookSessionId(remainingLogbook[0]?.id || null)
      return
    }

    const remainingCalendar = remainingSessions.filter((session) => session.status !== 'completed')
    setActiveCalendarSessionId(remainingCalendar[0]?.id || null)
  }

  const handleScheduleRecommendedSlot = (slot) => {
    handleNewSession({
      status: 'proposed',
      scheduled_for: getNextDateForDay(slot?.day),
      start_time: slot?.start || '19:00',
      end_time: slot?.end || '21:00',
      focus: `Propuesta de ensayo (${DAY_LABELS[slot?.day] || 'Horario'})`,
      notes: `Ventana detectada para ${slot?.member_names?.join(', ') || 'el proyecto'}.`,
    })
  }

  const updateSessionStatus = (sessionId, nextStatus) => {
    updateSession(sessionId, (session) => ({ ...session, status: nextStatus }))
    if (nextStatus === 'completed') {
      setActiveLogbookSessionId(sessionId)
    } else {
      setActiveCalendarSessionId(sessionId)
    }
  }

  const handleSave = () => {
    if (!draft?.project?.id) return

    const invalidAvailability = availability.some((entry) => (
      ['slots', 'unavailable_slots'].some((key) => (
        (Array.isArray(entry?.[key]) ? entry[key] : []).some((slot) => getDurationMinutes(slot?.start, slot?.end) <= 0)
      ))
    ))

    if (invalidAvailability) {
      setError('Todos los rangos base, disponibles o no disponibles, deben tener una hora de fin posterior a la de inicio.')
      return
    }

    const invalidSession = sessions.find((session) => {
      if (!session?.scheduled_for) return true
      return !!session?.end_time && getDurationMinutes(session?.start_time, session?.end_time) <= 0
    })

    if (invalidSession) {
      setError('Cada propuesta o ensayo necesita una fecha válida y un rango horario coherente.')
      return
    }

    const payload = {
      availability: availability.map((entry) => ({
        user_id: entry.user_id,
        notes: entry.notes,
        blocked_days: entry.blocked_days,
        slots: entry.slots.map((slot) => ({
          id: slot.id,
          day: slot.day,
          start: slot.start,
          end: slot.end,
        })),
        unavailable_slots: entry.unavailable_slots.map((slot) => ({
          id: slot.id,
          day: slot.day,
          start: slot.start,
          end: slot.end,
        })),
      })),
      sessions: sessions.map((session) => ({
        id: session.id,
        scheduled_for: session.scheduled_for,
        start_time: session.start_time,
        end_time: session.end_time,
        location: session.location,
        status: session.status,
        focus: session.focus,
        reviewed_items: session.reviewed_items,
        notes: session.notes,
        votes: session.votes.map((item) => ({
          user_id: item.user_id,
          vote: item.vote,
          comment: item.comment,
        })),
        attendance: session.attendance.map((item) => ({
          user_id: item.user_id,
          status: item.status,
          comment: item.comment,
        })),
        calendar: {
          event_id: session.calendar?.event_id || '',
          html_link: session.calendar?.html_link || '',
          synced_at: session.calendar?.synced_at || '',
          sync_status: session.calendar?.sync_status || '',
          sync_error: session.calendar?.sync_error || '',
        },
      })),
    }

    setSaving(true)
    setError(null)
    setNotice(null)

    api
      .saveProjectRehearsals(draft.project.id, payload)
      .then((response) => {
        const nextDraft = normalizeProjectPayload(response?.data)
        setDraft(nextDraft)
        const syncResults = Array.isArray(response?.data?.google_calendar_sync) ? response.data.google_calendar_sync : []
        const syncedCount = syncResults.filter((item) => item?.success).length
        setNotice(
          syncedCount > 0
            ? `Cambios guardados. Google Calendar actualizado en ${syncedCount} ensayo(s).`
            : 'Cambios guardados.',
        )
      })
      .catch((requestError) => {
        setError(requestError?.payload?.message || 'No fue posible guardar la herramienta de ensayos.')
      })
      .finally(() => {
        setSaving(false)
      })
  }

  const handleSyncCalendar = (sessionId) => {
    if (!draft?.project?.id || !sessionId) return

    setCalendarSyncingId(sessionId)
    setError(null)
    setNotice(null)

    api
      .syncProjectRehearsalCalendar(draft.project.id, { session_id: sessionId })
      .then((response) => {
        const nextDraft = normalizeProjectPayload(response?.data?.payload || {})
        setDraft(nextDraft)
        setNotice(response?.data?.message || 'Ensayo sincronizado con Google Calendar.')
      })
      .catch((requestError) => {
        setError(requestError?.payload?.message || 'No fue posible sincronizar este ensayo con Google Calendar.')
      })
      .finally(() => {
        setCalendarSyncingId(null)
      })
  }

  const connectCalendarUrl = googleCalendar?.connect_url || '#'
  const oauthSettingsUrl = googleCalendar?.oauth_settings_url || googleCalendar?.profile_url || '#'
  const oauthSettingsLabel = googleCalendar?.oauth_settings_label || (googleCalendar?.credentials_source === 'user' ? 'Abrir perfil OAuth' : 'Abrir credenciales globales')

  return (
    <section className="wpss-collections">
      <div className="wpss-project-rehearsals__editor">
        <div className="wpss-project-rehearsals__hero">
          <div>
            <p className="wpss-project-rehearsals__eyebrow">Ensayos por proyecto</p>
            <h1>Organiza disponibilidad, votación, agenda y bitácora del grupo.</h1>
            <p>
              Cada músico puede declarar con claridad cuándo sí puede ensayar, cuándo no puede y qué días quedan cerrados por completo.
              A partir de eso el proyecto propone ventanas reales y permite llevar seguimiento del compromiso del grupo.
            </p>
          </div>

          <div className="wpss-project-rehearsals__hero-actions">
            <label className="wpss-field">
              <span>Proyecto musical</span>
              <select
                value={activeProjectId || ''}
                onChange={(event) => setActiveProjectId(event.target.value ? Number(event.target.value) : null)}
                disabled={loading || detailLoading}
              >
                {!projects.length ? <option value="">Sin proyectos disponibles</option> : null}
                {projects.map((project) => (
                  <option key={project.id} value={project.id}>{project.titulo || `Proyecto ${project.id}`}</option>
                ))}
              </select>
            </label>
            <button type="button" className="button button-primary" onClick={handleSave} disabled={saving}>
              {saving ? 'Guardando…' : 'Guardar herramienta'}
            </button>
          </div>
        </div>

        {error ? <p className="wpss-error">{error}</p> : null}
        {notice ? <p className="wpss-feedback">{notice}</p> : null}
        {detailLoading ? <p className="wpss-collections__hint">Cargando herramienta de ensayos…</p> : null}

        {draft ? (
          <div className="wpss-project-rehearsals__stack">
            <div className="wpss-project-rehearsals__metrics">
              <article className="wpss-project-rehearsals__metric">
                <strong>{summary.members_with_availability}</strong>
                <span>Integrantes con horas disponibles</span>
              </article>
              <article className="wpss-project-rehearsals__metric">
                <strong>{summary.proposal_sessions}</strong>
                <span>Propuestas abiertas</span>
              </article>
              <article className="wpss-project-rehearsals__metric">
                <strong>{summary.confirmed_sessions}</strong>
                <span>Ensayos confirmados</span>
              </article>
              <article className="wpss-project-rehearsals__metric">
                <strong>{summary.completed_sessions}</strong>
                <span>Ensayos en bitácora</span>
              </article>
            </div>

            <section className="wpss-project-rehearsals__panel">
              <div className="wpss-project-rehearsals__panel-header">
                <div>
                  <h2>Ventanas de tiempo</h2>
                  <p>Define horarios base disponibles, rangos no disponibles y días cerrados. Las coincidencias excluyen automáticamente los bloqueos parciales y completos.</p>
                </div>
              </div>

              {!collaborators.length ? (
                <p className="wpss-empty">Este proyecto no tiene colaboradores vinculados todavía.</p>
              ) : (
                <div className="wpss-project-rehearsals__availability-grid">
                  {availability.map((entry) => (
                    <article key={entry.user_id} className="wpss-project-rehearsals__card">
                      <div className="wpss-project-rehearsals__card-header">
                        <strong>{entry.nombre}</strong>
                      </div>

                      <div className="wpss-project-rehearsals__availability-section">
                        <strong>No disponible todo el día</strong>
                        <div className="wpss-project-rehearsals__day-toggles">
                          {DAY_OPTIONS.map((option) => (
                            <label key={`${entry.user_id}-${option.value}`} className="wpss-project-rehearsals__day-chip">
                              <input
                                type="checkbox"
                                checked={entry.blocked_days.includes(option.value)}
                                onChange={(event) => toggleBlockedDay(entry.user_id, option.value, event.target.checked)}
                              />
                              <span>{option.label}</span>
                            </label>
                          ))}
                        </div>
                      </div>

                      <label className="wpss-field">
                        <span>Observaciones</span>
                        <textarea
                          rows="2"
                          value={entry.notes}
                          onChange={(event) => updateAvailabilityEntry(entry.user_id, { ...entry, notes: event.target.value })}
                        />
                      </label>

                      <div className="wpss-project-rehearsals__availability-section">
                        <div className="wpss-project-rehearsals__subheader">
                          <strong>Disponible por rangos</strong>
                          <button type="button" className="button button-small" onClick={() => addAvailabilityRange(entry.user_id, 'slots')}>
                            Añadir horario
                          </button>
                        </div>
                        <div className="wpss-project-rehearsals__slot-list">
                          {entry.slots.map((slot) => (
                            <div key={slot.id} className="wpss-project-rehearsals__slot-row">
                              <select value={slot.day} onChange={(event) => updateAvailabilityRangeField(entry.user_id, 'slots', slot.id, 'day', event.target.value)}>
                                {DAY_OPTIONS.filter((option) => !entry.blocked_days.includes(option.value)).map((option) => (
                                  <option key={option.value} value={option.value}>{option.label}</option>
                                ))}
                              </select>
                              <input
                                type="time"
                                value={slot.start}
                                onChange={(event) => updateAvailabilityRangeField(entry.user_id, 'slots', slot.id, 'start', event.target.value)}
                              />
                              <input
                                type="time"
                                value={slot.end}
                                onChange={(event) => updateAvailabilityRangeField(entry.user_id, 'slots', slot.id, 'end', event.target.value)}
                              />
                              <button type="button" className="button button-small button-link-delete" onClick={() => removeAvailabilityRange(entry.user_id, 'slots', slot.id)}>
                                Quitar
                              </button>
                            </div>
                          ))}
                          {!entry.slots.length ? <p className="wpss-collections__hint">Sin rangos disponibles definidos.</p> : null}
                        </div>
                      </div>

                      <div className="wpss-project-rehearsals__availability-section">
                        <div className="wpss-project-rehearsals__subheader">
                          <strong>No disponible por rangos</strong>
                          <button type="button" className="button button-small" onClick={() => addAvailabilityRange(entry.user_id, 'unavailable_slots')}>
                            Añadir bloqueo
                          </button>
                        </div>
                        <div className="wpss-project-rehearsals__slot-list">
                          {entry.unavailable_slots.map((slot) => (
                            <div key={slot.id} className="wpss-project-rehearsals__slot-row">
                              <select value={slot.day} onChange={(event) => updateAvailabilityRangeField(entry.user_id, 'unavailable_slots', slot.id, 'day', event.target.value)}>
                                {DAY_OPTIONS.filter((option) => !entry.blocked_days.includes(option.value)).map((option) => (
                                  <option key={option.value} value={option.value}>{option.label}</option>
                                ))}
                              </select>
                              <input
                                type="time"
                                value={slot.start}
                                onChange={(event) => updateAvailabilityRangeField(entry.user_id, 'unavailable_slots', slot.id, 'start', event.target.value)}
                              />
                              <input
                                type="time"
                                value={slot.end}
                                onChange={(event) => updateAvailabilityRangeField(entry.user_id, 'unavailable_slots', slot.id, 'end', event.target.value)}
                              />
                              <button type="button" className="button button-small button-link-delete" onClick={() => removeAvailabilityRange(entry.user_id, 'unavailable_slots', slot.id)}>
                                Quitar
                              </button>
                            </div>
                          ))}
                          {!entry.unavailable_slots.length ? <p className="wpss-collections__hint">Sin bloqueos parciales definidos.</p> : null}
                        </div>
                      </div>
                    </article>
                  ))}
                </div>
              )}

              <div className="wpss-project-rehearsals__recommendations">
                {recommendedSlots.map((slot, index) => (
                  <article key={`${slot.day}-${slot.start}-${slot.end}-${index}`} className="wpss-project-rehearsals__recommendation">
                    <strong>{formatSlotLabel(slot)}</strong>
                    <span>{slot.member_count} integrante(s) disponibles · {Math.round(slot.duration_minutes / 60 * 10) / 10} h</span>
                    <small>{slot.member_names.join(' · ')}</small>
                    <button type="button" className="button button-small" onClick={() => handleScheduleRecommendedSlot(slot)}>
                      Crear propuesta
                    </button>
                  </article>
                ))}
                {!recommendedSlots.length ? (
                  <p className="wpss-empty">Todavía no hay coincidencias suficientes para sugerir una ventana de ensayo.</p>
                ) : null}
              </div>
            </section>

            <section className="wpss-project-rehearsals__panel">
              <div className="wpss-project-rehearsals__panel-header">
                <div>
                  <h2>Calendario</h2>
                  <p>Propón fechas, deja que el grupo vote y sincroniza los ensayos confirmados con Google Calendar para enviar invitaciones y recordatorios.</p>
                </div>
              </div>

              <div className={`wpss-project-rehearsals__google-status ${googleCalendar.ready ? 'is-ready' : 'is-warning'}`}>
                <div>
                  <strong>Google Calendar</strong>
                  <p>{googleCalendar.status_message || 'Revisa el estado de la integración antes de sincronizar ensayos.'}</p>
                  <small>Drive y Calendar ahora usan autorizaciones separadas. Este bloque ya no depende de Mi Drive para conceder scopes de calendario.</small>
                  {googleCalendar.account_email ? (
                    <small>Cuenta conectada: {googleCalendar.account_email}</small>
                  ) : null}
                  {!googleCalendar.ready && googleCalendar.missing_scope_labels?.length ? (
                    <small>Permisos pendientes: {googleCalendar.missing_scope_labels.join(' · ')}</small>
                  ) : null}
                  {googleCalendar.last_error ? (
                    <small>Último error OAuth: {googleCalendar.last_error}</small>
                  ) : null}
                  {googleCalendar.calendar_probe_message ? (
                    <small>Prueba Calendar API: {googleCalendar.calendar_probe_message}</small>
                  ) : null}
                </div>
                <div className="wpss-project-rehearsals__google-status-actions">
                  <button type="button" className="button button-small button-secondary" onClick={() => loadProject(activeProjectId)} disabled={detailLoading}>
                    {detailLoading ? 'Actualizando…' : 'Actualizar estado'}
                  </button>
                  {googleCalendar.connect_url ? (
                    <a className="button button-small" href={connectCalendarUrl}>
                      {googleCalendar.connected ? 'Reconectar Google Calendar' : 'Conectar Google Calendar'}
                    </a>
                  ) : null}
                  {oauthSettingsUrl && oauthSettingsUrl !== '#' ? (
                    <a className="button button-small button-secondary" href={oauthSettingsUrl}>
                      {oauthSettingsLabel}
                    </a>
                  ) : null}
                </div>
              </div>

              <div className="wpss-project-rehearsals__google-diagnostics">
                <article className="wpss-project-rehearsals__google-card">
                  <strong>Estado de la cuenta</strong>
                  <span>Configuración OAuth: {googleCalendar.configured ? 'Lista' : 'Faltante'}</span>
                  <span>Conexión Google: {googleCalendar.connected ? 'Conectada' : 'Sin conectar'}</span>
                  <span>Access token: {googleCalendar.has_access_token ? 'Sí' : 'No'}</span>
                  <span>Refresh token: {googleCalendar.has_refresh_token ? 'Sí' : 'No'}</span>
                  <span>Origen credenciales: {googleCalendar.credentials_source === 'user' ? 'Perfil de usuario' : 'Configuración global'}</span>
                  {googleCalendar.client_id_hint ? (
                    <span>Cliente OAuth activo: {googleCalendar.client_id_hint}</span>
                  ) : null}
                </article>
                <article className="wpss-project-rehearsals__google-card">
                  <strong>Permisos requeridos</strong>
                  {(googleCalendar.required_scope_labels || []).length ? (
                    googleCalendar.required_scope_labels.map((label) => (
                      <span key={`required-${label}`}>{label}</span>
                    ))
                  ) : (
                    <span>Sin datos de permisos.</span>
                  )}
                </article>
                <article className="wpss-project-rehearsals__google-card">
                  <strong>Permisos concedidos</strong>
                  {(googleCalendar.granted_scope_labels || []).length ? (
                    googleCalendar.granted_scope_labels.map((label) => (
                      <span key={`granted-${label}`}>{label}</span>
                    ))
                  ) : (
                    <span>Google todavía no devolvió scopes guardados.</span>
                  )}
                </article>
                <article className="wpss-project-rehearsals__google-card">
                  <strong>Diagnóstico técnico</strong>
                  <span>Motivo de reconexión: {googleCalendar.reconnect_reason || 'Ninguno'}</span>
                  <span>Prueba directa Calendar API: {googleCalendar.calendar_probe_ok ? 'OK' : 'Sin validar / fallida'}</span>
                  <span>Redirect URI: {googleCalendar.redirect_uri || 'No disponible'}</span>
                  <span>Authorized origin: {googleCalendar.authorized_origin || 'No disponible'}</span>
                </article>
              </div>

              <div className="wpss-project-rehearsals__sessions-layout">
                <aside className="wpss-project-rehearsals__sessions-list">
                  {calendarSessions.map((session) => (
                    <button
                      key={session.id}
                      type="button"
                      className={`wpss-project-rehearsals__session-card ${activeCalendarSessionId === session.id ? 'is-active' : ''}`}
                      onClick={() => setActiveCalendarSessionId(session.id)}
                    >
                      <strong>{session.focus || 'Propuesta sin título'}</strong>
                      <span>{session.scheduled_for || 'Sin fecha'}{session.start_time ? ` · ${session.start_time}` : ''}</span>
                      <small>{SESSION_STATUS_LABELS[session.status] || 'Propuesta'} · {renderVoteSummary(session.votes)}</small>
                    </button>
                  ))}
                  {!calendarSessions.length ? <p className="wpss-empty">Aún no hay propuestas ni ensayos pendientes por confirmar.</p> : null}
                </aside>

                <div className="wpss-project-rehearsals__session-editor">
                  {activeCalendarSession ? (
                    <div className="wpss-project-rehearsals__session-form">
                      <div className="wpss-project-rehearsals__session-actions">
                        <button type="button" className="button button-small button-secondary" onClick={() => handleNewSession({ status: 'proposed' })}>
                          Nueva propuesta
                        </button>
                        <button type="button" className="button button-small button-link-delete" onClick={() => handleRemoveSession(activeCalendarSession.id)}>
                          Eliminar
                        </button>
                      </div>

                      <div className="wpss-project-rehearsals__field-grid">
                        <label className="wpss-field">
                          <span>Fecha propuesta</span>
                          <input
                            type="date"
                            value={activeCalendarSession.scheduled_for}
                            onChange={(event) => updateSession(activeCalendarSession.id, { ...activeCalendarSession, scheduled_for: event.target.value })}
                          />
                        </label>
                        <label className="wpss-field">
                          <span>Inicio</span>
                          <input
                            type="time"
                            value={activeCalendarSession.start_time}
                            onChange={(event) => updateSession(activeCalendarSession.id, { ...activeCalendarSession, start_time: event.target.value })}
                          />
                        </label>
                        <label className="wpss-field">
                          <span>Fin</span>
                          <input
                            type="time"
                            value={activeCalendarSession.end_time}
                            onChange={(event) => updateSession(activeCalendarSession.id, { ...activeCalendarSession, end_time: event.target.value })}
                          />
                        </label>
                        <label className="wpss-field">
                          <span>Estado</span>
                          <select
                            value={activeCalendarSession.status}
                            onChange={(event) => updateSession(activeCalendarSession.id, { ...activeCalendarSession, status: event.target.value })}
                          >
                            {SESSION_STATUS_OPTIONS.filter((option) => option.value !== 'completed').map((option) => (
                              <option key={option.value} value={option.value}>{option.label}</option>
                            ))}
                          </select>
                        </label>
                      </div>

                      <div className="wpss-project-rehearsals__field-grid wpss-project-rehearsals__field-grid--double">
                        <label className="wpss-field">
                          <span>Lugar / sala</span>
                          <input
                            type="text"
                            value={activeCalendarSession.location}
                            onChange={(event) => updateSession(activeCalendarSession.id, { ...activeCalendarSession, location: event.target.value })}
                          />
                        </label>
                        <label className="wpss-field">
                          <span>Objetivo del ensayo</span>
                          <input
                            type="text"
                            value={activeCalendarSession.focus}
                            onChange={(event) => updateSession(activeCalendarSession.id, { ...activeCalendarSession, focus: event.target.value })}
                          />
                        </label>
                      </div>

                      <label className="wpss-field">
                        <span>Observaciones para la propuesta</span>
                        <textarea
                          rows="3"
                          value={activeCalendarSession.notes}
                          onChange={(event) => updateSession(activeCalendarSession.id, { ...activeCalendarSession, notes: event.target.value })}
                        />
                      </label>

                      <div className="wpss-project-rehearsals__consensus-banner">
                        <strong>{activeCalendarSession.consensus_reached ? 'Consenso alcanzado' : 'Consenso pendiente'}</strong>
                        <span>{renderVoteSummary(activeCalendarSession.votes)}</span>
                        <div className="wpss-project-rehearsals__consensus-actions">
                          {activeCalendarSession.calendar?.has_event && activeCalendarSession.calendar?.html_link ? (
                            <a className="button button-small" href={activeCalendarSession.calendar.html_link} target="_blank" rel="noreferrer">
                              Abrir evento
                            </a>
                          ) : null}
                          {activeCalendarSession.calendar?.ready && activeCalendarSession.calendar?.google_calendar_url ? (
                            <a
                              className="button button-small button-secondary"
                              href={activeCalendarSession.calendar.google_calendar_url}
                              target="_blank"
                              rel="noreferrer"
                            >
                              Abrir plantilla manual
                            </a>
                          ) : null}
                        </div>
                      </div>

                      {renderSyncStatus(activeCalendarSession) ? (
                        <p className="wpss-collections__hint">{renderSyncStatus(activeCalendarSession)}</p>
                      ) : null}
                      {activeCalendarSession.calendar?.sync_error ? (
                        <p className="wpss-error">{activeCalendarSession.calendar.sync_error}</p>
                      ) : null}

                      <div className="wpss-project-rehearsals__attendance">
                        <div className="wpss-project-rehearsals__attendance-header">
                          <strong>Votación del grupo</strong>
                          <span>Cuando todos votan sí, la propuesta puede quedar confirmada y lista para Google Calendar.</span>
                        </div>
                        <div className="wpss-project-rehearsals__attendance-list">
                          {activeCalendarSession.votes.map((item) => (
                            <div key={item.user_id} className="wpss-project-rehearsals__attendance-row">
                              <strong>{item.nombre}</strong>
                              <select
                                value={item.vote}
                                onChange={(event) => updateSession(activeCalendarSession.id, {
                                  ...activeCalendarSession,
                                  votes: activeCalendarSession.votes.map((vote) => (
                                    vote.user_id === item.user_id
                                      ? { ...vote, vote: event.target.value }
                                      : vote
                                  )),
                                })}
                              >
                                {VOTE_OPTIONS.map((option) => (
                                  <option key={option.value} value={option.value}>{option.label}</option>
                                ))}
                              </select>
                              <input
                                type="text"
                                placeholder="Comentario del voto"
                                value={item.comment}
                                onChange={(event) => updateSession(activeCalendarSession.id, {
                                  ...activeCalendarSession,
                                  votes: activeCalendarSession.votes.map((vote) => (
                                    vote.user_id === item.user_id
                                      ? { ...vote, comment: event.target.value }
                                      : vote
                                  )),
                                })}
                              />
                            </div>
                          ))}
                        </div>
                      </div>

                      <div className="wpss-project-rehearsals__status-actions">
                        <button type="button" className="button button-small" onClick={() => updateSessionStatus(activeCalendarSession.id, 'voting')}>
                          Pasar a votación
                        </button>
                        <button type="button" className="button button-small" onClick={() => updateSessionStatus(activeCalendarSession.id, 'confirmed')}>
                          Confirmar manualmente
                        </button>
                        <button type="button" className="button button-small" onClick={() => updateSessionStatus(activeCalendarSession.id, 'completed')}>
                          Marcar realizado
                        </button>
                        <button type="button" className="button button-small button-link-delete" onClick={() => updateSessionStatus(activeCalendarSession.id, 'cancelled')}>
                          Cancelar
                        </button>
                        {activeCalendarSession.calendar?.can_sync ? (
                          <button
                            type="button"
                            className="button button-small button-primary"
                            onClick={() => handleSyncCalendar(activeCalendarSession.id)}
                            disabled={calendarSyncingId === activeCalendarSession.id}
                          >
                            {calendarSyncingId === activeCalendarSession.id ? 'Sincronizando…' : getCalendarActionLabel(activeCalendarSession)}
                          </button>
                        ) : null}
                        {!activeCalendarSession.calendar?.can_sync && !googleCalendar.ready && connectCalendarUrl ? (
                          <a className="button button-small button-secondary" href={connectCalendarUrl}>
                            {googleCalendar.connected ? 'Reconectar Google Calendar' : 'Conectar Google Calendar'}
                          </a>
                        ) : null}
                      </div>
                    </div>
                  ) : (
                    <p className="wpss-empty">Selecciona una propuesta para votar o crear un nuevo ensayo desde una ventana sugerida.</p>
                  )}
                </div>
              </div>
            </section>

            <section className="wpss-project-rehearsals__panel">
              <div className="wpss-project-rehearsals__panel-header">
                <div>
                  <h2>Bitácora</h2>
                  <p>Historial de ensayos realizados con asistencia, temas trabajados y observaciones del progreso del grupo.</p>
                </div>
              </div>

              <div className="wpss-project-rehearsals__sessions-layout">
                <aside className="wpss-project-rehearsals__sessions-list">
                  {logbookSessions.map((session) => (
                    <button
                      key={session.id}
                      type="button"
                      className={`wpss-project-rehearsals__session-card ${activeLogbookSessionId === session.id ? 'is-active' : ''}`}
                      onClick={() => setActiveLogbookSessionId(session.id)}
                    >
                      <strong>{session.focus || 'Ensayo realizado'}</strong>
                      <span>{session.scheduled_for || 'Sin fecha'}{session.start_time ? ` · ${session.start_time}` : ''}</span>
                      <small>{session.location || 'Sin sala definida'} · {session.reviewed_items.length} tema(s)</small>
                    </button>
                  ))}
                  {!logbookSessions.length ? <p className="wpss-empty">Todavía no hay ensayos realizados en la bitácora.</p> : null}
                </aside>

                <div className="wpss-project-rehearsals__session-editor">
                  {activeLogbookSession ? (
                    <div className="wpss-project-rehearsals__session-form">
                      <div className="wpss-project-rehearsals__session-actions">
                        <button type="button" className="button button-small" onClick={() => updateSessionStatus(activeLogbookSession.id, 'confirmed')}>
                          Regresar a calendario
                        </button>
                      </div>

                      <div className="wpss-project-rehearsals__field-grid wpss-project-rehearsals__field-grid--double">
                        <label className="wpss-field">
                          <span>Lugar / sala</span>
                          <input
                            type="text"
                            value={activeLogbookSession.location}
                            onChange={(event) => updateSession(activeLogbookSession.id, { ...activeLogbookSession, location: event.target.value })}
                          />
                        </label>
                        <label className="wpss-field">
                          <span>Objetivo / enfoque</span>
                          <input
                            type="text"
                            value={activeLogbookSession.focus}
                            onChange={(event) => updateSession(activeLogbookSession.id, { ...activeLogbookSession, focus: event.target.value })}
                          />
                        </label>
                      </div>

                      <label className="wpss-field">
                        <span>Temas trabajados</span>
                        <textarea
                          rows="4"
                          value={activeLogbookSession.reviewed_items.join('\n')}
                          onChange={(event) => updateSession(activeLogbookSession.id, {
                            ...activeLogbookSession,
                            reviewed_items: event.target.value
                              .split(/\r\n|\r|\n/)
                              .map((item) => item.trim())
                              .filter(Boolean),
                          })}
                        />
                      </label>

                      <label className="wpss-field">
                        <span>Observaciones del ensayo</span>
                        <textarea
                          rows="4"
                          value={activeLogbookSession.notes}
                          onChange={(event) => updateSession(activeLogbookSession.id, { ...activeLogbookSession, notes: event.target.value })}
                        />
                      </label>

                      <div className="wpss-project-rehearsals__attendance">
                        <div className="wpss-project-rehearsals__attendance-header">
                          <strong>Asistencia real</strong>
                          <span>Esto alimenta el historial del proyecto y el seguimiento de compromiso.</span>
                        </div>
                        <div className="wpss-project-rehearsals__attendance-list">
                          {activeLogbookSession.attendance.map((item) => (
                            <div key={item.user_id} className="wpss-project-rehearsals__attendance-row">
                              <strong>{item.nombre}</strong>
                              <select
                                value={item.status}
                                onChange={(event) => updateSession(activeLogbookSession.id, {
                                  ...activeLogbookSession,
                                  attendance: activeLogbookSession.attendance.map((attendance) => (
                                    attendance.user_id === item.user_id
                                      ? { ...attendance, status: event.target.value }
                                      : attendance
                                  )),
                                })}
                              >
                                {ATTENDANCE_STATUS_OPTIONS.map((option) => (
                                  <option key={option.value} value={option.value}>{option.label}</option>
                                ))}
                              </select>
                              <input
                                type="text"
                                placeholder="Comentario breve"
                                value={item.comment}
                                onChange={(event) => updateSession(activeLogbookSession.id, {
                                  ...activeLogbookSession,
                                  attendance: activeLogbookSession.attendance.map((attendance) => (
                                    attendance.user_id === item.user_id
                                      ? { ...attendance, comment: event.target.value }
                                      : attendance
                                  )),
                                })}
                              />
                            </div>
                          ))}
                        </div>
                      </div>
                    </div>
                  ) : (
                    <p className="wpss-empty">Los ensayos realizados aparecerán aquí con su bitácora y asistencia.</p>
                  )}
                </div>
              </div>
            </section>

            <section className="wpss-project-rehearsals__panel">
              <div className="wpss-project-rehearsals__panel-header">
                <div>
                  <h2>Seguimiento del grupo</h2>
                  <p>Indicadores rápidos de asistencia sobre ensayos ya realizados.</p>
                </div>
              </div>
              <div className="wpss-project-rehearsals__leaderboard">
                {attendanceLeaderboard.map((item) => (
                  <article key={item.user_id} className="wpss-project-rehearsals__leaderboard-item">
                    <strong>{item.nombre}</strong>
                    <span>
                      {item.completed
                        ? `${item.attended}/${item.completed} ensayos · ${item.rate}% de asistencia`
                        : 'Sin ensayos realizados todavía'}
                    </span>
                  </article>
                ))}
              </div>
            </section>
          </div>
        ) : null}
      </div>
    </section>
  )
}
