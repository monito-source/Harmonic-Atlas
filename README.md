# Harmonic Atlas

Harmonic Atlas es un plugin de WordPress pensado como cuaderno de estudio para tus canciones. Permite registrar cada pieza con su centro tonal principal, préstamos modales, modulaciones por sección y versos anotados acorde por acorde desde una GUI centralizada en el administrador.

## Instalación

1. Clona o descarga este repositorio.
2. Copia la carpeta `wp-song-study` en el directorio `wp-content/plugins/` de tu instalación de WordPress.
3. Activa **WP Song Study** desde el panel de administración (`Plugins > Instalados`).

> Requisitos mínimos: WordPress 6.0+, PHP 7.4+.

## Contenido registrado

### Canciones (`cancion`)

* **Título** (nativo de WordPress).
* **Tonalidad principal** mediante la taxonomía `tonalidad`. No existe lista cerrada: crea cualquier término como `D jónico`, `A eólico`, etc.
* **Campo armónico predominante** (`_campo_armonico_predominante`). Texto libre para describir el color armónico.
* **Notas generales** (`_notas_generales`). Campo opcional para observaciones.
* **Préstamos tonales** (`_prestamos_tonales_json`). Cadena JSON con objetos `{ "origen": "C eólico", "descripcion": "iv, bVI", "notas": "Color especial" }`.
* **Modulaciones** (`_modulaciones_json`). Cadena JSON con objetos `{ "seccion": "Puente", "destino": "E mixolidio" }`.
* **Banderas** (`_tiene_prestamos`, `_tiene_modulaciones`). Booleanos que facilitan filtros rápidos en la GUI y REST.
* **Conteo de versos** (`_conteo_versos`). Entero actualizado automáticamente tras cada guardado.

#### Ejemplos JSON

```json
{
  "prestamos": [
    { "origen": "C eólico", "descripcion": "iv, bVI", "notas": "Cierre modal" }
  ],
  "modulaciones": [
    { "seccion": "Pre-coro", "destino": "E mayor" }
  ]
}
```

### Versos (`verso`)

Cada verso se guarda como CPT hijo con los siguientes campos:

* **Texto del verso** (editor nativo).
* **Orden** (`_orden`). Número entero para ordenar la secuencia.
* **Acorde absoluto** (`_acorde_absoluto`). Texto libre (por ejemplo `E♭maj7`).
* **Función relativa** (`_funcion_relativa`). Opcional para numerales romanos o etiquetas como `V7/ii`.
* **Notas adicionales** (`_notas_verso`). Observaciones o análisis puntual.

## GUI Cancionario Armónico

El plugin añade un menú propio en el administrador de WordPress:

```
Cancionario Armónico
├── Dashboard / Biblioteca
└── Nueva Canción
```

Ambas páginas cargan una SPA (`assets/cancion-dashboard.js`) que permite:

1. **Explorar y filtrar canciones** por tonalidad, presencia de préstamos o modulaciones y paginar los resultados.
2. **Editar en una sola vista** el título, tonalidad, campo armónico predominante, préstamos tonales, modulaciones y la tabla completa de versos.
3. **Gestionar versos dinámicamente**: añadir, eliminar y reordenar con botones de subir/bajar que preservan el campo `_orden`.
4. **Guardar de forma atómica** canción + préstamos + modulaciones + versos mediante AJAX seguro con nonce dedicado.

> Consejo: la acción “Nueva canción” limpia el editor sin perder la lista filtrada de la biblioteca.

## API REST

Todas las rutas se exponen bajo el namespace `wpss/v1` y requieren `current_user_can( 'edit_posts' )` junto con el encabezado `X-WPSS-Nonce` generado en el administrador.

| Método | Ruta | Descripción |
| --- | --- | --- |
| `GET` | `/wpss/v1/canciones?tonalidad=&con_prestamos=&con_modulaciones=&page=1&per_page=20` | Lista paginada con filtros. Devuelve `id`, `titulo`, `tonalidad`, banderas y conteo de versos. Cabeceras `X-WP-Total` y `X-WP-TotalPages` indican totales. |
| `GET` | `/wpss/v1/cancion/{id}` | Recupera la estructura completa de una canción: metas, préstamos, modulaciones y versos ordenados. |
| `POST` | `/wpss/v1/cancion` | Crea o actualiza una canción y reemplaza su set de préstamos, modulaciones y versos en una sola operación. Responde con `{ ok, id, tiene_prestamos, tiene_modulaciones }`. |

Payload esperado al guardar:

```json
{
  "id": null,
  "titulo": "Nombre de la canción",
  "tonalidad": "D jónico",
  "campo_armonico": "Descripción breve",
  "prestamos": [
    { "origen": "D dórico", "descripcion": "iv menor, bVII", "notas": "" }
  ],
  "modulaciones": [
    { "seccion": "Solo pt1", "destino": "C jónico" }
  ],
  "versos": [
    { "orden": 1, "texto": "Primera línea", "acorde": "Dmaj7", "comentario": "I" }
  ]
}
```

## Shortcodes disponibles

* `[songs_by_key key="C-jonico"]` — Lista todas las canciones asignadas a la tonalidad indicada.
* `[song id="123"]` — Muestra la ficha completa de una canción con versos y acordes. Si `id` se omite, utiliza el ID del post en contexto.

## Desarrollo

* Hooks de activación/desactivación registrados para preparar reglas de reescritura.
* SPA administrativa (`assets/cancion-dashboard.js` + `.css`) que consume los endpoints internos y opera con nonces localizados.
* Código internacionalizable (`Text Domain: wp-song-study`).

## Pruebas manuales sugeridas

1. Abrir **Cancionario Armónico > Dashboard / Biblioteca** y verificar que la lista cargue con filtros y paginación.
2. Crear una canción nueva con préstamos, modulaciones y al menos tres versos; guardar y confirmar mensaje de éxito sin recargar la página.
3. Reordenar versos usando las flechas y confirmar que el orden se respeta tras guardar y recargar el registro.
4. Usar los filtros de la biblioteca para mostrar solo canciones con préstamos y validar que los indicadores correspondan.
5. Editar una canción existente desde la lista, modificar campos y guardar verificando que los cambios se reflejen en la tabla.
6. Revisar las peticiones REST en el inspector para comprobar cabeceras `X-WPSS-Nonce` y respuestas `{ ok, id, tiene_* }`.

Consulta `CHANGELOG.md` para detalles de versiones.
