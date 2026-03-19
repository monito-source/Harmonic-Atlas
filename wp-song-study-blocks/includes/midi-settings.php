<?php
/**
 * Opciones para el rango visible del editor MIDI.
 *
 * @package WP_Song_Study
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Devuelve los presets por defecto de rango MIDI.
 *
 * @return array
 */
function wpss_get_default_midi_range_presets() {
    return [
        [
            'id'    => 'graves',
            'label' => __( 'Graves (F)', 'wp-song-study' ),
            'min'   => 36, // C2
            'max'   => 65, // F4
        ],
        [
            'id'    => 'medios',
            'label' => __( 'Medios (C)', 'wp-song-study' ),
            'min'   => 48, // C3
            'max'   => 77, // F5
        ],
        [
            'id'    => 'agudos',
            'label' => __( 'Agudos (G)', 'wp-song-study' ),
            'min'   => 60, // C4
            'max'   => 89, // F6
        ],
    ];
}

/**
 * Sanitiza y normaliza los presets recibidos.
 *
 * @param mixed $value Valor entrante.
 * @return array
 */
function wpss_sanitize_midi_range_presets( $value ) {
    $defaults = wpss_get_default_midi_range_presets();
    $allowed_ids = array_map(
        static function( $preset ) {
            return $preset['id'];
        },
        $defaults
    );

    if ( ! is_array( $value ) ) {
        return $defaults;
    }

    $normalized = [];

    foreach ( $defaults as $fallback ) {
        $id = $fallback['id'];
        $incoming = isset( $value[ $id ] ) && is_array( $value[ $id ] ) ? $value[ $id ] : [];

        $label = isset( $incoming['label'] ) ? sanitize_text_field( $incoming['label'] ) : $fallback['label'];
        if ( '' === $label ) {
            $label = $fallback['label'];
        }

        $min = isset( $incoming['min'] ) ? (int) $incoming['min'] : $fallback['min'];
        $max = isset( $incoming['max'] ) ? (int) $incoming['max'] : $fallback['max'];

        $min = max( 0, min( 127, $min ) );
        $max = max( 0, min( 127, $max ) );

        if ( $min > $max ) {
            $tmp = $min;
            $min = $max;
            $max = $tmp;
        }

        $normalized[ $id ] = [
            'id'    => $id,
            'label' => $label,
            'min'   => $min,
            'max'   => $max,
        ];
    }

    return $normalized;
}

/**
 * Devuelve los presets actuales normalizados.
 *
 * @return array
 */
function wpss_get_midi_range_presets() {
    $stored = get_option( 'wpss_midi_range_presets', [] );
    $normalized = wpss_sanitize_midi_range_presets( $stored );
    return array_values( $normalized );
}

/**
 * Sanitiza el preset por defecto.
 *
 * @param mixed $value Valor entrante.
 * @return string
 */
function wpss_sanitize_midi_range_default( $value ) {
    $value = sanitize_key( $value );
    $allowed = array_map(
        static function( $preset ) {
            return $preset['id'];
        },
        wpss_get_default_midi_range_presets()
    );

    if ( ! in_array( $value, $allowed, true ) ) {
        return 'medios';
    }

    return $value;
}

/**
 * Devuelve el preset por defecto.
 *
 * @return string
 */
function wpss_get_midi_range_default() {
    $stored = get_option( 'wpss_midi_range_default', '' );
    return wpss_sanitize_midi_range_default( $stored );
}
