( function() {
    if ( 'undefined' === typeof window.WPSS ) {
        return;
    }

    const data = window.WPSS;

    const SECTION_PREFIX = 'sec-';
    let sectionSeed = Date.now();
    let sectionCounter = 0;

    document.addEventListener( 'DOMContentLoaded', () => {
        const container = document.getElementById( 'wpss-cancion-app' );
        if ( ! container ) {
            return;
        }

        const state = {
            view: container.dataset.view || 'dashboard',
            activeTab: 'editor',
            songs: [],
            filters: {
                tonica: '',
                con_prestamos: '',
                con_modulaciones: '',
                coleccion: '',
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
                library: Array.isArray( data.camposArmonicos ) ? data.camposArmonicos : [],
                draft: deepClone( Array.isArray( data.camposArmonicos ) ? data.camposArmonicos : [] ),
                saving: false,
                feedback: null,
                error: null,
            },
            camposNames: Array.isArray( data.camposArmonicosNombres ) ? data.camposArmonicosNombres : [],
            segmentSelection: {
                verse: null,
                segment: null,
                selectionStart: null,
                selectionEnd: null,
            },
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
            readingMode: 'inline',
            readingQueue: {
                ids: [],
                index: 0,
                coleccionId: null,
                nombre: '',
            },
        };

        const api = {
            async listSongs( overrides = {}, includeFilters = true ) {
                const params = new URLSearchParams();
                const page = overrides.page ? overrides.page : state.pagination.page;
                const perPage = overrides.per_page ? overrides.per_page : 20;

                params.set( 'page', page );
                params.set( 'per_page', perPage );

                if ( includeFilters ) {
                    if ( state.filters.tonica ) {
                        params.set( 'tonica', state.filters.tonica );
                    }
                    if ( '' !== state.filters.con_prestamos ) {
                        params.set( 'con_prestamos', state.filters.con_prestamos );
                    }
                    if ( '' !== state.filters.con_modulaciones ) {
                        params.set( 'con_modulaciones', state.filters.con_modulaciones );
                    }
                    if ( state.filters.coleccion ) {
                        params.set( 'coleccion', state.filters.coleccion );
                    }
                }

                Object.keys( overrides ).forEach( ( key ) => {
                    if ( [ 'page', 'per_page' ].includes( key ) ) {
                        return;
                    }

                    const value = overrides[ key ];
                    if ( null === value || 'undefined' === typeof value ) {
                        return;
                    }

                    if ( '' === value ) {
                        params.delete( key );
                        return;
                    }

                    params.set( key, value );
                } );

                return request( `canciones?${ params.toString() }` );
            },
            async getSong( id ) {
                return request( `cancion/${ id }` );
            },
            async saveSong( payload ) {
                return request( 'cancion', {
                    method: 'POST',
                    body: payload,
                } );
            },
            async listCampos() {
                return request( 'campos-armonicos' );
            },
            async saveCampos( campos ) {
                return request( 'campos-armonicos', {
                    method: 'POST',
                    body: { campos },
                } );
            },
            async listCollections() {
                return request( 'colecciones' );
            },
            async getCollection( id ) {
                return request( `coleccion/${ id }` );
            },
            async saveCollection( payload ) {
                return request( 'coleccion', {
                    method: 'POST',
                    body: payload,
                } );
            },
            async deleteCollection( id ) {
                return request( `coleccion/${ id }`, {
                    method: 'DELETE',
                } );
            },
        };

        if ( 'new' === state.view ) {
            state.feedback = null;
            state.activeTab = 'editor';
        }

        normalizeVerseOrder();
        refreshCampoNames();
        render();
        loadSongs();
        loadCollections();

        container.addEventListener( 'click', handleClick );
        container.addEventListener( 'input', handleInput );
        container.addEventListener( 'change', handleChange );
        container.addEventListener( 'focusin', updateSegmentSelectionFromEvent );
        container.addEventListener( 'keyup', updateSegmentSelectionFromEvent );
        container.addEventListener( 'mouseup', updateSegmentSelectionFromEvent );

        async function request( path, options = {} ) {
            const {
                method = 'GET',
                body = null,
                asJson = true,
            } = options;

            const headers = {
                'X-WP-Nonce': data.wpRestNonce,
                'X-WPSS-Nonce': data.wpssNonce,
            };

            const config = {
                method,
                credentials: 'same-origin',
                headers,
            };

            if ( null !== body ) {
                if ( asJson ) {
                    config.body = JSON.stringify( body );
                    config.headers[ 'Content-Type' ] = 'application/json';
                } else {
                    config.body = body;
                }
            }

            const response = await fetch( `${ data.restUrl }${ path }`, config );
            const payload = await parseResponse( response );

            if ( ! response.ok ) {
                const error = new Error( `REST ${ response.status }` );
                error.status = response.status;
                error.payload = payload;
                throw error;
            }

            return {
                data: payload,
                headers: response.headers,
            };
        }

        async function parseResponse( response ) {
            const text = await response.text();
            if ( ! text ) {
                return null;
            }

            try {
                return JSON.parse( text );
            } catch ( error ) {
                return text;
            }
        }

        function deepClone( value ) {
            try {
                return JSON.parse( JSON.stringify( value ) );
            } catch ( error ) {
                return Array.isArray( value ) ? [] : {};
            }
        }

        function generateSectionId() {
            sectionCounter += 1;
            return `${ SECTION_PREFIX }${ sectionSeed }-${ sectionCounter }`;
        }

        function getDefaultSectionName( index ) {
            const position = index + 1;
            return `Sección ${ position }`;
        }

        function createSection( nombre = '', index = 0 ) {
            const label = nombre && nombre.trim() ? nombre.trim().slice( 0, 64 ) : getDefaultSectionName( index );
            return {
                id: generateSectionId(),
                nombre: label,
            };
        }

        function normalizeSectionsFromApi( secciones ) {
            if ( ! Array.isArray( secciones ) ) {
                return [];
            }

            const used = new Set();

            return secciones.map( ( seccion, index ) => {
                let id = seccion && seccion.id ? String( seccion.id ) : generateSectionId();
                let nombre = seccion && seccion.nombre ? String( seccion.nombre ) : '';

                id = id.trim();
                nombre = nombre.trim();

                if ( ! id ) {
                    id = generateSectionId();
                }

                while ( used.has( id ) ) {
                    id = generateSectionId();
                }

                used.add( id );

                if ( ! nombre ) {
                    nombre = getDefaultSectionName( index );
                }

                return {
                    id,
                    nombre: nombre.slice( 0, 64 ),
                };
            } );
        }

        function createEmptySong() {
            return {
                id: null,
                titulo: '',
                tonica: '',
                campo_armonico: '',
                campo_armonico_predominante: '',
                prestamos: [],
                modulaciones: [],
                versos: [],
                secciones: [ createSection( '', 0 ) ],
                tiene_prestamos: false,
                tiene_modulaciones: false,
                colecciones: [],
            };
        }

        function createEmptySegment() {
            return { texto: '', acorde: '' };
        }

        function createEmptyVerse( orden, sectionId ) {
            return {
                orden: orden || 1,
                segmentos: [ createEmptySegment() ],
                comentario: '',
                evento_armonico: null,
                section_id: sectionId || '',
                fin_de_estrofa: false,
                nombre_estrofa: '',
            };
        }

        function padEndSafe( value, length ) {
            let result = String( value );
            if ( 'function' === typeof result.padEnd ) {
                return result.padEnd( length, ' ' );
            }

            while ( result.length < length ) {
                result += ' ';
            }

            return result;
        }

        function formatSegmentsForStackedMode( segmentos ) {
            if ( ! Array.isArray( segmentos ) || ! segmentos.length ) {
                return { chords: '', lyrics: '' };
            }

            const chordsParts = [];
            const lyricsParts = [];

            segmentos.forEach( ( segmento, index ) => {
                const texto = segmento && segmento.texto ? segmento.texto : '';
                const acorde = segmento && segmento.acorde ? segmento.acorde : '';
                const width = Math.max( texto.length, acorde.length );
                const padding = index === segmentos.length - 1 ? width : width + 2;

                chordsParts.push( acorde ? padEndSafe( acorde, padding ) : padEndSafe( '', padding ) );
                lyricsParts.push( padEndSafe( texto, padding ) );
            } );

            return {
                chords: chordsParts.join( '' ).trimEnd(),
                lyrics: lyricsParts.join( '' ).trimEnd(),
            };
        }

        function createEmptyCampo() {
            return {
                slug: '',
                nombre: '',
                sistema: 'otro',
                intervalos: '',
                descripcion: '',
                notas: '',
                activo: true,
            };
        }

        function createEmptyCollection() {
            return {
                id: null,
                nombre: '',
                descripcion: '',
                orden: [],
                items: [],
            };
        }

        function normalizeSongCollections( colecciones ) {
            if ( ! Array.isArray( colecciones ) ) {
                return [];
            }

            const seen = new Set();

            return colecciones.reduce( ( acc, item ) => {
                if ( ! item ) {
                    return acc;
                }

                const id = parseInt( item.id || item.term_id || item, 10 );
                if ( Number.isNaN( id ) || id <= 0 || seen.has( id ) ) {
                    return acc;
                }

                seen.add( id );

                acc.push( {
                    id,
                    nombre: item.nombre || item.name || '',
                    descripcion: item.descripcion || item.description || '',
                } );

                return acc;
            }, [] );
        }

        function toggleSongCollection( termId, checked ) {
            const id = parseInt( termId, 10 );
            if ( Number.isNaN( id ) || id <= 0 ) {
                return;
            }

            if ( ! Array.isArray( state.editingSong.colecciones ) ) {
                state.editingSong.colecciones = [];
            }

            const existingIndex = state.editingSong.colecciones.findIndex( ( item ) => item.id === id );

            if ( checked ) {
                if ( existingIndex === -1 ) {
                    const collection = state.collections.items.find( ( item ) => item.id === id );
                    state.editingSong.colecciones.push( {
                        id,
                        nombre: collection ? collection.nombre : '',
                        descripcion: collection ? collection.descripcion : '',
                    } );
                }
            } else if ( existingIndex > -1 ) {
                state.editingSong.colecciones.splice( existingIndex, 1 );
            }
        }

        function normalizeVerseOrder() {
            if ( ! Array.isArray( state.editingSong.versos ) ) {
                state.editingSong.versos = [];
            } else {
                state.editingSong.versos.sort( ( a, b ) => ( a.orden || 0 ) - ( b.orden || 0 ) );
                state.editingSong.versos.forEach( ( verso, index ) => {
                    verso.orden = index + 1;
                    if ( ! Array.isArray( verso.segmentos ) || ! verso.segmentos.length ) {
                        verso.segmentos = [ createEmptySegment() ];
                    } else {
                        verso.segmentos = verso.segmentos.map( ( segmento ) => ( {
                            texto: segmento && segmento.texto ? segmento.texto : '',
                            acorde: segmento && segmento.acorde ? segmento.acorde : '',
                        } ) );
                    }

                    if ( ! verso.evento_armonico || 'object' !== typeof verso.evento_armonico ) {
                        verso.evento_armonico = null;
                    }

                    verso.section_id = verso.section_id ? String( verso.section_id ) : '';
                    verso.fin_de_estrofa = !! verso.fin_de_estrofa;
                    verso.nombre_estrofa = verso.nombre_estrofa ? String( verso.nombre_estrofa ).slice( 0, 64 ) : '';
                } );
            }

            ensureSectionsIntegrity();
        }

        function ensureSectionsIntegrity() {
            if ( ! state.editingSong ) {
                return;
            }

            let secciones = Array.isArray( state.editingSong.secciones ) ? state.editingSong.secciones : [];
            const used = new Set();

            secciones = secciones.map( ( seccion, index ) => {
                let id = seccion && seccion.id ? String( seccion.id ).trim() : '';
                let nombre = seccion && seccion.nombre ? String( seccion.nombre ).trim() : '';

                if ( ! id ) {
                    id = generateSectionId();
                }

                while ( used.has( id ) ) {
                    id = generateSectionId();
                }

                used.add( id );

                if ( ! nombre ) {
                    nombre = getDefaultSectionName( index );
                }

                return {
                    id,
                    nombre: nombre.slice( 0, 64 ),
                };
            } );

            if ( ! secciones.length ) {
                secciones = [ createSection( '', 0 ) ];
            }

            state.editingSong.secciones = secciones;

            const ids = secciones.map( ( seccion ) => seccion.id );
            const fallbackId = ids[ 0 ];

            if ( Array.isArray( state.editingSong.versos ) ) {
                state.editingSong.versos.forEach( ( verso ) => {
                    if ( ! verso.section_id || ! ids.includes( verso.section_id ) ) {
                        verso.section_id = fallbackId;
                    }
                } );
            }

            syncLegacyFromSections();
        }

        function syncLegacyFromSections() {
            if ( ! state.editingSong || ! Array.isArray( state.editingSong.versos ) ) {
                return;
            }

            const sections = Array.isArray( state.editingSong.secciones ) ? state.editingSong.secciones : [];

            state.editingSong.versos.forEach( ( verso ) => {
                verso.fin_de_estrofa = false;
                verso.nombre_estrofa = '';
            } );

            if ( ! sections.length ) {
                return;
            }

            sections.forEach( ( section, index ) => {
                const versosSeccion = state.editingSong.versos.filter( ( verso ) => verso.section_id === section.id );
                if ( ! versosSeccion.length ) {
                    return;
                }

                const ultimo = versosSeccion[ versosSeccion.length - 1 ];
                if ( ! ultimo ) {
                    return;
                }

                if ( index < sections.length - 1 ) {
                    ultimo.fin_de_estrofa = true;
                    ultimo.nombre_estrofa = sections[ index + 1 ].nombre || '';
                }
            } );
        }

        function addSection() {
            if ( ! Array.isArray( state.editingSong.secciones ) ) {
                state.editingSong.secciones = [];
            }

            const index = state.editingSong.secciones.length;
            state.editingSong.secciones.push( {
                id: generateSectionId(),
                nombre: getDefaultSectionName( index ),
            } );

            ensureSectionsIntegrity();
        }

        function removeSection( index ) {
            if ( Number.isNaN( index ) || ! Array.isArray( state.editingSong.secciones ) ) {
                return;
            }

            if ( state.editingSong.secciones.length <= 1 || ! state.editingSong.secciones[ index ] ) {
                return;
            }

            const removed = state.editingSong.secciones.splice( index, 1 )[ 0 ];
            const fallback = state.editingSong.secciones[ 0 ] ? state.editingSong.secciones[ 0 ].id : '';

            if ( removed && Array.isArray( state.editingSong.versos ) ) {
                state.editingSong.versos.forEach( ( verso ) => {
                    if ( verso.section_id === removed.id ) {
                        verso.section_id = fallback;
                    }
                } );
            }

            ensureSectionsIntegrity();
        }

        function reorderSection( index, direction ) {
            if ( Number.isNaN( index ) || ! direction || ! Array.isArray( state.editingSong.secciones ) ) {
                return;
            }

            const target = index + direction;
            if ( target < 0 || target >= state.editingSong.secciones.length ) {
                return;
            }

            const sections = state.editingSong.secciones;
            const temp = sections[ index ];
            sections[ index ] = sections[ target ];
            sections[ target ] = temp;

            ensureSectionsIntegrity();
        }

        function renameSection( index, value ) {
            if ( Number.isNaN( index ) || ! Array.isArray( state.editingSong.secciones ) || ! state.editingSong.secciones[ index ] ) {
                return;
            }

            state.editingSong.secciones[ index ].nombre = value ? value.slice( 0, 64 ) : '';
            ensureSectionsIntegrity();
        }

        function updateVerseSection( index, sectionId ) {
            if ( Number.isNaN( index ) || ! Array.isArray( state.editingSong.versos ) || ! state.editingSong.versos[ index ] ) {
                return;
            }

            state.editingSong.versos[ index ].section_id = sectionId;
            ensureSectionsIntegrity();
        }

        function refreshCampoNames() {
            if ( ! Array.isArray( state.campos.library ) ) {
                state.campos.library = [];
            }

            state.camposNames = state.campos.library
                .filter( ( campo ) => campo && campo.activo )
                .map( ( campo ) => ( campo.nombre ? campo.nombre : campo.slug || '' ) )
                .filter( Boolean );
        }

        function resetEditor() {
            state.editingSong = createEmptySong();
            state.selectedSongId = null;
            state.feedback = null;
            state.error = null;
            state.activeTab = 'editor';
            clearReadingQueue();
            normalizeVerseOrder();
            render();
        }

        function setFeedback( message, type = 'success' ) {
            state.feedback = { message, type };
            state.error = null;
        }

        function setError( message ) {
            state.error = message;
            state.feedback = null;
        }

        function setCamposFeedback( message, type = 'success' ) {
            state.campos.feedback = { message, type };
            state.campos.error = null;
        }

        function setCamposError( message ) {
            state.campos.error = message;
            state.campos.feedback = null;
        }
        function render() {
            container.innerHTML = `
                <div class="wpss-app-layout">
                    ${ renderListPanel() }
                    <section class="wpss-panel wpss-panel--editor">
                        ${ renderEditorHeader() }
                        ${ renderTabNav() }
                        ${ renderStatus() }
                        ${ renderActiveTab() }
                    </section>
                </div>
                ${ renderDatalist() }
            `;
        }

        function renderListPanel() {
            return `
                <section class="wpss-panel wpss-panel--list">
                    <header class="wpss-panel__header">
                        <div>
                            <h1>${ escapeHtml( data.strings.filtersTitle || 'Canciones registradas' ) }</h1>
                            <p class="wpss-panel__meta">${ state.pagination.totalItems } registros</p>
                        </div>
                        <div class="wpss-panel__actions">
                            <button class="button button-secondary" data-action="new-song">${ escapeHtml( data.strings.newSong ) }</button>
                        </div>
                    </header>
                    ${ renderFilters() }
                    ${ renderSongTable() }
                </section>
            `;
        }

        function renderEditorHeader() {
            const song = state.editingSong;
            const title = song.id ? ( song.titulo || data.strings.newSong ) : data.strings.newSong;
            const meta = song.id ? `ID ${ song.id }` : '—';

            const actions = [];
            if ( 'editor' === state.activeTab ) {
                actions.push( `<button class="button" data-action="reset-editor">${ escapeHtml( data.strings.newSong ) }</button>` );
                actions.push( `<button class="button button-primary" data-action="save-song" ${ state.saving ? 'disabled' : '' }>${ state.saving ? escapeHtml( data.strings.saving ) : escapeHtml( data.strings.saveSong ) }</button>` );
            } else if ( 'campos' === state.activeTab ) {
                actions.push( `<button class="button button-primary" data-action="save-campos" ${ state.campos.saving ? 'disabled' : '' }>${ state.campos.saving ? escapeHtml( data.strings.saving ) : escapeHtml( data.strings.saveSong ) }</button>` );
            }

            return `
                <header class="wpss-panel__header">
                    <div>
                        <h2>${ escapeHtml( title ) }</h2>
                        <p class="wpss-panel__meta">${ escapeHtml( meta ) }</p>
                    </div>
                    <div class="wpss-panel__actions">
                        ${ actions.join( '' ) }
                    </div>
                </header>
            `;
        }

        function renderTabNav() {
            const tabs = [
                { id: 'editor', label: data.strings.editorView || 'Editor' },
                { id: 'reading', label: data.strings.readingView || 'Vista de lectura' },
                { id: 'campos', label: data.strings.libraryView || 'Campos armónicos' },
                { id: 'colecciones', label: data.strings.collectionsTab || 'Colecciones' },
            ];

            return `
                <nav class="wpss-tab-nav">
                    ${ tabs.map( ( tab ) => `
                        <button type="button" class="button button-secondary ${ state.activeTab === tab.id ? 'is-active' : '' }" data-action="set-tab" data-tab="${ tab.id }">${ escapeHtml( tab.label ) }</button>
                    ` ).join( '' ) }
                </nav>
            `;
        }

        function renderStatus() {
            if ( 'colecciones' === state.activeTab ) {
                if ( state.collections.detailLoading ) {
                    return `<div class="notice notice-info"><p>${ escapeHtml( data.strings.collectionsLoading || 'Cargando colecciones…' ) }</p></div>`;
                }
                if ( state.collections.feedback ) {
                    return `<div class="notice notice-success"><p>${ escapeHtml( state.collections.feedback ) }</p></div>`;
                }
                if ( state.collections.error ) {
                    return `<div class="notice notice-error"><p>${ escapeHtml( state.collections.error ) }</p></div>`;
                }
                return '';
            }

            if ( 'campos' === state.activeTab ) {
                if ( state.campos.saving ) {
                    return `<div class="notice notice-info"><p>${ escapeHtml( data.strings.saving || 'Guardando…' ) }</p></div>`;
                }
                if ( state.campos.feedback ) {
                    const clazz = 'success' === state.campos.feedback.type ? 'notice-success' : 'notice-info';
                    return `<div class="notice ${ clazz }"><p>${ escapeHtml( state.campos.feedback.message ) }</p></div>`;
                }
                if ( state.campos.error ) {
                    return `<div class="notice notice-error"><p>${ escapeHtml( state.campos.error ) }</p></div>`;
                }
                return '';
            }

            if ( state.songLoading ) {
                return `<div class="notice notice-info"><p>${ escapeHtml( data.strings.loadingSong || 'Cargando canción…' ) }</p></div>`;
            }

            if ( state.feedback ) {
                const clazz = 'success' === state.feedback.type ? 'notice-success' : 'notice-info';
                return `<div class="notice ${ clazz }"><p>${ escapeHtml( state.feedback.message ) }</p></div>`;
            }

            if ( state.error ) {
                return `<div class="notice notice-error"><p>${ escapeHtml( state.error ) }</p></div>`;
            }

            return '';
        }

        function renderActiveTab() {
            switch ( state.activeTab ) {
            case 'reading':
                return renderReadingView();
            case 'campos':
                return renderCamposLibrary();
            case 'colecciones':
                return renderCollectionsManager();
            default:
                return renderEditorForm();
            }
        }

        function renderFilters() {
            const tonicas = Array.isArray( data.tonicas ) ? data.tonicas : [];
            const tonicaOptions = [ '<option value="">Todas</option>' ];
            tonicas.forEach( ( nota ) => {
                const selected = nota === state.filters.tonica ? 'selected' : '';
                tonicaOptions.push( `<option value="${ escapeAttr( nota ) }" ${ selected }>${ escapeHtml( nota ) }</option>` );
            } );

            const collectionOptions = [ `<option value="">${ escapeHtml( data.strings.collectionsAll || 'Todas' ) }</option>` ];
            state.collections.items.forEach( ( collection ) => {
                const selected = String( collection.id ) === String( state.filters.coleccion ) ? 'selected' : '';
                collectionOptions.push( `<option value="${ escapeAttr( collection.id ) }" ${ selected }>${ escapeHtml( collection.nombre ) }</option>` );
            } );

            const collectionDisabled = state.collections.loading ? 'disabled' : '';
            const viewDisabled = state.filters.coleccion ? '' : 'disabled';
            const collectionNote = state.collections.loading ? `<p class="wpss-filters__hint">${ escapeHtml( data.strings.collectionsLoading || 'Cargando colecciones…' ) }</p>` : '';

            return `
                <div class="wpss-filters">
                    <label>
                        <span>Tónica</span>
                        <select data-action="filter-tonica">
                            ${ tonicaOptions.join( '' ) }
                        </select>
                    </label>
                    <label>
                        <span>Préstamos</span>
                        <select data-action="filter-prestamos">
                            <option value="">Todos</option>
                            <option value="1" ${ '1' === String( state.filters.con_prestamos ) ? 'selected' : '' }>Con préstamos</option>
                            <option value="0" ${ '0' === String( state.filters.con_prestamos ) ? 'selected' : '' }>Sin préstamos</option>
                        </select>
                    </label>
                    <label>
                        <span>Modulaciones</span>
                        <select data-action="filter-modulaciones">
                            <option value="">Todos</option>
                            <option value="1" ${ '1' === String( state.filters.con_modulaciones ) ? 'selected' : '' }>Con modulaciones</option>
                            <option value="0" ${ '0' === String( state.filters.con_modulaciones ) ? 'selected' : '' }>Sin modulaciones</option>
                        </select>
                    </label>
                    <label class="wpss-filter--collection">
                        <span>${ escapeHtml( data.strings.collectionsFilter || 'Colección' ) }</span>
                        <select data-action="filter-coleccion" ${ collectionDisabled }>
                            ${ collectionOptions.join( '' ) }
                        </select>
                    </label>
                    <div class="wpss-filters__actions">
                        <button type="button" class="button button-secondary" data-action="view-collection" ${ viewDisabled }>${ escapeHtml( data.strings.collectionsView || 'Ver colección' ) }</button>
                    </div>
                </div>
                ${ collectionNote }
            `;
        }

        function renderSongTable() {
            if ( state.listLoading ) {
                return '<p class="wpss-loading">Cargando canciones…</p>';
            }

            if ( ! state.songs.length ) {
                return `<p class="wpss-empty">${ escapeHtml( data.strings.listEmpty ) }</p>`;
            }

            const rows = state.songs.map( ( song ) => {
                const selected = song.id === state.selectedSongId ? 'is-active' : '';
                const tonicaLabel = song.tonica || song.tonalidad || '';
                const campoLabel = song.campo_armonico ? ` · ${ escapeHtml( song.campo_armonico ) }` : '';
                const collectionChips = Array.isArray( song.colecciones ) && song.colecciones.length
                    ? `<div class="wpss-collection-chips">${ song.colecciones.map( ( col ) => `<span class="wpss-collection-chip">${ escapeHtml( col.nombre || `#${ col.id }` ) }</span>` ).join( '' ) }</div>`
                    : '';
                return `
                    <tr class="${ selected }" data-action="select-song" data-id="${ song.id }">
                        <td class="wpss-col-title">
                            <strong>${ escapeHtml( song.titulo ) }</strong>
                            <span class="wpss-sub">${ escapeHtml( tonicaLabel || '—' ) }${ campoLabel }</span>
                            ${ collectionChips }
                        </td>
                        <td>${ song.tiene_prestamos ? 'Sí' : 'No' }</td>
                        <td>${ song.tiene_modulaciones ? 'Sí' : 'No' }</td>
                        <td>${ song.conteo_versos || 0 }</td>
                    </tr>
                `;
            } );

            return `
                <div class="wpss-table-wrapper">
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>Canción</th>
                                <th>Préstamos</th>
                                <th>Modulaciones</th>
                                <th>Versos</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${ rows.join( '' ) }
                        </tbody>
                    </table>
                </div>
                ${ renderPagination() }
            `;
        }

        function renderPagination() {
            if ( state.pagination.totalPages <= 1 ) {
                return '';
            }

            const prevDisabled = state.pagination.page <= 1 ? 'disabled' : '';
            const nextDisabled = state.pagination.page >= state.pagination.totalPages ? 'disabled' : '';

            return `
                <div class="wpss-pagination">
                    <button class="button" data-action="page-prev" ${ prevDisabled }>Anterior</button>
                    <span>Página ${ state.pagination.page } de ${ state.pagination.totalPages }</span>
                    <button class="button" data-action="page-next" ${ nextDisabled }>Siguiente</button>
                </div>
            `;
        }

        function renderEditorForm() {
            const song = state.editingSong;
            const campos = state.camposNames || [];

            const campoOptions = [ '<option value="">Selecciona un modo</option>' ];
            campos.forEach( ( modo ) => {
                const selected = modo === song.campo_armonico ? 'selected' : '';
                campoOptions.push( `<option value="${ escapeAttr( modo ) }" ${ selected }>${ escapeHtml( modo ) }</option>` );
            } );

            return `
                <form class="wpss-editor" novalidate>
                    <div class="wpss-field-group">
                        <label>
                            <span>Título</span>
                            <input type="text" required data-model="general" data-field="titulo" value="${ escapeAttr( song.titulo ) }" />
                        </label>
                        <label>
                            <span>Tónica</span>
                            <input type="text" required data-model="general" data-field="tonica" value="${ escapeAttr( song.tonica ) }" list="wpss-tonicas" />
                        </label>
                    </div>
                    <div class="wpss-field-group">
                        <label>
                            <span>Campo armónico (modo)</span>
                            <select required data-model="general" data-field="campo_armonico">
                                ${ campoOptions.join( '' ) }
                            </select>
                        </label>
                        <label>
                            <span>Campo armónico predominante</span>
                            <textarea data-model="general" data-field="campo_armonico_predominante">${ escapeHtml( song.campo_armonico_predominante ) }</textarea>
                        </label>
                    </div>
                    <div class="wpss-field">
                        <span>${ escapeHtml( data.strings.collectionsLabel || 'Colecciones' ) }</span>
                        ${ renderSongCollectionsField() }
                    </div>

                    <div class="wpss-section">
                        <header>
                            <h3>Préstamos tonales</h3>
                            <button type="button" class="button button-secondary" data-action="add-prestamo">Añadir préstamo</button>
                        </header>
                        ${ renderPrestamos() }
                    </div>

                    <div class="wpss-section">
                        <header>
                            <h3>Modulaciones</h3>
                            <button type="button" class="button button-secondary" data-action="add-modulacion">Añadir modulación</button>
                        </header>
                        ${ renderModulaciones() }
                    </div>

                    <div class="wpss-section">
                        <header>
                            <h3>Secciones</h3>
                            <button type="button" class="button button-secondary" data-action="add-section">Añadir sección</button>
                        </header>
                        ${ renderSectionsManager() }
                    </div>

                    <div class="wpss-section">
                        <header>
                            <h3>Versos</h3>
                            <button type="button" class="button button-secondary" data-action="add-verso">Añadir verso</button>
                        </header>
                        ${ renderVersos() }
                    </div>
                </form>
            `;
        }

        function renderSongCollectionsField() {
            const assignedIds = new Set(
                ( Array.isArray( state.editingSong.colecciones ) ? state.editingSong.colecciones : [] )
                    .map( ( item ) => Number( item.id ) )
            );

            if ( state.collections.loading && ! state.collections.items.length ) {
                return `<p class="wpss-empty">${ escapeHtml( data.strings.collectionsLoading || 'Cargando colecciones…' ) }</p>`;
            }

            if ( ! state.collections.items.length ) {
                return `<p class="wpss-empty">${ escapeHtml( data.strings.collectionsEmpty || 'Aún no hay colecciones disponibles.' ) }</p>`;
            }

            const disabled = state.collections.loading ? 'disabled' : '';

            return `
                <div class="wpss-collections-field">
                    ${ state.collections.items.map( ( collection ) => {
                        const checked = assignedIds.has( Number( collection.id ) ) ? 'checked' : '';
                        return `
                            <label class="wpss-collections-field__item">
                                <input type="checkbox" data-action="toggle-song-collection" data-id="${ collection.id }" ${ checked } ${ disabled } />
                                <span>${ escapeHtml( collection.nombre ) }</span>
                            </label>
                        `;
                    } ).join( '' ) }
                </div>
            `;
        }

        function renderCollectionsManager() {
            const collections = state.collections;
            const items = Array.isArray( collections.items ) ? collections.items : [];
            const active = collections.active || createEmptyCollection();
            const activeId = collections.activeId;

            const sidebar = items.length
                ? items.map( ( item ) => {
                    const isActive = String( item.id ) === String( activeId ) ? 'is-active' : '';
                    return `
                        <li class="${ isActive }">
                            <button type="button" class="wpss-collections__item" data-action="collection-select" data-id="${ item.id }">
                                <span>${ escapeHtml( item.nombre ) }</span>
                                <span class="wpss-collections__badge">${ item.items_count || 0 }</span>
                            </button>
                        </li>
                    `;
                } ).join( '' )
                : `<li class="wpss-empty">${ escapeHtml( data.strings.collectionsListEmpty || 'Aún no hay colecciones.' ) }</li>`;

            const availableOptions = ( collections.catalog || [] )
                .filter( ( song ) => ! active.orden.includes( song.id ) )
                .map( ( song ) => `<option value="${ escapeAttr( song.id ) }">${ escapeHtml( song.titulo || `#${ song.id }` ) }</option>` )
                .join( '' );

            const catalogDisabled = collections.catalogLoading || ! availableOptions ? 'disabled' : '';
            const catalogNote = collections.catalogLoading
                ? `<p class="wpss-collections__hint">${ escapeHtml( data.strings.collectionsCatalogLoading || 'Cargando canciones…' ) }</p>`
                : '';

            const songsList = active.orden.length
                ? `
                    <ol class="wpss-collection-songs">
                        ${ active.orden.map( ( id, index ) => {
                            const entry = active.items.find( ( item ) => item.id === id );
                            const label = entry ? entry.titulo : `#${ id }`;
                            const upDisabled = index === 0 ? 'disabled' : '';
                            const downDisabled = index === active.orden.length - 1 ? 'disabled' : '';

                            return `
                                <li>
                                    <span class="wpss-collection-songs__label">${ escapeHtml( label ) }</span>
                                    <div class="wpss-collection-songs__actions">
                                        <button type="button" class="button button-secondary" data-action="collection-up" data-index="${ index }" ${ upDisabled }>▲</button>
                                        <button type="button" class="button button-secondary" data-action="collection-down" data-index="${ index }" ${ downDisabled }>▼</button>
                                        <button type="button" class="button-link-delete" data-action="collection-remove-song" data-index="${ index }">${ escapeHtml( data.strings.camposRemove || 'Eliminar' ) }</button>
                                    </div>
                                </li>
                            `;
                        } ).join( '' ) }
                    </ol>
                `
                : `<p class="wpss-empty">${ escapeHtml( data.strings.collectionNoSongs || 'Añade canciones a la colección.' ) }</p>`;

            const saveDisabled = collections.saving ? 'disabled' : '';
            const deleteDisabled = ! active.id || collections.deleting ? 'disabled' : '';

            return `
                <div class="wpss-collections">
                    <aside class="wpss-collections__sidebar">
                        <div class="wpss-collections__sidebar-header">
                            <h3>${ escapeHtml( data.strings.collectionsSidebar || 'Colecciones' ) }</h3>
                            <button type="button" class="button" data-action="collection-new">${ escapeHtml( data.strings.collectionNew || 'Nueva colección' ) }</button>
                        </div>
                        <ul class="wpss-collections__list">
                            ${ sidebar }
                        </ul>
                        <button type="button" class="button button-link" data-action="collection-refresh">${ escapeHtml( data.strings.collectionRefresh || 'Actualizar lista' ) }</button>
                    </aside>
                    <div class="wpss-collections__editor ${ collections.detailLoading ? 'is-loading' : '' }">
                        <div class="wpss-field-group">
                            <label>
                                <span>${ escapeHtml( data.strings.collectionName || 'Nombre' ) }</span>
                                <input type="text" data-model="collection" data-field="nombre" value="${ escapeAttr( active.nombre || '' ) }" ${ collections.detailLoading ? 'disabled' : '' } />
                            </label>
                            <label>
                                <span>${ escapeHtml( data.strings.collectionDescription || 'Descripción' ) }</span>
                                <textarea data-model="collection" data-field="descripcion" ${ collections.detailLoading ? 'disabled' : '' }>${ escapeHtml( active.descripcion || '' ) }</textarea>
                            </label>
                        </div>
                        <div class="wpss-collections__songs">
                            <h4>${ escapeHtml( data.strings.collectionSongs || 'Canciones' ) }</h4>
                            <div class="wpss-collections__add">
                                <select data-role="collection-catalog" ${ catalogDisabled }>
                                    <option value="">${ escapeHtml( data.strings.collectionSelectSong || 'Selecciona una canción' ) }</option>
                                    ${ availableOptions }
                                </select>
                                <button type="button" class="button" data-action="collection-add-song" ${ catalogDisabled }>${ escapeHtml( data.strings.collectionAddSong || 'Añadir a la colección' ) }</button>
                            </div>
                            ${ catalogNote }
                            ${ songsList }
                        </div>
                        <div class="wpss-collections__actions">
                            <button type="button" class="button button-primary" data-action="collection-save" ${ saveDisabled }>${ collections.saving ? escapeHtml( data.strings.saving || 'Guardando…' ) : escapeHtml( data.strings.collectionSave || 'Guardar colección' ) }</button>
                            <button type="button" class="button button-secondary" data-action="collection-delete" ${ deleteDisabled }>${ collections.deleting ? escapeHtml( data.strings.saving || 'Guardando…' ) : escapeHtml( data.strings.collectionDelete || 'Eliminar colección' ) }</button>
                        </div>
                    </div>
                </div>
            `;
        }

        function renderPrestamos() {
            const prestamos = state.editingSong.prestamos;
            if ( ! prestamos.length ) {
                return `<p class="wpss-empty">${ escapeHtml( data.strings.loansEmpty ) }</p>`;
            }

            return `
                <div class="wpss-repeatable">
                    ${ prestamos.map( ( prestamo, index ) => `
                        <div class="wpss-card">
                            <div class="wpss-card__header">
                                <strong>Préstamo ${ index + 1 }</strong>
                                <button type="button" class="button-link-delete" data-action="remove-prestamo" data-index="${ index }">${ escapeHtml( data.strings.camposRemove || 'Eliminar' ) }</button>
                            </div>
                            <div class="wpss-card__body">
                                <label>
                                    <span>Tonalidad / modo de origen</span>
                                    <input type="text" data-model="prestamos" data-field="origen" data-index="${ index }" value="${ escapeAttr( prestamo.origen || '' ) }" />
                                </label>
                                <label>
                                    <span>Acordes o notas prestadas</span>
                                    <textarea data-model="prestamos" data-field="descripcion" data-index="${ index }">${ escapeHtml( prestamo.descripcion || '' ) }</textarea>
                                </label>
                                <label>
                                    <span>Comentarios</span>
                                    <textarea data-model="prestamos" data-field="notas" data-index="${ index }">${ escapeHtml( prestamo.notas || '' ) }</textarea>
                                </label>
                            </div>
                        </div>
                    ` ).join( '' ) }
                </div>
            `;
        }

        function renderModulaciones() {
            const modulaciones = state.editingSong.modulaciones;
            if ( ! modulaciones.length ) {
                return `<p class="wpss-empty">${ escapeHtml( data.strings.modsEmpty ) }</p>`;
            }

            return `
                <div class="wpss-repeatable">
                    ${ modulaciones.map( ( modulacion, index ) => `
                        <div class="wpss-card">
                            <div class="wpss-card__header">
                                <strong>Modulación ${ index + 1 }</strong>
                                <button type="button" class="button-link-delete" data-action="remove-modulacion" data-index="${ index }">${ escapeHtml( data.strings.camposRemove || 'Eliminar' ) }</button>
                            </div>
                            <div class="wpss-card__body">
                                <label>
                                    <span>Sección</span>
                                    <input type="text" data-model="modulaciones" data-field="seccion" data-index="${ index }" value="${ escapeAttr( modulacion.seccion || '' ) }" />
                                </label>
                                <label>
                                    <span>Tonalidad destino</span>
                                    <input type="text" data-model="modulaciones" data-field="destino" data-index="${ index }" value="${ escapeAttr( modulacion.destino || '' ) }" />
                                </label>
                            </div>
                        </div>
                    ` ).join( '' ) }
                </div>
            `;
        }

        function renderSectionsManager() {
            const secciones = Array.isArray( state.editingSong.secciones ) ? state.editingSong.secciones : [];
            if ( ! secciones.length ) {
                return `<p class="wpss-empty">${ escapeHtml( data.strings.sectionsEmpty || 'Sin secciones registradas.' ) }</p>`;
            }

            return `
                <div class="wpss-sections-manager">
                    ${ secciones.map( ( seccion, index ) => `
                        <div class="wpss-section-row">
                            <div class="wpss-section-row__header">
                                <strong class="wpss-section-row__title">${ escapeHtml( seccion.nombre || getDefaultSectionName( index ) ) }</strong>
                                <div class="wpss-section-row__actions">
                                    <button type="button" class="button button-small" data-action="section-up" data-index="${ index }" ${ 0 === index ? 'disabled' : '' }>↑</button>
                                    <button type="button" class="button button-small" data-action="section-down" data-index="${ index }" ${ index === secciones.length - 1 ? 'disabled' : '' }>↓</button>
                                    <button type="button" class="button button-link-delete" data-action="remove-section" data-index="${ index }" ${ secciones.length <= 1 ? 'disabled' : '' }>${ escapeHtml( data.strings.camposRemove || 'Eliminar' ) }</button>
                                </div>
                            </div>
                            <label>
                                <span>Nombre</span>
                                <input type="text" data-model="section" data-index="${ index }" value="${ escapeAttr( seccion.nombre || '' ) }" maxlength="64" />
                            </label>
                        </div>
                    ` ).join( '' ) }
                </div>
            `;
        }

        function renderVersos() {
            const versos = state.editingSong.versos;
            if ( ! versos.length ) {
                return `<p class="wpss-empty">${ escapeHtml( data.strings.versesEmpty ) }</p>`;
            }

            return `
                <div class="wpss-verses">
                    ${ versos.map( ( verso, verseIndex ) => renderVerseCard( verso, verseIndex ) ).join( '' ) }
                </div>
            `;
        }

        function renderVerseCard( verso, verseIndex ) {
            return `
                <div class="wpss-verse-card">
                    <div class="wpss-verse-card__header">
                        <strong>Verso ${ verseIndex + 1 }</strong>
                        <div class="wpss-verse-actions">
                            <button type="button" class="button button-small" data-action="verse-up" data-index="${ verseIndex }" ${ 0 === verseIndex ? 'disabled' : '' }>↑</button>
                            <button type="button" class="button button-small" data-action="verse-down" data-index="${ verseIndex }" ${ verseIndex === state.editingSong.versos.length - 1 ? 'disabled' : '' }>↓</button>
                            <button type="button" class="button button-link-delete" data-action="remove-verso" data-index="${ verseIndex }">${ escapeHtml( data.strings.camposRemove || 'Eliminar' ) }</button>
                        </div>
                    </div>
                    <div class="wpss-verse-card__body">
                        ${ renderVerseSegments( verso, verseIndex ) }
                        ${ renderVerseDetail( verso, verseIndex ) }
                    </div>
                </div>
            `;
        }

        function renderVerseSegments( verso, verseIndex ) {
            const segmentos = Array.isArray( verso.segmentos ) ? verso.segmentos : [];
            return `
                <div class="wpss-segment-list">
                    ${ segmentos.map( ( segmento, segmentIndex ) => renderSegmentRow( segmento, verseIndex, segmentIndex, segmentos.length ) ).join( '' ) }
                    <div class="wpss-segment-add">
                        <button type="button" class="button button-secondary" data-action="add-segment" data-verse="${ verseIndex }">${ escapeHtml( data.strings.segmentAdd || 'Añadir segmento' ) }</button>
                    </div>
                </div>
            `;
        }

        function renderSegmentRow( segmento, verseIndex, segmentIndex, totalSegments ) {
            return `
                <div class="wpss-segment" data-verse="${ verseIndex }" data-segment="${ segmentIndex }">
                    <div class="wpss-segment__fields">
                        <label>
                            <span>Texto</span>
                            <textarea data-model="segmento" data-field="texto" data-verse="${ verseIndex }" data-segment="${ segmentIndex }">${ escapeHtml( segmento.texto || '' ) }</textarea>
                        </label>
                        <label>
                            <span>Acorde</span>
                            <input type="text" data-model="segmento" data-field="acorde" data-verse="${ verseIndex }" data-segment="${ segmentIndex }" value="${ escapeAttr( segmento.acorde || '' ) }" />
                        </label>
                    </div>
                    <div class="wpss-segment__actions">
                        <button type="button" class="button button-small" data-action="segment-up" data-verse="${ verseIndex }" data-segment="${ segmentIndex }" ${ 0 === segmentIndex ? 'disabled' : '' }>↑</button>
                        <button type="button" class="button button-small" data-action="segment-down" data-verse="${ verseIndex }" data-segment="${ segmentIndex }" ${ segmentIndex === totalSegments - 1 ? 'disabled' : '' }>↓</button>
                        <button type="button" class="button button-small" data-action="segment-duplicate" data-verse="${ verseIndex }" data-segment="${ segmentIndex }">${ escapeHtml( data.strings.segmentDuplicate || 'Duplicar' ) }</button>
                        <button type="button" class="button button-small" data-action="segment-split" data-verse="${ verseIndex }" data-segment="${ segmentIndex }">${ escapeHtml( data.strings.segmentSplit || 'Dividir' ) }</button>
                        <button type="button" class="button button-link-delete" data-action="segment-remove" data-verse="${ verseIndex }" data-segment="${ segmentIndex }">${ escapeHtml( data.strings.camposRemove || 'Eliminar' ) }</button>
                    </div>
                </div>
            `;
        }

        function renderVerseDetail( verso, index ) {
            const comentario = verso.comentario || '';
            const evento = verso.evento_armonico || null;
            const tipo = evento && evento.tipo ? evento.tipo : '';
            const secciones = Array.isArray( state.editingSong.secciones ) && state.editingSong.secciones.length
                ? state.editingSong.secciones
                : [ { id: '', nombre: getDefaultSectionName( 0 ) } ];

            const sectionOptions = secciones.map( ( seccion ) => `
                <option value="${ escapeAttr( seccion.id ) }" ${ seccion.id === verso.section_id ? 'selected' : '' }>${ escapeHtml( seccion.nombre ) }</option>
            ` ).join( '' );

            let eventFields = '';

            if ( 'modulacion' === tipo ) {
                const tonicaDestino = evento.tonica_destino || '';
                const campoDestino = evento.campo_armonico_destino || '';
                eventFields = `
                    <div class="wpss-verse-event">
                        <label>
                            <span>Tónica destino</span>
                            <input type="text" data-action="verse-event-field" data-index="${ index }" data-field="tonica_destino" value="${ escapeAttr( tonicaDestino ) }" list="wpss-tonicas" />
                        </label>
                        <label>
                            <span>Campo destino</span>
                            <input type="text" data-action="verse-event-field" data-index="${ index }" data-field="campo_armonico_destino" value="${ escapeAttr( campoDestino ) }" list="wpss-campos-armonicos" />
                        </label>
                    </div>
                `;
            } else if ( 'prestamo' === tipo ) {
                const tonicaOrigen = evento.tonica_origen || '';
                const campoOrigen = evento.campo_armonico_origen || '';
                eventFields = `
                    <div class="wpss-verse-event">
                        <label>
                            <span>Tónica origen</span>
                            <input type="text" data-action="verse-event-field" data-index="${ index }" data-field="tonica_origen" value="${ escapeAttr( tonicaOrigen ) }" list="wpss-tonicas" />
                        </label>
                        <label>
                            <span>Campo origen</span>
                            <input type="text" data-action="verse-event-field" data-index="${ index }" data-field="campo_armonico_origen" value="${ escapeAttr( campoOrigen ) }" list="wpss-campos-armonicos" />
                        </label>
                    </div>
                `;
            }

            return `
                <div class="wpss-verse-detail">
                    <label>
                        <span>Sección</span>
                        <select data-action="verse-section" data-index="${ index }">
                            ${ sectionOptions }
                        </select>
                    </label>
                    <label>
                        <span>Comentario</span>
                        <input type="text" data-model="versos" data-field="comentario" data-index="${ index }" value="${ escapeAttr( comentario ) }" />
                    </label>
                    <label>
                        <span>Evento armónico</span>
                        <select data-action="verse-event-type" data-index="${ index }">
                            <option value="" ${ '' === tipo ? 'selected' : '' }>Ninguno</option>
                            <option value="modulacion" ${ 'modulacion' === tipo ? 'selected' : '' }>Modulación</option>
                            <option value="prestamo" ${ 'prestamo' === tipo ? 'selected' : '' }>Préstamo</option>
                        </select>
                    </label>
                    ${ eventFields }
                </div>
            `;
        }

        function renderCamposLibrary() {
            const campos = state.campos.draft || [];
            if ( ! campos.length ) {
                return `<p class="wpss-empty">${ escapeHtml( data.strings.camposEmpty || 'Sin campos registrados.' ) }</p>`;
            }

            const sistemas = [
                { value: 'mayor', label: 'Mayor' },
                { value: 'menor_armonico', label: 'Menor armónico' },
                { value: 'menor_melodico', label: 'Menor melódico' },
                { value: 'otro', label: 'Otro' },
            ];

            return `
                <div class="wpss-campos">
                    ${ campos.map( ( campo, index ) => `
                        <div class="wpss-card">
                            <div class="wpss-card__header">
                                <strong>${ escapeHtml( campo.nombre || campo.slug || `Modo ${ index + 1 }` ) }</strong>
                                <div class="wpss-card__actions">
                                    <label class="wpss-toggle">
                                        <input type="checkbox" data-action="campo-toggle" data-index="${ index }" ${ campo.activo ? 'checked' : '' } />
                                        <span>${ escapeHtml( data.strings.camposActive || 'Activo' ) }</span>
                                    </label>
                                    <button type="button" class="button-link-delete" data-action="campos-remove" data-index="${ index }">${ escapeHtml( data.strings.camposRemove || 'Eliminar' ) }</button>
                                </div>
                            </div>
                            <div class="wpss-card__body">
                                <div class="wpss-field-group">
                                    <label>
                                        <span>Nombre</span>
                                        <input type="text" data-model="campo" data-field="nombre" data-index="${ index }" value="${ escapeAttr( campo.nombre || '' ) }" />
                                    </label>
                                    <label>
                                        <span>Slug</span>
                                        <input type="text" data-model="campo" data-field="slug" data-index="${ index }" value="${ escapeAttr( campo.slug || '' ) }" />
                                    </label>
                                </div>
                                <div class="wpss-field-group">
                                    <label>
                                        <span>Sistema</span>
                                        <select data-model="campo" data-field="sistema" data-index="${ index }">
                                            ${ sistemas.map( ( option ) => `<option value="${ option.value }" ${ option.value === ( campo.sistema || 'otro' ) ? 'selected' : '' }>${ option.label }</option>` ).join( '' ) }
                                        </select>
                                    </label>
                                    <label>
                                        <span>Interválica</span>
                                        <input type="text" data-model="campo" data-field="intervalos" data-index="${ index }" value="${ escapeAttr( campo.intervalos || '' ) }" />
                                    </label>
                                </div>
                                <label>
                                    <span>Descripción contextual / ayudas</span>
                                    <textarea data-model="campo" data-field="descripcion" data-index="${ index }">${ escapeHtml( campo.descripcion || '' ) }</textarea>
                                </label>
                                <label>
                                    <span>Notas</span>
                                    <textarea data-model="campo" data-field="notas" data-index="${ index }">${ escapeHtml( campo.notas || '' ) }</textarea>
                                </label>
                            </div>
                        </div>
                    ` ).join( '' ) }
                    <div class="wpss-campos__footer">
                        <button type="button" class="button button-secondary" data-action="campos-add">${ escapeHtml( data.strings.camposAdd || 'Añadir modo' ) }</button>
                    </div>
                </div>
            `;
        }

        function renderReadingView() {
            const song = state.editingSong;
            if ( ! song.versos.length ) {
                return `<p class="wpss-empty">${ escapeHtml( data.strings.readingEmpty || 'Sin contenido para mostrar.' ) }</p>`;
            }

            const groups = groupVersesBySection();

            const modeButtons = `
                <div class="wpss-reading__modes">
                    <button type="button" class="button button-secondary ${ 'inline' === state.readingMode ? 'is-active' : '' }" data-action="set-reading-mode" data-mode="inline">${ escapeHtml( data.strings.readingModeInline || 'Acordes inline' ) }</button>
                    <button type="button" class="button button-secondary ${ 'stacked' === state.readingMode ? 'is-active' : '' }" data-action="set-reading-mode" data-mode="stacked">${ escapeHtml( data.strings.readingModeStacked || 'Acordes arriba' ) }</button>
                </div>
            `;

            const queue = state.readingQueue;
            const queueControls = queue.ids.length
                ? `
                    <div class="wpss-reading__queue">
                        <button type="button" class="button button-secondary" data-action="reading-prev" ${ queue.index <= 0 ? 'disabled' : '' }>${ escapeHtml( data.strings.readingPrev || 'Anterior' ) }</button>
                        <span>${ escapeHtml( data.strings.readingProgress || 'Canción' ) } ${ queue.index + 1 } / ${ queue.ids.length }</span>
                        <button type="button" class="button button-secondary" data-action="reading-next" ${ queue.index >= queue.ids.length - 1 ? 'disabled' : '' }>${ escapeHtml( data.strings.readingNext || 'Siguiente' ) }</button>
                        <button type="button" class="button" data-action="reading-exit">${ escapeHtml( data.strings.readingExit || 'Salir' ) }</button>
                    </div>
                `
                : '';

            return `
                <div class="wpss-reading">
                    <div class="wpss-reading__header">
                        <div>
                            <h3>${ escapeHtml( song.titulo || data.strings.newSong ) }</h3>
                            <p><strong>Tónica:</strong> ${ escapeHtml( song.tonica || '—' ) }</p>
                            <p><strong>Campo armónico:</strong> ${ escapeHtml( song.campo_armonico || '—' ) }</p>
                            ${ queue.nombre ? `<p><strong>${ escapeHtml( data.strings.collectionCurrent || 'Colección' ) }:</strong> ${ escapeHtml( queue.nombre ) }</p>` : '' }
                        </div>
                        <div class="wpss-reading__actions">
                            ${ modeButtons }
                            <button type="button" class="button" data-action="copy-reading">${ escapeHtml( data.strings.copyAsText || 'Copiar como texto' ) }</button>
                        </div>
                    </div>
                    ${ queueControls }
                    <div class="wpss-reading__sections">
                        ${ groups.map( ( group ) => `
                            <section class="wpss-reading__section">
                                <h4 class="wpss-section-title">${ escapeHtml( group.section.nombre || getDefaultSectionName( 0 ) ) }</h4>
                                <ol class="wpss-reading__verses">
                                    ${ group.versos.map( ( verso ) => renderReadingVerse( verso ) ).join( '' ) }
                                </ol>
                            </section>
                        ` ).join( '' ) }
                    </div>
                </div>
            `;
        }

        function renderReadingVerse( verso ) {
            const segmentos = Array.isArray( verso.segmentos ) ? verso.segmentos : [];
            const evento = renderEventoChip( verso.evento_armonico );
            const comentario = verso.comentario ? `<span class="wpss-reading__comment">${ escapeHtml( verso.comentario ) }</span>` : '';
            const metaContent = [ evento, comentario ].filter( Boolean ).join( ' ' );
            const meta = metaContent ? `<div class="wpss-reading__meta">${ metaContent }</div>` : '';

            if ( 'stacked' === state.readingMode ) {
                const lines = formatSegmentsForStackedMode( segmentos );
                const chordsLine = escapeHtml( lines.chords );
                const lyricsLine = escapeHtml( lines.lyrics );

                return `
                    <li>
                        <pre class="wpss-reading__stack">${ chordsLine }\n${ lyricsLine }</pre>
                        ${ meta }
                    </li>
                `;
            }

            const partes = segmentos.map( ( segmento ) => {
                const acorde = segmento.acorde ? `<span class="wpss-reading__chord">[${ escapeHtml( segmento.acorde ) }]</span>` : '';
                const texto = escapeHtml( segmento.texto || '' );
                return `${ acorde } ${ texto }`;
            } ).join( '' ).trim();

            return `
                <li>
                    <div class="wpss-reading__line">${ partes }</div>
                    ${ meta }
                </li>
            `;
        }

        function groupVersesBySection() {
            const sections = Array.isArray( state.editingSong.secciones ) ? state.editingSong.secciones : [];
            const versos = Array.isArray( state.editingSong.versos ) ? state.editingSong.versos : [];

            if ( ! sections.length ) {
                return versos.length
                    ? [ { section: { id: '', nombre: getDefaultSectionName( 0 ) }, versos } ]
                    : [];
            }

            const map = new Map();
            sections.forEach( ( section ) => {
                map.set( section.id, [] );
            } );

            const fallback = sections[ 0 ] ? sections[ 0 ].id : '';

            versos.forEach( ( verso ) => {
                const sectionId = map.has( verso.section_id ) ? verso.section_id : fallback;
                if ( ! map.has( sectionId ) ) {
                    map.set( sectionId, [] );
                }
                map.get( sectionId ).push( verso );
            } );

            const groups = [];
            sections.forEach( ( section ) => {
                const groupVerses = map.get( section.id ) || [];
                if ( groupVerses.length ) {
                    groups.push( { section, versos: groupVerses } );
                }
            } );

            return groups;
        }

        function renderEventoChip( evento ) {
            if ( ! evento || ! evento.tipo ) {
                return '';
            }

            if ( 'modulacion' === evento.tipo ) {
                const destino = [ evento.tonica_destino || '', evento.campo_armonico_destino || '' ].filter( Boolean ).join( ' ' );
                return `<span class="wpss-event-chip">Modulación → ${ escapeHtml( destino || '—' ) }</span>`;
            }

            if ( 'prestamo' === evento.tipo ) {
                const origen = [ evento.tonica_origen || '', evento.campo_armonico_origen || '' ].filter( Boolean ).join( ' ' );
                return `<span class="wpss-event-chip">Préstamo ← ${ escapeHtml( origen || '—' ) }</span>`;
            }

            return '';
        }

        function buildEventoTexto( evento ) {
            if ( ! evento || ! evento.tipo ) {
                return '';
            }

            if ( 'modulacion' === evento.tipo ) {
                const destino = [ evento.tonica_destino || '', evento.campo_armonico_destino || '' ].filter( Boolean ).join( ' ' );
                return `Modulación → ${ destino || '—' }`;
            }

            if ( 'prestamo' === evento.tipo ) {
                const origen = [ evento.tonica_origen || '', evento.campo_armonico_origen || '' ].filter( Boolean ).join( ' ' );
                return `Préstamo ← ${ origen || '—' }`;
            }

            return '';
        }

        function buildVerseMetaText( verso ) {
            if ( ! verso ) {
                return '';
            }

            const parts = [];
            const eventoTexto = buildEventoTexto( verso.evento_armonico );
            if ( eventoTexto ) {
                parts.push( eventoTexto );
            }

            if ( verso.comentario ) {
                parts.push( `(${ verso.comentario })` );
            }

            return parts.join( ' ' ).trim();
        }

        function renderDatalist() {
            const tonicas = Array.isArray( data.tonicas ) ? data.tonicas : [];
            const campos = Array.isArray( state.camposNames ) ? state.camposNames : [];

            const tonicaList = tonicas.length ? `
                <datalist id="wpss-tonicas">
                    ${ tonicas.map( ( nota ) => `<option value="${ escapeAttr( nota ) }"></option>` ).join( '' ) }
                </datalist>
            ` : '';

            const camposList = campos.length ? `
                <datalist id="wpss-campos-armonicos">
                    ${ campos.map( ( modo ) => `<option value="${ escapeAttr( modo ) }"></option>` ).join( '' ) }
                </datalist>
            ` : '';

            return tonicaList + camposList;
        }
        function handleClick( event ) {
            const target = event.target.closest( '[data-action]' );
            if ( ! target || ! container.contains( target ) ) {
                return;
            }

            const action = target.dataset.action;
            event.preventDefault();

            switch ( action ) {
            case 'new-song':
            case 'reset-editor':
                resetEditor();
                break;
            case 'save-song':
                handleSaveSong();
                break;
            case 'add-prestamo':
                state.editingSong.prestamos.push( { origen: '', descripcion: '', notas: '' } );
                render();
                break;
            case 'remove-prestamo':
                state.editingSong.prestamos.splice( parseInt( target.dataset.index, 10 ), 1 );
                render();
                break;
            case 'add-modulacion':
                state.editingSong.modulaciones.push( { seccion: '', destino: '' } );
                render();
                break;
            case 'remove-modulacion':
                state.editingSong.modulaciones.splice( parseInt( target.dataset.index, 10 ), 1 );
                render();
                break;
            case 'add-section':
                addSection();
                render();
                break;
            case 'remove-section':
                removeSection( parseInt( target.dataset.index, 10 ) );
                render();
                break;
            case 'section-up':
                reorderSection( parseInt( target.dataset.index, 10 ), -1 );
                render();
                break;
            case 'section-down':
                reorderSection( parseInt( target.dataset.index, 10 ), 1 );
                render();
                break;
            case 'add-verso':
                {
                    const primarySection = Array.isArray( state.editingSong.secciones ) && state.editingSong.secciones[ 0 ]
                        ? state.editingSong.secciones[ 0 ].id
                        : '';
                    state.editingSong.versos.push( createEmptyVerse( state.editingSong.versos.length + 1, primarySection ) );
                }
                normalizeVerseOrder();
                render();
                break;
            case 'remove-verso':
                state.editingSong.versos.splice( parseInt( target.dataset.index, 10 ), 1 );
                normalizeVerseOrder();
                render();
                break;
            case 'verse-up':
                reorderVerse( parseInt( target.dataset.index, 10 ), -1 );
                break;
            case 'verse-down':
                reorderVerse( parseInt( target.dataset.index, 10 ), 1 );
                break;
            case 'segment-up':
                moveSegment( parseInt( target.dataset.verse, 10 ), parseInt( target.dataset.segment, 10 ), -1 );
                break;
            case 'segment-down':
                moveSegment( parseInt( target.dataset.verse, 10 ), parseInt( target.dataset.segment, 10 ), 1 );
                break;
            case 'segment-duplicate':
                duplicateSegment( parseInt( target.dataset.verse, 10 ), parseInt( target.dataset.segment, 10 ) );
                break;
            case 'segment-remove':
                removeSegment( parseInt( target.dataset.verse, 10 ), parseInt( target.dataset.segment, 10 ) );
                break;
            case 'segment-split':
                splitSegment( parseInt( target.dataset.verse, 10 ), parseInt( target.dataset.segment, 10 ) );
                break;
            case 'add-segment':
                addSegment( parseInt( target.dataset.verse, 10 ) );
                break;
            case 'select-song':
                selectSong( parseInt( target.dataset.id, 10 ) );
                break;
            case 'page-prev':
                changePage( state.pagination.page - 1 );
                break;
            case 'page-next':
                changePage( state.pagination.page + 1 );
                break;
            case 'set-tab':
                setActiveTab( target.dataset.tab );
                break;
            case 'campos-add':
                state.campos.draft.push( createEmptyCampo() );
                render();
                break;
            case 'campos-remove':
                state.campos.draft.splice( parseInt( target.dataset.index, 10 ), 1 );
                render();
                break;
            case 'save-campos':
                handleSaveCampos();
                break;
            case 'campo-toggle':
                toggleCampoActivo( parseInt( target.dataset.index, 10 ), target.checked );
                break;
            case 'copy-reading':
                copyReadingText();
                break;
            case 'view-collection':
                if ( state.filters.coleccion ) {
                    startReadingCollection( parseInt( state.filters.coleccion, 10 ) );
                }
                break;
            case 'set-reading-mode':
                setReadingMode( target.dataset.mode );
                break;
            case 'reading-prev':
                goToPrevInQueue();
                break;
            case 'reading-next':
                goToNextInQueue();
                break;
            case 'reading-exit':
                clearReadingQueue();
                render();
                break;
            case 'collection-new':
                state.collections.activeId = null;
                state.collections.active = createEmptyCollection();
                state.collections.feedback = null;
                render();
                break;
            case 'collection-select':
                setActiveCollection( parseInt( target.dataset.id, 10 ) );
                break;
            case 'collection-save':
                handleCollectionSave();
                break;
            case 'collection-delete':
                handleCollectionDelete();
                break;
            case 'collection-add-song':
                {
                    const select = container.querySelector( '[data-role="collection-catalog"]' );
                    if ( select && select.value ) {
                        addSongToActiveCollection( select.value );
                        select.value = '';
                        render();
                    }
                }
                break;
            case 'collection-remove-song':
                removeSongFromActiveCollection( parseInt( target.dataset.index, 10 ) );
                render();
                break;
            case 'collection-up':
                moveSongInActiveCollection( parseInt( target.dataset.index, 10 ), -1 );
                render();
                break;
            case 'collection-down':
                moveSongInActiveCollection( parseInt( target.dataset.index, 10 ), 1 );
                render();
                break;
            case 'collection-refresh':
                loadCollections();
                ensureCollectionsCatalog();
                break;
            default:
                break;
            }
        }

        function handleInput( event ) {
            const action = event.target.dataset.action;

            if ( 'verse-event-field' === action ) {
                const index = parseInt( event.target.dataset.index, 10 );
                const field = event.target.dataset.field;
                if ( Number.isNaN( index ) || ! field ) {
                    return;
                }

                const verso = state.editingSong.versos[ index ];
                if ( ! verso || ! verso.evento_armonico ) {
                    return;
                }

                verso.evento_armonico[ field ] = event.target.value;
                return;
            }

            const model = event.target.dataset.model;
            if ( ! model ) {
                return;
            }

            const field = event.target.dataset.field;
            const value = event.target.value;

            if ( 'general' === model ) {
                state.editingSong[ field ] = value;
            } else if ( 'prestamos' === model || 'modulaciones' === model || 'versos' === model ) {
                const index = parseInt( event.target.dataset.index, 10 );
                if ( Number.isNaN( index ) || ! state.editingSong[ model ][ index ] ) {
                    return;
                }

                let nextValue = value;
                if ( 'nombre_estrofa' === field ) {
                    nextValue = value.slice( 0, 64 );
                }

                state.editingSong[ model ][ index ][ field ] = nextValue;
            } else if ( 'section' === model ) {
                const index = parseInt( event.target.dataset.index, 10 );
                if ( Number.isNaN( index ) ) {
                    return;
                }

                renameSection( index, value );

                const section = state.editingSong.secciones[ index ];
                const row = event.target.closest( '.wpss-section-row' );
                if ( row ) {
                    const title = row.querySelector( '.wpss-section-row__title' );
                    if ( title && section ) {
                        title.textContent = section.nombre;
                    }
                }

                if ( section ) {
                    container.querySelectorAll( 'select[data-action="verse-section"]' ).forEach( ( select ) => {
                        Array.from( select.options ).forEach( ( option ) => {
                            const match = state.editingSong.secciones.find( ( item ) => item.id === option.value );
                            if ( match ) {
                                option.textContent = match.nombre;
                            }
                        } );
                    } );
                }
            } else if ( 'collection' === model ) {
                updateActiveCollectionField( field, value );
            } else if ( 'segmento' === model ) {
                const verseIndex = parseInt( event.target.dataset.verse, 10 );
                const segmentIndex = parseInt( event.target.dataset.segment, 10 );
                const verso = state.editingSong.versos[ verseIndex ];
                if ( ! verso || ! verso.segmentos || Number.isNaN( segmentIndex ) ) {
                    return;
                }

                if ( ! verso.segmentos[ segmentIndex ] ) {
                    verso.segmentos[ segmentIndex ] = createEmptySegment();
                }

                verso.segmentos[ segmentIndex ][ field ] = value;
                if ( 'texto' === field ) {
                    updateSegmentSelection( verseIndex, segmentIndex, event.target.selectionStart, event.target.selectionEnd );
                }
            } else if ( 'campo' === model ) {
                const index = parseInt( event.target.dataset.index, 10 );
                if ( Number.isNaN( index ) || ! state.campos.draft[ index ] ) {
                    return;
                }

                state.campos.draft[ index ][ field ] = value;
                if ( 'nombre' === field && ! state.campos.draft[ index ].slug ) {
                    state.campos.draft[ index ].slug = slugify( value );
                }
            }
        }

        function handleChange( event ) {
            const action = event.target.dataset.action;
            if ( action ) {
                switch ( action ) {
                case 'filter-tonica':
                    state.filters.tonica = event.target.value;
                    state.pagination.page = 1;
                    loadSongs();
                    return;
                case 'filter-prestamos':
                    state.filters.con_prestamos = event.target.value;
                    state.pagination.page = 1;
                    loadSongs();
                    return;
                case 'filter-modulaciones':
                    state.filters.con_modulaciones = event.target.value;
                    state.pagination.page = 1;
                    loadSongs();
                    return;
                case 'filter-coleccion':
                    state.filters.coleccion = event.target.value;
                    state.pagination.page = 1;
                    loadSongs();
                    return;
                case 'verse-section':
                    updateVerseSection( parseInt( event.target.dataset.index, 10 ), event.target.value );
                    render();
                    return;
                case 'verse-event-type':
                    updateVerseEventType( parseInt( event.target.dataset.index, 10 ), event.target.value );
                    render();
                    return;
                case 'campo-toggle':
                    toggleCampoActivo( parseInt( event.target.dataset.index, 10 ), event.target.checked );
                    return;
                case 'toggle-song-collection':
                    toggleSongCollection( event.target.dataset.id, event.target.checked );
                    return;
                default:
                    break;
                }
            }

            const model = event.target.dataset.model;
            if ( 'general' === model ) {
                state.editingSong[ event.target.dataset.field ] = event.target.value;
            } else if ( 'campo' === model ) {
                const index = parseInt( event.target.dataset.index, 10 );
                if ( ! Number.isNaN( index ) && state.campos.draft[ index ] ) {
                    state.campos.draft[ index ][ event.target.dataset.field ] = event.target.value;
                }
            } else if ( 'collection' === model ) {
                updateActiveCollectionField( event.target.dataset.field, event.target.value );
            }
        }

        function updateSegmentSelectionFromEvent( event ) {
            const model = event.target.dataset.model;
            if ( 'segmento' !== model || 'TEXTAREA' !== event.target.tagName ) {
                return;
            }

            const verseIndex = parseInt( event.target.dataset.verse, 10 );
            const segmentIndex = parseInt( event.target.dataset.segment, 10 );
            if ( Number.isNaN( verseIndex ) || Number.isNaN( segmentIndex ) ) {
                return;
            }

            updateSegmentSelection( verseIndex, segmentIndex, event.target.selectionStart, event.target.selectionEnd );
        }

        function updateSegmentSelection( verseIndex, segmentIndex, start, end ) {
            state.segmentSelection = {
                verse: verseIndex,
                segment: segmentIndex,
                selectionStart: 'number' === typeof start ? start : null,
                selectionEnd: 'number' === typeof end ? end : null,
            };
        }

        function setActiveTab( tab ) {
            if ( ! tab || state.activeTab === tab ) {
                return;
            }

            state.activeTab = tab;
            if ( 'campos' === tab ) {
                state.campos.feedback = null;
                state.campos.error = null;
                state.campos.draft = deepClone( state.campos.library );
            } else if ( 'colecciones' === tab ) {
                state.collections.feedback = null;
                state.collections.error = null;
                if ( ! state.collections.items.length && ! state.collections.loading ) {
                    loadCollections();
                }
                ensureCollectionsCatalog();
            }
            render();
        }

        function reorderVerse( index, direction ) {
            const targetIndex = index + direction;
            if ( targetIndex < 0 || targetIndex >= state.editingSong.versos.length ) {
                return;
            }

            const versos = state.editingSong.versos;
            const temp = versos[ index ];
            versos[ index ] = versos[ targetIndex ];
            versos[ targetIndex ] = temp;
            normalizeVerseOrder();
            render();
        }

        function addSegment( verseIndex ) {
            const verso = state.editingSong.versos[ verseIndex ];
            if ( ! verso ) {
                return;
            }
            verso.segmentos.push( createEmptySegment() );
            render();
        }

        function duplicateSegment( verseIndex, segmentIndex ) {
            const verso = state.editingSong.versos[ verseIndex ];
            if ( ! verso || ! verso.segmentos[ segmentIndex ] ) {
                return;
            }

            const clone = deepClone( verso.segmentos[ segmentIndex ] );
            verso.segmentos.splice( segmentIndex + 1, 0, clone );
            render();
        }

        function removeSegment( verseIndex, segmentIndex ) {
            const verso = state.editingSong.versos[ verseIndex ];
            if ( ! verso || ! Array.isArray( verso.segmentos ) ) {
                return;
            }

            if ( verso.segmentos.length <= 1 ) {
                verso.segmentos[ 0 ] = createEmptySegment();
            } else {
                verso.segmentos.splice( segmentIndex, 1 );
            }
            render();
        }

        function moveSegment( verseIndex, segmentIndex, direction ) {
            const verso = state.editingSong.versos[ verseIndex ];
            if ( ! verso ) {
                return;
            }

            const target = segmentIndex + direction;
            if ( target < 0 || target >= verso.segmentos.length ) {
                return;
            }

            const segmentos = verso.segmentos;
            const temp = segmentos[ segmentIndex ];
            segmentos[ segmentIndex ] = segmentos[ target ];
            segmentos[ target ] = temp;
            render();
        }

        function splitSegment( verseIndex, segmentIndex ) {
            const verso = state.editingSong.versos[ verseIndex ];
            if ( ! verso || ! verso.segmentos[ segmentIndex ] ) {
                return;
            }

            const selection = state.segmentSelection;
            if ( selection.verse !== verseIndex || selection.segment !== segmentIndex ) {
                return;
            }

            const segment = verso.segmentos[ segmentIndex ];
            const texto = segment.texto || '';
            const start = selection.selectionStart;
            const end = selection.selectionEnd;
            if ( null === start || start !== end ) {
                return;
            }

            if ( start <= 0 || start >= texto.length ) {
                return;
            }

            const before = texto.slice( 0, start );
            const after = texto.slice( start );

            segment.texto = before;
            const nuevo = {
                texto: after,
                acorde: segment.acorde,
            };

            verso.segmentos.splice( segmentIndex + 1, 0, nuevo );
            render();
        }

        function updateVerseEventType( index, tipo ) {
            if ( Number.isNaN( index ) ) {
                return;
            }

            const verso = state.editingSong.versos[ index ];
            if ( ! verso ) {
                return;
            }

            if ( ! tipo ) {
                verso.evento_armonico = null;
                render();
                return;
            }

            const previous = verso.evento_armonico && 'object' === typeof verso.evento_armonico ? verso.evento_armonico : null;
            const next = { tipo };

            if ( previous && previous.tipo === tipo ) {
                if ( 'modulacion' === tipo ) {
                    if ( previous.tonica_destino ) {
                        next.tonica_destino = previous.tonica_destino;
                    }
                    if ( previous.campo_armonico_destino ) {
                        next.campo_armonico_destino = previous.campo_armonico_destino;
                    }
                } else if ( 'prestamo' === tipo ) {
                    if ( previous.tonica_origen ) {
                        next.tonica_origen = previous.tonica_origen;
                    }
                    if ( previous.campo_armonico_origen ) {
                        next.campo_armonico_origen = previous.campo_armonico_origen;
                    }
                }
            }

            verso.evento_armonico = next;
        }

        function toggleCampoActivo( index, checked ) {
            if ( Number.isNaN( index ) || ! state.campos.draft[ index ] ) {
                return;
            }

            state.campos.draft[ index ].activo = !! checked;
        }

        function selectSong( id, options = {} ) {
            if ( state.songLoading || state.saving || ! id ) {
                return;
            }

            const targetTab = options.targetTab || 'editor';
            const silent = !! options.silent;
            const preserveQueue = !! options.preserveQueue;

            if ( ! preserveQueue ) {
                clearReadingQueue();
            }

            state.songLoading = true;
            state.selectedSongId = id;
            if ( ! silent ) {
                state.feedback = null;
            }
            state.error = null;
            state.activeTab = targetTab;
            render();

            api.getSong( id ).then( ( response ) => {
                const song = response.data || {};
                state.editingSong = {
                    id: song.id,
                    titulo: song.titulo || '',
                    tonica: song.tonica || song.tonalidad || '',
                    campo_armonico: song.campo_armonico || '',
                    campo_armonico_predominante: song.campo_armonico_predominante || '',
                    prestamos: Array.isArray( song.prestamos ) ? song.prestamos : [],
                    modulaciones: Array.isArray( song.modulaciones ) ? song.modulaciones : [],
                    versos: normalizeVersesFromApi( song.versos ),
                    secciones: normalizeSectionsFromApi( song.secciones ),
                    tiene_prestamos: !! song.tiene_prestamos,
                    tiene_modulaciones: !! song.tiene_modulaciones,
                    colecciones: normalizeSongCollections( song.colecciones ),
                };
                normalizeVerseOrder();
                if ( ! silent ) {
                    setFeedback( data.strings.songLoaded || 'Canción cargada.' );
                }
            } ).catch( () => {
                setError( data.strings.loadSongError || 'No fue posible cargar la canción seleccionada.' );
            } ).finally( () => {
                state.songLoading = false;
                render();
            } );
        }

        function normalizeVersesFromApi( versos ) {
            if ( ! Array.isArray( versos ) || ! versos.length ) {
                return [];
            }

            return versos.map( ( verso, index ) => {
                const segmentos = Array.isArray( verso.segmentos ) && verso.segmentos.length
                    ? verso.segmentos.map( ( segmento ) => ( {
                        texto: segmento && segmento.texto ? segmento.texto : '',
                        acorde: segmento && segmento.acorde ? segmento.acorde : '',
                    } ) )
                    : [ createEmptySegment() ];

                return {
                    id: verso.id || null,
                    orden: verso.orden || index + 1,
                    segmentos,
                    comentario: verso.comentario || '',
                    evento_armonico: verso.evento_armonico || null,
                    section_id: verso.section_id ? String( verso.section_id ) : '',
                    fin_de_estrofa: !! verso.fin_de_estrofa,
                    nombre_estrofa: verso.nombre_estrofa ? String( verso.nombre_estrofa ).slice( 0, 64 ) : '',
                };
            } );
        }

        function handleSaveSong() {
            if ( state.saving ) {
                return;
            }

            const song = state.editingSong;

            if ( ! song.titulo.trim() ) {
                setError( data.strings.titleRequired || 'El título es obligatorio.' );
                render();
                return;
            }

            if ( ! song.tonica || ! song.tonica.trim() ) {
                setError( data.strings.tonicaRequired || 'La tónica es obligatoria.' );
                render();
                return;
            }

            if ( ! song.campo_armonico || ! song.campo_armonico.trim() ) {
                setError( data.strings.modeRequired || 'El campo armónico es obligatorio.' );
                render();
                return;
            }

            const validationError = validateSegments();
            if ( validationError ) {
                setError( validationError );
                render();
                return;
            }

            state.saving = true;
            setFeedback( data.strings.saving || 'Guardando…', 'info' );
            render();

            const payload = {
                id: song.id || null,
                titulo: song.titulo,
                tonica: song.tonica,
                campo_armonico: song.campo_armonico,
                campo_armonico_predominante: song.campo_armonico_predominante,
                prestamos_cancion: song.prestamos,
                modulaciones_cancion: song.modulaciones,
                secciones: song.secciones,
                versos: song.versos.map( ( verso ) => ( {
                    orden: verso.orden,
                    segmentos: verso.segmentos,
                    comentario: verso.comentario,
                    evento_armonico: verso.evento_armonico,
                    section_id: verso.section_id || '',
                    fin_de_estrofa: !! verso.fin_de_estrofa,
                    nombre_estrofa: verso.fin_de_estrofa ? ( verso.nombre_estrofa || '' ) : '',
                } ) ),
                colecciones: Array.isArray( song.colecciones ) ? song.colecciones.map( ( item ) => item.id ) : [],
            };

            api.saveSong( payload ).then( ( response ) => {
                const body = response.data || {};
                state.editingSong.id = body.id;
                state.editingSong.tiene_prestamos = !! body.tiene_prestamos;
                state.editingSong.tiene_modulaciones = !! body.tiene_modulaciones;
                state.editingSong.colecciones = normalizeSongCollections( body.colecciones );
                state.selectedSongId = body.id;
                setFeedback( data.strings.saved || 'Cambios guardados.' );
                loadSongs();
                loadCollections();
            } ).catch( ( error ) => {
                const message = ( error.payload && error.payload.message ) ? error.payload.message : data.strings.error;
                setError( message || 'Ocurrió un error al guardar.' );
                render();
            } ).finally( () => {
                state.saving = false;
            } );
        }

        function validateSegments() {
            if ( ! state.editingSong.versos.length ) {
                return null;
            }

            for ( const verso of state.editingSong.versos ) {
                if ( ! Array.isArray( verso.segmentos ) || ! verso.segmentos.length ) {
                    return data.strings.segmentRequired || 'Cada verso necesita al menos un segmento con texto o acorde.';
                }

                let previousEmpty = false;
                for ( const segmento of verso.segmentos ) {
                    const textoVacio = ! segmento.texto || ! segmento.texto.trim();
                    const acordeVacio = ! segmento.acorde || ! segmento.acorde.trim();

                    if ( textoVacio && acordeVacio ) {
                        return data.strings.segmentRequired || 'Cada verso necesita al menos un segmento con texto o acorde.';
                    }

                    if ( textoVacio && previousEmpty ) {
                        return data.strings.segmentConsecutive || 'No se permiten segmentos consecutivos sin texto.';
                    }

                    previousEmpty = textoVacio;
                }
            }

            return null;
        }

        function handleSaveCampos() {
            if ( state.campos.saving ) {
                return;
            }

            const invalid = state.campos.draft.find( ( campo ) => ! campo.slug || ! campo.slug.trim() );
            if ( invalid ) {
                setCamposError( data.strings.camposSlugRequired || 'Cada modo necesita un identificador.' );
                render();
                return;
            }

            state.campos.saving = true;
            setCamposFeedback( data.strings.saving || 'Guardando…', 'info' );
            render();

            api.saveCampos( state.campos.draft ).then( ( response ) => {
                const campos = Array.isArray( response.data && response.data.campos ) ? response.data.campos : [];
                state.campos.library = campos;
                state.campos.draft = deepClone( campos );
                refreshCampoNames();
                setCamposFeedback( data.strings.camposSaved || 'Campos armónicos actualizados.' );
                render();
            } ).catch( ( error ) => {
                const message = ( error.payload && error.payload.message ) ? error.payload.message : data.strings.camposError;
                setCamposError( message || 'No fue posible guardar la biblioteca de campos armónicos.' );
                render();
            } ).finally( () => {
                state.campos.saving = false;
            } );
        }

        function slugify( value ) {
            if ( ! value ) {
                return '';
            }

            return value
                .toString()
                .normalize( 'NFD' )
                .replace( /[\u0300-\u036f]/g, '' )
                .replace( /[^a-zA-Z0-9\s_-]/g, '' )
                .trim()
                .replace( /[\s_-]+/g, '-' )
                .toLowerCase();
        }

        function copyReadingText() {
            const text = buildReadingText();
            if ( ! text ) {
                return;
            }

            if ( navigator.clipboard && navigator.clipboard.writeText ) {
                navigator.clipboard.writeText( text ).catch( () => {} );
                return;
            }

            const textarea = document.createElement( 'textarea' );
            textarea.value = text;
            textarea.setAttribute( 'readonly', '' );
            textarea.style.position = 'absolute';
            textarea.style.left = '-9999px';
            document.body.appendChild( textarea );
            textarea.select();
            try {
                document.execCommand( 'copy' );
            } catch ( error ) {
                // No-op.
            }
            document.body.removeChild( textarea );
        }

        function buildReadingText() {
            const song = state.editingSong;
            if ( ! song || ! song.versos.length ) {
                return '';
            }

            const lines = [];
            lines.push( song.titulo || data.strings.newSong );
            lines.push( `Tónica: ${ song.tonica || '—' }` );
            lines.push( `Campo armónico: ${ song.campo_armonico || '—' }` );
            if ( state.readingQueue && state.readingQueue.nombre ) {
                lines.push( `${ data.strings.collectionCurrent || 'Colección' }: ${ state.readingQueue.nombre } (${ state.readingQueue.index + 1 } / ${ state.readingQueue.ids.length })` );
            }
            lines.push( '' );

            const groups = groupVersesBySection();

            groups.forEach( ( group, index ) => {
                if ( index > 0 ) {
                    lines.push( '' );
                }

                const nombreSeccion = group.section.nombre || getDefaultSectionName( index );
                lines.push( nombreSeccion );

                group.versos.forEach( ( verso ) => {
                    const segmentos = Array.isArray( verso.segmentos ) ? verso.segmentos : [];

                    if ( 'stacked' === state.readingMode ) {
                        const formatted = formatSegmentsForStackedMode( segmentos );
                        if ( formatted.chords ) {
                            lines.push( formatted.chords );
                        }
                        lines.push( formatted.lyrics );

                        const meta = buildVerseMetaText( verso );
                        if ( meta ) {
                            lines.push( meta );
                        }
                    } else {
                        const partes = segmentos.map( ( segmento ) => {
                            const acorde = segmento.acorde ? `[${ segmento.acorde }]` : '';
                            return `${ acorde } ${ segmento.texto || '' }`.trim();
                        } ).join( ' ' ).trim();

                        let linea = partes;
                        const meta = buildVerseMetaText( verso );
                        if ( meta ) {
                            linea += ` \u2014 ${ meta }`;
                        }

                        lines.push( linea.trim() );
                    }
                } );
            } );

            while ( lines.length && '' === lines[ lines.length - 1 ] ) {
                lines.pop();
            }

            return lines.join( '\n' );
        }

        function changePage( page ) {
            if ( page < 1 || page > state.pagination.totalPages ) {
                return;
            }
            state.pagination.page = page;
            loadSongs();
        }

        function loadSongs() {
            state.listLoading = true;
            render();

            api.listSongs().then( ( response ) => {
                state.songs = Array.isArray( response.data )
                    ? response.data.map( ( item ) => ( {
                        ...item,
                        colecciones: normalizeSongCollections( item.colecciones ),
                    } ) )
                    : [];
                state.pagination.totalItems = parseInt( response.headers.get( 'X-WP-Total' ), 10 ) || state.songs.length;
                state.pagination.totalPages = parseInt( response.headers.get( 'X-WP-TotalPages' ), 10 ) || 1;
            } ).catch( () => {
                setError( data.strings.loadSongsError || 'No fue posible cargar la lista de canciones.' );
            } ).finally( () => {
                state.listLoading = false;
                render();
            } );
        }

        function loadCollections() {
            if ( state.collections.loading ) {
                return;
            }

            state.collections.loading = true;
            state.collections.error = null;
            render();

            api.listCollections().then( ( response ) => {
                const items = Array.isArray( response.data ) ? response.data : [];
                state.collections.items = items;

                if ( state.filters.coleccion ) {
                    const exists = items.some( ( item ) => String( item.id ) === String( state.filters.coleccion ) );
                    if ( ! exists ) {
                        state.filters.coleccion = '';
                    }
                }
            } ).catch( () => {
                state.collections.error = data.strings.collectionsLoadError || 'No fue posible obtener las colecciones.';
            } ).finally( () => {
                state.collections.loading = false;
                render();
            } );
        }

        async function ensureCollectionsCatalog() {
            if ( state.collections.catalogLoading || state.collections.catalog.length ) {
                return;
            }

            state.collections.catalogLoading = true;
            render();

            const perPage = 100;
            let page = 1;
            const aggregated = [];

            try {
                while ( true ) {
                    const response = await api.listSongs( { page, per_page: perPage }, false );
                    const songs = Array.isArray( response.data ) ? response.data : [];
                    aggregated.push( ...songs.map( ( song ) => ( {
                        id: song.id,
                        titulo: song.titulo || '',
                    } ) ) );

                    const totalPages = parseInt( response.headers.get( 'X-WP-TotalPages' ), 10 ) || 1;
                    if ( page >= totalPages || songs.length < perPage ) {
                        break;
                    }
                    page += 1;
                }

                const map = new Map();
                aggregated.forEach( ( item ) => {
                    if ( item && item.id ) {
                        map.set( item.id, item );
                    }
                } );

                state.collections.catalog = Array.from( map.values() );
                syncActiveCollectionItems();
            } catch ( error ) {
                state.collections.error = data.strings.collectionsCatalogError || 'No fue posible cargar el catálogo de canciones.';
            } finally {
                state.collections.catalogLoading = false;
                render();
            }
        }

        function normalizeCollectionDetail( detail ) {
            const base = createEmptyCollection();

            if ( ! detail ) {
                return base;
            }

            base.id = detail.id || null;
            base.nombre = detail.nombre || '';
            base.descripcion = detail.descripcion || '';

            const orden = Array.isArray( detail.orden )
                ? detail.orden.map( ( id ) => parseInt( id, 10 ) ).filter( ( id ) => ! Number.isNaN( id ) && id > 0 )
                : [];

            const items = Array.isArray( detail.items )
                ? detail.items.map( ( item ) => ( {
                    id: item.id,
                    titulo: item.titulo || '',
                } ) )
                : [];

            base.orden = orden.length ? orden : items.map( ( item ) => item.id );
            base.items = items;

            return base;
        }

        function setActiveCollection( id ) {
            if ( state.collections.detailLoading ) {
                return;
            }

            if ( ! id ) {
                state.collections.activeId = null;
                state.collections.active = createEmptyCollection();
                state.collections.feedback = null;
                render();
                return;
            }

            state.collections.detailLoading = true;
            state.collections.activeId = id;
            state.collections.feedback = null;
            render();

            api.getCollection( id ).then( ( response ) => {
                state.collections.active = normalizeCollectionDetail( response.data );
                syncActiveCollectionItems();
                state.collections.error = null;
            } ).catch( () => {
                state.collections.error = data.strings.collectionLoadError || 'No fue posible cargar la colección seleccionada.';
                state.collections.activeId = null;
                state.collections.active = createEmptyCollection();
            } ).finally( () => {
                state.collections.detailLoading = false;
                render();
            } );
        }

        function updateActiveCollectionField( field, value ) {
            if ( ! state.collections.active ) {
                state.collections.active = createEmptyCollection();
            }

            state.collections.active[ field ] = value;
            state.collections.feedback = null;
        }

        function addSongToActiveCollection( songId ) {
            if ( ! state.collections.active ) {
                return;
            }

            const id = parseInt( songId, 10 );
            if ( Number.isNaN( id ) || id <= 0 ) {
                return;
            }

            if ( state.collections.active.orden.includes( id ) ) {
                return;
            }

            state.collections.active.orden.push( id );

            const catalogItem = state.collections.catalog.find( ( item ) => item.id === id );
            if ( catalogItem && ! state.collections.active.items.find( ( item ) => item.id === id ) ) {
                state.collections.active.items.push( { id, titulo: catalogItem.titulo } );
            }

            syncActiveCollectionItems();
        }

        function removeSongFromActiveCollection( index ) {
            if ( ! state.collections.active ) {
                return;
            }

            if ( index < 0 || index >= state.collections.active.orden.length ) {
                return;
            }

            const removedId = state.collections.active.orden.splice( index, 1 )[ 0 ];
            state.collections.active.items = state.collections.active.items.filter( ( item ) => item.id !== removedId );
            syncActiveCollectionItems();
        }

        function moveSongInActiveCollection( index, direction ) {
            if ( ! state.collections.active ) {
                return;
            }

            const target = index + direction;
            if ( target < 0 || target >= state.collections.active.orden.length ) {
                return;
            }

            const ids = state.collections.active.orden;
            const temp = ids[ index ];
            ids[ index ] = ids[ target ];
            ids[ target ] = temp;

            syncActiveCollectionItems();
        }

        function syncActiveCollectionItems() {
            if ( ! state.collections.active ) {
                return;
            }

            const ids = state.collections.active.orden;
            const map = new Map();
            state.collections.active.items.forEach( ( item ) => {
                map.set( item.id, item );
            } );

            state.collections.active.items = ids.map( ( id ) => {
                if ( map.has( id ) ) {
                    return map.get( id );
                }

                const catalogItem = state.collections.catalog.find( ( entry ) => entry.id === id );
                return catalogItem ? { id, titulo: catalogItem.titulo } : { id, titulo: `#${ id }` };
            } );
        }

        function handleCollectionSave() {
            if ( state.collections.saving || ! state.collections.active ) {
                return;
            }

            const payload = {
                id: state.collections.active.id,
                nombre: ( state.collections.active.nombre || '' ).trim(),
                descripcion: state.collections.active.descripcion || '',
                orden: state.collections.active.orden,
            };

            if ( ! payload.nombre ) {
                state.collections.error = data.strings.collectionNameRequired || 'El nombre de la colección es obligatorio.';
                render();
                return;
            }

            state.collections.saving = true;
            state.collections.error = null;
            render();

            api.saveCollection( payload ).then( ( response ) => {
                const detail = normalizeCollectionDetail( response.data );
                state.collections.active = detail;
                syncActiveCollectionItems();
                state.collections.activeId = detail.id;
                state.collections.feedback = data.strings.collectionSaved || 'Colección guardada.';
                state.collections.error = null;

                const existingIndex = state.collections.items.findIndex( ( item ) => item.id === detail.id );
                if ( existingIndex > -1 ) {
                    state.collections.items[ existingIndex ] = {
                        id: detail.id,
                        nombre: detail.nombre,
                        descripcion: detail.descripcion,
                        items_count: detail.orden.length,
                    };
                } else {
                    state.collections.items.push( {
                        id: detail.id,
                        nombre: detail.nombre,
                        descripcion: detail.descripcion,
                        items_count: detail.orden.length,
                    } );
                }

                loadSongs();
            } ).catch( ( error ) => {
                const message = ( error && error.payload && error.payload.message )
                    ? error.payload.message
                    : ( data.strings.collectionSaveError || 'No fue posible guardar la colección.' );
                state.collections.error = message;
            } ).finally( () => {
                state.collections.saving = false;
                render();
            } );
        }

        function handleCollectionDelete() {
            if ( state.collections.deleting || ! state.collections.active || ! state.collections.active.id ) {
                return;
            }

            if ( ! window.confirm( data.strings.collectionDeleteConfirm || '¿Eliminar la colección seleccionada?' ) ) {
                return;
            }

            state.collections.deleting = true;
            state.collections.error = null;
            render();

            const deletedId = state.collections.active.id;

            api.deleteCollection( deletedId ).then( () => {
                state.collections.items = state.collections.items.filter( ( item ) => item.id !== deletedId );
                state.collections.feedback = data.strings.collectionDeleted || 'Colección eliminada.';
                state.collections.active = createEmptyCollection();
                state.collections.activeId = null;
                if ( state.filters.coleccion && String( state.filters.coleccion ) === String( deletedId ) ) {
                    state.filters.coleccion = '';
                    loadSongs();
                }
                if ( state.readingQueue.coleccionId && String( state.readingQueue.coleccionId ) === String( deletedId ) ) {
                    clearReadingQueue();
                }
            } ).catch( ( error ) => {
                const message = ( error && error.payload && error.payload.message )
                    ? error.payload.message
                    : ( data.strings.collectionDeleteError || 'No fue posible eliminar la colección.' );
                state.collections.error = message;
            } ).finally( () => {
                state.collections.deleting = false;
                render();
            } );
        }

        function clearReadingQueue() {
            state.readingQueue = {
                ids: [],
                index: 0,
                coleccionId: null,
                nombre: '',
            };
        }

        function setReadingMode( mode ) {
            if ( ! mode || state.readingMode === mode ) {
                return;
            }

            state.readingMode = mode;
            render();
        }

        function startReadingCollection( coleccionId ) {
            if ( ! coleccionId ) {
                return;
            }

            state.collections.detailLoading = true;
            state.error = null;
            render();

            api.getCollection( coleccionId ).then( ( response ) => {
                const detail = normalizeCollectionDetail( response.data );
                if ( ! detail.orden.length ) {
                    state.error = data.strings.collectionEmpty || 'La colección no tiene canciones asignadas.';
                    return;
                }

                state.readingQueue = {
                    ids: detail.orden.slice(),
                    index: 0,
                    coleccionId: detail.id,
                    nombre: detail.nombre || '',
                };

                state.activeTab = 'reading';
                selectSong( detail.orden[ 0 ], { targetTab: 'reading', silent: true, preserveQueue: true } );
            } ).catch( () => {
                state.error = data.strings.collectionLoadError || 'No fue posible cargar la colección seleccionada.';
            } ).finally( () => {
                state.collections.detailLoading = false;
                render();
            } );
        }

        function goToReadingIndex( index ) {
            if ( ! state.readingQueue.ids.length ) {
                return;
            }

            if ( index < 0 || index >= state.readingQueue.ids.length ) {
                return;
            }

            state.readingQueue.index = index;
            const songId = state.readingQueue.ids[ index ];
            selectSong( songId, { targetTab: 'reading', silent: true, preserveQueue: true } );
        }

        function goToNextInQueue() {
            goToReadingIndex( state.readingQueue.index + 1 );
        }

        function goToPrevInQueue() {
            goToReadingIndex( state.readingQueue.index - 1 );
        }

        function escapeHtml( string ) {
            if ( 'string' !== typeof string ) {
                return '';
            }
            return string.replace( /&/g, '&amp;' )
                .replace( /</g, '&lt;' )
                .replace( />/g, '&gt;' )
                .replace( /"/g, '&quot;' )
                .replace( /'/g, '&#039;' );
        }

        function escapeAttr( string ) {
            return escapeHtml( string ).replace( /`/g, '&#x60;' );
        }
    } );
} )();
