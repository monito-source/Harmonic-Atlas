export function createEmptySegment() {
  return { texto: '', acorde: '', midi_clips: [], comentarios: [] }
}

export function createEmptyVerse(order, sectionId) {
  return {
    orden: order || 1,
    segmentos: [createEmptySegment()],
    comentario: '',
    comentarios: [],
    evento_armonico: null,
    section_id: sectionId || '',
    fin_de_estrofa: false,
    nombre_estrofa: '',
    instrumental: false,
    midi_clips: [],
  }
}

export function createSection(nombre = '', index = 0) {
  const label = nombre && nombre.trim() ? nombre.trim().slice(0, 64) : `Sección ${index + 1}`
  return {
    id: `sec-${Date.now()}-${Math.floor(Math.random() * 10000)}`,
    nombre: label,
    midi_clips: [],
    comentarios: [],
  }
}

export function createEmptySong() {
  return {
    id: null,
    autor_id: null,
    autor_nombre: '',
    es_reversion: false,
    reversion_origen_id: null,
    reversion_origen_titulo: '',
    reversion_raiz_id: null,
    reversion_raiz_titulo: '',
    reversion_autor_origen_id: null,
    reversion_autor_origen_nombre: '',
    estado_transcripcion: 'sin_iniciar',
    estado_transcripcion_label: 'Sin iniciar',
    estado_ensayo: 'sin_ensayar',
    estado_ensayo_label: 'No ensayada',
    titulo: '',
    bpm: 120,
    tonica: '',
    campo_armonico: '',
    campo_armonico_predominante: '',
    ficha_autores: '',
    ficha_anio: '',
    ficha_pais: '',
    ficha_estado_legal: '',
    ficha_licencia: '',
    ficha_fuente_verificacion: '',
    ficha_incompleta: false,
    ficha_incompleta_motivo: '',
    prestamos: [],
    modulaciones: [],
    versos: [],
    secciones: [createSection('', 0)],
    tiene_prestamos: false,
    tiene_modulaciones: false,
    colecciones: [],
    tags: [],
    estructura: [],
    estructuraPersonalizada: true,
  }
}

export function buildInitialState(wpData, view = 'dashboard') {
  const camposLibrary = Array.isArray(wpData?.camposArmonicos) ? wpData.camposArmonicos : []
  const chordsLibrary = Array.isArray(wpData?.chordsLibrary) ? wpData.chordsLibrary : []
  const chordsConfig = wpData?.chordsConfig && typeof wpData.chordsConfig === 'object'
    ? wpData.chordsConfig
    : { paradigms: [], qualities: {} }
  const camposNames = Array.isArray(wpData?.camposArmonicosNombres)
    ? wpData.camposArmonicosNombres
    : camposLibrary
        .filter((campo) => campo && campo.activo)
        .flatMap((campo) => {
          const labels = []
          if (campo.nombre) labels.push(campo.nombre)
          if (Array.isArray(campo.aliases)) {
            campo.aliases.forEach((alias) => {
              if (alias) labels.push(alias)
            })
          }
          return labels.length ? labels : [campo.slug || '']
        })

  return {
    view,
    activeTab: view === 'public' ? 'reading' : 'editor',
    songs: [],
    filters: {
      tonica: '',
      con_prestamos: '',
      con_modulaciones: '',
      coleccion: '',
      tag: '',
    },
    pagination: {
      page: 1,
      totalPages: 1,
      totalItems: 0,
    },
    listLoading: false,
    songLoading: false,
    saving: false,
    feedback: null,
    error: null,
    selectedSongId: null,
    editingSong: createEmptySong(),
    campos: {
      library: camposLibrary,
      draft: JSON.parse(JSON.stringify(camposLibrary || [])),
      saving: false,
      feedback: null,
      error: null,
    },
    chords: {
      library: chordsLibrary,
      draft: JSON.parse(JSON.stringify(chordsLibrary || [])),
      saving: false,
      feedback: null,
      error: null,
    },
    chordsConfig: {
      library: chordsConfig,
      draft: JSON.parse(JSON.stringify(chordsConfig || {})),
      saving: false,
      feedback: null,
      error: null,
    },
    camposNames,
    segmentSelection: {
      verse: null,
      segment: null,
      selectionStart: null,
      selectionEnd: null,
    },
    songTags: [],
    collections: {
      items: [],
      loading: false,
      error: null,
      feedback: null,
      activeId: null,
      active: null,
      saving: false,
      deleting: false,
      detailLoading: false,
      catalog: [],
      catalogLoading: false,
    },
    readingMode: 'stacked',
    readingFollowStructure: false,
    readingShowNotes: true,
    readingDoubleColumn: false,
    readingInstrument: 'guitar',
    readingTransposeTarget: 'concert',
    readingQueue: {
      ids: [],
      index: 0,
      coleccionId: null,
      nombre: '',
    },
    ui: {
      selectedSectionId: null,
      collapsedVerses: new Set(),
    },
  }
}

export function reducer(state, action) {
  switch (action.type) {
    case 'SET_STATE':
      return { ...state, ...action.payload }
    case 'SET_EDITING_SONG':
      return { ...state, editingSong: action.payload }
    default:
      return state
  }
}
