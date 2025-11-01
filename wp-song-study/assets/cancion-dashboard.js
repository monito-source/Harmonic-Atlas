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
                tonalidad: '',
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

                if ( state.filters.tonalidad ) {
                    params.set( 'tonalidad', state.filters.tonalidad );
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
                tonalidad: '',
                campo_armonico: '',
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
            const tonalidadOptions = data.tonalidades || [];
            const tonalidadSelect = [ '<option value="">Todas</option>' ];
            tonalidadOptions.forEach( ( term ) => {
                const selected = term.slug === state.filters.tonalidad ? 'selected' : '';
                tonalidadSelect.push( `<option value="${ escapeAttr( term.slug ) }" ${ selected }>${ escapeHtml( term.name ) }</option>` );
            } );

            return `
                <div class="wpss-filters">
                    <label>
                        <span>Tonalidad</span>
                        <select data-action="filter-tonalidad">
                            ${ tonalidadSelect.join( '' ) }
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
                return `
                    <tr class="${ selected }" data-action="select-song" data-id="${ song.id }">
                        <td class="wpss-col-title">
                            <strong>${ escapeHtml( song.titulo ) }</strong>
                            <span class="wpss-sub">${ escapeHtml( song.tonalidad || '—' ) }</span>
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

            return `
                <form class="wpss-editor" novalidate>
                    <div class="wpss-field-group">
                        <label>
                            <span>Título</span>
                            <input type="text" required data-model="general" data-field="titulo" value="${ escapeAttr( song.titulo ) }" />
                        </label>
                        <label>
                            <span>Tonalidad</span>
                            <input type="text" required data-model="general" data-field="tonalidad" value="${ escapeAttr( song.tonalidad ) }" list="wpss-tonalidades" />
                        </label>
                    </div>
                    <div class="wpss-field">
                        <label>
                            <span>Campo armónico predominante</span>
                            <textarea data-model="general" data-field="campo_armonico">${ escapeHtml( song.campo_armonico ) }</textarea>
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
                        <input type="text" data-model="versos" data-field="comentario" data-index="${ index }" value="${ escapeAttr( verso.comentario || '' ) }" />
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
                                <th>Comentario</th>
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

        function renderDatalist() {
            const tonalidadOptions = data.tonalidades || [];
            if ( ! tonalidadOptions.length ) {
                return '';
            }

            return `
                <datalist id="wpss-tonalidades">
                    ${ tonalidadOptions.map( ( term ) => `<option value="${ escapeAttr( term.name ) }"></option>` ).join( '' ) }
                </datalist>
            `;
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
                state.editingSong.versos.push( { orden: state.editingSong.versos.length + 1, texto: '', acorde: '', comentario: '' } );
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
            if ( ! action ) {
                return;
            }

            switch ( action ) {
            case 'filter-tonalidad':
                state.filters.tonalidad = event.target.value;
                state.pagination.page = 1;
                loadSongs();
                break;
            case 'filter-prestamos':
                state.filters.con_prestamos = event.target.value;
                state.pagination.page = 1;
                loadSongs();
                break;
            case 'filter-modulaciones':
                state.filters.con_modulaciones = event.target.value;
                state.pagination.page = 1;
                loadSongs();
                break;
            }
        } );

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
                    tonalidad: song.tonalidad || '',
                    campo_armonico: song.campo_armonico || '',
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

            if ( ! song.tonalidad.trim() ) {
                setError( data.strings.tonalityRequired || 'La tonalidad es obligatoria.' );
                render();
                return;
            }

            state.saving = true;
            setFeedback( data.strings.saving, 'info' );
            render();

            const payload = {
                id: song.id || null,
                titulo: song.titulo,
                tonalidad: song.tonalidad,
                campo_armonico: song.campo_armonico,
                prestamos: song.prestamos,
                modulaciones: song.modulaciones,
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
