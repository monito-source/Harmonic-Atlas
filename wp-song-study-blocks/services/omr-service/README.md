# HarmonyAtlas OMR Service

Microservicio HTTP para interpretar fotos de partituras desde WordPress. Recibe una imagen en base64, ejecuta Audiveris en modo batch/headless y devuelve MusicXML para que `wp-song-study-blocks` lo convierta a `midi_clips`.

## Endpoint para WordPress

Configura en los ajustes de WordPress:

```text
Proveedor OMR: local_service
Local service URL: http://TU_HOST:8080/omr
Local service API key: la misma que OMR_API_KEY
```

En hosting compartido, `localhost` no apunta a tu Mac; apunta al servidor del hosting. Para conectar WordPress alojado remotamente necesitas una URL pública HTTPS del microservicio, ya sea por túnel durante desarrollo o por despliegue en un VPS/plataforma de contenedores.

WordPress envía `image_base64`, `mime_type`, `file_name`, `song_id`, `attachment_id` y `score`.
La capa de WordPress selecciona proveedor con `wpss_omr_provider`:

- `local_service`: usa este microservicio y mantiene compatibilidad con `POST /omr`.
- `external_api`: usa una URL externa configurable con API key y timeout propios.

## Construir

```bash
cd wp-song-study-blocks/services/omr-service
docker build -t harmonyatlas-omr .
```

Por defecto el build descarga el `.deb` Linux de Audiveris `5.10.2` desde el release oficial de GitHub, instala dependencias headless y crea el enlace `/usr/local/bin/audiveris`.
La imagen fuerza `linux/amd64` porque los instaladores Linux de Audiveris se publican como `.deb` x86_64. En Apple Silicon usa Docker Desktop con Rosetta o Colima con `--vz-rosetta`; para producción usa preferentemente un host Linux amd64.
El contenedor define `JAVA_TOOL_OPTIONS=-Djava.awt.headless=true` para ejecutar Audiveris sin interfaz gráfica.

En Mac Apple Silicon, usa buildx para que la imagen completa sea amd64:

```bash
docker buildx build --platform linux/amd64 -t harmonyatlas-omr --load .
docker image inspect harmonyatlas-omr --format '{{.Architecture}}/{{.Os}}'
```

La inspección debe imprimir `amd64/linux`.

Para fijar otra versión:

```bash
docker buildx build --platform linux/amd64 --build-arg AUDIVERIS_VERSION=5.10.1 -t harmonyatlas-omr --load .
```

Colima recomendado en Apple Silicon:

```bash
colima start --arch aarch64 --vm-type vz --vz-rosetta --cpu 4 --memory 6 --disk 60
docker buildx build --platform linux/amd64 -t harmonyatlas-omr --load .
```

## Ejecutar

```bash
docker run --rm -p 8080:8080 \
  -e OMR_API_KEY="cambia-esta-llave" \
  harmonyatlas-omr
```

En Apple Silicon puedes agregar `--platform linux/amd64` al `docker run` para evitar advertencias de plataforma.

Audiveris se invoca internamente así:

```bash
audiveris -batch -transcribe -export -output <workdir>/audiveris-output -- <input-file>
```

## Health check

```bash
curl http://localhost:8080/health
```

Respuesta esperada cuando Audiveris está disponible:

```json
{
  "ok": true,
  "service": "omr-service",
  "engine": "audiveris",
  "engines": {
    "audiveris": {
      "available": true,
      "version": "5.10.2",
      "binary": "/usr/local/bin/audiveris"
    },
    "homr": {
      "available": false,
      "version": null,
      "binary": null,
      "implemented": false
    }
  }
}
```

## Probar /omr

Con una imagen local de partitura:

```bash
IMAGE_BASE64="$(python3 -c 'import base64,sys; print(base64.b64encode(open(sys.argv[1],"rb").read()).decode())' ./partitura.png)"

curl -X POST http://localhost:8080/omr \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer cambia-esta-llave" \
  -d "{
    \"song_id\": \"demo\",
    \"attachment_id\": \"score-1\",
    \"file_name\": \"partitura.png\",
    \"mime_type\": \"image/png\",
    \"image_base64\": \"$IMAGE_BASE64\",
    \"score\": { \"tempo\": 100, \"instrument\": \"piano\", \"notes\": \"\" }
  }"
```

Respuesta exitosa:

```json
{
  "ok": true,
  "status": "ready",
  "engine": "audiveris",
  "song_id": "demo",
  "attachment_id": "score-1",
  "message": "Partitura interpretada por OMR.",
  "tempo": 100,
  "instrument": "piano",
  "musicxml": "<score-partwise>...</score-partwise>",
  "warnings": [],
  "artifacts": {
    "input_file": "input.png",
    "normalized_file": "normalized.png",
    "musicxml_file": "audiveris-output/..."
  }
}
```

Respuesta de error:

```json
{
  "ok": false,
  "engine": "audiveris",
  "message": "OMR engine failed",
  "error": {
    "code": "AUDIVERIS_FAILED",
    "message": "OMR engine failed",
    "details": "..."
  },
  "warnings": []
}
```

## Variables de entorno

`OMR_API_KEY`: llave opcional. Si existe, `/omr` exige `Authorization: Bearer <key>` o `X-WPSS-OMR-Key`.

`OMR_ENGINE`: `auto`, `audiveris` o `homr`. `auto` usa Audiveris.

`AUDIVERIS_BIN`: binario de Audiveris. Default: `audiveris`.

`HOMR_BIN`: binario reservado para una integración futura de homr. Default: `homr`.

`OMR_TIMEOUT_SECONDS`: timeout del proceso OMR. Default: `180`.

`OMR_MAX_IMAGE_BYTES`: tamaño máximo decodificado de imagen. Default: `16777216`.

`OMR_NORMALIZE_IMAGES`: `true` o `false`. Convierte imágenes raster a escala de grises con autocontraste antes de Audiveris. No modifica el original.

`OMR_KEEP_WORKDIR`: `true` conserva el directorio temporal para depuración.

`LOG_LEVEL`: nivel de logs. Default: `INFO`.

## Scripts smoke

```bash
./scripts/check_health.sh
./scripts/check_omr.sh ./partitura.png
```

`check_omr.sh` falla si la respuesta no incluye `musicxml`.

Para una guía paso a paso de Docker, logs y pruebas locales, revisa `TESTING.md`.

Para probar WordPress remoto en hosting compartido contra el OMR local de la Mac usando una URL HTTPS temporal, revisa `TUNNEL.md`.

## Limitaciones conocidas

- Audiveris es OMR, no OCR genérico: imágenes borrosas, inclinadas, recortadas o con pentagramas incompletos pueden fallar o producir MusicXML incorrecto.
- PDF está permitido porque Audiveris puede recibirlo, pero la normalización previa sólo se aplica a imágenes raster.
- `homr` queda preparado en la estructura, pero el adaptador devuelve `HOMR_NOT_IMPLEMENTED`.
- El servicio no registra `image_base64` completo en logs.

## Agregar proveedores OMR futuros en WordPress

El pipeline interno espera que todos los proveedores terminen en el mismo contrato previo a MIDI:

```json
{
  "ok": true,
  "engine": "nombre-del-motor",
  "musicxml": "<score-partwise>...</score-partwise>",
  "warnings": [],
  "error": null
}
```

La integración está separada en estas piezas:

- `wpss_get_supported_omr_providers()`: agrega aquí la nueva clave del proveedor.
- `wpss_get_omr_provider_http_config($provider)`: resuelve URL, API key y timeout.
- `wpss_request_omr($provider, $payload)`: interfaz provider-based que envía el payload.
- `wpss_normalize_omr_provider_payload($payload, $provider)`: normaliza la respuesta al contrato estándar.
- `wpss_normalize_external_omr_response($payload, $context)`: no debe depender del proveedor; convierte MusicXML, `midi` o `midi_clips` al estado interno reproducible.

Si un proveedor requiere headers o body distinto, usa el filtro:

```php
add_filter( 'wpss_omr_provider_request_args', function( $args, $provider, $payload ) {
    if ( 'mi_proveedor' !== $provider ) {
        return $args;
    }

    $args['headers']['X-Custom-Auth'] = '...';
    return $args;
}, 10, 3 );
```

La regla importante: no cambies el flujo posterior a `musicxml`; sólo adapta la petición y normaliza la respuesta.
