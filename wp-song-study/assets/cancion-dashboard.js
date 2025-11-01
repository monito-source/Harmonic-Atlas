( function() {
    if ( 'undefined' === typeof window.wpssCancionData ) {
        return;
    }

    const data = window.wpssCancionData;

    document.addEventListener( 'DOMContentLoaded', () => {
        const container = document.getElementById( 'wpss-cancion-app' );
        if ( ! container ) {
            return;
        }

        const state = {
            view: container.dataset.view || 'dashboard',
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
        };

        if ( 'new' === state.view ) {
            state.feedback = null;
        }

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

                return request( `${ data.restUrl }canciones?${ params.toString() }` );
            },
            async getSong( id ) {
                return request( `${ data.restUrl }cancion/${ id }` );
            },
            async saveSong( payload ) {
                return request( `${ data.restUrl }cancion`, {
                    method: 'POST',
                    body: JSON.stringify( payload ),
                } );
            },
        };

        function request( url, options = {} ) {
            const config = {
                method: options.method || 'GET',
                headers: {
                    'X-WPSS-Nonce': data.wpssNonce,
                },
            };

            if ( options.body ) {
                config.body = options.body;
                config.headers[ 'Content-Type' ] = 'application/json';
            }

            return fetch( url, config ).then( async ( response ) => {
                const payload = await parseResponse( response );
                if ( ! response.ok ) {
                    const error = new Error( 'Request failed' );
                    error.status = response.status;
                    error.payload = payload;
                    throw error;
                }
                return {
                    data: payload,
                    headers: response.headers,
                };
            } );
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

        function normalizeVerseOrder() {
            state.editingSong.versos.forEach( ( verso, index ) => {
                verso.orden = index + 1;
                if ( ! verso.evento_armonico || 'object' !== typeof verso.evento_armonico ) {
                    verso.evento_armonico = null;
                }
            } );
        }

        function setFeedback( message, type = 'success' ) {
            state.feedback = { message, type };
            state.error = null;
        }

        function setError( message ) {
            state.error = message;
            state.feedback = null;
        }

        function resetEditor() {
            state.editingSong = createEmptySong();
            state.selectedSongId = null;
            state.feedback = null;
            state.error = null;
            render();
        }

        function render() {
            container.innerHTML = `
                <div class="wpss-app-layout">
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
                    <section class="wpss-panel wpss-panel--editor">
                        <header class="wpss-panel__header">
                            <div>
                                <h2>${ escapeHtml( state.editingSong.id ? state.editingSong.titulo || data.strings.newSong : data.strings.newSong ) }</h2>
                                <p class="wpss-panel__meta">${ state.editingSong.id ? `ID ${ state.editingSong.id }` : '—' }</p>
                            </div>
                            <div class="wpss-panel__actions">
                                <button class="button" data-action="reset-editor">${ escapeHtml( data.strings.newSong ) }</button>
                                <button class="button button-primary" data-action="save-song" ${ state.saving ? 'disabled' : '' }>${ state.saving ? escapeHtml( data.strings.saving ) : escapeHtml( data.strings.saveSong ) }</button>
                            </div>
                        </header>
                        ${ renderStatus() }
                        ${ renderEditorForm() }
                    </section>
                </div>
                ${ renderDatalist() }
            `;
        }

        function renderStatus() {
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
            const campos = Array.isArray( data.camposArmonicos ) ? data.camposArmonicos : [];

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
                                <button type="button" class="button-link-delete" data-action="remove-prestamo" data-index="${ index }">Eliminar</button>
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
                                <button type="button" class="button-link-delete" data-action="remove-modulacion" data-index="${ index }">Eliminar</button>
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

            const rows = versos.map( ( verso, index ) => `
                <tr>
                    <td>${ index + 1 }</td>
                    <td>
                        <input type="text" data-model="versos" data-field="texto" data-index="${ index }" value="${ escapeAttr( verso.texto || '' ) }" />
                    </td>
                    <td>
                        <input type="text" data-model="versos" data-field="acorde" data-index="${ index }" value="${ escapeAttr( verso.acorde || '' ) }" />
                    </td>
                    <td>
                        ${ renderVerseEventSelect( verso, index ) }
                    </td>
                    <td>
                        ${ renderVerseDetail( verso, index ) }
                    </td>
                    <td class="wpss-verse-actions">
                        <button type="button" class="button button-small" data-action="verse-up" data-index="${ index }" ${ index === 0 ? 'disabled' : '' }>↑</button>
                        <button type="button" class="button button-small" data-action="verse-down" data-index="${ index }" ${ index === versos.length - 1 ? 'disabled' : '' }>↓</button>
                        <button type="button" class="button button-link-delete" data-action="remove-verso" data-index="${ index }">Eliminar</button>
                    </td>
                </tr>
            ` );

            return `
                <div class="wpss-table-wrapper">
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Verso / etiqueta</th>
                                <th>Acorde</th>
                                <th>Evento</th>
                                <th>Detalle</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            ${ rows.join( '' ) }
                        </tbody>
                    </table>
                </div>
            `;
        }

        function renderVerseEventSelect( verso, index ) {
            const tipo = verso.evento_armonico && verso.evento_armonico.tipo ? verso.evento_armonico.tipo : '';

            return `
                <select data-action="verse-event-type" data-index="${ index }">
                    <option value="" ${ '' === tipo ? 'selected' : '' }>Ninguno</option>
                    <option value="modulacion" ${ 'modulacion' === tipo ? 'selected' : '' }>Modulación</option>
                    <option value="prestamo" ${ 'prestamo' === tipo ? 'selected' : '' }>Préstamo</option>
                </select>
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
                    ${ eventFields }
                </div>
            `;
        }

        function renderDatalist() {
            const tonicas = Array.isArray( data.tonicas ) ? data.tonicas : [];
            const campos = Array.isArray( data.camposArmonicos ) ? data.camposArmonicos : [];

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

        container.addEventListener( 'click', ( event ) => {
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
                state.editingSong.versos.push( { orden: state.editingSong.versos.length + 1, texto: '', acorde: '', comentario: '', evento_armonico: null } );
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
            case 'select-song':
                selectSong( parseInt( target.dataset.id, 10 ) );
                break;
            case 'page-prev':
                changePage( state.pagination.page - 1 );
                break;
            case 'page-next':
                changePage( state.pagination.page + 1 );
                break;
            }
        } );

        container.addEventListener( 'input', ( event ) => {
            const action = event.target.dataset.action;

            if ( 'verse-event-field' === action ) {
                const index = parseInt( event.target.dataset.index, 10 );
                const field = event.target.dataset.field;

                if ( Number.isNaN( index ) || ! field ) {
                    return;
                }

                const verso = state.editingSong.versos[ index ];
                if ( ! verso || ! verso.evento_armonico || 'object' !== typeof verso.evento_armonico ) {
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
                if ( Number.isNaN( index ) ) {
                    return;
                }

                if ( ! state.editingSong[ model ][ index ] ) {
                    return;
                }

                state.editingSong[ model ][ index ][ field ] = value;
            }
        } );

        container.addEventListener( 'change', ( event ) => {
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
                }
            }

            const model = event.target.dataset.model;
            if ( ! model ) {
                return;
            }

            const field = event.target.dataset.field;
            const value = event.target.value;

            if ( 'general' === model ) {
                state.editingSong[ field ] = value;
            }
        } );

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

        function reorderVerse( index, direction ) {
            const targetIndex = index + direction;
            if ( targetIndex < 0 || targetIndex >= state.editingSong.versos.length ) {
                return;
            }

            const temp = state.editingSong.versos[ index ];
            state.editingSong.versos[ index ] = state.editingSong.versos[ targetIndex ];
            state.editingSong.versos[ targetIndex ] = temp;
            normalizeVerseOrder();
            render();
        }

        function changePage( page ) {
            if ( page < 1 || page > state.pagination.totalPages ) {
                return;
            }
            state.pagination.page = page;
            loadSongs();
        }

        function selectSong( id ) {
            if ( state.songLoading || state.saving ) {
                return;
            }

            state.songLoading = true;
            state.selectedSongId = id;
            state.feedback = null;
            state.error = null;
            render();

            api.getSong( id ).then( ( response ) => {
                const song = response.data;
                state.editingSong = {
                    id: song.id,
                    titulo: song.titulo || '',
                    tonica: song.tonica || song.tonalidad || '',
                    campo_armonico: song.campo_armonico || '',
                    campo_armonico_predominante: song.campo_armonico_predominante || '',
                    prestamos: Array.isArray( song.prestamos ) ? song.prestamos : [],
                    modulaciones: Array.isArray( song.modulaciones ) ? song.modulaciones : [],
                    versos: Array.isArray( song.versos ) ? song.versos : [],
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

            state.saving = true;
            setFeedback( data.strings.saving, 'info' );
            render();

            const payload = {
                id: song.id || null,
                titulo: song.titulo,
                tonica: song.tonica,
                campo_armonico: song.campo_armonico,
                campo_armonico_predominante: song.campo_armonico_predominante,
                prestamos_cancion: song.prestamos,
                modulaciones_cancion: song.modulaciones,
                versos: song.versos,
            };

            api.saveSong( payload ).then( ( response ) => {
                const body = response.data || {};
                state.editingSong.id = body.id;
                state.editingSong.tiene_prestamos = !! body.tiene_prestamos;
                state.editingSong.tiene_modulaciones = !! body.tiene_modulaciones;
                state.selectedSongId = body.id;
                setFeedback( data.strings.saved );
                loadSongs();
            } ).catch( ( error ) => {
                const message = ( error.payload && error.payload.message ) ? error.payload.message : data.strings.error;
                setError( message );
                render();
            } ).finally( () => {
                state.saving = false;
            } );
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

        render();
        loadSongs();
    } );
} )();
