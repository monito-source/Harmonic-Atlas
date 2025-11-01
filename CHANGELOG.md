# Changelog

# [Unreleased]
### Added
- Versos soportan múltiples segmentos texto-acorde persistidos en `_segmentos_json` con migración retrocompatible.
- Nuevo modo predeterminado **Frigio Dominante** y biblioteca editable de campos armónicos (REST `GET/POST /wpss/v1/campos-armonicos`).
- SPA con pestañas para Editor, Vista de lectura y Campos armónicos, incluyendo validaciones de segmentos y botón “Copiar como texto”.
- Vista pública `[song]` actualizada para mostrar segmentos y eventos por verso.

### Changed
- Interfaz de versos reorganizada con controles para duplicar, dividir y reordenar segmentos.
- Documentación ampliada con ejemplos de segmentos y guía de la biblioteca de campos armónicos.

## [0.1.0] - 2024-05-31
### Added
- Versión inicial del plugin **WP Song Study** con CPT `cancion` y `verso`.
- Taxonomía `tonalidad` para agrupar canciones por centro tonal.
- Metacampos para campo armónico, notas generales, préstamos, modulaciones y versos.
- Shortcodes `[songs_by_key]` y `[song]` para listar y mostrar canciones.
- Endpoint REST `GET /wpss/v1/cancion/{id}/versos` para consultar versos ordenados.
- Columnas administrativas con conteo de versos e indicadores de préstamos/modulaciones.
