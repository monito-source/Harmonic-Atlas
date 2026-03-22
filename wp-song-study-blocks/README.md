# WP Song Study Blocks

Versión basada en bloques de `wp-song-study`.

## Bloques incluidos

- `wp-song-study/interface`: reemplaza el shortcode del lector público mediante un bloque dinámico SSR.
- `wp-song-study/song-list`: expone un listado reutilizable de canciones para posts, pages y templates FSE.

## Migración desde shortcodes

- `[wpss_public_reader]` → bloque **Song Study Interface**.
- `[songs_by_key key="c"]` → bloque **Song List** con atributo `tonalidad="c"`.

## Notas técnicas

- Si el plugin clásico `wp-song-study` está activo, este plugin reutiliza sus funciones backend para evitar colisiones.
- Si el plugin clásico no está activo, carga una copia del backend legado incluida en `includes/`.
- La interfaz principal reutiliza el frontend React ya existente compilado en `assets/admin-build/`.
