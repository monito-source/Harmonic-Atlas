<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../bootstrap.php';

class WpssSanitizeSeccionesArrayTest extends TestCase {
    public function test_preserves_ids_and_names_from_legacy_objects() {
        $legacy_sections = [
            (object) [ 'id' => 'Sec-INTRO_1', 'nombre' => 'Intro' ],
            (object) [ 'id' => 'sec-002', 'nombre' => 'Coro' ],
        ];

        $sanitized = wpss_sanitize_secciones_array( $legacy_sections );

        $this->assertCount( 2, $sanitized );
        $this->assertSame(
            [ 'id' => 'sec-intro_1', 'nombre' => 'Intro' ],
            $sanitized[0]
        );
        $this->assertSame(
            [ 'id' => 'sec-002', 'nombre' => 'Coro' ],
            $sanitized[1]
        );
    }

    public function test_default_structure_consumes_normalized_sections() {
        $legacy_sections = [
            (object) [ 'id' => 'Sec-INTRO_1', 'nombre' => 'Intro' ],
            (object) [ 'id' => 'sec-002', 'nombre' => 'Coro' ],
        ];

        $sanitized  = wpss_sanitize_secciones_array( $legacy_sections );
        $estructura = wpss_get_default_estructura( $sanitized );

        $this->assertSame(
            [
                [ 'ref' => 'sec-intro_1' ],
                [ 'ref' => 'sec-002' ],
            ],
            $estructura
        );

        foreach ( $estructura as $entry ) {
            $this->assertIsArray( $entry );
            $this->assertArrayHasKey( 'ref', $entry );
        }
    }
}
