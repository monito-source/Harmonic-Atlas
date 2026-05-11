<?php
/**
 * Centraliza proyectos, membresías y presskits para plugin y tema.
 *
 * @package WP_Song_Study_Blocks
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'WPSSB_PROJECTS_CENTRALIZED' ) ) {
    define( 'WPSSB_PROJECTS_CENTRALIZED', true );
}

if ( ! defined( 'WPSSB_COLLABORATOR_CAP' ) ) {
    define( 'WPSSB_COLLABORATOR_CAP', 'pd_colaborador' );
}

if ( ! defined( 'WPSSB_PROJECT_POST_TYPE' ) ) {
    define( 'WPSSB_PROJECT_POST_TYPE', 'proyecto' );
}

if ( ! defined( 'WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE' ) ) {
    define( 'WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE', 'presskit' );
}

if ( ! defined( 'WPSSB_PROJECT_AREA_TAX' ) ) {
    define( 'WPSSB_PROJECT_AREA_TAX', 'area_proyecto' );
}

if ( ! defined( 'WPSSB_COLLABORATOR_TARGET_META' ) ) {
    define( 'WPSSB_COLLABORATOR_TARGET_META', '_wpssb_collaborator_user_id' );
}

if ( ! defined( 'WPSSB_COLLABORATOR_PRESSKIT_USER_META' ) ) {
    define( 'WPSSB_COLLABORATOR_PRESSKIT_USER_META', '_wpssb_collaborator_presskit_post_id' );
}

/**
 * Sanitiza listas de IDs.
 *
 * @param mixed $value Valor crudo.
 * @return int[]
 */
function wpssb_sanitize_id_list( $value ) {
    if ( empty( $value ) ) {
        return [];
    }

    if ( is_string( $value ) ) {
        $value = array_filter( array_map( 'trim', explode( ',', $value ) ) );
    }

    $ids = array_filter(
        array_map(
            static function ( $item ) {
                return max( 0, (int) $item );
            },
            (array) $value
        )
    );

    return array_values( array_unique( $ids ) );
}

/**
 * Registra el rol reutilizable para colaboradores.
 *
 * @return void
 */
function wpssb_register_collaborator_role() {
    if ( null === get_role( 'pd_colaborador' ) ) {
        add_role(
            'pd_colaborador',
            __( 'Colaborador digital', 'wp-song-study-blocks' ),
            [
                'read'                 => true,
                'upload_files'         => true,
                WPSSB_COLLABORATOR_CAP => true,
            ]
        );
    }

    $role = get_role( 'pd_colaborador' );
    if ( $role && ! $role->has_cap( 'upload_files' ) ) {
        $role->add_cap( 'upload_files' );
    }

    $collaborator_presskit_caps = [
        'read_presskit',
        'edit_presskit',
        'delete_presskit',
        'edit_presskits',
        'publish_presskits',
        'delete_presskits',
        'delete_published_presskits',
        'edit_published_presskits',
    ];

    $admin_presskit_caps = array_merge(
        $collaborator_presskit_caps,
        [
            'edit_others_presskits',
            'read_private_presskits',
            'delete_private_presskits',
            'delete_others_presskits',
            'edit_private_presskits',
        ]
    );

    if ( $role ) {
        foreach ( $collaborator_presskit_caps as $cap ) {
            if ( ! $role->has_cap( $cap ) ) {
                $role->add_cap( $cap );
            }
        }

        foreach ( array_diff( $admin_presskit_caps, $collaborator_presskit_caps ) as $cap ) {
            if ( $role->has_cap( $cap ) ) {
                $role->remove_cap( $cap );
            }
        }
    }

    if ( function_exists( 'wpss_add_cap_to_role' ) ) {
        wpss_add_cap_to_role( 'pd_colaborador', WPSSB_COLLABORATOR_CAP );

        if ( defined( 'WPSS_ROLE_COLEGA' ) ) {
            wpss_add_cap_to_role( WPSS_ROLE_COLEGA, WPSSB_COLLABORATOR_CAP );
            wpss_add_cap_to_role( WPSS_ROLE_COLEGA, 'upload_files' );
            foreach ( $collaborator_presskit_caps as $cap ) {
                wpss_add_cap_to_role( WPSS_ROLE_COLEGA, $cap );
            }

            $colega_role = get_role( WPSS_ROLE_COLEGA );

            if ( $colega_role ) {
                foreach ( array_diff( $admin_presskit_caps, $collaborator_presskit_caps ) as $cap ) {
                    if ( $colega_role->has_cap( $cap ) ) {
                        $colega_role->remove_cap( $cap );
                    }
                }
            }
        }

        wpss_add_cap_to_role( 'administrator', WPSSB_COLLABORATOR_CAP );
        foreach ( $admin_presskit_caps as $cap ) {
            wpss_add_cap_to_role( 'administrator', $cap );
        }
    }

    $admin_role = get_role( 'administrator' );

    if ( $admin_role ) {
        foreach ( $admin_presskit_caps as $cap ) {
            if ( ! $admin_role->has_cap( $cap ) ) {
                $admin_role->add_cap( $cap );
            }
        }
    }
}
add_action( 'init', 'wpssb_register_collaborator_role' );

/**
 * Registra el CPT editable de presskits personales.
 *
 * @return void
 */
function wpssb_register_collaborator_presskit_post_type() {
    if ( post_type_exists( WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE ) ) {
        return;
    }

    $labels = [
        'name'               => __( 'Presskits personales', 'wp-song-study-blocks' ),
        'singular_name'      => __( 'Presskit personal', 'wp-song-study-blocks' ),
        'add_new'            => __( 'Añadir nuevo', 'wp-song-study-blocks' ),
        'add_new_item'       => __( 'Añadir nuevo presskit personal', 'wp-song-study-blocks' ),
        'edit_item'          => __( 'Editar presskit personal', 'wp-song-study-blocks' ),
        'new_item'           => __( 'Nuevo presskit personal', 'wp-song-study-blocks' ),
        'view_item'          => __( 'Ver presskit personal', 'wp-song-study-blocks' ),
        'search_items'       => __( 'Buscar presskits personales', 'wp-song-study-blocks' ),
        'not_found'          => __( 'No se encontraron presskits personales', 'wp-song-study-blocks' ),
        'not_found_in_trash' => __( 'No hay presskits personales en la papelera', 'wp-song-study-blocks' ),
        'all_items'          => __( 'Todos los presskits personales', 'wp-song-study-blocks' ),
    ];

    register_post_type(
        WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE,
        [
            'labels'          => $labels,
            'public'          => true,
            'show_in_rest'    => true,
            'show_in_menu'    => true,
            'menu_icon'       => 'dashicons-id-alt',
            'supports'        => [ 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ],
            'has_archive'     => false,
            'rewrite'         => [
                'slug' => 'presskit',
            ],
            'map_meta_cap'    => true,
            'capability_type' => [ 'presskit', 'presskits' ],
        ]
    );
}
add_action( 'init', 'wpssb_register_collaborator_presskit_post_type' );

/**
 * Registra el CPT de proyectos.
 *
 * @return void
 */
function wpssb_register_project_post_type() {
    if ( post_type_exists( WPSSB_PROJECT_POST_TYPE ) ) {
        return;
    }

    $labels = [
        'name'               => __( 'Proyectos', 'wp-song-study-blocks' ),
        'singular_name'      => __( 'Proyecto', 'wp-song-study-blocks' ),
        'add_new'            => __( 'Añadir nuevo', 'wp-song-study-blocks' ),
        'add_new_item'       => __( 'Añadir nuevo proyecto', 'wp-song-study-blocks' ),
        'edit_item'          => __( 'Editar proyecto', 'wp-song-study-blocks' ),
        'new_item'           => __( 'Nuevo proyecto', 'wp-song-study-blocks' ),
        'view_item'          => __( 'Ver proyecto', 'wp-song-study-blocks' ),
        'search_items'       => __( 'Buscar proyectos', 'wp-song-study-blocks' ),
        'not_found'          => __( 'No se encontraron proyectos', 'wp-song-study-blocks' ),
        'not_found_in_trash' => __( 'No hay proyectos en la papelera', 'wp-song-study-blocks' ),
        'all_items'          => __( 'Todos los proyectos', 'wp-song-study-blocks' ),
    ];

    register_post_type(
        WPSSB_PROJECT_POST_TYPE,
        [
            'labels'       => $labels,
            'public'       => true,
            'show_in_rest' => true,
            'menu_icon'    => 'dashicons-networking',
            'supports'     => [ 'title', 'editor', 'thumbnail', 'excerpt' ],
            'has_archive'  => true,
            'rewrite'      => [
                'slug' => 'proyecto',
            ],
        ]
    );
}
add_action( 'init', 'wpssb_register_project_post_type' );

/**
 * Registra la taxonomía de áreas de proyecto.
 *
 * @return void
 */
function wpssb_register_project_area_taxonomy() {
    if ( taxonomy_exists( WPSSB_PROJECT_AREA_TAX ) ) {
        return;
    }

    $labels = [
        'name'          => __( 'Áreas del proyecto', 'wp-song-study-blocks' ),
        'singular_name' => __( 'Área del proyecto', 'wp-song-study-blocks' ),
        'search_items'  => __( 'Buscar áreas', 'wp-song-study-blocks' ),
        'all_items'     => __( 'Todas las áreas', 'wp-song-study-blocks' ),
        'edit_item'     => __( 'Editar área', 'wp-song-study-blocks' ),
        'update_item'   => __( 'Actualizar área', 'wp-song-study-blocks' ),
        'add_new_item'  => __( 'Añadir nueva área', 'wp-song-study-blocks' ),
        'new_item_name' => __( 'Nuevo nombre de área', 'wp-song-study-blocks' ),
        'menu_name'     => __( 'Áreas', 'wp-song-study-blocks' ),
    ];

    register_taxonomy(
        WPSSB_PROJECT_AREA_TAX,
        [ WPSSB_PROJECT_POST_TYPE ],
        [
            'labels'            => $labels,
            'public'            => true,
            'hierarchical'      => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => [
                'slug' => 'area-proyecto',
            ],
        ]
    );
}
add_action( 'init', 'wpssb_register_project_area_taxonomy' );

/**
 * Registra meta del proyecto.
 *
 * @return void
 */
function wpssb_register_project_meta() {
    register_post_meta(
        WPSSB_PROJECT_POST_TYPE,
        'pd_proyecto_colaboradores',
        [
            'type'              => 'array',
            'single'            => true,
            'sanitize_callback' => 'wpssb_sanitize_id_list',
            'show_in_rest'      => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'integer',
                    ],
                ],
            ],
        ]
    );

    register_post_meta(
        WPSSB_PROJECT_POST_TYPE,
        'pd_proyecto_galeria',
        [
            'type'              => 'array',
            'single'            => true,
            'sanitize_callback' => 'wpssb_sanitize_id_list',
            'show_in_rest'      => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'integer',
                    ],
                ],
            ],
        ]
    );

    register_post_meta(
        WPSSB_PROJECT_POST_TYPE,
        'pd_proyecto_contacto',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'wp_kses_post',
            'show_in_rest'      => true,
        ]
    );

    register_post_meta(
        WPSSB_PROJECT_POST_TYPE,
        'pd_proyecto_tagline',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest'      => true,
        ]
    );

    register_post_meta(
        WPSSB_PROJECT_POST_TYPE,
        'pd_proyecto_presskit',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'wp_kses_post',
            'show_in_rest'      => true,
        ]
    );

    register_post_meta(
        WPSSB_PROJECT_POST_TYPE,
        'pd_proyecto_links',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'sanitize_textarea_field',
            'show_in_rest'      => true,
        ]
    );

    register_post_meta(
        WPSSB_PROJECT_POST_TYPE,
        'pd_proyecto_ensayos',
        [
            'type'              => 'object',
            'single'            => true,
            'sanitize_callback' => 'wpssb_sanitize_project_rehearsal_meta',
            'show_in_rest'      => false,
        ]
    );
}
add_action( 'init', 'wpssb_register_project_meta' );

/**
 * Días válidos para disponibilidad de ensayo.
 *
 * @return string[]
 */
function wpssb_get_project_rehearsal_days() {
    return [ 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday' ];
}

/**
 * Normaliza un día de disponibilidad.
 *
 * @param mixed $value Valor crudo.
 * @return string
 */
function wpssb_normalize_project_rehearsal_day( $value ) {
    $value = sanitize_key( (string) $value );

    return in_array( $value, wpssb_get_project_rehearsal_days(), true ) ? $value : 'monday';
}

/**
 * Sanitiza un horario HH:MM.
 *
 * @param mixed $value Valor crudo.
 * @return string
 */
function wpssb_sanitize_project_rehearsal_time( $value ) {
    $value = trim( (string) $value );

    if ( preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ) {
        return $value;
    }

    return '';
}

/**
 * Convierte un horario HH:MM en minutos.
 *
 * @param string $value Horario.
 * @return int
 */
function wpssb_project_rehearsal_time_to_minutes( $value ) {
    $value = wpssb_sanitize_project_rehearsal_time( $value );

    if ( '' === $value ) {
        return -1;
    }

    list( $hours, $minutes ) = array_map( 'intval', explode( ':', $value ) );
    return ( $hours * 60 ) + $minutes;
}

/**
 * Sanitiza slots de disponibilidad de ensayo.
 *
 * @param mixed $slots Slots crudos.
 * @return array<int, array<string, mixed>>
 */
function wpssb_sanitize_project_rehearsal_slots( $slots ) {
    if ( ! is_array( $slots ) ) {
        return [];
    }

    $items = [];

    foreach ( $slots as $slot ) {
        if ( ! is_array( $slot ) ) {
            continue;
        }

        $day   = wpssb_normalize_project_rehearsal_day( $slot['day'] ?? '' );
        $start = wpssb_sanitize_project_rehearsal_time( $slot['start'] ?? '' );
        $end   = wpssb_sanitize_project_rehearsal_time( $slot['end'] ?? '' );

        if ( '' === $start || '' === $end ) {
            continue;
        }

        if ( wpssb_project_rehearsal_time_to_minutes( $start ) >= wpssb_project_rehearsal_time_to_minutes( $end ) ) {
            continue;
        }

        $items[] = [
            'id'    => sanitize_key( ! empty( $slot['id'] ) ? (string) $slot['id'] : wp_generate_uuid4() ),
            'day'   => $day,
            'start' => $start,
            'end'   => $end,
        ];
    }

    usort(
        $items,
        static function ( $left, $right ) {
            $days = array_flip( wpssb_get_project_rehearsal_days() );
            $left_day_index  = isset( $days[ $left['day'] ] ) ? (int) $days[ $left['day'] ] : 0;
            $right_day_index = isset( $days[ $right['day'] ] ) ? (int) $days[ $right['day'] ] : 0;

            if ( $left_day_index !== $right_day_index ) {
                return $left_day_index <=> $right_day_index;
            }

            $left_start  = wpssb_project_rehearsal_time_to_minutes( $left['start'] );
            $right_start = wpssb_project_rehearsal_time_to_minutes( $right['start'] );

            if ( $left_start !== $right_start ) {
                return $left_start <=> $right_start;
            }

            return wpssb_project_rehearsal_time_to_minutes( $left['end'] ) <=> wpssb_project_rehearsal_time_to_minutes( $right['end'] );
        }
    );

    return array_values( $items );
}

/**
 * Sanitiza una lista de días de la semana.
 *
 * @param mixed $items Lista cruda.
 * @return string[]
 */
function wpssb_sanitize_project_rehearsal_days_list( $items ) {
    if ( ! is_array( $items ) ) {
        return [];
    }

    $days = array_filter(
        array_map(
            'wpssb_normalize_project_rehearsal_day',
            $items
        )
    );

    return array_values( array_unique( $days ) );
}

/**
 * Sanitiza listado de puntos revisados en un ensayo.
 *
 * @param mixed $items Lista cruda.
 * @return string[]
 */
function wpssb_sanitize_project_rehearsal_reviewed_items( $items ) {
    if ( is_string( $items ) ) {
        $items = preg_split( '/\r\n|\r|\n/', $items );
    }

    if ( ! is_array( $items ) ) {
        return [];
    }

    $sanitized = array_filter(
        array_map(
            static function ( $item ) {
                return sanitize_text_field( (string) $item );
            },
            $items
        )
    );

    return array_values( array_unique( $sanitized ) );
}

/**
 * Sanitiza la matriz de disponibilidad guardada por proyecto.
 *
 * @param mixed $items   Disponibilidad cruda.
 * @param int   $post_id Proyecto actual.
 * @return array<int, array<string, mixed>>
 */
function wpssb_sanitize_project_rehearsal_availability( $items, $post_id = 0 ) {
    if ( ! is_array( $items ) ) {
        return [];
    }

    $allowed_users = wpssb_get_project_rehearsal_member_name_map( $post_id, $items );

    $availability = [];

    foreach ( $items as $entry ) {
        if ( ! is_array( $entry ) ) {
            continue;
        }

        $user_id = absint( $entry['user_id'] ?? 0 );
        if ( $user_id <= 0 || ( ! empty( $allowed_users ) && ! isset( $allowed_users[ $user_id ] ) ) ) {
            continue;
        }

        $blocked_days = wpssb_sanitize_project_rehearsal_days_list( $entry['blocked_days'] ?? [] );
        $slots        = array_values(
            array_filter(
                wpssb_sanitize_project_rehearsal_slots( $entry['slots'] ?? [] ),
                static function ( $slot ) use ( $blocked_days ) {
                    return ! in_array( $slot['day'] ?? '', $blocked_days, true );
                }
            )
        );
        $unavailable_slots = array_values(
            array_filter(
                wpssb_sanitize_project_rehearsal_slots( $entry['unavailable_slots'] ?? [] ),
                static function ( $slot ) use ( $blocked_days ) {
                    return ! in_array( $slot['day'] ?? '', $blocked_days, true );
                }
            )
        );

        $availability[ $user_id ] = [
            'user_id'           => $user_id,
            'nombre'            => $allowed_users[ $user_id ] ?? sanitize_text_field( (string) ( $entry['nombre'] ?? '' ) ),
            'notes'             => sanitize_textarea_field( (string) ( $entry['notes'] ?? '' ) ),
            'blocked_days'      => $blocked_days,
            'slots'             => $slots,
            'unavailable_slots' => $unavailable_slots,
            'updated_at'        => sanitize_text_field( (string) ( $entry['updated_at'] ?? '' ) ),
        ];
    }

    foreach ( $allowed_users as $user_id => $name ) {
        if ( isset( $availability[ $user_id ] ) ) {
            continue;
        }

        $availability[ $user_id ] = [
            'user_id'           => $user_id,
            'nombre'            => $name,
            'notes'             => '',
            'blocked_days'      => [],
            'slots'             => [],
            'unavailable_slots' => [],
            'updated_at'        => '',
        ];
    }

    uasort(
        $availability,
        static function ( $left, $right ) {
            return strnatcasecmp( (string) $left['nombre'], (string) $right['nombre'] );
        }
    );

    return array_values( $availability );
}

/**
 * Sanitiza votos para propuestas o ensayos del calendario.
 *
 * @param mixed $votes   Votos crudos.
 * @param int   $post_id Proyecto actual.
 * @return array<int, array<string, mixed>>
 */
function wpssb_sanitize_project_rehearsal_votes( $votes, $post_id = 0 ) {
    $allowed_votes = [ 'pending', 'yes', 'no', 'maybe' ];

    if ( ! is_array( $votes ) ) {
        $votes = [];
    }

    $members = wpssb_get_project_rehearsal_member_name_map( $post_id, $votes );

    $items = [];

    foreach ( $votes as $entry ) {
        if ( ! is_array( $entry ) ) {
            continue;
        }

        $user_id = absint( $entry['user_id'] ?? 0 );
        if ( $user_id <= 0 || ! isset( $members[ $user_id ] ) ) {
            continue;
        }

        $vote = sanitize_key( (string) ( $entry['vote'] ?? 'pending' ) );
        if ( ! in_array( $vote, $allowed_votes, true ) ) {
            $vote = 'pending';
        }

        $items[ $user_id ] = [
            'user_id' => $user_id,
            'nombre'  => $members[ $user_id ],
            'vote'    => $vote,
            'comment' => sanitize_text_field( (string) ( $entry['comment'] ?? '' ) ),
        ];
    }

    foreach ( $members as $user_id => $name ) {
        if ( isset( $items[ $user_id ] ) ) {
            continue;
        }

        $items[ $user_id ] = [
            'user_id' => $user_id,
            'nombre'  => $name,
            'vote'    => 'pending',
            'comment' => '',
        ];
    }

    uasort(
        $items,
        static function ( $left, $right ) {
            return strnatcasecmp( (string) $left['nombre'], (string) $right['nombre'] );
        }
    );

    return array_values( $items );
}

/**
 * Indica si la propuesta alcanzó consenso total.
 *
 * @param array<int, array<string, mixed>> $votes   Votos saneados.
 * @param int                              $post_id Proyecto actual.
 * @return bool
 */
function wpssb_project_rehearsal_votes_reach_consensus( $votes, $post_id = 0 ) {
    if ( ! is_array( $votes ) || empty( $votes ) ) {
        return false;
    }

    $members = wpssb_get_project_rehearsal_member_name_map( $post_id, $votes );
    if ( empty( $members ) ) {
        return false;
    }

    if ( count( $votes ) < count( $members ) ) {
        return false;
    }

    foreach ( $votes as $vote ) {
        if ( ! is_array( $vote ) || 'yes' !== ( $vote['vote'] ?? '' ) ) {
            return false;
        }
    }

    return true;
}

/**
 * Indica si ya existe actividad de voto en una sesión.
 *
 * @param array<int, array<string, mixed>> $votes Votos saneados.
 * @return bool
 */
function wpssb_project_rehearsal_votes_started( $votes ) {
    if ( ! is_array( $votes ) || empty( $votes ) ) {
        return false;
    }

    foreach ( $votes as $vote ) {
        if ( ! is_array( $vote ) ) {
            continue;
        }

        if ( in_array( sanitize_key( (string) ( $vote['vote'] ?? 'pending' ) ), [ 'yes', 'maybe', 'no' ], true ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Recalcula el estado de una sesión con base en sus votos actuales.
 *
 * @param array<string, mixed> $session Sesión saneada.
 * @param int                  $post_id Proyecto actual.
 * @return array<string, mixed>
 */
function wpssb_refresh_project_rehearsal_session_vote_state( $session, $post_id = 0 ) {
    if ( ! is_array( $session ) ) {
        return [];
    }

    $status            = sanitize_key( (string) ( $session['status'] ?? 'proposed' ) );
    $votes             = is_array( $session['votes'] ?? null ) ? $session['votes'] : [];
    $consensus_reached = wpssb_project_rehearsal_votes_reach_consensus( $votes, $post_id );
    $votes_started     = wpssb_project_rehearsal_votes_started( $votes );

    if ( $consensus_reached && in_array( $status, [ 'proposed', 'voting' ], true ) ) {
        $status = 'confirmed';
    } elseif ( $votes_started && 'proposed' === $status ) {
        $status = 'voting';
    } elseif ( ! $votes_started && 'voting' === $status ) {
        $status = 'proposed';
    }

    if ( in_array( $status, [ 'proposed', 'voting' ], true ) && ! $consensus_reached && wpssb_project_rehearsal_session_has_ended( $session ) ) {
        $status = 'expired';
    }

    $session['status']            = $status;
    $session['consensus_reached'] = $consensus_reached;

    return $session;
}

/**
 * Sanitiza asistencia de una sesión de ensayo.
 *
 * @param mixed $attendance Asistencia cruda.
 * @param int   $post_id    Proyecto actual.
 * @return array<int, array<string, mixed>>
 */
function wpssb_sanitize_project_rehearsal_attendance( $attendance, $post_id = 0 ) {
    $allowed_statuses = [ 'pending', 'confirmed', 'attended', 'late', 'absent', 'excused' ];

    if ( ! is_array( $attendance ) ) {
        $attendance = [];
    }

    $members = wpssb_get_project_rehearsal_member_name_map( $post_id, $attendance );

    $items = [];

    foreach ( $attendance as $entry ) {
        if ( ! is_array( $entry ) ) {
            continue;
        }

        $user_id = absint( $entry['user_id'] ?? 0 );
        if ( $user_id <= 0 || ! isset( $members[ $user_id ] ) ) {
            continue;
        }

        $status = sanitize_key( (string) ( $entry['status'] ?? 'pending' ) );
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            $status = 'pending';
        }

        $items[ $user_id ] = [
            'user_id' => $user_id,
            'nombre'  => $members[ $user_id ],
            'status'  => $status,
            'comment' => sanitize_text_field( (string) ( $entry['comment'] ?? '' ) ),
        ];
    }

    foreach ( $members as $user_id => $name ) {
        if ( isset( $items[ $user_id ] ) ) {
            continue;
        }

        $items[ $user_id ] = [
            'user_id' => $user_id,
            'nombre'  => $name,
            'status'  => 'pending',
            'comment' => '',
        ];
    }

    uasort(
        $items,
        static function ( $left, $right ) {
            return strnatcasecmp( (string) $left['nombre'], (string) $right['nombre'] );
        }
    );

    return array_values( $items );
}

/**
 * Sanitiza metadatos de sincronización con Google Calendar para una sesión.
 *
 * @param mixed $calendar Datos crudos del calendario.
 * @return array<string, mixed>
 */
function wpssb_sanitize_project_rehearsal_calendar_sync( $calendar ) {
    if ( ! is_array( $calendar ) ) {
        return [
            'event_id'          => '',
            'html_link'         => '',
            'synced_at'         => '',
            'sync_status'       => '',
            'sync_error'        => '',
            'synced_by_user_id' => 0,
        ];
    }

    $sync_status = sanitize_key( (string) ( $calendar['sync_status'] ?? '' ) );
    if ( ! in_array( $sync_status, [ '', 'synced', 'pending', 'cancelled', 'error' ], true ) ) {
        $sync_status = '';
    }

    return [
        'event_id'          => sanitize_text_field( (string) ( $calendar['event_id'] ?? '' ) ),
        'html_link'         => esc_url_raw( (string) ( $calendar['html_link'] ?? '' ) ),
        'synced_at'         => sanitize_text_field( (string) ( $calendar['synced_at'] ?? '' ) ),
        'sync_status'       => $sync_status,
        'sync_error'        => sanitize_text_field( (string) ( $calendar['sync_error'] ?? '' ) ),
        'synced_by_user_id' => absint( $calendar['synced_by_user_id'] ?? 0 ),
    ];
}

/**
 * Valida la integridad mínima de una sesión de ensayo antes de guardarla.
 *
 * @param mixed $session  Sesión cruda.
 * @param int   $position Posición humana dentro del lote.
 * @return array<string, string>|WP_Error
 */
function wpssb_validate_project_rehearsal_session_payload( $session, $position = 0 ) {
    $position = max( 1, absint( $position ) );

    if ( ! is_array( $session ) ) {
        return new WP_Error(
            'wpssb_invalid_rehearsal_session',
            sprintf(
                /* translators: %d: session position */
                __( 'La sesión %d no tiene un formato válido.', 'wp-song-study-blocks' ),
                $position
            ),
            [ 'status' => 422 ]
        );
    }

    $session_id = sanitize_key( ! empty( $session['id'] ) ? (string) $session['id'] : wp_generate_uuid4() );
    $date       = sanitize_text_field( (string) ( $session['scheduled_for'] ?? '' ) );
    $start_time = wpssb_sanitize_project_rehearsal_time( $session['start_time'] ?? '' );
    $end_time   = wpssb_sanitize_project_rehearsal_time( $session['end_time'] ?? '' );

    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
        return new WP_Error(
            'wpssb_invalid_rehearsal_session_date',
            sprintf(
                /* translators: %d: session position */
                __( 'La sesión %d necesita una fecha válida.', 'wp-song-study-blocks' ),
                $position
            ),
            [ 'status' => 422 ]
        );
    }

    if ( '' === $start_time || '' === $end_time ) {
        return new WP_Error(
            'wpssb_incomplete_rehearsal_session_range',
            sprintf(
                /* translators: %d: session position */
                __( 'La sesión %d necesita hora de inicio y fin completas.', 'wp-song-study-blocks' ),
                $position
            ),
            [ 'status' => 422 ]
        );
    }

    if ( wpssb_project_rehearsal_time_to_minutes( $start_time ) >= wpssb_project_rehearsal_time_to_minutes( $end_time ) ) {
        return new WP_Error(
            'wpssb_invalid_rehearsal_session_range',
            sprintf(
                /* translators: %d: session position */
                __( 'La sesión %d tiene un rango horario incongruente.', 'wp-song-study-blocks' ),
                $position
            ),
            [ 'status' => 422 ]
        );
    }

    return [
        'id'            => $session_id,
        'scheduled_for' => $date,
        'start_time'    => $start_time,
        'end_time'      => $end_time,
    ];
}

/**
 * Valida un lote completo de sesiones antes de persistirlo desde interfaces
 * administrativas o REST.
 *
 * @param mixed $items Sesiones crudas.
 * @return true|WP_Error
 */
function wpssb_validate_project_rehearsal_sessions_payload( $items ) {
    if ( ! is_array( $items ) ) {
        return new WP_Error(
            'wpssb_invalid_rehearsal_sessions_payload',
            __( 'La lista de ensayos no tiene un formato válido.', 'wp-song-study-blocks' ),
            [ 'status' => 422 ]
        );
    }

    $seen_ids = [];

    foreach ( $items as $index => $session ) {
        $validated = wpssb_validate_project_rehearsal_session_payload( $session, $index + 1 );
        if ( is_wp_error( $validated ) ) {
            return $validated;
        }

        $session_id = sanitize_key( (string) ( $validated['id'] ?? '' ) );
        if ( '' !== $session_id && isset( $seen_ids[ $session_id ] ) ) {
            return new WP_Error(
                'wpssb_duplicate_rehearsal_session_id',
                sprintf(
                    /* translators: %s: duplicated session identifier */
                    __( 'Hay dos sesiones con el mismo identificador interno (%s).', 'wp-song-study-blocks' ),
                    $session_id
                ),
                [ 'status' => 422 ]
            );
        }

        if ( '' !== $session_id ) {
            $seen_ids[ $session_id ] = true;
        }
    }

    return true;
}

/**
 * Sanitiza las sesiones de ensayo guardadas por proyecto.
 *
 * @param mixed $items   Sesiones crudas.
 * @param int   $post_id Proyecto actual.
 * @return array<int, array<string, mixed>>
 */
function wpssb_sanitize_project_rehearsal_sessions( $items, $post_id = 0 ) {
    if ( ! is_array( $items ) ) {
        return [];
    }

    $allowed_statuses = [ 'proposed', 'voting', 'confirmed', 'completed', 'cancelled', 'expired' ];
    $sessions         = [];
    $used_session_ids = [];

    foreach ( $items as $index => $session ) {
        $validated_session = wpssb_validate_project_rehearsal_session_payload( $session, $index + 1 );
        if ( is_wp_error( $validated_session ) ) {
            continue;
        }

        $session_id = sanitize_key( (string) ( $validated_session['id'] ?? '' ) );
        $date       = sanitize_text_field( (string) ( $validated_session['scheduled_for'] ?? '' ) );
        $start_time = sanitize_text_field( (string) ( $validated_session['start_time'] ?? '' ) );
        $end_time   = sanitize_text_field( (string) ( $validated_session['end_time'] ?? '' ) );

        while ( '' !== $session_id && isset( $used_session_ids[ $session_id ] ) ) {
            $session_id = sanitize_key( wp_generate_uuid4() );
        }

        if ( '' !== $session_id ) {
            $used_session_ids[ $session_id ] = true;
        }

        $status = sanitize_key( (string) ( $session['status'] ?? 'scheduled' ) );
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            $status = 'proposed';
        }

        if ( 'scheduled' === $status ) {
            $status = 'confirmed';
        }

        $votes             = wpssb_sanitize_project_rehearsal_votes( $session['votes'] ?? [], $post_id );
        $consensus_reached = wpssb_project_rehearsal_votes_reach_consensus( $votes, $post_id );
        $votes_started     = wpssb_project_rehearsal_votes_started( $votes );

        if ( $consensus_reached && in_array( $status, [ 'proposed', 'voting' ], true ) ) {
            $status = 'confirmed';
        } elseif ( $votes_started && 'proposed' === $status ) {
            $status = 'voting';
        }

        if ( in_array( $status, [ 'proposed', 'voting' ], true ) && ! $consensus_reached ) {
            $candidate_session = [
                'scheduled_for' => $date,
                'start_time'    => $start_time,
                'end_time'      => $end_time,
            ];

            if ( wpssb_project_rehearsal_session_has_ended( $candidate_session ) ) {
                $status = 'expired';
            }
        }

        $created_by = absint( $session['created_by'] ?? 0 );
        if ( $created_by <= 0 && ! empty( $votes[0]['user_id'] ) ) {
            $created_by = absint( $votes[0]['user_id'] );
        }

        $sessions[] = [
            'id'                => $session_id,
            'scheduled_for'     => $date,
            'start_time'        => $start_time,
            'end_time'          => $end_time,
            'location'          => sanitize_text_field( (string) ( $session['location'] ?? '' ) ),
            'status'            => $status,
            'created_by'        => $created_by,
            'focus'             => sanitize_text_field( (string) ( $session['focus'] ?? '' ) ),
            'reviewed_items'    => wpssb_sanitize_project_rehearsal_reviewed_items( $session['reviewed_items'] ?? [] ),
            'notes'             => sanitize_textarea_field( (string) ( $session['notes'] ?? '' ) ),
            'cancellation_reason' => 'cancelled' === $status ? sanitize_textarea_field( (string) ( $session['cancellation_reason'] ?? '' ) ) : '',
            'cancelled_by'      => 'cancelled' === $status ? absint( $session['cancelled_by'] ?? 0 ) : 0,
            'cancelled_at'      => 'cancelled' === $status ? sanitize_text_field( (string) ( $session['cancelled_at'] ?? '' ) ) : '',
            'attendance'        => wpssb_sanitize_project_rehearsal_attendance( $session['attendance'] ?? [], $post_id ),
            'votes'             => $votes,
            'consensus_reached' => $consensus_reached,
            'calendar'          => wpssb_sanitize_project_rehearsal_calendar_sync( $session['calendar'] ?? [] ),
        ];
    }

    usort(
        $sessions,
        static function ( $left, $right ) {
            $left_timestamp  = strtotime( $left['scheduled_for'] . ' ' . ( $left['start_time'] ?: '00:00' ) );
            $right_timestamp = strtotime( $right['scheduled_for'] . ' ' . ( $right['start_time'] ?: '00:00' ) );

            if ( $left_timestamp === $right_timestamp ) {
                return strnatcasecmp( (string) $left['focus'], (string) $right['focus'] );
            }

            return $left_timestamp <=> $right_timestamp;
        }
    );

    return array_values( $sessions );
}

/**
 * Persiste como expiradas las propuestas que ya pasaron su hora de fin.
 *
 * @param int                  $post_id Proyecto actual.
 * @param array<string, mixed> $meta    Meta de ensayos ya cargado.
 * @return array<string, mixed>
 */
function wpssb_expire_project_rehearsal_proposals_in_meta( $post_id, $meta ) {
    $post_id = absint( $post_id );
    $meta    = is_array( $meta ) ? $meta : [];

    if ( $post_id <= 0 ) {
        return $meta;
    }

    $raw_sessions = is_array( $meta['sessions'] ?? null ) ? $meta['sessions'] : [];
    if ( empty( $raw_sessions ) ) {
        return $meta;
    }

    $raw_status_by_id = [];
    foreach ( $raw_sessions as $raw_session ) {
        if ( ! is_array( $raw_session ) ) {
            continue;
        }

        $raw_session_id = sanitize_key( (string) ( $raw_session['id'] ?? '' ) );
        if ( '' === $raw_session_id ) {
            continue;
        }

        $raw_status_by_id[ $raw_session_id ] = sanitize_key( (string) ( $raw_session['status'] ?? 'proposed' ) );
    }

    $sessions = wpssb_sanitize_project_rehearsal_sessions( $raw_sessions, $post_id );
    $changed  = false;

    foreach ( $sessions as $session ) {
        if ( ! is_array( $session ) || 'expired' !== sanitize_key( (string) ( $session['status'] ?? '' ) ) ) {
            continue;
        }

        $session_id = sanitize_key( (string) ( $session['id'] ?? '' ) );
        $raw_status = $raw_status_by_id[ $session_id ] ?? '';

        if ( in_array( $raw_status, [ 'proposed', 'voting' ], true ) ) {
            $changed = true;
            break;
        }
    }

    if ( ! $changed ) {
        $meta['sessions'] = $sessions;
        return $meta;
    }

    $saved_meta = wpssb_update_project_rehearsal_meta(
        $post_id,
        [
            'project_id'   => $post_id,
            'availability' => $meta['availability'] ?? [],
            'sessions'     => $sessions,
        ]
    );

    return is_array( $saved_meta ) ? $saved_meta : array_merge( $meta, [ 'sessions' => $sessions ] );
}

/**
 * Sanitiza la estructura completa del módulo de ensayos del proyecto.
 *
 * @param mixed $value Valor crudo.
 * @return array<string, mixed>
 */
function wpssb_sanitize_project_rehearsal_meta( $value ) {
    if ( ! is_array( $value ) ) {
        return [
            'project_id'     => 0,
            'availability'  => [],
            'sessions'      => [],
            'updated_at_gmt'=> '',
            'updated_by'    => 0,
        ];
    }

    $post_id = absint( $value['project_id'] ?? 0 );

    return [
        'project_id'      => $post_id,
        'availability'   => wpssb_sanitize_project_rehearsal_availability( $value['availability'] ?? [], $post_id ),
        'sessions'       => wpssb_sanitize_project_rehearsal_sessions( $value['sessions'] ?? [], $post_id ),
        'updated_at_gmt' => sanitize_text_field( (string) ( $value['updated_at_gmt'] ?? '' ) ),
        'updated_by'     => absint( $value['updated_by'] ?? 0 ),
    ];
}

/**
 * Registra meta de usuario para presskits.
 *
 * @return void
 */
function wpssb_register_collaborator_meta() {
    register_meta(
        'user',
        'pd_colaborador_tagline',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'sanitize_text_field',
            'show_in_rest'      => true,
        ]
    );

    register_meta(
        'user',
        'pd_colaborador_presskit',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'wp_kses_post',
            'show_in_rest'      => true,
        ]
    );

    register_meta(
        'user',
        'pd_colaborador_links',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'sanitize_textarea_field',
            'show_in_rest'      => true,
        ]
    );

    register_meta(
        'user',
        'pd_colaborador_contacto',
        [
            'type'              => 'string',
            'single'            => true,
            'sanitize_callback' => 'wp_kses_post',
            'show_in_rest'      => true,
        ]
    );

    register_meta(
        'user',
        'pd_colaborador_galeria',
        [
            'type'              => 'array',
            'single'            => true,
            'sanitize_callback' => 'wpssb_sanitize_id_list',
            'show_in_rest'      => [
                'schema' => [
                    'type'  => 'array',
                    'items' => [
                        'type' => 'integer',
                    ],
                ],
            ],
        ]
    );
}
add_action( 'init', 'wpssb_register_collaborator_meta' );

/**
 * Obtiene colaboradores ordenados para administración y render.
 *
 * @return WP_User[]
 */
function wpssb_user_is_project_collaborator_candidate( $user ) {
    if ( ! $user instanceof WP_User ) {
        return false;
    }

    if ( user_can( $user, WPSSB_COLLABORATOR_CAP ) ) {
        return true;
    }

    if ( defined( 'WPSS_CAP_MANAGE' ) && user_can( $user, WPSS_CAP_MANAGE ) ) {
        return true;
    }

    if ( defined( 'WPSS_ROLE_COLEGA' ) && in_array( WPSS_ROLE_COLEGA, (array) $user->roles, true ) ) {
        return true;
    }

    return false;
}

/**
 * Indica si un usuario puede entrar al area privada de musica.
 *
 * Acepta administradores, colaboradores digitales y colegas musicales.
 *
 * @param int|null $user_id ID del usuario objetivo. Usa el actual por defecto.
 * @return bool
 */
function wpssb_user_can_access_private_music_area( $user_id = null ) {
    if ( null === $user_id ) {
        $user_id = get_current_user_id();
    }

    $user_id = absint( $user_id );

    if ( $user_id <= 0 ) {
        return false;
    }

    $user = get_userdata( $user_id );

    if ( ! $user instanceof WP_User ) {
        return false;
    }

    if (
        user_can( $user, 'manage_options' ) ||
        user_can( $user, WPSSB_COLLABORATOR_CAP ) ||
        user_can( $user, 'edit_presskits' )
    ) {
        return true;
    }

    if ( defined( 'WPSS_ROLE_COLEGA' ) && in_array( WPSS_ROLE_COLEGA, (array) $user->roles, true ) ) {
        return true;
    }

    return false;
}

/**
 * Obtiene usuarios elegibles como colaboradores de proyecto.
 *
 * @return WP_User[]
 */
function wpssb_get_collaborators() {
    $users = get_users(
        [
            'orderby' => 'display_name',
            'order'   => 'ASC',
        ]
    );

    return array_values(
        array_filter(
            $users,
            'wpssb_user_is_project_collaborator_candidate'
        )
    );
}

/**
 * Meta boxes de proyecto.
 *
 * @return void
 */
function wpssb_add_project_meta_boxes() {
    add_meta_box(
        'wpssb-project-collaborators',
        __( 'Colaboradores', 'wp-song-study-blocks' ),
        'wpssb_render_project_collaborators_meta_box',
        WPSSB_PROJECT_POST_TYPE,
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes', 'wpssb_add_project_meta_boxes' );

/**
 * Registra el meta box para elegir el usuario objetivo de una página pública.
 *
 * @return void
 */
function wpssb_add_collaborator_target_meta_box() {
    foreach ( [ 'page', WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE ] as $screen ) {
        add_meta_box(
            'wpssb-collaborator-target',
            __( 'Usuario objetivo del presskit', 'wp-song-study-blocks' ),
            'wpssb_render_collaborator_target_meta_box',
            $screen,
            'side',
            'default'
        );
    }
}
add_action( 'add_meta_boxes', 'wpssb_add_collaborator_target_meta_box' );

/**
 * Renderiza el selector de usuario objetivo en páginas.
 *
 * @param WP_Post $post Post actual.
 * @return void
 */
function wpssb_render_collaborator_target_meta_box( WP_Post $post ) {
    wp_nonce_field( 'wpssb_save_collaborator_target_meta', 'wpssb_collaborator_target_nonce' );

    $selected_user_id = absint( get_post_meta( $post->ID, WPSSB_COLLABORATOR_TARGET_META, true ) );
    $page_template    = (string) get_page_template_slug( $post->ID );
    $users            = get_users(
        [
            'orderby' => 'display_name',
            'order'   => 'ASC',
        ]
    );

    echo '<p>' . esc_html__( 'Elige qué colaborador, colega músico o administrador representa esta página. Los bloques públicos de presskit, galería, contacto y proyectos usarán este usuario antes de tomar el autor de la página.', 'wp-song-study-blocks' ) . '</p>';

    if ( 'presskit' === $page_template ) {
        echo '<p><strong>' . esc_html__( 'La plantilla actual es Press Kit.', 'wp-song-study-blocks' ) . '</strong></p>';
    }

    echo '<label class="screen-reader-text" for="wpssb_collaborator_target_user">' . esc_html__( 'Usuario objetivo', 'wp-song-study-blocks' ) . '</label>';
    echo '<select id="wpssb_collaborator_target_user" name="wpssb_collaborator_target_user" class="widefat">';
    echo '<option value="0">' . esc_html__( 'Autor de la página (por defecto)', 'wp-song-study-blocks' ) . '</option>';

    foreach ( $users as $user ) {
        if ( ! $user instanceof WP_User ) {
            continue;
        }

        printf(
            '<option value="%1$d" %2$s>%3$s</option>',
            (int) $user->ID,
            selected( $selected_user_id, (int) $user->ID, false ),
            esc_html( $user->display_name . ' · ' . $user->user_email )
        );
    }

    echo '</select>';
}

/**
 * Obtiene el ID del presskit personal vinculado a un usuario.
 *
 * @param int $user_id Usuario objetivo.
 * @return int
 */
function wpssb_get_collaborator_presskit_post_id( $user_id ) {
    $user_id = absint( $user_id );

    if ( $user_id <= 0 ) {
        return 0;
    }

    $stored_post_id = absint( get_user_meta( $user_id, WPSSB_COLLABORATOR_PRESSKIT_USER_META, true ) );

    if ( $stored_post_id > 0 && WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE === get_post_type( $stored_post_id ) ) {
        wpssb_sync_collaborator_presskit_post_slug( $stored_post_id, $user_id );
        return $stored_post_id;
    }

    $posts = get_posts(
        [
            'post_type'      => WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE,
            'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                [
                    'key'   => WPSSB_COLLABORATOR_TARGET_META,
                    'value' => $user_id,
                ],
            ],
        ]
    );

    $post_id = ! empty( $posts[0] ) ? (int) $posts[0] : 0;

    if ( $post_id > 0 ) {
        update_user_meta( $user_id, WPSSB_COLLABORATOR_PRESSKIT_USER_META, $post_id );
        wpssb_sync_collaborator_presskit_post_slug( $post_id, $user_id );
    }

    return $post_id;
}

/**
 * Devuelve el URL público preferido para un colaborador.
 *
 * @param int $user_id Usuario objetivo.
 * @return string
 */
function wpssb_get_collaborator_public_url( $user_id ) {
    $user_id = absint( $user_id );

    if ( $user_id <= 0 ) {
        return home_url( '/' );
    }

    $presskit_post_id = wpssb_get_collaborator_presskit_post_id( $user_id );

    if ( $presskit_post_id > 0 ) {
        $permalink = get_permalink( $presskit_post_id );

        if ( is_string( $permalink ) && '' !== $permalink ) {
            return $permalink;
        }
    }

    return get_author_posts_url( $user_id );
}

/**
 * Redirige el archivo de autor al presskit personal cuando exista.
 *
 * @return void
 */
function wpssb_redirect_author_archive_to_presskit() {
    if ( is_admin() || ! is_author() || is_feed() || is_preview() ) {
        return;
    }

    if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }

    $user_id = (int) get_queried_object_id();

    if ( $user_id <= 0 ) {
        return;
    }

    $presskit_post_id = wpssb_get_collaborator_presskit_post_id( $user_id );

    if ( $presskit_post_id <= 0 ) {
        return;
    }

    $target_url = get_permalink( $presskit_post_id );

    if ( ! is_string( $target_url ) || '' === $target_url ) {
        return;
    }

    wp_safe_redirect( $target_url, 302, 'WP Song Study Blocks' );
    exit;
}
add_action( 'template_redirect', 'wpssb_redirect_author_archive_to_presskit' );

/**
 * Redirige slugs legacy o adivinados de presskit al permalink real del usuario.
 *
 * Ejemplo: `/presskit/sergiomendoza/` aunque el post se haya creado con otro slug.
 *
 * @return void
 */
function wpssb_redirect_guessed_presskit_slug() {
    if ( is_admin() || ! is_404() || is_feed() || is_preview() ) {
        return;
    }

    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
    $request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
    $request_path = trim( $request_path, '/' );

    if ( '' === $request_path ) {
        return;
    }

    $home_path = trim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );

    if ( '' !== $home_path && str_starts_with( $request_path, $home_path . '/' ) ) {
        $request_path = substr( $request_path, strlen( $home_path ) + 1 );
    }

    if ( ! preg_match( '#^presskit/([^/]+)$#', $request_path, $matches ) ) {
        return;
    }

    $candidate = sanitize_title( $matches[1] );

    if ( '' === $candidate ) {
        return;
    }

    $user = get_user_by( 'slug', $candidate );

    if ( ! $user instanceof WP_User ) {
        $user = get_user_by( 'login', $candidate );
    }

    if ( ! $user instanceof WP_User ) {
        return;
    }

    $presskit_post_id = wpssb_get_collaborator_presskit_post_id( (int) $user->ID );

    if ( $presskit_post_id <= 0 ) {
        return;
    }

    $target_url = get_permalink( $presskit_post_id );

    if ( ! $target_url ) {
        return;
    }

    wp_safe_redirect( $target_url, 301, 'WP Song Study Blocks' );
    exit;
}
add_action( 'template_redirect', 'wpssb_redirect_guessed_presskit_slug', 1 );

/**
 * Construye el contenido inicial editable del presskit personal.
 *
 * @param int $user_id Usuario objetivo.
 * @return string
 */
function wpssb_get_default_collaborator_presskit_content( $user_id = 0 ) {
    $user_id      = absint( $user_id );
    $user         = $user_id ? get_user_by( 'id', $user_id ) : null;
    $display_name = $user instanceof WP_User ? $user->display_name : __( 'Tu nombre artístico', 'wp-song-study-blocks' );
    $tagline      = trim( (string) get_user_meta( $user_id, 'pd_colaborador_tagline', true ) );

    if ( '' === $tagline ) {
        $tagline = __( 'Define aquí una frase breve que ubique tu sonido, enfoque o propuesta.', 'wp-song-study-blocks' );
    }

    $content   = [];
    $content[] = '<!-- wp:group {"align":"wide","className":"pd-presskit__surface pd-presskit__surface--hero","layout":{"type":"constrained"}} -->';
    $content[] = '<div class="wp-block-group alignwide pd-presskit__surface pd-presskit__surface--hero">';
    $content[] = '<!-- wp:wp-song-study/collaborator-presskit /-->';
    $content[] = '</div>';
    $content[] = '<!-- /wp:group -->';

    $content[] = '<!-- wp:group {"align":"wide","className":"pd-presskit__section pd-presskit__surface","layout":{"type":"constrained"}} -->';
    $content[] = '<div class="wp-block-group alignwide pd-presskit__section pd-presskit__surface">';
    $content[] = '<!-- wp:paragraph {"fontSize":"x-small","className":"pd-eyebrow"} -->';
    $content[] = '<p class="pd-eyebrow has-x-small-font-size">' . esc_html__( 'Presentación', 'wp-song-study-blocks' ) . '</p>';
    $content[] = '<!-- /wp:paragraph -->';
    $content[] = '<!-- wp:heading {"level":2} -->';
    $content[] = '<h2 class="wp-block-heading">' . esc_html__( 'Una entrada clara a tu universo', 'wp-song-study-blocks' ) . '</h2>';
    $content[] = '<!-- /wp:heading -->';
    $content[] = '<!-- wp:paragraph {"fontSize":"large"} -->';
    $content[] = '<p class="has-large-font-size">' . esc_html( $tagline ) . '</p>';
    $content[] = '<!-- /wp:paragraph -->';
    $content[] = '<!-- wp:paragraph -->';
    $content[] = '<p>' . sprintf(
        /* translators: %s collaborator name */
        esc_html__( '%s puede usar este presskit como una carta de presentación viva: una mezcla de contexto, materiales, referencias y piezas que ayuden a entender su trabajo de un vistazo.', 'wp-song-study-blocks' ),
        esc_html( $display_name )
    ) . '</p>';
    $content[] = '<!-- /wp:paragraph -->';
    $content[] = '<!-- wp:paragraph -->';
    $content[] = '<p>' . esc_html__( 'Empieza por lo esencial: quién eres, qué haces, qué atmósfera construyes y qué materiales quieres que la gente vea, escuche o descargue.', 'wp-song-study-blocks' ) . '</p>';
    $content[] = '<!-- /wp:paragraph -->';
    $content[] = '</div>';
    $content[] = '<!-- /wp:group -->';

    $content[] = '<!-- wp:columns {"align":"wide","className":"pd-presskit__split"} -->';
    $content[] = '<div class="wp-block-columns alignwide pd-presskit__split">';
    $content[] = '<!-- wp:column -->';
    $content[] = '<div class="wp-block-column">';
    $content[] = '<!-- wp:group {"className":"pd-presskit__section pd-presskit__surface","layout":{"type":"constrained"}} -->';
    $content[] = '<div class="wp-block-group pd-presskit__section pd-presskit__surface">';
    $content[] = '<!-- wp:paragraph {"fontSize":"x-small","className":"pd-eyebrow"} -->';
    $content[] = '<p class="pd-eyebrow has-x-small-font-size">' . esc_html__( 'Narrativa', 'wp-song-study-blocks' ) . '</p>';
    $content[] = '<!-- /wp:paragraph -->';
    $content[] = '<!-- wp:heading {"level":2} -->';
    $content[] = '<h2 class="wp-block-heading">' . esc_html__( 'Visión artística', 'wp-song-study-blocks' ) . '</h2>';
    $content[] = '<!-- /wp:heading -->';
    $content[] = '<!-- wp:paragraph -->';
    $content[] = '<p>' . esc_html__( 'Cuenta aquí tu historia, tu enfoque creativo, las preguntas que atraviesan tu trabajo o el tipo de experiencia que quieres provocar.', 'wp-song-study-blocks' ) . '</p>';
    $content[] = '<!-- /wp:paragraph -->';
    $content[] = '<!-- wp:quote -->';
    $content[] = '<blockquote class="wp-block-quote"><p>' . esc_html__( 'Incluye aquí una frase, statement o cita que ayude a resumir tu tono.', 'wp-song-study-blocks' ) . '</p></blockquote>';
    $content[] = '<!-- /wp:quote -->';
    $content[] = '</div>';
    $content[] = '<!-- /wp:group -->';
    $content[] = '</div>';
    $content[] = '<!-- /wp:column -->';
    $content[] = '<!-- wp:column -->';
    $content[] = '<div class="wp-block-column">';
    $content[] = '<!-- wp:group {"className":"pd-presskit__section pd-presskit__surface","layout":{"type":"constrained"}} -->';
    $content[] = '<div class="wp-block-group pd-presskit__section pd-presskit__surface">';
    $content[] = '<!-- wp:paragraph {"fontSize":"x-small","className":"pd-eyebrow"} -->';
    $content[] = '<p class="pd-eyebrow has-x-small-font-size">' . esc_html__( 'Señas de identidad', 'wp-song-study-blocks' ) . '</p>';
    $content[] = '<!-- /wp:paragraph -->';
    $content[] = '<!-- wp:heading {"level":2} -->';
    $content[] = '<h2 class="wp-block-heading">' . esc_html__( 'Claves rápidas', 'wp-song-study-blocks' ) . '</h2>';
    $content[] = '<!-- /wp:heading -->';
    $content[] = '<!-- wp:list -->';
    $content[] = '<ul class="wp-block-list"><li>' . esc_html__( 'Géneros, cruces o territorios en los que te mueves.', 'wp-song-study-blocks' ) . '</li><li>' . esc_html__( 'Formato actual: solista, banda, dúo, ensamble o colectivo.', 'wp-song-study-blocks' ) . '</li><li>' . esc_html__( 'Contexto geográfico, escena o comunidad desde la que trabajas.', 'wp-song-study-blocks' ) . '</li></ul>';
    $content[] = '<!-- /wp:list -->';
    $content[] = '</div>';
    $content[] = '<!-- /wp:group -->';
    $content[] = '</div>';
    $content[] = '<!-- /wp:column -->';
    $content[] = '</div>';
    $content[] = '<!-- /wp:columns -->';

    $content[] = '<!-- wp:group {"align":"wide","className":"pd-presskit__section pd-presskit__surface","layout":{"type":"constrained"}} -->';
    $content[] = '<div class="wp-block-group alignwide pd-presskit__section pd-presskit__surface">';
    $content[] = '<!-- wp:paragraph {"fontSize":"x-small","className":"pd-eyebrow"} -->';
    $content[] = '<p class="pd-eyebrow has-x-small-font-size">' . esc_html__( 'Escucha y mira', 'wp-song-study-blocks' ) . '</p>';
    $content[] = '<!-- /wp:paragraph -->';
    $content[] = '<!-- wp:columns -->';
    $content[] = '<div class="wp-block-columns">';
    $content[] = '<!-- wp:column -->';
    $content[] = '<div class="wp-block-column">';
    $content[] = '<!-- wp:heading {"level":3} -->';
    $content[] = '<h3 class="wp-block-heading">' . esc_html__( 'Música', 'wp-song-study-blocks' ) . '</h3>';
    $content[] = '<!-- /wp:heading -->';
    $content[] = '<!-- wp:embed {"url":"https://open.spotify.com","type":"rich","className":"pd-presskit__embed"} -->';
    $content[] = '<figure class="wp-block-embed is-type-rich pd-presskit__embed"><div class="wp-block-embed__wrapper">https://open.spotify.com</div></figure>';
    $content[] = '<!-- /wp:embed -->';
    $content[] = '</div>';
    $content[] = '<!-- /wp:column -->';
    $content[] = '<!-- wp:column -->';
    $content[] = '<div class="wp-block-column">';
    $content[] = '<!-- wp:heading {"level":3} -->';
    $content[] = '<h3 class="wp-block-heading">' . esc_html__( 'Video', 'wp-song-study-blocks' ) . '</h3>';
    $content[] = '<!-- /wp:heading -->';
    $content[] = '<!-- wp:embed {"url":"https://youtube.com","type":"video","className":"pd-presskit__embed"} -->';
    $content[] = '<figure class="wp-block-embed is-type-video pd-presskit__embed"><div class="wp-block-embed__wrapper">https://youtube.com</div></figure>';
    $content[] = '<!-- /wp:embed -->';
    $content[] = '</div>';
    $content[] = '<!-- /wp:column -->';
    $content[] = '</div>';
    $content[] = '<!-- /wp:columns -->';
    $content[] = '</div>';
    $content[] = '<!-- /wp:group -->';

    $content[] = '<!-- wp:group {"align":"wide","className":"pd-presskit__section pd-presskit__surface","layout":{"type":"constrained"}} -->';
    $content[] = '<div class="wp-block-group alignwide pd-presskit__section pd-presskit__surface">';
    $content[] = '<!-- wp:paragraph {"fontSize":"x-small","className":"pd-eyebrow"} -->';
    $content[] = '<p class="pd-eyebrow has-x-small-font-size">' . esc_html__( 'Trayectoria', 'wp-song-study-blocks' ) . '</p>';
    $content[] = '<!-- /wp:paragraph -->';
    $content[] = '<!-- wp:heading {"level":2} -->';
    $content[] = '<h2 class="wp-block-heading">' . esc_html__( 'Proyectos y colaboraciones', 'wp-song-study-blocks' ) . '</h2>';
    $content[] = '<!-- /wp:heading -->';
    $content[] = '<!-- wp:wp-song-study/collaborator-projects /-->';
    $content[] = '</div>';
    $content[] = '<!-- /wp:group -->';

    $content[] = '<!-- wp:columns {"align":"wide","className":"pd-presskit__split"} -->';
    $content[] = '<div class="wp-block-columns alignwide pd-presskit__split">';
    $content[] = '<!-- wp:column -->';
    $content[] = '<div class="wp-block-column">';
    $content[] = '<!-- wp:group {"className":"pd-presskit__section pd-presskit__surface","layout":{"type":"constrained"}} -->';
    $content[] = '<div class="wp-block-group pd-presskit__section pd-presskit__surface">';
    $content[] = '<!-- wp:paragraph {"fontSize":"x-small","className":"pd-eyebrow"} -->';
    $content[] = '<p class="pd-eyebrow has-x-small-font-size">' . esc_html__( 'Prensa y logros', 'wp-song-study-blocks' ) . '</p>';
    $content[] = '<!-- /wp:paragraph -->';
    $content[] = '<!-- wp:list -->';
    $content[] = '<ul class="wp-block-list"><li>' . esc_html__( 'Añade aquí una cita de prensa o una mención destacada.', 'wp-song-study-blocks' ) . '</li><li>' . esc_html__( 'Incluye festivales, recintos, residencias o circuitos en los que hayas participado.', 'wp-song-study-blocks' ) . '</li><li>' . esc_html__( 'Si aplica, anota premios, becas, lanzamientos o hitos importantes.', 'wp-song-study-blocks' ) . '</li></ul>';
    $content[] = '<!-- /wp:list -->';
    $content[] = '</div>';
    $content[] = '<!-- /wp:group -->';
    $content[] = '</div>';
    $content[] = '<!-- /wp:column -->';
    $content[] = '<!-- wp:column -->';
    $content[] = '<div class="wp-block-column">';
    $content[] = '<!-- wp:group {"className":"pd-presskit__section pd-presskit__surface","layout":{"type":"constrained"}} -->';
    $content[] = '<div class="wp-block-group pd-presskit__section pd-presskit__surface">';
    $content[] = '<!-- wp:paragraph {"fontSize":"x-small","className":"pd-eyebrow"} -->';
    $content[] = '<p class="pd-eyebrow has-x-small-font-size">' . esc_html__( 'Materiales', 'wp-song-study-blocks' ) . '</p>';
    $content[] = '<!-- /wp:paragraph -->';
    $content[] = '<!-- wp:heading {"level":3} -->';
    $content[] = '<h3 class="wp-block-heading">' . esc_html__( 'Descargas o dossier', 'wp-song-study-blocks' ) . '</h3>';
    $content[] = '<!-- /wp:heading -->';
    $content[] = '<!-- wp:file {"className":"pd-presskit__download"} -->';
    $content[] = '<div class="wp-block-file pd-presskit__download"><a href="#">' . esc_html__( 'Descargar material de prensa', 'wp-song-study-blocks' ) . '</a></div>';
    $content[] = '<!-- /wp:file -->';
    $content[] = '<!-- wp:paragraph -->';
    $content[] = '<p>' . esc_html__( 'También puedes usar botones, columnas o grupos para organizar enlaces a dossier, rider, EPK o contacto directo.', 'wp-song-study-blocks' ) . '</p>';
    $content[] = '<!-- /wp:paragraph -->';
    $content[] = '</div>';
    $content[] = '<!-- /wp:group -->';
    $content[] = '</div>';
    $content[] = '<!-- /wp:column -->';
    $content[] = '</div>';
    $content[] = '<!-- /wp:columns -->';

    return implode( "\n", $content );
}

/**
 * Construye contenido migrado para un presskit personal legacy.
 *
 * Si el nuevo CPT existe pero llega vacío, intenta sembrarlo con la información
 * mínima que antes vivía en user meta para no dejar el documento en blanco.
 *
 * @param int $user_id Usuario objetivo.
 * @return string
 */
function wpssb_get_migrated_collaborator_presskit_content( $user_id ) {
    $user_id = absint( $user_id );

    if ( $user_id <= 0 ) {
        return '';
    }

    $user = get_user_by( 'id', $user_id );

    if ( ! $user instanceof WP_User ) {
        return '';
    }

    $tagline       = trim( (string) get_user_meta( $user_id, 'pd_colaborador_tagline', true ) );
    $legacy_text   = trim( (string) get_user_meta( $user_id, 'pd_colaborador_presskit', true ) );
    $description   = trim( (string) $user->description );
    $fallback_text = '' !== $legacy_text ? $legacy_text : $description;

    if ( '' === $tagline && '' === $fallback_text ) {
        return '';
    }

    $content = [];

    $content[] = '<!-- wp:group {"align":"wide","className":"pd-presskit__surface pd-presskit__surface--hero","layout":{"type":"constrained"}} -->';
    $content[] = '<div class="wp-block-group alignwide pd-presskit__surface pd-presskit__surface--hero">';
    $content[] = '<!-- wp:wp-song-study/collaborator-presskit /-->';
    $content[] = '</div>';
    $content[] = '<!-- /wp:group -->';
    $content[] = '<!-- wp:group {"align":"wide","className":"pd-presskit__section pd-presskit__surface","layout":{"type":"constrained"}} -->';
    $content[] = '<div class="wp-block-group alignwide pd-presskit__section pd-presskit__surface">';
    $content[] = '<!-- wp:paragraph {"fontSize":"x-small","className":"pd-eyebrow"} -->';
    $content[] = '<p class="pd-eyebrow has-x-small-font-size">' . esc_html__( 'Presentación', 'wp-song-study-blocks' ) . '</p>';
    $content[] = '<!-- /wp:paragraph -->';
    $content[] = '<!-- wp:heading {"level":2} -->';
    $content[] = '<h2 class="wp-block-heading">' . esc_html( $user->display_name ) . '</h2>';
    $content[] = '<!-- /wp:heading -->';

    if ( '' !== $tagline ) {
        $content[] = '<!-- wp:paragraph {"fontSize":"large"} -->';
        $content[] = '<p class="has-large-font-size">' . esc_html( $tagline ) . '</p>';
        $content[] = '<!-- /wp:paragraph -->';
    }

    if ( '' !== $fallback_text ) {
        foreach ( preg_split( "/\n\s*\n/", $fallback_text ) as $paragraph ) {
            $paragraph = trim( wp_strip_all_tags( $paragraph ) );

            if ( '' === $paragraph ) {
                continue;
            }

            $content[] = '<!-- wp:paragraph -->';
            $content[] = '<p>' . esc_html( $paragraph ) . '</p>';
            $content[] = '<!-- /wp:paragraph -->';
        }
    }

    $content[] = '</div>';
    $content[] = '<!-- /wp:group -->';
    $content[] = '<!-- wp:group {"align":"wide","className":"pd-presskit__section pd-presskit__surface","layout":{"type":"constrained"}} -->';
    $content[] = '<div class="wp-block-group alignwide pd-presskit__section pd-presskit__surface">';
    $content[] = '<!-- wp:paragraph {"fontSize":"x-small","className":"pd-eyebrow"} -->';
    $content[] = '<p class="pd-eyebrow has-x-small-font-size">' . esc_html__( 'Trayectoria', 'wp-song-study-blocks' ) . '</p>';
    $content[] = '<!-- /wp:paragraph -->';
    $content[] = '<!-- wp:heading {"level":2} -->';
    $content[] = '<h2 class="wp-block-heading">' . esc_html__( 'Proyectos', 'wp-song-study-blocks' ) . '</h2>';
    $content[] = '<!-- /wp:heading -->';
    $content[] = '<!-- wp:wp-song-study/collaborator-projects /-->';
    $content[] = '</div>';
    $content[] = '<!-- /wp:group -->';

    return implode( "\n", $content );
}

/**
 * Devuelve contenido efectivo para un documento editable de presskit/proyecto.
 *
 * @param WP_Post $post Post objetivo.
 * @return string
 */
function wpssb_get_effective_presskit_document_content( WP_Post $post ) {
    $content = trim( (string) $post->post_content );

    if ( '' !== $content ) {
        return (string) $post->post_content;
    }

    if ( WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE === $post->post_type ) {
        $user_id = wpssb_get_explicit_collaborator_target_user_id( $post->ID );

        if ( ! $user_id ) {
            $user_id = (int) ( $post->post_author ?: get_current_user_id() );
        }

        $migrated = wpssb_get_migrated_collaborator_presskit_content( $user_id );

        return '' !== $migrated ? $migrated : wpssb_get_default_collaborator_presskit_content( $user_id );
    }

    if ( WPSSB_PROJECT_POST_TYPE === $post->post_type ) {
        return wpssb_get_default_project_presskit_content( (int) $post->ID );
    }

    return '';
}

/**
 * Construye el contenido inicial editable del proyecto.
 *
 * @param int $post_id Proyecto objetivo.
 * @return string
 */
function wpssb_get_default_project_presskit_content( $post_id = 0 ) {
    return <<<'HTML'
<!-- wp:group {"align":"wide","className":"pd-presskit__surface pd-presskit__surface--hero","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide pd-presskit__surface pd-presskit__surface--hero">
  <!-- wp:wp-song-study/project-presskit /-->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"pd-presskit__section pd-presskit__surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide pd-presskit__section pd-presskit__surface">
  <!-- wp:paragraph {"fontSize":"x-small","className":"pd-eyebrow"} -->
  <p class="pd-eyebrow has-x-small-font-size">Narrativa del proyecto</p>
  <!-- /wp:paragraph -->

  <!-- wp:heading {"level":2} -->
  <h2 class="wp-block-heading">Contenido editorial</h2>
  <!-- /wp:heading -->

  <!-- wp:paragraph -->
  <p>Este documento ya es libre para componer el presskit del proyecto con bloques: contexto, manifiesto, hitos, embeds, dossier, agenda, prensa o cualquier estructura editorial.</p>
  <!-- /wp:paragraph -->
  <!-- wp:paragraph -->
  <p>Si quieres empezar con una base visual, inserta un patrón del tema y ajústalo libremente en el editor.</p>
  <!-- /wp:paragraph -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","className":"pd-presskit__section pd-presskit__surface","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide pd-presskit__section pd-presskit__surface">
  <!-- wp:paragraph {"fontSize":"x-small","className":"pd-eyebrow"} -->
  <p class="pd-eyebrow has-x-small-font-size">Equipo</p>
  <!-- /wp:paragraph -->

  <!-- wp:heading {"level":2} -->
  <h2 class="wp-block-heading">Integrantes y colaboradores</h2>
  <!-- /wp:heading -->

  <!-- wp:wp-song-study/project-collaborators /-->
</div>
<!-- /wp:group -->
HTML;
}

/**
 * Aplica contenido inicial al editor para presskits personales y proyectos.
 *
 * @param string  $content Contenido actual.
 * @param WP_Post $post    Post actual.
 * @return string
 */
function wpssb_filter_default_presskit_content( $content, $post ) {
    if ( ! $post instanceof WP_Post || '' !== trim( (string) $content ) ) {
        return $content;
    }

    if ( WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE === $post->post_type ) {
        $user_id = wpssb_get_explicit_collaborator_target_user_id( $post->ID );

        if ( ! $user_id ) {
            $user_id = (int) ( $post->post_author ?: get_current_user_id() );
        }

        return wpssb_get_default_collaborator_presskit_content( $user_id );
    }

    if ( WPSSB_PROJECT_POST_TYPE === $post->post_type ) {
        return wpssb_get_default_project_presskit_content( (int) $post->ID );
    }

    return $content;
}
add_filter( 'default_content', 'wpssb_filter_default_presskit_content', 10, 2 );

/**
 * Garantiza que un colaborador tenga un presskit editable vinculado.
 *
 * @param int $user_id Usuario objetivo.
 * @return int
 */
function wpssb_ensure_collaborator_presskit_post( $user_id ) {
    $user_id = absint( $user_id );

    if ( $user_id <= 0 ) {
        return 0;
    }

    $existing_post_id = wpssb_get_collaborator_presskit_post_id( $user_id );

    if ( $existing_post_id > 0 ) {
        $existing_post = get_post( $existing_post_id );

        if ( $existing_post instanceof WP_Post && '' === trim( (string) $existing_post->post_content ) ) {
            $effective_content = wpssb_get_effective_presskit_document_content( $existing_post );

            if ( '' !== trim( $effective_content ) ) {
                wp_update_post(
                    [
                        'ID'           => $existing_post_id,
                        'post_content' => $effective_content,
                    ]
                );
            }
        }

        return $existing_post_id;
    }

    $user = get_user_by( 'id', $user_id );

    if ( ! $user instanceof WP_User ) {
        return 0;
    }

    $post_id = wp_insert_post(
        [
            'post_type'    => WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE,
            'post_status'  => 'publish',
            'post_title'   => $user->display_name,
            'post_author'  => $user_id,
            'post_name'    => wpssb_get_preferred_collaborator_presskit_slug( $user ),
            'post_content' => wpssb_get_default_collaborator_presskit_content( $user_id ),
            'meta_input'   => [
                WPSSB_COLLABORATOR_TARGET_META => $user_id,
            ],
        ],
        true
    );

    if ( is_wp_error( $post_id ) ) {
        return 0;
    }

    update_user_meta( $user_id, WPSSB_COLLABORATOR_PRESSKIT_USER_META, (int) $post_id );
    wpssb_sync_collaborator_presskit_post_slug( (int) $post_id, $user_id );

    return (int) $post_id;
}

/**
 * Render del meta box de miembros.
 *
 * @param WP_Post $post Post actual.
 * @return void
 */
function wpssb_render_project_collaborators_meta_box( WP_Post $post ) {
    wp_nonce_field( 'wpssb_save_project_meta', 'wpssb_project_meta_nonce' );

    $selected = wpssb_sanitize_id_list( get_post_meta( $post->ID, 'pd_proyecto_colaboradores', true ) );
    $users    = wpssb_get_collaborators();

    if ( empty( $users ) ) {
        echo '<p>' . esc_html__( 'No hay colaboradores disponibles. Asigna el rol o capability primero.', 'wp-song-study-blocks' ) . '</p>';
        return;
    }

    echo '<div class="wpssb-project-collaborators-meta">';

    foreach ( $users as $user ) {
        $checked = in_array( (int) $user->ID, $selected, true ) ? 'checked' : '';
        printf(
            '<label style="display:block;margin-bottom:6px;"><input type="checkbox" name="pd_proyecto_colaboradores[]" value="%1$d" %2$s /> %3$s</label>',
            (int) $user->ID,
            $checked,
            esc_html( $user->display_name )
        );
    }

    echo '</div>';
}

/**
 * Render del meta box de contacto.
 *
 * @param WP_Post $post Post actual.
 * @return void
 */
function wpssb_render_project_contact_meta_box( WP_Post $post ) {
    $contact = get_post_meta( $post->ID, 'pd_proyecto_contacto', true );

    echo '<p>' . esc_html__( 'Cómo contactar al proyecto: email, teléfono, formulario o redes.', 'wp-song-study-blocks' ) . '</p>';
    printf(
        '<textarea name="pd_proyecto_contacto" rows="4" style="width:100%%;">%s</textarea>',
        esc_textarea( (string) $contact )
    );
}

/**
 * Render del meta box de galería.
 *
 * @param WP_Post $post Post actual.
 * @return void
 */
function wpssb_render_project_gallery_meta_box( WP_Post $post ) {
    $gallery = wpssb_sanitize_id_list( get_post_meta( $post->ID, 'pd_proyecto_galeria', true ) );

    echo '<div class="wpssb-project-gallery-meta" data-initial="' . esc_attr( implode( ',', $gallery ) ) . '">';
    echo '<p>' . esc_html__( 'Selecciona imágenes para la galería del proyecto.', 'wp-song-study-blocks' ) . '</p>';
    echo '<input type="hidden" name="pd_proyecto_galeria" value="' . esc_attr( implode( ',', $gallery ) ) . '" />';
    echo '<button type="button" class="button wpssb-project-gallery-select">' . esc_html__( 'Elegir imágenes', 'wp-song-study-blocks' ) . '</button>';
    echo '<button type="button" class="button wpssb-project-gallery-clear" style="margin-left:6px;">' . esc_html__( 'Limpiar galería', 'wp-song-study-blocks' ) . '</button>';
    echo '<div class="wpssb-project-gallery-preview" style="margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;"></div>';
    echo '</div>';
}

/**
 * Render del meta box de presskit.
 *
 * @param WP_Post $post Post actual.
 * @return void
 */
function wpssb_render_project_presskit_meta_box( WP_Post $post ) {
    $tagline  = get_post_meta( $post->ID, 'pd_proyecto_tagline', true );
    $presskit = get_post_meta( $post->ID, 'pd_proyecto_presskit', true );
    $links    = get_post_meta( $post->ID, 'pd_proyecto_links', true );

    echo '<p>' . esc_html__( 'Resume el proyecto como presskit: tagline, descripción breve y enlaces.', 'wp-song-study-blocks' ) . '</p>';
    printf(
        '<label style="display:block;margin-bottom:10px;"><strong>%s</strong><br/><input type="text" name="pd_proyecto_tagline" value="%s" style="width:100%%;" /></label>',
        esc_html__( 'Tagline', 'wp-song-study-blocks' ),
        esc_attr( (string) $tagline )
    );

    printf(
        '<label style="display:block;margin-bottom:10px;"><strong>%s</strong><br/><textarea name="pd_proyecto_presskit" rows="4" style="width:100%%;">%s</textarea></label>',
        esc_html__( 'Descripción / Presskit', 'wp-song-study-blocks' ),
        esc_textarea( (string) $presskit )
    );

    printf(
        '<label style="display:block;"><strong>%s</strong><br/><textarea name="pd_proyecto_links" rows="3" style="width:100%%;">%s</textarea></label>',
        esc_html__( 'Links (uno por línea)', 'wp-song-study-blocks' ),
        esc_textarea( (string) $links )
    );
}

/**
 * Guarda meta del proyecto.
 *
 * @param int     $post_id ID del post.
 * @param WP_Post $post    Post actual.
 * @return void
 */
function wpssb_save_project_meta( $post_id, $post ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! $post instanceof WP_Post || WPSSB_PROJECT_POST_TYPE !== $post->post_type ) {
        return;
    }

    if ( ! isset( $_POST['wpssb_project_meta_nonce'] ) || ! wp_verify_nonce( $_POST['wpssb_project_meta_nonce'], 'wpssb_save_project_meta' ) ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $collaborators = isset( $_POST['pd_proyecto_colaboradores'] ) ? wpssb_sanitize_id_list( wp_unslash( $_POST['pd_proyecto_colaboradores'] ) ) : [];
    update_post_meta( $post_id, 'pd_proyecto_colaboradores', $collaborators );

    if ( isset( $_POST['pd_proyecto_galeria'] ) ) {
        update_post_meta( $post_id, 'pd_proyecto_galeria', wpssb_sanitize_id_list( wp_unslash( $_POST['pd_proyecto_galeria'] ) ) );
    }

    if ( isset( $_POST['pd_proyecto_contacto'] ) ) {
        update_post_meta( $post_id, 'pd_proyecto_contacto', wp_kses_post( wp_unslash( $_POST['pd_proyecto_contacto'] ) ) );
    }

    if ( isset( $_POST['pd_proyecto_tagline'] ) ) {
        update_post_meta( $post_id, 'pd_proyecto_tagline', sanitize_text_field( wp_unslash( $_POST['pd_proyecto_tagline'] ) ) );
    }

    if ( isset( $_POST['pd_proyecto_presskit'] ) ) {
        update_post_meta( $post_id, 'pd_proyecto_presskit', wp_kses_post( wp_unslash( $_POST['pd_proyecto_presskit'] ) ) );
    }

    if ( isset( $_POST['pd_proyecto_links'] ) ) {
        update_post_meta( $post_id, 'pd_proyecto_links', sanitize_textarea_field( wp_unslash( $_POST['pd_proyecto_links'] ) ) );
    }
}
add_action( 'save_post_' . WPSSB_PROJECT_POST_TYPE, 'wpssb_save_project_meta', 10, 2 );

/**
 * Guarda el usuario objetivo vinculado a una página pública.
 *
 * @param int $post_id ID del post.
 * @param WP_Post $post Post actual.
 * @return void
 */
function wpssb_save_collaborator_target_meta( $post_id, $post ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, [ 'page', WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE ], true ) ) {
        return;
    }

    if ( ! isset( $_POST['wpssb_collaborator_target_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['wpssb_collaborator_target_nonce'] ), 'wpssb_save_collaborator_target_meta' ) ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $user_id = isset( $_POST['wpssb_collaborator_target_user'] ) ? absint( wp_unslash( $_POST['wpssb_collaborator_target_user'] ) ) : 0;

    if ( $user_id > 0 ) {
        update_post_meta( $post_id, WPSSB_COLLABORATOR_TARGET_META, $user_id );
        if ( WPSSB_COLLABORATOR_PRESSKIT_POST_TYPE === $post->post_type ) {
            update_user_meta( $user_id, WPSSB_COLLABORATOR_PRESSKIT_USER_META, $post_id );
        }
        return;
    }

    delete_post_meta( $post_id, WPSSB_COLLABORATOR_TARGET_META );
}
add_action( 'save_post', 'wpssb_save_collaborator_target_meta', 10, 2 );

/**
 * Obtiene el usuario objetivo explícito configurado para una página o post.
 *
 * @param int $post_id ID del post.
 * @return int
 */
function wpssb_get_explicit_collaborator_target_user_id( $post_id ) {
    $post_id = absint( $post_id );

    if ( ! $post_id ) {
        return 0;
    }

    return absint( get_post_meta( $post_id, WPSSB_COLLABORATOR_TARGET_META, true ) );
}

/**
 * Devuelve el usuario owner principal del sitio.
 *
 * Prioriza el usuario cuyo correo coincide con `admin_email` y, si no existe,
 * usa el primer administrador disponible.
 *
 * @return int
 */
function wpssb_get_primary_site_owner_user_id() {
    $admin_email = sanitize_email( (string) get_option( 'admin_email' ) );

    if ( '' !== $admin_email ) {
        $user = get_user_by( 'email', $admin_email );

        if ( $user instanceof WP_User ) {
            return (int) $user->ID;
        }
    }

    $admins = get_users(
        [
            'role'    => 'administrator',
            'orderby' => 'ID',
            'order'   => 'ASC',
            'number'  => 1,
            'fields'  => 'ID',
        ]
    );

    return ! empty( $admins[0] ) ? (int) $admins[0] : 0;
}

/**
 * Devuelve el slug preferido del presskit público de un usuario.
 *
 * @param WP_User $user Usuario objetivo.
 * @return string
 */
function wpssb_get_preferred_collaborator_presskit_slug( WP_User $user ) {
    $candidate = sanitize_title( (string) $user->user_nicename );

    if ( '' === $candidate ) {
        $candidate = sanitize_title( (string) $user->user_login );
    }

    if ( '' === $candidate ) {
        $candidate = 'presskit-' . (int) $user->ID;
    }

    return $candidate;
}

/**
 * Sincroniza el slug del presskit con el identificador público del usuario.
 *
 * @param int $post_id ID del presskit.
 * @param int $user_id ID del usuario.
 * @return void
 */
function wpssb_sync_collaborator_presskit_post_slug( $post_id, $user_id ) {
    $post_id = absint( $post_id );
    $user_id = absint( $user_id );

    if ( $post_id <= 0 || $user_id <= 0 ) {
        return;
    }

    $post = get_post( $post_id );
    $user = get_user_by( 'id', $user_id );

    if ( ! $post instanceof WP_Post || ! $user instanceof WP_User ) {
        return;
    }

    $preferred_slug = wp_unique_post_slug(
        wpssb_get_preferred_collaborator_presskit_slug( $user ),
        $post_id,
        $post->post_status,
        $post->post_type,
        (int) $post->post_parent
    );

    if ( $preferred_slug === $post->post_name ) {
        return;
    }

    wp_update_post(
        [
            'ID'        => $post_id,
            'post_name' => $preferred_slug,
        ]
    );
}

/**
 * Resuelve el usuario objetivo de una página pública de presskit.
 *
 * @param int $post_id ID de la página.
 * @return int
 */
function wpssb_resolve_presskit_page_target_user_id( $post_id ) {
    $post_id = absint( $post_id );

    if ( $post_id <= 0 ) {
        return 0;
    }

    $user_id = wpssb_get_explicit_collaborator_target_user_id( $post_id );

    if ( $user_id > 0 ) {
        return $user_id;
    }

    $owner_user_id = wpssb_get_primary_site_owner_user_id();

    if ( $owner_user_id > 0 ) {
        return $owner_user_id;
    }

    return (int) get_post_field( 'post_author', $post_id );
}

/**
 * Redirige páginas con template `presskit` al documento personal real del usuario objetivo.
 *
 * @return void
 */
function wpssb_redirect_presskit_page_to_personal_presskit() {
    if ( is_admin() || ! is_page() || is_feed() || is_preview() ) {
        return;
    }

    if ( wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
        return;
    }

    $page_id = (int) get_queried_object_id();

    if ( $page_id <= 0 || 'presskit' !== get_page_template_slug( $page_id ) ) {
        return;
    }

    $user_id = wpssb_resolve_presskit_page_target_user_id( $page_id );

    if ( $user_id <= 0 ) {
        return;
    }

    $target_url = wpssb_get_collaborator_public_url( $user_id );
    $current_url = get_permalink( $page_id );

    if ( ! $target_url || ! $current_url || untrailingslashit( $target_url ) === untrailingslashit( $current_url ) ) {
        return;
    }

    wp_safe_redirect( $target_url, 302, 'WP Song Study Blocks' );
    exit;
}
add_action( 'template_redirect', 'wpssb_redirect_presskit_page_to_personal_presskit', 11 );

/**
 * Carga assets admin para proyectos y perfiles.
 *
 * @param string $hook Hook actual.
 * @return void
 */
function wpssb_enqueue_project_admin_assets( $hook ) {
    unset( $hook );

    return;
}
add_action( 'admin_enqueue_scripts', 'wpssb_enqueue_project_admin_assets' );

/**
 * Renderiza contenido de un presskit con contexto correcto de post.
 *
 * @param int         $post_id           ID del presskit.
 * @param string|null $content_override  Contenido alterno.
 * @return string
 */
function wpssb_render_saved_presskit_post_content( $post_id, $content_override = null ) {
    $post_id = absint( $post_id );

    if ( $post_id <= 0 ) {
        return '';
    }

    $post = get_post( $post_id );

    if ( ! $post instanceof WP_Post ) {
        return '';
    }

    $content       = is_string( $content_override ) ? $content_override : wpssb_get_effective_presskit_document_content( $post );
    $previous_post = isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof WP_Post ? $GLOBALS['post'] : null;
    $GLOBALS['post'] = $post;
    setup_postdata( $post );

    $context_filter = static function ( $context ) use ( $post_id, $post ) {
        if ( empty( $context['postId'] ) ) {
            $context['postId'] = $post_id;
        }

        if ( empty( $context['postType'] ) ) {
            $context['postType'] = $post->post_type;
        }

        return $context;
    };

    add_filter( 'render_block_context', $context_filter, 10, 1 );
    $rendered = apply_filters( 'the_content', $content );
    remove_filter( 'render_block_context', $context_filter, 10 );

    if ( $previous_post instanceof WP_Post ) {
        $GLOBALS['post'] = $previous_post;
        setup_postdata( $previous_post );
    } else {
        wp_reset_postdata();
    }

    return (string) $rendered;
}

/**
 * REST: renderiza preview del presskit personal para edición frontal.
 *
 * @return void
 */
function wpssb_register_presskit_preview_rest_route() {
    register_rest_route(
        'wpssb/v1',
        '/presskit-preview/(?P<id>\d+)',
        [
            'methods'             => WP_REST_Server::CREATABLE,
            'permission_callback' => static function ( WP_REST_Request $request ) {
                return current_user_can( 'edit_post', (int) $request['id'] );
            },
            'callback'            => static function ( WP_REST_Request $request ) {
                $post_id = (int) $request['id'];
                $content = $request->get_param( 'content' );

                return rest_ensure_response(
                    [
                        'html' => wpssb_render_saved_presskit_post_content(
                            $post_id,
                            is_string( $content ) ? $content : null
                        ),
                    ]
                );
            },
        ]
    );
}
add_action( 'rest_api_init', 'wpssb_register_presskit_preview_rest_route' );

/**
 * Encola scripts y estilos de editor para todos los bloques registrados.
 *
 * Esto permite usar bloques nativos y de terceros dentro del editor frontal
 * sin depender del admin de WordPress.
 *
 * @return void
 */
function wpssb_enqueue_frontend_block_editor_block_assets() {
    static $enqueued = false;

    if ( $enqueued || ! class_exists( 'WP_Block_Type_Registry' ) ) {
        return;
    }

    $enqueued = true;
    $registry = WP_Block_Type_Registry::get_instance()->get_all_registered();

    foreach ( $registry as $block_type ) {
        if ( ! $block_type instanceof WP_Block_Type ) {
            continue;
        }

        foreach ( [ 'editor_script_handles', 'script_handles', 'view_script_handles', 'editor_script', 'script', 'view_script' ] as $property ) {
            if ( empty( $block_type->{$property} ) || ! is_array( $block_type->{$property} ) ) {
                if ( empty( $block_type->{$property} ) || ! is_string( $block_type->{$property} ) ) {
                    continue;
                }

                $handles = [ $block_type->{$property} ];
            } else {
                $handles = $block_type->{$property};
            }

            foreach ( $handles as $handle ) {
                if ( is_string( $handle ) && wp_script_is( $handle, 'registered' ) ) {
                    wp_enqueue_script( $handle );
                }
            }
        }

        foreach ( [ 'editor_style_handles', 'style_handles', 'view_style_handles', 'editor_style', 'style', 'view_style' ] as $property ) {
            if ( empty( $block_type->{$property} ) || ! is_array( $block_type->{$property} ) ) {
                if ( empty( $block_type->{$property} ) || ! is_string( $block_type->{$property} ) ) {
                    continue;
                }

                $handles = [ $block_type->{$property} ];
            } else {
                $handles = $block_type->{$property};
            }

            foreach ( $handles as $handle ) {
                if ( is_string( $handle ) && wp_style_is( $handle, 'registered' ) ) {
                    wp_enqueue_style( $handle );
                }
            }
        }
    }

    $core_editor_script_handles = [
        'wp-block-library',
        'wp-format-library',
        'wp-block-paragraph',
        'wp-block-heading',
        'wp-block-list',
        'wp-block-quote',
        'wp-block-image',
        'wp-block-gallery',
        'wp-block-group',
        'wp-block-columns',
        'wp-block-column',
        'wp-block-buttons',
        'wp-block-button',
        'wp-block-cover',
        'wp-block-media-text',
        'wp-block-file',
        'wp-block-audio',
        'wp-block-video',
        'wp-block-separator',
        'wp-block-spacer',
        'wp-block-pullquote',
        'wp-block-table',
        'wp-block-details',
        'wp-block-code',
        'wp-block-preformatted',
        'wp-block-verse',
        'wp-block-social-links',
        'wp-block-social-link',
        'wp-block-site-logo',
    ];

    foreach ( $core_editor_script_handles as $handle ) {
        if ( wp_script_is( $handle, 'registered' ) ) {
            wp_enqueue_script( $handle );
        }
    }
}

/**
 * Resuelve una URL de asset local a ruta del sistema.
 *
 * @param string $src URL o ruta relativa.
 * @return string
 */
function wpssb_resolve_local_asset_path_from_src( $src ) {
    $src = (string) $src;

    if ( '' === $src ) {
        return '';
    }

    $src = strtok( $src, '?' );

    if ( 0 === strpos( $src, '/' ) && file_exists( ABSPATH . ltrim( $src, '/' ) ) ) {
        return ABSPATH . ltrim( $src, '/' );
    }

    $parsed_path = wp_parse_url( $src, PHP_URL_PATH );

    if ( ! is_string( $parsed_path ) || '' === $parsed_path ) {
        return '';
    }

    $site_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );

    if ( '' !== $site_path && 0 === strpos( $parsed_path, $site_path ) ) {
        $parsed_path = substr( $parsed_path, strlen( $site_path ) );
    }

    $parsed_path = ltrim( $parsed_path, '/' );

    if ( '' === $parsed_path ) {
        return '';
    }

    $candidate = ABSPATH . $parsed_path;

    return file_exists( $candidate ) ? $candidate : '';
}

/**
 * Devuelve CSS inline util a inyectar dentro del canvas del editor frontal.
 *
 * @return array<int, array<string, string>>
 */
function wpssb_get_frontend_presskit_editor_iframe_styles() {
    global $wp_styles;

    $styles = [];
    $handles = [
        'wp-block-library',
        'wp-block-library-theme',
        'global-styles',
        'classic-theme-styles',
        'pertenencia-digital-style',
    ];

    if ( isset( $wp_styles->queue ) && is_array( $wp_styles->queue ) ) {
        foreach ( $wp_styles->queue as $queued_handle ) {
            if ( ! is_string( $queued_handle ) ) {
                continue;
            }

            if ( 0 === strpos( $queued_handle, 'wp-block-' ) || 0 === strpos( $queued_handle, 'core-block-supports' ) ) {
                $handles[] = $queued_handle;
            }
        }
    }

    $handles = array_values( array_unique( $handles ) );

    foreach ( $handles as $handle ) {
        if ( ! isset( $wp_styles->registered[ $handle ] ) ) {
            continue;
        }

        $registered = $wp_styles->registered[ $handle ];
        $src        = isset( $registered->src ) ? (string) $registered->src : '';
        $path       = wpssb_resolve_local_asset_path_from_src( $src );

        if ( '' === $path || ! file_exists( $path ) || ! is_readable( $path ) ) {
            continue;
        }

        $css = file_get_contents( $path );

        if ( ! is_string( $css ) || '' === trim( $css ) ) {
            continue;
        }

        $styles[] = [
            'css' => $css,
        ];

        if ( ! empty( $registered->extra['after'] ) && is_array( $registered->extra['after'] ) ) {
            foreach ( $registered->extra['after'] as $after_css ) {
                if ( is_string( $after_css ) && '' !== trim( $after_css ) ) {
                    $styles[] = [
                        'css' => $after_css,
                    ];
                }
            }
        }
    }

    return $styles;
}

/**
 * Encola el editor de bloques frontal para el presskit personal.
 *
 * @param int $post_id ID del presskit.
 * @return void
 */
function wpssb_enqueue_frontend_presskit_editor_assets( $post_id ) {
    static $enqueued = [];

    $post_id = absint( $post_id );

    if ( $post_id <= 0 || isset( $enqueued[ $post_id ] ) ) {
        return;
    }

    $post = get_post( $post_id );

    if ( ! $post instanceof WP_Post || ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $editor_settings = [];

    if ( class_exists( 'WP_Block_Editor_Context' ) && function_exists( 'get_block_editor_settings' ) ) {
        $editor_settings = get_block_editor_settings(
            [],
            new WP_Block_Editor_Context(
                [
                    'post' => $post,
                ]
            )
        );
    }

    $editor_settings['allowedBlockTypes'] = true;
    $editor_settings['templateLock']      = false;

    wp_enqueue_media();
    wp_enqueue_style( 'wp-block-library' );
    wp_enqueue_style( 'wp-block-library-theme' );
    wp_enqueue_style( 'wp-components' );
    wp_enqueue_editor_format_library_assets();

    if ( wp_style_is( 'wp-block-editor', 'registered' ) ) {
        wp_enqueue_style( 'wp-block-editor' );
    }

    if ( wp_style_is( 'wp-edit-blocks', 'registered' ) ) {
        wp_enqueue_style( 'wp-edit-blocks' );
    }

    if ( wp_script_is( 'wp-block-library', 'registered' ) ) {
        wp_enqueue_script( 'wp-block-library' );
    }

    wpssb_enqueue_frontend_block_editor_block_assets();

    $editor_settings['styles'] = array_merge(
        isset( $editor_settings['styles'] ) && is_array( $editor_settings['styles'] ) ? $editor_settings['styles'] : [],
        wpssb_get_frontend_presskit_editor_iframe_styles()
    );

    $editor_script_dependencies = [
        'wp-api-fetch',
        'wp-block-editor',
        'wp-blocks',
        'wp-components',
        'wp-compose',
        'wp-core-data',
        'wp-data',
        'wp-dom-ready',
        'wp-editor',
        'wp-element',
        'wp-html-entities',
        'wp-hooks',
        'wp-i18n',
        'wp-primitives',
        'wp-rich-text',
    ];

    if ( wp_script_is( 'wp-media-utils', 'registered' ) && ! in_array( 'wp-media-utils', $editor_script_dependencies, true ) ) {
        $editor_script_dependencies[] = 'wp-media-utils';
    }

    if ( wp_script_is( 'wp-block-library', 'registered' ) ) {
        array_unshift( $editor_script_dependencies, 'wp-block-library' );
    }

    if ( wp_script_is( 'wp-format-library', 'registered' ) && ! in_array( 'wp-format-library', $editor_script_dependencies, true ) ) {
        $editor_script_dependencies[] = 'wp-format-library';
    }

    wp_enqueue_script(
        'wpssb-frontend-presskit-editor',
        WPSSB_URL . 'assets/project-frontend/presskit-editor.js',
        $editor_script_dependencies,
        file_exists( WPSSB_PATH . 'assets/project-frontend/presskit-editor.js' ) ? (string) filemtime( WPSSB_PATH . 'assets/project-frontend/presskit-editor.js' ) : WPSSB_VERSION,
        true
    );

    wp_add_inline_script(
        'wpssb-frontend-presskit-editor',
        'window.wpssbFrontendPresskitEditor = ' . wp_json_encode(
            [
                'postId'        => $post_id,
                'postType'      => $post->post_type,
                'content'       => wpssb_get_effective_presskit_document_content( $post ),
                'previewHtml'   => wpssb_render_saved_presskit_post_content( $post_id ),
                'previewPath'   => '/wpssb/v1/presskit-preview/' . $post_id,
                'settings'      => $editor_settings,
                'restPath'      => '/wp/v2/' . $post->post_type,
                'restNonce'     => wp_create_nonce( 'wp_rest' ),
                'saveLabel'     => __( 'Guardar presskit', 'wp-song-study-blocks' ),
                'savedLabel'    => __( 'Presskit actualizado.', 'wp-song-study-blocks' ),
                'errorLabel'    => __( 'No se pudo guardar el presskit.', 'wp-song-study-blocks' ),
                'editTabLabel'  => __( 'Editar', 'wp-song-study-blocks' ),
                'previewLabel'  => __( 'Preview', 'wp-song-study-blocks' ),
                'inserterLabel' => __( 'Insertar bloque', 'wp-song-study-blocks' ),
            ]
        ) . ';',
        'before'
    );

    $enqueued[ $post_id ] = true;
}

/**
 * Renderiza el panel frontal de edición/preview del presskit personal.
 *
 * @param int $post_id ID del presskit.
 * @return string
 */
function wpssb_render_frontend_presskit_workbench( $post_id ) {
    $post_id = absint( $post_id );

    if ( $post_id <= 0 || ! current_user_can( 'edit_post', $post_id ) ) {
        return '';
    }

    wpssb_enqueue_frontend_presskit_editor_assets( $post_id );

    $output  = '<section class="pd-membership-panel pd-membership-panel--presskit-workbench">';
    $output .= '<header class="pd-membership-presskit-workbench__header">';
    $output .= '<div>';
    $output .= '<p class="pd-membership-shell__eyebrow">' . esc_html__( 'Presskit personal', 'wp-song-study-blocks' ) . '</p>';
    $output .= '<h2>' . esc_html__( 'Editar tu documento público con vista en vivo', 'wp-song-study-blocks' ) . '</h2>';
    $output .= '<p>' . esc_html__( 'Aquí trabajas directamente sobre tu presskit real con bloques. El propio lienzo de edición ya respeta los estilos del tema para que no dependas de un preview aparte.', 'wp-song-study-blocks' ) . '</p>';
    $output .= '</div>';
    $output .= '</header>';
    $output .= '<div id="wpssb-frontend-presskit-editor" class="pd-membership-presskit-workbench__app"></div>';
    $output .= '</section>';

    return $output;
}

/**
 * Renderiza campos de presskit para perfiles de usuario.
 *
 * @param WP_User $user Usuario actual.
 * @return void
 */
function wpssb_render_collaborator_presskit_fields( $user ) {
    if ( ! $user instanceof WP_User ) {
        return;
    }

    $tagline  = get_user_meta( $user->ID, 'pd_colaborador_tagline', true );
    $presskit_post_id = wpssb_get_collaborator_presskit_post_id( $user->ID );
    $presskit_edit_url = $presskit_post_id ? wpssb_get_frontend_membership_url( $user->ID, true ) : '';
    $presskit_view_url = wpssb_get_collaborator_public_url( $user->ID );

    echo '<h2>' . esc_html__( 'Presskit del colaborador', 'wp-song-study-blocks' ) . '</h2>';
    echo '<p>' . esc_html__( 'La composición pública del presskit ahora vive en un documento editable con bloques. Aquí solo se conservan datos base mínimos del perfil.', 'wp-song-study-blocks' ) . '</p>';
    echo '<p>';
    if ( $presskit_edit_url ) {
        echo '<a class="button button-secondary" href="' . esc_url( $presskit_edit_url ) . '">' . esc_html__( 'Editar documento del presskit', 'wp-song-study-blocks' ) . '</a> ';
    }
    echo '<a class="button button-secondary" href="' . esc_url( $presskit_view_url ) . '">' . esc_html__( 'Ver página pública', 'wp-song-study-blocks' ) . '</a>';
    echo '</p>';
    echo '<table class="form-table" role="presentation">';
    echo '<tr><th><label for="pd_colaborador_tagline">' . esc_html__( 'Tagline', 'wp-song-study-blocks' ) . '</label></th>';
    echo '<td><input type="text" name="pd_colaborador_tagline" id="pd_colaborador_tagline" value="' . esc_attr( (string) $tagline ) . '" class="regular-text" /></td></tr>';
    echo '</table>';
}
add_action( 'show_user_profile', 'wpssb_render_collaborator_presskit_fields' );
add_action( 'edit_user_profile', 'wpssb_render_collaborator_presskit_fields' );

/**
 * Guarda meta de presskit del colaborador.
 *
 * @param int $user_id Usuario a guardar.
 * @return void
 */
function wpssb_save_collaborator_presskit_fields( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return;
    }

    if ( isset( $_POST['pd_colaborador_tagline'] ) ) {
        update_user_meta( $user_id, 'pd_colaborador_tagline', sanitize_text_field( wp_unslash( $_POST['pd_colaborador_tagline'] ) ) );
    }
}
add_action( 'personal_options_update', 'wpssb_save_collaborator_presskit_fields' );
add_action( 'edit_user_profile_update', 'wpssb_save_collaborator_presskit_fields' );

/**
 * Indica si el tema activo expone una template FSE concreta.
 *
 * El slug coincide con el valor guardado en `_wp_page_template` para block themes.
 *
 * @param string $slug Slug de template sin extensión.
 * @return bool
 */
function wpssb_theme_has_block_template_slug( $slug ) {
    $slug = sanitize_title( (string) $slug );

    if ( '' === $slug ) {
        return false;
    }

    $candidate_paths = array_unique(
        array_filter(
            [
                trailingslashit( get_stylesheet_directory() ) . 'templates/' . $slug . '.html',
                trailingslashit( get_template_directory() ) . 'templates/' . $slug . '.html',
            ]
        )
    );

    foreach ( $candidate_paths as $candidate_path ) {
        if ( file_exists( $candidate_path ) ) {
            return true;
        }
    }

    return false;
}

/**
 * Devuelve la template preferida para la página editorial de presskit.
 *
 * La capa visual debe vivir en el tema; el plugin solo la detecta y la utiliza
 * cuando existe para no duplicar markup ni acoplarse a un tema concreto.
 *
 * @return string
 */
function wpssb_get_preferred_presskit_page_template() {
    return wpssb_theme_has_block_template_slug( 'presskit' ) ? 'presskit' : '';
}

/**
 * Asigna la template `presskit` a páginas llamadas `presskit` cuando el tema activo
 * la declara y la página todavía usa la template por defecto.
 *
 * @return void
 */
function wpssb_sync_presskit_page_template_assignment() {
    $template_slug = wpssb_get_preferred_presskit_page_template();

    if ( '' === $template_slug ) {
        return;
    }

    $presskit_pages = get_posts(
        [
            'post_type'      => 'page',
            'post_status'    => [ 'publish', 'draft', 'pending', 'private' ],
            'name'           => 'presskit',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]
    );

    foreach ( $presskit_pages as $page_id ) {
        $page_id = (int) $page_id;
        if ( $page_id <= 0 ) {
            continue;
        }

        $current_template = (string) get_post_meta( $page_id, '_wp_page_template', true );

        if ( '' !== $current_template && 'default' !== $current_template ) {
            continue;
        }

        update_post_meta( $page_id, '_wp_page_template', $template_slug );
    }
}
add_action( 'admin_init', 'wpssb_sync_presskit_page_template_assignment' );

/**
 * Indica si el usuario actual puede editar su propio presskit desde frontend.
 *
 * @return bool
 */
function wpssb_current_user_can_manage_own_presskit() {
    $user_id = get_current_user_id();

    return wpssb_current_user_can_manage_presskit_user( $user_id );
}

/**
 * Indica si el usuario actual puede gestionar el presskit de un usuario objetivo.
 *
 * @param int $user_id Usuario objetivo.
 * @return bool
 */
function wpssb_current_user_can_manage_presskit_user( $user_id ) {
    $user_id = absint( $user_id );

    if ( $user_id <= 0 ) {
        return false;
    }

    $current_user_id = get_current_user_id();

    if ( $current_user_id <= 0 ) {
        return false;
    }

    if ( current_user_can( 'manage_options' ) || current_user_can( 'edit_user', $user_id ) ) {
        return true;
    }

    return $current_user_id === $user_id && wpssb_user_can_access_private_music_area( $current_user_id );
}

/**
 * Devuelve la URL frontal de "Mi pertenencia digital".
 *
 * @param int  $user_id     Usuario objetivo.
 * @param bool $open_editor Si debe apuntar al editor frontal.
 * @return string
 */
function wpssb_get_frontend_membership_url( $user_id = 0, $open_editor = false ) {
    $user_id = absint( $user_id );
    $page_id = 0;
    $pages   = get_posts(
        [
            'post_type'      => 'page',
            'post_status'    => [ 'publish', 'private' ],
            'meta_key'       => '_wp_page_template',
            'meta_value'     => 'mi-pertenencia',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]
    );

    if ( ! empty( $pages[0] ) ) {
        $page_id = (int) $pages[0];
    }

    if ( ! $page_id ) {
        $page = get_page_by_path( 'mi-pertenencia' );

        if ( $page instanceof WP_Post ) {
            $page_id = (int) $page->ID;
        }
    }

    $url = $page_id ? get_permalink( $page_id ) : home_url( '/mi-pertenencia/' );
    $args = [];

    if ( $user_id > 0 && current_user_can( 'manage_options' ) && get_current_user_id() !== $user_id ) {
        $args['membership_user'] = $user_id;
    }

    if ( $open_editor ) {
        $args['membership_view'] = 'editor';
    }

    if ( ! empty( $args ) ) {
        $url = add_query_arg( $args, $url );
    }

    return $open_editor ? $url . '#wpssb-frontend-presskit-editor' : $url;
}

/**
 * Devuelve usuarios gestionables para la vista frontend de pertenencia.
 *
 * @return WP_User[]
 */
function wpssb_get_manageable_membership_users() {
    if ( current_user_can( 'manage_options' ) ) {
        return get_users(
            [
                'orderby' => 'display_name',
                'order'   => 'ASC',
            ]
        );
    }

    $current_user = wp_get_current_user();

    return $current_user instanceof WP_User && $current_user->exists()
        ? [ $current_user ]
        : [];
}

/**
 * Resuelve el usuario objetivo de la vista frontend de pertenencia.
 *
 * @param array $settings Ajustes del bloque.
 * @return int
 */
function wpssb_resolve_membership_target_user_id( $settings = [] ) {
    $target_user_id = 0;

    if ( isset( $settings['target_user_id'] ) ) {
        $target_user_id = absint( $settings['target_user_id'] );
    }

    if ( current_user_can( 'manage_options' ) && isset( $_GET['membership_user'] ) ) {
        $target_user_id = absint( wp_unslash( $_GET['membership_user'] ) );
    }

    if ( ! $target_user_id ) {
        $target_user_id = get_current_user_id();
    }

    if ( ! wpssb_current_user_can_manage_presskit_user( $target_user_id ) ) {
        return get_current_user_id();
    }

    return absint( $target_user_id );
}

/**
 * Redirige al usuario a una URL segura del frontend de pertenencia.
 *
 * @param string $status Estado a reflejar en query args.
 * @return void
 */
function wpssb_redirect_membership_frontend( $status ) {
    $redirect_to = isset( $_POST['redirect_to'] ) ? wp_unslash( $_POST['redirect_to'] ) : wp_get_referer();
    $redirect_to = $redirect_to ? wp_validate_redirect( $redirect_to, home_url( '/' ) ) : home_url( '/' );
    $redirect_to = add_query_arg( 'wpssb_membership_status', sanitize_key( $status ), $redirect_to );

    wp_safe_redirect( $redirect_to );
    exit;
}

/**
 * Encola assets frontend para gestionar la galería de pertenencia.
 *
 * @return void
 */
function wpssb_enqueue_frontend_membership_assets() {
    static $enqueued = false;

    if ( $enqueued ) {
        return;
    }

    $enqueued = true;

    wp_enqueue_media();
    wp_enqueue_script(
        'wpssb-membership-gallery',
        WPSSB_URL . 'assets/project-frontend/membership-gallery.js',
        [ 'jquery' ],
        WPSSB_VERSION,
        true
    );

    wp_enqueue_script(
        'wpssb-membership-tabs',
        WPSSB_URL . 'assets/project-frontend/membership-tabs.js',
        [],
        file_exists( WPSSB_PATH . 'assets/project-frontend/membership-tabs.js' ) ? (string) filemtime( WPSSB_PATH . 'assets/project-frontend/membership-tabs.js' ) : WPSSB_VERSION,
        true
    );
}

/**
 * Guarda el presskit del usuario actual desde frontend.
 *
 * @return void
 */
function wpssb_handle_frontend_membership_save() {
    if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) {
        wpssb_redirect_membership_frontend( 'invalid_request' );
    }

    if ( ! is_user_logged_in() ) {
        wpssb_redirect_membership_frontend( 'login_required' );
    }

    if ( ! isset( $_POST['wpssb_membership_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['wpssb_membership_nonce'] ), 'wpssb_save_my_membership' ) ) {
        wpssb_redirect_membership_frontend( 'invalid_nonce' );
    }

    $user_id = isset( $_POST['target_user_id'] ) ? absint( wp_unslash( $_POST['target_user_id'] ) ) : get_current_user_id();

    if ( ! wpssb_current_user_can_manage_presskit_user( $user_id ) ) {
        wpssb_redirect_membership_frontend( 'forbidden' );
    }

    if ( isset( $_POST['pd_colaborador_tagline'] ) ) {
        update_user_meta( $user_id, 'pd_colaborador_tagline', sanitize_text_field( wp_unslash( $_POST['pd_colaborador_tagline'] ) ) );
    }

    wp_update_user(
        [
            'ID'          => $user_id,
            'user_url'    => isset( $_POST['pd_colaborador_user_url'] ) ? esc_url_raw( wp_unslash( $_POST['pd_colaborador_user_url'] ) ) : '',
            'description' => isset( $_POST['pd_colaborador_descripcion_corta'] ) ? sanitize_textarea_field( wp_unslash( $_POST['pd_colaborador_descripcion_corta'] ) ) : '',
        ]
    );

    wpssb_redirect_membership_frontend( 'updated' );
}
add_action( 'admin_post_wpssb_save_my_membership', 'wpssb_handle_frontend_membership_save' );

/**
 * Devuelve feedback para la vista frontend de pertenencia.
 *
 * @return array|null
 */
function wpssb_get_membership_feedback() {
    $status = isset( $_GET['wpssb_membership_status'] ) ? sanitize_key( wp_unslash( $_GET['wpssb_membership_status'] ) ) : '';

    if ( ! $status ) {
        return null;
    }

    $messages = [
        'updated'         => [ 'type' => 'success', 'message' => __( 'Los datos base del perfil se actualizaron correctamente.', 'wp-song-study-blocks' ) ],
        'login_required'  => [ 'type' => 'error', 'message' => __( 'Necesitas iniciar sesión para gestionar tu pertenencia.', 'wp-song-study-blocks' ) ],
        'forbidden'       => [ 'type' => 'error', 'message' => __( 'No tienes permisos para editar este perfil.', 'wp-song-study-blocks' ) ],
        'invalid_nonce'   => [ 'type' => 'error', 'message' => __( 'La sesión del formulario expiró. Inténtalo de nuevo.', 'wp-song-study-blocks' ) ],
        'invalid_request' => [ 'type' => 'error', 'message' => __( 'La solicitud recibida no es válida.', 'wp-song-study-blocks' ) ],
    ];

    return $messages[ $status ] ?? null;
}

/**
 * Extrae un mensaje legible del último error OAuth guardado.
 *
 * @param mixed $last_error Error guardado.
 * @return string
 */
function wpssb_get_membership_google_error_message( $last_error ) {
    if ( is_array( $last_error ) ) {
        return sanitize_text_field( (string) ( $last_error['message'] ?? '' ) );
    }

    return sanitize_text_field( (string) $last_error );
}

/**
 * Devuelve labels de scopes faltantes para Drive o Calendar.
 *
 * @param array<string, mixed> $status Estado de Google.
 * @return string[]
 */
function wpssb_get_membership_google_missing_scope_labels( array $status ) {
    $required = array_values( array_filter( array_map( 'strval', (array) ( $status['required_scopes'] ?? [] ) ) ) );
    $granted  = array_values( array_filter( array_map( 'strval', (array) ( $status['granted_scopes'] ?? [] ) ) ) );
    $missing  = array_values( array_diff( $required, $granted ) );

    if ( empty( $missing ) ) {
        return [];
    }

    $labels = function_exists( 'wpss_get_google_drive_scope_labels' ) ? wpss_get_google_drive_scope_labels() : [];

    return array_values(
        array_map(
            static function ( $scope ) use ( $labels ) {
                return isset( $labels[ $scope ] ) ? sanitize_text_field( (string) $labels[ $scope ] ) : sanitize_text_field( (string) $scope );
            },
            $missing
        )
    );
}

/**
 * Renderiza una tarjeta de vinculación Google dentro de Mi pertenencia.
 *
 * @param string $service       Servicio google_drive|google_calendar.
 * @param int    $user_id       Usuario objetivo.
 * @param string $return_url    URL de retorno.
 * @param bool   $allow_connect Si el usuario actual puede iniciar OAuth.
 * @return string
 */
function wpssb_render_membership_google_connection_card( $service, $user_id, $return_url, $allow_connect = true ) {
    $service    = sanitize_key( (string) $service );
    $user_id    = absint( $user_id );
    $return_url = esc_url_raw( (string) $return_url );

    if ( $user_id <= 0 || ! in_array( $service, [ 'google_drive', 'google_calendar' ], true ) ) {
        return '';
    }

    $is_calendar = 'google_calendar' === $service;
    $title       = $is_calendar ? __( 'Google Calendar', 'wp-song-study-blocks' ) : __( 'Google Drive', 'wp-song-study-blocks' );
    $summary     = $is_calendar
        ? __( 'Usado para crear eventos de ensayos confirmados e invitar al grupo.', 'wp-song-study-blocks' )
        : __( 'Usado para adjuntos, audios, fotos y materiales del cancionero.', 'wp-song-study-blocks' );

    $status = [];
    if ( $is_calendar && function_exists( 'wpss_get_google_calendar_status_payload' ) ) {
        $status = wpss_get_google_calendar_status_payload( $user_id );
    } elseif ( ! $is_calendar && function_exists( 'wpss_get_google_drive_status_payload' ) ) {
        $status = wpss_get_google_drive_status_payload( $user_id );
    }

    if ( ! is_array( $status ) || empty( $status ) ) {
        return '<article class="pd-membership-google-card is-warning"><div><h3>' . esc_html( $title ) . '</h3><p>' . esc_html__( 'La integración no está disponible en este momento.', 'wp-song-study-blocks' ) . '</p></div></article>';
    }

    $configured  = ! empty( $status['configured'] );
    $connected   = ! empty( $status['connected'] );
    $has_refresh = ! empty( $status['has_refresh_token'] );
    $has_scope   = ! empty( $status['has_required_scope'] );
    $ready       = $configured && $connected && $has_refresh && $has_scope;
    $missing     = wpssb_get_membership_google_missing_scope_labels( $status );
    $last_error  = wpssb_get_membership_google_error_message( $status['last_error'] ?? '' );
    $connect_url = '';

    if ( $allow_connect && $configured ) {
        if ( $is_calendar && function_exists( 'wpss_get_google_calendar_connect_url' ) && function_exists( 'wpss_get_google_calendar_oauth_scopes' ) ) {
            $connect_url = wpss_get_google_calendar_connect_url( $user_id, $return_url, wpss_get_google_calendar_oauth_scopes(), true );
        } elseif ( ! $is_calendar && function_exists( 'wpss_get_google_drive_connect_url' ) && function_exists( 'wpss_get_google_drive_oauth_scopes' ) ) {
            $connect_url = wpss_get_google_drive_connect_url( $user_id, $return_url, wpss_get_google_drive_oauth_scopes(), true );
        }
    }

    if ( $ready ) {
        $status_label = __( 'Listo', 'wp-song-study-blocks' );
        $message      = $is_calendar
            ? __( 'Calendar está listo para crear y actualizar eventos de ensayo.', 'wp-song-study-blocks' )
            : __( 'Drive está listo para operar adjuntos y materiales privados.', 'wp-song-study-blocks' );
    } elseif ( ! $configured ) {
        $status_label = __( 'Sin credenciales OAuth', 'wp-song-study-blocks' );
        $message      = __( 'Faltan Client ID y Client Secret en la configuración OAuth activa.', 'wp-song-study-blocks' );
    } elseif ( ! $connected ) {
        $status_label = __( 'Sin conectar', 'wp-song-study-blocks' );
        $message      = __( 'La cuenta todavía no está vinculada con Google.', 'wp-song-study-blocks' );
    } elseif ( ! $has_refresh ) {
        $status_label = __( 'Reconexión necesaria', 'wp-song-study-blocks' );
        $message      = __( 'La conexión no tiene refresh token. Reconecta para permitir sincronización estable.', 'wp-song-study-blocks' );
    } elseif ( ! $has_scope ) {
        $status_label = __( 'Permisos incompletos', 'wp-song-study-blocks' );
        $message      = __( 'La conexión existe, pero necesita permisos adicionales.', 'wp-song-study-blocks' );
    } else {
        $status_label = __( 'Revisar conexión', 'wp-song-study-blocks' );
        $message      = __( 'La vinculación existe, pero no está lista para operar.', 'wp-song-study-blocks' );
    }

    $output  = '<article class="pd-membership-google-card ' . ( $ready ? 'is-ready' : 'is-warning' ) . '">';
    $output .= '<div class="pd-membership-google-card__header">';
    $output .= '<div>';
    $output .= '<p class="pd-membership-shell__eyebrow">' . esc_html__( 'Vinculación Google', 'wp-song-study-blocks' ) . '</p>';
    $output .= '<h3>' . esc_html( $title ) . '</h3>';
    $output .= '<p>' . esc_html( $summary ) . '</p>';
    $output .= '</div>';
    $output .= '<span class="pd-membership-google-card__status">' . esc_html( $status_label ) . '</span>';
    $output .= '</div>';
    $output .= '<p class="pd-membership-google-card__message">' . esc_html( $message ) . '</p>';
    $output .= '<dl class="pd-membership-summary pd-membership-google-card__details">';
    $output .= '<div><dt>' . esc_html__( 'Cuenta', 'wp-song-study-blocks' ) . '</dt><dd>' . esc_html( ! empty( $status['account_email'] ) ? (string) $status['account_email'] : __( 'Sin cuenta conectada', 'wp-song-study-blocks' ) ) . '</dd></div>';
    $output .= '<div><dt>' . esc_html__( 'Tokens', 'wp-song-study-blocks' ) . '</dt><dd>' . esc_html( sprintf( __( 'Access: %1$s · Refresh: %2$s', 'wp-song-study-blocks' ), ! empty( $status['has_access_token'] ) ? __( 'sí', 'wp-song-study-blocks' ) : __( 'no', 'wp-song-study-blocks' ), $has_refresh ? __( 'sí', 'wp-song-study-blocks' ) : __( 'no', 'wp-song-study-blocks' ) ) ) . '</dd></div>';
    $output .= '<div><dt>' . esc_html__( 'Credenciales', 'wp-song-study-blocks' ) . '</dt><dd>' . esc_html( 'user' === ( $status['credentials_source'] ?? '' ) ? __( 'Perfil de usuario', 'wp-song-study-blocks' ) : __( 'Globales del sitio', 'wp-song-study-blocks' ) ) . '</dd></div>';

    if ( ! $is_calendar && ! empty( $status['folder_url'] ) ) {
        $folder_label = ! empty( $status['folder_name'] ) ? (string) $status['folder_name'] : __( 'Carpeta de Drive', 'wp-song-study-blocks' );
        $output .= '<div><dt>' . esc_html__( 'Carpeta', 'wp-song-study-blocks' ) . '</dt><dd><a href="' . esc_url( (string) $status['folder_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $folder_label ) . '</a></dd></div>';
    }

    if ( ! empty( $missing ) ) {
        $output .= '<div><dt>' . esc_html__( 'Permisos pendientes', 'wp-song-study-blocks' ) . '</dt><dd>' . esc_html( implode( ' · ', $missing ) ) . '</dd></div>';
    }

    if ( '' !== $last_error ) {
        $output .= '<div><dt>' . esc_html__( 'Último error', 'wp-song-study-blocks' ) . '</dt><dd>' . esc_html( $last_error ) . '</dd></div>';
    }

    $output .= '</dl>';

    if ( '' !== $connect_url ) {
        $output .= '<p class="pd-membership-google-card__actions"><a class="wp-block-button__link wp-element-button' . ( $ready ? ' is-style-outline' : '' ) . '" href="' . esc_url( $connect_url ) . '">' . esc_html( $connected ? __( 'Reconectar', 'wp-song-study-blocks' ) : __( 'Conectar', 'wp-song-study-blocks' ) ) . '</a></p>';
    } elseif ( ! $allow_connect ) {
        $output .= '<p class="pd-membership-form__hint">' . esc_html__( 'Solo el usuario de la sesión activa puede iniciar o renovar su propia autorización OAuth.', 'wp-song-study-blocks' ) . '</p>';
    }

    $output .= '</article>';

    return $output;
}

/**
 * Renderiza una tarjeta de colaborador reutilizable.
 *
 * @param WP_User $user      Usuario a renderizar.
 * @param array   $settings  Opciones visuales.
 * @return string
 */
function wpssb_render_collaborator_card( $user, $settings = [] ) {
    if ( ! $user instanceof WP_User ) {
        return '';
    }

    $settings = wp_parse_args(
        $settings,
        [
            'show_avatar' => true,
            'show_bio'    => true,
            'show_link'   => true,
        ]
    );

    $avatar     = get_avatar( $user->ID, 96, '', $user->display_name, [ 'class' => 'pd-colaborador-avatar' ] );
    $bio        = get_user_meta( $user->ID, 'description', true );
    $tagline    = get_user_meta( $user->ID, 'pd_colaborador_tagline', true );
    $url        = $user->user_url ? esc_url( $user->user_url ) : '';
    $public_url = wpssb_get_collaborator_public_url( $user->ID );

    $output  = '<article class="pd-colaborador-card">';
    $output .= '<div class="pd-colaborador-card__header">';

    if ( ! empty( $settings['show_avatar'] ) && $avatar ) {
        $output .= '<div class="pd-colaborador-card__avatar">' . $avatar . '</div>';
    }

    $output .= '<h3 class="pd-colaborador-card__name"><a href="' . esc_url( $public_url ) . '">' . esc_html( $user->display_name ) . '</a></h3>';
    $output .= '</div>';

    if ( ! empty( $settings['show_bio'] ) ) {
        if ( $bio ) {
            $output .= '<p class="pd-colaborador-card__bio">' . esc_html( $bio ) . '</p>';
        } elseif ( $tagline ) {
            $output .= '<p class="pd-colaborador-card__bio">' . esc_html( $tagline ) . '</p>';
        }
    }

    if ( ! empty( $settings['show_link'] ) && $url ) {
        $output .= '<p class="pd-colaborador-card__link"><a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Sitio / portafolio', 'wp-song-study-blocks' ) . '</a></p>';
    }

    $output .= '</article>';

    return $output;
}

/**
 * Obtiene usuarios vinculados a un proyecto.
 *
 * @param int $post_id Proyecto actual.
 * @return WP_User[]
 */
function wpssb_get_project_collaborators( $post_id ) {
    $ids = wpssb_sanitize_id_list( get_post_meta( $post_id, 'pd_proyecto_colaboradores', true ) );

    if ( empty( $ids ) ) {
        return [];
    }

    return array_values(
        array_filter(
            array_map(
                static function ( $id ) {
                    return get_user_by( 'id', (int) $id );
                },
                $ids
            )
        )
    );
}

/**
 * Obtiene integrantes vinculados a un proyecto, incluyendo al autor.
 *
 * @param int $post_id Proyecto actual.
 * @return WP_User[]
 */
function wpssb_get_project_members( $post_id ) {
    $post_id = absint( $post_id );

    if ( $post_id <= 0 || WPSSB_PROJECT_POST_TYPE !== get_post_type( $post_id ) ) {
        return [];
    }

    $members = [];

    foreach ( wpssb_get_project_collaborators( $post_id ) as $user ) {
        if ( $user instanceof WP_User ) {
            $members[ (int) $user->ID ] = $user;
        }
    }

    $author = get_user_by( 'id', (int) get_post_field( 'post_author', $post_id ) );
    if ( $author instanceof WP_User ) {
        $members[ (int) $author->ID ] = $author;
    }

    return array_values( $members );
}

/**
 * Indica si un usuario pertenece explícitamente a un proyecto.
 *
 * @param int $post_id Proyecto actual.
 * @param int $user_id Usuario objetivo.
 * @return bool
 */
function wpssb_user_is_project_member( $post_id, $user_id ) {
    $post_id = absint( $post_id );
    $user_id = absint( $user_id );

    if ( $post_id <= 0 || $user_id <= 0 || WPSSB_PROJECT_POST_TYPE !== get_post_type( $post_id ) ) {
        return false;
    }

    if ( $user_id === (int) get_post_field( 'post_author', $post_id ) ) {
        return true;
    }

    $collaborator_ids = wp_list_pluck( wpssb_get_project_collaborators( $post_id ), 'ID' );

    return in_array( $user_id, array_map( 'intval', $collaborator_ids ), true );
}

/**
 * Indica si un proyecto pertenece al área de música.
 *
 * @param int $post_id Proyecto actual.
 * @return bool
 */
function wpssb_project_is_music_project( $post_id ) {
    $post_id = absint( $post_id );

    if ( $post_id <= 0 || WPSSB_PROJECT_POST_TYPE !== get_post_type( $post_id ) ) {
        return false;
    }

    if ( ! taxonomy_exists( WPSSB_PROJECT_AREA_TAX ) ) {
        return true;
    }

    $terms = get_the_terms( $post_id, WPSSB_PROJECT_AREA_TAX );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        // Compatibilidad con proyectos legacy todavía sin clasificar por área.
        return true;
    }

    foreach ( $terms as $term ) {
        if ( $term instanceof WP_Term && 'musica' === $term->slug ) {
            return true;
        }
    }

    return false;
}

/**
 * Devuelve los estados permitidos para proyectos privados del planificador.
 *
 * @return string[]
 */
function wpssb_get_rehearsal_project_post_statuses() {
    return [ 'publish', 'private', 'draft', 'pending', 'future' ];
}

/**
 * Indica si un usuario pertenece explícitamente a un proyecto.
 *
 * @param int $post_id Proyecto actual.
 * @param int $user_id Usuario objetivo.
 * @return bool
 */
function wpssb_user_is_project_rehearsal_member( $post_id, $user_id ) {
    $post_id = absint( $post_id );
    $user_id = absint( $user_id );

    if ( $post_id <= 0 || $user_id <= 0 || ! wpssb_project_is_music_project( $post_id ) ) {
        return false;
    }

    return wpssb_user_is_project_member( $post_id, $user_id );
}

/**
 * Devuelve los integrantes efectivos del módulo de ensayos.
 *
 * Incluye colaboradores explícitos y al autor del proyecto para evitar que
 * sus votos o asistencia se pierdan si no está repetido en el metacampo.
 *
 * @param int $post_id Proyecto actual.
 * @return WP_User[]
 */
function wpssb_get_project_rehearsal_members( $post_id ) {
    $post_id = absint( $post_id );
    if ( $post_id <= 0 || ! wpssb_project_is_music_project( $post_id ) ) {
        return [];
    }

    return wpssb_get_project_members( $post_id );
}

/**
 * Devuelve un mapa de usuarios válidos para el módulo de ensayos.
 *
 * Conserva únicamente integrantes vigentes del proyecto musical.
 *
 * @param int   $post_id      Proyecto actual.
 * @param array $seed_entries Entradas crudas con posible `user_id`.
 * @return array<int, string>
 */
function wpssb_get_project_rehearsal_member_name_map( $post_id, $seed_entries = [] ) {
    $post_id = absint( $post_id );
    $members = [];

    foreach ( wpssb_get_project_rehearsal_members( $post_id ) as $user ) {
        if ( $user instanceof WP_User ) {
            $members[ (int) $user->ID ] = sanitize_text_field( $user->display_name );
        }
    }

    if ( ! is_array( $seed_entries ) ) {
        return $members;
    }

    foreach ( $seed_entries as $entry ) {
        if ( ! is_array( $entry ) ) {
            continue;
        }

        $user_id = absint( $entry['user_id'] ?? 0 );
        if ( $user_id <= 0 || isset( $members[ $user_id ] ) || ! wpssb_user_is_project_rehearsal_member( $post_id, $user_id ) ) {
            continue;
        }

        $user = get_user_by( 'id', $user_id );
        if ( ! $user instanceof WP_User ) {
            continue;
        }

        $members[ $user_id ] = sanitize_text_field( $user->display_name );
    }

    return $members;
}

/**
 * Determina si un usuario puede administrar el módulo de ensayos de un proyecto.
 *
 * @param int      $post_id Proyecto actual.
 * @param int|null $user_id Usuario objetivo.
 * @return bool
 */
function wpssb_user_can_manage_project_rehearsals( $post_id, $user_id = null ) {
    $post_id = absint( $post_id );
    if ( $post_id <= 0 || ! wpssb_project_is_music_project( $post_id ) ) {
        return false;
    }

    if ( null === $user_id ) {
        $user_id = get_current_user_id();
    }

    $user_id = absint( $user_id );
    if ( $user_id <= 0 ) {
        return false;
    }

    return wpssb_user_is_project_rehearsal_member( $post_id, $user_id );
}

/**
 * Devuelve IDs con acceso operativo al frontend de ensayos de un proyecto.
 *
 * Incluye solamente integrantes vigentes del proyecto musical.
 *
 * @param int $post_id Proyecto actual.
 * @return int[]
 */
function wpssb_get_project_rehearsal_access_user_ids( $post_id ) {
    $post_id = absint( $post_id );
    if ( $post_id <= 0 || ! wpssb_project_is_music_project( $post_id ) ) {
        return [];
    }

    return array_values(
        array_unique(
            array_filter(
                array_map(
                    'absint',
                    wp_list_pluck( wpssb_get_project_rehearsal_members( $post_id ), 'ID' )
                )
            )
        )
    );
}

/**
 * Determina si un usuario puede usar el frontend de ensayos de un proyecto.
 *
 * @param int      $post_id Proyecto actual.
 * @param int|null $user_id Usuario objetivo.
 * @return bool
 */
function wpssb_user_can_access_project_rehearsals( $post_id, $user_id = null ) {
    $post_id = absint( $post_id );
    if ( $post_id <= 0 || ! wpssb_project_is_music_project( $post_id ) ) {
        return false;
    }

    if ( null === $user_id ) {
        $user_id = get_current_user_id();
    }

    $user_id = absint( $user_id );
    if ( $user_id <= 0 ) {
        return false;
    }

    return in_array( $user_id, wpssb_get_project_rehearsal_access_user_ids( $post_id ), true );
}

/**
 * Obtiene la data saneada del módulo de ensayos de un proyecto.
 *
 * @param int $post_id Proyecto actual.
 * @return array<string, mixed>
 */
function wpssb_get_project_rehearsal_meta( $post_id ) {
    $post_id = absint( $post_id );
    $stored  = get_post_meta( $post_id, 'pd_proyecto_ensayos', true );

    if ( ! is_array( $stored ) ) {
        $stored = [];
    }

    $stored['project_id'] = $post_id;
    return wpssb_sanitize_project_rehearsal_meta( $stored );
}

/**
 * Obtiene la data cruda del módulo de ensayos de un proyecto.
 *
 * Conserva entradas legacy para permitir rescates puntuales desde admin.
 *
 * @param int $post_id Proyecto actual.
 * @return array<string, mixed>
 */
function wpssb_get_project_rehearsal_meta_raw( $post_id ) {
    $post_id = absint( $post_id );
    $stored  = get_post_meta( $post_id, 'pd_proyecto_ensayos', true );

    if ( ! is_array( $stored ) ) {
        $stored = [];
    }

    return [
        'project_id'      => $post_id,
        'availability'    => isset( $stored['availability'] ) && is_array( $stored['availability'] ) ? array_values( $stored['availability'] ) : [],
        'sessions'        => isset( $stored['sessions'] ) && is_array( $stored['sessions'] ) ? $stored['sessions'] : [],
        'updated_at_gmt'  => sanitize_text_field( (string) ( $stored['updated_at_gmt'] ?? '' ) ),
        'updated_by'      => absint( $stored['updated_by'] ?? 0 ),
    ];
}

/**
 * Guarda la data saneada del módulo de ensayos de un proyecto.
 *
 * @param int   $post_id Proyecto actual.
 * @param array $data    Datos saneados.
 * @return array<string, mixed>
 */
function wpssb_update_project_rehearsal_meta( $post_id, $data ) {
    $post_id = absint( $post_id );
    $payload = is_array( $data ) ? $data : [];

    $payload['project_id']      = $post_id;
    $payload['updated_at_gmt']  = gmdate( 'c' );
    $payload['updated_by']      = get_current_user_id();
    $sanitized                  = wpssb_sanitize_project_rehearsal_meta( $payload );

    update_post_meta( $post_id, 'pd_proyecto_ensayos', $sanitized );

    return wpssb_get_project_rehearsal_meta( $post_id );
}

/**
 * Guarda la data cruda del módulo de ensayos de un proyecto.
 *
 * Se usa para rescatar disponibilidades legacy sin purgar otros registros aún
 * no migrados por un admin.
 *
 * @param int   $post_id Proyecto actual.
 * @param array $data    Datos crudos.
 * @return array<string, mixed>
 */
function wpssb_update_project_rehearsal_meta_raw( $post_id, $data ) {
    $post_id = absint( $post_id );
    $stored  = get_post_meta( $post_id, 'pd_proyecto_ensayos', true );
    $payload = is_array( $data ) ? $data : [];

    if ( ! is_array( $stored ) ) {
        $stored = [];
    }

    $payload = array_merge( $stored, $payload );

    $payload['project_id']     = $post_id;
    $payload['availability']   = isset( $payload['availability'] ) && is_array( $payload['availability'] ) ? array_values( $payload['availability'] ) : [];
    $payload['sessions']       = isset( $payload['sessions'] ) && is_array( $payload['sessions'] ) ? $payload['sessions'] : [];
    $payload['updated_at_gmt'] = gmdate( 'c' );
    $payload['updated_by']     = get_current_user_id();

    update_post_meta( $post_id, 'pd_proyecto_ensayos', $payload );

    return wpssb_get_project_rehearsal_meta_raw( $post_id );
}

/**
 * Normaliza una entrada cruda de disponibilidad para rescates administrativos.
 *
 * @param mixed $entry Entrada cruda.
 * @return array<string, mixed>
 */
function wpssb_normalize_project_rehearsal_rescue_entry( $entry ) {
    if ( ! is_array( $entry ) ) {
        return [];
    }

    $user_id = absint( $entry['user_id'] ?? 0 );
    if ( $user_id <= 0 ) {
        return [];
    }

    $user        = get_user_by( 'id', $user_id );
    $display_name = $user instanceof WP_User
        ? sanitize_text_field( $user->display_name )
        : sanitize_text_field( (string) ( $entry['nombre'] ?? '' ) );

    return [
        'user_id'           => $user_id,
        'nombre'            => $display_name,
        'notes'             => sanitize_textarea_field( (string) ( $entry['notes'] ?? '' ) ),
        'blocked_days'      => wpssb_sanitize_project_rehearsal_days_list( $entry['blocked_days'] ?? [] ),
        'slots'             => wpssb_sanitize_project_rehearsal_slots( $entry['slots'] ?? [] ),
        'unavailable_slots' => wpssb_sanitize_project_rehearsal_slots( $entry['unavailable_slots'] ?? [] ),
        'updated_at'        => sanitize_text_field( (string) ( $entry['updated_at'] ?? '' ) ),
    ];
}

/**
 * Devuelve disponibilidades crudas rescatables de un proyecto.
 *
 * @param int $post_id Proyecto actual.
 * @return array<int, array<string, mixed>>
 */
function wpssb_get_project_rehearsal_rescue_availability( $post_id ) {
    $post_id = absint( $post_id );
    if ( $post_id <= 0 || WPSSB_PROJECT_POST_TYPE !== get_post_type( $post_id ) ) {
        return [];
    }

    $meta    = wpssb_get_project_rehearsal_meta_raw( $post_id );
    $entries = [];

    foreach ( (array) $meta['availability'] as $entry ) {
        $normalized = wpssb_normalize_project_rehearsal_rescue_entry( $entry );
        if ( empty( $normalized ) ) {
            continue;
        }

        $user_id                         = (int) $normalized['user_id'];
        $normalized['is_project_member'] = wpssb_user_is_project_member( $post_id, $user_id );
        $entries[ $user_id ]             = $normalized;
    }

    uasort(
        $entries,
        static function ( $left, $right ) {
            return strnatcasecmp( (string) ( $left['nombre'] ?? '' ), (string) ( $right['nombre'] ?? '' ) );
        }
    );

    return array_values( $entries );
}

/**
 * Resume un proyecto para el gestor admin de rescate de horarios.
 *
 * @param int $post_id Proyecto actual.
 * @return array<string, mixed>
 */
function wpssb_get_project_rehearsal_rescue_project_summary( $post_id ) {
    $post_id = absint( $post_id );
    if ( $post_id <= 0 || WPSSB_PROJECT_POST_TYPE !== get_post_type( $post_id ) ) {
        return [];
    }

    $members = array_map(
        static function ( $user ) {
            return [
                'id'     => (int) $user->ID,
                'nombre' => sanitize_text_field( $user->display_name ),
            ];
        },
        wpssb_get_project_members( $post_id )
    );

    $rehearsal_enabled = wpssb_project_is_music_project( $post_id );
    $availability      = wpssb_get_project_rehearsal_rescue_availability( $post_id );

    return [
        'id'                    => $post_id,
        'titulo'                => sanitize_text_field( get_the_title( $post_id ) ),
        'status'                => sanitize_key( (string) get_post_status( $post_id ) ),
        'rehearsal_enabled'     => $rehearsal_enabled,
        'is_music_project'      => $rehearsal_enabled,
        'raw_availability_count'=> count( $availability ),
        'member_ids'            => array_values(
            array_map(
                'absint',
                wp_list_pluck( $members, 'id' )
            )
        ),
        'colaboradores'         => $members,
    ];
}

/**
 * Copia o mueve la disponibilidad de un usuario entre proyectos.
 *
 * Opera sobre meta crudo para no purgar otras entradas legacy ajenas al
 * rescate actual. El destino siempre reemplaza la disponibilidad existente del
 * mismo usuario.
 *
 * @param int    $source_project_id Proyecto origen.
 * @param int    $target_project_id Proyecto destino.
 * @param int    $user_id           Usuario objetivo.
 * @param string $mode              Acción: copy|move.
 * @return array<string, mixed>|WP_Error
 */
function wpssb_rescue_project_rehearsal_availability( $source_project_id, $target_project_id, $user_id, $mode = 'move' ) {
    $source_project_id = absint( $source_project_id );
    $target_project_id = absint( $target_project_id );
    $user_id           = absint( $user_id );
    $mode              = 'copy' === sanitize_key( $mode ) ? 'copy' : 'move';

    if (
        $source_project_id <= 0
        || $target_project_id <= 0
        || $user_id <= 0
        || WPSSB_PROJECT_POST_TYPE !== get_post_type( $source_project_id )
        || WPSSB_PROJECT_POST_TYPE !== get_post_type( $target_project_id )
    ) {
        return new WP_Error(
            'wpssb_rehearsal_rescue_invalid_project',
            __( 'Selecciona un proyecto origen, un proyecto destino y un integrante válidos.', 'wp-song-study-blocks' ),
            [ 'status' => 400 ]
        );
    }

    if ( $source_project_id === $target_project_id ) {
        return new WP_Error(
            'wpssb_rehearsal_rescue_same_project',
            __( 'El proyecto origen y el destino deben ser diferentes.', 'wp-song-study-blocks' ),
            [ 'status' => 400 ]
        );
    }

    if ( ! wpssb_user_is_project_rehearsal_member( $target_project_id, $user_id ) ) {
        return new WP_Error(
            'wpssb_rehearsal_rescue_invalid_target',
            __( 'El proyecto destino debe ser musical y el usuario debe pertenecer realmente a ese proyecto.', 'wp-song-study-blocks' ),
            [ 'status' => 400 ]
        );
    }

    $source_meta  = wpssb_get_project_rehearsal_meta_raw( $source_project_id );
    $target_meta  = wpssb_get_project_rehearsal_meta_raw( $target_project_id );
    $source_entry = [];

    foreach ( (array) $source_meta['availability'] as $entry ) {
        if ( ! is_array( $entry ) || $user_id !== absint( $entry['user_id'] ?? 0 ) ) {
            continue;
        }

        $source_entry = wpssb_normalize_project_rehearsal_rescue_entry( $entry );
    }

    if ( empty( $source_entry ) ) {
        return new WP_Error(
            'wpssb_rehearsal_rescue_missing_source',
            __( 'No existe una disponibilidad rescatable de ese usuario en el proyecto origen.', 'wp-song-study-blocks' ),
            [ 'status' => 404 ]
        );
    }

    $target_user = get_user_by( 'id', $user_id );
    $target_entry = [
        'user_id'           => $user_id,
        'nombre'            => $target_user instanceof WP_User ? sanitize_text_field( $target_user->display_name ) : (string) ( $source_entry['nombre'] ?? '' ),
        'notes'             => (string) ( $source_entry['notes'] ?? '' ),
        'blocked_days'      => $source_entry['blocked_days'] ?? [],
        'slots'             => $source_entry['slots'] ?? [],
        'unavailable_slots' => $source_entry['unavailable_slots'] ?? [],
        'updated_at'        => gmdate( 'c' ),
    ];

    $next_target_availability = [];
    foreach ( (array) $target_meta['availability'] as $entry ) {
        if ( is_array( $entry ) && $user_id === absint( $entry['user_id'] ?? 0 ) ) {
            continue;
        }

        $next_target_availability[] = $entry;
    }
    $next_target_availability[] = $target_entry;

    wpssb_update_project_rehearsal_meta_raw(
        $target_project_id,
        [
            'availability' => $next_target_availability,
            'sessions'     => $target_meta['sessions'] ?? [],
        ]
    );

    if ( 'move' === $mode ) {
        $next_source_availability = [];

        foreach ( (array) $source_meta['availability'] as $entry ) {
            if ( is_array( $entry ) && $user_id === absint( $entry['user_id'] ?? 0 ) ) {
                continue;
            }

            $next_source_availability[] = $entry;
        }

        wpssb_update_project_rehearsal_meta_raw(
            $source_project_id,
            [
                'availability' => $next_source_availability,
                'sessions'     => $source_meta['sessions'] ?? [],
            ]
        );
    }

    return [
        'mode'           => $mode,
        'entry'          => $target_entry,
        'source_project' => [
            'id'     => $source_project_id,
            'titulo' => sanitize_text_field( get_the_title( $source_project_id ) ),
        ],
        'target_project' => [
            'id'     => $target_project_id,
            'titulo' => sanitize_text_field( get_the_title( $target_project_id ) ),
        ],
        'message'        => sprintf(
            /* translators: 1: action label, 2: member name, 3: source project title, 4: target project title */
            __( 'Horario %1$s para %2$s: de "%3$s" a "%4$s".', 'wp-song-study-blocks' ),
            'copy' === $mode ? __( 'copiado', 'wp-song-study-blocks' ) : __( 'movido', 'wp-song-study-blocks' ),
            (string) ( $target_entry['nombre'] ?? '' ),
            sanitize_text_field( get_the_title( $source_project_id ) ),
            sanitize_text_field( get_the_title( $target_project_id ) )
        ),
    ];
}

/**
 * Genera recomendaciones de horario a partir de la disponibilidad semanal.
 *
 * @param array<int, array<string, mixed>> $availability Disponibilidad saneada.
 * @return array<int, array<string, mixed>>
 */
function wpssb_get_project_rehearsal_recommendations( $availability ) {
    if ( ! is_array( $availability ) || empty( $availability ) ) {
        return [];
    }

    $days               = wpssb_get_project_rehearsal_days();
    $day_indexes        = array_flip( $days );
    $recommendations    = [];
    $total_members      = count( $availability );
    $minimum_attendance = $total_members > 1 ? 2 : 1;

    foreach ( $days as $day ) {
        $boundaries = [];
        $day_slots  = [];

        foreach ( $availability as $member ) {
            if ( ! is_array( $member ) || empty( $member['user_id'] ) ) {
                continue;
            }

            $member_id   = absint( $member['user_id'] );
            $member_name = sanitize_text_field( (string) ( $member['nombre'] ?? '' ) );
            $member_unavailable = [];

            foreach ( (array) ( $member['slots'] ?? [] ) as $slot ) {
                if ( ! is_array( $slot ) || ( $slot['day'] ?? '' ) !== $day ) {
                    continue;
                }

                $start = wpssb_project_rehearsal_time_to_minutes( $slot['start'] ?? '' );
                $end   = wpssb_project_rehearsal_time_to_minutes( $slot['end'] ?? '' );

                if ( $start < 0 || $end <= $start ) {
                    continue;
                }

                $boundaries[] = $start;
                $boundaries[] = $end;
                $day_slots[]  = [
                    'user_id'   => $member_id,
                    'nombre'    => $member_name,
                    'start'     => $start,
                    'end'       => $end,
                ];
            }

            foreach ( (array) ( $member['unavailable_slots'] ?? [] ) as $slot ) {
                if ( ! is_array( $slot ) || ( $slot['day'] ?? '' ) !== $day ) {
                    continue;
                }

                $start = wpssb_project_rehearsal_time_to_minutes( $slot['start'] ?? '' );
                $end   = wpssb_project_rehearsal_time_to_minutes( $slot['end'] ?? '' );

                if ( $start < 0 || $end <= $start ) {
                    continue;
                }

                $boundaries[]          = $start;
                $boundaries[]          = $end;
                $member_unavailable[] = [
                    'start' => $start,
                    'end'   => $end,
                ];
            }

            if ( ! empty( $member_unavailable ) ) {
                $day_slots[] = [
                    'user_id'          => $member_id,
                    'nombre'           => $member_name,
                    'start'            => null,
                    'end'              => null,
                    'unavailable'      => $member_unavailable,
                    'is_restriction'   => true,
                ];
            }
        }

        $boundaries = array_values( array_unique( array_map( 'intval', $boundaries ) ) );
        sort( $boundaries );

        if ( count( $boundaries ) < 2 ) {
            continue;
        }

        $previous = null;

        for ( $index = 0; $index < count( $boundaries ) - 1; $index++ ) {
            $segment_start = (int) $boundaries[ $index ];
            $segment_end   = (int) $boundaries[ $index + 1 ];

            if ( $segment_end <= $segment_start ) {
                continue;
            }

            $members = [];

            foreach ( $day_slots as $slot ) {
                if ( ! empty( $slot['is_restriction'] ) ) {
                    continue;
                }

                if ( $slot['start'] <= $segment_start && $slot['end'] >= $segment_end ) {
                    $blocked = false;
                    foreach ( $day_slots as $restriction ) {
                        if (
                            empty( $restriction['is_restriction'] )
                            || (int) $restriction['user_id'] !== (int) $slot['user_id']
                            || empty( $restriction['unavailable'] )
                        ) {
                            continue;
                        }

                        foreach ( (array) $restriction['unavailable'] as $range ) {
                            $range_start = isset( $range['start'] ) ? (int) $range['start'] : -1;
                            $range_end   = isset( $range['end'] ) ? (int) $range['end'] : -1;

                            if ( $range_start < $segment_end && $range_end > $segment_start ) {
                                $blocked = true;
                                break 2;
                            }
                        }
                    }

                    if ( $blocked ) {
                        continue;
                    }

                    $members[ $slot['user_id'] ] = $slot['nombre'];
                }
            }

            if ( count( $members ) < $minimum_attendance ) {
                $previous = null;
                continue;
            }

            ksort( $members );
            $member_ids = array_map( 'intval', array_keys( $members ) );

            if (
                is_array( $previous )
                && $previous['day'] === $day
                && $previous['member_ids'] === $member_ids
                && (int) $previous['end_minutes'] === $segment_start
            ) {
                $previous['end_minutes'] = $segment_end;
                $previous['duration_minutes'] = $segment_end - (int) $previous['start_minutes'];
                $recommendations[ count( $recommendations ) - 1 ] = $previous;
                continue;
            }

            $previous = [
                'day'              => $day,
                'day_index'        => isset( $day_indexes[ $day ] ) ? (int) $day_indexes[ $day ] : 0,
                'start_minutes'    => $segment_start,
                'end_minutes'      => $segment_end,
                'duration_minutes' => $segment_end - $segment_start,
                'member_count'     => count( $members ),
                'member_ids'       => $member_ids,
                'member_names'     => array_values( $members ),
            ];

            $recommendations[] = $previous;
        }
    }

    usort(
        $recommendations,
        static function ( $left, $right ) {
            if ( $left['day_index'] !== $right['day_index'] ) {
                return $left['day_index'] <=> $right['day_index'];
            }

            if ( $left['start_minutes'] !== $right['start_minutes'] ) {
                return $left['start_minutes'] <=> $right['start_minutes'];
            }

            if ( $left['end_minutes'] !== $right['end_minutes'] ) {
                return $left['end_minutes'] <=> $right['end_minutes'];
            }

            if ( $left['member_count'] !== $right['member_count'] ) {
                return $right['member_count'] <=> $left['member_count'];
            }

            return $right['duration_minutes'] <=> $left['duration_minutes'];
        }
    );

    return array_slice(
        array_map(
            static function ( $item ) {
                $start_hours   = floor( $item['start_minutes'] / 60 );
                $start_minutes = $item['start_minutes'] % 60;
                $end_hours     = floor( $item['end_minutes'] / 60 );
                $end_minutes   = $item['end_minutes'] % 60;

                return [
                    'day'              => $item['day'],
                    'start'            => sprintf( '%02d:%02d', $start_hours, $start_minutes ),
                    'end'              => sprintf( '%02d:%02d', $end_hours, $end_minutes ),
                    'duration_minutes' => (int) $item['duration_minutes'],
                    'member_count'     => (int) $item['member_count'],
                    'member_ids'       => array_map( 'intval', $item['member_ids'] ),
                    'member_names'     => array_values( $item['member_names'] ),
                ];
            },
            $recommendations
        ),
        0,
        8
    );
}

/**
 * Construye un resumen rápido para la herramienta de ensayos del proyecto.
 *
 * @param array<int, array<string, mixed>> $availability  Disponibilidad.
 * @param array<int, array<string, mixed>> $sessions      Bitácora de ensayos.
 * @param array<int, array<string, mixed>> $collaborators Integrantes del proyecto.
 * @return array<string, mixed>
 */
function wpssb_get_project_rehearsal_summary( $availability, $sessions, $collaborators = [] ) {
    $members_with_availability = 0;
    $member_ids               = [];

    foreach ( (array) $collaborators as $collaborator ) {
        if ( is_array( $collaborator ) && ! empty( $collaborator['id'] ) ) {
            $member_ids[] = absint( $collaborator['id'] );
        }
    }

    foreach ( (array) $availability as $item ) {
        if ( is_array( $item ) && ! empty( $item['user_id'] ) ) {
            $member_ids[] = absint( $item['user_id'] );
        }

        if ( ! empty( $item['slots'] ) || ! empty( $item['blocked_days'] ) || ! empty( $item['unavailable_slots'] ) ) {
            $members_with_availability++;
        }
    }

    $member_ids = array_values( array_unique( array_filter( array_map( 'absint', $member_ids ) ) ) );

    $completed_sessions = 0;
    $confirmed_sessions = 0;
    $proposal_sessions  = 0;
    $next_session       = null;
    $now_timestamp      = current_time( 'timestamp' );

    foreach ( (array) $sessions as $session ) {
        if ( ! is_array( $session ) ) {
            continue;
        }

        if ( 'completed' === ( $session['status'] ?? '' ) ) {
            $completed_sessions++;
        }

        if ( 'confirmed' === ( $session['status'] ?? '' ) && ! wpssb_frontend_rehearsal_session_is_logbook_available( $session, $now_timestamp ) ) {
            $confirmed_sessions++;
        }

        if ( in_array( $session['status'] ?? '', [ 'proposed', 'voting' ], true ) ) {
            $proposal_sessions++;
        }

        if ( 'confirmed' !== ( $session['status'] ?? '' ) ) {
            continue;
        }

        $session_timestamp = strtotime( (string) $session['scheduled_for'] . ' ' . ( ! empty( $session['start_time'] ) ? (string) $session['start_time'] : '00:00' ) );
        if ( false === $session_timestamp || $session_timestamp < $now_timestamp ) {
            continue;
        }

        if ( null === $next_session || $session_timestamp < $next_session ) {
            $next_session = $session_timestamp;
        }
    }

    return [
        'total_members'              => count( $member_ids ),
        'members_with_availability'  => $members_with_availability,
        'sessions_total'             => count( (array) $sessions ),
        'proposal_sessions'          => $proposal_sessions,
        'confirmed_sessions'         => $confirmed_sessions,
        'completed_sessions'         => $completed_sessions,
        'next_session_iso'           => $next_session ? gmdate( 'c', $next_session ) : '',
    ];
}

/**
 * Devuelve el estado operativo de Google Calendar para el usuario actual.
 *
 * @return array<string, mixed>
 */
function wpssb_google_calendar_request( $user_id, $method, $url, array $args = [] ) {
    $user_id = absint( $user_id );
    if ( $user_id <= 0 || ! function_exists( 'wpss_get_google_calendar_access_token' ) ) {
        return new WP_Error( 'wpss_calendar_unavailable', __( 'La integración con Google Calendar no está disponible.', 'wp-song-study-blocks' ) );
    }

    $token = wpss_get_google_calendar_access_token( $user_id );
    if ( is_wp_error( $token ) ) {
        return $token;
    }

    $headers                  = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : [];
    $headers['Authorization'] = 'Bearer ' . $token;

    $request_args            = $args;
    $request_args['method']  = strtoupper( $method );
    $request_args['headers'] = $headers;
    if ( ! isset( $request_args['timeout'] ) ) {
        $request_args['timeout'] = 30;
    }

    $response = wp_remote_request( $url, $request_args );
    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code( $response );
    if ( $code >= 400 ) {
        $error_body = wp_remote_retrieve_body( $response );
        $decoded    = json_decode( $error_body, true );
        $message    = sprintf( 'Google Calendar HTTP %d', $code );

        if ( is_array( $decoded ) ) {
            if ( ! empty( $decoded['error']['message'] ) ) {
                $message = sprintf( 'Google Calendar HTTP %d: %s', $code, sanitize_text_field( (string) $decoded['error']['message'] ) );
            } elseif ( ! empty( $decoded['message'] ) ) {
                $message = sprintf( 'Google Calendar HTTP %d: %s', $code, sanitize_text_field( (string) $decoded['message'] ) );
            }
        }

        return new WP_Error(
            'wpss_calendar_http_error',
            $message,
            [
                'body'        => $error_body,
                'status_code' => $code,
            ]
        );
    }

    $body    = wp_remote_retrieve_body( $response );
    $decoded = json_decode( $body, true );

    return [
        'code'    => $code,
        'headers' => wp_remote_retrieve_headers( $response ),
        'body'    => $body,
        'json'    => is_array( $decoded ) ? $decoded : null,
    ];
}

/**
 * Devuelve el estado operativo de Google Calendar para el usuario actual.
 *
 * @return array<string, mixed>
 */
function wpssb_get_project_rehearsal_google_calendar_status() {
    $user_id = get_current_user_id();
    $default = [
        'available'             => false,
        'configured'            => false,
        'connected'             => false,
        'ready'                 => false,
        'has_access_token'      => false,
        'has_refresh_token'     => false,
        'account_email'         => '',
        'connect_url'           => '',
        'oauth_settings_url'    => '',
        'oauth_settings_label'  => '',
        'profile_url'           => '',
        'redirect_uri'          => '',
        'authorized_origin'     => '',
        'credentials_source'    => '',
        'client_id_hint'        => '',
        'required_scopes'       => [],
        'required_scope_labels' => [],
        'granted_scopes'        => [],
        'granted_scope_labels'  => [],
        'missing_scopes'        => [],
        'missing_scope_labels'  => [],
        'last_error'            => '',
        'reconnect_reason'      => '',
        'calendar_probe_ok'     => false,
        'calendar_probe_message'=> '',
        'status_message'        => __( 'La integración de Google Calendar no está disponible en este momento.', 'wp-song-study-blocks' ),
    ];

    if (
        $user_id <= 0
        || ! function_exists( 'wpss_get_google_calendar_oauth_scopes' )
        || ! function_exists( 'wpss_get_google_calendar_status_payload' )
        || ! function_exists( 'wpss_get_google_calendar_connect_url' )
        || ! function_exists( 'wpss_get_google_drive_missing_scopes' )
        || ! function_exists( 'wpss_get_google_drive_scope_labels' )
        || ! function_exists( 'wpss_normalize_google_drive_scopes' )
        || ! function_exists( 'wpss_get_google_drive_oauth_credentials' )
    ) {
        return $default;
    }

    $required_scopes = array_values( wpss_get_google_calendar_oauth_scopes() );
    $status          = wpss_get_google_calendar_status_payload( $user_id );
    $credentials     = wpss_get_google_drive_oauth_credentials( $user_id );
    $granted_scopes  = wpss_normalize_google_drive_scopes( $status['granted_scopes'] ?? [] );
    $missing_scopes  = wpss_get_google_drive_missing_scopes( $granted_scopes, $required_scopes );
    $scope_labels    = wpss_get_google_drive_scope_labels();
    $configured      = ! empty( $status['configured'] );
    $connected       = ! empty( $status['connected'] );
    $has_access      = ! empty( $status['has_access_token'] );
    $has_refresh     = ! empty( $status['has_refresh_token'] );
    $calendar_probe_ok = false;
    $calendar_probe_message = '';
    $return_url      = admin_url( 'admin.php?page=wpss-ensayos-proyecto' );
    $profile_url     = admin_url( 'profile.php' );
    $oauth_settings_url = 'user' === ( $status['credentials_source'] ?? '' )
        ? $profile_url
        : admin_url( 'admin.php?page=wpss-drive-global-settings' );
    $oauth_settings_label = 'user' === ( $status['credentials_source'] ?? '' )
        ? __( 'Abrir perfil OAuth', 'wp-song-study-blocks' )
        : __( 'Abrir credenciales globales', 'wp-song-study-blocks' );

    if (
        $configured
        && $connected
        && $has_refresh
        && ! empty( $missing_scopes )
    ) {
        $probe = wpssb_google_calendar_request(
            $user_id,
            'GET',
            add_query_arg(
                [
                    'maxResults'   => 1,
                    'singleEvents' => 'true',
                    'timeMin'      => gmdate( 'c', time() - HOUR_IN_SECONDS ),
                    'timeMax'      => gmdate( 'c', time() + DAY_IN_SECONDS ),
                    'fields'       => 'items(id,status),summary,timeZone',
                ],
                'https://www.googleapis.com/calendar/v3/calendars/primary/events'
            )
        );

        if ( is_wp_error( $probe ) ) {
            $calendar_probe_message = sanitize_text_field( $probe->get_error_message() );
        } else {
            $calendar_probe_ok      = true;
            $calendar_probe_message = __( 'La cuenta respondió correctamente al endpoint principal de Google Calendar.', 'wp-song-study-blocks' );
            $granted_scopes         = $required_scopes;
            $missing_scopes         = [];
            $has_access             = true;
        }
    }

    $ready = $configured && $connected && $has_refresh && empty( $missing_scopes );

    if ( $ready ) {
        $message = __( 'La cuenta conectada ya puede crear y actualizar eventos de ensayo en Google Calendar.', 'wp-song-study-blocks' );
        $reason  = '';
    } elseif ( ! $configured ) {
        $message = __( 'Faltan Client ID y Client Secret de Google en la configuración activa.', 'wp-song-study-blocks' );
        $reason  = 'missing_credentials';
    } elseif ( ! $connected ) {
        $message = __( 'Conecta tu cuenta de Google para poder agendar ensayos en Calendar.', 'wp-song-study-blocks' );
        $reason  = 'not_connected';
    } elseif ( ! $has_refresh ) {
        $message = __( 'La cuenta conectada no tiene refresh token. Reconecta Google para habilitar la sincronización estable.', 'wp-song-study-blocks' );
        $reason  = 'missing_refresh_token';
    } elseif ( 'user' === ( $status['credentials_source'] ?? '' ) ) {
        $message = __( 'La conexión activa usa credenciales guardadas en el perfil de usuario. Calendar ya no comparte token con Drive: debes reconectar desde este botón y revisar ese mismo cliente OAuth en el perfil del usuario.', 'wp-song-study-blocks' );
        $reason  = 'missing_scopes_user_credentials';
    } else {
        $message = __( 'La cuenta conectada necesita permisos adicionales de Calendar. Reconecta Google Calendar desde este módulo; el flujo de Mi Drive ya no administra estos scopes.', 'wp-song-study-blocks' );
        $reason  = 'missing_scopes';
    }

    return [
        'available'             => true,
        'configured'            => $configured,
        'connected'             => $connected,
        'ready'                 => $ready,
        'has_access_token'      => $has_access,
        'has_refresh_token'     => $has_refresh,
        'account_email'         => sanitize_email( (string) ( $status['account_email'] ?? '' ) ),
        'connect_url'           => esc_url_raw( wpss_get_google_calendar_connect_url( $user_id, $return_url, $required_scopes, true ) ),
        'oauth_settings_url'    => esc_url_raw( $oauth_settings_url ),
        'oauth_settings_label'  => sanitize_text_field( $oauth_settings_label ),
        'profile_url'           => esc_url_raw( $profile_url ),
        'redirect_uri'          => esc_url_raw( (string) ( $status['redirect_uri'] ?? '' ) ),
        'authorized_origin'     => esc_url_raw( (string) ( $status['authorized_origin'] ?? '' ) ),
        'credentials_source'    => sanitize_text_field( (string) ( $status['credentials_source'] ?? '' ) ),
        'client_id_hint'        => function_exists( 'wpss_google_drive_debug_hint' ) ? sanitize_text_field( wpss_google_drive_debug_hint( (string) ( $credentials['client_id'] ?? '' ) ) ) : '',
        'required_scopes'       => array_values( $required_scopes ),
        'required_scope_labels' => array_values(
            array_map(
                static function ( $scope ) use ( $scope_labels ) {
                    return isset( $scope_labels[ $scope ] ) ? sanitize_text_field( (string) $scope_labels[ $scope ] ) : sanitize_text_field( (string) $scope );
                },
                $required_scopes
            )
        ),
        'granted_scopes'        => array_values( $granted_scopes ),
        'granted_scope_labels'  => array_values(
            array_map(
                static function ( $scope ) use ( $scope_labels ) {
                    return isset( $scope_labels[ $scope ] ) ? sanitize_text_field( (string) $scope_labels[ $scope ] ) : sanitize_text_field( (string) $scope );
                },
                $granted_scopes
            )
        ),
        'missing_scopes'        => array_values( $missing_scopes ),
        'missing_scope_labels'  => array_values(
            array_map(
                static function ( $scope ) use ( $scope_labels ) {
                    return isset( $scope_labels[ $scope ] ) ? sanitize_text_field( (string) $scope_labels[ $scope ] ) : sanitize_text_field( (string) $scope );
                },
                $missing_scopes
            )
        ),
        'last_error'            => sanitize_text_field( (string) ( $status['last_error']['message'] ?? '' ) ),
        'reconnect_reason'      => $reason,
        'calendar_probe_ok'     => $calendar_probe_ok,
        'calendar_probe_message'=> $calendar_probe_message,
        'status_message'        => $message,
    ];
}

/**
 * Construye la información base de fecha/hora para Google Calendar.
 *
 * @param array $session Sesión saneada.
 * @return array<string, DateTimeImmutable>|null
 */
function wpssb_get_project_rehearsal_event_datetimes( $session ) {
    if ( ! is_array( $session ) || empty( $session['scheduled_for'] ) ) {
        return null;
    }

    $timezone = wp_timezone();
    $date     = sanitize_text_field( (string) $session['scheduled_for'] );
    $start    = ! empty( $session['start_time'] ) ? sanitize_text_field( (string) $session['start_time'] ) : '19:00';
    $end      = ! empty( $session['end_time'] ) ? sanitize_text_field( (string) $session['end_time'] ) : '';

    try {
        $start_dt = new DateTimeImmutable( $date . ' ' . $start . ':00', $timezone );
    } catch ( Exception $exception ) {
        return null;
    }

    if ( '' !== $end ) {
        try {
            $end_dt = new DateTimeImmutable( $date . ' ' . $end . ':00', $timezone );
        } catch ( Exception $exception ) {
            $end_dt = $start_dt->modify( '+2 hours' );
        }
    } else {
        $end_dt = $start_dt->modify( '+2 hours' );
    }

    if ( $end_dt <= $start_dt ) {
        $end_dt = $start_dt->modify( '+2 hours' );
    }

    return [
        'start' => $start_dt,
        'end'   => $end_dt,
    ];
}

/**
 * Construye el payload de evento para Google Calendar.
 *
 * @param int   $post_id Proyecto actual.
 * @param array $session Sesión saneada.
 * @return array<string, mixed>|null
 */
function wpssb_build_project_rehearsal_google_event( $post_id, $session ) {
    $post_id = absint( $post_id );
    if ( $post_id <= 0 || ! is_array( $session ) ) {
        return null;
    }

    $datetimes = wpssb_get_project_rehearsal_event_datetimes( $session );
    if ( ! is_array( $datetimes ) || empty( $datetimes['start'] ) || empty( $datetimes['end'] ) ) {
        return null;
    }

    $title = sprintf(
        /* translators: 1: project title, 2: rehearsal focus */
        __( 'Ensayo confirmado %1$s · %2$s', 'wp-song-study-blocks' ),
        get_the_title( $post_id ),
        ! empty( $session['focus'] ) ? (string) $session['focus'] : __( 'Sesión general', 'wp-song-study-blocks' )
    );

    $details = [
        __( 'Ensayo confirmado por el grupo.', 'wp-song-study-blocks' ),
    ];
    if ( ! empty( $session['notes'] ) ) {
        $details[] = (string) $session['notes'];
    }

    if ( ! empty( $session['reviewed_items'] ) && is_array( $session['reviewed_items'] ) ) {
        $details[] = __( 'Temas a trabajar:', 'wp-song-study-blocks' ) . "\n- " . implode( "\n- ", array_map( 'sanitize_text_field', $session['reviewed_items'] ) );
    }

    $vote_map = [];
    foreach ( (array) ( $session['votes'] ?? [] ) as $vote_entry ) {
        if ( ! is_array( $vote_entry ) || empty( $vote_entry['user_id'] ) ) {
            continue;
        }

        $vote_map[ absint( $vote_entry['user_id'] ) ] = sanitize_key( (string) ( $vote_entry['vote'] ?? 'pending' ) );
    }

    $attendees        = [];
    $forced_confirmed = wpssb_project_rehearsal_is_forced_confirmed( $session );
    $session_status   = sanitize_key( (string) ( $session['status'] ?? '' ) );
    foreach ( wpssb_get_project_rehearsal_members( $post_id ) as $user ) {
        if ( ! $user instanceof WP_User || empty( $user->user_email ) ) {
            continue;
        }

        $vote = isset( $vote_map[ (int) $user->ID ] ) ? $vote_map[ (int) $user->ID ] : 'pending';

        if ( $forced_confirmed && ! in_array( $vote, [ 'yes', 'maybe' ], true ) ) {
            continue;
        }

        if ( in_array( $session_status, [ 'confirmed', 'completed' ], true ) && 'no' === $vote ) {
            continue;
        }

        if ( 'yes' === $vote ) {
            $response_status = 'accepted';
        } elseif ( 'maybe' === $vote ) {
            $response_status = 'tentative';
        } elseif ( 'no' === $vote ) {
            $response_status = 'declined';
        } else {
            $response_status = 'needsAction';
        }

        $attendees[] = [
            'email'          => sanitize_email( $user->user_email ),
            'displayName'    => sanitize_text_field( $user->display_name ),
            'responseStatus' => $response_status,
        ];
    }

    return [
        'summary'     => $title,
        'description' => implode( "\n\n", array_filter( $details ) ),
        'location'    => sanitize_text_field( (string) ( $session['location'] ?? '' ) ),
        'start'       => [
            'dateTime' => $datetimes['start']->format( DATE_ATOM ),
            'timeZone' => wp_timezone_string() ?: 'UTC',
        ],
        'end'         => [
            'dateTime' => $datetimes['end']->format( DATE_ATOM ),
            'timeZone' => wp_timezone_string() ?: 'UTC',
        ],
        'attendees'   => array_values( array_filter( $attendees ) ),
    ];
}

/**
 * Devuelve el correo remitente por defecto para notificaciones de ensayos.
 *
 * @return string
 */
function wpssb_get_default_rehearsal_notification_sender_email() {
    $admin_email = sanitize_email( (string) get_option( 'admin_email' ) );

    if ( '' !== $admin_email && is_email( $admin_email ) ) {
        return $admin_email;
    }

    $host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
    $host = is_string( $host ) ? strtolower( preg_replace( '/^www\./', '', $host ) ) : '';

    if ( '' === $host || false === strpos( $host, '.' ) ) {
        return 'wordpress@localhost';
    }

    return 'notificaciones@' . $host;
}

/**
 * Devuelve la configuración por defecto de notificaciones de ensayos.
 *
 * @return array<string, mixed>
 */
function wpssb_get_default_rehearsal_notification_settings() {
    $site_name = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

    return [
        'enabled'    => 1,
        'from_name'  => trim( $site_name . ' · ' . __( 'Planificador de ensayos', 'wp-song-study-blocks' ) ),
        'from_email' => wpssb_get_default_rehearsal_notification_sender_email(),
        'reply_to'   => sanitize_email( (string) get_option( 'admin_email' ) ),
    ];
}

/**
 * Sanitiza la configuración de correo para notificaciones de ensayos.
 *
 * @param mixed $value Valor crudo.
 * @return array<string, mixed>
 */
function wpssb_sanitize_rehearsal_notification_settings( $value ) {
    $defaults = wpssb_get_default_rehearsal_notification_settings();
    $value    = is_array( $value ) ? $value : [];

    $from_name  = sanitize_text_field( (string) ( $value['from_name'] ?? $defaults['from_name'] ) );
    $from_email = sanitize_email( (string) ( $value['from_email'] ?? $defaults['from_email'] ) );
    $reply_to   = sanitize_email( (string) ( $value['reply_to'] ?? $defaults['reply_to'] ) );

    if ( '' === $from_name ) {
        $from_name = $defaults['from_name'];
    }

    if ( '' === $from_email || ! is_email( $from_email ) ) {
        $from_email = $defaults['from_email'];
    }

    if ( '' !== $reply_to && ! is_email( $reply_to ) ) {
        $reply_to = '';
    }

    return [
        'enabled'    => ! empty( $value['enabled'] ) ? 1 : 0,
        'from_name'  => $from_name,
        'from_email' => $from_email,
        'reply_to'   => $reply_to,
    ];
}

/**
 * Devuelve la configuración efectiva de notificaciones de ensayos.
 *
 * @return array<string, mixed>
 */
function wpssb_get_rehearsal_notification_settings() {
    $settings = get_option( 'wpss_rehearsal_notification_settings', [] );
    $settings = wpssb_sanitize_rehearsal_notification_settings( $settings );

    /**
     * Filtra la configuración de correos del planificador.
     *
     * @param array<string, mixed> $settings Configuración saneada.
     */
    return apply_filters( 'wpssb_rehearsal_notification_settings', $settings );
}

/**
 * Devuelve los correos destino para un proyecto musical.
 *
 * @param int   $post_id          Proyecto actual.
 * @param int[] $exclude_user_ids Usuarios a excluir.
 * @return string[]
 */
function wpssb_get_project_rehearsal_notification_recipients( $post_id, $exclude_user_ids = [] ) {
    $post_id          = absint( $post_id );
    $exclude_user_ids = array_values( array_filter( array_map( 'absint', is_array( $exclude_user_ids ) ? $exclude_user_ids : [] ) ) );
    $recipients       = [];

    if ( $post_id <= 0 ) {
        return [];
    }

    foreach ( wpssb_get_project_rehearsal_members( $post_id ) as $member ) {
        if ( ! $member instanceof WP_User || empty( $member->user_email ) ) {
            continue;
        }

        if ( in_array( (int) $member->ID, $exclude_user_ids, true ) ) {
            continue;
        }

        $recipients[] = sanitize_email( $member->user_email );
    }

    return array_values( array_unique( array_filter( $recipients ) ) );
}

/**
 * Envía una notificación por correo ligada al planificador de ensayos.
 *
 * @param int                  $post_id       Proyecto actual.
 * @param string               $subject       Asunto.
 * @param array<int, string>   $message_lines Líneas del mensaje.
 * @param array<string, mixed> $args          Argumentos extra.
 * @return void
 */
function wpssb_send_project_rehearsal_notification( $post_id, $subject, $message_lines, $args = [] ) {
    $post_id        = absint( $post_id );
    $subject        = sanitize_text_field( (string) $subject );
    $message_lines  = is_array( $message_lines ) ? $message_lines : [];
    $args           = is_array( $args ) ? $args : [];
    $settings       = wpssb_get_rehearsal_notification_settings();
    $exclude_user_ids = isset( $args['exclude_user_ids'] ) && is_array( $args['exclude_user_ids'] )
        ? $args['exclude_user_ids']
        : [];

    if ( $post_id <= 0 || '' === $subject || empty( $settings['enabled'] ) || ! function_exists( 'wp_mail' ) ) {
        return;
    }

    $recipients = isset( $args['recipients'] ) && is_array( $args['recipients'] )
        ? array_values( array_unique( array_filter( array_map( 'sanitize_email', $args['recipients'] ) ) ) )
        : wpssb_get_project_rehearsal_notification_recipients( $post_id, $exclude_user_ids );

    if ( empty( $recipients ) ) {
        return;
    }

    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'X-WPSSB-Notification: rehearsals',
    ];

    $from_name  = sanitize_text_field( (string) ( $settings['from_name'] ?? '' ) );
    $from_email = sanitize_email( (string) ( $settings['from_email'] ?? '' ) );
    $reply_to   = sanitize_email( (string) ( $settings['reply_to'] ?? '' ) );

    if ( '' !== $from_name && '' !== $from_email ) {
        $headers[] = sprintf( 'From: %1$s <%2$s>', str_replace( [ "\r", "\n" ], '', $from_name ), $from_email );
    }

    if ( '' !== $reply_to ) {
        $headers[] = sprintf( 'Reply-To: %s', $reply_to );
    }

    $message = implode(
        "\n\n",
        array_values(
            array_filter(
                array_map(
                    static function ( $line ) {
                        return is_string( $line ) ? trim( $line ) : '';
                    },
                    $message_lines
                )
            )
        )
    );

    if ( '' === $message ) {
        return;
    }

    foreach ( $recipients as $recipient ) {
        wp_mail( $recipient, $subject, $message, $headers );
    }
}

/**
 * Devuelve el nombre visible del actor de una acción de ensayo.
 *
 * @param int    $actor_id Usuario actor.
 * @param string $fallback Respaldo.
 * @return string
 */
function wpssb_get_project_rehearsal_notification_actor_name( $actor_id, $fallback = '' ) {
    $actor_id = absint( $actor_id );
    $fallback = sanitize_text_field( (string) $fallback );
    $actor    = $actor_id > 0 ? get_user_by( 'id', $actor_id ) : null;

    if ( $actor instanceof WP_User ) {
        return sanitize_text_field( $actor->display_name );
    }

    return $fallback;
}

/**
 * Devuelve el correo saneado de un usuario para notificaciones individuales.
 *
 * @param int $user_id Usuario objetivo.
 * @return string
 */
function wpssb_get_project_rehearsal_notification_user_email( $user_id ) {
    $user_id = absint( $user_id );
    $user    = $user_id > 0 ? get_user_by( 'id', $user_id ) : null;

    if ( $user instanceof WP_User && ! empty( $user->user_email ) ) {
        return sanitize_email( $user->user_email );
    }

    return '';
}

/**
 * Devuelve el objetivo legible de una sesión para correos.
 *
 * @param array<string, mixed> $session Sesión actual.
 * @return string
 */
function wpssb_get_project_rehearsal_notification_focus( $session ) {
    return ! empty( $session['focus'] )
        ? sanitize_text_field( (string) $session['focus'] )
        : __( 'Sesión general', 'wp-song-study-blocks' );
}

/**
 * Obtiene una sesión saneada desde el meta actual del proyecto.
 *
 * @param int    $post_id    Proyecto actual.
 * @param string $session_id Identificador de sesión.
 * @return array<string, mixed>
 */
function wpssb_get_project_rehearsal_session_by_id( $post_id, $session_id ) {
    $post_id    = absint( $post_id );
    $session_id = sanitize_key( (string) $session_id );

    if ( $post_id <= 0 || '' === $session_id ) {
        return [];
    }

    $meta     = wpssb_get_project_rehearsal_meta( $post_id );
    $sessions = wpssb_sanitize_project_rehearsal_sessions( $meta['sessions'] ?? [], $post_id );

    foreach ( $sessions as $session ) {
        if ( is_array( $session ) && $session_id === sanitize_key( (string) ( $session['id'] ?? '' ) ) ) {
            return $session;
        }
    }

    return [];
}

/**
 * Notifica cuando se crea una propuesta de ensayo.
 *
 * @param int                  $post_id  Proyecto actual.
 * @param array<string, mixed> $session  Sesión creada.
 * @param int                  $actor_id Usuario actor.
 * @return void
 */
function wpssb_notify_project_rehearsal_proposal_created( $post_id, $session, $actor_id ) {
    if ( ! is_array( $session ) ) {
        return;
    }

    $actor_name   = wpssb_get_project_rehearsal_notification_actor_name( $actor_id );
    $project_name = get_the_title( $post_id );
    $focus        = wpssb_get_project_rehearsal_notification_focus( $session );
    $schedule     = wpssb_format_project_rehearsal_schedule( $session );
    $location     = sanitize_text_field( (string) ( $session['location'] ?? '' ) );
    $notes        = sanitize_textarea_field( (string) ( $session['notes'] ?? '' ) );

    $message_lines = [
        sprintf(
            __( '%1$s creó una nueva propuesta de ensayo para "%2$s".', 'wp-song-study-blocks' ),
            '' !== $actor_name ? $actor_name : __( 'Un integrante', 'wp-song-study-blocks' ),
            $focus
        ),
    ];

    if ( '' !== $schedule ) {
        $message_lines[] = sprintf( __( 'Fecha y hora: %s', 'wp-song-study-blocks' ), $schedule );
    }

    if ( '' !== $location ) {
        $message_lines[] = sprintf( __( 'Lugar: %s', 'wp-song-study-blocks' ), $location );
    }

    if ( '' !== $notes ) {
        $message_lines[] = sprintf( __( 'Notas: %s', 'wp-song-study-blocks' ), $notes );
    }

    $message_lines[] = sprintf(
        __( 'Revisa y vota la propuesta aquí: %s', 'wp-song-study-blocks' ),
        wpssb_get_frontend_rehearsal_calendar_url( $post_id, 'proposals' )
    );

    wpssb_send_project_rehearsal_notification(
        $post_id,
        sprintf(
            __( '[%1$s] %2$s creó una propuesta de ensayo', 'wp-song-study-blocks' ),
            $project_name,
            '' !== $actor_name ? $actor_name : __( 'Un integrante', 'wp-song-study-blocks' )
        ),
        $message_lines,
        [
            'exclude_user_ids' => [ $actor_id ],
        ]
    );

    $actor_email = wpssb_get_project_rehearsal_notification_user_email( $actor_id );
    if ( '' === $actor_email ) {
        return;
    }

    $creator_lines = [
        sprintf(
            __( 'Creaste correctamente la propuesta de ensayo "%s".', 'wp-song-study-blocks' ),
            $focus
        ),
    ];

    if ( '' !== $schedule ) {
        $creator_lines[] = sprintf( __( 'Fecha y hora: %s', 'wp-song-study-blocks' ), $schedule );
    }

    if ( '' !== $location ) {
        $creator_lines[] = sprintf( __( 'Lugar: %s', 'wp-song-study-blocks' ), $location );
    }

    if ( '' !== $notes ) {
        $creator_lines[] = sprintf( __( 'Notas: %s', 'wp-song-study-blocks' ), $notes );
    }

    $creator_lines[] = __( 'Mientras siga como propuesta y todavía no hayan confirmado todos, puedes modificarla desde el área de ensayos.', 'wp-song-study-blocks' );
    $creator_lines[] = sprintf(
        __( 'Revisa tu propuesta aquí: %s', 'wp-song-study-blocks' ),
        wpssb_get_frontend_rehearsal_calendar_url( $post_id, 'proposals' )
    );

    wpssb_send_project_rehearsal_notification(
        $post_id,
        sprintf(
            __( '[%1$s] Creaste una propuesta de ensayo', 'wp-song-study-blocks' ),
            $project_name
        ),
        $creator_lines,
        [
            'recipients' => [ $actor_email ],
        ]
    );
}

/**
 * Notifica cuando se elimina una propuesta de ensayo.
 *
 * @param int                  $post_id  Proyecto actual.
 * @param array<string, mixed> $session  Sesión eliminada.
 * @param int                  $actor_id Usuario actor.
 * @return void
 */
function wpssb_notify_project_rehearsal_proposal_deleted( $post_id, $session, $actor_id ) {
    if ( ! is_array( $session ) ) {
        return;
    }

    $actor_name   = wpssb_get_project_rehearsal_notification_actor_name( $actor_id );
    $project_name = get_the_title( $post_id );
    $focus        = wpssb_get_project_rehearsal_notification_focus( $session );
    $schedule     = wpssb_format_project_rehearsal_schedule( $session );

    $message_lines = [
        sprintf(
            __( '%1$s eliminó la propuesta de ensayo "%2$s".', 'wp-song-study-blocks' ),
            '' !== $actor_name ? $actor_name : __( 'Un integrante', 'wp-song-study-blocks' ),
            $focus
        ),
    ];

    if ( '' !== $schedule ) {
        $message_lines[] = sprintf( __( 'La propuesta estaba agendada para: %s', 'wp-song-study-blocks' ), $schedule );
    }

    $message_lines[] = sprintf(
        __( 'Revisa el calendario del proyecto aquí: %s', 'wp-song-study-blocks' ),
        wpssb_get_frontend_rehearsal_calendar_url( $post_id, 'proposals' )
    );

    wpssb_send_project_rehearsal_notification(
        $post_id,
        sprintf(
            __( '[%1$s] %2$s eliminó una propuesta de ensayo', 'wp-song-study-blocks' ),
            $project_name,
            '' !== $actor_name ? $actor_name : __( 'Un integrante', 'wp-song-study-blocks' )
        ),
        $message_lines,
        [
            'exclude_user_ids' => [ $actor_id ],
        ]
    );
}

/**
 * Notifica cuando una propuesta o ensayo cambia por un voto individual.
 *
 * @param int                  $post_id     Proyecto actual.
 * @param array<string, mixed> $before_session Estado previo de la sesión.
 * @param array<string, mixed> $after_session  Estado nuevo de la sesión.
 * @param int                  $actor_id    Usuario que cambió su respuesta.
 * @param array<string, mixed> $before_vote Estado previo del voto.
 * @param array<string, mixed> $after_vote  Estado nuevo del voto.
 * @return void
 */
function wpssb_notify_project_rehearsal_vote_change( $post_id, $before_session, $after_session, $actor_id, $before_vote, $after_vote ) {
    $post_id  = absint( $post_id );
    $actor_id = absint( $actor_id );

    if ( $post_id <= 0 || $actor_id <= 0 || ! is_array( $after_session ) ) {
        return;
    }

    $before_status = sanitize_key( (string) ( $before_session['status'] ?? 'proposed' ) );
    $after_status  = sanitize_key( (string) ( $after_session['status'] ?? $before_status ) );

    if ( ! in_array( $after_status, [ 'proposed', 'voting', 'confirmed', 'completed' ], true ) ) {
        return;
    }

    $before_vote_value = sanitize_key( (string) ( $before_vote['vote'] ?? 'pending' ) );
    $after_vote_value  = sanitize_key( (string) ( $after_vote['vote'] ?? 'pending' ) );
    $before_comment    = sanitize_text_field( (string) ( $before_vote['comment'] ?? '' ) );
    $after_comment     = sanitize_text_field( (string) ( $after_vote['comment'] ?? '' ) );

    if ( $before_vote_value === $after_vote_value && $before_comment === $after_comment ) {
        return;
    }

    $actor_name   = wpssb_get_project_rehearsal_notification_actor_name( $actor_id, (string) ( $after_vote['nombre'] ?? '' ) );
    $project_name = get_the_title( $post_id );
    $session_id   = sanitize_key( (string) ( $after_session['id'] ?? '' ) );
    $latest_session = '' !== $session_id ? wpssb_get_project_rehearsal_session_by_id( $post_id, $session_id ) : [];
    if ( empty( $latest_session ) ) {
        $latest_session = $after_session;
    }

    $focus        = wpssb_get_project_rehearsal_notification_focus( $latest_session );
    $schedule     = wpssb_format_project_rehearsal_schedule( $latest_session );
    $comment      = sanitize_text_field( (string) ( $after_vote['comment'] ?? '' ) );
    $just_confirmed = ! in_array( $before_status, [ 'confirmed', 'completed' ], true ) && in_array( $after_status, [ 'confirmed', 'completed' ], true );

    if ( $just_confirmed ) {
        $calendar = wpssb_sanitize_project_rehearsal_calendar_sync( $latest_session['calendar'] ?? [] );
        $event_url = ! empty( $calendar['html_link'] ) ? esc_url_raw( (string) $calendar['html_link'] ) : '';

        $message_lines = [
            sprintf(
                __( 'El ensayo "%s" ya quedó confirmado por consenso del grupo.', 'wp-song-study-blocks' ),
                $focus
            ),
        ];

        if ( '' !== $schedule ) {
            $message_lines[] = sprintf( __( 'Fecha y hora: %s', 'wp-song-study-blocks' ), $schedule );
        }

        $location = sanitize_text_field( (string) ( $latest_session['location'] ?? '' ) );
        if ( '' !== $location ) {
            $message_lines[] = sprintf( __( 'Lugar: %s', 'wp-song-study-blocks' ), $location );
        }

        $notes = sanitize_textarea_field( (string) ( $latest_session['notes'] ?? '' ) );
        if ( '' !== $notes ) {
            $message_lines[] = sprintf( __( 'Notas: %s', 'wp-song-study-blocks' ), $notes );
        }

        if ( '' !== $event_url ) {
            $message_lines[] = sprintf( __( 'Evento de Google Calendar: %s', 'wp-song-study-blocks' ), $event_url );
        } else {
            $message_lines[] = __( 'Google Calendar todavía no devolvió una liga para el evento. Revisa el estado de sincronización desde el área de ensayos.', 'wp-song-study-blocks' );
        }

        $message_lines[] = sprintf(
            __( 'Revisa el ensayo confirmado aquí: %s', 'wp-song-study-blocks' ),
            wpssb_get_frontend_rehearsal_calendar_url( $post_id, 'confirmed' )
        );

        wpssb_send_project_rehearsal_notification(
            $post_id,
            sprintf(
                __( '[%1$s] Ensayo confirmado por consenso: %2$s', 'wp-song-study-blocks' ),
                $project_name,
                $focus
            ),
            $message_lines
        );

        return;
    }

    if ( in_array( $after_status, [ 'confirmed', 'completed' ], true ) ) {
        $subject = sprintf(
            __( '[%1$s] %2$s cambió su confirmación de ensayo', 'wp-song-study-blocks' ),
            $project_name,
            '' !== $actor_name ? $actor_name : __( 'Un integrante', 'wp-song-study-blocks' )
        );
    } else {
        $subject = sprintf(
            __( '[%1$s] %2$s actualizó su voto de propuesta', 'wp-song-study-blocks' ),
            $project_name,
            '' !== $actor_name ? $actor_name : __( 'Un integrante', 'wp-song-study-blocks' )
        );
    }

    $message_lines = [
        sprintf(
            __( '%1$s actualizó su respuesta para "%2$s".', 'wp-song-study-blocks' ),
            '' !== $actor_name ? $actor_name : __( 'Un integrante', 'wp-song-study-blocks' ),
            $focus
        ),
    ];

    if ( '' !== $schedule ) {
        $message_lines[] = sprintf( __( 'Fecha y hora: %s', 'wp-song-study-blocks' ), $schedule );
    }

    if ( in_array( $after_status, [ 'confirmed', 'completed' ], true ) ) {
        $message_lines[] = __( 'Este ensayo ya estaba confirmado y el grupo acaba de recibir un cambio individual de asistencia/confirmación.', 'wp-song-study-blocks' );
    } else {
        $message_lines[] = sprintf(
            __( 'Estado actual de la propuesta: %s', 'wp-song-study-blocks' ),
            wpssb_get_project_rehearsal_status_label( $after_status )
        );
    }

    $message_lines[] = sprintf(
        __( 'Cambio: %1$s -> %2$s', 'wp-song-study-blocks' ),
        wpssb_get_project_rehearsal_vote_label( $before_vote_value ),
        wpssb_get_project_rehearsal_vote_label( $after_vote_value )
    );

    if ( '' !== $comment ) {
        $message_lines[] = sprintf( __( 'Comentario: %s', 'wp-song-study-blocks' ), $comment );
    }

    $message_lines[] = sprintf(
        in_array( $after_status, [ 'confirmed', 'completed' ], true )
            ? __( 'Revisa el ensayo agendado aquí: %s', 'wp-song-study-blocks' )
            : __( 'Revisa y vota la propuesta aquí: %s', 'wp-song-study-blocks' ),
        wpssb_get_frontend_rehearsal_calendar_url(
            $post_id,
            in_array( $after_status, [ 'confirmed', 'completed' ], true ) ? 'confirmed' : 'proposals'
        )
    );

    wpssb_send_project_rehearsal_notification(
        $post_id,
        $subject,
        $message_lines,
        [
            'exclude_user_ids' => [ $actor_id ],
        ]
    );

    $actor_email = wpssb_get_project_rehearsal_notification_user_email( $actor_id );
    if ( '' === $actor_email ) {
        return;
    }

    $actor_lines = [
        sprintf(
            __( 'Tu respuesta para "%s" se modificó correctamente.', 'wp-song-study-blocks' ),
            $focus
        ),
    ];

    if ( '' !== $schedule ) {
        $actor_lines[] = sprintf( __( 'Fecha y hora: %s', 'wp-song-study-blocks' ), $schedule );
    }

    $actor_lines[] = sprintf(
        __( 'Cambio guardado: %1$s -> %2$s', 'wp-song-study-blocks' ),
        wpssb_get_project_rehearsal_vote_label( $before_vote_value ),
        wpssb_get_project_rehearsal_vote_label( $after_vote_value )
    );

    if ( '' !== $comment ) {
        $actor_lines[] = sprintf( __( 'Tu comentario: %s', 'wp-song-study-blocks' ), $comment );
    }

    $actor_lines[] = sprintf(
        in_array( $after_status, [ 'confirmed', 'completed' ], true )
            ? __( 'Revisa el ensayo aquí: %s', 'wp-song-study-blocks' )
            : __( 'Puedes volver a modificar tu voto mientras la propuesta siga abierta: %s', 'wp-song-study-blocks' ),
        wpssb_get_frontend_rehearsal_calendar_url(
            $post_id,
            in_array( $after_status, [ 'confirmed', 'completed' ], true ) ? 'confirmed' : 'proposals'
        )
    );

    wpssb_send_project_rehearsal_notification(
        $post_id,
        sprintf(
            __( '[%1$s] Tu respuesta de ensayo quedó actualizada', 'wp-song-study-blocks' ),
            $project_name
        ),
        $actor_lines,
        [
            'recipients' => [ $actor_email ],
        ]
    );
}

/**
 * Notifica cuando alguien fuerza la confirmación de un ensayo.
 *
 * @param int                  $post_id       Proyecto actual.
 * @param array<string, mixed> $before_session Estado previo.
 * @param array<string, mixed> $after_session  Estado nuevo.
 * @param int                  $actor_id      Usuario actor.
 * @return void
 */
function wpssb_notify_project_rehearsal_force_confirmed( $post_id, $before_session, $after_session, $actor_id ) {
    if ( ! is_array( $after_session ) ) {
        return;
    }

    $actor_name   = wpssb_get_project_rehearsal_notification_actor_name( $actor_id );
    $project_name = get_the_title( $post_id );
    $focus        = wpssb_get_project_rehearsal_notification_focus( $after_session );
    $schedule     = wpssb_format_project_rehearsal_schedule( $after_session );
    $location     = sanitize_text_field( (string) ( $after_session['location'] ?? '' ) );
    $was_confirmed = is_array( $before_session ) && in_array( sanitize_key( (string) ( $before_session['status'] ?? '' ) ), [ 'confirmed', 'completed' ], true );

    $message_lines = [
        sprintf(
            $was_confirmed
                ? __( '%1$s reforzó por excepción la confirmación del ensayo "%2$s".', 'wp-song-study-blocks' )
                : __( '%1$s forzó la confirmación del ensayo "%2$s".', 'wp-song-study-blocks' ),
            '' !== $actor_name ? $actor_name : __( 'Un integrante', 'wp-song-study-blocks' ),
            $focus
        ),
    ];

    if ( '' !== $schedule ) {
        $message_lines[] = sprintf( __( 'Fecha y hora: %s', 'wp-song-study-blocks' ), $schedule );
    }

    if ( '' !== $location ) {
        $message_lines[] = sprintf( __( 'Lugar: %s', 'wp-song-study-blocks' ), $location );
    }

    $message_lines[] = sprintf(
        __( 'Revisa el ensayo confirmado aquí: %s', 'wp-song-study-blocks' ),
        wpssb_get_frontend_rehearsal_calendar_url( $post_id, 'confirmed' )
    );

    wpssb_send_project_rehearsal_notification(
        $post_id,
        sprintf(
            __( '[%1$s] %2$s forzó un ensayo confirmado', 'wp-song-study-blocks' ),
            $project_name,
            '' !== $actor_name ? $actor_name : __( 'Un integrante', 'wp-song-study-blocks' )
        ),
        $message_lines,
        [
            'exclude_user_ids' => [ $actor_id ],
        ]
    );
}

/**
 * Notifica cuando quien propuso un ensayo confirmado lo cancela.
 *
 * @param int                  $post_id       Proyecto actual.
 * @param array<string, mixed> $before_session Estado previo.
 * @param array<string, mixed> $after_session  Estado cancelado.
 * @param int                  $actor_id      Usuario actor.
 * @return void
 */
function wpssb_notify_project_rehearsal_cancelled( $post_id, $before_session, $after_session, $actor_id ) {
    if ( ! is_array( $after_session ) ) {
        return;
    }

    $actor_name   = wpssb_get_project_rehearsal_notification_actor_name( $actor_id );
    $project_name = get_the_title( $post_id );
    $focus        = wpssb_get_project_rehearsal_notification_focus( $after_session );
    $schedule     = wpssb_format_project_rehearsal_schedule( $before_session );
    $reason       = sanitize_textarea_field( (string) ( $after_session['cancellation_reason'] ?? '' ) );

    $message_lines = [
        sprintf(
            __( '%1$s canceló el ensayo confirmado "%2$s".', 'wp-song-study-blocks' ),
            '' !== $actor_name ? $actor_name : __( 'Un integrante', 'wp-song-study-blocks' ),
            $focus
        ),
    ];

    if ( '' !== $schedule ) {
        $message_lines[] = sprintf( __( 'Fecha y hora original: %s', 'wp-song-study-blocks' ), $schedule );
    }

    if ( '' !== $reason ) {
        $message_lines[] = sprintf( __( 'Razón de la cancelación: %s', 'wp-song-study-blocks' ), $reason );
    }

    $message_lines[] = __( 'La sesión quedó bloqueada en el Planificador y el evento de Google Calendar se intentó eliminar para todos los invitados.', 'wp-song-study-blocks' );
    $message_lines[] = sprintf(
        __( 'Revisa las sesiones confirmadas aquí: %s', 'wp-song-study-blocks' ),
        wpssb_get_frontend_rehearsal_calendar_url( $post_id, 'confirmed' )
    );

    wpssb_send_project_rehearsal_notification(
        $post_id,
        sprintf(
            __( '[%1$s] Ensayo cancelado: %2$s', 'wp-song-study-blocks' ),
            $project_name,
            $focus
        ),
        $message_lines,
        [
            'exclude_user_ids' => [ $actor_id ],
        ]
    );
}

/**
 * Indica si una sesión ya debería sincronizarse automáticamente con Google Calendar.
 *
 * @param array $session Sesión saneada.
 * @return bool
 */
function wpssb_should_auto_sync_project_rehearsal_session( $session ) {
    if ( ! is_array( $session ) || empty( $session['scheduled_for'] ) ) {
        return false;
    }

    $status   = sanitize_key( (string) ( $session['status'] ?? '' ) );
    $calendar = wpssb_sanitize_project_rehearsal_calendar_sync( $session['calendar'] ?? [] );

    if ( 'cancelled' === $status ) {
        return '' !== ( $calendar['event_id'] ?? '' );
    }

    return in_array( $status, [ 'confirmed', 'completed' ], true );
}

/**
 * Verifica si una cuenta puede operar Calendar, con una prueba API como respaldo
 * cuando los scopes guardados quedaron incompletos en instalaciones previas.
 *
 * @param int $user_id Usuario candidato.
 * @return bool
 */
function wpssb_project_rehearsal_calendar_user_can_sync( $user_id ) {
    $user_id = absint( $user_id );

    if (
        $user_id <= 0
        || ! function_exists( 'wpss_get_google_calendar_status_payload' )
        || ! function_exists( 'wpss_get_google_calendar_access_token' )
    ) {
        return false;
    }

    $status = wpss_get_google_calendar_status_payload( $user_id );
    if (
        empty( $status['configured'] )
        || empty( $status['connected'] )
        || empty( $status['has_refresh_token'] )
    ) {
        return false;
    }

    $token = wpss_get_google_calendar_access_token( $user_id );
    if ( is_wp_error( $token ) || '' === trim( (string) $token ) ) {
        return false;
    }

    if ( ! empty( $status['has_required_scope'] ) ) {
        return true;
    }

    if ( ! function_exists( 'wpssb_google_calendar_request' ) ) {
        return false;
    }

    $probe = wpssb_google_calendar_request(
        $user_id,
        'GET',
        add_query_arg(
            [
                'maxResults'   => 1,
                'singleEvents' => 'true',
                'timeMin'      => gmdate( 'c', time() - HOUR_IN_SECONDS ),
                'timeMax'      => gmdate( 'c', time() + DAY_IN_SECONDS ),
                'fields'       => 'items(id,status),summary,timeZone',
            ],
            'https://www.googleapis.com/calendar/v3/calendars/primary/events'
        )
    );

    return ! is_wp_error( $probe );
}

/**
 * Resuelve qué usuario puede sincronizar ensayos con Google Calendar.
 *
 * Prioriza al usuario actual, pero si no tiene Calendar listo usa al autor
 * del proyecto o a otro integrante con credenciales operativas.
 *
 * @param int $post_id           Proyecto actual.
 * @param int $preferred_user_id Usuario preferido.
 * @return int
 */
function wpssb_get_project_rehearsal_calendar_sync_user_id( $post_id, $preferred_user_id = 0 ) {
    $post_id           = absint( $post_id );
    $preferred_user_id = absint( $preferred_user_id );

    if (
        $post_id <= 0
        || ! function_exists( 'wpss_get_google_calendar_status_payload' )
        || ! function_exists( 'wpss_get_google_calendar_access_token' )
    ) {
        return 0;
    }

    $candidate_ids = [];

    foreach ( [ $preferred_user_id, get_current_user_id(), (int) get_post_field( 'post_author', $post_id ) ] as $candidate_id ) {
        $candidate_id = absint( $candidate_id );
        if ( $candidate_id > 0 ) {
            $candidate_ids[] = $candidate_id;
        }
    }

    foreach ( wpssb_get_project_collaborators( $post_id ) as $user ) {
        if ( $user instanceof WP_User ) {
            $candidate_ids[] = (int) $user->ID;
        }
    }

    $candidate_ids = array_values( array_unique( array_filter( array_map( 'absint', $candidate_ids ) ) ) );

    foreach ( $candidate_ids as $candidate_id ) {
        if ( wpssb_project_rehearsal_calendar_user_can_sync( $candidate_id ) ) {
            return $candidate_id;
        }
    }

    return 0;
}

/**
 * Resuelve la cuenta Google que debe operar el evento de una sesión.
 *
 * Para crear ensayos confirmados se usa la cuenta de quien propuso la sesión.
 * Si el evento ya existe, se conserva la cuenta que lo sincronizó originalmente.
 *
 * @param int                  $post_id  Proyecto actual.
 * @param array<string, mixed> $session  Sesión saneada.
 * @param array<string, mixed> $calendar Estado de sincronización.
 * @return int
 */
function wpssb_get_project_rehearsal_calendar_session_sync_user_id( $post_id, $session, $calendar = [] ) {
    $post_id = absint( $post_id );

    if (
        $post_id <= 0
        || ! is_array( $session )
        || ! function_exists( 'wpss_get_google_calendar_status_payload' )
        || ! function_exists( 'wpss_get_google_calendar_access_token' )
    ) {
        return 0;
    }

    $calendar       = wpssb_sanitize_project_rehearsal_calendar_sync( $calendar );
    $synced_user_id = absint( $calendar['synced_by_user_id'] ?? 0 );
    if ( $synced_user_id > 0 && wpssb_project_rehearsal_calendar_user_can_sync( $synced_user_id ) ) {
        return $synced_user_id;
    }

    $creator_id = absint( $session['created_by'] ?? 0 );
    if ( $creator_id > 0 && wpssb_project_rehearsal_calendar_user_can_sync( $creator_id ) ) {
        return $creator_id;
    }

    return 0;
}

/**
 * Construye un enlace de Google Calendar para un ensayo confirmado.
 *
 * @param int   $post_id  Proyecto actual.
 * @param array $session  Sesión saneada.
 * @return string
 */
function wpssb_get_project_rehearsal_google_calendar_url( $post_id, $session ) {
    $post_id = absint( $post_id );
    if ( $post_id <= 0 || ! is_array( $session ) || empty( $session['scheduled_for'] ) ) {
        return '';
    }

    $status = sanitize_key( (string) ( $session['status'] ?? '' ) );
    if ( ! in_array( $status, [ 'confirmed', 'completed' ], true ) ) {
        return '';
    }

    $event_data = wpssb_build_project_rehearsal_google_event( $post_id, $session );
    if ( ! is_array( $event_data ) ) {
        return '';
    }

    $params = [
        'action'   => 'TEMPLATE',
        'text'     => sanitize_text_field( (string) $event_data['summary'] ),
        'dates'    => ( new DateTimeImmutable( $event_data['start']['dateTime'] ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' ) . '/' . ( new DateTimeImmutable( $event_data['end']['dateTime'] ) )->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Ymd\THis\Z' ),
        'details'  => sanitize_textarea_field( (string) $event_data['description'] ),
        'location' => sanitize_text_field( (string) $event_data['location'] ),
        'ctz'      => wp_timezone_string() ?: 'UTC',
    ];

    $guests = array_values(
        array_unique(
            array_filter(
                array_map(
                    static function ( $attendee ) {
                        return sanitize_email( (string) ( $attendee['email'] ?? '' ) );
                    },
                    (array) ( $event_data['attendees'] ?? [] )
                )
            )
        )
    );

    if ( ! empty( $guests ) ) {
        $params['add'] = implode( ',', $guests );
    }

    return add_query_arg( $params, 'https://calendar.google.com/calendar/render' );
}

/**
 * Sincroniza una sesión del proyecto con Google Calendar.
 *
 * @param int    $post_id    Proyecto actual.
 * @param string $session_id Identificador de la sesión.
 * @return array<string, mixed>|WP_Error
 */
function wpssb_sync_project_rehearsal_google_calendar( $post_id, $session_id ) {
    $post_id    = absint( $post_id );
    $session_id = sanitize_key( (string) $session_id );

    if ( $post_id <= 0 || '' === $session_id ) {
        return new WP_Error( 'wpss_rehearsal_calendar_invalid', __( 'Faltan datos para sincronizar el ensayo con Google Calendar.', 'wp-song-study-blocks' ) );
    }

    if ( ! function_exists( 'wpssb_google_calendar_request' ) ) {
        return new WP_Error( 'wpss_rehearsal_calendar_unavailable', __( 'La integración con Google Calendar no está disponible.', 'wp-song-study-blocks' ) );
    }

    $meta     = wpssb_get_project_rehearsal_meta( $post_id );
    $sessions = wpssb_sanitize_project_rehearsal_sessions( $meta['sessions'] ?? [], $post_id );
    $index    = null;

    foreach ( $sessions as $candidate_index => $session ) {
        if ( is_array( $session ) && $session_id === ( $session['id'] ?? '' ) ) {
            $index = (int) $candidate_index;
            break;
        }
    }

    if ( null === $index || ! isset( $sessions[ $index ] ) || ! is_array( $sessions[ $index ] ) ) {
        return new WP_Error( 'wpss_rehearsal_session_not_found', __( 'La sesión de ensayo ya no existe en este proyecto.', 'wp-song-study-blocks' ), [ 'status' => 404 ] );
    }

    $session    = $sessions[ $index ];
    $status     = sanitize_key( (string) ( $session['status'] ?? '' ) );
    $calendar   = wpssb_sanitize_project_rehearsal_calendar_sync( $session['calendar'] ?? [] );
    $event_id   = sanitize_text_field( (string) ( $calendar['event_id'] ?? '' ) );
    $sync_user_id = 0;
    $request_url = '';
    $method      = 'POST';
    $body        = [];
    $message     = '';

    if ( 'cancelled' === $status ) {
        if ( '' === $event_id ) {
            $sessions[ $index ]['calendar'] = array_merge(
                $calendar,
                [
                    'event_id'          => '',
                    'html_link'         => '',
                    'synced_at'         => gmdate( 'c' ),
                    'sync_status'       => 'cancelled',
                    'sync_error'        => '',
                    'synced_by_user_id' => absint( $calendar['synced_by_user_id'] ?? 0 ),
                ]
            );

            wpssb_update_project_rehearsal_meta(
                $post_id,
                [
                    'project_id'   => $post_id,
                    'availability' => $meta['availability'] ?? [],
                    'sessions'     => $sessions,
                ]
            );

            return [
                'message' => __( 'El ensayo cancelado no tenía evento previo en Google Calendar.', 'wp-song-study-blocks' ),
                'event'   => [
                    'id'        => '',
                    'html_link' => '',
                    'status'    => 'cancelled',
                ],
            ];
        }

        $sync_user_id = wpssb_get_project_rehearsal_calendar_session_sync_user_id( $post_id, $session, $calendar );
        if ( $sync_user_id <= 0 ) {
            return new WP_Error(
                'wpss_rehearsal_calendar_not_ready',
                __( 'No se pudo eliminar el evento porque la cuenta que lo sincronizó o la persona que propuso el ensayo no tiene Google Calendar listo.', 'wp-song-study-blocks' ),
                [ 'status' => 400 ]
            );
        }

        $method      = 'DELETE';
        $request_url = add_query_arg(
            [ 'sendUpdates' => 'all' ],
            'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode( $event_id )
        );
        $message = __( 'El evento del ensayo se eliminó de Google Calendar.', 'wp-song-study-blocks' );
    } else {
        if ( ! in_array( $status, [ 'confirmed', 'completed' ], true ) ) {
            return new WP_Error( 'wpss_rehearsal_calendar_not_confirmed', __( 'Google Calendar solo se sincroniza cuando la propuesta ya quedó como ensayo confirmado.', 'wp-song-study-blocks' ), [ 'status' => 400 ] );
        }

        $sync_user_id = wpssb_get_project_rehearsal_calendar_session_sync_user_id( $post_id, $session, $calendar );
        if ( $sync_user_id <= 0 ) {
            return new WP_Error(
                'wpss_rehearsal_calendar_not_ready',
                __( 'La persona que propuso el ensayo debe conectar Google Calendar para crear o actualizar el evento.', 'wp-song-study-blocks' ),
                [ 'status' => 400 ]
            );
        }

        $event = wpssb_build_project_rehearsal_google_event( $post_id, $session );
        if ( ! is_array( $event ) ) {
            return new WP_Error( 'wpss_rehearsal_calendar_payload_invalid', __( 'No fue posible construir el evento de Google Calendar para este ensayo.', 'wp-song-study-blocks' ), [ 'status' => 400 ] );
        }

        $method = '' !== $event_id ? 'PATCH' : 'POST';
        $body   = $event;
        $request_url = add_query_arg(
            [ 'sendUpdates' => 'all' ],
            'https://www.googleapis.com/calendar/v3/calendars/primary/events' . ( '' !== $event_id ? '/' . rawurlencode( $event_id ) : '' )
        );
        if ( '' !== $event_id ) {
            $message = __( 'El evento del ensayo se actualizó en Google Calendar.', 'wp-song-study-blocks' );
        } else {
            $message = __( 'El evento del ensayo confirmado se creó en Google Calendar.', 'wp-song-study-blocks' );
        }
    }

    $result = wpssb_google_calendar_request(
        $sync_user_id,
        $method,
        $request_url,
        [
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
            ],
            'body'    => 'DELETE' === $method ? '' : wp_json_encode( $body ),
        ]
    );

    if ( is_wp_error( $result ) ) {
        $error_data = $result->get_error_data();
        $error_code = is_array( $error_data ) ? absint( $error_data['status_code'] ?? 0 ) : 0;

        if ( 'cancelled' === $status && in_array( $error_code, [ 404, 410 ], true ) ) {
            $sessions[ $index ]['calendar'] = [
                'event_id'          => '',
                'html_link'         => '',
                'synced_at'         => gmdate( 'c' ),
                'sync_status'       => 'cancelled',
                'sync_error'        => '',
                'synced_by_user_id' => $sync_user_id,
            ];

            wpssb_update_project_rehearsal_meta(
                $post_id,
                [
                    'project_id'   => $post_id,
                    'availability' => $meta['availability'] ?? [],
                    'sessions'     => $sessions,
                ]
            );

            return [
                'message' => __( 'El evento de Google Calendar ya no existía y se limpió de la sesión cancelada.', 'wp-song-study-blocks' ),
                'event'   => [
                    'id'        => $event_id,
                    'html_link' => '',
                    'status'    => 'cancelled',
                ],
            ];
        }

        $sessions[ $index ]['calendar'] = array_merge(
            $calendar,
            [
                'synced_at'   => gmdate( 'c' ),
                'sync_status' => 'error',
                'sync_error'  => sanitize_text_field( $result->get_error_message() ),
            ]
        );

        wpssb_update_project_rehearsal_meta(
            $post_id,
            [
                'project_id'   => $post_id,
                'availability' => $meta['availability'] ?? [],
                'sessions'     => $sessions,
            ]
        );

        return new WP_Error(
            'wpss_rehearsal_calendar_sync_failed',
            $result->get_error_message(),
            [ 'status' => 502 ]
        );
    }

    $result_json = isset( $result['json'] ) && is_array( $result['json'] ) ? $result['json'] : [];
    if ( 'cancelled' === $status ) {
        $sessions[ $index ]['calendar'] = [
            'event_id'          => '',
            'html_link'         => '',
            'synced_at'         => gmdate( 'c' ),
            'sync_status'       => 'cancelled',
            'sync_error'        => '',
            'synced_by_user_id' => $sync_user_id,
        ];

        wpssb_update_project_rehearsal_meta(
            $post_id,
            [
                'project_id'   => $post_id,
                'availability' => $meta['availability'] ?? [],
                'sessions'     => $sessions,
            ]
        );

        return [
            'message' => $message,
            'event'   => [
                'id'        => $event_id,
                'html_link' => '',
                'status'    => 'cancelled',
            ],
        ];
    }

    $sessions[ $index ]['calendar'] = [
        'event_id'          => sanitize_text_field( (string) ( $result_json['id'] ?? $event_id ) ),
        'html_link'         => esc_url_raw( (string) ( $result_json['htmlLink'] ?? ( $calendar['html_link'] ?? '' ) ) ),
        'synced_at'         => gmdate( 'c' ),
        'sync_status'       => 'synced',
        'sync_error'        => '',
        'synced_by_user_id' => $sync_user_id,
    ];

    wpssb_update_project_rehearsal_meta(
        $post_id,
        [
            'project_id'   => $post_id,
            'availability' => $meta['availability'] ?? [],
            'sessions'     => $sessions,
        ]
    );

    return [
        'message' => $message,
        'event'   => [
            'id'        => sanitize_text_field( (string) ( $result_json['id'] ?? $event_id ) ),
            'html_link' => esc_url_raw( (string) ( $result_json['htmlLink'] ?? '' ) ),
            'status'    => sanitize_key( (string) ( $result_json['status'] ?? $status ) ),
        ],
    ];
}

/**
 * Guarda un error de sincronización de Calendar en una sesión concreta.
 *
 * @param int    $post_id    Proyecto actual.
 * @param string $session_id Identificador de la sesión.
 * @param string $message    Mensaje de error.
 */
function wpssb_mark_project_rehearsal_calendar_sync_error( $post_id, $session_id, $message ) {
    $post_id    = absint( $post_id );
    $session_id = sanitize_key( (string) $session_id );
    $message    = sanitize_text_field( (string) $message );

    if ( $post_id <= 0 || '' === $session_id || '' === $message ) {
        return;
    }

    $meta     = wpssb_get_project_rehearsal_meta( $post_id );
    $sessions = wpssb_sanitize_project_rehearsal_sessions( $meta['sessions'] ?? [], $post_id );
    $updated  = false;

    foreach ( $sessions as $index => $session ) {
        if ( ! is_array( $session ) || $session_id !== ( $session['id'] ?? '' ) ) {
            continue;
        }

        $calendar = wpssb_sanitize_project_rehearsal_calendar_sync( $session['calendar'] ?? [] );
        $sessions[ $index ]['calendar'] = array_merge(
            $calendar,
            [
                'synced_at'   => gmdate( 'c' ),
                'sync_status' => 'error',
                'sync_error'  => $message,
            ]
        );
        $updated = true;
        break;
    }

    if ( ! $updated ) {
        return;
    }

    wpssb_update_project_rehearsal_meta(
        $post_id,
        [
            'project_id'   => $post_id,
            'availability' => $meta['availability'] ?? [],
            'sessions'     => $sessions,
        ]
    );
}

/**
 * Sincroniza y conserva en la sesión cualquier error que devuelva el flujo.
 *
 * @param int    $post_id    Proyecto actual.
 * @param string $session_id Identificador de la sesión.
 * @return array<string, mixed>|WP_Error
 */
function wpssb_attempt_project_rehearsal_google_calendar_sync( $post_id, $session_id ) {
    if ( ! function_exists( 'wpssb_sync_project_rehearsal_google_calendar' ) ) {
        return new WP_Error( 'wpss_rehearsal_calendar_unavailable', __( 'La integración con Google Calendar no está disponible.', 'wp-song-study-blocks' ) );
    }

    $result = wpssb_sync_project_rehearsal_google_calendar( $post_id, $session_id );

    if ( is_wp_error( $result ) ) {
        wpssb_mark_project_rehearsal_calendar_sync_error( $post_id, $session_id, $result->get_error_message() );
    }

    return $result;
}

/**
 * Devuelve proyectos candidatos para revisar eventos huérfanos de Calendar.
 *
 * @param int $project_id Proyecto específico o 0 para todos.
 * @return int[]
 */
function wpssb_get_rehearsal_calendar_cleanup_project_ids( $project_id = 0 ) {
    $project_id = absint( $project_id );
    if ( $project_id > 0 ) {
        return WPSSB_PROJECT_POST_TYPE === get_post_type( $project_id ) ? [ $project_id ] : [];
    }

    return array_map(
        'absint',
        get_posts(
            [
                'post_type'      => WPSSB_PROJECT_POST_TYPE,
                'post_status'    => wpssb_get_rehearsal_project_post_statuses(),
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_key'       => 'pd_proyecto_ensayos',
            ]
        )
    );
}

/**
 * Encuentra eventos de Calendar asociados a sesiones que no son ensayos activos.
 *
 * @param int $project_id Proyecto específico o 0 para todos.
 * @return array<int, array<string, mixed>>
 */
function wpssb_find_project_rehearsal_unconfirmed_calendar_events( $project_id = 0 ) {
    $events = [];

    foreach ( wpssb_get_rehearsal_calendar_cleanup_project_ids( $project_id ) as $current_project_id ) {
        $meta     = wpssb_get_project_rehearsal_meta( $current_project_id );
        $sessions = wpssb_sanitize_project_rehearsal_sessions( $meta['sessions'] ?? [], $current_project_id );

        foreach ( $sessions as $session ) {
            if ( ! is_array( $session ) ) {
                continue;
            }

            $status   = sanitize_key( (string) ( $session['status'] ?? '' ) );
            $calendar = wpssb_sanitize_project_rehearsal_calendar_sync( $session['calendar'] ?? [] );
            $event_id = sanitize_text_field( (string) ( $calendar['event_id'] ?? '' ) );

            if ( '' === $event_id || in_array( $status, [ 'confirmed', 'completed' ], true ) ) {
                continue;
            }

            $synced_by_user_id = absint( $calendar['synced_by_user_id'] ?? 0 );
            $synced_by_user    = $synced_by_user_id > 0 ? get_user_by( 'id', $synced_by_user_id ) : false;

            $events[] = [
                'project_id'        => $current_project_id,
                'project_title'     => sanitize_text_field( get_the_title( $current_project_id ) ),
                'session_id'        => sanitize_key( (string) ( $session['id'] ?? '' ) ),
                'session_focus'     => sanitize_text_field( (string) ( $session['focus'] ?? '' ) ),
                'session_schedule'  => wpssb_format_project_rehearsal_schedule( $session ),
                'session_status'    => $status,
                'event_id'          => $event_id,
                'html_link'         => esc_url_raw( (string) ( $calendar['html_link'] ?? '' ) ),
                'synced_by_user_id' => $synced_by_user_id,
                'synced_by_label'   => $synced_by_user instanceof WP_User ? sanitize_text_field( $synced_by_user->display_name ) : '',
            ];
        }
    }

    return $events;
}

/**
 * Elimina un evento de Google Calendar usando el usuario más probable como propietario.
 *
 * @param int    $project_id         Proyecto actual.
 * @param string $event_id           ID del evento en Google.
 * @param int    $preferred_user_id  Usuario que creó/sincronizó el evento.
 * @return array<string, mixed>|WP_Error
 */
function wpssb_delete_project_rehearsal_google_calendar_event( $project_id, $event_id, $preferred_user_id = 0 ) {
    $project_id = absint( $project_id );
    $event_id   = sanitize_text_field( (string) $event_id );

    if ( $project_id <= 0 || '' === $event_id || ! function_exists( 'wpssb_google_calendar_request' ) ) {
        return new WP_Error( 'wpss_rehearsal_calendar_delete_invalid', __( 'Faltan datos para borrar el evento de Google Calendar.', 'wp-song-study-blocks' ) );
    }

    $candidate_user_ids = array_values(
        array_unique(
            array_filter(
                array_map(
                    'absint',
                    [
                        $preferred_user_id,
                        get_current_user_id(),
                        wpssb_get_project_rehearsal_calendar_sync_user_id( $project_id, get_current_user_id() ),
                    ]
                )
            )
        )
    );

    if ( empty( $candidate_user_ids ) ) {
        return new WP_Error( 'wpss_rehearsal_calendar_delete_user_missing', __( 'No hay una cuenta de Google Calendar conectada para borrar este evento.', 'wp-song-study-blocks' ) );
    }

    $request_url = add_query_arg(
        [ 'sendUpdates' => 'all' ],
        'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . rawurlencode( $event_id )
    );
    $last_error  = null;

    foreach ( $candidate_user_ids as $user_id ) {
        $result = wpssb_google_calendar_request(
            $user_id,
            'DELETE',
            $request_url,
            [
                'headers' => [
                    'Content-Type' => 'application/json; charset=utf-8',
                ],
                'body'    => '',
            ]
        );

        if ( ! is_wp_error( $result ) ) {
            return [
                'deleted'          => true,
                'already_missing'  => false,
                'deleted_by_user'  => $user_id,
            ];
        }

        $last_error = $result;
        $error_data = $result->get_error_data();
        $error_code = is_array( $error_data ) ? absint( $error_data['status_code'] ?? 0 ) : 0;

        if ( in_array( $error_code, [ 404, 410 ], true ) ) {
            return [
                'deleted'          => true,
                'already_missing'  => true,
                'deleted_by_user'  => $user_id,
            ];
        }
    }

    return $last_error instanceof WP_Error
        ? $last_error
        : new WP_Error( 'wpss_rehearsal_calendar_delete_failed', __( 'Google Calendar no permitió borrar el evento.', 'wp-song-study-blocks' ) );
}

/**
 * Borra eventos de Calendar asociados a sesiones no confirmadas y limpia sus IDs locales.
 *
 * @param int $project_id Proyecto específico o 0 para todos.
 * @return array<string, mixed>
 */
function wpssb_cleanup_project_rehearsal_unconfirmed_calendar_events( $project_id = 0 ) {
    $summary = [
        'found'   => 0,
        'deleted' => 0,
        'failed'  => 0,
        'results' => [],
    ];

    foreach ( wpssb_get_rehearsal_calendar_cleanup_project_ids( $project_id ) as $current_project_id ) {
        $meta     = wpssb_get_project_rehearsal_meta( $current_project_id );
        $sessions = wpssb_sanitize_project_rehearsal_sessions( $meta['sessions'] ?? [], $current_project_id );
        $changed  = false;

        foreach ( $sessions as $index => $session ) {
            if ( ! is_array( $session ) ) {
                continue;
            }

            $status   = sanitize_key( (string) ( $session['status'] ?? '' ) );
            $calendar = wpssb_sanitize_project_rehearsal_calendar_sync( $session['calendar'] ?? [] );
            $event_id = sanitize_text_field( (string) ( $calendar['event_id'] ?? '' ) );

            if ( '' === $event_id || in_array( $status, [ 'confirmed', 'completed' ], true ) ) {
                continue;
            }

            $summary['found']++;
            $delete_result = wpssb_delete_project_rehearsal_google_calendar_event(
                $current_project_id,
                $event_id,
                absint( $calendar['synced_by_user_id'] ?? 0 )
            );

            $result_entry = [
                'project_id'     => $current_project_id,
                'project_title'  => sanitize_text_field( get_the_title( $current_project_id ) ),
                'session_id'     => sanitize_key( (string) ( $session['id'] ?? '' ) ),
                'session_status' => $status,
                'event_id'       => $event_id,
                'success'        => ! is_wp_error( $delete_result ),
                'message'        => '',
            ];

            if ( is_wp_error( $delete_result ) ) {
                $summary['failed']++;
                $result_entry['message'] = sanitize_text_field( $delete_result->get_error_message() );
                $sessions[ $index ]['calendar'] = array_merge(
                    $calendar,
                    [
                        'synced_at'   => gmdate( 'c' ),
                        'sync_status' => 'error',
                        'sync_error'  => $result_entry['message'],
                    ]
                );
                $changed = true;
            } else {
                $summary['deleted']++;
                $result_entry['message'] = ! empty( $delete_result['already_missing'] )
                    ? __( 'El evento ya no existía en Google Calendar; se limpió la referencia local.', 'wp-song-study-blocks' )
                    : __( 'Evento eliminado de Google Calendar y referencia local limpiada.', 'wp-song-study-blocks' );
                $sessions[ $index ]['calendar'] = [
                    'event_id'          => '',
                    'html_link'         => '',
                    'synced_at'         => gmdate( 'c' ),
                    'sync_status'       => 'cancelled',
                    'sync_error'        => '',
                    'synced_by_user_id' => absint( $delete_result['deleted_by_user'] ?? 0 ),
                ];
                $changed = true;
            }

            $summary['results'][] = $result_entry;
        }

        if ( $changed ) {
            wpssb_update_project_rehearsal_meta(
                $current_project_id,
                [
                    'project_id'   => $current_project_id,
                    'availability' => $meta['availability'] ?? [],
                    'sessions'     => $sessions,
                ]
            );
        }
    }

    return $summary;
}

/**
 * Ejecuta desde admin la limpieza de eventos de ensayos no confirmados.
 *
 * @return void
 */
function wpssb_handle_admin_rehearsal_calendar_orphan_cleanup() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'No tienes permiso para ejecutar esta limpieza.', 'wp-song-study-blocks' ) );
    }

    check_admin_referer( 'wpssb_cleanup_rehearsal_calendar_orphans', 'wpssb_calendar_cleanup_nonce' );

    $project_id = isset( $_POST['project_id'] ) ? absint( wp_unslash( $_POST['project_id'] ) ) : 0;
    $summary    = wpssb_cleanup_project_rehearsal_unconfirmed_calendar_events( $project_id );
    $redirect   = add_query_arg(
        [
            'page'                      => 'wpss-drive-global-settings',
            'calendar_cleanup'          => 'done',
            'calendar_cleanup_found'    => absint( $summary['found'] ?? 0 ),
            'calendar_cleanup_deleted'  => absint( $summary['deleted'] ?? 0 ),
            'calendar_cleanup_failed'   => absint( $summary['failed'] ?? 0 ),
        ],
        admin_url( 'admin.php' )
    );

    wp_safe_redirect( $redirect );
    exit;
}
add_action( 'admin_post_wpssb_cleanup_rehearsal_calendar_orphans', 'wpssb_handle_admin_rehearsal_calendar_orphan_cleanup' );

/**
 * Sincroniza automáticamente las sesiones elegibles al guardar la herramienta.
 *
 * @param int $post_id Proyecto actual.
 * @return array<int, array<string, mixed>>
 */
function wpssb_auto_sync_project_rehearsal_google_calendar( $post_id ) {
    $post_id = absint( $post_id );
    if ( $post_id <= 0 ) {
        return [];
    }

    $calendar_status = wpssb_get_project_rehearsal_google_calendar_status();
    if ( empty( $calendar_status['ready'] ) ) {
        return [];
    }

    $meta    = wpssb_get_project_rehearsal_meta( $post_id );
    $results = [];

    foreach ( (array) ( $meta['sessions'] ?? [] ) as $session ) {
        if ( ! is_array( $session ) || empty( $session['id'] ) || ! wpssb_should_auto_sync_project_rehearsal_session( $session ) ) {
            continue;
        }

        $result = wpssb_attempt_project_rehearsal_google_calendar_sync( $post_id, (string) $session['id'] );
        $results[] = [
            'session_id' => sanitize_key( (string) $session['id'] ),
            'success'    => ! is_wp_error( $result ),
            'message'    => is_wp_error( $result )
                ? sanitize_text_field( $result->get_error_message() )
                : sanitize_text_field( (string) ( $result['message'] ?? '' ) ),
        ];
    }

    return $results;
}

/**
 * Devuelve el payload completo para la herramienta de ensayos de un proyecto.
 *
 * @param int $post_id Proyecto actual.
 * @return array<string, mixed>
 */
function wpssb_get_project_rehearsal_payload( $post_id ) {
    $post_id        = absint( $post_id );
    $post           = get_post( $post_id );
    $collaborators  = array_map(
        static function ( $user ) {
            return [
                'id'     => (int) $user->ID,
                'nombre' => sanitize_text_field( $user->display_name ),
            ];
        },
        wpssb_get_project_rehearsal_members( $post_id )
    );
    $meta           = wpssb_expire_project_rehearsal_proposals_in_meta( $post_id, wpssb_get_project_rehearsal_meta( $post_id ) );
    $availability   = wpssb_sanitize_project_rehearsal_availability( $meta['availability'] ?? [], $post_id );
    $google_calendar = wpssb_get_project_rehearsal_google_calendar_status();
    $sessions       = array_map(
        static function ( $session ) use ( $post_id, $google_calendar ) {
            if ( ! is_array( $session ) ) {
                return [];
            }

            $stored_calendar     = wpssb_sanitize_project_rehearsal_calendar_sync( $session['calendar'] ?? [] );
            $status              = sanitize_key( (string) ( $session['status'] ?? '' ) );
            $session['calendar'] = array_merge(
                $stored_calendar,
                [
                    'google_calendar_url' => wpssb_get_project_rehearsal_google_calendar_url( $post_id, $session ),
                    'ready'               => in_array( $status, [ 'confirmed', 'completed' ], true ),
                    'can_sync'            => ! empty( $google_calendar['ready'] ) && (
                        in_array( $status, [ 'confirmed', 'completed' ], true )
                        || ( 'cancelled' === $status && ! empty( $stored_calendar['event_id'] ) )
                    ),
                    'has_event'           => ! empty( $stored_calendar['event_id'] ),
                ]
            );

            return $session;
        },
        wpssb_sanitize_project_rehearsal_sessions( $meta['sessions'] ?? [], $post_id )
    );

    return [
        'project'            => [
            'id'                    => $post_id,
            'titulo'                => $post instanceof WP_Post ? sanitize_text_field( get_the_title( $post_id ) ) : '',
            'colaboradores'         => $collaborators,
            'can_manage_rehearsals' => wpssb_user_can_manage_project_rehearsals( $post_id ),
            'google_calendar'       => $google_calendar,
        ],
        'availability'       => $availability,
        'recommended_slots'  => wpssb_get_project_rehearsal_recommendations( $availability ),
        'sessions'           => $sessions,
        'summary'            => wpssb_get_project_rehearsal_summary( $availability, $sessions, $collaborators ),
        'updated_at_gmt'     => sanitize_text_field( (string) ( $meta['updated_at_gmt'] ?? '' ) ),
        'updated_by'         => absint( $meta['updated_by'] ?? 0 ),
    ];
}

/**
 * Obtiene términos de área de un proyecto.
 *
 * @param int $post_id Proyecto actual.
 * @return WP_Term[]
 */
function wpssb_get_project_area_terms( $post_id ) {
    $terms = get_the_terms( $post_id, WPSSB_PROJECT_AREA_TAX );

    if ( is_wp_error( $terms ) || empty( $terms ) ) {
        return [];
    }

    return array_values(
        array_filter(
            $terms,
            static function ( $term ) {
                return $term instanceof WP_Term;
            }
        )
    );
}

/**
 * Renderiza una tarjeta de proyecto reutilizable.
 *
 * @param int   $post_id  Proyecto actual.
 * @param array $settings Ajustes visuales.
 * @return string
 */
function wpssb_render_project_card( $post_id, $settings = [] ) {
    $post_id = absint( $post_id );
    if ( ! $post_id ) {
        return '';
    }

    $settings = wp_parse_args(
        $settings,
        [
            'show_image'         => true,
            'show_excerpt'       => true,
            'show_area'          => true,
            'show_collaborators' => true,
        ]
    );

    $title         = get_the_title( $post_id );
    $permalink     = get_permalink( $post_id );
    $excerpt       = get_the_excerpt( $post_id );
    $area_terms    = wpssb_get_project_area_terms( $post_id );
    $collaborators = wpssb_get_project_collaborators( $post_id );

    $output = '<article class="pd-proyectos-relacionados__item pd-proyecto-card">';

    if ( ! empty( $settings['show_image'] ) && has_post_thumbnail( $post_id ) ) {
        $output .= '<a class="pd-proyecto-card__image" href="' . esc_url( $permalink ) . '">';
        $output .= get_the_post_thumbnail( $post_id, 'medium_large' );
        $output .= '</a>';
    }

    if ( ! empty( $settings['show_area'] ) && ! empty( $area_terms ) ) {
        $output .= '<p class="pd-proyecto-card__areas">';
        $output .= esc_html( implode( ' · ', wp_list_pluck( $area_terms, 'name' ) ) );
        $output .= '</p>';
    }

    $output .= '<h3 class="pd-proyecto-card__title"><a href="' . esc_url( $permalink ) . '">' . esc_html( $title ) . '</a></h3>';

    if ( ! empty( $settings['show_excerpt'] ) && $excerpt ) {
        $output .= '<p class="pd-proyecto-card__excerpt">' . esc_html( wp_trim_words( $excerpt, 28 ) ) . '</p>';
    }

    if ( ! empty( $settings['show_collaborators'] ) && ! empty( $collaborators ) ) {
        $output .= '<p class="pd-proyecto-card__collaborators">';
        $output .= esc_html( implode( ' · ', wp_list_pluck( $collaborators, 'display_name' ) ) );
        $output .= '</p>';
    }

    $output .= '</article>';

    return $output;
}

/**
 * Resuelve el slug de área para el directorio de proyectos según el contexto actual.
 *
 * Si el bloque no recibe `areaSlug`, intenta usar el término actual en archivos de
 * la taxonomía `area_proyecto` para que el tema pueda reutilizar el mismo bloque
 * en plantillas FSE sin duplicar queries manuales.
 *
 * @param string $area_slug Slug explícito del bloque.
 * @return string
 */
function wpssb_resolve_project_directory_area_slug( $area_slug ) {
    $area_slug = sanitize_title( (string) $area_slug );

    if ( '' !== $area_slug ) {
        return $area_slug;
    }

    if ( is_tax( WPSSB_PROJECT_AREA_TAX ) ) {
        $term = get_queried_object();

        if ( $term instanceof WP_Term && WPSSB_PROJECT_AREA_TAX === $term->taxonomy ) {
            return sanitize_title( $term->slug );
        }
    }

    return '';
}

/**
 * Renderiza un directorio reusable de proyectos.
 *
 * @param array $settings Ajustes del directorio.
 * @return string
 */
function wpssb_render_project_directory_markup( $settings = [] ) {
    $settings = wp_parse_args(
        $settings,
        [
            'area_slug'          => '',
            'posts_per_page'     => 9,
            'show_image'         => true,
            'show_excerpt'       => true,
            'show_area'          => true,
            'show_collaborators' => true,
            'only_current_user'  => false,
            'user_id'            => 0,
            'empty_message'      => __( 'No hay proyectos publicados todavía.', 'wp-song-study-blocks' ),
            'login_message'      => __( 'Inicia sesión para ver tus proyectos relacionados.', 'wp-song-study-blocks' ),
        ]
    );

    $query_args = [
        'post_type'      => WPSSB_PROJECT_POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => max( 1, absint( $settings['posts_per_page'] ) ),
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];

    $area_slug = wpssb_resolve_project_directory_area_slug( $settings['area_slug'] );
    if ( $area_slug ) {
        $query_args['tax_query'] = [
            [
                'taxonomy' => WPSSB_PROJECT_AREA_TAX,
                'field'    => 'slug',
                'terms'    => $area_slug,
            ],
        ];
    }

    $membership_user_id = absint( $settings['user_id'] );

    if ( ! $membership_user_id && ! empty( $settings['only_current_user'] ) ) {
        $membership_user_id = get_current_user_id();
    }

    if ( $membership_user_id > 0 ) {
        if ( ! is_user_logged_in() && ! empty( $settings['only_current_user'] ) ) {
            return '<p>' . esc_html( $settings['login_message'] ) . '</p>';
        }

        $project_ids = wpssb_get_user_project_ids( $membership_user_id );

        if ( empty( $project_ids ) ) {
            return '<p>' . esc_html( $settings['empty_message'] ) . '</p>';
        }

        $query_args['post__in'] = $project_ids;
        $query_args['orderby']  = 'post__in';
    }

    $query = new WP_Query( $query_args );

    if ( ! $query->have_posts() ) {
        return '<p>' . esc_html( $settings['empty_message'] ) . '</p>';
    }

    $output = '<div class="pd-proyectos-grid pd-proyectos-grid--directory">';

    while ( $query->have_posts() ) {
        $query->the_post();
        $output .= wpssb_render_project_card(
            get_the_ID(),
            [
                'show_image'         => ! empty( $settings['show_image'] ),
                'show_excerpt'       => ! empty( $settings['show_excerpt'] ),
                'show_area'          => ! empty( $settings['show_area'] ),
                'show_collaborators' => ! empty( $settings['show_collaborators'] ),
            ]
        );
    }

    wp_reset_postdata();

    $output .= '</div>';

    return $output;
}

/**
 * Devuelve IDs de proyectos donde participa un usuario.
 *
 * @param int $user_id Usuario a consultar.
 * @return int[]
 */
function wpssb_get_user_project_ids( $user_id ) {
    $user_id = absint( $user_id );
    if ( $user_id <= 0 ) {
        return [];
    }

    $query = new WP_Query(
        [
            'post_type'      => WPSSB_PROJECT_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]
    );

    if ( empty( $query->posts ) ) {
        return [];
    }

    $project_ids = [];

    foreach ( (array) $query->posts as $project_id ) {
        $project_id = (int) $project_id;

        if ( $project_id <= 0 ) {
            continue;
        }

        $collaborators = wpssb_sanitize_id_list( get_post_meta( $project_id, 'pd_proyecto_colaboradores', true ) );

        if ( in_array( $user_id, $collaborators, true ) ) {
            $project_ids[] = $project_id;
        }
    }

    return $project_ids;
}

/**
 * Indica si un usuario pertenece a alguno de los proyectos dados.
 *
 * @param int   $user_id     Usuario a consultar.
 * @param int[] $project_ids Proyectos objetivo.
 * @return bool
 */
function wpssb_user_belongs_to_projects( $user_id, $project_ids ) {
    $user_id = absint( $user_id );
    if ( $user_id <= 0 ) {
        return false;
    }

    $project_ids = array_filter( array_map( 'absint', (array) $project_ids ) );
    if ( empty( $project_ids ) ) {
        return false;
    }

    $user_project_ids = wpssb_get_user_project_ids( $user_id );
    return ! empty( array_intersect( $project_ids, $user_project_ids ) );
}

/**
 * Devuelve IDs de proyectos musicales donde el usuario sí puede usar el planificador.
 *
 * @param int $user_id Usuario actual.
 * @return int[]
 */
function wpssb_get_user_rehearsal_project_ids( $user_id ) {
    $user_id = absint( $user_id );

    if ( $user_id <= 0 ) {
        return [];
    }

    $project_ids = get_posts(
        [
            'post_type'      => WPSSB_PROJECT_POST_TYPE,
            'post_status'    => wpssb_get_rehearsal_project_post_statuses(),
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'orderby'        => 'title',
            'order'          => 'ASC',
            'no_found_rows'  => true,
        ]
    );

    if ( empty( $project_ids ) ) {
        return [];
    }

    return array_values(
        array_filter(
            array_map(
                static function ( $project_id ) use ( $user_id ) {
                    $project_id = absint( $project_id );

                    if ( $project_id <= 0 || ! wpssb_user_is_project_rehearsal_member( $project_id, $user_id ) ) {
                        return 0;
                    }

                    return $project_id;
                },
                $project_ids
            )
        )
    );
}

/**
 * Render común del listado de colaboradores del proyecto.
 *
 * @param int   $post_id     Proyecto actual.
 * @param array $settings    Ajustes visuales.
 * @return string
 */
function wpssb_render_project_collaborators_markup( $post_id, $settings = [] ) {
    $users = wpssb_get_project_collaborators( $post_id );

    if ( empty( $users ) ) {
        return '<p>' . esc_html__( 'Este proyecto no tiene colaboradores asignados todavía.', 'wp-song-study-blocks' ) . '</p>';
    }

    $output = '<div class="pd-colaboradores-grid">';

    foreach ( $users as $user ) {
        $output .= wpssb_render_collaborator_card( $user, $settings );
    }

    $output .= '</div>';

    return $output;
}

/**
 * Shortcode de todos los colaboradores.
 *
 * @return string
 */
function wpssb_shortcode_collaborators() {
    $users = wpssb_get_collaborators();

    if ( empty( $users ) ) {
        return '<p>' . esc_html__( 'Aún no hay colaboradores registrados.', 'wp-song-study-blocks' ) . '</p>';
    }

    $output = '<div class="pd-colaboradores-grid">';

    foreach ( $users as $user ) {
        $output .= wpssb_render_collaborator_card( $user );
    }

    $output .= '</div>';

    return $output;
}
add_shortcode( 'pd_colaboradores', 'wpssb_shortcode_collaborators' );

/**
 * Shortcode de colaboradores del proyecto actual.
 *
 * @return string
 */
function wpssb_shortcode_project_collaborators() {
    $post_id = wpssb_resolve_block_project_post_id();
    if ( ! $post_id ) {
        return '';
    }

    return wpssb_render_project_collaborators_markup( $post_id );
}
add_shortcode( 'pd_proyecto_colaboradores', 'wpssb_shortcode_project_collaborators' );

/**
 * Resuelve el post actual para bloques o shortcodes de proyecto.
 *
 * @param WP_Block|null $block Instancia del bloque.
 * @return int
 */
function wpssb_resolve_block_project_post_id( $block = null ) {
    if ( $block instanceof WP_Block && ! empty( $block->context['postId'] ) ) {
        return absint( $block->context['postId'] );
    }

    return absint( get_the_ID() );
}

/**
 * Renderiza un listado de links para presskits.
 *
 * @param string $links_raw  Texto con links separados por línea.
 * @param string $class_name Clase CSS del listado.
 * @return string
 */
function wpssb_render_presskit_links_markup( $links_raw, $class_name ) {
    if ( ! $links_raw ) {
        return '';
    }

    $lines = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $links_raw ) ) );
    if ( empty( $lines ) ) {
        return '';
    }

    $output = '<ul class="' . esc_attr( $class_name ) . '">';

    foreach ( $lines as $line ) {
        $url = esc_url( $line );
        if ( $url ) {
            $output .= '<li><a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '</a></li>';
        } else {
            $output .= '<li>' . esc_html( $line ) . '</li>';
        }
    }

    $output .= '</ul>';

    return $output;
}

/**
 * Render común de la galería del proyecto.
 *
 * @param int $post_id Proyecto actual.
 * @return string
 */
function wpssb_render_project_gallery_markup( $post_id ) {
    $post_id = absint( $post_id );
    if ( ! $post_id ) {
        return '';
    }

    $ids = wpssb_sanitize_id_list( get_post_meta( $post_id, 'pd_proyecto_galeria', true ) );

    if ( empty( $ids ) ) {
        return '<p>' . esc_html__( 'Aún no hay imágenes en la galería del proyecto.', 'wp-song-study-blocks' ) . '</p>';
    }

    $output = '<div class="pd-proyecto-galeria">';

    foreach ( $ids as $id ) {
        $image = wp_get_attachment_image( $id, 'medium_large' );
        if ( $image ) {
            $output .= '<figure class="pd-proyecto-galeria__item">' . $image . '</figure>';
        }
    }

    $output .= '</div>';

    return $output;
}

/**
 * Render común del contacto del proyecto.
 *
 * @param int $post_id Proyecto actual.
 * @return string
 */
function wpssb_render_project_contact_markup( $post_id ) {
    $post_id = absint( $post_id );
    if ( ! $post_id ) {
        return '';
    }

    $contact = get_post_meta( $post_id, 'pd_proyecto_contacto', true );

    if ( ! $contact ) {
        return '<p>' . esc_html__( 'No hay información de contacto definida.', 'wp-song-study-blocks' ) . '</p>';
    }

    return '<div class="pd-proyecto-contacto">' . wpautop( wp_kses_post( $contact ) ) . '</div>';
}

/**
 * Render común del presskit del proyecto.
 *
 * @param int $post_id Proyecto actual.
 * @return string
 */
function wpssb_render_project_presskit_markup( $post_id ) {
    $post_id = absint( $post_id );
    if ( ! $post_id ) {
        return '';
    }

    $tagline  = get_post_meta( $post_id, 'pd_proyecto_tagline', true );
    $presskit = get_post_meta( $post_id, 'pd_proyecto_presskit', true );
    $links    = get_post_meta( $post_id, 'pd_proyecto_links', true );
    $excerpt  = get_post_field( 'post_excerpt', $post_id );

    if ( ! $tagline && ! $presskit && ! $links && ! $excerpt ) {
        return '<p>' . esc_html__( 'No hay información de presskit definida.', 'wp-song-study-blocks' ) . '</p>';
    }

    $output = '<div class="pd-proyecto-presskit">';

    if ( $tagline ) {
        $output .= '<p class="pd-proyecto-presskit__tagline">' . esc_html( $tagline ) . '</p>';
    }

    if ( $presskit ) {
        $output .= '<div class="pd-proyecto-presskit__text">' . wpautop( wp_kses_post( $presskit ) ) . '</div>';
    } elseif ( $excerpt ) {
        $output .= '<div class="pd-proyecto-presskit__text">' . wpautop( esc_html( $excerpt ) ) . '</div>';
    }

    $output .= wpssb_render_presskit_links_markup( $links, 'pd-proyecto-presskit__links' );
    $output .= '</div>';

    return $output;
}

/**
 * Shortcode de galería de proyecto.
 *
 * @return string
 */
function wpssb_shortcode_project_gallery() {
    return wpssb_render_project_gallery_markup( wpssb_resolve_block_project_post_id() );
}
add_shortcode( 'pd_proyecto_galeria', 'wpssb_shortcode_project_gallery' );

/**
 * Shortcode de contacto del proyecto.
 *
 * @return string
 */
function wpssb_shortcode_project_contact() {
    return wpssb_render_project_contact_markup( wpssb_resolve_block_project_post_id() );
}
add_shortcode( 'pd_proyecto_contacto', 'wpssb_shortcode_project_contact' );

/**
 * Shortcode de presskit del proyecto.
 *
 * @return string
 */
function wpssb_shortcode_project_presskit() {
    return wpssb_render_project_presskit_markup( wpssb_resolve_block_project_post_id() );
}
add_shortcode( 'pd_proyecto_presskit', 'wpssb_shortcode_project_presskit' );

/**
 * Resuelve un usuario objetivo para shortcodes de autor.
 *
 * @param array $atts Shortcode attrs.
 * @return int
 */
function wpssb_resolve_collaborator_user_id( $atts = [] ) {
    $atts    = shortcode_atts( [ 'id' => 0 ], (array) $atts );
    return wpssb_resolve_block_collaborator_user_id(
        [
            'userId' => isset( $atts['id'] ) ? $atts['id'] : 0,
        ]
    );
}

/**
 * Resuelve el usuario objetivo para bloques o shortcodes de colaborador.
 *
 * @param array         $attributes Atributos del bloque.
 * @param WP_Block|null $block      Instancia del bloque.
 * @return int
 */
function wpssb_resolve_block_collaborator_user_id( $attributes = [], $block = null ) {
    $user_id = isset( $attributes['userId'] ) ? absint( $attributes['userId'] ) : 0;

    if ( ! $user_id && is_page() ) {
        $presskit_page_id = (int) get_queried_object_id();

        if ( $presskit_page_id > 0 && 'presskit' === get_page_template_slug( $presskit_page_id ) ) {
            $user_id = wpssb_resolve_presskit_page_target_user_id( $presskit_page_id );
        }
    }

    if ( ! $user_id ) {
        $queried_object = get_queried_object();
        if ( $queried_object instanceof WP_User ) {
            $user_id = (int) $queried_object->ID;
        }
    }

    if ( ! $user_id && get_query_var( 'author' ) ) {
        $user_id = (int) get_query_var( 'author' );
    }

    if ( ! $user_id && $block instanceof WP_Block && ! empty( $block->context['postId'] ) ) {
        $post_id = (int) $block->context['postId'];
        $user_id = wpssb_get_explicit_collaborator_target_user_id( $post_id );

        if ( ! $user_id ) {
            $user_id = (int) get_post_field( 'post_author', $post_id );
        }
    }

    if ( ! $user_id ) {
        $post_id = get_the_ID();
        if ( $post_id ) {
            $user_id = wpssb_get_explicit_collaborator_target_user_id( $post_id );

            if ( ! $user_id ) {
                $user_id = (int) get_post_field( 'post_author', $post_id );
            }
        }
    }

    return absint( $user_id );
}

/**
 * Render común del presskit del colaborador.
 *
 * @param int $user_id Usuario objetivo.
 * @return string
 */
function wpssb_render_collaborator_presskit_markup( $user_id ) {
    $user_id = absint( $user_id );
    if ( ! $user_id ) {
        return '';
    }

    $user = get_user_by( 'id', $user_id );
    if ( ! $user instanceof WP_User ) {
        return '';
    }

    $tagline      = get_user_meta( $user_id, 'pd_colaborador_tagline', true );
    $presskit     = get_user_meta( $user_id, 'pd_colaborador_presskit', true );
    $links        = get_user_meta( $user_id, 'pd_colaborador_links', true );
    $fallback_url = $user->user_url ? esc_url_raw( $user->user_url ) : '';

    $output  = '<section class="pd-colaborador-presskit">';
    $output .= '<div class="pd-colaborador-presskit__header">';
    $output .= get_avatar( $user_id, 120, '', $user->display_name, [ 'class' => 'pd-colaborador-presskit__avatar' ] );
    $output .= '<div>';
    $output .= '<h1 class="pd-colaborador-presskit__name">' . esc_html( $user->display_name ) . '</h1>';
    if ( $tagline ) {
        $output .= '<p class="pd-colaborador-presskit__tagline">' . esc_html( $tagline ) . '</p>';
    }
    $output .= '</div></div>';

    if ( $presskit ) {
        $output .= '<div class="pd-colaborador-presskit__text">' . wpautop( wp_kses_post( $presskit ) ) . '</div>';
    } elseif ( $user->description ) {
        $output .= '<div class="pd-colaborador-presskit__text">' . wpautop( esc_html( $user->description ) ) . '</div>';
    }

    $output .= wpssb_render_presskit_links_markup( $links ?: $fallback_url, 'pd-colaborador-presskit__links' );
    $output .= '</section>';

    return $output;
}

/**
 * Render común de la galería del colaborador.
 *
 * @param int $user_id Usuario objetivo.
 * @return string
 */
function wpssb_render_collaborator_gallery_markup( $user_id ) {
    $user_id = absint( $user_id );
    if ( ! $user_id ) {
        return '';
    }

    $ids = wpssb_sanitize_id_list( get_user_meta( $user_id, 'pd_colaborador_galeria', true ) );
    if ( empty( $ids ) ) {
        return '<p>' . esc_html__( 'Aún no hay imágenes en la galería del colaborador.', 'wp-song-study-blocks' ) . '</p>';
    }

    $output = '<div class="pd-proyecto-galeria">';

    foreach ( $ids as $id ) {
        $image = wp_get_attachment_image( $id, 'medium_large' );
        if ( $image ) {
            $output .= '<figure class="pd-proyecto-galeria__item">' . $image . '</figure>';
        }
    }

    $output .= '</div>';

    return $output;
}

/**
 * Render común del contacto del colaborador.
 *
 * @param int $user_id Usuario objetivo.
 * @return string
 */
function wpssb_render_collaborator_contact_markup( $user_id ) {
    $user_id = absint( $user_id );
    if ( ! $user_id ) {
        return '';
    }

    $contact = get_user_meta( $user_id, 'pd_colaborador_contacto', true );
    if ( ! $contact ) {
        return '<p>' . esc_html__( 'No hay información de contacto definida.', 'wp-song-study-blocks' ) . '</p>';
    }

    return '<div class="pd-proyecto-contacto">' . wpautop( wp_kses_post( $contact ) ) . '</div>';
}

/**
 * Render común de proyectos relacionados a un colaborador.
 *
 * @param int $user_id        Usuario objetivo.
 * @param int $posts_per_page Número máximo de proyectos.
 * @return string
 */
function wpssb_render_collaborator_projects_markup( $user_id, $posts_per_page = 6 ) {
    $user_id        = absint( $user_id );
    $posts_per_page = max( 1, absint( $posts_per_page ) );

    if ( ! $user_id ) {
        return '';
    }

    $project_ids = array_slice( wpssb_get_user_project_ids( $user_id ), 0, $posts_per_page );

    if ( empty( $project_ids ) ) {
        return '<p>' . esc_html__( 'No hay proyectos asociados todavía.', 'wp-song-study-blocks' ) . '</p>';
    }

    $query = new WP_Query(
        [
            'post_type'      => WPSSB_PROJECT_POST_TYPE,
            'posts_per_page' => $posts_per_page,
            'orderby'        => 'post__in',
            'post__in'       => $project_ids,
        ]
    );

    if ( ! $query->have_posts() ) {
        return '<p>' . esc_html__( 'No hay proyectos asociados todavía.', 'wp-song-study-blocks' ) . '</p>';
    }

    $output = '<div class="pd-proyectos-relacionados">';
    while ( $query->have_posts() ) {
        $query->the_post();
        $output .= '<article class="pd-proyectos-relacionados__item">';
        if ( has_post_thumbnail() ) {
            $output .= '<a href="' . esc_url( get_permalink() ) . '">' . get_the_post_thumbnail( get_the_ID(), 'medium_large' ) . '</a>';
        }
        $output .= '<h3><a href="' . esc_url( get_permalink() ) . '">' . esc_html( get_the_title() ) . '</a></h3>';
        $output .= '</article>';
    }
    wp_reset_postdata();
    $output .= '</div>';

    return $output;
}

/**
 * Shortcode de presskit del colaborador.
 *
 * @param array $atts Atributos del shortcode.
 * @return string
 */
function wpssb_shortcode_collaborator_presskit( $atts = [] ) {
    return wpssb_render_collaborator_presskit_markup( wpssb_resolve_collaborator_user_id( $atts ) );
}
add_shortcode( 'pd_colaborador_presskit', 'wpssb_shortcode_collaborator_presskit' );

/**
 * Shortcode de galería del colaborador.
 *
 * @param array $atts Atributos del shortcode.
 * @return string
 */
function wpssb_shortcode_collaborator_gallery( $atts = [] ) {
    return wpssb_render_collaborator_gallery_markup( wpssb_resolve_collaborator_user_id( $atts ) );
}
add_shortcode( 'pd_colaborador_galeria', 'wpssb_shortcode_collaborator_gallery' );

/**
 * Shortcode de contacto del colaborador.
 *
 * @param array $atts Atributos del shortcode.
 * @return string
 */
function wpssb_shortcode_collaborator_contact( $atts = [] ) {
    return wpssb_render_collaborator_contact_markup( wpssb_resolve_collaborator_user_id( $atts ) );
}
add_shortcode( 'pd_colaborador_contacto', 'wpssb_shortcode_collaborator_contact' );

/**
 * Shortcode de proyectos relacionados a un colaborador.
 *
 * @param array $atts Atributos del shortcode.
 * @return string
 */
function wpssb_shortcode_collaborator_projects( $atts = [] ) {
    return wpssb_render_collaborator_projects_markup( wpssb_resolve_collaborator_user_id( $atts ), 6 );
}
add_shortcode( 'pd_colaborador_proyectos', 'wpssb_shortcode_collaborator_projects' );

/**
 * Render callback del bloque de colaboradores por proyecto.
 *
 * @param array    $attributes Atributos del bloque.
 * @param string   $content    Contenido del bloque.
 * @param WP_Block $block      Instancia del bloque.
 * @return string
 */
function wpssb_render_block_project_collaborators( $attributes = [], $content = '', $block = null ) {
    $post_id = wpssb_resolve_block_project_post_id( $block );

    if ( ! $post_id ) {
        return '';
    }

    $settings = [
        'show_avatar' => ! isset( $attributes['showAvatar'] ) || (bool) $attributes['showAvatar'],
        'show_bio'    => ! isset( $attributes['showBio'] ) || (bool) $attributes['showBio'],
        'show_link'   => ! isset( $attributes['showLink'] ) || (bool) $attributes['showLink'],
    ];

    return wpssb_render_project_collaborators_markup( $post_id, $settings );
}

/**
 * Render callback del bloque de presskit del proyecto.
 *
 * @param array    $attributes Atributos del bloque.
 * @param string   $content    Contenido interno.
 * @param WP_Block $block      Instancia del bloque.
 * @return string
 */
function wpssb_render_block_project_presskit( $attributes = [], $content = '', $block = null ) {
    return wpssb_render_project_presskit_markup( wpssb_resolve_block_project_post_id( $block ) );
}

/**
 * Render callback del bloque de galería del proyecto.
 *
 * @param array    $attributes Atributos del bloque.
 * @param string   $content    Contenido interno.
 * @param WP_Block $block      Instancia del bloque.
 * @return string
 */
function wpssb_render_block_project_gallery( $attributes = [], $content = '', $block = null ) {
    return wpssb_render_project_gallery_markup( wpssb_resolve_block_project_post_id( $block ) );
}

/**
 * Render callback del bloque de contacto del proyecto.
 *
 * @param array    $attributes Atributos del bloque.
 * @param string   $content    Contenido interno.
 * @param WP_Block $block      Instancia del bloque.
 * @return string
 */
function wpssb_render_block_project_contact( $attributes = [], $content = '', $block = null ) {
    return wpssb_render_project_contact_markup( wpssb_resolve_block_project_post_id( $block ) );
}

/**
 * Render callback del bloque de presskit del colaborador.
 *
 * @param array    $attributes Atributos del bloque.
 * @param string   $content    Contenido interno.
 * @param WP_Block $block      Instancia del bloque.
 * @return string
 */
function wpssb_render_block_collaborator_presskit( $attributes = [], $content = '', $block = null ) {
    return wpssb_render_collaborator_presskit_markup( wpssb_resolve_block_collaborator_user_id( $attributes, $block ) );
}

/**
 * Render callback del bloque de galería del colaborador.
 *
 * @param array    $attributes Atributos del bloque.
 * @param string   $content    Contenido interno.
 * @param WP_Block $block      Instancia del bloque.
 * @return string
 */
function wpssb_render_block_collaborator_gallery( $attributes = [], $content = '', $block = null ) {
    return wpssb_render_collaborator_gallery_markup( wpssb_resolve_block_collaborator_user_id( $attributes, $block ) );
}

/**
 * Render callback del bloque de contacto del colaborador.
 *
 * @param array    $attributes Atributos del bloque.
 * @param string   $content    Contenido interno.
 * @param WP_Block $block      Instancia del bloque.
 * @return string
 */
function wpssb_render_block_collaborator_contact( $attributes = [], $content = '', $block = null ) {
    return wpssb_render_collaborator_contact_markup( wpssb_resolve_block_collaborator_user_id( $attributes, $block ) );
}

/**
 * Render callback del bloque de proyectos del colaborador.
 *
 * @param array    $attributes Atributos del bloque.
 * @param string   $content    Contenido interno.
 * @param WP_Block $block      Instancia del bloque.
 * @return string
 */
function wpssb_render_block_collaborator_projects( $attributes = [], $content = '', $block = null ) {
    $posts_per_page = isset( $attributes['postsPerPage'] ) ? max( 1, absint( $attributes['postsPerPage'] ) ) : 6;

    return wpssb_render_collaborator_projects_markup(
        wpssb_resolve_block_collaborator_user_id( $attributes, $block ),
        $posts_per_page
    );
}

/**
 * Render callback del bloque de directorio de proyectos.
 *
 * @param array    $attributes Atributos del bloque.
 * @param string   $content    Contenido interno.
 * @param WP_Block $block      Instancia del bloque.
 * @return string
 */
function wpssb_render_block_project_directory( $attributes = [], $content = '', $block = null ) {
    return wpssb_render_project_directory_markup(
        [
            'area_slug'          => isset( $attributes['areaSlug'] ) ? sanitize_title( $attributes['areaSlug'] ) : '',
            'posts_per_page'     => isset( $attributes['postsPerPage'] ) ? max( 1, absint( $attributes['postsPerPage'] ) ) : 9,
            'show_image'         => ! isset( $attributes['showImage'] ) || (bool) $attributes['showImage'],
            'show_excerpt'       => ! isset( $attributes['showExcerpt'] ) || (bool) $attributes['showExcerpt'],
            'show_area'          => ! isset( $attributes['showArea'] ) || (bool) $attributes['showArea'],
            'show_collaborators' => ! empty( $attributes['showCollaborators'] ),
            'only_current_user'  => ! empty( $attributes['onlyCurrentUser'] ),
            'empty_message'      => isset( $attributes['emptyMessage'] ) ? sanitize_text_field( $attributes['emptyMessage'] ) : __( 'No hay proyectos publicados todavía.', 'wp-song-study-blocks' ),
            'login_message'      => isset( $attributes['loginMessage'] ) ? sanitize_text_field( $attributes['loginMessage'] ) : __( 'Inicia sesión para ver tus proyectos relacionados.', 'wp-song-study-blocks' ),
        ]
    );
}

/**
 * Renderiza el espacio frontend de pertenencia del usuario actual.
 *
 * @param array $settings Ajustes visuales.
 * @return string
 */
function wpssb_render_current_membership_markup( $settings = [] ) {
    $settings = wp_parse_args(
        $settings,
        [
            'show_projects'   => true,
            'show_preview'    => true,
            'show_admin_link' => true,
            'target_user_id'  => 0,
            'login_message'   => __( 'Mi pertenencia es un espacio privado para colaboradores musicales, colaboradores y administradores. Inicia sesion con una cuenta autorizada para editar tu perfil, mantener tu presskit y revisar tus proyectos.', 'wp-song-study-blocks' ),
        ]
    );

    $current_url = get_permalink() ? get_permalink() : home_url( '/' );

    if ( ! is_user_logged_in() || ! wpssb_user_can_access_private_music_area() ) {
        if ( function_exists( 'pd_render_music_access_gate_panel' ) ) {
            return pd_render_music_access_gate_panel(
                [
                    'context'     => 'membership',
                    'redirect_to' => $current_url,
                    'intro'       => $settings['login_message'],
                ]
            );
        }

        if ( ! is_user_logged_in() ) {
            $login_url = wp_login_url( $current_url );

            return '<div class="pd-membership-shell"><p>' . esc_html( $settings['login_message'] ) . '</p><p><a class="wp-block-button__link wp-element-button" href="' . esc_url( $login_url ) . '">' . esc_html__( 'Iniciar sesion', 'wp-song-study-blocks' ) . '</a></p></div>';
        }

        $switch_url = wp_logout_url( wp_login_url( $current_url ) );

        return '<div class="pd-membership-shell"><p>' . esc_html__( 'Tu cuenta inició sesion, pero todavia no tiene permisos para entrar a Mi pertenencia. Usa una cuenta con rol de colaborador musical, colaborador o administrador.', 'wp-song-study-blocks' ) . '</p><p><a class="wp-block-button__link wp-element-button" href="' . esc_url( $switch_url ) . '">' . esc_html__( 'Usar otra cuenta', 'wp-song-study-blocks' ) . '</a></p></div>';
    }

    $viewer_id = get_current_user_id();
    $user_id   = wpssb_resolve_membership_target_user_id( $settings );
    $user    = get_user_by( 'id', $user_id );

    if ( ! $user instanceof WP_User ) {
        return '';
    }

    $can_manage_target  = wpssb_current_user_can_manage_presskit_user( $user_id );
    $can_switch_targets = current_user_can( 'manage_options' );
    $is_admin_override  = $can_switch_targets && $viewer_id > 0 && $viewer_id !== $user_id;
    $manageable_users   = $can_switch_targets ? wpssb_get_manageable_membership_users() : [];
    $feedback = wpssb_get_membership_feedback();
    $action   = admin_url( 'admin-post.php' );
    $presskit_post_id = $can_manage_target ? wpssb_ensure_collaborator_presskit_post( $user_id ) : wpssb_get_collaborator_presskit_post_id( $user_id );
    $public_presskit  = wpssb_get_collaborator_public_url( $user_id );
    $edit_presskit    = $presskit_post_id ? wpssb_get_frontend_membership_url( $user_id, true ) : '';
    $tagline          = (string) get_user_meta( $user_id, 'pd_colaborador_tagline', true );
    $short_bio        = (string) $user->description;
    $portfolio_url    = (string) $user->user_url;
    $project_ids      = wpssb_get_user_project_ids( $user_id );
    $project_count    = is_array( $project_ids ) ? count( $project_ids ) : 0;
    $requested_tab    = isset( $_GET['membership_tab'] ) ? sanitize_key( wp_unslash( $_GET['membership_tab'] ) ) : '';
    $current_tab      = in_array( $requested_tab, [ 'profile', 'presskit', 'google' ], true ) ? $requested_tab : '';

    if ( '' === $current_tab ) {
        $current_tab = ( isset( $_GET['membership_view'] ) && 'editor' === sanitize_key( wp_unslash( $_GET['membership_view'] ) ) ) ? 'presskit' : 'profile';
    }

    $profile_redirect = esc_url(
        add_query_arg(
            [
                'membership_user' => $user_id,
                'membership_tab'  => $current_tab,
            ],
            $current_url
        )
    );
    $google_return_url = add_query_arg(
        [
            'membership_user' => $user_id,
            'membership_tab'  => 'google',
        ],
        $current_url
    );
    $can_connect_google = $viewer_id > 0 && $viewer_id === $user_id;

    wpssb_enqueue_frontend_membership_assets();

    $output  = '<section class="pd-membership-shell">';
    $output .= '<header class="pd-membership-shell__header">';
    $output .= '<div class="pd-membership-shell__identity">';
    $output .= get_avatar( $user_id, 96, '', $user->display_name, [ 'class' => 'pd-membership-shell__avatar' ] );
    $output .= '<div>';
    $output .= '<p class="pd-membership-shell__eyebrow">' . esc_html( $is_admin_override ? __( 'Administración de pertenencia', 'wp-song-study-blocks' ) : __( 'Mi pertenencia digital', 'wp-song-study-blocks' ) ) . '</p>';
    $output .= '<h1 class="pd-membership-shell__title">' . esc_html( $user->display_name ) . '</h1>';
    $output .= '<p class="pd-membership-shell__meta">' . esc_html( $user->user_email ) . '</p>';
    $output .= '</div></div>';
    $output .= '<div class="pd-membership-shell__actions">';
    $output .= '<a class="wp-block-button__link wp-element-button is-style-outline" href="' . esc_url( $public_presskit ) . '">' . esc_html__( 'Ver perfil público', 'wp-song-study-blocks' ) . '</a>';
    if ( ! empty( $settings['show_admin_link'] ) ) {
        $output .= '<a class="wp-block-button__link wp-element-button is-style-outline" href="' . esc_url( admin_url( 'profile.php' ) ) . '">' . esc_html__( 'Abrir perfil de WordPress', 'wp-song-study-blocks' ) . '</a>';
    }
    $output .= '</div></header>';

    if ( $can_switch_targets && ! empty( $manageable_users ) ) {
        $output .= '<div class="pd-membership-panel pd-membership-panel--switcher">';
        $output .= '<form class="pd-membership-switcher" method="get" action="' . esc_url( $current_url ) . '">';
        $output .= '<label><span>' . esc_html__( 'Administrar pertenencia de', 'wp-song-study-blocks' ) . '</span>';
        $output .= '<select name="membership_user">';
        foreach ( $manageable_users as $manageable_user ) {
            if ( ! $manageable_user instanceof WP_User ) {
                continue;
            }
            $selected = selected( $user_id, (int) $manageable_user->ID, false );
            $output .= '<option value="' . (int) $manageable_user->ID . '" ' . $selected . '>' . esc_html( $manageable_user->display_name . ' · ' . $manageable_user->user_email ) . '</option>';
        }
        $output .= '</select></label>';
        $output .= '<button type="submit" class="wp-block-button__link wp-element-button is-style-outline">' . esc_html__( 'Abrir', 'wp-song-study-blocks' ) . '</button>';
        $output .= '</form>';
        if ( $is_admin_override ) {
            $output .= '<p class="pd-membership-form__hint">' . esc_html__( 'Estás editando este perfil con permisos de administrador.', 'wp-song-study-blocks' ) . '</p>';
        }
        $output .= '</div>';
    }

    if ( is_array( $feedback ) && ! empty( $feedback['message'] ) ) {
        $class   = 'success' === ( $feedback['type'] ?? '' ) ? 'is-success' : 'is-error';
        $output .= '<p class="pd-membership-feedback ' . esc_attr( $class ) . '">' . esc_html( $feedback['message'] ) . '</p>';
    }

    $output .= '<nav class="pd-membership-tabs" aria-label="' . esc_attr__( 'Secciones de pertenencia digital', 'wp-song-study-blocks' ) . '" data-membership-tabs>';
    $output .= '<button type="button" class="pd-membership-tabs__tab' . ( 'profile' === $current_tab ? ' is-active' : '' ) . '" role="tab" aria-selected="' . ( 'profile' === $current_tab ? 'true' : 'false' ) . '" aria-controls="pd-membership-panel-profile" id="pd-membership-tab-profile" data-membership-tab="profile">' . esc_html__( 'Perfil base', 'wp-song-study-blocks' ) . '</button>';
    $output .= '<button type="button" class="pd-membership-tabs__tab' . ( 'presskit' === $current_tab ? ' is-active' : '' ) . '" role="tab" aria-selected="' . ( 'presskit' === $current_tab ? 'true' : 'false' ) . '" aria-controls="pd-membership-panel-presskit" id="pd-membership-tab-presskit" data-membership-tab="presskit">' . esc_html__( 'Presskit público', 'wp-song-study-blocks' ) . '</button>';
    $output .= '<button type="button" class="pd-membership-tabs__tab' . ( 'google' === $current_tab ? ' is-active' : '' ) . '" role="tab" aria-selected="' . ( 'google' === $current_tab ? 'true' : 'false' ) . '" aria-controls="pd-membership-panel-google" id="pd-membership-tab-google" data-membership-tab="google">' . esc_html__( 'Google', 'wp-song-study-blocks' ) . '</button>';
    $output .= '</nav>';

    $output .= '<div class="pd-membership-sections" data-membership-panels>';

    $output .= '<section class="pd-membership-section pd-membership-section--profile" id="pd-membership-panel-profile" role="tabpanel" aria-labelledby="pd-membership-tab-profile"' . ( 'profile' === $current_tab ? '' : ' hidden' ) . '>';
    $output .= '<header class="pd-membership-section__header">';
    $output .= '<div class="pd-membership-section__intro">';
    $output .= '<p class="pd-membership-shell__eyebrow">' . esc_html__( 'Perfil base', 'wp-song-study-blocks' ) . '</p>';
    $output .= '<h2>' . esc_html( $is_admin_override ? __( 'Identidad y datos del perfil seleccionado', 'wp-song-study-blocks' ) : __( 'Identidad y datos de tu perfil', 'wp-song-study-blocks' ) ) . '</h2>';
    $output .= '<p>' . esc_html__( 'Aquí defines la base estructurada del usuario: presentación breve, tagline y enlace principal. Esta información alimenta el resto del ecosistema del sitio.', 'wp-song-study-blocks' ) . '</p>';
    $output .= '</div>';
    $output .= '<div class="pd-membership-section__metrics">';
    $output .= '<span class="pd-membership-metric"><strong>' . (int) $project_count . '</strong><span>' . esc_html__( 'proyectos vinculados', 'wp-song-study-blocks' ) . '</span></span>';
    $output .= '<span class="pd-membership-metric"><strong>' . esc_html( $presskit_post_id ? __( 'Sí', 'wp-song-study-blocks' ) : __( 'No', 'wp-song-study-blocks' ) ) . '</strong><span>' . esc_html__( 'presskit asignado', 'wp-song-study-blocks' ) . '</span></span>';
    $output .= '</div>';
    $output .= '</header>';

    $output .= '<div class="pd-membership-shell__grid pd-membership-shell__grid--profile">';
    $output .= '<div class="pd-membership-editor">';
    $output .= '<h2>' . esc_html( $is_admin_override ? __( 'Editar datos base del perfil seleccionado', 'wp-song-study-blocks' ) : __( 'Editar datos base del perfil', 'wp-song-study-blocks' ) ) . '</h2>';
    if ( ! $can_manage_target ) {
        $output .= '<p>' . esc_html__( 'No tienes permisos para editar este perfil.', 'wp-song-study-blocks' ) . '</p>';
        $output .= '</div>';
    } else {
        $output .= '<form class="pd-membership-form" method="post" action="' . esc_url( $action ) . '">';
        $output .= '<input type="hidden" name="action" value="wpssb_save_my_membership" />';
        $output .= '<input type="hidden" name="target_user_id" value="' . (int) $user_id . '" />';
        $output .= '<input type="hidden" name="redirect_to" value="' . $profile_redirect . '" />';
        $output .= wp_nonce_field( 'wpssb_save_my_membership', 'wpssb_membership_nonce', true, false );
        $output .= '<label><span>' . esc_html__( 'Tagline', 'wp-song-study-blocks' ) . '</span><input type="text" name="pd_colaborador_tagline" value="' . esc_attr( $tagline ) . '" /></label>';
        $output .= '<label><span>' . esc_html__( 'Biografía breve', 'wp-song-study-blocks' ) . '</span><textarea name="pd_colaborador_descripcion_corta" rows="3">' . esc_textarea( $short_bio ) . '</textarea></label>';
        $output .= '<label><span>' . esc_html__( 'Sitio / portafolio principal', 'wp-song-study-blocks' ) . '</span><input type="url" name="pd_colaborador_user_url" value="' . esc_attr( $portfolio_url ) . '" placeholder="https://..." /></label>';
        $output .= '<p class="pd-membership-form__hint">' . esc_html__( 'Esta capa es para datos base. La composición visual, narrativa y multimedia del perfil vive abajo, en el editor del presskit.', 'wp-song-study-blocks' ) . '</p>';
        $output .= '<button type="submit" class="wp-block-button__link wp-element-button">' . esc_html( $is_admin_override ? __( 'Guardar perfil seleccionado', 'wp-song-study-blocks' ) : __( 'Guardar datos base', 'wp-song-study-blocks' ) ) . '</button>';
        $output .= '</form>';
        $output .= '</div>';
    }

    $output .= '<div class="pd-membership-sidebar pd-membership-sidebar--profile">';
    if ( $can_switch_targets && ! empty( $manageable_users ) ) {
        $output .= '<div class="pd-membership-panel pd-membership-panel--switcher">';
        $output .= '<form class="pd-membership-switcher" method="get" action="' . esc_url( $current_url ) . '">';
        $output .= '<label><span>' . esc_html__( 'Administrar pertenencia de', 'wp-song-study-blocks' ) . '</span>';
        $output .= '<input type="hidden" name="membership_tab" value="' . esc_attr( $current_tab ) . '" />';
        $output .= '<select name="membership_user">';
        foreach ( $manageable_users as $manageable_user ) {
            if ( ! $manageable_user instanceof WP_User ) {
                continue;
            }
            $selected = selected( $user_id, (int) $manageable_user->ID, false );
            $output .= '<option value="' . (int) $manageable_user->ID . '" ' . $selected . '>' . esc_html( $manageable_user->display_name . ' · ' . $manageable_user->user_email ) . '</option>';
        }
        $output .= '</select></label>';
        $output .= '<button type="submit" class="wp-block-button__link wp-element-button is-style-outline">' . esc_html__( 'Abrir', 'wp-song-study-blocks' ) . '</button>';
        $output .= '</form>';
        if ( $is_admin_override ) {
            $output .= '<p class="pd-membership-form__hint">' . esc_html__( 'Estás editando este perfil con permisos de administrador.', 'wp-song-study-blocks' ) . '</p>';
        }
        $output .= '</div>';
    }

    $output .= '<div class="pd-membership-panel pd-membership-panel--summary">';
    $output .= '<h2>' . esc_html__( 'Resumen del perfil', 'wp-song-study-blocks' ) . '</h2>';
    $output .= '<dl class="pd-membership-summary">';
    $output .= '<div><dt>' . esc_html__( 'Tagline actual', 'wp-song-study-blocks' ) . '</dt><dd>' . esc_html( '' !== $tagline ? $tagline : __( 'Aún sin definir', 'wp-song-study-blocks' ) ) . '</dd></div>';
    $output .= '<div><dt>' . esc_html__( 'Portafolio', 'wp-song-study-blocks' ) . '</dt><dd>';
    if ( '' !== $portfolio_url ) {
        $output .= '<a href="' . esc_url( $portfolio_url ) . '">' . esc_html( preg_replace( '#^https?://#', '', $portfolio_url ) ) . '</a>';
    } else {
        $output .= esc_html__( 'Sin enlace principal', 'wp-song-study-blocks' );
    }
    $output .= '</dd></div>';
    $output .= '<div><dt>' . esc_html__( 'Biografía breve', 'wp-song-study-blocks' ) . '</dt><dd>' . esc_html( '' !== $short_bio ? wp_trim_words( $short_bio, 24 ) : __( 'Todavía no hay una bio breve.', 'wp-song-study-blocks' ) ) . '</dd></div>';
    $output .= '</dl>';
    $output .= '</div>';

    if ( ! empty( $settings['show_projects'] ) ) {
        $output .= '<div class="pd-membership-panel">';
        $output .= '<h2>' . esc_html__( 'Mis proyectos', 'wp-song-study-blocks' ) . '</h2>';
        $output .= wpssb_render_project_directory_markup(
            [
                'user_id'            => $user_id,
                'posts_per_page'     => 12,
                'show_image'         => true,
                'show_excerpt'       => true,
                'show_area'          => true,
                'show_collaborators' => true,
                'empty_message'      => $is_admin_override
                    ? __( 'Este usuario todavía no está vinculado a ningún proyecto.', 'wp-song-study-blocks' )
                    : __( 'Todavía no estás vinculado a ningún proyecto.', 'wp-song-study-blocks' ),
                'login_message'      => __( 'Inicia sesión para ver tus proyectos relacionados.', 'wp-song-study-blocks' ),
            ]
        );
        $output .= '</div>';
    }
    $output .= '</div></div></section>';

    $output .= '<section class="pd-membership-section pd-membership-section--presskit" id="pd-membership-panel-presskit" role="tabpanel" aria-labelledby="pd-membership-tab-presskit"' . ( 'presskit' === $current_tab ? '' : ' hidden' ) . '>';
    $output .= '<header class="pd-membership-section__header">';
    $output .= '<div class="pd-membership-section__intro">';
    $output .= '<p class="pd-membership-shell__eyebrow">' . esc_html__( 'Presskit público', 'wp-song-study-blocks' ) . '</p>';
    $output .= '<h2>' . esc_html__( 'Dirección visual, narrativa y composición pública', 'wp-song-study-blocks' ) . '</h2>';
    $output .= '<p>' . esc_html__( 'Esta sección es el lienzo vivo del perfil. Aquí construyes tu presencia pública con bloques, medios, fondos, overlays y composición libre.', 'wp-song-study-blocks' ) . '</p>';
    $output .= '</div>';
    $output .= '<div class="pd-membership-shell__actions">';
    if ( $can_manage_target && $edit_presskit ) {
        $output .= '<a class="wp-block-button__link wp-element-button" href="' . esc_url( add_query_arg( 'membership_tab', 'presskit', $edit_presskit ) ) . '">' . esc_html__( 'Abrir editor del presskit', 'wp-song-study-blocks' ) . '</a>';
    }
    $output .= '<a class="wp-block-button__link wp-element-button is-style-outline" href="' . esc_url( $public_presskit ) . '">' . esc_html__( 'Ver vista pública', 'wp-song-study-blocks' ) . '</a>';
    $output .= '</div>';
    $output .= '</header>';

    $output .= '<div class="pd-membership-shell__grid pd-membership-shell__grid--presskit">';
    $output .= '<div class="pd-membership-panel pd-membership-panel--builder pd-membership-panel--builder-help">';
    $output .= '<div class="pd-membership-help-bubble">';
    $output .= '<p class="pd-membership-help-bubble__eyebrow">' . esc_html__( 'Guía breve', 'wp-song-study-blocks' ) . '</p>';
    $output .= '<h2>' . esc_html__( 'Construye un presskit que sí te represente', 'wp-song-study-blocks' ) . '</h2>';
    $output .= '<p>' . esc_html__( 'Este espacio es para dar forma a tu presencia pública: una página donde puedas presentarte con claridad, carácter y materiales que ayuden a entender quién eres y qué haces.', 'wp-song-study-blocks' ) . '</p>';
    $output .= '<ul class="pd-membership-presskit-highlights">';
    $output .= '<li>' . esc_html__( 'Piensa cada bloque como una pieza de contenido: título, texto, imagen, audio, cita, columnas o grupo.', 'wp-song-study-blocks' ) . '</li>';
    $output .= '<li>' . esc_html__( 'Agrupa bloques cuando quieras que varias piezas funcionen juntas como una sola sección visual.', 'wp-song-study-blocks' ) . '</li>';
    $output .= '<li>' . esc_html__( 'Usa fondos, medios y overlays para mejorar legibilidad y atmósfera, sin perder claridad.', 'wp-song-study-blocks' ) . '</li>';
    $output .= '<li>' . esc_html__( 'Empieza simple: una presentación fuerte, una imagen significativa y una estructura clara ya dicen mucho.', 'wp-song-study-blocks' ) . '</li>';
    $output .= '</ul>';
    $output .= '<p class="pd-membership-help-bubble__note">' . esc_html__( 'No necesitas terminarlo en una sola sesión. Piensa este presskit como un espacio vivo que puedes ir afinando con el tiempo.', 'wp-song-study-blocks' ) . '</p>';
    $output .= '</div>';
    $output .= '</div>';

    $output .= '<div class="pd-membership-sidebar pd-membership-sidebar--presskit">';
    $output .= '<div class="pd-membership-panel pd-membership-panel--presskit-actions">';
    $output .= '<h2>' . esc_html__( 'Acciones rápidas', 'wp-song-study-blocks' ) . '</h2>';
    $output .= '<p class="pd-membership-form__hint">' . esc_html__( 'Cuando quieras ver el resultado final fuera del área de edición, puedes abrir la vista pública en una nueva pestaña.', 'wp-song-study-blocks' ) . '</p>';
    $output .= '<div class="pd-membership-shell__actions">';
    if ( $can_manage_target && $edit_presskit ) {
        $output .= '<a class="wp-block-button__link wp-element-button" href="' . esc_url( add_query_arg( 'membership_tab', 'presskit', $edit_presskit ) ) . '">' . esc_html__( 'Seguir editando', 'wp-song-study-blocks' ) . '</a>';
    }
    $output .= '<a class="wp-block-button__link wp-element-button is-style-outline" href="' . esc_url( $public_presskit ) . '">' . esc_html__( 'Abrir vista pública', 'wp-song-study-blocks' ) . '</a>';
    $output .= '</div>';
    $output .= '</div>';
    $output .= '</div></div>';

    if ( $can_manage_target && $presskit_post_id ) {
        $output .= wpssb_render_frontend_presskit_workbench( $presskit_post_id );
    } else {
        $output .= '<div class="pd-membership-panel pd-membership-panel--builder">';
        $output .= '<p>' . esc_html__( 'No hay permisos o todavía no existe un presskit editable para este perfil.', 'wp-song-study-blocks' ) . '</p>';
        $output .= '</div>';
    }

    $output .= '</section>';

    $output .= '<section class="pd-membership-section pd-membership-section--google" id="pd-membership-panel-google" role="tabpanel" aria-labelledby="pd-membership-tab-google"' . ( 'google' === $current_tab ? '' : ' hidden' ) . '>';
    $output .= '<header class="pd-membership-section__header">';
    $output .= '<div class="pd-membership-section__intro">';
    $output .= '<p class="pd-membership-shell__eyebrow">' . esc_html__( 'Google Drive y Calendar', 'wp-song-study-blocks' ) . '</p>';
    $output .= '<h2>' . esc_html__( 'Vinculaciones personales de trabajo', 'wp-song-study-blocks' ) . '</h2>';
    $output .= '<p>' . esc_html__( 'Desde aquí puedes revisar si tu cuenta está lista para guardar materiales en Drive y sincronizar ensayos confirmados en Calendar. Drive y Calendar usan autorizaciones separadas, aunque pueden compartir el mismo cliente OAuth.', 'wp-song-study-blocks' ) . '</p>';
    $output .= '</div>';
    $output .= '</header>';
    $output .= '<div class="pd-membership-google-grid">';
    $output .= wpssb_render_membership_google_connection_card( 'google_drive', $user_id, $google_return_url, $can_connect_google );
    $output .= wpssb_render_membership_google_connection_card( 'google_calendar', $user_id, $google_return_url, $can_connect_google );
    $output .= '</div>';
    $output .= '<div class="pd-membership-panel pd-membership-panel--google-note">';
    $output .= '<h3>' . esc_html__( 'Cómo se usa esta vinculación', 'wp-song-study-blocks' ) . '</h3>';
    $output .= '<ul class="pd-membership-presskit-highlights">';
    $output .= '<li>' . esc_html__( 'Drive guarda y consulta adjuntos multimedia del cancionero y materiales de trabajo.', 'wp-song-study-blocks' ) . '</li>';
    $output .= '<li>' . esc_html__( 'Calendar permite crear eventos de ensayos confirmados e invitar a los integrantes que votaron sí o tal vez.', 'wp-song-study-blocks' ) . '</li>';
    $output .= '<li>' . esc_html__( 'Si cambias permisos en Google Cloud, reconecta desde aquí para generar tokens nuevos con los scopes correctos.', 'wp-song-study-blocks' ) . '</li>';
    $output .= '</ul>';
    $output .= '</div>';
    $output .= '</section></div>';

    $output .= '</section>';

    return $output;
}

/**
 * Render callback del bloque de pertenencia del usuario actual.
 *
 * @param array    $attributes Atributos del bloque.
 * @param string   $content    Contenido interno.
 * @param WP_Block $block      Instancia del bloque.
 * @return string
 */
function wpssb_render_block_current_membership( $attributes = [], $content = '', $block = null ) {
    $settings = [
        'show_projects'   => ! isset( $attributes['showProjects'] ) || (bool) $attributes['showProjects'],
        'show_preview'    => ! isset( $attributes['showPreview'] ) || (bool) $attributes['showPreview'],
        'show_admin_link' => ! isset( $attributes['showAdminLink'] ) || (bool) $attributes['showAdminLink'],
        'target_user_id'  => isset( $attributes['targetUserId'] ) ? absint( $attributes['targetUserId'] ) : 0,
        'login_message'   => isset( $attributes['loginMessage'] ) ? sanitize_text_field( $attributes['loginMessage'] ) : __( 'Mi pertenencia es un espacio privado para colaboradores musicales, colaboradores y administradores. Inicia sesion con una cuenta autorizada para editar tu perfil, mantener tu presskit y revisar tus proyectos.', 'wp-song-study-blocks' ),
    ];

    $classes = [ 'pd-membership-block' ];
    $layout_width = isset( $attributes['layoutWidth'] ) ? sanitize_key( $attributes['layoutWidth'] ) : 'immersive';
    $valid_layout_widths = [ 'default', 'wide', 'immersive' ];

    if ( ! in_array( $layout_width, $valid_layout_widths, true ) ) {
        $layout_width = 'immersive';
    }

    $classes[] = 'is-layout-' . $layout_width;

    $style_vars = [];
    $style_attributes = [
        'shellTextColor'       => '--pd-membership-custom-shell-text',
        'headerBackgroundColor'=> '--pd-membership-custom-header-background',
        'headerTextColor'      => '--pd-membership-custom-header-text',
        'panelBackgroundColor' => '--pd-membership-custom-panel-background',
        'panelTextColor'       => '--pd-membership-custom-panel-text',
        'panelBorderColor'     => '--pd-membership-custom-panel-border',
        'fieldBackgroundColor' => '--pd-membership-custom-field-background',
        'fieldTextColor'       => '--pd-membership-custom-field-text',
        'linkColor'            => '--pd-membership-custom-link',
    ];

    foreach ( $style_attributes as $attribute_name => $css_var ) {
        if ( empty( $attributes[ $attribute_name ] ) || ! is_string( $attributes[ $attribute_name ] ) ) {
            continue;
        }

        $value = trim( sanitize_text_field( $attributes[ $attribute_name ] ) );

        if ( '' === $value || ! preg_match( '/^[#(),.%\sA-Za-z0-9_-]+$/', $value ) ) {
            continue;
        }

        $style_vars[] = $css_var . ':' . $value;
    }

    if ( isset( $attributes['editorMinHeight'] ) ) {
        $editor_min_height = max( 560, min( 1200, absint( $attributes['editorMinHeight'] ) ) );
        $style_vars[] = '--pd-membership-editor-min-height:' . $editor_min_height . 'px';
    }

    $wrapper_attributes = get_block_wrapper_attributes(
        [
            'class' => implode( ' ', $classes ),
            'style' => implode( ';', $style_vars ),
        ]
    );

    return sprintf(
        '<div %1$s>%2$s</div>',
        $wrapper_attributes,
        wpssb_render_current_membership_markup( $settings )
    );
}

/**
 * Devuelve la template preferida para la página frontend de ensayos.
 *
 * @return string
 */
function wpssb_get_preferred_rehearsals_page_template() {
    return wpssb_theme_has_block_template_slug( 'ensayos' ) ? 'ensayos' : '';
}

/**
 * Asigna la template `ensayos` a la página `musica/ensayos` o `ensayos`.
 *
 * @return void
 */
function wpssb_sync_rehearsals_page_template_assignment() {
    $template_slug = wpssb_get_preferred_rehearsals_page_template();

    if ( '' === $template_slug ) {
        return;
    }

    $candidates = [
        get_page_by_path( 'musica/ensayos' ),
        get_page_by_path( 'ensayos' ),
    ];

    foreach ( $candidates as $candidate ) {
        if ( ! $candidate instanceof WP_Post ) {
            continue;
        }

        $page_id           = (int) $candidate->ID;
        $current_template  = (string) get_post_meta( $page_id, '_wp_page_template', true );

        if ( '' !== $current_template && 'default' !== $current_template ) {
            continue;
        }

        update_post_meta( $page_id, '_wp_page_template', $template_slug );
        break;
    }
}
add_action( 'admin_init', 'wpssb_sync_rehearsals_page_template_assignment' );

/**
 * Devuelve la URL base preferida para la página frontend de ensayos.
 *
 * @return string
 */
function wpssb_get_frontend_rehearsal_page_base_url() {
    $queried_id = get_queried_object_id();

    if ( $queried_id > 0 && 'page' === get_post_type( $queried_id ) ) {
        $permalink = get_permalink( $queried_id );

        if ( is_string( $permalink ) && '' !== $permalink ) {
            return $permalink;
        }
    }

    $page = get_page_by_path( 'musica/ensayos' );
    if ( ! $page instanceof WP_Post ) {
        $page = get_page_by_path( 'ensayos' );
    }

    if ( $page instanceof WP_Post ) {
        $permalink = get_permalink( $page );

        if ( is_string( $permalink ) && '' !== $permalink ) {
            return $permalink;
        }
    }

    return home_url( '/' );
}

/**
 * Construye una URL segura para volver al frontend de ensayos.
 *
 * @param int    $project_id Proyecto seleccionado.
 * @param string $tab        Pestaña activa.
 * @return string
 */
function wpssb_get_frontend_rehearsal_url( $project_id = 0, $tab = '' ) {
    $base_url   = wpssb_get_frontend_rehearsal_page_base_url();
    $query_args = [];

    $project_id = absint( $project_id );
    if ( $project_id > 0 ) {
        $query_args['rehearsal_project'] = $project_id;
    }

    $tab = sanitize_key( (string) $tab );
    if ( in_array( $tab, [ 'availability', 'calendar', 'logbook' ], true ) ) {
        $query_args['rehearsal_tab'] = $tab;
    }

    return ! empty( $query_args ) ? add_query_arg( $query_args, $base_url ) : $base_url;
}

/**
 * Construye una URL al calendario de ensayos en una subvista concreta.
 *
 * @param int    $project_id Proyecto seleccionado.
 * @param string $view       Vista del calendario.
 * @return string
 */
function wpssb_get_frontend_rehearsal_calendar_url( $project_id = 0, $view = 'proposals' ) {
    $view = sanitize_key( (string) $view );
    if ( ! in_array( $view, [ 'proposals', 'confirmed' ], true ) ) {
        $view = 'proposals';
    }

    return add_query_arg(
        'rehearsal_calendar_view',
        $view,
        wpssb_get_frontend_rehearsal_url( $project_id, 'calendar' )
    );
}

/**
 * Redirige al frontend de ensayos con feedback.
 *
 * @param string $status     Estado corto.
 * @param int    $project_id Proyecto seleccionado.
 * @param string $tab        Pestaña activa.
 * @return void
 */
function wpssb_redirect_frontend_rehearsal( $status, $project_id = 0, $tab = '' ) {
    $fallback_url = wpssb_get_frontend_rehearsal_url( $project_id, $tab );
    $redirect_to  = isset( $_POST['redirect_to'] )
        ? wp_validate_redirect( wp_unslash( $_POST['redirect_to'] ), $fallback_url )
        : $fallback_url;

    $redirect_to = add_query_arg( 'rehearsal_status', sanitize_key( (string) $status ), $redirect_to );

    wp_safe_redirect( $redirect_to );
    exit;
}

/**
 * Devuelve el feedback actual para el frontend de ensayos.
 *
 * @return array<string, string>|null
 */
function wpssb_get_frontend_rehearsal_feedback() {
    $status = isset( $_GET['rehearsal_status'] ) ? sanitize_key( wp_unslash( $_GET['rehearsal_status'] ) ) : '';

    if ( '' === $status ) {
        return null;
    }

    $messages = [
        'updated_availability' => [
            'type'    => 'success',
            'message' => __( 'Tu disponibilidad base quedó guardada con éxito.', 'wp-song-study-blocks' ),
        ],
        'updated_logbook'      => [
            'type'    => 'success',
            'message' => __( 'La bitácora del ensayo quedó guardada con éxito.', 'wp-song-study-blocks' ),
        ],
        'updated_vote'         => [
            'type'    => 'success',
            'message' => __( 'Tu voto para el ensayo quedó guardado.', 'wp-song-study-blocks' ),
        ],
        'created_proposal'     => [
            'type'    => 'success',
            'message' => __( 'La propuesta de ensayo quedó creada.', 'wp-song-study-blocks' ),
        ],
        'forced_confirmed'     => [
            'type'    => 'success',
            'message' => __( 'El ensayo quedó confirmado por excepción y ya se intentó sincronizar con Google Calendar.', 'wp-song-study-blocks' ),
        ],
        'calendar_synced'      => [
            'type'    => 'success',
            'message' => __( 'El ensayo se sincronizó con Google Calendar.', 'wp-song-study-blocks' ),
        ],
        'calendar_sync_failed' => [
            'type'    => 'error',
            'message' => __( 'No se pudo sincronizar el ensayo con Google Calendar. Revisa el mensaje guardado en la tarjeta del ensayo.', 'wp-song-study-blocks' ),
        ],
        'cancelled_session'    => [
            'type'    => 'success',
            'message' => __( 'El ensayo quedó cancelado, bloqueado y se eliminó su evento de Google Calendar si existía.', 'wp-song-study-blocks' ),
        ],
        'cancelled_calendar_failed' => [
            'type'    => 'error',
            'message' => __( 'El ensayo quedó cancelado, pero Google Calendar no pudo eliminar el evento. Revisa el mensaje guardado en la tarjeta.', 'wp-song-study-blocks' ),
        ],
        'missing_cancel_reason' => [
            'type'    => 'error',
            'message' => __( 'Escribe la razón de la cancelación para avisar al grupo.', 'wp-song-study-blocks' ),
        ],
        'updated_proposal'     => [
            'type'    => 'success',
            'message' => __( 'La propuesta quedó actualizada.', 'wp-song-study-blocks' ),
        ],
        'deleted_proposal'     => [
            'type'    => 'success',
            'message' => __( 'La propuesta fue eliminada.', 'wp-song-study-blocks' ),
        ],
        'invalid_request'      => [
            'type'    => 'error',
            'message' => __( 'No se pudo procesar la acción solicitada.', 'wp-song-study-blocks' ),
        ],
        'login_required'       => [
            'type'    => 'error',
            'message' => __( 'Necesitas iniciar sesión para usar este Planificador de ensayos.', 'wp-song-study-blocks' ),
        ],
        'invalid_nonce'        => [
            'type'    => 'error',
            'message' => __( 'La sesión del formulario expiró. Intenta de nuevo.', 'wp-song-study-blocks' ),
        ],
        'invalid_project'      => [
            'type'    => 'error',
            'message' => __( 'El proyecto elegido ya no está disponible para tu usuario.', 'wp-song-study-blocks' ),
        ],
        'invalid_session'      => [
            'type'    => 'error',
            'message' => __( 'La propuesta de ensayo ya no existe o dejó de estar disponible.', 'wp-song-study-blocks' ),
        ],
        'invalid_logbook_session' => [
            'type'    => 'error',
            'message' => __( 'Ese ensayo ya no está disponible dentro de la bitácora.', 'wp-song-study-blocks' ),
        ],
        'invalid_ranges'       => [
            'type'    => 'error',
            'message' => __( 'Usa el formato HH:MM-HH:MM para cada rango de horario.', 'wp-song-study-blocks' ),
        ],
        'forbidden'            => [
            'type'    => 'error',
            'message' => __( 'No tienes permiso para modificar esa información del Planificador de ensayos.', 'wp-song-study-blocks' ),
        ],
        'session_locked'       => [
            'type'    => 'error',
            'message' => __( 'Ese ensayo ya está cerrado para nuevas votaciones.', 'wp-song-study-blocks' ),
        ],
        'logbook_locked'       => [
            'type'    => 'error',
            'message' => __( 'La bitácora de ese ensayo se habilita cuando llega su fecha y hora programadas.', 'wp-song-study-blocks' ),
        ],
    ];

    return isset( $messages[ $status ] ) ? $messages[ $status ] : null;
}

/**
 * Encola assets del frontend de ensayos.
 *
 * @return void
 */
function wpssb_enqueue_frontend_rehearsal_assets() {
    $script_path = WPSSB_PATH . 'assets/project-frontend/rehearsal-tabs.js';

    if ( file_exists( $script_path ) ) {
        wp_enqueue_script(
            'wpssb-rehearsal-tabs',
            WPSSB_URL . 'assets/project-frontend/rehearsal-tabs.js',
            [],
            (string) filemtime( $script_path ),
            true
        );

        wp_localize_script(
            'wpssb-rehearsal-tabs',
            'wpssbRehearsalFrontend',
            [
                'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
                'autosaveNonce'    => wp_create_nonce( 'wpssb_autosave_rehearsal_proposal' ),
                'availabilityAutosaveNonce' => wp_create_nonce( 'wpssb_autosave_rehearsal_availability' ),
                'logbookAutosaveNonce' => wp_create_nonce( 'wpssb_autosave_rehearsal_logbook' ),
                'autosaveMessages' => [
                    'saving'  => __( 'Guardando cambios...', 'wp-song-study-blocks' ),
                    'saved'   => __( 'Cambios guardados.', 'wp-song-study-blocks' ),
                    'error'   => __( 'No se pudieron guardar los cambios.', 'wp-song-study-blocks' ),
                ],
                'availabilityAutosaveMessages' => [
                    'idle'    => __( 'Tu disponibilidad se guarda automáticamente cuando todos los horarios están completos.', 'wp-song-study-blocks' ),
                    'saving'  => __( 'Guardando disponibilidad...', 'wp-song-study-blocks' ),
                    'saved'   => __( 'Disponibilidad guardada con éxito.', 'wp-song-study-blocks' ),
                    'error'   => __( 'No se pudo guardar tu disponibilidad.', 'wp-song-study-blocks' ),
                    'invalid' => __( 'Corrige los horarios marcados para reactivar el autoguardado.', 'wp-song-study-blocks' ),
                ],
                'logbookAutosaveMessages' => [
                    'idle'   => __( 'Las observaciones y la asistencia se guardan automáticamente mientras escribes.', 'wp-song-study-blocks' ),
                    'saving' => __( 'Guardando bitácora...', 'wp-song-study-blocks' ),
                    'saved'  => __( 'Bitácora guardada con éxito.', 'wp-song-study-blocks' ),
                    'error'  => __( 'No se pudo guardar la bitácora del ensayo.', 'wp-song-study-blocks' ),
                ],
                'deleteConfirm'    => __( 'Esta propuesta se eliminará. Esta acción no se puede deshacer.', 'wp-song-study-blocks' ),
                'validationMessages' => [
                    'rounded'        => __( 'Se ajustó automáticamente a bloques de 5 minutos.', 'wp-song-study-blocks' ),
                    'incompleteRange'=> __( 'Completa o elimina este rango antes de guardar.', 'wp-song-study-blocks' ),
                    'invalidRange'   => __( 'La hora final debe ser mayor a la hora inicial.', 'wp-song-study-blocks' ),
                    'invalidSubmit'  => __( 'Corrige los horarios marcados antes de guardar tu disponibilidad.', 'wp-song-study-blocks' ),
                    'invalidDisabled'=> __( 'El botón se habilitará cuando todos los horarios queden completos y coherentes.', 'wp-song-study-blocks' ),
                ],
            ]
        );
    }
}

/**
 * Devuelve los proyectos disponibles para el frontend de ensayos.
 *
 * @param int $user_id Usuario actual.
 * @return WP_Post[]
 */
function wpssb_get_frontend_rehearsal_projects_for_user( $user_id ) {
    $user_id = absint( $user_id );

    if ( $user_id <= 0 ) {
        return [];
    }

    $project_ids = wpssb_get_user_rehearsal_project_ids( $user_id );
    if ( empty( $project_ids ) ) {
        return [];
    }

    $projects = array_values(
        array_filter(
            array_map(
                static function ( $project_id ) {
                    $project_id = absint( $project_id );

                    if ( $project_id <= 0 ) {
                        return null;
                    }

                    $post = get_post( $project_id );
                    return $post instanceof WP_Post ? $post : null;
                },
                $project_ids
            )
        )
    );

    usort(
        $projects,
        static function ( $left, $right ) {
            return strnatcasecmp( $left->post_title, $right->post_title );
        }
    );

    return $projects;
}

/**
 * Resuelve el proyecto activo dentro del frontend de ensayos.
 *
 * @param WP_Post[] $projects Proyectos disponibles.
 * @return int
 */
function wpssb_resolve_frontend_rehearsal_project_id( $projects ) {
    $requested = isset( $_GET['rehearsal_project'] ) ? absint( wp_unslash( $_GET['rehearsal_project'] ) ) : 0;
    $allowed   = array_map(
        static function ( $project ) {
            return $project instanceof WP_Post ? (int) $project->ID : 0;
        },
        (array) $projects
    );
    $allowed   = array_values( array_filter( $allowed ) );

    if ( $requested > 0 && in_array( $requested, $allowed, true ) ) {
        return $requested;
    }

    return ! empty( $allowed ) ? (int) $allowed[0] : 0;
}

/**
 * Devuelve etiquetas legibles para los días de ensayo.
 *
 * @return array<string, string>
 */
function wpssb_get_project_rehearsal_day_labels() {
    return [
        'monday'    => __( 'Lunes', 'wp-song-study-blocks' ),
        'tuesday'   => __( 'Martes', 'wp-song-study-blocks' ),
        'wednesday' => __( 'Miércoles', 'wp-song-study-blocks' ),
        'thursday'  => __( 'Jueves', 'wp-song-study-blocks' ),
        'friday'    => __( 'Viernes', 'wp-song-study-blocks' ),
        'saturday'  => __( 'Sábado', 'wp-song-study-blocks' ),
        'sunday'    => __( 'Domingo', 'wp-song-study-blocks' ),
    ];
}

/**
 * Devuelve la etiqueta del estado de una sesión.
 *
 * @param string $status Estado saneado.
 * @return string
 */
function wpssb_get_project_rehearsal_status_label( $status ) {
    $labels = [
        'proposed'  => __( 'Propuesta', 'wp-song-study-blocks' ),
        'voting'    => __( 'Votación', 'wp-song-study-blocks' ),
        'confirmed' => __( 'Confirmado', 'wp-song-study-blocks' ),
        'completed' => __( 'Realizado', 'wp-song-study-blocks' ),
        'cancelled' => __( 'Cancelado', 'wp-song-study-blocks' ),
        'expired'   => __( 'Propuesta expirada', 'wp-song-study-blocks' ),
    ];

    return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( sanitize_text_field( (string) $status ) );
}

/**
 * Indica si la sesión quedó confirmada por excepción y no por consenso total.
 *
 * @param array<string, mixed> $session Sesión saneada.
 * @return bool
 */
function wpssb_project_rehearsal_is_forced_confirmed( $session ) {
    return is_array( $session )
        && 'confirmed' === sanitize_key( (string) ( $session['status'] ?? '' ) )
        && empty( $session['consensus_reached'] );
}

/**
 * Devuelve la próxima fecha sugerida para una ventana semanal.
 *
 * @param string $day Día normalizado.
 * @return string
 */
function wpssb_get_frontend_rehearsal_default_date_for_day( $day ) {
    $day       = sanitize_key( (string) $day );
    $days      = wpssb_get_project_rehearsal_days();
    $day_index = array_search( $day, $days, true );

    if ( false === $day_index ) {
        return gmdate( 'Y-m-d' );
    }

    $today_timestamp = current_time( 'timestamp' );
    $today_index     = (int) gmdate( 'N', $today_timestamp ) - 1;
    $offset          = ( (int) $day_index - $today_index + 7 ) % 7;

    if ( 0 === $offset ) {
        $offset = 7;
    }

    return gmdate( 'Y-m-d', strtotime( '+' . $offset . ' days', $today_timestamp ) );
}

/**
 * Devuelve la etiqueta de un voto.
 *
 * @param string $vote Voto saneado.
 * @return string
 */
function wpssb_get_project_rehearsal_vote_label( $vote ) {
    $labels = [
        'pending' => __( 'Sin votar', 'wp-song-study-blocks' ),
        'yes'     => __( 'Sí', 'wp-song-study-blocks' ),
        'no'      => __( 'No', 'wp-song-study-blocks' ),
        'maybe'   => __( 'Tal vez', 'wp-song-study-blocks' ),
    ];

    return isset( $labels[ $vote ] ) ? $labels[ $vote ] : ucfirst( sanitize_text_field( (string) $vote ) );
}

/**
 * Devuelve la etiqueta de asistencia.
 *
 * @param string $status Estado saneado.
 * @return string
 */
function wpssb_get_project_rehearsal_attendance_label( $status ) {
    $labels = [
        'pending'   => __( 'Pendiente', 'wp-song-study-blocks' ),
        'confirmed' => __( 'Confirmado', 'wp-song-study-blocks' ),
        'attended'  => __( 'Asistió', 'wp-song-study-blocks' ),
        'late'      => __( 'Tarde', 'wp-song-study-blocks' ),
        'absent'    => __( 'No asistió', 'wp-song-study-blocks' ),
        'excused'   => __( 'Justificado', 'wp-song-study-blocks' ),
    ];

    return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( sanitize_text_field( (string) $status ) );
}

/**
 * Formatea la fecha y hora de una sesión.
 *
 * @param array<string, mixed> $session Sesión saneada.
 * @return string
 */
function wpssb_format_project_rehearsal_schedule( $session ) {
    if ( ! is_array( $session ) || empty( $session['scheduled_for'] ) ) {
        return '';
    }

    $timestamp = strtotime( sanitize_text_field( (string) $session['scheduled_for'] ) . ' ' . ( ! empty( $session['start_time'] ) ? sanitize_text_field( (string) $session['start_time'] ) : '00:00' ) );
    if ( false === $timestamp ) {
        return sanitize_text_field( (string) $session['scheduled_for'] );
    }

    $label = wp_date( 'j M Y', $timestamp );

    if ( ! empty( $session['start_time'] ) ) {
        $label .= ' · ' . sanitize_text_field( (string) $session['start_time'] );

        if ( ! empty( $session['end_time'] ) ) {
            $label .= ' - ' . sanitize_text_field( (string) $session['end_time'] );
        }
    }

    return $label;
}

/**
 * Localiza la disponibilidad de un miembro.
 *
 * @param array<int, array<string, mixed>> $availability Disponibilidad saneada.
 * @param int                              $user_id      Usuario buscado.
 * @return array<string, mixed>
 */
function wpssb_get_project_rehearsal_member_entry( $availability, $user_id ) {
    $user_id = absint( $user_id );

    foreach ( (array) $availability as $entry ) {
        if ( is_array( $entry ) && $user_id === absint( $entry['user_id'] ?? 0 ) ) {
            return $entry;
        }
    }

    $user = get_user_by( 'id', $user_id );

    return [
        'user_id'           => $user_id,
        'nombre'            => $user instanceof WP_User ? sanitize_text_field( $user->display_name ) : '',
        'notes'             => '',
        'blocked_days'      => [],
        'slots'             => [],
        'unavailable_slots' => [],
        'updated_at'        => '',
    ];
}

/**
 * Localiza el voto de un usuario dentro de una sesión.
 *
 * @param array<string, mixed> $session Sesión saneada.
 * @param int                  $user_id Usuario buscado.
 * @return array<string, mixed>
 */
function wpssb_get_project_rehearsal_member_vote( $session, $user_id ) {
    $user_id = absint( $user_id );

    foreach ( (array) ( $session['votes'] ?? [] ) as $vote ) {
        if ( is_array( $vote ) && $user_id === absint( $vote['user_id'] ?? 0 ) ) {
            return $vote;
        }
    }

    $user = get_user_by( 'id', $user_id );

    return [
        'user_id' => $user_id,
        'nombre'  => $user instanceof WP_User ? sanitize_text_field( $user->display_name ) : '',
        'vote'    => 'pending',
        'comment' => '',
    ];
}

/**
 * Devuelve el estado de asistencia del usuario dentro de una sesión.
 *
 * @param array<string, mixed> $session Sesión saneada.
 * @param int                  $user_id Usuario buscado.
 * @return array<string, mixed>
 */
function wpssb_get_project_rehearsal_member_attendance( $session, $user_id ) {
    $user_id = absint( $user_id );

    foreach ( (array) ( $session['attendance'] ?? [] ) as $entry ) {
        if ( is_array( $entry ) && $user_id === absint( $entry['user_id'] ?? 0 ) ) {
            return $entry;
        }
    }

    return [
        'user_id' => $user_id,
        'nombre'  => '',
        'status'  => 'pending',
        'comment' => '',
    ];
}

/**
 * Localiza una sesión por ID dentro de un arreglo saneado.
 *
 * @param array<int, array<string, mixed>> $sessions   Sesiones saneadas.
 * @param string                           $session_id Sesión buscada.
 * @return array<string, mixed>
 */
function wpssb_get_project_rehearsal_session_entry( $sessions, $session_id ) {
    $session_id = sanitize_key( (string) $session_id );

    foreach ( (array) $sessions as $session ) {
        if ( is_array( $session ) && $session_id === sanitize_key( (string) ( $session['id'] ?? '' ) ) ) {
            return $session;
        }
    }

    return [];
}

/**
 * Cuenta votos por tipo dentro de una sesión.
 *
 * @param array<string, mixed> $session Sesión saneada.
 * @return array<string, int>
 */
function wpssb_get_project_rehearsal_vote_totals( $session ) {
    $totals = [
        'yes'     => 0,
        'maybe'   => 0,
        'no'      => 0,
        'pending' => 0,
    ];

    foreach ( (array) ( $session['votes'] ?? [] ) as $vote ) {
        if ( ! is_array( $vote ) ) {
            continue;
        }

        $status = sanitize_key( (string) ( $vote['vote'] ?? 'pending' ) );
        if ( ! isset( $totals[ $status ] ) ) {
            $status = 'pending';
        }

        $totals[ $status ]++;
    }

    return $totals;
}

/**
 * Indica si una sesión admite voto desde frontend.
 *
 * @param array<string, mixed> $session Sesión saneada.
 * @return bool
 */
function wpssb_frontend_rehearsal_session_accepts_votes( $session ) {
    $status = sanitize_key( (string) ( $session['status'] ?? '' ) );

    if ( in_array( $status, [ 'completed', 'cancelled', 'expired' ], true ) ) {
        return false;
    }

    return ! wpssb_project_rehearsal_session_is_expired_proposal( $session );
}

/**
 * Prepara los valores iniciales del formulario de disponibilidad.
 *
 * @param array<string, mixed> $availability Disponibilidad del usuario.
 * @return array<string, array<string, mixed>>
 */
function wpssb_prepare_frontend_rehearsal_availability_form( $availability ) {
    $form = [];

    foreach ( wpssb_get_project_rehearsal_days() as $day ) {
        $form[ $day ] = [
            'blocked'           => in_array( $day, (array) ( $availability['blocked_days'] ?? [] ), true ),
            'slots'             => [],
            'unavailable_slots' => [],
        ];
    }

    foreach ( (array) ( $availability['slots'] ?? [] ) as $slot ) {
        if ( ! is_array( $slot ) ) {
            continue;
        }

        $day = sanitize_key( (string) ( $slot['day'] ?? '' ) );
        if ( ! isset( $form[ $day ] ) ) {
            continue;
        }

        $form[ $day ]['slots'][] = wpssb_prepare_frontend_rehearsal_slot_form_row( $slot );
    }

    foreach ( (array) ( $availability['unavailable_slots'] ?? [] ) as $slot ) {
        if ( ! is_array( $slot ) ) {
            continue;
        }

        $day = sanitize_key( (string) ( $slot['day'] ?? '' ) );
        if ( ! isset( $form[ $day ] ) ) {
            continue;
        }

        $form[ $day ]['unavailable_slots'][] = wpssb_prepare_frontend_rehearsal_slot_form_row( $slot );
    }

    return $form;
}

/**
 * Convierte un horario 24h a partes de formulario 12h.
 *
 * @param string $value Horario HH:MM.
 * @return array<string, string>
 */
function wpssb_prepare_frontend_rehearsal_time_parts( $value ) {
    $value = wpssb_sanitize_project_rehearsal_time( $value );

    if ( '' === $value ) {
        return [
            'hour'     => '',
            'minute'   => '',
            'meridiem' => '',
        ];
    }

    list( $hours, $minutes ) = array_map( 'intval', explode( ':', $value ) );

    return [
        'hour'     => (string) ( 0 === $hours % 12 ? 12 : $hours % 12 ),
        'minute'   => sprintf( '%02d', $minutes ),
        'meridiem' => $hours >= 12 ? 'pm' : 'am',
    ];
}

/**
 * Prepara una fila editable de disponibilidad para frontend.
 *
 * @param array<string, mixed> $slot Slot saneado o parcial.
 * @return array<string, mixed>
 */
function wpssb_prepare_frontend_rehearsal_slot_form_row( $slot = [] ) {
    $start = wpssb_sanitize_project_rehearsal_time( $slot['start'] ?? '' );
    $end   = wpssb_sanitize_project_rehearsal_time( $slot['end'] ?? '' );

    return [
        'id'          => sanitize_key( (string) ( $slot['id'] ?? '' ) ),
        'start'       => $start,
        'end'         => $end,
        'start_parts' => wpssb_prepare_frontend_rehearsal_time_parts( $start ),
        'end_parts'   => wpssb_prepare_frontend_rehearsal_time_parts( $end ),
    ];
}

/**
 * Interpreta un valor horario enviado desde el selector frontend.
 *
 * @param mixed $value Valor crudo.
 * @return array<string, string>
 */
function wpssb_parse_frontend_rehearsal_time_value( $value ) {
    if ( is_array( $value ) ) {
        $hour     = sanitize_text_field( (string) ( $value['hour'] ?? '' ) );
        $minute   = sanitize_text_field( (string) ( $value['minute'] ?? '' ) );
        $meridiem = sanitize_key( (string) ( $value['meridiem'] ?? '' ) );

        if ( '' === $hour && '' === $minute && '' === $meridiem ) {
            return [
                'state' => 'blank',
                'value' => '',
            ];
        }

        if ( '' === $hour || '' === $minute || '' === $meridiem ) {
            return [
                'state' => 'invalid',
                'value' => '',
            ];
        }

        if ( ! preg_match( '/^(?:[1-9]|1[0-2])$/', $hour ) || ! preg_match( '/^[0-5]\d$/', $minute ) || ! in_array( $meridiem, [ 'am', 'pm' ], true ) ) {
            return [
                'state' => 'invalid',
                'value' => '',
            ];
        }

        $hour_int   = (int) $hour;
        $minute_int = (int) $minute;

        if ( 'am' === $meridiem ) {
            $hour_int = 12 === $hour_int ? 0 : $hour_int;
        } else {
            $hour_int = 12 === $hour_int ? 12 : $hour_int + 12;
        }

        return [
            'state' => 'valid',
            'value' => sprintf( '%02d:%02d', $hour_int, $minute_int ),
        ];
    }

    $raw_value = trim( (string) $value );
    $time      = wpssb_sanitize_project_rehearsal_time( $raw_value );

    if ( '' === $raw_value ) {
        return [
            'state' => 'blank',
            'value' => '',
        ];
    }

    if ( '' === $time ) {
        return [
            'state' => 'invalid',
            'value' => '',
        ];
    }

    return [
        'state' => 'valid',
        'value' => $time,
    ];
}

/**
 * Convierte filas estructuradas del frontend a slots saneados.
 *
 * @param string $day  Día objetivo.
 * @param mixed  $rows Filas crudas.
 * @return array<string, mixed>
 */
function wpssb_parse_frontend_rehearsal_slot_rows( $day, $rows ) {
    $day    = wpssb_normalize_project_rehearsal_day( $day );
    $result = [
        'slots'   => [],
        'invalid' => [],
    ];

    if ( ! is_array( $rows ) ) {
        return $result;
    }

    foreach ( $rows as $row_key => $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }

        $start = wpssb_parse_frontend_rehearsal_time_value( $row['start'] ?? '' );
        $end   = wpssb_parse_frontend_rehearsal_time_value( $row['end'] ?? '' );

        if ( 'blank' === $start['state'] && 'blank' === $end['state'] ) {
            continue;
        }

        if ( 'valid' !== $start['state'] || 'valid' !== $end['state'] ) {
            $result['invalid'][] = sanitize_key( (string) $row_key );
            continue;
        }

        if ( wpssb_project_rehearsal_time_to_minutes( $start['value'] ) >= wpssb_project_rehearsal_time_to_minutes( $end['value'] ) ) {
            $result['invalid'][] = sanitize_key( (string) $row_key );
            continue;
        }

        $result['slots'][] = [
            'id'    => sanitize_key( ! empty( $row['id'] ) ? (string) $row['id'] : wp_generate_uuid4() ),
            'day'   => $day,
            'start' => $start['value'],
            'end'   => $end['value'],
        ];
    }

    return $result;
}

/**
 * Convierte rangos de texto del frontend a slots saneados.
 *
 * @param string $day  Día objetivo.
 * @param string $text Texto crudo.
 * @return array<string, mixed>
 */
function wpssb_parse_frontend_rehearsal_ranges( $day, $text ) {
    $day    = wpssb_normalize_project_rehearsal_day( $day );
    $text   = trim( (string) $text );
    $result = [
        'slots'   => [],
        'invalid' => [],
    ];

    if ( '' === $text ) {
        return $result;
    }

    $chunks = preg_split( '/[\r\n,;]+/', $text );

    foreach ( (array) $chunks as $chunk ) {
        $chunk = trim( (string) $chunk );

        if ( '' === $chunk ) {
            continue;
        }

        if ( ! preg_match( '/^(\d{1,2}):([0-5]\d)\s*-\s*(\d{1,2}):([0-5]\d)$/', $chunk, $matches ) ) {
            $result['invalid'][] = $chunk;
            continue;
        }

        $start_hour = (int) $matches[1];
        $start_min  = (int) $matches[2];
        $end_hour   = (int) $matches[3];
        $end_min    = (int) $matches[4];

        if ( $start_hour > 23 || $end_hour > 23 ) {
            $result['invalid'][] = $chunk;
            continue;
        }

        $start = sprintf( '%02d:%02d', $start_hour, $start_min );
        $end   = sprintf( '%02d:%02d', $end_hour, $end_min );

        if ( wpssb_project_rehearsal_time_to_minutes( $start ) >= wpssb_project_rehearsal_time_to_minutes( $end ) ) {
            $result['invalid'][] = $chunk;
            continue;
        }

        $result['slots'][] = [
            'id'    => wp_generate_uuid4(),
            'day'   => $day,
            'start' => $start,
            'end'   => $end,
        ];
    }

    return $result;
}

/**
 * Guarda la disponibilidad del usuario actual a partir del payload frontend.
 *
 * @param int   $project_id ID del proyecto.
 * @param int   $user_id    ID del usuario.
 * @param array $request    Datos crudos del formulario o AJAX.
 * @return array<string, mixed>|WP_Error
 */
function wpssb_save_frontend_rehearsal_availability_payload( $project_id, $user_id, $request ) {
    $project_id = absint( $project_id );
    $user_id    = absint( $user_id );
    $request    = is_array( $request ) ? $request : [];

    if ( $project_id <= 0 || WPSSB_PROJECT_POST_TYPE !== get_post_type( $project_id ) ) {
        return new WP_Error(
            'wpssb_invalid_project',
            __( 'El proyecto elegido ya no está disponible para tu usuario.', 'wp-song-study-blocks' ),
            [ 'status' => 404 ]
        );
    }

    if ( $user_id <= 0 || ! wpssb_user_can_access_project_rehearsals( $project_id, $user_id ) ) {
        return new WP_Error(
            'wpssb_invalid_project',
            __( 'El proyecto elegido ya no está disponible para tu usuario.', 'wp-song-study-blocks' ),
            [ 'status' => 404 ]
        );
    }

    $meta         = wpssb_get_project_rehearsal_meta( $project_id );
    $availability = wpssb_sanitize_project_rehearsal_availability( $meta['availability'] ?? [], $project_id );
    $by_user      = [];

    foreach ( $availability as $entry ) {
        if ( is_array( $entry ) && ! empty( $entry['user_id'] ) ) {
            $by_user[ absint( $entry['user_id'] ) ] = $entry;
        }
    }

    $blocked_days          = isset( $request['blocked_days'] ) ? wpssb_sanitize_project_rehearsal_days_list( wp_unslash( $request['blocked_days'] ) ) : [];
    $available_slots_raw   = isset( $request['available_slots'] ) && is_array( $request['available_slots'] ) ? wp_unslash( $request['available_slots'] ) : [];
    $unavailable_slots_raw = isset( $request['unavailable_slots'] ) && is_array( $request['unavailable_slots'] ) ? wp_unslash( $request['unavailable_slots'] ) : [];
    $available_ranges      = isset( $request['available_ranges'] ) && is_array( $request['available_ranges'] ) ? wp_unslash( $request['available_ranges'] ) : [];
    $unavailable_ranges    = isset( $request['unavailable_ranges'] ) && is_array( $request['unavailable_ranges'] ) ? wp_unslash( $request['unavailable_ranges'] ) : [];
    $slots                 = [];
    $unavailable_slots     = [];
    $uses_structured_rows  = ! empty( $available_slots_raw ) || ! empty( $unavailable_slots_raw );

    foreach ( wpssb_get_project_rehearsal_days() as $day ) {
        if ( in_array( $day, $blocked_days, true ) ) {
            continue;
        }

        if ( $uses_structured_rows ) {
            $available_result = wpssb_parse_frontend_rehearsal_slot_rows( $day, $available_slots_raw[ $day ] ?? [] );
            $blocked_result   = wpssb_parse_frontend_rehearsal_slot_rows( $day, $unavailable_slots_raw[ $day ] ?? [] );
        } else {
            $available_result = wpssb_parse_frontend_rehearsal_ranges( $day, isset( $available_ranges[ $day ] ) ? (string) $available_ranges[ $day ] : '' );
            $blocked_result   = wpssb_parse_frontend_rehearsal_ranges( $day, isset( $unavailable_ranges[ $day ] ) ? (string) $unavailable_ranges[ $day ] : '' );
        }

        if ( ! empty( $available_result['invalid'] ) || ! empty( $blocked_result['invalid'] ) ) {
            return new WP_Error(
                'wpssb_invalid_ranges',
                __( 'Corrige los horarios marcados antes de guardar tu disponibilidad.', 'wp-song-study-blocks' ),
                [ 'status' => 422 ]
            );
        }

        $slots             = array_merge( $slots, $available_result['slots'] );
        $unavailable_slots = array_merge( $unavailable_slots, $blocked_result['slots'] );
    }

    $user = get_user_by( 'id', $user_id );

    $by_user[ $user_id ] = [
        'user_id'           => $user_id,
        'nombre'            => $user instanceof WP_User ? sanitize_text_field( $user->display_name ) : '',
        'notes'             => isset( $request['availability_notes'] ) ? sanitize_textarea_field( wp_unslash( $request['availability_notes'] ) ) : '',
        'blocked_days'      => $blocked_days,
        'slots'             => $slots,
        'unavailable_slots' => $unavailable_slots,
        'updated_at'        => gmdate( 'c' ),
    ];

    $saved_meta   = wpssb_update_project_rehearsal_meta(
        $project_id,
        [
            'project_id'   => $project_id,
            'availability' => array_values( $by_user ),
            'sessions'     => $meta['sessions'] ?? [],
        ]
    );
    $saved_entry   = wpssb_get_project_rehearsal_member_entry( $saved_meta['availability'] ?? [], $user_id );
    $saved_summary = wpssb_get_frontend_rehearsal_member_availability_summary( $saved_entry );
    $updated_at    = sanitize_text_field( (string) ( $saved_entry['updated_at'] ?? '' ) );
    $updated_label = '';

    if ( '' !== $updated_at ) {
        $updated_timestamp = strtotime( $updated_at );
        if ( false !== $updated_timestamp ) {
            $updated_label = wp_date( 'j M Y · H:i', $updated_timestamp );
        }
    }

    return [
        'message'            => __( 'Disponibilidad guardada con éxito.', 'wp-song-study-blocks' ),
        'updated_at'         => $updated_at,
        'updated_label'      => $updated_label,
        'updated_label_text' => '' !== $updated_label
            ? sprintf( __( 'Actualizado: %s', 'wp-song-study-blocks' ), $updated_label )
            : '',
        'has_availability'   => ! empty( $saved_summary['has_availability'] ),
        'days_with_data'     => absint( $saved_summary['days_with_data'] ?? 0 ),
        'summary_text'       => sanitize_text_field( (string) ( $saved_summary['summary_text'] ?? '' ) ),
        'entry'              => $saved_entry,
    ];
}

/**
 * Guarda la disponibilidad del usuario actual desde frontend.
 *
 * @return void
 */
function wpssb_handle_frontend_rehearsal_availability_save() {
    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_request' );
    }

    if ( ! is_user_logged_in() ) {
        wpssb_redirect_frontend_rehearsal( 'login_required' );
    }

    if ( ! isset( $_POST['wpssb_rehearsal_availability_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpssb_rehearsal_availability_nonce'] ) ), 'wpssb_save_my_rehearsal_availability' ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_nonce' );
    }

    $project_id = isset( $_POST['project_id'] ) ? absint( wp_unslash( $_POST['project_id'] ) ) : 0;
    $user_id    = get_current_user_id();
    $result     = wpssb_save_frontend_rehearsal_availability_payload( $project_id, $user_id, $_POST );

    if ( is_wp_error( $result ) ) {
        $error_code = $result->get_error_code();

        if ( 'wpssb_invalid_ranges' === $error_code ) {
            wpssb_redirect_frontend_rehearsal( 'invalid_ranges', $project_id, 'availability' );
        }

        if ( 'wpssb_invalid_project' === $error_code ) {
            wpssb_redirect_frontend_rehearsal( 'invalid_project', $project_id, 'availability' );
        }

        if ( 'wpssb_forbidden' === $error_code ) {
            wpssb_redirect_frontend_rehearsal( 'forbidden', $project_id, 'availability' );
        }

        wpssb_redirect_frontend_rehearsal( 'invalid_request', $project_id, 'availability' );
    }

    wpssb_redirect_frontend_rehearsal( 'updated_availability', $project_id, 'availability' );
}
add_action( 'admin_post_wpssb_save_my_rehearsal_availability', 'wpssb_handle_frontend_rehearsal_availability_save' );
add_action( 'admin_post_nopriv_wpssb_save_my_rehearsal_availability', 'wpssb_handle_frontend_rehearsal_availability_save' );

/**
 * Crea una propuesta de ensayo desde frontend a partir de una ventana sugerida.
 *
 * @return void
 */
function wpssb_handle_frontend_rehearsal_proposal_create() {
    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_request' );
    }

    if ( ! is_user_logged_in() ) {
        wpssb_redirect_frontend_rehearsal( 'login_required' );
    }

    if ( ! isset( $_POST['wpssb_rehearsal_proposal_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpssb_rehearsal_proposal_nonce'] ) ), 'wpssb_create_rehearsal_proposal' ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_nonce' );
    }

    $project_id = isset( $_POST['project_id'] ) ? absint( wp_unslash( $_POST['project_id'] ) ) : 0;
    $user_id    = get_current_user_id();

    if ( $project_id <= 0 || ! wpssb_user_can_access_project_rehearsals( $project_id, $user_id ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_project', $project_id, 'calendar' );
    }

    $scheduled_for = sanitize_text_field( (string) wp_unslash( $_POST['scheduled_for'] ?? '' ) );
    $day           = sanitize_key( (string) wp_unslash( $_POST['slot_day'] ?? '' ) );
    $start_time    = wpssb_sanitize_project_rehearsal_time( wp_unslash( $_POST['slot_start'] ?? '' ) );
    $end_time      = wpssb_sanitize_project_rehearsal_time( wp_unslash( $_POST['slot_end'] ?? '' ) );
    $force_confirm = ! empty( $_POST['force_confirmed'] );

    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $scheduled_for ) || '' === $start_time || '' === $end_time || wpssb_project_rehearsal_time_to_minutes( $start_time ) >= wpssb_project_rehearsal_time_to_minutes( $end_time ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_session', $project_id, 'calendar' );
    }

    if ( ! in_array( $day, wpssb_get_project_rehearsal_days(), true ) ) {
        $day = '';
    }

    $day_label = '';
    if ( '' !== $day ) {
        $day_labels = wpssb_get_project_rehearsal_day_labels();
        $day_label  = isset( $day_labels[ $day ] ) ? $day_labels[ $day ] : ucfirst( $day );
    }

    $user = get_user_by( 'id', $user_id );
    $meta = wpssb_get_project_rehearsal_meta( $project_id );

    $new_session = [
        'id'             => wp_generate_uuid4(),
        'scheduled_for'  => $scheduled_for,
        'start_time'     => $start_time,
        'end_time'       => $end_time,
        'location'       => sanitize_text_field( (string) wp_unslash( $_POST['location'] ?? '' ) ),
        'status'         => $force_confirm ? 'confirmed' : 'proposed',
        'created_by'     => $user_id,
        'focus'          => sanitize_text_field( (string) wp_unslash( $_POST['focus'] ?? '' ) ),
        'reviewed_items' => [],
        'notes'          => sanitize_textarea_field( (string) wp_unslash( $_POST['proposal_notes'] ?? '' ) ),
        'attendance'     => [],
        'votes'          => [
            [
                'user_id' => $user_id,
                'nombre'  => $user instanceof WP_User ? sanitize_text_field( $user->display_name ) : '',
                'vote'    => $force_confirm ? 'yes' : 'pending',
                'comment' => $force_confirm
                    ? __( 'Confirmación forzada desde frontend.', 'wp-song-study-blocks' )
                    : __( 'Propuesta creada desde una ventana sugerida.', 'wp-song-study-blocks' ),
            ],
        ],
        'calendar'       => [],
    ];

    if ( '' === $new_session['focus'] ) {
        $new_session['focus'] = '' !== $day_label
            ? sprintf( __( 'Ensayo propuesto (%s)', 'wp-song-study-blocks' ), $day_label )
            : __( 'Ensayo propuesto', 'wp-song-study-blocks' );
    }

    if ( '' !== $day_label ) {
        $new_session['notes'] = trim(
            implode(
                "\n\n",
                array_filter(
                    [
                        $new_session['notes'],
                        sprintf( __( 'Ventana base sugerida: %1$s %2$s - %3$s.', 'wp-song-study-blocks' ), $day_label, $start_time, $end_time ),
                    ]
                )
            )
        );
    }

    $sessions     = wpssb_sanitize_project_rehearsal_sessions( $meta['sessions'] ?? [], $project_id );
    $new_session  = wpssb_sanitize_project_rehearsal_sessions( [ $new_session ], $project_id );
    $new_session  = ! empty( $new_session[0] ) && is_array( $new_session[0] ) ? $new_session[0] : [];

    if ( empty( $new_session ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_session', $project_id, 'calendar' );
    }

    $new_session_id = sanitize_key( (string) $new_session['id'] );
    $sessions[]   = $new_session;

    wpssb_update_project_rehearsal_meta(
        $project_id,
        [
            'project_id'   => $project_id,
            'availability' => $meta['availability'] ?? [],
            'sessions'     => $sessions,
        ]
    );

    $calendar_sync_result = null;

    if (
        '' !== $new_session_id
        && function_exists( 'wpssb_should_auto_sync_project_rehearsal_session' )
        && wpssb_should_auto_sync_project_rehearsal_session( $new_session )
        && function_exists( 'wpssb_sync_project_rehearsal_google_calendar' )
    ) {
        $calendar_sync_result = wpssb_attempt_project_rehearsal_google_calendar_sync( $project_id, $new_session_id );
    }

    if ( $force_confirm ) {
        wpssb_notify_project_rehearsal_force_confirmed( $project_id, [], $new_session, $user_id );
    } else {
        wpssb_notify_project_rehearsal_proposal_created( $project_id, $new_session, $user_id );
    }

    if ( is_wp_error( $calendar_sync_result ) ) {
        wpssb_redirect_frontend_rehearsal( 'calendar_sync_failed', $project_id, 'calendar' );
    }

    if ( is_array( $calendar_sync_result ) ) {
        wpssb_redirect_frontend_rehearsal( 'calendar_synced', $project_id, 'calendar' );
    }

    wpssb_redirect_frontend_rehearsal( $force_confirm ? 'forced_confirmed' : 'created_proposal', $project_id, 'calendar' );
}
add_action( 'admin_post_wpssb_create_frontend_rehearsal_proposal', 'wpssb_handle_frontend_rehearsal_proposal_create' );
add_action( 'admin_post_nopriv_wpssb_create_frontend_rehearsal_proposal', 'wpssb_handle_frontend_rehearsal_proposal_create' );

/**
 * Indica si un usuario puede editar o eliminar una propuesta pública.
 *
 * @param array<string, mixed> $session Sesión saneada.
 * @param int                  $user_id Usuario actual.
 * @return bool
 */
function wpssb_frontend_user_can_edit_rehearsal_proposal( $session, $user_id ) {
    if ( ! is_array( $session ) ) {
        return false;
    }

    $user_id    = absint( $user_id );
    $created_by = absint( $session['created_by'] ?? 0 );
    $status     = sanitize_key( (string) ( $session['status'] ?? '' ) );

    return $user_id > 0
        && $created_by > 0
        && $created_by === $user_id
        && in_array( $status, [ 'proposed', 'voting' ], true );
}

/**
 * Indica si un usuario puede cancelar un ensayo ya confirmado.
 *
 * @param array<string, mixed> $session Sesión saneada.
 * @param int                  $user_id Usuario actual.
 * @return bool
 */
function wpssb_frontend_user_can_cancel_rehearsal_session( $session, $user_id ) {
    if ( ! is_array( $session ) ) {
        return false;
    }

    $user_id    = absint( $user_id );
    $created_by = absint( $session['created_by'] ?? 0 );
    $status     = sanitize_key( (string) ( $session['status'] ?? '' ) );

    return $user_id > 0
        && $created_by > 0
        && $created_by === $user_id
        && 'confirmed' === $status;
}

/**
 * Fuerza la confirmación de una propuesta existente desde frontend.
 *
 * @return void
 */
function wpssb_handle_frontend_rehearsal_force_confirm() {
    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_request' );
    }

    if ( ! is_user_logged_in() ) {
        wpssb_redirect_frontend_rehearsal( 'login_required' );
    }

    if ( ! isset( $_POST['wpssb_rehearsal_force_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpssb_rehearsal_force_nonce'] ) ), 'wpssb_force_rehearsal_confirmation' ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_nonce' );
    }

    $project_id = isset( $_POST['project_id'] ) ? absint( wp_unslash( $_POST['project_id'] ) ) : 0;
    $session_id = sanitize_key( (string) wp_unslash( $_POST['session_id'] ?? '' ) );
    $user_id    = get_current_user_id();

    if ( $project_id <= 0 || '' === $session_id || ! wpssb_user_can_access_project_rehearsals( $project_id, $user_id ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_project', $project_id, 'calendar' );
    }

    $meta     = wpssb_get_project_rehearsal_meta( $project_id );
    $sessions = wpssb_sanitize_project_rehearsal_sessions( $meta['sessions'] ?? [], $project_id );
    $updated  = false;
    $synced_session_id = '';
    $user     = get_user_by( 'id', $user_id );
    $previous_session = [];
    $updated_session  = [];
    $calendar_sync_result = null;

    foreach ( $sessions as $index => $session ) {
        if ( ! is_array( $session ) || $session_id !== ( $session['id'] ?? '' ) ) {
            continue;
        }

        if ( ! in_array( sanitize_key( (string) ( $session['status'] ?? '' ) ), [ 'proposed', 'voting', 'confirmed' ], true ) ) {
            wpssb_redirect_frontend_rehearsal( 'session_locked', $project_id, 'calendar' );
        }

        $previous_session = $session;
        $member_vote_found = false;

        foreach ( (array) ( $session['votes'] ?? [] ) as $vote_index => $vote_entry ) {
            if ( ! is_array( $vote_entry ) || $user_id !== absint( $vote_entry['user_id'] ?? 0 ) ) {
                continue;
            }

            $member_vote_found = true;

            if ( ! in_array( sanitize_key( (string) ( $vote_entry['vote'] ?? 'pending' ) ), [ 'yes', 'maybe' ], true ) ) {
                $sessions[ $index ]['votes'][ $vote_index ]['vote']    = 'yes';
                $sessions[ $index ]['votes'][ $vote_index ]['comment'] = __( 'Confirmado por excepción desde frontend.', 'wp-song-study-blocks' );
                $sessions[ $index ]['votes'][ $vote_index ]['nombre']  = $user instanceof WP_User ? sanitize_text_field( $user->display_name ) : sanitize_text_field( (string) ( $vote_entry['nombre'] ?? '' ) );
            }
        }

        if ( ! $member_vote_found ) {
            $sessions[ $index ]['votes'][] = [
                'user_id' => $user_id,
                'nombre'  => $user instanceof WP_User ? sanitize_text_field( $user->display_name ) : '',
                'vote'    => 'yes',
                'comment' => __( 'Confirmado por excepción desde frontend.', 'wp-song-study-blocks' ),
            ];
        }

        $sessions[ $index ]['status'] = 'confirmed';
        $sessions[ $index ]           = wpssb_refresh_project_rehearsal_session_vote_state( $sessions[ $index ], $project_id );
        $updated                      = true;
        $synced_session_id            = sanitize_key( (string) ( $sessions[ $index ]['id'] ?? '' ) );
        $updated_session              = $sessions[ $index ];
        break;
    }

    if ( ! $updated ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_session', $project_id, 'calendar' );
    }

    wpssb_update_project_rehearsal_meta(
        $project_id,
        [
            'project_id'   => $project_id,
            'availability' => $meta['availability'] ?? [],
            'sessions'     => $sessions,
        ]
    );

    if (
        '' !== $synced_session_id
        && function_exists( 'wpssb_sync_project_rehearsal_google_calendar' )
    ) {
        $calendar_sync_result = wpssb_attempt_project_rehearsal_google_calendar_sync( $project_id, $synced_session_id );
    }

    wpssb_notify_project_rehearsal_force_confirmed( $project_id, $previous_session, $updated_session, $user_id );

    if ( is_wp_error( $calendar_sync_result ) ) {
        wpssb_redirect_frontend_rehearsal( 'calendar_sync_failed', $project_id, 'calendar' );
    }

    wpssb_redirect_frontend_rehearsal( is_array( $calendar_sync_result ) ? 'calendar_synced' : 'forced_confirmed', $project_id, 'calendar' );
}
add_action( 'admin_post_wpssb_force_frontend_rehearsal_confirmation', 'wpssb_handle_frontend_rehearsal_force_confirm' );
add_action( 'admin_post_nopriv_wpssb_force_frontend_rehearsal_confirmation', 'wpssb_handle_frontend_rehearsal_force_confirm' );

/**
 * Reintenta la sincronización de un ensayo confirmado desde frontend.
 *
 * @return void
 */
function wpssb_handle_frontend_rehearsal_calendar_sync() {
    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_request' );
    }

    if ( ! is_user_logged_in() ) {
        wpssb_redirect_frontend_rehearsal( 'login_required' );
    }

    if ( ! isset( $_POST['wpssb_rehearsal_calendar_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpssb_rehearsal_calendar_nonce'] ) ), 'wpssb_sync_frontend_rehearsal_calendar' ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_nonce' );
    }

    $project_id = isset( $_POST['project_id'] ) ? absint( wp_unslash( $_POST['project_id'] ) ) : 0;
    $session_id = sanitize_key( (string) wp_unslash( $_POST['session_id'] ?? '' ) );
    $user_id    = get_current_user_id();

    if ( $project_id <= 0 || '' === $session_id || ! wpssb_user_can_access_project_rehearsals( $project_id, $user_id ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_project', $project_id, 'calendar' );
    }

    $result = wpssb_attempt_project_rehearsal_google_calendar_sync( $project_id, $session_id );

    wpssb_redirect_frontend_rehearsal( is_wp_error( $result ) ? 'calendar_sync_failed' : 'calendar_synced', $project_id, 'calendar' );
}
add_action( 'admin_post_wpssb_sync_frontend_rehearsal_calendar', 'wpssb_handle_frontend_rehearsal_calendar_sync' );
add_action( 'admin_post_nopriv_wpssb_sync_frontend_rehearsal_calendar', 'wpssb_handle_frontend_rehearsal_calendar_sync' );

/**
 * Cancela un ensayo confirmado desde frontend.
 *
 * @return void
 */
function wpssb_handle_frontend_rehearsal_cancel() {
    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_request' );
    }

    if ( ! is_user_logged_in() ) {
        wpssb_redirect_frontend_rehearsal( 'login_required' );
    }

    if ( ! isset( $_POST['wpssb_rehearsal_cancel_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpssb_rehearsal_cancel_nonce'] ) ), 'wpssb_cancel_rehearsal_session' ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_nonce' );
    }

    $project_id = isset( $_POST['project_id'] ) ? absint( wp_unslash( $_POST['project_id'] ) ) : 0;
    $session_id = sanitize_key( (string) wp_unslash( $_POST['session_id'] ?? '' ) );
    $user_id    = get_current_user_id();
    $reason     = sanitize_textarea_field( (string) wp_unslash( $_POST['cancellation_reason'] ?? '' ) );

    if ( $project_id <= 0 || '' === $session_id || ! wpssb_user_can_access_project_rehearsals( $project_id, $user_id ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_project', $project_id, 'calendar' );
    }

    if ( '' === trim( $reason ) ) {
        wpssb_redirect_frontend_rehearsal( 'missing_cancel_reason', $project_id, 'calendar' );
    }

    $meta             = wpssb_get_project_rehearsal_meta( $project_id );
    $sessions         = wpssb_sanitize_project_rehearsal_sessions( $meta['sessions'] ?? [], $project_id );
    $updated          = false;
    $previous_session = [];
    $updated_session  = [];

    foreach ( $sessions as $index => $session ) {
        if ( ! is_array( $session ) || $session_id !== ( $session['id'] ?? '' ) ) {
            continue;
        }

        if ( ! wpssb_frontend_user_can_cancel_rehearsal_session( $session, $user_id ) ) {
            wpssb_redirect_frontend_rehearsal( 'forbidden', $project_id, 'calendar' );
        }

        $previous_session = $session;

        $sessions[ $index ]['status']              = 'cancelled';
        $sessions[ $index ]['cancellation_reason'] = $reason;
        $sessions[ $index ]['cancelled_by']        = $user_id;
        $sessions[ $index ]['cancelled_at']        = gmdate( 'c' );
        $sessions[ $index ]                        = wpssb_refresh_project_rehearsal_session_vote_state( $sessions[ $index ], $project_id );
        $updated_session                           = $sessions[ $index ];
        $updated                                   = true;
        break;
    }

    if ( ! $updated ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_session', $project_id, 'calendar' );
    }

    wpssb_update_project_rehearsal_meta(
        $project_id,
        [
            'project_id'   => $project_id,
            'availability' => $meta['availability'] ?? [],
            'sessions'     => $sessions,
        ]
    );

    $calendar_sync_result = wpssb_attempt_project_rehearsal_google_calendar_sync( $project_id, $session_id );

    wpssb_notify_project_rehearsal_cancelled( $project_id, $previous_session, $updated_session, $user_id );

    if ( is_wp_error( $calendar_sync_result ) ) {
        wpssb_redirect_frontend_rehearsal( 'cancelled_calendar_failed', $project_id, 'calendar' );
    }

    wpssb_redirect_frontend_rehearsal( 'cancelled_session', $project_id, 'calendar' );
}
add_action( 'admin_post_wpssb_cancel_frontend_rehearsal_session', 'wpssb_handle_frontend_rehearsal_cancel' );
add_action( 'admin_post_nopriv_wpssb_cancel_frontend_rehearsal_session', 'wpssb_handle_frontend_rehearsal_cancel' );

/**
 * Actualiza una propuesta de ensayo desde frontend.
 *
 * @return void
 */
function wpssb_handle_frontend_rehearsal_proposal_update() {
    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_request' );
    }

    if ( ! is_user_logged_in() ) {
        wpssb_redirect_frontend_rehearsal( 'login_required' );
    }

    if ( ! isset( $_POST['wpssb_rehearsal_update_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpssb_rehearsal_update_nonce'] ) ), 'wpssb_update_rehearsal_proposal' ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_nonce' );
    }

    $project_id = isset( $_POST['project_id'] ) ? absint( wp_unslash( $_POST['project_id'] ) ) : 0;
    $session_id = sanitize_key( (string) wp_unslash( $_POST['session_id'] ?? '' ) );
    $user_id    = get_current_user_id();

    if ( $project_id <= 0 || '' === $session_id || ! wpssb_user_can_access_project_rehearsals( $project_id, $user_id ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_project', $project_id, 'calendar' );
    }

    $scheduled_for = sanitize_text_field( (string) wp_unslash( $_POST['scheduled_for'] ?? '' ) );
    $start_time    = wpssb_sanitize_project_rehearsal_time( wp_unslash( $_POST['start_time'] ?? '' ) );
    $end_time      = wpssb_sanitize_project_rehearsal_time( wp_unslash( $_POST['end_time'] ?? '' ) );

    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $scheduled_for ) || '' === $start_time || '' === $end_time || wpssb_project_rehearsal_time_to_minutes( $start_time ) >= wpssb_project_rehearsal_time_to_minutes( $end_time ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_session', $project_id, 'calendar' );
    }

    $meta     = wpssb_get_project_rehearsal_meta( $project_id );
    $sessions = wpssb_sanitize_project_rehearsal_sessions( $meta['sessions'] ?? [], $project_id );
    $updated  = false;
    $synced_session_id = '';

    foreach ( $sessions as $index => $session ) {
        if ( ! is_array( $session ) || $session_id !== ( $session['id'] ?? '' ) ) {
            continue;
        }

        if ( ! in_array( sanitize_key( (string) ( $session['status'] ?? '' ) ), [ 'proposed', 'voting' ], true ) ) {
            wpssb_redirect_frontend_rehearsal( 'session_locked', $project_id, 'calendar' );
        }

        if ( ! wpssb_frontend_user_can_edit_rehearsal_proposal( $session, $user_id ) ) {
            wpssb_redirect_frontend_rehearsal( 'forbidden', $project_id, 'calendar' );
        }

        $sessions[ $index ]['scheduled_for'] = $scheduled_for;
        $sessions[ $index ]['start_time']    = $start_time;
        $sessions[ $index ]['end_time']      = $end_time;
        $sessions[ $index ]['location']      = sanitize_text_field( (string) wp_unslash( $_POST['location'] ?? '' ) );
        $sessions[ $index ]['focus']         = sanitize_text_field( (string) wp_unslash( $_POST['focus'] ?? '' ) );
        $sessions[ $index ]['notes']         = sanitize_textarea_field( (string) wp_unslash( $_POST['proposal_notes'] ?? '' ) );
        $sessions[ $index ]                  = wpssb_refresh_project_rehearsal_session_vote_state( $sessions[ $index ], $project_id );
        $updated                             = true;
        $synced_session_id                   = sanitize_key( (string) ( $sessions[ $index ]['id'] ?? '' ) );
        break;
    }

    if ( ! $updated ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_session', $project_id, 'calendar' );
    }

    wpssb_update_project_rehearsal_meta(
        $project_id,
        [
            'project_id'   => $project_id,
            'availability' => $meta['availability'] ?? [],
            'sessions'     => $sessions,
        ]
    );

    if (
        '' !== $synced_session_id
        && function_exists( 'wpssb_should_auto_sync_project_rehearsal_session' )
        && isset( $sessions[ $index ] )
        && is_array( $sessions[ $index ] )
        && wpssb_should_auto_sync_project_rehearsal_session( $sessions[ $index ] )
        && function_exists( 'wpssb_sync_project_rehearsal_google_calendar' )
    ) {
        wpssb_attempt_project_rehearsal_google_calendar_sync( $project_id, $synced_session_id );
    }

    wpssb_redirect_frontend_rehearsal( 'updated_proposal', $project_id, 'calendar' );
}
add_action( 'admin_post_wpssb_update_frontend_rehearsal_proposal', 'wpssb_handle_frontend_rehearsal_proposal_update' );
add_action( 'admin_post_nopriv_wpssb_update_frontend_rehearsal_proposal', 'wpssb_handle_frontend_rehearsal_proposal_update' );

/**
 * Elimina una propuesta de ensayo desde frontend.
 *
 * @return void
 */
function wpssb_handle_frontend_rehearsal_proposal_delete() {
    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_request' );
    }

    if ( ! is_user_logged_in() ) {
        wpssb_redirect_frontend_rehearsal( 'login_required' );
    }

    if ( ! isset( $_POST['wpssb_rehearsal_delete_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpssb_rehearsal_delete_nonce'] ) ), 'wpssb_delete_rehearsal_proposal' ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_nonce' );
    }

    $project_id = isset( $_POST['project_id'] ) ? absint( wp_unslash( $_POST['project_id'] ) ) : 0;
    $session_id = sanitize_key( (string) wp_unslash( $_POST['session_id'] ?? '' ) );
    $user_id    = get_current_user_id();

    if ( $project_id <= 0 || '' === $session_id || ! wpssb_user_can_access_project_rehearsals( $project_id, $user_id ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_project', $project_id, 'calendar' );
    }

    $meta     = wpssb_get_project_rehearsal_meta( $project_id );
    $sessions = wpssb_sanitize_project_rehearsal_sessions( $meta['sessions'] ?? [], $project_id );
    $deleted  = false;
    $deleted_session = [];

    foreach ( $sessions as $index => $session ) {
        if ( ! is_array( $session ) || $session_id !== ( $session['id'] ?? '' ) ) {
            continue;
        }

        if ( ! wpssb_frontend_user_can_edit_rehearsal_proposal( $session, $user_id ) ) {
            wpssb_redirect_frontend_rehearsal( 'forbidden', $project_id, 'calendar' );
        }

        $deleted_session = $session;
        unset( $sessions[ $index ] );
        $deleted = true;
        break;
    }

    if ( ! $deleted ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_session', $project_id, 'calendar' );
    }

    wpssb_update_project_rehearsal_meta(
        $project_id,
        [
            'project_id'   => $project_id,
            'availability' => $meta['availability'] ?? [],
            'sessions'     => array_values( $sessions ),
        ]
    );

    wpssb_notify_project_rehearsal_proposal_deleted( $project_id, $deleted_session, $user_id );

    wpssb_redirect_frontend_rehearsal( 'deleted_proposal', $project_id, 'calendar' );
}
add_action( 'admin_post_wpssb_delete_frontend_rehearsal_proposal', 'wpssb_handle_frontend_rehearsal_proposal_delete' );
add_action( 'admin_post_nopriv_wpssb_delete_frontend_rehearsal_proposal', 'wpssb_handle_frontend_rehearsal_proposal_delete' );

/**
 * Guarda observaciones y asistencia de una sesión dentro de la bitácora.
 *
 * @param int                  $project_id Proyecto actual.
 * @param string               $session_id Sesión objetivo.
 * @param int                  $user_id    Usuario actual.
 * @param array<string, mixed> $request    Solicitud cruda.
 * @return array<string, mixed>|WP_Error
 */
function wpssb_save_frontend_rehearsal_logbook_payload( $project_id, $session_id, $user_id, $request ) {
    $project_id = absint( $project_id );
    $session_id = sanitize_key( (string) $session_id );
    $user_id    = absint( $user_id );
    $request    = is_array( $request ) ? $request : [];

    if ( $project_id <= 0 || '' === $session_id || $user_id <= 0 || ! wpssb_user_can_access_project_rehearsals( $project_id, $user_id ) ) {
        return new WP_Error(
            'wpssb_invalid_project',
            __( 'No tienes permiso para editar la bitácora de este proyecto.', 'wp-song-study-blocks' ),
            [ 'status' => 403 ]
        );
    }

    $meta     = wpssb_get_project_rehearsal_meta( $project_id );
    $sessions = wpssb_sanitize_project_rehearsal_sessions( $meta['sessions'] ?? [], $project_id );
    $updated  = false;

    foreach ( $sessions as $index => $session ) {
        if ( ! is_array( $session ) || $session_id !== sanitize_key( (string) ( $session['id'] ?? '' ) ) ) {
            continue;
        }

        if ( ! wpssb_frontend_rehearsal_session_is_logbook_available( $session ) ) {
            return new WP_Error(
                'wpssb_logbook_locked',
                __( 'La bitácora de este ensayo se habilita cuando llega la hora programada.', 'wp-song-study-blocks' ),
                [ 'status' => 409 ]
            );
        }

        if ( wpssb_project_rehearsal_session_is_expired_proposal( $session ) ) {
            return new WP_Error(
                'wpssb_logbook_locked',
                __( 'Esta propuesta expiró y queda en bitácora solo como referencia de votos.', 'wp-song-study-blocks' ),
                [ 'status' => 409 ]
            );
        }

        $attendance_request = isset( $request['attendance'] ) && is_array( $request['attendance'] )
            ? wp_unslash( $request['attendance'] )
            : [];
        $attendance_entries = [];

        foreach ( $attendance_request as $attendance_user_id => $attendance_entry ) {
            if ( ! is_array( $attendance_entry ) ) {
                continue;
            }

            $attendance_entries[] = [
                'user_id' => absint( $attendance_user_id ),
                'status'  => sanitize_key( (string) ( $attendance_entry['status'] ?? 'pending' ) ),
                'comment' => sanitize_text_field( (string) ( $attendance_entry['comment'] ?? '' ) ),
            ];
        }

        $sessions[ $index ]['reviewed_items'] = wpssb_sanitize_project_rehearsal_reviewed_items(
            isset( $request['logbook_reviewed_items'] ) ? wp_unslash( $request['logbook_reviewed_items'] ) : ''
        );
        $sessions[ $index ]['notes']          = sanitize_textarea_field( (string) wp_unslash( $request['logbook_notes'] ?? '' ) );
        $sessions[ $index ]['attendance']     = wpssb_sanitize_project_rehearsal_attendance( $attendance_entries, $project_id );
        $updated                              = true;
        break;
    }

    if ( ! $updated ) {
        return new WP_Error(
            'wpssb_invalid_logbook_session',
            __( 'Ese ensayo ya no está disponible dentro de la bitácora.', 'wp-song-study-blocks' ),
            [ 'status' => 404 ]
        );
    }

    $saved_meta     = wpssb_update_project_rehearsal_meta(
        $project_id,
        [
            'project_id'   => $project_id,
            'availability' => $meta['availability'] ?? [],
            'sessions'     => $sessions,
        ]
    );
    $saved_sessions = wpssb_sanitize_project_rehearsal_sessions( $saved_meta['sessions'] ?? [], $project_id );
    $saved_session  = wpssb_get_project_rehearsal_session_entry( $saved_sessions, $session_id );
    $updated_at     = sanitize_text_field( (string) ( $saved_meta['updated_at_gmt'] ?? '' ) );
    $updated_label  = '';

    if ( '' !== $updated_at ) {
        $updated_timestamp = strtotime( $updated_at );
        if ( false !== $updated_timestamp ) {
            $updated_label = wp_date( 'j M Y · H:i', $updated_timestamp );
        }
    }

    return [
        'message'            => __( 'Bitácora guardada con éxito.', 'wp-song-study-blocks' ),
        'updated_at'         => $updated_at,
        'updated_label'      => $updated_label,
        'updated_label_text' => '' !== $updated_label
            ? sprintf( __( 'Bitácora guardada: %s', 'wp-song-study-blocks' ), $updated_label )
            : '',
        'session'            => $saved_session,
    ];
}

/**
 * Guarda la bitácora de un ensayo desde frontend.
 *
 * @return void
 */
function wpssb_handle_frontend_rehearsal_logbook_save() {
    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_request' );
    }

    if ( ! is_user_logged_in() ) {
        wpssb_redirect_frontend_rehearsal( 'login_required' );
    }

    if ( ! isset( $_POST['wpssb_rehearsal_logbook_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpssb_rehearsal_logbook_nonce'] ) ), 'wpssb_save_rehearsal_logbook' ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_nonce' );
    }

    $project_id = isset( $_POST['project_id'] ) ? absint( wp_unslash( $_POST['project_id'] ) ) : 0;
    $session_id = sanitize_key( (string) wp_unslash( $_POST['session_id'] ?? '' ) );
    $user_id    = get_current_user_id();
    $result     = wpssb_save_frontend_rehearsal_logbook_payload( $project_id, $session_id, $user_id, $_POST );

    if ( is_wp_error( $result ) ) {
        $error_code = $result->get_error_code();

        if ( 'wpssb_logbook_locked' === $error_code ) {
            wpssb_redirect_frontend_rehearsal( 'logbook_locked', $project_id, 'logbook' );
        }

        if ( 'wpssb_invalid_logbook_session' === $error_code ) {
            wpssb_redirect_frontend_rehearsal( 'invalid_logbook_session', $project_id, 'logbook' );
        }

        if ( 'wpssb_invalid_project' === $error_code ) {
            wpssb_redirect_frontend_rehearsal( 'invalid_project', $project_id, 'logbook' );
        }

        wpssb_redirect_frontend_rehearsal( 'forbidden', $project_id, 'logbook' );
    }

    wpssb_redirect_frontend_rehearsal( 'updated_logbook', $project_id, 'logbook' );
}
add_action( 'admin_post_wpssb_save_frontend_rehearsal_logbook', 'wpssb_handle_frontend_rehearsal_logbook_save' );
add_action( 'admin_post_nopriv_wpssb_save_frontend_rehearsal_logbook', 'wpssb_handle_frontend_rehearsal_logbook_save' );

/**
 * Autosave AJAX para propuestas públicas.
 *
 * @return void
 */
function wpssb_handle_frontend_rehearsal_proposal_autosave() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => __( 'Necesitas iniciar sesión.', 'wp-song-study-blocks' ) ], 401 );
    }

    check_ajax_referer( 'wpssb_autosave_rehearsal_proposal', 'nonce' );

    $project_id = isset( $_POST['project_id'] ) ? absint( wp_unslash( $_POST['project_id'] ) ) : 0;
    $session_id = sanitize_key( (string) wp_unslash( $_POST['session_id'] ?? '' ) );
    $user_id    = get_current_user_id();

    if ( $project_id <= 0 || '' === $session_id || ! wpssb_user_can_access_project_rehearsals( $project_id, $user_id ) ) {
        wp_send_json_error( [ 'message' => __( 'No tienes permiso para editar esta propuesta.', 'wp-song-study-blocks' ) ], 403 );
    }

    $scheduled_for = sanitize_text_field( (string) wp_unslash( $_POST['scheduled_for'] ?? '' ) );
    $start_time    = wpssb_sanitize_project_rehearsal_time( wp_unslash( $_POST['start_time'] ?? '' ) );
    $end_time      = wpssb_sanitize_project_rehearsal_time( wp_unslash( $_POST['end_time'] ?? '' ) );

    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $scheduled_for ) || '' === $start_time || '' === $end_time || wpssb_project_rehearsal_time_to_minutes( $start_time ) >= wpssb_project_rehearsal_time_to_minutes( $end_time ) ) {
        wp_send_json_error( [ 'message' => __( 'Completa una fecha y un rango horario válidos.', 'wp-song-study-blocks' ) ], 422 );
    }

    $meta     = wpssb_get_project_rehearsal_meta( $project_id );
    $sessions = wpssb_sanitize_project_rehearsal_sessions( $meta['sessions'] ?? [], $project_id );
    $updated  = false;
    $synced_session_id = '';

    foreach ( $sessions as $index => $session ) {
        if ( ! is_array( $session ) || $session_id !== ( $session['id'] ?? '' ) ) {
            continue;
        }

        if ( ! wpssb_frontend_user_can_edit_rehearsal_proposal( $session, $user_id ) ) {
            wp_send_json_error( [ 'message' => __( 'Solo quien creó la propuesta puede editarla o eliminarla.', 'wp-song-study-blocks' ) ], 403 );
        }

        $sessions[ $index ]['scheduled_for'] = $scheduled_for;
        $sessions[ $index ]['start_time']    = $start_time;
        $sessions[ $index ]['end_time']      = $end_time;
        $sessions[ $index ]['location']      = sanitize_text_field( (string) wp_unslash( $_POST['location'] ?? '' ) );
        $sessions[ $index ]['focus']         = sanitize_text_field( (string) wp_unslash( $_POST['focus'] ?? '' ) );
        $sessions[ $index ]['notes']         = sanitize_textarea_field( (string) wp_unslash( $_POST['proposal_notes'] ?? '' ) );
        $sessions[ $index ]                  = wpssb_refresh_project_rehearsal_session_vote_state( $sessions[ $index ], $project_id );
        $synced_session_id                   = sanitize_key( (string) ( $sessions[ $index ]['id'] ?? '' ) );
        $updated                             = true;
        break;
    }

    if ( ! $updated ) {
        wp_send_json_error( [ 'message' => __( 'La propuesta ya no está disponible.', 'wp-song-study-blocks' ) ], 404 );
    }

    wpssb_update_project_rehearsal_meta(
        $project_id,
        [
            'project_id'   => $project_id,
            'availability' => $meta['availability'] ?? [],
            'sessions'     => $sessions,
        ]
    );

    if (
        '' !== $synced_session_id
        && isset( $sessions[ $index ] )
        && is_array( $sessions[ $index ] )
        && function_exists( 'wpssb_should_auto_sync_project_rehearsal_session' )
        && wpssb_should_auto_sync_project_rehearsal_session( $sessions[ $index ] )
        && function_exists( 'wpssb_sync_project_rehearsal_google_calendar' )
    ) {
        wpssb_attempt_project_rehearsal_google_calendar_sync( $project_id, $synced_session_id );
    }

    wp_send_json_success(
        [
            'message' => __( 'Cambios guardados.', 'wp-song-study-blocks' ),
        ]
    );
}
add_action( 'wp_ajax_wpssb_autosave_frontend_rehearsal_proposal', 'wpssb_handle_frontend_rehearsal_proposal_autosave' );

/**
 * Autosave AJAX para bitácora de ensayo.
 *
 * @return void
 */
function wpssb_handle_frontend_rehearsal_logbook_autosave() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => __( 'Necesitas iniciar sesión.', 'wp-song-study-blocks' ) ], 401 );
    }

    check_ajax_referer( 'wpssb_autosave_rehearsal_logbook', 'nonce' );

    $project_id = isset( $_POST['project_id'] ) ? absint( wp_unslash( $_POST['project_id'] ) ) : 0;
    $session_id = sanitize_key( (string) wp_unslash( $_POST['session_id'] ?? '' ) );
    $user_id    = get_current_user_id();
    $result     = wpssb_save_frontend_rehearsal_logbook_payload( $project_id, $session_id, $user_id, $_POST );

    if ( is_wp_error( $result ) ) {
        $status_code = 400;
        $error_data  = $result->get_error_data();

        if ( is_array( $error_data ) && ! empty( $error_data['status'] ) ) {
            $status_code = absint( $error_data['status'] );
        }

        wp_send_json_error(
            [
                'message' => sanitize_text_field( (string) $result->get_error_message() ),
            ],
            $status_code
        );
    }

    wp_send_json_success( $result );
}
add_action( 'wp_ajax_wpssb_autosave_frontend_rehearsal_logbook', 'wpssb_handle_frontend_rehearsal_logbook_autosave' );

/**
 * Autosave AJAX para disponibilidad personal.
 *
 * @return void
 */
function wpssb_handle_frontend_rehearsal_availability_autosave() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( [ 'message' => __( 'Necesitas iniciar sesión.', 'wp-song-study-blocks' ) ], 401 );
    }

    check_ajax_referer( 'wpssb_autosave_rehearsal_availability', 'nonce' );

    $project_id = isset( $_POST['project_id'] ) ? absint( wp_unslash( $_POST['project_id'] ) ) : 0;
    $user_id    = get_current_user_id();
    $result     = wpssb_save_frontend_rehearsal_availability_payload( $project_id, $user_id, $_POST );

    if ( is_wp_error( $result ) ) {
        $status = 422;
        $data   = $result->get_error_data();

        if ( is_array( $data ) && ! empty( $data['status'] ) ) {
            $status = (int) $data['status'];
        }

        wp_send_json_error(
            [
                'message' => $result->get_error_message(),
            ],
            $status
        );
    }

    wp_send_json_success( $result );
}
add_action( 'wp_ajax_wpssb_autosave_frontend_rehearsal_availability', 'wpssb_handle_frontend_rehearsal_availability_autosave' );

/**
 * Renderiza un selector horario AM/PM para frontend.
 *
 * @param string $name_prefix Nombre base del campo.
 * @param string $value       Horario HH:MM.
 * @param string $label       Etiqueta accesible base.
 * @return string
 */
function wpssb_render_frontend_rehearsal_time_picker( $name_prefix, $value, $label ) {
    $value  = wpssb_sanitize_project_rehearsal_time( $value );
    $output = '<div class="pd-rehearsal-time-picker">';
    $output .= '<input type="time" class="pd-rehearsal-time-picker__input" name="' . esc_attr( $name_prefix ) . '" value="' . esc_attr( $value ) . '" step="300" aria-label="' . esc_attr( $label ) . '" />';
    $output .= '</div>';

    return $output;
}

/**
 * Renderiza una fila editable de rango horario para frontend.
 *
 * @param string               $group   Tipo de grupo.
 * @param string               $day     Día objetivo.
 * @param string|int           $row_key Índice o token de fila.
 * @param array<string, mixed> $row     Valores de la fila.
 * @return string
 */
function wpssb_render_frontend_rehearsal_slot_row( $group, $day, $row_key, $row = [] ) {
    $group       = 'unavailable_slots' === $group ? 'unavailable_slots' : 'available_slots';
    $row         = wpssb_prepare_frontend_rehearsal_slot_form_row( $row );
    $name_prefix = $group . '[' . $day . '][' . $row_key . ']';
    $labels      = [
        'available_slots'   => [
            'start'  => __( 'Inicio disponible', 'wp-song-study-blocks' ),
            'end'    => __( 'Fin disponible', 'wp-song-study-blocks' ),
            'button' => __( 'Quitar rango disponible', 'wp-song-study-blocks' ),
        ],
        'unavailable_slots' => [
            'start'  => __( 'Inicio bloqueado', 'wp-song-study-blocks' ),
            'end'    => __( 'Fin bloqueado', 'wp-song-study-blocks' ),
            'button' => __( 'Quitar bloqueo parcial', 'wp-song-study-blocks' ),
        ],
    ];

    $output  = '<div class="pd-rehearsal-slot-row" data-rehearsal-slot-row>';
    $output .= '<input type="hidden" name="' . esc_attr( $name_prefix ) . '[id]" value="' . esc_attr( $row['id'] ) . '" />';
    $output .= '<div class="pd-rehearsal-slot-row__fields">';
    $output .= '<label class="pd-rehearsal-slot-row__field">';
    $output .= '<span>' . esc_html__( 'Desde', 'wp-song-study-blocks' ) . '</span>';
    $output .= wpssb_render_frontend_rehearsal_time_picker( $name_prefix . '[start]', (string) $row['start'], $labels[ $group ]['start'] );
    $output .= '</label>';
    $output .= '<label class="pd-rehearsal-slot-row__field">';
    $output .= '<span>' . esc_html__( 'Hasta', 'wp-song-study-blocks' ) . '</span>';
    $output .= wpssb_render_frontend_rehearsal_time_picker( $name_prefix . '[end]', (string) $row['end'], $labels[ $group ]['end'] );
    $output .= '</label>';
    $output .= '</div>';
    $output .= '<p class="pd-rehearsal-slot-row__feedback" data-rehearsal-slot-feedback hidden></p>';
    $output .= '<button type="button" class="pd-rehearsal-slot-row__remove" data-rehearsal-slot-remove aria-label="' . esc_attr( $labels[ $group ]['button'] ) . '"><span aria-hidden="true">&times;</span></button>';
    $output .= '</div>';

    return $output;
}

/**
 * Determina si la entrada de un integrante ya tiene disponibilidad cargada.
 *
 * @param array<string, mixed> $entry Entrada del integrante.
 * @return bool
 */
function wpssb_frontend_rehearsal_member_has_availability( $entry ) {
    return ! empty( $entry['slots'] ) || ! empty( $entry['blocked_days'] ) || ! empty( $entry['unavailable_slots'] );
}

/**
 * Resume la disponibilidad actualmente guardada de un integrante.
 *
 * @param array<string, mixed> $entry Entrada del integrante.
 * @return array<string, mixed>
 */
function wpssb_get_frontend_rehearsal_member_availability_summary( $entry ) {
    $entry         = is_array( $entry ) ? $entry : [];
    $form_values   = wpssb_prepare_frontend_rehearsal_availability_form( $entry );
    $days_with_data = 0;

    foreach ( $form_values as $day_state ) {
        if ( ! is_array( $day_state ) ) {
            continue;
        }

        if ( ! empty( $day_state['blocked'] ) || ! empty( $day_state['slots'] ) || ! empty( $day_state['unavailable_slots'] ) ) {
            $days_with_data++;
        }
    }

    $has_availability = wpssb_frontend_rehearsal_member_has_availability( $entry );

    return [
        'form_values'      => $form_values,
        'has_availability' => $has_availability,
        'days_with_data'   => $days_with_data,
        'summary_text'     => $has_availability
            ? sprintf(
                _n( '%s día cargado', '%s días cargados', $days_with_data, 'wp-song-study-blocks' ),
                $days_with_data
            )
            : __( 'Sin horarios aún', 'wp-song-study-blocks' ),
    ];
}

/**
 * Devuelve el directorio de integrantes disponible para el frontend de ensayos.
 *
 * @param int                              $project_id    Proyecto actual.
 * @param array<int, array<string, mixed>> $availability  Disponibilidad saneada.
 * @param int                              $viewer_id     Usuario actual.
 * @return array<int, array<string, mixed>>
 */
function wpssb_get_frontend_rehearsal_member_directory( $project_id, $availability, $viewer_id ) {
    $project_id        = absint( $project_id );
    $viewer_id         = absint( $viewer_id );
    $availability      = is_array( $availability ) ? $availability : [];
    $availability_by_user = [];
    $member_ids        = [];

    foreach ( $availability as $entry ) {
        if ( ! is_array( $entry ) || empty( $entry['user_id'] ) ) {
            continue;
        }

        $entry_user_id                            = absint( $entry['user_id'] );
        $availability_by_user[ $entry_user_id ]   = $entry;
        $member_ids[]                             = $entry_user_id;
    }

    foreach ( wpssb_get_project_rehearsal_members( $project_id ) as $user ) {
        if ( $user instanceof WP_User ) {
            $member_ids[] = (int) $user->ID;
        }
    }

    if ( $viewer_id > 0 ) {
        $member_ids[] = $viewer_id;
    }

    $member_ids = array_values( array_unique( array_filter( array_map( 'absint', $member_ids ) ) ) );

    $members = array_map(
        static function ( $member_id ) use ( $availability_by_user, $availability, $viewer_id ) {
            $entry = isset( $availability_by_user[ $member_id ] ) ? $availability_by_user[ $member_id ] : wpssb_get_project_rehearsal_member_entry( $availability, $member_id );
            $user  = get_user_by( 'id', $member_id );
            $name  = $user instanceof WP_User ? sanitize_text_field( $user->display_name ) : sanitize_text_field( (string) ( $entry['nombre'] ?? '' ) );

            if ( '' === $name ) {
                $name = __( 'Integrante sin nombre', 'wp-song-study-blocks' );
            }

            $entry['nombre'] = $name;
            $availability_summary = wpssb_get_frontend_rehearsal_member_availability_summary( $entry );
            $form_values          = is_array( $availability_summary['form_values'] ?? null ) ? $availability_summary['form_values'] : [];
            $days_with_data       = absint( $availability_summary['days_with_data'] ?? 0 );

            $updated_label = '';
            if ( ! empty( $entry['updated_at'] ) ) {
                $updated_timestamp = strtotime( (string) $entry['updated_at'] );
                if ( false !== $updated_timestamp ) {
                    $updated_label = wp_date( 'j M Y · H:i', $updated_timestamp );
                }
            }

            return [
                'user_id'           => $member_id,
                'nombre'            => $name,
                'entry'             => $entry,
                'form_values'       => $form_values,
                'is_current_user'   => $member_id === $viewer_id,
                'has_availability'  => ! empty( $availability_summary['has_availability'] ),
                'days_with_data'    => $days_with_data,
                'summary_text'      => sanitize_text_field( (string) ( $availability_summary['summary_text'] ?? '' ) ),
                'updated_label'     => $updated_label,
            ];
        },
        $member_ids
    );

    usort(
        $members,
        static function ( $left, $right ) {
            $left_is_current  = ! empty( $left['is_current_user'] );
            $right_is_current = ! empty( $right['is_current_user'] );

            if ( $left_is_current !== $right_is_current ) {
                return $left_is_current ? -1 : 1;
            }

            return strnatcasecmp( (string) ( $left['nombre'] ?? '' ), (string) ( $right['nombre'] ?? '' ) );
        }
    );

    return $members;
}

/**
 * Formatea un horario para mostrarlo en frontend.
 *
 * @param string $value Horario HH:MM.
 * @return string
 */
function wpssb_format_frontend_rehearsal_time_label( $value ) {
    $value = wpssb_sanitize_project_rehearsal_time( $value );

    if ( '' === $value ) {
        return '';
    }

    list( $hours, $minutes ) = array_map( 'intval', explode( ':', $value ) );

    return sprintf(
        '%1$s:%2$02d %3$s',
        (string) ( 0 === $hours % 12 ? 12 : $hours % 12 ),
        $minutes,
        $hours >= 12 ? 'PM' : 'AM'
    );
}

/**
 * Compara sesiones por fecha y hora para orden cronológico.
 *
 * @param array<string, mixed> $left  Sesión izquierda.
 * @param array<string, mixed> $right Sesión derecha.
 * @return int
 */
function wpssb_compare_project_rehearsal_sessions_by_schedule( $left, $right ) {
    $left_timestamp  = wpssb_get_project_rehearsal_session_timestamp( $left );
    $right_timestamp = wpssb_get_project_rehearsal_session_timestamp( $right );

    if ( $left_timestamp === $right_timestamp ) {
        return strnatcasecmp(
            sanitize_text_field( (string) ( $left['focus'] ?? '' ) ),
            sanitize_text_field( (string) ( $right['focus'] ?? '' ) )
        );
    }

    return $left_timestamp <=> $right_timestamp;
}

/**
 * Obtiene el timestamp operativo de una sesión de ensayo.
 *
 * @param array<string, mixed> $session      Sesión saneada.
 * @param bool                 $use_end_time Si debe usar la hora de fin.
 * @return int|false
 */
function wpssb_get_project_rehearsal_session_timestamp( $session, $use_end_time = false ) {
    if ( ! is_array( $session ) || empty( $session['scheduled_for'] ) ) {
        return false;
    }

    $time = $use_end_time
        ? wpssb_sanitize_project_rehearsal_time( $session['end_time'] ?? '' )
        : wpssb_sanitize_project_rehearsal_time( $session['start_time'] ?? '' );

    if ( '' === $time ) {
        $time = $use_end_time
            ? ( wpssb_sanitize_project_rehearsal_time( $session['start_time'] ?? '' ) ?: '23:59' )
            : '00:00';
    }

    return strtotime( sanitize_text_field( (string) $session['scheduled_for'] ) . ' ' . $time );
}

/**
 * Indica si una sesión ya rebasó su hora de finalización.
 *
 * @param array<string, mixed> $session       Sesión saneada o parcial.
 * @param int|null             $now_timestamp Timestamp de referencia.
 * @return bool
 */
function wpssb_project_rehearsal_session_has_ended( $session, $now_timestamp = null ) {
    if ( ! is_array( $session ) ) {
        return false;
    }

    if ( null === $now_timestamp ) {
        $now_timestamp = current_time( 'timestamp' );
    }

    $end_timestamp = wpssb_get_project_rehearsal_session_timestamp( $session, true );

    return false !== $end_timestamp && $end_timestamp < (int) $now_timestamp;
}

/**
 * Indica si una propuesta ya venció sin convertirse en ensayo confirmado.
 *
 * @param array<string, mixed> $session       Sesión saneada.
 * @param int|null             $now_timestamp Timestamp de referencia.
 * @return bool
 */
function wpssb_project_rehearsal_session_is_expired_proposal( $session, $now_timestamp = null ) {
    if ( ! is_array( $session ) ) {
        return false;
    }

    $status = sanitize_key( (string) ( $session['status'] ?? '' ) );
    if ( 'expired' === $status ) {
        return true;
    }

    if ( ! in_array( $status, [ 'proposed', 'voting' ], true ) ) {
        return false;
    }

    if ( ! empty( $session['consensus_reached'] ) ) {
        return false;
    }

    return wpssb_project_rehearsal_session_has_ended( $session, $now_timestamp );
}

/**
 * Indica si una sesión ya debe aparecer en la bitácora frontend.
 *
 * @param array<string, mixed> $session       Sesión saneada.
 * @param int|null             $now_timestamp Timestamp de referencia.
 * @return bool
 */
function wpssb_frontend_rehearsal_session_is_logbook_available( $session, $now_timestamp = null ) {
    if ( ! is_array( $session ) ) {
        return false;
    }

    $status = sanitize_key( (string) ( $session['status'] ?? '' ) );
    if ( wpssb_project_rehearsal_session_is_expired_proposal( $session, $now_timestamp ) ) {
        return true;
    }

    if ( 'completed' === $status ) {
        return true;
    }

    if ( 'confirmed' !== $status ) {
        return false;
    }

    if ( null === $now_timestamp ) {
        $now_timestamp = current_time( 'timestamp' );
    }

    $session_timestamp = wpssb_get_project_rehearsal_session_timestamp( $session );

    return false !== $session_timestamp && $session_timestamp <= (int) $now_timestamp;
}

/**
 * Indica si la sesión ya está transcurriendo según su horario.
 *
 * @param array<string, mixed> $session       Sesión saneada.
 * @param int|null             $now_timestamp Timestamp de referencia.
 * @return bool
 */
function wpssb_frontend_rehearsal_session_is_in_progress( $session, $now_timestamp = null ) {
    if ( ! is_array( $session ) ) {
        return false;
    }

    if ( null === $now_timestamp ) {
        $now_timestamp = current_time( 'timestamp' );
    }

    $start_timestamp = wpssb_get_project_rehearsal_session_timestamp( $session );
    $end_timestamp   = wpssb_get_project_rehearsal_session_timestamp( $session, true );

    if ( false === $start_timestamp ) {
        return false;
    }

    if ( false === $end_timestamp || $end_timestamp < $start_timestamp ) {
        return $start_timestamp <= (int) $now_timestamp;
    }

    return $start_timestamp <= (int) $now_timestamp && $end_timestamp >= (int) $now_timestamp;
}

/**
 * Renderiza las cápsulas de horarios en modo solo lectura.
 *
 * @param array<int, array<string, mixed>> $rows          Filas saneadas.
 * @param string                           $empty_message Mensaje vacío.
 * @param string                           $variant       Variante visual.
 * @return string
 */
function wpssb_render_frontend_rehearsal_slot_pills( $rows, $empty_message, $variant = 'available' ) {
    $rows       = is_array( $rows ) ? $rows : [];
    $valid_rows = array_values(
        array_filter(
            array_map( 'wpssb_prepare_frontend_rehearsal_slot_form_row', $rows ),
            static function ( $row ) {
                return is_array( $row ) && ! empty( $row['start'] ) && ! empty( $row['end'] );
            }
        )
    );

    if ( empty( $valid_rows ) ) {
        return '<p class="pd-rehearsal-slot-list__empty">' . esc_html( $empty_message ) . '</p>';
    }

    $output = '<ul class="pd-rehearsal-slot-pill-list">';

    foreach ( $valid_rows as $row ) {
        $label = wpssb_format_frontend_rehearsal_time_label( (string) $row['start'] ) . ' - ' . wpssb_format_frontend_rehearsal_time_label( (string) $row['end'] );
        $class = 'unavailable' === $variant ? ' pd-rehearsal-slot-pill--blocked' : '';
        $output .= '<li class="pd-rehearsal-slot-pill' . esc_attr( $class ) . '">' . esc_html( $label ) . '</li>';
    }

    $output .= '</ul>';

    return $output;
}

/**
 * Renderiza el editor por días para un integrante.
 *
 * @param string                            $editor_key  Token único del editor.
 * @param array<string, array<string, mixed>> $form_values Valores preparados por día.
 * @param bool                              $editable    Si el integrante puede editar.
 * @return string
 */
function wpssb_render_frontend_rehearsal_day_editor( $editor_key, $form_values, $editable = true ) {
    $editor_key = sanitize_html_class( (string) $editor_key );
    $day_labels = wpssb_get_project_rehearsal_day_labels();
    $output     = '<div class="pd-rehearsal-day-editor' . ( $editable ? '' : ' pd-rehearsal-day-editor--readonly' ) . '" data-rehearsal-day-editor>';

    $output .= '<div class="pd-rehearsal-day-editor__nav-wrap">';
    $output .= '<button type="button" class="pd-rehearsal-day-editor__arrow" data-rehearsal-day-prev aria-label="' . esc_attr__( 'Ir al día anterior', 'wp-song-study-blocks' ) . '"><span aria-hidden="true">&#x2039;</span></button>';
    $output .= '<div class="pd-rehearsal-day-editor__nav-shell">';
    $output .= '<div class="pd-rehearsal-day-editor__nav" role="tablist" aria-label="' . esc_attr__( 'Días de disponibilidad', 'wp-song-study-blocks' ) . '" data-rehearsal-day-tabs>';

    foreach ( wpssb_get_project_rehearsal_days() as $day_index => $day ) {
        $day_state         = is_array( $form_values[ $day ] ?? null ) ? $form_values[ $day ] : [];
        $day_label         = isset( $day_labels[ $day ] ) ? $day_labels[ $day ] : ucfirst( $day );
        $available_count   = count( (array) ( $day_state['slots'] ?? [] ) );
        $unavailable_count = count( (array) ( $day_state['unavailable_slots'] ?? [] ) );
        $is_day_blocked    = ! empty( $day_state['blocked'] );
        $is_day_active     = 0 === (int) $day_index;
        $tab_meta          = $is_day_blocked
            ? __( 'Día inhábil', 'wp-song-study-blocks' )
            : sprintf( __( '%1$s libres · %2$s bloqueos', 'wp-song-study-blocks' ), (int) $available_count, (int) $unavailable_count );

        $output .= '<button type="button" class="pd-rehearsal-day-editor__tab' . ( $is_day_active ? ' is-active' : '' ) . '" id="pd-rehearsal-day-tab-' . esc_attr( $editor_key . '-' . $day ) . '" role="tab" aria-selected="' . ( $is_day_active ? 'true' : 'false' ) . '" aria-controls="pd-rehearsal-day-panel-' . esc_attr( $editor_key . '-' . $day ) . '" data-rehearsal-day-tab="' . esc_attr( $day ) . '">';
        $output .= '<span class="pd-rehearsal-day-editor__tab-label">' . esc_html( $day_label ) . '</span>';
        $output .= '<small class="pd-rehearsal-day-editor__tab-meta">' . esc_html( $tab_meta ) . '</small>';
        $output .= '</button>';
    }

    $output .= '</div>';
    $output .= '</div>';
    $output .= '<button type="button" class="pd-rehearsal-day-editor__arrow" data-rehearsal-day-next aria-label="' . esc_attr__( 'Ir al día siguiente', 'wp-song-study-blocks' ) . '"><span aria-hidden="true">&#x203A;</span></button>';
    $output .= '</div>';
    $output .= '<div class="pd-rehearsal-day-editor__panels">';

    foreach ( wpssb_get_project_rehearsal_days() as $day_index => $day ) {
        $day_state         = is_array( $form_values[ $day ] ?? null ) ? $form_values[ $day ] : [];
        $day_label         = isset( $day_labels[ $day ] ) ? $day_labels[ $day ] : ucfirst( $day );
        $is_day_active     = 0 === (int) $day_index;
        $is_day_blocked    = ! empty( $day_state['blocked'] );
        $available_rows    = ! empty( $day_state['slots'] ) ? (array) $day_state['slots'] : [ [] ];
        $unavailable_rows  = (array) ( $day_state['unavailable_slots'] ?? [] );
        $panel_classes     = 'pd-rehearsal-day-panel' . ( $is_day_blocked ? ' is-day-blocked' : '' ) . ( $editable ? '' : ' pd-rehearsal-day-panel--readonly' );

        $output .= '<section class="' . esc_attr( $panel_classes ) . '" id="pd-rehearsal-day-panel-' . esc_attr( $editor_key . '-' . $day ) . '" role="tabpanel" aria-labelledby="pd-rehearsal-day-tab-' . esc_attr( $editor_key . '-' . $day ) . '" data-rehearsal-day-panel="' . esc_attr( $day ) . '"' . ( $is_day_active ? '' : ' hidden' ) . '>';
        $output .= '<div class="pd-rehearsal-day-panel__header">';
        $output .= '<div class="pd-rehearsal-day-panel__intro">';
        $output .= '<h3>' . esc_html( $day_label ) . '</h3>';
        $output .= '<p>' . esc_html(
            $editable
                ? __( 'Configura aquí tus ventanas base para este día. Puedes sumar varios bloques si tienes pausas o compromisos intermedios.', 'wp-song-study-blocks' )
                : __( 'Aquí puedes revisar las ventanas declaradas por este integrante para esta jornada.', 'wp-song-study-blocks' )
        ) . '</p>';
        $output .= '</div>';

        if ( $editable ) {
            $output .= '<label class="pd-rehearsal-day-block-toggle">';
            $output .= '<input type="checkbox" name="blocked_days[]" value="' . esc_attr( $day ) . '" ' . checked( $is_day_blocked, true, false ) . ' data-rehearsal-day-block aria-label="' . esc_attr__( 'Estado del día: disponible o no disponible', 'wp-song-study-blocks' ) . '" />';
            $output .= '<span class="pd-rehearsal-day-block-toggle__ui">';
            $output .= '<span class="pd-rehearsal-day-block-toggle__switch" aria-hidden="true"><span class="pd-rehearsal-day-block-toggle__switch-thumb"></span><span class="pd-rehearsal-day-block-toggle__option pd-rehearsal-day-block-toggle__option--available">' . esc_html__( 'Disponible', 'wp-song-study-blocks' ) . '</span><span class="pd-rehearsal-day-block-toggle__option pd-rehearsal-day-block-toggle__option--unavailable">' . esc_html__( 'No disponible', 'wp-song-study-blocks' ) . '</span></span>';
            $output .= '</span>';
            $output .= '</label>';
        } else {
            $output .= '<span class="pd-rehearsal-pill' . ( $is_day_blocked ? ' pd-rehearsal-pill--blocked' : '' ) . '">' . esc_html( $is_day_blocked ? __( 'No disponible', 'wp-song-study-blocks' ) : __( 'Disponible', 'wp-song-study-blocks' ) ) . '</span>';
        }

        $output .= '</div>';

        if ( $editable ) {
            $output .= '<p class="pd-rehearsal-day-panel__blocked-note" data-rehearsal-day-blocked-note>' . esc_html__( 'Al declarar este día como inhábil ya no podrás fijar horarios disponibles ni bloqueos parciales. Desmarca esta opción para volver a habilitar el día y capturar rangos.', 'wp-song-study-blocks' ) . '</p>';
        } elseif ( $is_day_blocked ) {
            $output .= '<p class="pd-rehearsal-day-panel__blocked-note">' . esc_html__( 'Este día quedó marcado como no disponible, así que no contará para cruzar coincidencias del grupo.', 'wp-song-study-blocks' ) . '</p>';
        }

        $output .= '<div class="pd-rehearsal-day-panel__sections"' . ( $editable ? ' data-rehearsal-day-sections' : '' ) . ( $is_day_blocked ? ' hidden' : '' ) . '>';
        $output .= '<div class="pd-rehearsal-slot-card">';
        $output .= '<div class="pd-rehearsal-slot-card__header">';
        $output .= '<div><strong>' . esc_html__( 'Disponible por rangos', 'wp-song-study-blocks' ) . '</strong><p>' . esc_html__( 'Agrega uno o más espacios libres para ensayar este día.', 'wp-song-study-blocks' ) . '</p></div>';
        if ( $editable ) {
            $output .= '<button type="button" class="wp-block-button__link wp-element-button is-style-outline pd-rehearsal-slot-card__button" data-rehearsal-slot-add="available_slots">' . esc_html__( 'Añadir horario', 'wp-song-study-blocks' ) . '</button>';
        }
        $output .= '</div>';

        if ( $editable ) {
            $output .= '<div class="pd-rehearsal-slot-list" data-rehearsal-slot-list="available_slots">';
            $output .= '<p class="pd-rehearsal-slot-list__empty" data-rehearsal-empty hidden>' . esc_html__( 'Sin horarios disponibles cargados todavía.', 'wp-song-study-blocks' ) . '</p>';
            foreach ( $available_rows as $row_index => $row ) {
                $output .= wpssb_render_frontend_rehearsal_slot_row( 'available_slots', $day, 'slot_' . $row_index, is_array( $row ) ? $row : [] );
            }
            $output .= '</div>';
            $output .= '<template data-rehearsal-slot-template="available_slots">' . wpssb_render_frontend_rehearsal_slot_row( 'available_slots', $day, '__INDEX__', [] ) . '</template>';
        } else {
            $output .= wpssb_render_frontend_rehearsal_slot_pills( (array) ( $day_state['slots'] ?? [] ), __( 'Sin horarios disponibles cargados para este día.', 'wp-song-study-blocks' ) );
        }

        $output .= '</div>';
        $output .= '<div class="pd-rehearsal-slot-card">';
        $output .= '<div class="pd-rehearsal-slot-card__header">';
        $output .= '<div><strong>' . esc_html__( 'No disponible por rangos', 'wp-song-study-blocks' ) . '</strong><p>' . esc_html__( 'Úsalo cuando sí puedes ensayar ese día, pero tienes cortes concretos que deben excluirse.', 'wp-song-study-blocks' ) . '</p></div>';
        if ( $editable ) {
            $output .= '<button type="button" class="wp-block-button__link wp-element-button is-style-outline pd-rehearsal-slot-card__button" data-rehearsal-slot-add="unavailable_slots">' . esc_html__( 'Añadir bloqueo', 'wp-song-study-blocks' ) . '</button>';
        }
        $output .= '</div>';

        if ( $editable ) {
            $output .= '<div class="pd-rehearsal-slot-list" data-rehearsal-slot-list="unavailable_slots">';
            $output .= '<p class="pd-rehearsal-slot-list__empty"' . ( ! empty( $unavailable_rows ) ? ' hidden' : '' ) . ' data-rehearsal-empty>' . esc_html__( 'Sin bloqueos parciales en este día.', 'wp-song-study-blocks' ) . '</p>';
            foreach ( $unavailable_rows as $row_index => $row ) {
                $output .= wpssb_render_frontend_rehearsal_slot_row( 'unavailable_slots', $day, 'blocked_' . $row_index, is_array( $row ) ? $row : [] );
            }
            $output .= '</div>';
            $output .= '<template data-rehearsal-slot-template="unavailable_slots">' . wpssb_render_frontend_rehearsal_slot_row( 'unavailable_slots', $day, '__INDEX__', [] ) . '</template>';
        } else {
            $output .= wpssb_render_frontend_rehearsal_slot_pills( (array) ( $day_state['unavailable_slots'] ?? [] ), __( 'Sin bloqueos parciales declarados para este día.', 'wp-song-study-blocks' ), 'unavailable' );
        }

        $output .= '</div>';
        $output .= '</div>';
        $output .= '</section>';
    }

    $output .= '</div>';
    $output .= '</div>';

    return $output;
}

/**
 * Renderiza el panel de un integrante dentro del carrusel de disponibilidad.
 *
 * @param int                  $project_id    Proyecto actual.
 * @param array<string, mixed> $member_state  Estado del integrante.
 * @param string               $action_url    URL de envío.
 * @param string               $schedule_url  URL de retorno.
 * @param bool                 $is_active     Si el panel arranca activo.
 * @return string
 */
function wpssb_render_frontend_rehearsal_member_panel( $project_id, $member_state, $action_url, $schedule_url, $is_active = false ) {
    $project_id      = absint( $project_id );
    $member_id       = absint( $member_state['user_id'] ?? 0 );
    $member_name     = sanitize_text_field( (string) ( $member_state['nombre'] ?? '' ) );
    $is_current_user = ! empty( $member_state['is_current_user'] );
    $panel_key       = 'member-' . $member_id;
    $updated_label   = sanitize_text_field( (string) ( $member_state['updated_label'] ?? '' ) );
    $entry           = is_array( $member_state['entry'] ?? null ) ? $member_state['entry'] : [];
    $form_values     = is_array( $member_state['form_values'] ?? null ) ? $member_state['form_values'] : [];
    $output          = '<section class="pd-rehearsal-member-panel' . ( $is_current_user ? ' is-editable' : ' is-readonly' ) . '" id="pd-rehearsal-member-panel-' . esc_attr( $member_id ) . '" data-rehearsal-member-panel="' . esc_attr( (string) $member_id ) . '"' . ( $is_active ? '' : ' hidden' ) . '>';

    $output .= '<div class="pd-rehearsal-member-panel__header">';
    $output .= '<div class="pd-rehearsal-member-panel__intro">';
    $output .= '<p class="pd-membership-shell__eyebrow">' . esc_html( $is_current_user ? __( 'Tu disponibilidad editable', 'wp-song-study-blocks' ) : __( 'Disponibilidad del integrante', 'wp-song-study-blocks' ) ) . '</p>';
    $output .= '<h3>' . esc_html( $member_name ) . '</h3>';
    $output .= '<p>' . esc_html(
        $is_current_user
            ? __( 'Aquí ajustas tus rangos base y el resto del grupo podrá revisarlos en este mismo espacio.', 'wp-song-study-blocks' )
            : __( 'Puedes revisar su disponibilidad desde aquí, pero solo esa persona puede editarla.', 'wp-song-study-blocks' )
    ) . '</p>';
    $output .= '</div>';
    $output .= '<div class="pd-rehearsal-member-panel__meta">';
    $output .= '<span class="pd-rehearsal-pill">' . esc_html( $is_current_user ? __( 'Editable', 'wp-song-study-blocks' ) : __( 'Solo lectura', 'wp-song-study-blocks' ) ) . '</span>';
    $updated_label_text = '' !== $updated_label ? sprintf( __( 'Actualizado: %s', 'wp-song-study-blocks' ), $updated_label ) : '';
    $output .= '<p class="pd-rehearsal-panel__meta" data-rehearsal-member-updated-label' . ( '' === $updated_label_text ? ' hidden' : '' ) . '>' . esc_html( $updated_label_text ) . '</p>';
    $output .= '</div>';
    $output .= '</div>';

    if ( $is_current_user ) {
        $output .= '<form class="pd-rehearsal-form" method="post" action="' . esc_url( $action_url ) . '" data-rehearsal-availability-autosave>';
        $output .= '<input type="hidden" name="action" value="wpssb_save_my_rehearsal_availability" />';
        $output .= '<input type="hidden" name="project_id" value="' . (int) $project_id . '" />';
        $output .= '<input type="hidden" name="redirect_to" value="' . esc_url( $schedule_url ) . '" />';
        $output .= wp_nonce_field( 'wpssb_save_my_rehearsal_availability', 'wpssb_rehearsal_availability_nonce', true, false );
        $output .= wpssb_render_frontend_rehearsal_day_editor( $panel_key, $form_values, true );
        $output .= '<label><span>' . esc_html__( 'Notas sobre tu disponibilidad', 'wp-song-study-blocks' ) . '</span><textarea name="availability_notes" rows="4" placeholder="' . esc_attr__( 'Ej. después de las 21:00 ya no puedo mover equipo, o los jueves dependo del tráfico.', 'wp-song-study-blocks' ) . '">' . esc_textarea( (string) ( $entry['notes'] ?? '' ) ) . '</textarea></label>';
        $output .= '<div class="pd-rehearsal-proposal-form__actions">';
        $output .= '<p class="pd-rehearsal-proposal-form__status" data-rehearsal-availability-status role="status" aria-live="polite">' . esc_html__( 'Tu disponibilidad se guarda automáticamente cuando todos los horarios están completos.', 'wp-song-study-blocks' ) . '</p>';
        $output .= '<button type="submit" class="wp-block-button__link wp-element-button" data-rehearsal-availability-submit>' . esc_html__( 'Guardar mi disponibilidad', 'wp-song-study-blocks' ) . '</button>';
        $output .= '</div>';
        $output .= '</form>';
    } else {
        if ( ! empty( $entry['notes'] ) ) {
            $output .= '<div class="pd-rehearsal-member-panel__note">';
            $output .= '<strong>' . esc_html__( 'Notas compartidas', 'wp-song-study-blocks' ) . '</strong>';
            $output .= '<p>' . esc_html( (string) $entry['notes'] ) . '</p>';
            $output .= '</div>';
        }

        $output .= wpssb_render_frontend_rehearsal_day_editor( $panel_key, $form_values, false );
    }

    $output .= '</section>';

    return $output;
}

/**
 * Guarda el voto del usuario actual desde frontend.
 *
 * @return void
 */
function wpssb_handle_frontend_rehearsal_vote_save() {
    if ( 'POST' !== strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_request' );
    }

    if ( ! is_user_logged_in() ) {
        wpssb_redirect_frontend_rehearsal( 'login_required' );
    }

    if ( ! isset( $_POST['wpssb_rehearsal_vote_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpssb_rehearsal_vote_nonce'] ) ), 'wpssb_save_my_rehearsal_vote' ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_nonce' );
    }

    $project_id = isset( $_POST['project_id'] ) ? absint( wp_unslash( $_POST['project_id'] ) ) : 0;
    $session_id = isset( $_POST['session_id'] ) ? sanitize_key( wp_unslash( $_POST['session_id'] ) ) : '';
    $user_id    = get_current_user_id();

    if ( $project_id <= 0 || '' === $session_id || ! wpssb_user_can_access_project_rehearsals( $project_id, $user_id ) ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_project', $project_id, 'calendar' );
    }

    $vote = isset( $_POST['my_vote'] ) ? sanitize_key( wp_unslash( $_POST['my_vote'] ) ) : 'pending';
    if ( ! in_array( $vote, [ 'pending', 'yes', 'no', 'maybe' ], true ) ) {
        $vote = 'pending';
    }

    $meta     = wpssb_get_project_rehearsal_meta( $project_id );
    $sessions = wpssb_sanitize_project_rehearsal_sessions( $meta['sessions'] ?? [], $project_id );
    $updated  = false;
    $session_found = false;
    $synced_session_id = '';
    $previous_vote_state = [];
    $updated_vote_state  = [];
    $updated_session     = [];
    $previous_session    = [];
    $calendar_sync_result = null;
    $user     = get_user_by( 'id', $user_id );

    foreach ( $sessions as $index => $session ) {
        if ( ! is_array( $session ) || $session_id !== ( $session['id'] ?? '' ) ) {
            continue;
        }

        $session_found = true;

        if ( ! wpssb_frontend_rehearsal_session_accepts_votes( $session ) ) {
            wpssb_redirect_frontend_rehearsal( 'session_locked', $project_id, 'calendar' );
        }

        $previous_session = $session;
        $votes = [];
        foreach ( (array) ( $session['votes'] ?? [] ) as $vote_entry ) {
            if ( ! is_array( $vote_entry ) || empty( $vote_entry['user_id'] ) ) {
                continue;
            }

            $vote_entry_user_id = absint( $vote_entry['user_id'] );

            if ( $vote_entry_user_id === $user_id ) {
                $previous_vote_state = $vote_entry;
                $vote_entry['vote']    = $vote;
                $vote_entry['comment'] = isset( $_POST['vote_comment'] ) ? sanitize_text_field( wp_unslash( $_POST['vote_comment'] ) ) : '';
                $vote_entry['nombre']  = $user instanceof WP_User ? sanitize_text_field( $user->display_name ) : sanitize_text_field( (string) ( $vote_entry['nombre'] ?? '' ) );
                $updated_vote_state    = $vote_entry;
                $updated               = true;
            }

            $votes[] = $vote_entry;
        }

        if ( ! $updated ) {
            $updated_vote_state = [
                'user_id' => $user_id,
                'nombre'  => $user instanceof WP_User ? sanitize_text_field( $user->display_name ) : '',
                'vote'    => $vote,
                'comment' => isset( $_POST['vote_comment'] ) ? sanitize_text_field( wp_unslash( $_POST['vote_comment'] ) ) : '',
            ];
            $votes[] = $updated_vote_state;
            $previous_vote_state = [
                'user_id' => $user_id,
                'nombre'  => $user instanceof WP_User ? sanitize_text_field( $user->display_name ) : '',
                'vote'    => 'pending',
                'comment' => '',
            ];
            $updated = true;
        }

        $sessions[ $index ]['votes'] = $votes;
        $sessions[ $index ]          = wpssb_refresh_project_rehearsal_session_vote_state( $sessions[ $index ], $project_id );
        $synced_session_id           = sanitize_key( (string) ( $sessions[ $index ]['id'] ?? '' ) );
        $updated_session             = $sessions[ $index ];
        break;
    }

    if ( ! $session_found ) {
        wpssb_redirect_frontend_rehearsal( 'invalid_session', $project_id, 'calendar' );
    }

    wpssb_update_project_rehearsal_meta(
        $project_id,
        [
            'project_id'   => $project_id,
            'availability' => $meta['availability'] ?? [],
            'sessions'     => $sessions,
        ]
    );

    if (
        '' !== $synced_session_id
        && function_exists( 'wpssb_should_auto_sync_project_rehearsal_session' )
        && isset( $sessions[ $index ] )
        && is_array( $sessions[ $index ] )
        && wpssb_should_auto_sync_project_rehearsal_session( $sessions[ $index ] )
        && function_exists( 'wpssb_sync_project_rehearsal_google_calendar' )
    ) {
        $calendar_sync_result = wpssb_attempt_project_rehearsal_google_calendar_sync( $project_id, $synced_session_id );
    }

    if ( function_exists( 'wpssb_notify_project_rehearsal_vote_change' ) ) {
        wpssb_notify_project_rehearsal_vote_change( $project_id, $previous_session, $updated_session, $user_id, $previous_vote_state, $updated_vote_state );
    }

    if ( is_wp_error( $calendar_sync_result ) ) {
        wpssb_redirect_frontend_rehearsal( 'calendar_sync_failed', $project_id, 'calendar' );
    }

    if ( is_array( $calendar_sync_result ) ) {
        wpssb_redirect_frontend_rehearsal( 'calendar_synced', $project_id, 'calendar' );
    }

    wpssb_redirect_frontend_rehearsal( 'updated_vote', $project_id, 'calendar' );
}
add_action( 'admin_post_wpssb_save_my_rehearsal_vote', 'wpssb_handle_frontend_rehearsal_vote_save' );
add_action( 'admin_post_nopriv_wpssb_save_my_rehearsal_vote', 'wpssb_handle_frontend_rehearsal_vote_save' );

/**
 * Devuelve los datos visuales para una sesión dentro del calendario integral.
 *
 * @param array<string, mixed> $session       Sesión saneada.
 * @param int|null             $now_timestamp Timestamp de referencia.
 * @return array<string, string>
 */
function wpssb_get_frontend_rehearsal_calendar_event_badge( $session, $now_timestamp = null ) {
    $status = sanitize_key( (string) ( $session['status'] ?? '' ) );

    if ( wpssb_project_rehearsal_session_is_expired_proposal( $session, $now_timestamp ) ) {
        return [
            'kind'  => 'expired',
            'icon'  => '&#8987;',
            'label' => __( 'Propuesta expirada', 'wp-song-study-blocks' ),
        ];
    }

    if ( 'cancelled' === $status ) {
        return [
            'kind'  => 'cancelled',
            'icon'  => '&times;',
            'label' => __( 'Ensayo cancelado', 'wp-song-study-blocks' ),
        ];
    }

    if ( in_array( $status, [ 'proposed', 'voting' ], true ) ) {
        return [
            'kind'  => 'proposal',
            'icon'  => '?',
            'label' => __( 'Propuesta de ensayo', 'wp-song-study-blocks' ),
        ];
    }

    if ( wpssb_frontend_rehearsal_session_is_logbook_available( $session, $now_timestamp ) ) {
        return [
            'kind'  => 'logbook',
            'icon'  => '&#10003;',
            'label' => __( 'Ensayo con bitácora', 'wp-song-study-blocks' ),
        ];
    }

    return [
        'kind'  => 'confirmed',
        'icon'  => '&#127919;',
        'label' => __( 'Ensayo confirmado', 'wp-song-study-blocks' ),
    ];
}

/**
 * Renderiza el detalle completo de una sesión dentro del calendario integral.
 *
 * @param array<string, mixed> $session Sesión saneada.
 * @param string               $kind    Tipo visual proposal|confirmed|logbook|cancelled|expired.
 * @param int                  $project_id Proyecto actual.
 * @param int                  $viewer_id  Usuario actual.
 * @param string               $action_url URL admin-post.
 * @param string               $redirect_url URL de retorno.
 * @return string
 */
function wpssb_render_frontend_rehearsal_calendar_event_detail( $session, $kind, $project_id = 0, $viewer_id = 0, $action_url = '', $redirect_url = '' ) {
    if ( ! is_array( $session ) ) {
        return '';
    }

    $kind         = sanitize_key( (string) $kind );
    $project_id   = absint( $project_id );
    $viewer_id    = absint( $viewer_id );
    $action_url   = esc_url_raw( (string) $action_url );
    $redirect_url = esc_url_raw( (string) $redirect_url );
    $calendar     = wpssb_sanitize_project_rehearsal_calendar_sync( $session['calendar'] ?? [] );
    $can_vote     = $project_id > 0
        && $viewer_id > 0
        && '' !== $action_url
        && '' !== $redirect_url
        && in_array( $kind, [ 'proposal', 'confirmed' ], true )
        && wpssb_frontend_rehearsal_session_accepts_votes( $session );
    $my_vote      = $can_vote ? wpssb_get_project_rehearsal_member_vote( $session, $viewer_id ) : [];
    $output       = '<div class="pd-rehearsal-calendar-event-detail">';

    $output .= '<dl class="pd-rehearsal-calendar-event-detail__meta">';
    $output .= '<div><dt>' . esc_html__( 'Horario', 'wp-song-study-blocks' ) . '</dt><dd>' . esc_html( wpssb_format_project_rehearsal_schedule( $session ) ) . '</dd></div>';
    if ( ! empty( $session['location'] ) ) {
        $output .= '<div><dt>' . esc_html__( 'Lugar', 'wp-song-study-blocks' ) . '</dt><dd>' . esc_html( (string) $session['location'] ) . '</dd></div>';
    }
    $output .= '<div><dt>' . esc_html__( 'Estado', 'wp-song-study-blocks' ) . '</dt><dd>' . esc_html( wpssb_get_project_rehearsal_status_label( sanitize_key( (string) ( $session['status'] ?? '' ) ) ) ) . '</dd></div>';
    $output .= '</dl>';

    if ( ! empty( $session['notes'] ) ) {
        $output .= '<div class="pd-rehearsal-calendar-event-detail__block">';
        $output .= '<strong>' . esc_html__( 'Notas', 'wp-song-study-blocks' ) . '</strong>';
        $output .= '<p>' . esc_html( (string) $session['notes'] ) . '</p>';
        $output .= '</div>';
    }

    if ( ! empty( $session['reviewed_items'] ) && is_array( $session['reviewed_items'] ) ) {
        $output .= '<div class="pd-rehearsal-calendar-event-detail__block">';
        $output .= '<strong>' . esc_html( 'logbook' === $kind ? __( 'Temas trabajados', 'wp-song-study-blocks' ) : __( 'Temas planeados', 'wp-song-study-blocks' ) ) . '</strong>';
        $output .= '<ul>';
        foreach ( $session['reviewed_items'] as $item ) {
            $output .= '<li>' . esc_html( sanitize_text_field( (string) $item ) ) . '</li>';
        }
        $output .= '</ul></div>';
    }

    if ( 'cancelled' === $kind ) {
        $reason = sanitize_textarea_field( (string) ( $session['cancellation_reason'] ?? '' ) );
        $output .= '<div class="pd-rehearsal-calendar-event-detail__block">';
        $output .= '<strong>' . esc_html__( 'Razón de la cancelación', 'wp-song-study-blocks' ) . '</strong>';
        $output .= '<p>' . esc_html( '' !== $reason ? $reason : __( 'No se registró una razón pública.', 'wp-song-study-blocks' ) ) . '</p>';
        $output .= '</div>';
    }

    if ( 'expired' === $kind ) {
        $output .= '<div class="pd-rehearsal-calendar-event-detail__block">';
        $output .= '<strong>' . esc_html__( 'Registro referencial', 'wp-song-study-blocks' ) . '</strong>';
        $output .= '<p>' . esc_html__( 'Esta propuesta rebasó su hora de finalización sin confirmarse por consenso. Ya no admite votos ni edición; queda visible solo para revisar quién aceptó, quién no y quién quedó pendiente.', 'wp-song-study-blocks' ) . '</p>';
        $output .= '</div>';
    }

    if ( in_array( $kind, [ 'proposal', 'confirmed', 'cancelled', 'expired' ], true ) ) {
        $totals = wpssb_get_project_rehearsal_vote_totals( $session );
        $output .= '<div class="pd-rehearsal-calendar-event-detail__block">';
        $output .= '<strong>' . esc_html__( 'Respuestas del grupo', 'wp-song-study-blocks' ) . '</strong>';
        $output .= '<p>' . esc_html(
            sprintf(
                __( 'Sí %1$d · Tal vez %2$d · No %3$d · Pendientes %4$d', 'wp-song-study-blocks' ),
                (int) $totals['yes'],
                (int) $totals['maybe'],
                (int) $totals['no'],
                (int) $totals['pending']
            )
        ) . '</p>';

        if ( ! empty( $session['votes'] ) && is_array( $session['votes'] ) ) {
            $output .= '<ul class="pd-rehearsal-calendar-event-detail__people">';
            foreach ( $session['votes'] as $vote_entry ) {
                if ( ! is_array( $vote_entry ) ) {
                    continue;
                }

                $vote_name    = sanitize_text_field( (string) ( $vote_entry['nombre'] ?? __( 'Integrante', 'wp-song-study-blocks' ) ) );
                $vote_key     = sanitize_key( (string) ( $vote_entry['vote'] ?? 'pending' ) );
                $vote_comment = sanitize_text_field( (string) ( $vote_entry['comment'] ?? '' ) );
                $output .= '<li><span>' . esc_html( $vote_name ) . '</span><strong>' . esc_html( wpssb_get_project_rehearsal_vote_label( $vote_key ) ) . '</strong>';
                if ( '' !== $vote_comment ) {
                    $output .= '<small>' . esc_html( $vote_comment ) . '</small>';
                }
                $output .= '</li>';
            }
            $output .= '</ul>';
        }

        if ( $can_vote ) {
            $output .= '<form class="pd-rehearsal-calendar-vote-form" method="post" action="' . esc_url( $action_url ) . '">';
            $output .= '<input type="hidden" name="action" value="wpssb_save_my_rehearsal_vote" />';
            $output .= '<input type="hidden" name="project_id" value="' . (int) $project_id . '" />';
            $output .= '<input type="hidden" name="session_id" value="' . esc_attr( (string) ( $session['id'] ?? '' ) ) . '" />';
            $output .= '<input type="hidden" name="redirect_to" value="' . esc_url( $redirect_url ) . '" />';
            $output .= wp_nonce_field( 'wpssb_save_my_rehearsal_vote', 'wpssb_rehearsal_vote_nonce', true, false );
            $output .= '<label><span>' . esc_html__( 'Mi respuesta', 'wp-song-study-blocks' ) . '</span><select name="my_vote">';
            foreach ( [ 'pending', 'yes', 'maybe', 'no' ] as $option ) {
                $output .= '<option value="' . esc_attr( $option ) . '" ' . selected( sanitize_key( (string) ( $my_vote['vote'] ?? 'pending' ) ), $option, false ) . '>' . esc_html( wpssb_get_project_rehearsal_vote_label( $option ) ) . '</option>';
            }
            $output .= '</select></label>';
            $output .= '<label><span>' . esc_html__( 'Comentario', 'wp-song-study-blocks' ) . '</span><input type="text" name="vote_comment" value="' . esc_attr( (string) ( $my_vote['comment'] ?? '' ) ) . '" placeholder="' . esc_attr__( 'Ej. sí llego, pero necesito salir puntual.', 'wp-song-study-blocks' ) . '" /></label>';
            $output .= '<button type="submit" class="wp-block-button__link wp-element-button is-style-outline">' . esc_html__( 'Actualizar respuesta', 'wp-song-study-blocks' ) . '</button>';
            $output .= '</form>';
        }
        $output .= '</div>';
    }

    if ( 'logbook' === $kind && ! empty( $session['attendance'] ) && is_array( $session['attendance'] ) ) {
        $output .= '<div class="pd-rehearsal-calendar-event-detail__block">';
        $output .= '<strong>' . esc_html__( 'Asistencia registrada', 'wp-song-study-blocks' ) . '</strong>';
        $output .= '<ul class="pd-rehearsal-calendar-event-detail__people">';
        foreach ( $session['attendance'] as $attendance ) {
            if ( ! is_array( $attendance ) ) {
                continue;
            }

            $attendance_name    = sanitize_text_field( (string) ( $attendance['nombre'] ?? __( 'Integrante', 'wp-song-study-blocks' ) ) );
            $attendance_status  = sanitize_key( (string) ( $attendance['status'] ?? 'pending' ) );
            $attendance_comment = sanitize_text_field( (string) ( $attendance['comment'] ?? '' ) );
            $output .= '<li><span>' . esc_html( $attendance_name ) . '</span><strong>' . esc_html( wpssb_get_project_rehearsal_attendance_label( $attendance_status ) ) . '</strong>';
            if ( '' !== $attendance_comment ) {
                $output .= '<small>' . esc_html( $attendance_comment ) . '</small>';
            }
            $output .= '</li>';
        }
        $output .= '</ul></div>';
    }

    if ( ! empty( $calendar['html_link'] ) ) {
        $output .= '<p class="pd-rehearsal-calendar-event-detail__calendar"><a href="' . esc_url( (string) $calendar['html_link'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Abrir evento de Google Calendar', 'wp-song-study-blocks' ) . '</a></p>';
    }

    $output .= '</div>';

    return $output;
}

/**
 * Renderiza una vista mensual integral con propuestas, confirmadas y bitácora.
 *
 * @param array<int, array<string, mixed>> $sessions      Sesiones saneadas.
 * @param int                              $now_timestamp Timestamp de referencia.
 * @param int                              $project_id    Proyecto actual.
 * @param int                              $viewer_id     Usuario actual.
 * @param string                           $action_url    URL admin-post.
 * @param string                           $redirect_url  URL de retorno.
 * @return string
 */
function wpssb_render_frontend_rehearsal_integrated_calendar( $sessions, $now_timestamp, $project_id = 0, $viewer_id = 0, $action_url = '', $redirect_url = '' ) {
    if ( ! is_array( $sessions ) ) {
        return '';
    }

    $events = [];

    foreach ( $sessions as $session ) {
        if ( ! is_array( $session ) || empty( $session['scheduled_for'] ) ) {
            continue;
        }

        $timestamp = wpssb_get_project_rehearsal_session_timestamp( $session );
        if ( false === $timestamp ) {
            continue;
        }

        $badge = wpssb_get_frontend_rehearsal_calendar_event_badge( $session, $now_timestamp );
        $kind  = sanitize_key( (string) ( $badge['kind'] ?? '' ) );

        $events[] = [
            'timestamp' => $timestamp,
            'month'     => wp_date( 'Y-m', $timestamp ),
            'day'       => (int) wp_date( 'j', $timestamp ),
            'time'      => trim( sanitize_text_field( (string) ( $session['start_time'] ?? '' ) ) . ( ! empty( $session['end_time'] ) ? ' - ' . sanitize_text_field( (string) $session['end_time'] ) : '' ) ),
            'title'     => sanitize_text_field( ! empty( $session['focus'] ) ? (string) $session['focus'] : __( 'Sesión general', 'wp-song-study-blocks' ) ),
            'schedule'  => wpssb_format_project_rehearsal_schedule( $session ),
            'kind'      => $kind,
            'icon'      => (string) ( $badge['icon'] ?? '' ),
            'label'     => sanitize_text_field( (string) ( $badge['label'] ?? '' ) ),
            'detail'    => wpssb_render_frontend_rehearsal_calendar_event_detail( $session, $kind, $project_id, $viewer_id, $action_url, $redirect_url ),
        ];
    }

    usort(
        $events,
        static function ( $left, $right ) {
            return (int) ( $left['timestamp'] ?? 0 ) <=> (int) ( $right['timestamp'] ?? 0 );
        }
    );

    $output  = '<div class="pd-rehearsal-integrated-calendar">';
    $output .= '<div class="pd-rehearsal-session-group__header">';
    $output .= '<h3>' . esc_html__( 'Calendario integral', 'wp-song-study-blocks' ) . '</h3>';
    $output .= '<p>' . esc_html__( 'Una sola vista para ubicar propuestas, ensayos confirmados y sesiones que ya viven en la bitácora.', 'wp-song-study-blocks' ) . '</p>';
    $output .= '</div>';
    $output .= '<div class="pd-rehearsal-integrated-calendar__legend" aria-label="' . esc_attr__( 'Leyenda del calendario', 'wp-song-study-blocks' ) . '">';
    $output .= '<span class="is-proposal"><strong aria-hidden="true">?</strong>' . esc_html__( 'Propuesta', 'wp-song-study-blocks' ) . '</span>';
    $output .= '<span class="is-confirmed"><strong aria-hidden="true">&#127919;</strong>' . esc_html__( 'Confirmado', 'wp-song-study-blocks' ) . '</span>';
    $output .= '<span class="is-logbook"><strong aria-hidden="true">&#10003;</strong>' . esc_html__( 'Bitácora', 'wp-song-study-blocks' ) . '</span>';
    $output .= '<span class="is-cancelled"><strong aria-hidden="true">&times;</strong>' . esc_html__( 'Cancelado', 'wp-song-study-blocks' ) . '</span>';
    $output .= '<span class="is-expired"><strong aria-hidden="true">&#8987;</strong>' . esc_html__( 'Propuesta expirada', 'wp-song-study-blocks' ) . '</span>';
    $output .= '</div>';

    if ( empty( $events ) ) {
        $output .= '<p>' . esc_html__( 'Todavía no hay sesiones para mostrar en el calendario integral.', 'wp-song-study-blocks' ) . '</p>';
        $output .= '</div>';

        return $output;
    }

    $events_by_month = [];
    foreach ( $events as $event ) {
        $month = (string) ( $event['month'] ?? '' );
        if ( '' === $month ) {
            continue;
        }

        if ( ! isset( $events_by_month[ $month ] ) ) {
            $events_by_month[ $month ] = [];
        }

        $events_by_month[ $month ][] = $event;
    }

    $weekday_labels = [
        __( 'Lun', 'wp-song-study-blocks' ),
        __( 'Mar', 'wp-song-study-blocks' ),
        __( 'Mié', 'wp-song-study-blocks' ),
        __( 'Jue', 'wp-song-study-blocks' ),
        __( 'Vie', 'wp-song-study-blocks' ),
        __( 'Sáb', 'wp-song-study-blocks' ),
        __( 'Dom', 'wp-song-study-blocks' ),
    ];

    foreach ( $events_by_month as $month_key => $month_events ) {
        try {
            $month_start = new DateTimeImmutable( $month_key . '-01 00:00:00', wp_timezone() );
        } catch ( Exception $exception ) {
            continue;
        }

        $days_in_month = (int) $month_start->format( 't' );
        $first_weekday = (int) $month_start->format( 'N' );
        $events_by_day = [];

        foreach ( $month_events as $event ) {
            $day = absint( $event['day'] ?? 0 );
            if ( $day <= 0 ) {
                continue;
            }

            if ( ! isset( $events_by_day[ $day ] ) ) {
                $events_by_day[ $day ] = [];
            }

            $events_by_day[ $day ][] = $event;
        }

        $output .= '<section class="pd-rehearsal-calendar-month">';
        $output .= '<h4>' . esc_html( wp_date( 'F Y', $month_start->getTimestamp() ) ) . '</h4>';
        $output .= '<div class="pd-rehearsal-calendar-month__weekdays" aria-hidden="true">';
        foreach ( $weekday_labels as $weekday_label ) {
            $output .= '<span>' . esc_html( $weekday_label ) . '</span>';
        }
        $output .= '</div>';
        $output .= '<div class="pd-rehearsal-calendar-month__grid">';

        for ( $blank = 1; $blank < $first_weekday; $blank++ ) {
            $output .= '<div class="pd-rehearsal-calendar-day is-empty" aria-hidden="true"></div>';
        }

        for ( $day = 1; $day <= $days_in_month; $day++ ) {
            $day_events = isset( $events_by_day[ $day ] ) && is_array( $events_by_day[ $day ] ) ? $events_by_day[ $day ] : [];

            $output .= '<div class="pd-rehearsal-calendar-day' . ( ! empty( $day_events ) ? ' has-events' : '' ) . '">';
            $output .= '<span class="pd-rehearsal-calendar-day__number">' . (int) $day . '</span>';

            foreach ( $day_events as $event ) {
                $event_detail_id = 'pd-rehearsal-calendar-event-detail-' . md5( (string) ( $event['timestamp'] ?? '' ) . '|' . (string) ( $event['title'] ?? '' ) . '|' . (string) ( $event['kind'] ?? '' ) );
                $event_title     = sanitize_text_field( (string) ( $event['title'] ?? '' ) );

                $output .= '<div class="pd-rehearsal-calendar-event is-' . esc_attr( (string) ( $event['kind'] ?? '' ) ) . '">';
                $output .= '<button type="button" class="pd-rehearsal-calendar-event__trigger" data-rehearsal-calendar-event-open="' . esc_attr( $event_detail_id ) . '" data-rehearsal-calendar-event-title="' . esc_attr( $event_title ) . '" aria-haspopup="dialog">';
                $output .= '<span class="pd-rehearsal-calendar-event__icon" aria-hidden="true">' . wp_kses_post( (string) ( $event['icon'] ?? '' ) ) . '</span>';
                $output .= '<span class="pd-rehearsal-calendar-event__copy">';
                $output .= '<strong>' . esc_html( $event_title ) . '</strong>';
                $output .= '<small>' . esc_html( trim( (string) ( $event['label'] ?? '' ) . ( ! empty( $event['time'] ) ? ' · ' . (string) $event['time'] : '' ) ) ) . '</small>';
                $output .= '</span>';
                $output .= '</button>';
                $output .= '<div id="' . esc_attr( $event_detail_id ) . '" class="pd-rehearsal-calendar-event__detail-source" hidden>' . (string) ( $event['detail'] ?? '' ) . '</div>';
                $output .= '</div>';
            }

            $output .= '</div>';
        }

        $output .= '</div>';
        $output .= '</section>';
    }

    $output .= '<div class="pd-rehearsal-calendar-modal" data-rehearsal-calendar-event-modal hidden aria-hidden="true">';
    $output .= '<div class="pd-rehearsal-calendar-modal__backdrop" data-rehearsal-calendar-event-close></div>';
    $output .= '<section class="pd-rehearsal-calendar-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="pd-rehearsal-calendar-modal-title">';
    $output .= '<button type="button" class="pd-rehearsal-calendar-modal__close" data-rehearsal-calendar-event-close aria-label="' . esc_attr__( 'Cerrar detalle del evento', 'wp-song-study-blocks' ) . '">&times;</button>';
    $output .= '<p class="pd-rehearsal-session-card__eyebrow">' . esc_html__( 'Detalle del evento', 'wp-song-study-blocks' ) . '</p>';
    $output .= '<h3 id="pd-rehearsal-calendar-modal-title" data-rehearsal-calendar-event-modal-title></h3>';
    $output .= '<div data-rehearsal-calendar-event-modal-content></div>';
    $output .= '</section>';
    $output .= '</div>';

    $output .= '</div>';

    return $output;
}

/**
 * Abre un carrusel de tarjetas de ensayo.
 *
 * @param string $label      Etiqueta accesible.
 * @param string $list_class Clase base del listado interno.
 * @return string
 */
function wpssb_render_frontend_rehearsal_card_carousel_open( $label, $list_class = 'pd-rehearsal-session-list' ) {
    $label      = sanitize_text_field( (string) $label );
    $list_class = trim( sanitize_html_class( (string) $list_class ) );
    if ( '' === $list_class ) {
        $list_class = 'pd-rehearsal-session-list';
    }

    $output  = '<div class="pd-rehearsal-card-carousel" data-rehearsal-card-carousel aria-label="' . esc_attr( $label ) . '">';
    $output .= '<div class="pd-rehearsal-card-carousel__nav">';
    $output .= '<button type="button" class="pd-rehearsal-card-carousel__button" data-rehearsal-carousel-prev aria-label="' . esc_attr__( 'Ver elemento anterior', 'wp-song-study-blocks' ) . '"><span aria-hidden="true">&#x2039;</span></button>';
    $output .= '<span class="pd-rehearsal-card-carousel__status" data-rehearsal-carousel-status aria-live="polite">1 / 1</span>';
    $output .= '<button type="button" class="pd-rehearsal-card-carousel__button" data-rehearsal-carousel-next aria-label="' . esc_attr__( 'Ver elemento siguiente', 'wp-song-study-blocks' ) . '"><span aria-hidden="true">&#x203A;</span></button>';
    $output .= '</div>';
    $output .= '<div class="pd-rehearsal-card-carousel__viewport">';
    $output .= '<div class="' . esc_attr( $list_class ) . ' pd-rehearsal-card-carousel__track" data-rehearsal-carousel-track>';

    return $output;
}

/**
 * Cierra un carrusel de tarjetas de ensayo.
 *
 * @return string
 */
function wpssb_render_frontend_rehearsal_card_carousel_close() {
    return '</div></div></div>';
}

/**
 * Renderiza el workspace frontend de ensayos del usuario actual.
 *
 * @param array<string, mixed> $settings Ajustes visuales.
 * @return string
 */
function wpssb_render_current_rehearsals_markup( $settings = [] ) {
    $settings = wp_parse_args(
        $settings,
        [
            'show_admin_link' => true,
            'login_message'   => __( 'El Planificador de ensayos es un espacio privado para colegas musicales, colaboradores y administradores que ya pertenecen a un proyecto musical. Inicia sesion con una cuenta autorizada para registrar disponibilidad, responder propuestas y revisar la bitacora del proyecto.', 'wp-song-study-blocks' ),
        ]
    );

    $current_url = wpssb_get_frontend_rehearsal_page_base_url();

    if ( ! is_user_logged_in() || ! wpssb_user_can_access_private_music_area() ) {
        if ( function_exists( 'pd_render_music_access_gate_panel' ) ) {
            return pd_render_music_access_gate_panel(
                [
                    'context'     => 'rehearsals',
                    'redirect_to' => $current_url,
                    'intro'       => $settings['login_message'],
                ]
            );
        }

        if ( ! is_user_logged_in() ) {
            return '<div class="pd-rehearsal-shell"><p>' . esc_html( $settings['login_message'] ) . '</p></div>';
        }

        $switch_url = wp_logout_url( wp_login_url( $current_url ) );

        return '<div class="pd-rehearsal-shell"><p>' . esc_html__( 'Tu cuenta inició sesion, pero todavía no pertenece a un proyecto musical con acceso al Planificador de ensayos. Usa una cuenta vinculada al proyecto correcto.', 'wp-song-study-blocks' ) . '</p><p><a class="wp-block-button__link wp-element-button" href="' . esc_url( $switch_url ) . '">' . esc_html__( 'Usar otra cuenta', 'wp-song-study-blocks' ) . '</a></p></div>';
    }

    $viewer_id = get_current_user_id();
    $projects  = wpssb_get_frontend_rehearsal_projects_for_user( $viewer_id );

    if ( empty( $projects ) ) {
        $output  = '<section class="pd-rehearsal-shell" data-rehearsal-shell>';
        $output .= '<div class="pd-rehearsal-panel">';
        $output .= '<p class="pd-membership-shell__eyebrow">' . esc_html__( 'Planificador de ensayos', 'wp-song-study-blocks' ) . '</p>';
        $output .= '<h2>' . esc_html__( 'Todavía no tienes proyectos musicales vinculados', 'wp-song-study-blocks' ) . '</h2>';
        $output .= '<p>' . esc_html__( 'Cuando un proyecto musical te agregue como integrante, aquí verás tu disponibilidad, las propuestas activas y la bitácora del grupo.', 'wp-song-study-blocks' ) . '</p>';
        $output .= '</div></section>';

        return $output;
    }

    $project_id    = wpssb_resolve_frontend_rehearsal_project_id( $projects );
    $project       = get_post( $project_id );
    $payload       = wpssb_get_project_rehearsal_payload( $project_id );
    $summary       = is_array( $payload['summary'] ?? null ) ? $payload['summary'] : [];
    $sessions      = is_array( $payload['sessions'] ?? null ) ? $payload['sessions'] : [];
    $availability  = is_array( $payload['availability'] ?? null ) ? $payload['availability'] : [];
    $google_calendar = is_array( $payload['project']['google_calendar'] ?? null ) ? $payload['project']['google_calendar'] : [];
    $recommendations = is_array( $payload['recommended_slots'] ?? null ) ? $payload['recommended_slots'] : [];
    $day_labels    = wpssb_get_project_rehearsal_day_labels();
    $feedback      = wpssb_get_frontend_rehearsal_feedback();
    $member_entry  = wpssb_get_project_rehearsal_member_entry( $availability, $viewer_id );
    $member_directory = wpssb_get_frontend_rehearsal_member_directory( $project_id, $availability, $viewer_id );
    $action_url    = admin_url( 'admin-post.php' );
    $requested_tab = isset( $_GET['rehearsal_tab'] ) ? sanitize_key( wp_unslash( $_GET['rehearsal_tab'] ) ) : 'availability';
    $current_tab   = in_array( $requested_tab, [ 'availability', 'calendar', 'logbook' ], true ) ? $requested_tab : 'availability';
    $schedule_url  = wpssb_get_frontend_rehearsal_url( $project_id, 'availability' );
    $calendar_url  = wpssb_get_frontend_rehearsal_url( $project_id, 'calendar' );
    $logbook_url   = wpssb_get_frontend_rehearsal_url( $project_id, 'logbook' );
    $requested_calendar_view = isset( $_GET['rehearsal_calendar_view'] ) ? sanitize_key( wp_unslash( $_GET['rehearsal_calendar_view'] ) ) : 'overview';
    $calendar_view = in_array( $requested_calendar_view, [ 'overview', 'proposals', 'confirmed', 'logbook' ], true ) ? $requested_calendar_view : 'overview';
    $calendar_overview_url = add_query_arg( 'rehearsal_calendar_view', 'overview', $calendar_url );
    $calendar_proposals_url = add_query_arg( 'rehearsal_calendar_view', 'proposals', $calendar_url );
    $calendar_confirmed_url = add_query_arg( 'rehearsal_calendar_view', 'confirmed', $calendar_url );
    $now_timestamp = current_time( 'timestamp' );
    $completed_sessions = array_values(
        array_filter(
            $sessions,
            static function ( $session ) {
                return is_array( $session ) && 'completed' === ( $session['status'] ?? '' );
            }
        )
    );
    $proposal_sessions = array_values(
        array_filter(
            $sessions,
            static function ( $session ) {
                return is_array( $session ) && in_array( sanitize_key( (string) ( $session['status'] ?? '' ) ), [ 'proposed', 'voting' ], true );
            }
        )
    );
    $confirmed_sessions = array_values(
        array_filter(
            $sessions,
            static function ( $session ) use ( $now_timestamp ) {
                if ( ! is_array( $session ) ) {
                    return false;
                }

                $status = sanitize_key( (string) ( $session['status'] ?? '' ) );
                if ( 'cancelled' === $status ) {
                    return true;
                }

                return 'confirmed' === $status
                    && ! wpssb_frontend_rehearsal_session_is_logbook_available( $session, $now_timestamp );
            }
        )
    );
    $logbook_sessions = array_values(
        array_filter(
            $sessions,
            static function ( $session ) use ( $now_timestamp ) {
                return is_array( $session ) && wpssb_frontend_rehearsal_session_is_logbook_available( $session, $now_timestamp );
            }
        )
    );

    usort( $proposal_sessions, 'wpssb_compare_project_rehearsal_sessions_by_schedule' );
    usort( $confirmed_sessions, 'wpssb_compare_project_rehearsal_sessions_by_schedule' );
    usort(
        $logbook_sessions,
        static function ( $left, $right ) use ( $now_timestamp ) {
            $left_is_live  = 'completed' !== sanitize_key( (string) ( $left['status'] ?? '' ) );
            $right_is_live = 'completed' !== sanitize_key( (string) ( $right['status'] ?? '' ) );

            if ( $left_is_live !== $right_is_live ) {
                return $left_is_live ? -1 : 1;
            }

            return wpssb_compare_project_rehearsal_sessions_by_schedule( $right, $left );
        }
    );

    $next_session_label = '';

    if ( ! empty( $summary['next_session_iso'] ) ) {
        $next_timestamp = strtotime( (string) $summary['next_session_iso'] );
        if ( false !== $next_timestamp ) {
            $next_session_label = wp_date( 'j M Y · H:i', $next_timestamp );
        }
    }

    wpssb_enqueue_frontend_rehearsal_assets();

    $output  = '<section class="pd-rehearsal-shell" data-rehearsal-shell data-rehearsal-project-id="' . (int) $project_id . '">';
    $output .= '<header class="pd-membership-shell__header pd-rehearsal-shell__header">';
    $output .= '<div class="pd-membership-shell__identity">';
    $output .= '<div>';
    $output .= '<p class="pd-membership-shell__eyebrow">' . esc_html__( 'Música · Planificador de ensayos', 'wp-song-study-blocks' ) . '</p>';
    $output .= '<h1 class="pd-membership-shell__title">' . esc_html( $project instanceof WP_Post ? get_the_title( $project ) : __( 'Planificador de ensayos', 'wp-song-study-blocks' ) ) . '</h1>';
    $output .= '<p class="pd-membership-shell__meta">' . esc_html( sprintf( __( '%1$s integrantes · %2$s propuestas/ensayos registrados', 'wp-song-study-blocks' ), (int) ( $summary['total_members'] ?? 0 ), (int) ( $summary['sessions_total'] ?? 0 ) ) ) . '</p>';
    $output .= '</div></div>';
    $output .= '<div class="pd-membership-shell__actions">';
    if ( ! empty( $next_session_label ) ) {
        $output .= '<span class="pd-rehearsal-pill">' . esc_html( sprintf( __( 'Próximo confirmado: %s', 'wp-song-study-blocks' ), $next_session_label ) ) . '</span>';
    }
    if ( ! empty( $settings['show_admin_link'] ) && function_exists( 'wpss_user_can_manage_songbook' ) && wpss_user_can_manage_songbook( $viewer_id ) ) {
        $output .= '<a class="wp-block-button__link wp-element-button is-style-outline" href="' . esc_url( admin_url( 'admin.php?page=wpss-ensayos-proyecto' ) ) . '">' . esc_html__( 'Abrir administración', 'wp-song-study-blocks' ) . '</a>';
    }
    $output .= '</div>';
    $output .= '</header>';

    $output .= '<div class="pd-rehearsal-panel pd-rehearsal-panel--switcher">';
    if ( count( $projects ) > 1 ) {
        $output .= '<form class="pd-membership-switcher pd-rehearsal-project-switcher" method="get" action="' . esc_url( $current_url ) . '" data-rehearsal-project-switcher>';
        $output .= '<label><span>' . esc_html__( 'Proyecto activo', 'wp-song-study-blocks' ) . '</span>';
        $output .= '<select name="rehearsal_project" data-rehearsal-project-selector>';
        foreach ( $projects as $candidate ) {
            if ( ! $candidate instanceof WP_Post ) {
                continue;
            }
            $output .= '<option value="' . (int) $candidate->ID . '" ' . selected( $project_id, (int) $candidate->ID, false ) . '>' . esc_html( get_the_title( $candidate ) ) . '</option>';
        }
        $output .= '</select></label>';
        $output .= '<input type="hidden" name="rehearsal_tab" value="' . esc_attr( $current_tab ) . '" />';
        $output .= '<input type="hidden" name="rehearsal_calendar_view" value="' . esc_attr( $calendar_view ) . '" />';
        $output .= '<noscript><button type="submit" class="wp-block-button__link wp-element-button is-style-outline">' . esc_html__( 'Cambiar proyecto', 'wp-song-study-blocks' ) . '</button></noscript>';
        $output .= '</form>';
    }

    $output .= '<nav class="pd-membership-tabs pd-rehearsal-tabs" aria-label="' . esc_attr__( 'Secciones de ensayos', 'wp-song-study-blocks' ) . '" data-rehearsal-tabs data-rehearsal-query="rehearsal_tab">';
    $output .= '<button type="button" class="pd-membership-tabs__tab' . ( 'availability' === $current_tab ? ' is-active' : '' ) . '" role="tab" aria-selected="' . ( 'availability' === $current_tab ? 'true' : 'false' ) . '" aria-controls="pd-rehearsal-panel-availability" id="pd-rehearsal-tab-availability" data-rehearsal-tab="availability">' . esc_html__( 'Ventanas de tiempo', 'wp-song-study-blocks' ) . '</button>';
    $output .= '<button type="button" class="pd-membership-tabs__tab' . ( 'calendar' === $current_tab ? ' is-active' : '' ) . '" role="tab" aria-selected="' . ( 'calendar' === $current_tab ? 'true' : 'false' ) . '" aria-controls="pd-rehearsal-panel-calendar" id="pd-rehearsal-tab-calendar" data-rehearsal-tab="calendar">' . esc_html__( 'Calendario', 'wp-song-study-blocks' ) . '</button>';
    $output .= '<button type="button" class="pd-membership-tabs__tab' . ( 'logbook' === $current_tab ? ' is-active' : '' ) . '" role="tab" aria-selected="' . ( 'logbook' === $current_tab ? 'true' : 'false' ) . '" aria-controls="pd-rehearsal-panel-logbook" id="pd-rehearsal-tab-logbook" data-rehearsal-tab="logbook">' . esc_html__( 'Bitácora', 'wp-song-study-blocks' ) . '</button>';
    $output .= '</nav>';
    $output .= '</div>';

    if ( is_array( $feedback ) && ! empty( $feedback['message'] ) ) {
        $class   = 'success' === ( $feedback['type'] ?? '' ) ? 'is-success' : 'is-error';
        $output .= '<p class="pd-membership-feedback ' . esc_attr( $class ) . '">' . esc_html( $feedback['message'] ) . '</p>';
    }

    $output .= '<div class="pd-rehearsal-sections" data-rehearsal-panels>';

    $output .= '<section class="pd-rehearsal-section pd-rehearsal-section--availability" id="pd-rehearsal-panel-availability" data-rehearsal-panel="availability" role="tabpanel" aria-labelledby="pd-rehearsal-tab-availability"' . ( 'availability' === $current_tab ? '' : ' hidden' ) . '>';
    $output .= '<div class="pd-rehearsal-grid">';
    $output .= '<div class="pd-rehearsal-panel pd-rehearsal-panel--form pd-rehearsal-panel--secondary">';
    $output .= '<header class="pd-membership-section__header">';
    $output .= '<div class="pd-membership-section__intro">';
    $output .= '<p class="pd-membership-shell__eyebrow">' . esc_html__( 'Ventanas base', 'wp-song-study-blocks' ) . '</p>';
    $output .= '<h2>' . esc_html__( 'Disponibilidad semanal del grupo', 'wp-song-study-blocks' ) . '</h2>';
    $output .= '<p>' . esc_html__( 'Navega integrante por integrante en el mismo lugar. Cada persona edita solo su propia disponibilidad, pero el grupo completo puede revisar los horarios cargados.', 'wp-song-study-blocks' ) . '</p>';
    $output .= '</div></header>';
    $output .= '<div class="pd-rehearsal-member-editor" data-rehearsal-member-editor>';
    $output .= '<div class="pd-rehearsal-member-editor__nav-wrap">';
    $output .= '<button type="button" class="pd-rehearsal-member-editor__arrow" data-rehearsal-member-prev aria-label="' . esc_attr__( 'Ir al integrante anterior', 'wp-song-study-blocks' ) . '"><span aria-hidden="true">&#x2039;</span></button>';
    $output .= '<div class="pd-rehearsal-member-editor__nav-shell">';
    $output .= '<div class="pd-rehearsal-member-editor__nav" role="tablist" aria-label="' . esc_attr__( 'Integrantes del proyecto', 'wp-song-study-blocks' ) . '" data-rehearsal-member-tabs>';
    foreach ( $member_directory as $member_index => $member_state ) {
        if ( ! is_array( $member_state ) ) {
            continue;
        }

        $member_id      = absint( $member_state['user_id'] ?? 0 );
        $member_name    = sanitize_text_field( (string) ( $member_state['nombre'] ?? '' ) );
        $is_member_self = ! empty( $member_state['is_current_user'] );
        $is_member_active = 0 === (int) $member_index;
        $member_avatar  = get_avatar(
            $member_id,
            40,
            '',
            $member_name,
            [
                'class' => 'pd-rehearsal-member-editor__tab-avatar-image',
            ]
        );
        $tab_role_label   = $is_member_self ? __( 'Editable', 'wp-song-study-blocks' ) : __( 'Solo lectura', 'wp-song-study-blocks' );
        $tab_summary_text = sanitize_text_field( (string) ( $member_state['summary_text'] ?? '' ) );

        if ( '' === $tab_summary_text ) {
            $tab_summary_text = __( 'Sin horarios aún', 'wp-song-study-blocks' );
        }

        $output .= '<button type="button" class="pd-rehearsal-member-editor__tab' . ( $is_member_active ? ' is-active' : '' ) . '" id="pd-rehearsal-member-tab-' . esc_attr( $member_id ) . '" role="tab" aria-selected="' . ( $is_member_active ? 'true' : 'false' ) . '" aria-controls="pd-rehearsal-member-panel-' . esc_attr( $member_id ) . '" data-rehearsal-member-tab="' . esc_attr( (string) $member_id ) . '">';
        $output .= '<span class="pd-rehearsal-member-editor__tab-avatar" aria-hidden="true">' . $member_avatar . '</span>';
        $output .= '<span class="pd-rehearsal-member-editor__tab-copy">';
        $output .= '<span class="pd-rehearsal-member-editor__tab-label">' . esc_html( $member_name ) . '</span>';
        $output .= '<small class="pd-rehearsal-member-editor__tab-meta" data-rehearsal-member-tab-meta>';
        $output .= '<span data-rehearsal-member-tab-role>' . esc_html( $tab_role_label ) . '</span>';
        $output .= '<span aria-hidden="true"> · </span>';
        $output .= '<span data-rehearsal-member-tab-summary>' . esc_html( $tab_summary_text ) . '</span>';
        $output .= '</small>';
        $output .= '</span>';
        $output .= '</button>';
    }
    $output .= '</div>';
    $output .= '</div>';
    $output .= '<button type="button" class="pd-rehearsal-member-editor__arrow" data-rehearsal-member-next aria-label="' . esc_attr__( 'Ir al siguiente integrante', 'wp-song-study-blocks' ) . '"><span aria-hidden="true">&#x203A;</span></button>';
    $output .= '</div>';
    $output .= '<div class="pd-rehearsal-member-editor__panels">';
    foreach ( $member_directory as $member_index => $member_state ) {
        if ( ! is_array( $member_state ) ) {
            continue;
        }

        $is_member_active = 0 === (int) $member_index;
        $member_panel     = wpssb_render_frontend_rehearsal_member_panel( $project_id, $member_state, $action_url, $schedule_url, $is_member_active );

        if ( $is_member_active ) {
            $output .= $member_panel;
            continue;
        }

        $member_id = absint( $member_state['user_id'] ?? 0 );
        $output   .= '<template data-rehearsal-member-template="' . esc_attr( (string) $member_id ) . '">' . $member_panel . '</template>';
    }
    $output .= '</div>';
    $output .= '</div>';
    $output .= '</div>';

    $output .= '<aside class="pd-rehearsal-sidebar">';
    $output .= '<div class="pd-rehearsal-panel">';
    $output .= '<h3>' . esc_html__( 'Resumen del proyecto', 'wp-song-study-blocks' ) . '</h3>';
    $output .= '<dl class="pd-rehearsal-summary">';
    $output .= '<div><dt>' . esc_html__( 'Integrantes', 'wp-song-study-blocks' ) . '</dt><dd>' . (int) ( $summary['total_members'] ?? 0 ) . '</dd></div>';
    $output .= '<div><dt>' . esc_html__( 'Con disponibilidad cargada', 'wp-song-study-blocks' ) . '</dt><dd>' . (int) ( $summary['members_with_availability'] ?? 0 ) . '</dd></div>';
    $output .= '<div><dt>' . esc_html__( 'Propuestas activas', 'wp-song-study-blocks' ) . '</dt><dd>' . (int) ( $summary['proposal_sessions'] ?? 0 ) . '</dd></div>';
    $output .= '<div><dt>' . esc_html__( 'Ensayos realizados', 'wp-song-study-blocks' ) . '</dt><dd>' . (int) ( $summary['completed_sessions'] ?? 0 ) . '</dd></div>';
    $output .= '</dl>';
    if ( ! empty( $member_entry['updated_at'] ) ) {
        $updated_timestamp = strtotime( (string) $member_entry['updated_at'] );
        if ( false !== $updated_timestamp ) {
            $output .= '<p class="pd-rehearsal-panel__meta">' . esc_html( sprintf( __( 'Última actualización personal: %s', 'wp-song-study-blocks' ), wp_date( 'j M Y · H:i', $updated_timestamp ) ) ) . '</p>';
        }
    }
    $output .= '</div>';

    $output .= '<div class="pd-rehearsal-panel">';
    $output .= '<h3>' . esc_html__( 'Ventanas sugeridas', 'wp-song-study-blocks' ) . '</h3>';
    if ( empty( $recommendations ) ) {
        $output .= '<p>' . esc_html__( 'Todavía no hay suficientes cruces de horario para sugerir una ventana común.', 'wp-song-study-blocks' ) . '</p>';
    } else {
        $output .= '<ul class="pd-rehearsal-recommendations">';
        foreach ( $recommendations as $slot ) {
            if ( ! is_array( $slot ) ) {
                continue;
            }
            $labels = wpssb_get_project_rehearsal_day_labels();
            $slot_day = sanitize_key( (string) ( $slot['day'] ?? '' ) );
            $slot_label = isset( $labels[ $slot_day ] ) ? $labels[ $slot_day ] : ucfirst( $slot_day );
            $output .= '<li>';
            $output .= '<strong>' . esc_html( $slot_label . ' · ' . sanitize_text_field( (string) ( $slot['start'] ?? '' ) ) . ' - ' . sanitize_text_field( (string) ( $slot['end'] ?? '' ) ) ) . '</strong>';
            $output .= '<span>' . esc_html( sprintf( __( '%1$s integrantes · %2$s min', 'wp-song-study-blocks' ), (int) ( $slot['member_count'] ?? 0 ), (int) ( $slot['duration_minutes'] ?? 0 ) ) ) . '</span>';
            if ( ! empty( $slot['member_names'] ) && is_array( $slot['member_names'] ) ) {
                $output .= '<small>' . esc_html( implode( ', ', array_map( 'sanitize_text_field', $slot['member_names'] ) ) ) . '</small>';
            }
            $output .= '<form class="pd-rehearsal-recommendation-form" method="post" action="' . esc_url( $action_url ) . '">';
            $output .= '<input type="hidden" name="action" value="wpssb_create_frontend_rehearsal_proposal" />';
            $output .= '<input type="hidden" name="project_id" value="' . (int) $project_id . '" />';
            $output .= '<input type="hidden" name="redirect_to" value="' . esc_url( $calendar_proposals_url ) . '" />';
            $output .= '<input type="hidden" name="slot_day" value="' . esc_attr( $slot_day ) . '" />';
            $output .= '<input type="hidden" name="slot_start" value="' . esc_attr( sanitize_text_field( (string) ( $slot['start'] ?? '' ) ) ) . '" />';
            $output .= '<input type="hidden" name="slot_end" value="' . esc_attr( sanitize_text_field( (string) ( $slot['end'] ?? '' ) ) ) . '" />';
            $output .= '<input type="hidden" name="scheduled_for" value="' . esc_attr( wpssb_get_frontend_rehearsal_default_date_for_day( $slot_day ) ) . '" />';
            $output .= wp_nonce_field( 'wpssb_create_rehearsal_proposal', 'wpssb_rehearsal_proposal_nonce', true, false );
            $output .= '<button type="submit" class="wp-block-button__link wp-element-button is-style-outline pd-rehearsal-recommendation-form__button">' . esc_html__( 'Convertir en propuesta', 'wp-song-study-blocks' ) . '</button>';
            $output .= '</form>';
            $output .= '</li>';
        }
        $output .= '</ul>';
    }
    $output .= '</div>';
    $output .= '</aside>';
    $output .= '</div>';
    $output .= '</section>';

    $output .= '<section class="pd-rehearsal-section" id="pd-rehearsal-panel-calendar" data-rehearsal-panel="calendar" role="tabpanel" aria-labelledby="pd-rehearsal-tab-calendar"' . ( 'calendar' === $current_tab ? '' : ' hidden' ) . '>';
    $output .= '<div class="pd-rehearsal-panel">';
    $output .= '<header class="pd-membership-section__header">';
    $output .= '<div class="pd-membership-section__intro">';
    $output .= '<p class="pd-membership-shell__eyebrow">' . esc_html__( 'Calendario del proyecto', 'wp-song-study-blocks' ) . '</p>';
    $output .= '<h2>' . esc_html__( 'Propuestas de ensayo y ensayos agendados', 'wp-song-study-blocks' ) . '</h2>';
    $output .= '<p>' . esc_html__( 'Aquí puedes levantar propuestas desde las ventanas sugeridas, votar opciones abiertas y revisar lo que ya quedó confirmado. Si una sesión debe oficializarse por excepción, también puedes forzar su confirmación.', 'wp-song-study-blocks' ) . '</p>';
    $output .= '</div></header>';
    $calendar_status_class = ! empty( $google_calendar['ready'] ) ? 'is-ready' : 'is-warning';
    $calendar_status_message = sanitize_text_field( (string) ( $google_calendar['status_message'] ?? '' ) );
    if ( '' === $calendar_status_message ) {
        $calendar_status_message = __( 'Revisa el estado de Google Calendar antes de sincronizar ensayos confirmados.', 'wp-song-study-blocks' );
    }
    $output .= '<div class="pd-rehearsal-google-status ' . esc_attr( $calendar_status_class ) . '">';
    $output .= '<div class="pd-rehearsal-google-status__copy">';
    $output .= '<strong>' . esc_html__( 'Google Calendar', 'wp-song-study-blocks' ) . '</strong>';
    $output .= '<p>' . esc_html( $calendar_status_message ) . '</p>';
    if ( ! empty( $google_calendar['account_email'] ) ) {
        $output .= '<small>' . esc_html( sprintf( __( 'Cuenta conectada: %s', 'wp-song-study-blocks' ), (string) $google_calendar['account_email'] ) ) . '</small>';
    }
    if ( empty( $google_calendar['ready'] ) && ! empty( $google_calendar['missing_scope_labels'] ) && is_array( $google_calendar['missing_scope_labels'] ) ) {
        $output .= '<small>' . esc_html( sprintf( __( 'Permisos pendientes: %s', 'wp-song-study-blocks' ), implode( ' · ', array_map( 'sanitize_text_field', $google_calendar['missing_scope_labels'] ) ) ) ) . '</small>';
    }
    if ( ! empty( $google_calendar['last_error'] ) ) {
        $output .= '<small>' . esc_html( sprintf( __( 'Último error OAuth: %s', 'wp-song-study-blocks' ), (string) $google_calendar['last_error'] ) ) . '</small>';
    }
    if ( ! empty( $google_calendar['calendar_probe_message'] ) ) {
        $output .= '<small>' . esc_html( sprintf( __( 'Prueba Calendar API: %s', 'wp-song-study-blocks' ), (string) $google_calendar['calendar_probe_message'] ) ) . '</small>';
    }
    $output .= '<small>' . esc_html__( 'Cuando todos votan sí, el sistema intenta crear el evento automáticamente e invitar a los integrantes con correo registrado. Si falla, la tarjeta del ensayo guarda el motivo y permite reintentar.', 'wp-song-study-blocks' ) . '</small>';
    $output .= '</div>';
    if ( ! empty( $google_calendar['connect_url'] ) ) {
        $output .= '<div class="pd-rehearsal-google-status__actions">';
        $output .= '<a class="wp-block-button__link wp-element-button is-style-outline" href="' . esc_url( (string) $google_calendar['connect_url'] ) . '">' . esc_html( ! empty( $google_calendar['connected'] ) ? __( 'Reconectar Google Calendar', 'wp-song-study-blocks' ) : __( 'Conectar Google Calendar', 'wp-song-study-blocks' ) ) . '</a>';
        $output .= '</div>';
    }
    $output .= '</div>';
    $output .= '<nav class="pd-membership-tabs pd-rehearsal-calendar-toggle" aria-label="' . esc_attr__( 'Vista del calendario de ensayos', 'wp-song-study-blocks' ) . '" data-rehearsal-calendar-view-tabs data-rehearsal-query="rehearsal_calendar_view">';
    $output .= '<button type="button" class="pd-membership-tabs__tab' . ( 'overview' === $calendar_view ? ' is-active' : '' ) . '" aria-selected="' . ( 'overview' === $calendar_view ? 'true' : 'false' ) . '" data-rehearsal-calendar-view-tab="overview">' . esc_html__( 'Vista integral', 'wp-song-study-blocks' ) . '</button>';
    $output .= '<button type="button" class="pd-membership-tabs__tab' . ( 'proposals' === $calendar_view ? ' is-active' : '' ) . '" aria-selected="' . ( 'proposals' === $calendar_view ? 'true' : 'false' ) . '" data-rehearsal-calendar-view-tab="proposals">' . esc_html__( 'Propuestas de ensayo', 'wp-song-study-blocks' ) . '</button>';
    $output .= '<button type="button" class="pd-membership-tabs__tab' . ( 'confirmed' === $calendar_view ? ' is-active' : '' ) . '" aria-selected="' . ( 'confirmed' === $calendar_view ? 'true' : 'false' ) . '" data-rehearsal-calendar-view-tab="confirmed">' . esc_html__( 'Ensayos agendados', 'wp-song-study-blocks' ) . '</button>';
    $output .= '<button type="button" class="pd-membership-tabs__tab' . ( 'logbook' === $calendar_view ? ' is-active' : '' ) . '" aria-selected="' . ( 'logbook' === $calendar_view ? 'true' : 'false' ) . '" data-rehearsal-calendar-view-tab="logbook">' . esc_html__( 'Bitácora', 'wp-song-study-blocks' ) . '</button>';
    $output .= '</nav>';
    $output .= '<div class="pd-rehearsal-calendar-views">';
    $output .= '<div class="pd-rehearsal-session-group" data-rehearsal-calendar-view-panel="overview"' . ( 'overview' === $calendar_view ? '' : ' hidden' ) . '>';
    $output .= wpssb_render_frontend_rehearsal_integrated_calendar( $sessions, $now_timestamp, $project_id, $viewer_id, $action_url, $calendar_overview_url );
    $output .= '</div>';
    $output .= '<div class="pd-rehearsal-session-group" data-rehearsal-calendar-view-panel="proposals"' . ( 'proposals' === $calendar_view ? '' : ' hidden' ) . '>';
    $output .= '<div class="pd-rehearsal-session-group__header">';
    $output .= '<h3>' . esc_html__( 'Propuestas de ensayo', 'wp-song-study-blocks' ) . '</h3>';
    $output .= '<p>' . esc_html__( 'Estas son las propuestas reales abiertas por el grupo. Aquí puedes afinar la sesión, votar y, si hace falta, confirmarla por excepción.', 'wp-song-study-blocks' ) . '</p>';
    $output .= '</div>';

    if ( empty( $proposal_sessions ) ) {
        $output .= '<p>' . esc_html__( 'Aún no hay propuestas abiertas para votación.', 'wp-song-study-blocks' ) . '</p>';
    } else {
        $output .= wpssb_render_frontend_rehearsal_card_carousel_open( __( 'Navegar propuestas de ensayo', 'wp-song-study-blocks' ) );
        foreach ( $proposal_sessions as $session ) {
            if ( ! is_array( $session ) ) {
                continue;
            }

            $totals       = wpssb_get_project_rehearsal_vote_totals( $session );
            $my_vote      = wpssb_get_project_rehearsal_member_vote( $session, $viewer_id );
            $can_vote     = wpssb_frontend_rehearsal_session_accepts_votes( $session );
            $status       = sanitize_key( (string) ( $session['status'] ?? '' ) );
            $can_edit     = wpssb_frontend_user_can_edit_rehearsal_proposal( $session, $viewer_id );
            $creator_id   = absint( $session['created_by'] ?? 0 );
            $creator_user = $creator_id > 0 ? get_user_by( 'id', $creator_id ) : null;
            $creator_name = $creator_user instanceof WP_User ? sanitize_text_field( $creator_user->display_name ) : '';
            $session_timestamp = wpssb_get_project_rehearsal_session_timestamp( $session );

            $output .= '<article class="pd-rehearsal-session-card status-' . esc_attr( $status ) . '" data-rehearsal-carousel-timestamp="' . esc_attr( false !== $session_timestamp ? (string) $session_timestamp : '' ) . '">';
            $output .= '<div class="pd-rehearsal-proposal-layout">';
            $output .= '<div class="pd-rehearsal-proposal-main">';

            if ( $can_edit ) {
                $output .= '<form class="pd-rehearsal-proposal-form pd-rehearsal-proposal-form--integrated" method="post" action="' . esc_url( $action_url ) . '" data-rehearsal-proposal-autosave>';
                $output .= '<input type="hidden" name="action" value="wpssb_update_frontend_rehearsal_proposal" />';
                $output .= '<input type="hidden" name="project_id" value="' . (int) $project_id . '" />';
                $output .= '<input type="hidden" name="session_id" value="' . esc_attr( (string) $session['id'] ) . '" />';
                $output .= '<input type="hidden" name="redirect_to" value="' . esc_url( $calendar_proposals_url ) . '" />';
                $output .= wp_nonce_field( 'wpssb_update_rehearsal_proposal', 'wpssb_rehearsal_update_nonce', true, false );
                $output .= '<div class="pd-rehearsal-session-card__header pd-rehearsal-session-card__header--editable">';
                $output .= '<div class="pd-rehearsal-proposal-form__headline">';
                $output .= '<p class="pd-rehearsal-session-card__eyebrow">' . esc_html( wpssb_get_project_rehearsal_status_label( $status ) ) . '</p>';
                $output .= '<label class="pd-rehearsal-proposal-form__focus">';
                $output .= '<span>' . esc_html__( 'Objetivo del ensayo', 'wp-song-study-blocks' ) . '</span>';
                $output .= '<input class="pd-rehearsal-proposal-form__focus-input" type="text" name="focus" value="' . esc_attr( (string) ( $session['focus'] ?? '' ) ) . '" placeholder="' . esc_attr__( 'Sesión general', 'wp-song-study-blocks' ) . '" />';
                $output .= '</label>';
                $output .= '<div class="pd-rehearsal-proposal-form__schedule-grid">';
                $output .= '<label><span>' . esc_html__( 'Fecha propuesta', 'wp-song-study-blocks' ) . '</span><input type="date" name="scheduled_for" value="' . esc_attr( sanitize_text_field( (string) ( $session['scheduled_for'] ?? '' ) ) ) . '" required /></label>';
                $output .= '<label><span>' . esc_html__( 'Inicio', 'wp-song-study-blocks' ) . '</span><input type="time" name="start_time" value="' . esc_attr( sanitize_text_field( (string) ( $session['start_time'] ?? '' ) ) ) . '" required /></label>';
                $output .= '<label><span>' . esc_html__( 'Fin', 'wp-song-study-blocks' ) . '</span><input type="time" name="end_time" value="' . esc_attr( sanitize_text_field( (string) ( $session['end_time'] ?? '' ) ) ) . '" required /></label>';
                $output .= '<label><span>' . esc_html__( 'Lugar / sala', 'wp-song-study-blocks' ) . '</span><input type="text" name="location" value="' . esc_attr( (string) ( $session['location'] ?? '' ) ) . '" /></label>';
                $output .= '</div>';
                if ( '' !== $creator_name ) {
                    $output .= '<p class="pd-rehearsal-session-card__meta">' . esc_html( sprintf( __( 'Propuesta por: %s', 'wp-song-study-blocks' ), $creator_name ) ) . '</p>';
                }
                $output .= '</div></div>';

                if ( ! empty( $session['reviewed_items'] ) && is_array( $session['reviewed_items'] ) ) {
                    $output .= '<div class="pd-rehearsal-session-card__block">';
                    $output .= '<strong>' . esc_html__( 'Temas planeados', 'wp-song-study-blocks' ) . '</strong>';
                    $output .= '<ul>';
                    foreach ( $session['reviewed_items'] as $item ) {
                        $output .= '<li>' . esc_html( sanitize_text_field( (string) $item ) ) . '</li>';
                    }
                    $output .= '</ul></div>';
                }

                $output .= '<div class="pd-rehearsal-session-card__block pd-rehearsal-session-card__block--editable">';
                $output .= '<label class="pd-rehearsal-proposal-form__notes">';
                $output .= '<span>' . esc_html__( 'Notas de la propuesta', 'wp-song-study-blocks' ) . '</span>';
                $output .= '<textarea name="proposal_notes" rows="3" placeholder="' . esc_attr__( 'Contexto, objetivos, restricciones o pendientes para el grupo.', 'wp-song-study-blocks' ) . '">' . esc_textarea( (string) ( $session['notes'] ?? '' ) ) . '</textarea>';
                $output .= '</label>';
                $output .= '</div>';
                $output .= '<div class="pd-rehearsal-proposal-form__actions">';
                $output .= '<p class="pd-rehearsal-proposal-form__status" data-rehearsal-proposal-status role="status" aria-live="polite">' . esc_html__( 'Los cambios se guardan automáticamente.', 'wp-song-study-blocks' ) . '</p>';
                $output .= '<button type="submit" class="wp-block-button__link wp-element-button is-style-outline">' . esc_html__( 'Guardar ahora', 'wp-song-study-blocks' ) . '</button>';
                $output .= '</div>';
                $output .= '</form>';

                $output .= '<form class="pd-rehearsal-proposal-delete-form pd-rehearsal-proposal-delete-form--integrated" method="post" action="' . esc_url( $action_url ) . '" data-rehearsal-proposal-delete>';
                $output .= '<input type="hidden" name="action" value="wpssb_delete_frontend_rehearsal_proposal" />';
                $output .= '<input type="hidden" name="project_id" value="' . (int) $project_id . '" />';
                $output .= '<input type="hidden" name="session_id" value="' . esc_attr( (string) $session['id'] ) . '" />';
                $output .= '<input type="hidden" name="redirect_to" value="' . esc_url( $calendar_proposals_url ) . '" />';
                $output .= wp_nonce_field( 'wpssb_delete_rehearsal_proposal', 'wpssb_rehearsal_delete_nonce', true, false );
                $output .= '<button type="submit" class="wp-block-button__link wp-element-button is-style-outline">' . esc_html__( 'Eliminar propuesta', 'wp-song-study-blocks' ) . '</button>';
                $output .= '</form>';
            } else {
                $output .= '<div class="pd-rehearsal-session-card__header">';
                $output .= '<div>';
                $output .= '<p class="pd-rehearsal-session-card__eyebrow">' . esc_html( wpssb_get_project_rehearsal_status_label( $status ) ) . '</p>';
                $output .= '<h3>' . esc_html( ! empty( $session['focus'] ) ? (string) $session['focus'] : __( 'Sesión general', 'wp-song-study-blocks' ) ) . '</h3>';
                $output .= '<p class="pd-rehearsal-session-card__meta">' . esc_html( wpssb_format_project_rehearsal_schedule( $session ) ) . '</p>';
                if ( '' !== $creator_name ) {
                    $output .= '<p class="pd-rehearsal-session-card__meta">' . esc_html( sprintf( __( 'Propuesta por: %s', 'wp-song-study-blocks' ), $creator_name ) ) . '</p>';
                }
                if ( ! empty( $session['location'] ) ) {
                    $output .= '<p class="pd-rehearsal-session-card__meta">' . esc_html( sprintf( __( 'Lugar: %s', 'wp-song-study-blocks' ), (string) $session['location'] ) ) . '</p>';
                }
                $output .= '</div></div>';

                if ( ! empty( $session['reviewed_items'] ) && is_array( $session['reviewed_items'] ) ) {
                    $output .= '<div class="pd-rehearsal-session-card__block">';
                    $output .= '<strong>' . esc_html__( 'Temas planeados', 'wp-song-study-blocks' ) . '</strong>';
                    $output .= '<ul>';
                    foreach ( $session['reviewed_items'] as $item ) {
                        $output .= '<li>' . esc_html( sanitize_text_field( (string) $item ) ) . '</li>';
                    }
                    $output .= '</ul></div>';
                }

                if ( ! empty( $session['notes'] ) ) {
                    $output .= '<div class="pd-rehearsal-session-card__block">';
                    $output .= '<strong>' . esc_html__( 'Notas de coordinación', 'wp-song-study-blocks' ) . '</strong>';
                    $output .= '<p>' . esc_html( (string) $session['notes'] ) . '</p>';
                    $output .= '</div>';
                }

                $output .= '<div class="pd-rehearsal-session-card__block">';
                $output .= '<strong>' . esc_html__( 'Edición de la propuesta', 'wp-song-study-blocks' ) . '</strong>';
                $output .= '<p>' . esc_html__( 'Solo la persona que abrió esta propuesta puede modificarla o eliminarla. Tú sí puedes votar y revisar sus detalles.', 'wp-song-study-blocks' ) . '</p>';
                $output .= '</div>';
            }

            $output .= '</div>';
            $output .= '<aside class="pd-rehearsal-vote-panel" aria-label="' . esc_attr__( 'Votación de la propuesta', 'wp-song-study-blocks' ) . '">';
            $output .= '<div class="pd-rehearsal-vote-panel__header">';
            $output .= '<div>';
            $output .= '<p class="pd-rehearsal-session-card__eyebrow">' . esc_html__( 'Votación', 'wp-song-study-blocks' ) . '</p>';
            $output .= '<h4>' . esc_html__( 'Respuestas del grupo', 'wp-song-study-blocks' ) . '</h4>';
            $output .= '</div>';
            $output .= '<span class="pd-rehearsal-vote-panel__count">' . esc_html( sprintf( __( '%1$d/%2$d sí', 'wp-song-study-blocks' ), (int) $totals['yes'], (int) array_sum( $totals ) ) ) . '</span>';
            $output .= '</div>';
            $output .= '<div class="pd-rehearsal-vote-panel__totals" aria-label="' . esc_attr__( 'Resumen de votos', 'wp-song-study-blocks' ) . '">';
            $output .= '<span class="is-yes">' . esc_html( sprintf( __( 'Sí %d', 'wp-song-study-blocks' ), (int) $totals['yes'] ) ) . '</span>';
            $output .= '<span class="is-maybe">' . esc_html( sprintf( __( 'Tal vez %d', 'wp-song-study-blocks' ), (int) $totals['maybe'] ) ) . '</span>';
            $output .= '<span class="is-no">' . esc_html( sprintf( __( 'No %d', 'wp-song-study-blocks' ), (int) $totals['no'] ) ) . '</span>';
            $output .= '<span class="is-pending">' . esc_html( sprintf( __( 'Pendientes %d', 'wp-song-study-blocks' ), (int) $totals['pending'] ) ) . '</span>';
            $output .= '</div>';

            if ( ! empty( $session['votes'] ) && is_array( $session['votes'] ) ) {
                $output .= '<ul class="pd-rehearsal-session-card__vote-list pd-rehearsal-session-card__vote-list--compact">';
                foreach ( $session['votes'] as $vote_entry ) {
                    if ( ! is_array( $vote_entry ) ) {
                        continue;
                    }

                    $vote_user_id = absint( $vote_entry['user_id'] ?? 0 );
                    $vote_name    = sanitize_text_field( (string) ( $vote_entry['nombre'] ?? '' ) );
                    $vote_key     = sanitize_key( (string) ( $vote_entry['vote'] ?? 'pending' ) );
                    $vote_label   = wpssb_get_project_rehearsal_vote_label( $vote_key );
                    $vote_comment = sanitize_text_field( (string) ( $vote_entry['comment'] ?? '' ) );
                    $is_current   = $viewer_id === $vote_user_id;

                    $output .= '<li class="vote-' . esc_attr( $vote_key ) . ( $is_current ? ' is-current-user' : '' ) . '">';
                    $output .= '<span>' . esc_html( '' !== $vote_name ? $vote_name : __( 'Integrante', 'wp-song-study-blocks' ) ) . '</span>';
                    $output .= '<strong>' . esc_html( $vote_label ) . '</strong>';
                    if ( '' !== $vote_comment ) {
                        $output .= '<small>' . esc_html( $vote_comment ) . '</small>';
                    }
                    $output .= '</li>';
                }
                $output .= '</ul>';
            }

            if ( $can_vote ) {
                $output .= '<form class="pd-rehearsal-vote-form" method="post" action="' . esc_url( $action_url ) . '">';
                $output .= '<input type="hidden" name="action" value="wpssb_save_my_rehearsal_vote" />';
                $output .= '<input type="hidden" name="project_id" value="' . (int) $project_id . '" />';
                $output .= '<input type="hidden" name="session_id" value="' . esc_attr( (string) $session['id'] ) . '" />';
                $output .= '<input type="hidden" name="redirect_to" value="' . esc_url( $calendar_proposals_url ) . '" />';
                $output .= wp_nonce_field( 'wpssb_save_my_rehearsal_vote', 'wpssb_rehearsal_vote_nonce', true, false );
                $output .= '<label><span>' . esc_html__( 'Mi voto', 'wp-song-study-blocks' ) . '</span>';
                $output .= '<select name="my_vote">';
                foreach ( [ 'pending', 'yes', 'maybe', 'no' ] as $option ) {
                    $output .= '<option value="' . esc_attr( $option ) . '" ' . selected( sanitize_key( (string) ( $my_vote['vote'] ?? 'pending' ) ), $option, false ) . '>' . esc_html( wpssb_get_project_rehearsal_vote_label( $option ) ) . '</option>';
                }
                $output .= '</select></label>';
                $output .= '<label><span>' . esc_html__( 'Comentario de mi voto', 'wp-song-study-blocks' ) . '</span><input type="text" name="vote_comment" value="' . esc_attr( (string) ( $my_vote['comment'] ?? '' ) ) . '" placeholder="' . esc_attr__( 'Ej. sí llego, pero necesito salir puntual.', 'wp-song-study-blocks' ) . '" /></label>';
                $output .= '<button type="submit" class="wp-block-button__link wp-element-button">' . esc_html__( 'Guardar mi voto', 'wp-song-study-blocks' ) . '</button>';
                $output .= '</form>';
                $output .= '<form class="pd-rehearsal-force-form" method="post" action="' . esc_url( $action_url ) . '">';
                $output .= '<input type="hidden" name="action" value="wpssb_force_frontend_rehearsal_confirmation" />';
                $output .= '<input type="hidden" name="project_id" value="' . (int) $project_id . '" />';
                $output .= '<input type="hidden" name="session_id" value="' . esc_attr( (string) $session['id'] ) . '" />';
                $output .= '<input type="hidden" name="redirect_to" value="' . esc_url( $calendar_confirmed_url ) . '" />';
                $output .= wp_nonce_field( 'wpssb_force_rehearsal_confirmation', 'wpssb_rehearsal_force_nonce', true, false );
                $output .= '<button type="submit" class="wp-block-button__link wp-element-button is-style-outline">' . esc_html__( 'Forzar ensayo confirmado', 'wp-song-study-blocks' ) . '</button>';
                $output .= '</form>';
            } else {
                $output .= '<p class="pd-rehearsal-panel__meta">' . esc_html__( 'Este ensayo ya no admite cambios de voto desde frontend.', 'wp-song-study-blocks' ) . '</p>';
            }

            $output .= '</aside>';
            $output .= '</div>';
            $output .= '</article>';
        }
        $output .= wpssb_render_frontend_rehearsal_card_carousel_close();
    }
    $output .= '</div>';
    $output .= '<div class="pd-rehearsal-session-group" data-rehearsal-calendar-view-panel="confirmed"' . ( 'confirmed' === $calendar_view ? '' : ' hidden' ) . '>';
    $output .= '<div class="pd-rehearsal-session-group__header">';
    $output .= '<h3>' . esc_html__( 'Sesiones confirmadas', 'wp-song-study-blocks' ) . '</h3>';
    $output .= '<p>' . esc_html__( 'Aquí permanecen las sesiones confirmadas que todavía no arrancan y también las canceladas, bloqueadas como registro visible. En cuanto una sesión activa llega a su horario, se habilita automáticamente dentro de la bitácora.', 'wp-song-study-blocks' ) . '</p>';
    $output .= '</div>';

    if ( empty( $confirmed_sessions ) ) {
        $output .= '<p>' . esc_html__( 'Todavía no hay sesiones confirmadas en este proyecto.', 'wp-song-study-blocks' ) . '</p>';
    } else {
        $output .= wpssb_render_frontend_rehearsal_card_carousel_open( __( 'Navegar ensayos agendados', 'wp-song-study-blocks' ) );
        foreach ( $confirmed_sessions as $session ) {
            if ( ! is_array( $session ) ) {
                continue;
            }

            $session_status      = sanitize_key( (string) ( $session['status'] ?? '' ) );
            $is_cancelled        = 'cancelled' === $session_status;
            $totals              = wpssb_get_project_rehearsal_vote_totals( $session );
            $calendar            = is_array( $session['calendar'] ?? null ) ? $session['calendar'] : [];
            $calendar_sync_status = sanitize_key( (string) ( $calendar['sync_status'] ?? '' ) );
            $synced_by_user      = ! empty( $calendar['synced_by_user_id'] ) ? get_user_by( 'id', absint( $calendar['synced_by_user_id'] ) ) : false;
            $synced_by_label     = $synced_by_user instanceof WP_User ? sanitize_text_field( $synced_by_user->display_name ) : '';
            $cancelled_by_user   = ! empty( $session['cancelled_by'] ) ? get_user_by( 'id', absint( $session['cancelled_by'] ) ) : false;
            $cancelled_by_label  = $cancelled_by_user instanceof WP_User ? sanitize_text_field( $cancelled_by_user->display_name ) : '';
            $forced_confirmed    = wpssb_project_rehearsal_is_forced_confirmed( $session );
            $my_vote             = wpssb_get_project_rehearsal_member_vote( $session, $viewer_id );
            $can_vote            = ! $is_cancelled && wpssb_frontend_rehearsal_session_accepts_votes( $session );
            $can_cancel          = wpssb_frontend_user_can_cancel_rehearsal_session( $session, $viewer_id );
            $can_retry_cancelled_calendar = $is_cancelled
                && absint( $session['created_by'] ?? 0 ) === $viewer_id
                && ! empty( $calendar['event_id'] )
                && ! empty( $calendar['sync_error'] );
            $session_timestamp = wpssb_get_project_rehearsal_session_timestamp( $session );

            $output .= '<article class="pd-rehearsal-session-card status-' . esc_attr( $is_cancelled ? 'cancelled' : 'confirmed' ) . '" data-rehearsal-carousel-timestamp="' . esc_attr( false !== $session_timestamp ? (string) $session_timestamp : '' ) . '">';
            $output .= '<div class="pd-rehearsal-proposal-layout">';
            $output .= '<div class="pd-rehearsal-proposal-main">';
            $output .= '<div class="pd-rehearsal-session-card__header">';
            $output .= '<div>';
            $output .= '<p class="pd-rehearsal-session-card__eyebrow">' . esc_html( $is_cancelled ? __( 'Ensayo cancelado', 'wp-song-study-blocks' ) : __( 'Ensayo confirmado', 'wp-song-study-blocks' ) ) . '</p>';
            $output .= '<h3>' . esc_html( ! empty( $session['focus'] ) ? (string) $session['focus'] : __( 'Sesión general', 'wp-song-study-blocks' ) ) . '</h3>';
            $output .= '<p class="pd-rehearsal-session-card__meta">' . esc_html( wpssb_format_project_rehearsal_schedule( $session ) ) . '</p>';
            if ( ! empty( $session['location'] ) ) {
                $output .= '<p class="pd-rehearsal-session-card__meta">' . esc_html( sprintf( __( 'Lugar: %s', 'wp-song-study-blocks' ), (string) $session['location'] ) ) . '</p>';
            }
            $output .= '</div>';
            $output .= '<div class="pd-rehearsal-session-card__summary">';
            if ( $is_cancelled ) {
                $output .= '<span>' . esc_html__( 'Cancelado', 'wp-song-study-blocks' ) . '</span>';
            } elseif ( $forced_confirmed ) {
                $output .= '<span>' . esc_html__( 'Confirmado por excepción', 'wp-song-study-blocks' ) . '</span>';
            } else {
                $output .= '<span>' . esc_html__( 'Confirmado por consenso', 'wp-song-study-blocks' ) . '</span>';
            }
            $output .= '</div></div>';

            if ( ! empty( $session['reviewed_items'] ) && is_array( $session['reviewed_items'] ) ) {
                $output .= '<div class="pd-rehearsal-session-card__block">';
                $output .= '<strong>' . esc_html__( 'Temas planeados', 'wp-song-study-blocks' ) . '</strong>';
                $output .= '<ul>';
                foreach ( $session['reviewed_items'] as $item ) {
                    $output .= '<li>' . esc_html( sanitize_text_field( (string) $item ) ) . '</li>';
                }
                $output .= '</ul></div>';
            }

            if ( ! empty( $session['notes'] ) ) {
                $output .= '<div class="pd-rehearsal-session-card__block">';
                $output .= '<strong>' . esc_html__( 'Notas de coordinación', 'wp-song-study-blocks' ) . '</strong>';
                $output .= '<p>' . esc_html( (string) $session['notes'] ) . '</p>';
                $output .= '</div>';
            }

            if ( $is_cancelled ) {
                $cancellation_reason = sanitize_textarea_field( (string) ( $session['cancellation_reason'] ?? '' ) );
                $cancelled_timestamp = ! empty( $session['cancelled_at'] ) ? strtotime( (string) $session['cancelled_at'] ) : false;
                $cancelled_at        = false !== $cancelled_timestamp ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $cancelled_timestamp ) : '';

                $output .= '<div class="pd-rehearsal-session-card__block pd-rehearsal-cancelled-note">';
                $output .= '<strong>' . esc_html__( 'Razón de la cancelación', 'wp-song-study-blocks' ) . '</strong>';
                $output .= '<p>' . esc_html( '' !== $cancellation_reason ? $cancellation_reason : __( 'No se registró una razón pública.', 'wp-song-study-blocks' ) ) . '</p>';
                if ( '' !== $cancelled_by_label || '' !== $cancelled_at ) {
                    $output .= '<small>' . esc_html(
                        sprintf(
                            __( 'Cancelado por %1$s%2$s.', 'wp-song-study-blocks' ),
                            '' !== $cancelled_by_label ? $cancelled_by_label : __( 'quien propuso el ensayo', 'wp-song-study-blocks' ),
                            '' !== $cancelled_at ? sprintf( __( ' el %s', 'wp-song-study-blocks' ), $cancelled_at ) : ''
                        )
                    ) . '</small>';
                }
                $output .= '</div>';
            } elseif ( $forced_confirmed ) {
                $output .= '<p class="pd-rehearsal-panel__meta">' . esc_html__( 'Esta sesión se confirmó por excepción. En Google Calendar solo se registran quienes respondieron sí o tal vez.', 'wp-song-study-blocks' ) . '</p>';
            }

            $output .= '</div>';
            $output .= '<aside class="pd-rehearsal-vote-panel pd-rehearsal-vote-panel--confirmed" aria-label="' . esc_attr__( 'Confirmación del ensayo agendado', 'wp-song-study-blocks' ) . '">';
            $output .= '<div class="pd-rehearsal-vote-panel__header">';
            $output .= '<div>';
            $output .= '<p class="pd-rehearsal-session-card__eyebrow">' . esc_html__( 'Confirmaciones', 'wp-song-study-blocks' ) . '</p>';
            $output .= '<h4>' . esc_html__( 'Respuestas del grupo', 'wp-song-study-blocks' ) . '</h4>';
            $output .= '</div>';
            $output .= '<span class="pd-rehearsal-vote-panel__count">' . esc_html( sprintf( __( '%1$d/%2$d sí', 'wp-song-study-blocks' ), (int) $totals['yes'], (int) array_sum( $totals ) ) ) . '</span>';
            $output .= '</div>';
            $output .= '<div class="pd-rehearsal-vote-panel__totals" aria-label="' . esc_attr__( 'Resumen de confirmaciones', 'wp-song-study-blocks' ) . '">';
            $output .= '<span class="is-yes">' . esc_html( sprintf( __( 'Sí %d', 'wp-song-study-blocks' ), (int) $totals['yes'] ) ) . '</span>';
            $output .= '<span class="is-maybe">' . esc_html( sprintf( __( 'Tal vez %d', 'wp-song-study-blocks' ), (int) $totals['maybe'] ) ) . '</span>';
            $output .= '<span class="is-no">' . esc_html( sprintf( __( 'No %d', 'wp-song-study-blocks' ), (int) $totals['no'] ) ) . '</span>';
            $output .= '<span class="is-pending">' . esc_html( sprintf( __( 'Pendientes %d', 'wp-song-study-blocks' ), (int) $totals['pending'] ) ) . '</span>';
            $output .= '</div>';

            if ( ! empty( $session['votes'] ) && is_array( $session['votes'] ) ) {
                $output .= '<ul class="pd-rehearsal-session-card__vote-list pd-rehearsal-session-card__vote-list--compact">';
                foreach ( $session['votes'] as $vote_entry ) {
                    if ( ! is_array( $vote_entry ) ) {
                        continue;
                    }

                    $vote_user_id = absint( $vote_entry['user_id'] ?? 0 );
                    $vote_name    = sanitize_text_field( (string) ( $vote_entry['nombre'] ?? '' ) );
                    $vote_key     = sanitize_key( (string) ( $vote_entry['vote'] ?? 'pending' ) );
                    $vote_label   = wpssb_get_project_rehearsal_vote_label( $vote_key );
                    $vote_comment = sanitize_text_field( (string) ( $vote_entry['comment'] ?? '' ) );
                    $is_current   = $viewer_id === $vote_user_id;

                    $output .= '<li class="vote-' . esc_attr( $vote_key ) . ( $is_current ? ' is-current-user' : '' ) . '"><span>' . esc_html( '' !== $vote_name ? $vote_name : __( 'Integrante', 'wp-song-study-blocks' ) ) . '</span><strong>' . esc_html( $vote_label ) . '</strong>';
                    if ( '' !== $vote_comment ) {
                        $output .= '<small>' . esc_html( $vote_comment ) . '</small>';
                    }
                    $output .= '</li>';
                }
                $output .= '</ul>';
            }

            if ( $can_vote ) {
                $output .= '<form class="pd-rehearsal-vote-form pd-rehearsal-vote-form--confirmed" method="post" action="' . esc_url( $action_url ) . '">';
                $output .= '<input type="hidden" name="action" value="wpssb_save_my_rehearsal_vote" />';
                $output .= '<input type="hidden" name="project_id" value="' . (int) $project_id . '" />';
                $output .= '<input type="hidden" name="session_id" value="' . esc_attr( (string) $session['id'] ) . '" />';
                $output .= '<input type="hidden" name="redirect_to" value="' . esc_url( $calendar_confirmed_url ) . '" />';
                $output .= wp_nonce_field( 'wpssb_save_my_rehearsal_vote', 'wpssb_rehearsal_vote_nonce', true, false );
                $output .= '<label><span>' . esc_html__( 'Mi confirmación actual', 'wp-song-study-blocks' ) . '</span>';
                $output .= '<select name="my_vote">';
                foreach ( [ 'pending', 'yes', 'maybe', 'no' ] as $option ) {
                    $output .= '<option value="' . esc_attr( $option ) . '" ' . selected( sanitize_key( (string) ( $my_vote['vote'] ?? 'pending' ) ), $option, false ) . '>' . esc_html( wpssb_get_project_rehearsal_vote_label( $option ) ) . '</option>';
                }
                $output .= '</select></label>';
                $output .= '<label><span>' . esc_html__( 'Comentario sobre mi cambio', 'wp-song-study-blocks' ) . '</span><input type="text" name="vote_comment" value="' . esc_attr( (string) ( $my_vote['comment'] ?? '' ) ) . '" placeholder="' . esc_attr__( 'Ej. ya no alcanzo a llegar, o solo puedo estar media sesión.', 'wp-song-study-blocks' ) . '" /></label>';
                $output .= '<button type="submit" class="wp-block-button__link wp-element-button is-style-outline">' . esc_html__( 'Actualizar mi confirmación', 'wp-song-study-blocks' ) . '</button>';
                $output .= '</form>';
            }

            if ( $is_cancelled ) {
                if ( 'cancelled' === $calendar_sync_status && empty( $calendar['event_id'] ) ) {
                    $output .= '<p class="pd-rehearsal-panel__meta">' . esc_html__( 'El evento de Google Calendar fue eliminado o no existía evento previo.', 'wp-song-study-blocks' ) . '</p>';
                } elseif ( ! empty( $calendar['sync_error'] ) ) {
                    $output .= '<p class="pd-rehearsal-panel__meta">' . esc_html( sprintf( __( 'Google Calendar no pudo eliminar el evento: %s', 'wp-song-study-blocks' ), (string) $calendar['sync_error'] ) ) . '</p>';
                    if ( $can_retry_cancelled_calendar ) {
                        $output .= '<form class="pd-rehearsal-force-form pd-rehearsal-calendar-sync-form" method="post" action="' . esc_url( $action_url ) . '">';
                        $output .= '<input type="hidden" name="action" value="wpssb_sync_frontend_rehearsal_calendar" />';
                        $output .= '<input type="hidden" name="project_id" value="' . (int) $project_id . '" />';
                        $output .= '<input type="hidden" name="session_id" value="' . esc_attr( (string) $session['id'] ) . '" />';
                        $output .= '<input type="hidden" name="redirect_to" value="' . esc_url( $calendar_confirmed_url ) . '" />';
                        $output .= wp_nonce_field( 'wpssb_sync_frontend_rehearsal_calendar', 'wpssb_rehearsal_calendar_nonce', true, false );
                        $output .= '<button type="submit" class="wp-block-button__link wp-element-button is-style-outline">' . esc_html__( 'Reintentar eliminación en Calendar', 'wp-song-study-blocks' ) . '</button>';
                        $output .= '</form>';
                    }
                } else {
                    $output .= '<p class="pd-rehearsal-panel__meta">' . esc_html__( 'Este ensayo está cancelado y ya no admite cambios desde frontend.', 'wp-song-study-blocks' ) . '</p>';
                }
            } elseif ( ! empty( $calendar['html_link'] ) ) {
                $output .= '<p class="pd-rehearsal-session-card__calendar"><a href="' . esc_url( (string) $calendar['html_link'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Abrir evento de Google Calendar', 'wp-song-study-blocks' ) . '</a></p>';
                if ( '' !== $synced_by_label ) {
                    $output .= '<p class="pd-rehearsal-panel__meta">' . esc_html( sprintf( __( 'Evento sincronizado desde la cuenta de %s.', 'wp-song-study-blocks' ), $synced_by_label ) ) . '</p>';
                }
            } else {
                if ( ! empty( $calendar['sync_error'] ) ) {
                    $output .= '<p class="pd-rehearsal-panel__meta">' . esc_html( sprintf( __( 'Google Calendar no pudo sincronizar este ensayo: %s', 'wp-song-study-blocks' ), (string) $calendar['sync_error'] ) ) . '</p>';
                } else {
                    $output .= '<p class="pd-rehearsal-panel__meta">' . esc_html__( 'Este ensayo confirmado todavía no tiene evento sincronizado en Google Calendar.', 'wp-song-study-blocks' ) . '</p>';
                }

                $output .= '<form class="pd-rehearsal-force-form pd-rehearsal-calendar-sync-form" method="post" action="' . esc_url( $action_url ) . '">';
                $output .= '<input type="hidden" name="action" value="wpssb_sync_frontend_rehearsal_calendar" />';
                $output .= '<input type="hidden" name="project_id" value="' . (int) $project_id . '" />';
                $output .= '<input type="hidden" name="session_id" value="' . esc_attr( (string) $session['id'] ) . '" />';
                $output .= '<input type="hidden" name="redirect_to" value="' . esc_url( $calendar_confirmed_url ) . '" />';
                $output .= wp_nonce_field( 'wpssb_sync_frontend_rehearsal_calendar', 'wpssb_rehearsal_calendar_nonce', true, false );
                $output .= '<button type="submit" class="wp-block-button__link wp-element-button is-style-outline">' . esc_html__( 'Reintentar sincronización', 'wp-song-study-blocks' ) . '</button>';
                $output .= '</form>';

                if ( ! empty( $calendar['google_calendar_url'] ) ) {
                    $output .= '<p class="pd-rehearsal-session-card__calendar"><a href="' . esc_url( (string) $calendar['google_calendar_url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Añadir manualmente a Google Calendar', 'wp-song-study-blocks' ) . '</a></p>';
                }
            }

            if ( $can_cancel ) {
                $output .= '<form class="pd-rehearsal-force-form pd-rehearsal-cancel-form" method="post" action="' . esc_url( $action_url ) . '">';
                $output .= '<input type="hidden" name="action" value="wpssb_cancel_frontend_rehearsal_session" />';
                $output .= '<input type="hidden" name="project_id" value="' . (int) $project_id . '" />';
                $output .= '<input type="hidden" name="session_id" value="' . esc_attr( (string) $session['id'] ) . '" />';
                $output .= '<input type="hidden" name="redirect_to" value="' . esc_url( $calendar_confirmed_url ) . '" />';
                $output .= wp_nonce_field( 'wpssb_cancel_rehearsal_session', 'wpssb_rehearsal_cancel_nonce', true, false );
                $output .= '<label><span>' . esc_html__( 'Razón de la cancelación', 'wp-song-study-blocks' ) . '</span><textarea name="cancellation_reason" rows="3" required placeholder="' . esc_attr__( 'Ej. cambio de agenda, falta de quorum, emergencia, etc.', 'wp-song-study-blocks' ) . '"></textarea></label>';
                $output .= '<p class="pd-rehearsal-panel__meta">' . esc_html__( 'Al cancelar se bloqueará la sesión, se intentará eliminar el evento de Google Calendar y se avisará por correo al grupo.', 'wp-song-study-blocks' ) . '</p>';
                $output .= '<button type="submit" class="wp-block-button__link wp-element-button is-style-outline">' . esc_html__( 'Cancelar ensayo', 'wp-song-study-blocks' ) . '</button>';
                $output .= '</form>';
            }

            $output .= '</aside>';
            $output .= '</div>';
            $output .= '</article>';
        }
        $output .= wpssb_render_frontend_rehearsal_card_carousel_close();
    }
    $output .= '</div>';
    $output .= '<div class="pd-rehearsal-session-group" data-rehearsal-calendar-view-panel="logbook"' . ( 'logbook' === $calendar_view ? '' : ' hidden' ) . '>';
    $output .= '<div class="pd-rehearsal-session-group__header">';
    $output .= '<h3>' . esc_html__( 'Sesiones en bitácora', 'wp-song-study-blocks' ) . '</h3>';
    $output .= '<p>' . esc_html__( 'Resumen de los ensayos que ya llegaron a fase de bitácora. Para editar observaciones y asistencia, abre el panel completo de Bitácora.', 'wp-song-study-blocks' ) . '</p>';
    $output .= '</div>';

    if ( empty( $logbook_sessions ) ) {
        $output .= '<p>' . esc_html__( 'Todavía no hay sesiones habilitadas dentro de la bitácora.', 'wp-song-study-blocks' ) . '</p>';
    } else {
        $output .= wpssb_render_frontend_rehearsal_card_carousel_open( __( 'Navegar resumen de bitácora', 'wp-song-study-blocks' ), 'pd-rehearsal-logbook-summary-list' );
        foreach ( $logbook_sessions as $session ) {
            if ( ! is_array( $session ) ) {
                continue;
            }

            $session_status = sanitize_key( (string) ( $session['status'] ?? '' ) );
            $badge          = wpssb_get_frontend_rehearsal_calendar_event_badge( $session, $now_timestamp );
            $reviewed_items = is_array( $session['reviewed_items'] ?? null ) ? $session['reviewed_items'] : [];
            $attendance     = is_array( $session['attendance'] ?? null ) ? $session['attendance'] : [];
            $is_expired_proposal = wpssb_project_rehearsal_session_is_expired_proposal( $session, $now_timestamp );
            $session_timestamp = wpssb_get_project_rehearsal_session_timestamp( $session );
            $attended_count = 0;

            foreach ( $attendance as $attendance_entry ) {
                if ( is_array( $attendance_entry ) && in_array( sanitize_key( (string) ( $attendance_entry['status'] ?? '' ) ), [ 'attended', 'late' ], true ) ) {
                    $attended_count++;
                }
            }

            $output .= '<article class="pd-rehearsal-logbook-summary status-' . esc_attr( $session_status ) . '" data-rehearsal-carousel-timestamp="' . esc_attr( false !== $session_timestamp ? (string) $session_timestamp : '' ) . '">';
            $output .= '<span class="pd-rehearsal-logbook-summary__icon is-' . esc_attr( (string) ( $badge['kind'] ?? 'logbook' ) ) . '" aria-hidden="true">' . wp_kses_post( (string) ( $badge['icon'] ?? '&#10003;' ) ) . '</span>';
            $output .= '<div>';
            $output .= '<p class="pd-rehearsal-session-card__eyebrow">' . esc_html( (string) ( $badge['label'] ?? __( 'Ensayo con bitácora', 'wp-song-study-blocks' ) ) ) . '</p>';
            $output .= '<h4>' . esc_html( ! empty( $session['focus'] ) ? (string) $session['focus'] : __( 'Sesión general', 'wp-song-study-blocks' ) ) . '</h4>';
            $output .= '<p class="pd-rehearsal-session-card__meta">' . esc_html( wpssb_format_project_rehearsal_schedule( $session ) ) . '</p>';
            if ( $is_expired_proposal ) {
                $totals = wpssb_get_project_rehearsal_vote_totals( $session );
                $output .= '<p class="pd-rehearsal-panel__meta">' . esc_html(
                    sprintf(
                        __( 'Votos: sí %1$d · tal vez %2$d · no %3$d · pendientes %4$d', 'wp-song-study-blocks' ),
                        (int) $totals['yes'],
                        (int) $totals['maybe'],
                        (int) $totals['no'],
                        (int) $totals['pending']
                    )
                ) . '</p>';
            } else {
                $output .= '<p class="pd-rehearsal-panel__meta">' . esc_html(
                    sprintf(
                        __( '%1$d temas registrados · %2$d asistencias marcadas', 'wp-song-study-blocks' ),
                        count( $reviewed_items ),
                        $attended_count
                    )
                ) . '</p>';
            }
            $output .= '</div>';
            $output .= '<a class="wp-block-button__link wp-element-button is-style-outline" href="' . esc_url( $logbook_url ) . '">' . esc_html__( 'Abrir bitácora', 'wp-song-study-blocks' ) . '</a>';
            $output .= '</article>';
        }
        $output .= wpssb_render_frontend_rehearsal_card_carousel_close();
    }
    $output .= '</div>';
    $output .= '</div>';
    $output .= '</div></section>';

    $output .= '<section class="pd-rehearsal-section" id="pd-rehearsal-panel-logbook" data-rehearsal-panel="logbook" role="tabpanel" aria-labelledby="pd-rehearsal-tab-logbook"' . ( 'logbook' === $current_tab ? '' : ' hidden' ) . '>';
    $output .= '<div class="pd-rehearsal-panel">';
    $output .= '<header class="pd-membership-section__header">';
    $output .= '<div class="pd-membership-section__intro">';
    $output .= '<p class="pd-membership-shell__eyebrow">' . esc_html__( 'Historial', 'wp-song-study-blocks' ) . '</p>';
    $output .= '<h2>' . esc_html__( 'Bitácora del proyecto', 'wp-song-study-blocks' ) . '</h2>';
    $output .= '<p>' . esc_html__( 'Aquí quedan visibles los ensayos ya realizados y también se abre automáticamente cada sesión confirmada cuando llega su horario, para registrar observaciones y asistencia mientras ocurre o después.', 'wp-song-study-blocks' ) . '</p>';
    $output .= '</div></header>';
    if ( empty( $logbook_sessions ) ) {
        $output .= '<p>' . esc_html__( 'Todavía no hay ensayos habilitados dentro de la bitácora.', 'wp-song-study-blocks' ) . '</p>';
    } else {
        $output .= wpssb_render_frontend_rehearsal_card_carousel_open( __( 'Navegar bitácora del proyecto', 'wp-song-study-blocks' ) );
        foreach ( $logbook_sessions as $session ) {
            if ( ! is_array( $session ) ) {
                continue;
            }

            $session_status   = sanitize_key( (string) ( $session['status'] ?? '' ) );
            $is_expired_proposal = wpssb_project_rehearsal_session_is_expired_proposal( $session, $now_timestamp );
            $is_live_logbook  = 'completed' !== $session_status;
            $is_in_progress   = $is_live_logbook && wpssb_frontend_rehearsal_session_is_in_progress( $session, $now_timestamp );
            $my_attendance    = wpssb_get_project_rehearsal_member_attendance( $session, $viewer_id );
            $reviewed_items   = is_array( $session['reviewed_items'] ?? null ) ? $session['reviewed_items'] : [];
            $session_notes    = sanitize_textarea_field( (string) ( $session['notes'] ?? '' ) );
            $attendance_items = is_array( $session['attendance'] ?? null ) ? $session['attendance'] : [];
            $session_timestamp = wpssb_get_project_rehearsal_session_timestamp( $session );

            $output .= '<article class="pd-rehearsal-session-card status-' . esc_attr( $session_status ) . ( $is_live_logbook && ! $is_expired_proposal ? ' is-logbook-live' : '' ) . '" data-rehearsal-carousel-timestamp="' . esc_attr( false !== $session_timestamp ? (string) $session_timestamp : '' ) . '">';
            $output .= '<div class="pd-rehearsal-session-card__header">';
            $output .= '<div>';
            if ( $is_expired_proposal ) {
                $output .= '<p class="pd-rehearsal-session-card__eyebrow">' . esc_html__( 'Propuesta expirada', 'wp-song-study-blocks' ) . '</p>';
            } elseif ( $is_live_logbook ) {
                $output .= '<p class="pd-rehearsal-session-card__eyebrow">' . esc_html( $is_in_progress ? __( 'Ensayo en curso', 'wp-song-study-blocks' ) : __( 'Bitácora abierta', 'wp-song-study-blocks' ) ) . '</p>';
            } else {
                $output .= '<p class="pd-rehearsal-session-card__eyebrow">' . esc_html__( 'Ensayo realizado', 'wp-song-study-blocks' ) . '</p>';
            }
            $output .= '<h3>' . esc_html( ! empty( $session['focus'] ) ? (string) $session['focus'] : __( 'Sesión general', 'wp-song-study-blocks' ) ) . '</h3>';
            $output .= '<p class="pd-rehearsal-session-card__meta">' . esc_html( wpssb_format_project_rehearsal_schedule( $session ) ) . '</p>';
            if ( ! empty( $session['location'] ) ) {
                $output .= '<p class="pd-rehearsal-session-card__meta">' . esc_html( sprintf( __( 'Lugar: %s', 'wp-song-study-blocks' ), (string) $session['location'] ) ) . '</p>';
            }
            $output .= '</div>';
            if ( $is_expired_proposal ) {
                $output .= '<span class="pd-rehearsal-pill">' . esc_html__( 'Solo referencia', 'wp-song-study-blocks' ) . '</span>';
            } else {
                $output .= '<span class="pd-rehearsal-pill">' . esc_html( sprintf( __( 'Tu asistencia: %s', 'wp-song-study-blocks' ), wpssb_get_project_rehearsal_attendance_label( sanitize_key( (string) ( $my_attendance['status'] ?? 'pending' ) ) ) ) ) . '</span>';
            }
            $output .= '</div>';

            if ( $is_expired_proposal ) {
                $totals = wpssb_get_project_rehearsal_vote_totals( $session );
                $output .= '<div class="pd-rehearsal-session-card__block">';
                $output .= '<strong>' . esc_html__( 'Resultado al expirar', 'wp-song-study-blocks' ) . '</strong>';
                $output .= '<p>' . esc_html__( 'La propuesta pasó su hora de finalización sin convertirse en ensayo confirmado. Ya no se puede votar ni modificar; se conserva únicamente como control referencial.', 'wp-song-study-blocks' ) . '</p>';
                $output .= '<p>' . esc_html(
                    sprintf(
                        __( 'Sí %1$d · Tal vez %2$d · No %3$d · Pendientes %4$d', 'wp-song-study-blocks' ),
                        (int) $totals['yes'],
                        (int) $totals['maybe'],
                        (int) $totals['no'],
                        (int) $totals['pending']
                    )
                ) . '</p>';
                $output .= '</div>';

                if ( ! empty( $session['votes'] ) && is_array( $session['votes'] ) ) {
                    $output .= '<div class="pd-rehearsal-session-card__block">';
                    $output .= '<strong>' . esc_html__( 'Respuestas registradas', 'wp-song-study-blocks' ) . '</strong>';
                    $output .= '<ul class="pd-rehearsal-session-card__vote-list">';
                    foreach ( $session['votes'] as $vote_entry ) {
                        if ( ! is_array( $vote_entry ) ) {
                            continue;
                        }

                        $vote_user_id = absint( $vote_entry['user_id'] ?? 0 );
                        $vote_name    = sanitize_text_field( (string) ( $vote_entry['nombre'] ?? '' ) );
                        $vote_key     = sanitize_key( (string) ( $vote_entry['vote'] ?? 'pending' ) );
                        $vote_comment = sanitize_text_field( (string) ( $vote_entry['comment'] ?? '' ) );
                        $is_current   = $viewer_id === $vote_user_id;

                        $output .= '<li class="vote-' . esc_attr( $vote_key ) . ( $is_current ? ' is-current-user' : '' ) . '">';
                        $output .= '<span>' . esc_html( '' !== $vote_name ? $vote_name : __( 'Integrante', 'wp-song-study-blocks' ) ) . '</span>';
                        $output .= '<strong>' . esc_html( wpssb_get_project_rehearsal_vote_label( $vote_key ) ) . '</strong>';
                        if ( '' !== $vote_comment ) {
                            $output .= '<small>' . esc_html( $vote_comment ) . '</small>';
                        }
                        $output .= '</li>';
                    }
                    $output .= '</ul>';
                    $output .= '</div>';
                }

                $output .= '</article>';
                continue;
            }

            if ( $is_live_logbook ) {
                $output .= '<p class="pd-rehearsal-panel__meta">' . esc_html__( 'Esta sesión se abrió automáticamente en la bitácora al llegar su horario. Lo que registres aquí se guarda solo y queda disponible para consulta posterior.', 'wp-song-study-blocks' ) . '</p>';
            }

            $output .= '<form class="pd-rehearsal-proposal-form pd-rehearsal-logbook-form" method="post" action="' . esc_url( $action_url ) . '" data-rehearsal-logbook-autosave>';
            $output .= '<input type="hidden" name="action" value="wpssb_save_frontend_rehearsal_logbook" />';
            $output .= '<input type="hidden" name="project_id" value="' . (int) $project_id . '" />';
            $output .= '<input type="hidden" name="session_id" value="' . esc_attr( (string) ( $session['id'] ?? '' ) ) . '" />';
            $output .= '<input type="hidden" name="redirect_to" value="' . esc_url( $logbook_url ) . '" />';
            $output .= wp_nonce_field( 'wpssb_save_rehearsal_logbook', 'wpssb_rehearsal_logbook_nonce', true, false );
            $output .= '<div class="pd-rehearsal-proposal-form__fields">';
            $output .= '<label><span>' . esc_html__( 'Temas trabajados', 'wp-song-study-blocks' ) . '</span><textarea name="logbook_reviewed_items" rows="4" placeholder="' . esc_attr__( 'Una línea por tema, arreglo o bloque revisado.', 'wp-song-study-blocks' ) . '">' . esc_textarea( implode( "\n", array_map( 'sanitize_text_field', $reviewed_items ) ) ) . '</textarea></label>';
            $output .= '<label><span>' . esc_html__( 'Observaciones del ensayo', 'wp-song-study-blocks' ) . '</span><textarea name="logbook_notes" rows="5" placeholder="' . esc_attr__( 'Ej. qué funcionó, qué falta ajustar, acuerdos pendientes, incidencias, etc.', 'wp-song-study-blocks' ) . '">' . esc_textarea( $session_notes ) . '</textarea></label>';
            $output .= '</div>';
            $output .= '<div class="pd-rehearsal-session-card__block">';
            $output .= '<strong>' . esc_html__( 'Asistencia del grupo', 'wp-song-study-blocks' ) . '</strong>';
            $output .= '<div class="pd-rehearsal-logbook-attendance-editor">';

            foreach ( $attendance_items as $attendance ) {
                if ( ! is_array( $attendance ) ) {
                    continue;
                }

                $attendance_user_id = absint( $attendance['user_id'] ?? 0 );
                $attendance_name    = sanitize_text_field( (string) ( $attendance['nombre'] ?? '' ) );
                $attendance_status  = sanitize_key( (string) ( $attendance['status'] ?? 'pending' ) );
                $attendance_comment = sanitize_text_field( (string) ( $attendance['comment'] ?? '' ) );
                $is_current         = $viewer_id === $attendance_user_id;

                $output .= '<div class="pd-rehearsal-logbook-attendance-row' . ( $is_current ? ' is-current-user' : '' ) . '">';
                $output .= '<p class="pd-rehearsal-logbook-attendance-row__name">' . esc_html( '' !== $attendance_name ? $attendance_name : __( 'Integrante', 'wp-song-study-blocks' ) ) . '</p>';
                $output .= '<label><span>' . esc_html__( 'Asistencia', 'wp-song-study-blocks' ) . '</span><select name="attendance[' . esc_attr( (string) $attendance_user_id ) . '][status]">';

                foreach ( [ 'pending', 'confirmed', 'attended', 'late', 'absent', 'excused' ] as $attendance_option ) {
                    $output .= '<option value="' . esc_attr( $attendance_option ) . '" ' . selected( $attendance_status, $attendance_option, false ) . '>' . esc_html( wpssb_get_project_rehearsal_attendance_label( $attendance_option ) ) . '</option>';
                }

                $output .= '</select></label>';
                $output .= '<label><span>' . esc_html__( 'Comentario de asistencia', 'wp-song-study-blocks' ) . '</span><input type="text" name="attendance[' . esc_attr( (string) $attendance_user_id ) . '][comment]" value="' . esc_attr( $attendance_comment ) . '" placeholder="' . esc_attr__( 'Ej. llegó 10 min tarde, salió antes, justificó ausencia.', 'wp-song-study-blocks' ) . '" /></label>';
                $output .= '</div>';
            }

            $output .= '</div>';
            $output .= '</div>';
            $output .= '<div class="pd-rehearsal-proposal-form__actions">';
            $output .= '<p class="pd-rehearsal-proposal-form__status" data-rehearsal-logbook-status role="status" aria-live="polite">' . esc_html__( 'Las observaciones y la asistencia se guardan automáticamente mientras escribes.', 'wp-song-study-blocks' ) . '</p>';
            $output .= '<button type="submit" class="wp-block-button__link wp-element-button is-style-outline">' . esc_html__( 'Guardar bitácora ahora', 'wp-song-study-blocks' ) . '</button>';
            $output .= '</div>';
            $output .= '</form>';

            $output .= '</article>';
        }
        $output .= wpssb_render_frontend_rehearsal_card_carousel_close();
    }
    $output .= '</div></section>';

    $output .= '</div>';
    $output .= '</section>';

    return $output;
}

/**
 * Render callback del bloque frontend de ensayos del usuario actual.
 *
 * @param array<string, mixed> $attributes Atributos del bloque.
 * @return string
 */
function wpssb_render_block_current_rehearsals( $attributes = [] ) {
    $settings = [
        'show_admin_link' => ! isset( $attributes['showAdminLink'] ) || (bool) $attributes['showAdminLink'],
        'login_message'   => isset( $attributes['loginMessage'] ) ? sanitize_text_field( $attributes['loginMessage'] ) : __( 'El Planificador de ensayos es un espacio privado para colegas musicales, colaboradores y administradores que ya pertenecen a un proyecto musical. Inicia sesion con una cuenta autorizada para registrar disponibilidad, responder propuestas y revisar la bitacora del proyecto.', 'wp-song-study-blocks' ),
    ];

    $classes          = [ 'pd-rehearsal-block' ];
    $layout_width     = isset( $attributes['layoutWidth'] ) ? sanitize_key( $attributes['layoutWidth'] ) : 'immersive';
    $valid_layouts    = [ 'default', 'wide', 'immersive' ];

    if ( ! in_array( $layout_width, $valid_layouts, true ) ) {
        $layout_width = 'immersive';
    }

    $classes[] = 'is-layout-' . $layout_width;

    $panel_background = sanitize_hex_color( isset( $attributes['panelBackgroundColor'] ) ? (string) $attributes['panelBackgroundColor'] : '' );
    $panel_border     = sanitize_hex_color( isset( $attributes['panelBorderColor'] ) ? (string) $attributes['panelBorderColor'] : '' );
    $panel_accent     = sanitize_hex_color( isset( $attributes['panelGradientAccentColor'] ) ? (string) $attributes['panelGradientAccentColor'] : '' );
    $panel_highlight  = sanitize_hex_color( isset( $attributes['panelGradientHighlightColor'] ) ? (string) $attributes['panelGradientHighlightColor'] : '' );
    $use_gradient     = ! isset( $attributes['usePanelGradient'] ) || (bool) $attributes['usePanelGradient'];
    $accent_opacity   = isset( $attributes['panelGradientAccentOpacity'] ) ? intval( $attributes['panelGradientAccentOpacity'] ) : 10;
    $highlight_opacity = isset( $attributes['panelGradientHighlightOpacity'] ) ? intval( $attributes['panelGradientHighlightOpacity'] ) : 12;
    $header_background = sanitize_hex_color( isset( $attributes['headerBackgroundColor'] ) ? (string) $attributes['headerBackgroundColor'] : '' );
    $header_border     = sanitize_hex_color( isset( $attributes['headerBorderColor'] ) ? (string) $attributes['headerBorderColor'] : '' );
    $header_text       = sanitize_hex_color( isset( $attributes['headerTextColor'] ) ? (string) $attributes['headerTextColor'] : '' );
    $header_title      = sanitize_hex_color( isset( $attributes['headerTitleColor'] ) ? (string) $attributes['headerTitleColor'] : '' );
    $header_muted      = sanitize_hex_color( isset( $attributes['headerMutedColor'] ) ? (string) $attributes['headerMutedColor'] : '' );
    $panel_text       = sanitize_hex_color( isset( $attributes['panelTextColor'] ) ? (string) $attributes['panelTextColor'] : '' );
    $panel_title      = sanitize_hex_color( isset( $attributes['panelTitleColor'] ) ? (string) $attributes['panelTitleColor'] : '' );
    $panel_muted      = sanitize_hex_color( isset( $attributes['panelMutedColor'] ) ? (string) $attributes['panelMutedColor'] : '' );
    $form_panel_min   = isset( $attributes['formPanelMinWidth'] ) ? intval( $attributes['formPanelMinWidth'] ) : 720;
    $style_tokens     = [];

    $accent_opacity    = max( 0, min( 100, $accent_opacity ) );
    $highlight_opacity = max( 0, min( 100, $highlight_opacity ) );
    $form_panel_min    = max( 480, min( 1400, $form_panel_min ) );

    $classes[] = $use_gradient ? 'has-panel-gradient' : 'has-flat-panels';

    if ( empty( $panel_background ) && ! empty( $attributes['style']['color']['background'] ) ) {
        $panel_background = sanitize_hex_color( (string) $attributes['style']['color']['background'] );
    }

    if ( empty( $panel_text ) && ! empty( $attributes['style']['color']['text'] ) ) {
        $panel_text = sanitize_hex_color( (string) $attributes['style']['color']['text'] );
    }

    if ( empty( $panel_border ) && ! empty( $attributes['style']['border']['color'] ) ) {
        $panel_border = sanitize_hex_color( (string) $attributes['style']['border']['color'] );
    }

    if ( ! empty( $panel_background ) ) {
        $style_tokens[] = '--pd-rehearsal-custom-card-background-solid:' . $panel_background;
    }

    if ( ! empty( $panel_border ) ) {
        $style_tokens[] = '--pd-rehearsal-custom-card-border:' . $panel_border;
    }

    if ( ! empty( $header_background ) ) {
        $style_tokens[] = '--pd-rehearsal-custom-header-background-solid:' . $header_background;
    }

    if ( ! empty( $header_border ) ) {
        $style_tokens[] = '--pd-rehearsal-custom-header-border:' . $header_border;
    }

    if ( ! empty( $panel_accent ) ) {
        $style_tokens[] = '--pd-rehearsal-custom-accent:' . $panel_accent;
    }

    if ( ! empty( $panel_highlight ) ) {
        $style_tokens[] = '--pd-rehearsal-custom-highlight:' . $panel_highlight;
    }

    $style_tokens[] = '--pd-rehearsal-custom-accent-opacity:' . $accent_opacity . '%';
    $style_tokens[] = '--pd-rehearsal-custom-highlight-opacity:' . $highlight_opacity . '%';

    if ( ! empty( $panel_text ) ) {
        $style_tokens[] = '--pd-rehearsal-custom-panel-text:' . $panel_text;
    }

    if ( ! empty( $header_text ) ) {
        $style_tokens[] = '--pd-rehearsal-custom-header-text:' . $header_text;
    }

    if ( ! empty( $panel_title ) ) {
        $style_tokens[] = '--pd-rehearsal-custom-panel-title:' . $panel_title;
    }

    if ( ! empty( $header_title ) ) {
        $style_tokens[] = '--pd-rehearsal-custom-header-title:' . $header_title;
    }

    if ( ! empty( $panel_muted ) ) {
        $style_tokens[] = '--pd-rehearsal-custom-panel-muted:' . $panel_muted;
    }

    if ( ! empty( $header_muted ) ) {
        $style_tokens[] = '--pd-rehearsal-custom-header-muted:' . $header_muted;
    }

    $style_tokens[] = '--pd-rehearsal-form-panel-min:' . $form_panel_min . 'px';

    $wrapper_arguments = [
        'class' => implode( ' ', $classes ),
    ];

    if ( ! empty( $style_tokens ) ) {
        $wrapper_arguments['style'] = implode( ';', $style_tokens );
    }

    $wrapper_attributes = get_block_wrapper_attributes( $wrapper_arguments );

    return sprintf(
        '<div %1$s>%2$s</div>',
        $wrapper_attributes,
        wpssb_render_current_rehearsals_markup( $settings )
    );
}
