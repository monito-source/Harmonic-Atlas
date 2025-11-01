# Changelog

# [Unreleased]
### Added
- Versos soportan múltiples segmentos texto-acorde persistidos en `_segmentos_json` con migración retrocompatible.
- Nuevo modo predeterminado **Frigio Dominante** y biblioteca editable de campos armónicos (REST `GET/POST /wpss/v1/campos-armonicos`).
- SPA con pestañas para Editor, Vista de lectura y Campos armónicos, incluyendo validaciones de segmentos y botón “Copiar como texto”.
- Vista pública `[song]` actualizada para mostrar segmentos y eventos por verso.
- Separadores de estrofa opcionales por verso con etiqueta editable para secciones como “Coro” o “Puente”, visibles en el editor, la vista de lectura y el portapapeles.

### Changed
- Interfaz de versos reorganizada con controles para duplicar, dividir y reordenar segmentos.
- Documentación ampliada con ejemplos de segmentos y guía de la biblioteca de campos armónicos.

### Fixed
- Rehidratación robusta de `_segmentos_json` al cargar versos (unslash/deserialización/JSON) con registro de corrupciones antes de caer en la ruta retrocompatible, preservando la segmentación guardada.

## [0.1.0] - 2024-05-31
### Added
- Versión inicial del plugin **WP Song Study** con CPT `cancion` y `verso`.
- Taxonomía `tonalidad` para agrupar canciones por centro tonal.
- Metacampos para campo armónico, notas generales, préstamos, modulaciones y versos.
- Shortcodes `[songs_by_key]` y `[song]` para listar y mostrar canciones.
- Endpoint REST `GET /wpss/v1/cancion/{id}/versos` para consultar versos ordenados.
- Columnas administrativas con conteo de versos e indicadores de préstamos/modulaciones.
