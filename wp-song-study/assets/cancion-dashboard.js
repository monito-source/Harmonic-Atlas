( function() {
    if ( 'undefined' === typeof window.WPSS ) {
        return;
    }

    const data = window.WPSS;

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
        };

        const api = {
            async listSongs() {
                const params = new URLSearchParams();
                params.set( 'page', state.pagination.page );
                params.set( 'per_page', 20 );

                if ( state.filters.tonica ) {
                    params.set( 'tonica', state.filters.tonica );
                }
                if ( '' !== state.filters.con_prestamos ) {
                    params.set( 'con_prestamos', state.filters.con_prestamos );
                }
                if ( '' !== state.filters.con_modulaciones ) {
                    params.set( 'con_modulaciones', state.filters.con_modulaciones );
                }

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
        };

        if ( 'new' === state.view ) {
            state.feedback = null;
            state.activeTab = 'editor';
        }

        normalizeVerseOrder();
        refreshCampoNames();
        render();
        loadSongs();

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
                tiene_prestamos: false,
                tiene_modulaciones: false,
            };
        }

        function createEmptySegment() {
            return { texto: '', acorde: '' };
        }

        function createEmptyVerse( orden ) {
            return {
                orden: orden || 1,
                segmentos: [ createEmptySegment() ],
                comentario: '',
                evento_armonico: null,
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

        function normalizeVerseOrder() {
            if ( ! Array.isArray( state.editingSong.versos ) ) {
                state.editingSong.versos = [];
                return;
            }

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
            } );
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
                </div>
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
                return `
                    <tr class="${ selected }" data-action="select-song" data-id="${ song.id }">
                        <td class="wpss-col-title">
                            <strong>${ escapeHtml( song.titulo ) }</strong>
                            <span class="wpss-sub">${ escapeHtml( tonicaLabel || '—' ) }${ campoLabel }</span>
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
                            <h3>Versos</h3>
                            <button type="button" class="button button-secondary" data-action="add-verso">Añadir verso</button>
                        </header>
                        ${ renderVersos() }
                    </div>
                </form>
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

            return `
                <div class="wpss-reading">
                    <div class="wpss-reading__header">
                        <h3>${ escapeHtml( song.titulo || data.strings.newSong ) }</h3>
                        <p><strong>Tónica:</strong> ${ escapeHtml( song.tonica || '—' ) }</p>
                        <p><strong>Campo armónico:</strong> ${ escapeHtml( song.campo_armonico || '—' ) }</p>
                        <button type="button" class="button" data-action="copy-reading">${ escapeHtml( data.strings.copyAsText || 'Copiar como texto' ) }</button>
                    </div>
                    <ol class="wpss-reading__verses">
                        ${ song.versos.map( ( verso ) => renderReadingVerse( verso ) ).join( '' ) }
                    </ol>
                </div>
            `;
        }

        function renderReadingVerse( verso ) {
            const segmentos = Array.isArray( verso.segmentos ) ? verso.segmentos : [];
            const partes = segmentos.map( ( segmento ) => {
                const acorde = segmento.acorde ? `<span class="wpss-reading__chord">[${ escapeHtml( segmento.acorde ) }]</span>` : '';
                const texto = escapeHtml( segmento.texto || '' );
                return `${ acorde } ${ texto }`;
            } ).join( '' ).trim();

            const evento = renderEventoChip( verso.evento_armonico );
            const comentario = verso.comentario ? `<span class="wpss-reading__comment">${ escapeHtml( verso.comentario ) }</span>` : '';

            return `
                <li>
                    <div class="wpss-reading__line">${ partes }</div>
                    <div class="wpss-reading__meta">${ evento } ${ comentario }</div>
                </li>
            `;
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
            case 'add-verso':
                state.editingSong.versos.push( createEmptyVerse( state.editingSong.versos.length + 1 ) );
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

                state.editingSong[ model ][ index ][ field ] = value;
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
                case 'verse-event-type':
                    updateVerseEventType( parseInt( event.target.dataset.index, 10 ), event.target.value );
                    render();
                    return;
                case 'campo-toggle':
                    toggleCampoActivo( parseInt( event.target.dataset.index, 10 ), event.target.checked );
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

        function selectSong( id ) {
            if ( state.songLoading || state.saving || ! id ) {
                return;
            }

            state.songLoading = true;
            state.selectedSongId = id;
            state.feedback = null;
            state.error = null;
            state.activeTab = 'editor';
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
                    tiene_prestamos: !! song.tiene_prestamos,
                    tiene_modulaciones: !! song.tiene_modulaciones,
                };
                normalizeVerseOrder();
                setFeedback( data.strings.songLoaded || 'Canción cargada.' );
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
                versos: song.versos.map( ( verso ) => ( {
                    orden: verso.orden,
                    segmentos: verso.segmentos,
                    comentario: verso.comentario,
                    evento_armonico: verso.evento_armonico,
                } ) ),
            };

            api.saveSong( payload ).then( ( response ) => {
                const body = response.data || {};
                state.editingSong.id = body.id;
                state.editingSong.tiene_prestamos = !! body.tiene_prestamos;
                state.editingSong.tiene_modulaciones = !! body.tiene_modulaciones;
                state.selectedSongId = body.id;
                setFeedback( data.strings.saved || 'Cambios guardados.' );
                loadSongs();
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
            lines.push( '' );

            song.versos.forEach( ( verso ) => {
                const segmentos = Array.isArray( verso.segmentos ) ? verso.segmentos : [];
                const partes = segmentos.map( ( segmento ) => {
                    const acorde = segmento.acorde ? `[${ segmento.acorde }]` : '';
                    return `${ acorde } ${ segmento.texto || '' }`.trim();
                } ).join( ' ' ).trim();

                let linea = partes;
                if ( verso.evento_armonico && verso.evento_armonico.tipo ) {
                    if ( 'modulacion' === verso.evento_armonico.tipo ) {
                        const destino = [ verso.evento_armonico.tonica_destino || '', verso.evento_armonico.campo_armonico_destino || '' ].filter( Boolean ).join( ' ' );
                        linea += ` \u2014 Modulación → ${ destino || '—' }`;
                    } else if ( 'prestamo' === verso.evento_armonico.tipo ) {
                        const origen = [ verso.evento_armonico.tonica_origen || '', verso.evento_armonico.campo_armonico_origen || '' ].filter( Boolean ).join( ' ' );
                        linea += ` \u2014 Préstamo ← ${ origen || '—' }`;
                    }
                }

                if ( verso.comentario ) {
                    linea += ` (${ verso.comentario })`;
                }

                lines.push( linea.trim() );
            } );

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
                state.songs = Array.isArray( response.data ) ? response.data : [];
                state.pagination.totalItems = parseInt( response.headers.get( 'X-WP-Total' ), 10 ) || state.songs.length;
                state.pagination.totalPages = parseInt( response.headers.get( 'X-WP-TotalPages' ), 10 ) || 1;
            } ).catch( () => {
                setError( data.strings.loadSongsError || 'No fue posible cargar la lista de canciones.' );
            } ).finally( () => {
                state.listLoading = false;
                render();
            } );
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
