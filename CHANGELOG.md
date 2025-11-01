# Changelog

# [Unreleased]
### Added
- Secciones nombrables persistidas en `_secciones_json` y `section_id` por verso, incluidas en los endpoints `GET/POST /wpss/v1/cancion`.
- Gestor de secciones en la SPA para añadir, renombrar, reordenar o eliminar agrupaciones y asignar versos mediante selectores dedicados.
- Vista de lectura y exportación de texto agrupadas por sección con encabezados reutilizables.

### Changed
- UI de versos actualizada para trabajar con asignaciones de sección en lugar de toggles de fin de estrofa.
- Documentación extendida con contrato REST de secciones, guía de la nueva UI y escenarios de prueba manual.

### Fixed
- Rehidratación de `_segmentos_json` sin perder caracteres UTF-8 ni barras invertidas al leer metadatos legados.

## [0.1.0] - 2024-05-31
### Added
- Versión inicial del plugin **WP Song Study** con CPT `cancion` y `verso`.
- Taxonomía `tonalidad` para agrupar canciones por centro tonal.
- Metacampos para campo armónico, notas generales, préstamos, modulaciones y versos.
- Shortcodes `[songs_by_key]` y `[song]` para listar y mostrar canciones.
- Endpoint REST `GET /wpss/v1/cancion/{id}/versos` para consultar versos ordenados.
- Columnas administrativas con conteo de versos e indicadores de préstamos/modulaciones.
