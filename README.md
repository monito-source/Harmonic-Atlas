# Harmonic Atlas

Harmonic Atlas es un plugin de WordPress pensado como cuaderno de estudio para tus canciones. Permite registrar cada pieza con su centro tonal principal, préstamos modales, modulaciones por sección y versos anotados acorde por acorde.

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
* **Préstamos tonales** (`_prestamos_tonales_json`). Cadena JSON con objetos `{ "de_tonalidad": "C eólico", "acordes": "iv, bVI" }`.
* **Modulaciones** (`_modulaciones_json`). Cadena JSON con objetos `{ "seccion": "Puente", "a_tonalidad": "E mixolidio" }`.

#### Ejemplos JSON

```json
{
  "prestamos": [
    { "de_tonalidad": "C eólico", "acordes": "iv, bVI" },
    { "de_tonalidad": "G mixolidio", "acordes": "bVII" }
  ],
  "modulaciones": [
    { "seccion": "Pre-coro", "a_tonalidad": "E mayor" },
    { "seccion": "Solo", "a_tonalidad": "B mayor" }
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

## Interfaz administrativa

En el listado de canciones encontrarás columnas adicionales para mostrar:

* Tonalidad principal.
* Número de versos.
* Indicadores de si existen préstamos o modulaciones registradas.

Las columnas de **Versos** son ordenables para ubicar las canciones con más o menos material lírico.

## Shortcodes disponibles

* `[songs_by_key key="C-jonico"]` — Lista todas las canciones asignadas a la tonalidad indicada.
* `[song id="123"]` — Muestra la ficha completa de una canción con versos y acordes. Si `id` se omite, utiliza el ID del post en contexto.

## API REST

Se expone un endpoint público para integraciones o visualizaciones externas:

```
GET /wp-json/wpss/v1/cancion/{id}/versos
```

Respuesta: lista de versos ordenados con acorde absoluto, función relativa y notas.

## Desarrollo

* Hooks de activación/desactivación registrados para preparar reglas de reescritura.
* Plantilla JS (`assets/admin.js`) lista para ampliar la experiencia en el administrador.
* Código internacionalizable (`Text Domain: wp-song-study`).

## Roadmap breve

1. UI enriquecida para editar préstamos y modulaciones con repetidores amigables.
2. Reordenar versos mediante arrastrar/soltar.
3. Visualizaciones analíticas de viaje tonal.

Consulta `CHANGELOG.md` para detalles de versiones.
