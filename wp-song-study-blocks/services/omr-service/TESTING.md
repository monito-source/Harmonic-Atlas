# Pruebas Locales del Servicio OMR

Esta guía valida el flujo local:

```text
imagen/PDF -> Audiveris OMR -> MusicXML -> JSON
```

Ejecuta los comandos desde:

```bash
cd wp-song-study-blocks/services/omr-service
```

## 1. Instalar Docker

En macOS puedes usar Docker Desktop o Docker CLI con Colima.

Docker Desktop:

```text
https://www.docker.com/products/docker-desktop/
```

Después abre Docker Desktop y espera a que indique que Docker está corriendo.

Colima en Apple Silicon:

```bash
brew install docker docker-buildx docker-compose colima
colima start --arch aarch64 --vm-type vz --vz-rosetta --cpu 4 --memory 6 --disk 60
```

El Dockerfile fuerza `linux/amd64` porque los instaladores Linux publicados por Audiveris son `.deb` x86_64. En Apple Silicon, Rosetta evita varios problemas de QEMU al ejecutar el runtime Java empaquetado por Audiveris.

Si ya tenías un perfil Colima creado sin Rosetta y no necesitas conservar sus contenedores:

```bash
colima delete -f
colima start --arch aarch64 --vm-type vz --vz-rosetta --cpu 4 --memory 6 --disk 60
```

Verifica Docker:

```bash
docker --version
docker info
```

Si `docker info` falla, Docker Desktop todavía no está corriendo o el CLI no está instalado.

## 2. Construir la imagen

En Linux amd64:

```bash
docker build -t harmonyatlas-omr .
```

En Mac Apple Silicon con Colima/Docker Desktop:

```bash
docker buildx build --platform linux/amd64 -t harmonyatlas-omr --load .
```

El build descarga el `.deb` de Audiveris para Linux amd64 dentro de una imagen `linux/amd64`. En servidores Linux amd64 no requiere configuración especial. En Mac Apple Silicon usa Docker Desktop con soporte Rosetta o Colima con `--vz-rosetta`; usa `buildx --platform linux/amd64 --load` para que toda la imagen, dependencias de sistema y wheels de Python queden en amd64.

Para reconstruir desde cero:

```bash
docker buildx build --platform linux/amd64 --no-cache -t harmonyatlas-omr --load .
```

Para fijar otra versión de Audiveris:

```bash
docker buildx build --platform linux/amd64 --build-arg AUDIVERIS_VERSION=5.10.2 -t harmonyatlas-omr --load .
```

Verifica que la imagen final sea amd64:

```bash
docker image inspect harmonyatlas-omr --format '{{.Architecture}}/{{.Os}}'
```

Debe imprimir:

```text
amd64/linux
```

## 3. Levantar el contenedor

Sin API key:

```bash
docker run --rm -p 8080:8080 harmonyatlas-omr
```

En Apple Silicon, para evitar la advertencia de plataforma:

```bash
docker run --rm --platform linux/amd64 -p 8080:8080 harmonyatlas-omr
```

Con nombre fijo para poder usar `docker logs` y `docker stop` por nombre:

```bash
docker run --rm --platform linux/amd64 -p 8080:8080 --name harmonyatlas-omr harmonyatlas-omr
```

Con API key:

```bash
docker run --rm -p 8080:8080 --name harmonyatlas-omr \
  -e OMR_API_KEY="cambia-esta-llave" \
  harmonyatlas-omr
```

Para depuración conservando archivos temporales:

```bash
docker run --rm -p 8080:8080 --name harmonyatlas-omr \
  -e OMR_KEEP_WORKDIR=true \
  -e LOG_LEVEL=INFO \
  harmonyatlas-omr
```

## 4. Detener el contenedor

Si está corriendo en primer plano, usa `Ctrl+C`.

Si está corriendo en segundo plano:

```bash
docker stop harmonyatlas-omr
```

## 5. Ver logs

```bash
docker logs harmonyatlas-omr
```

Seguir logs en vivo:

```bash
docker logs -f harmonyatlas-omr
```

Los logs deben mostrar:

- inicio de request,
- engine usado,
- archivo temporal de entrada,
- comando de Audiveris,
- duración,
- errores de stderr si Audiveris falla.

El servicio no imprime `image_base64` completo.

## 6. Probar /health

```bash
curl http://localhost:8080/health
```

Con formato legible:

```bash
curl -fsS http://localhost:8080/health | python3 -m json.tool
```

También puedes usar el script incluido:

```bash
./scripts/check_health.sh
```

Respuesta correcta esperada:

```json
{
  "ok": true,
  "service": "omr-service",
  "version": "1.0.0",
  "engine": "audiveris",
  "engines": {
    "audiveris": {
      "available": true,
      "version": "5.10.2",
      "binary": "/usr/local/bin/audiveris"
    }
  }
}
```

Si `ok` es `false` o `engines.audiveris.available` es `false`, Audiveris no quedó instalado o el path `AUDIVERIS_BIN` no apunta al ejecutable correcto.

## 7. Probar /info

```bash
curl -fsS http://localhost:8080/info | python3 -m json.tool
```

Este endpoint devuelve versión del servicio, motores disponibles, límites, MIME types permitidos y si hay API key configurada.

## 8. Preparar una imagen de prueba

Coloca una partitura ligera en:

```text
test-assets/
```

Ejemplos recomendados:

- PNG limpio exportado desde un editor de partituras.
- Escaneo en alto contraste, bien alineado.
- PDF exportado desde MuseScore, Finale, Sibelius u otro editor.

Evita al inicio:

- fotos inclinadas,
- sombras fuertes,
- páginas recortadas,
- pentagramas incompletos,
- imágenes borrosas.

## 9. Probar /omr con script

Imagen PNG/JPG/TIFF/BMP:

```bash
./scripts/check_omr.sh ./test-assets/partitura.png
```

Smoke test incluido que sí debe producir MusicXML:

```bash
./scripts/check_omr.sh ./test-assets/greensleeves-aeolian-sheet-music.png
```

Smoke test incluido que normalmente falla por baja resolución, pero valida errores JSON claros:

```bash
./scripts/check_omr.sh ./test-assets/twinkle-twinkle-sheet-music.png
```

PDF:

```bash
./scripts/check_omr.sh ./test-assets/partitura.pdf
```

Con API key:

```bash
OMR_API_KEY="cambia-esta-llave" ./scripts/check_omr.sh ./test-assets/partitura.png
```

El script falla si la respuesta no incluye `musicxml`.

## 10. Probar /omr con curl

Crear JSON desde una imagen local:

```bash
python3 - ./test-assets/partitura.png > /tmp/omr-request.json <<'PY'
import base64
import json
import sys
from pathlib import Path

path = Path(sys.argv[1])
suffix = path.suffix.lower()
mime_type = {
    ".jpg": "image/jpeg",
    ".jpeg": "image/jpeg",
    ".tif": "image/tiff",
    ".tiff": "image/tiff",
    ".bmp": "image/bmp",
    ".pdf": "application/pdf",
}.get(suffix, "image/png")

print(json.dumps({
    "song_id": "local-test",
    "attachment_id": "score-1",
    "file_name": path.name,
    "mime_type": mime_type,
    "image_base64": base64.b64encode(path.read_bytes()).decode("ascii"),
    "score": {
        "tempo": 100,
        "instrument": "piano",
        "notes": ""
    }
}))
PY
```

Enviar request:

```bash
curl -sS -X POST http://localhost:8080/omr \
  -H "Content-Type: application/json" \
  --data-binary @/tmp/omr-request.json \
  -o /tmp/omr-response.json \
  -w "HTTP %{http_code}\n"
```

Ver respuesta resumida:

```bash
python3 - /tmp/omr-response.json <<'PY'
import json
import sys

payload = json.load(open(sys.argv[1], encoding="utf-8"))
print(json.dumps({
    "ok": payload.get("ok"),
    "engine": payload.get("engine"),
    "message": payload.get("message"),
    "warnings": payload.get("warnings"),
    "has_musicxml": bool(payload.get("musicxml")),
    "error": payload.get("error"),
}, indent=2))
PY
```

Guardar el MusicXML si existe:

```bash
python3 - /tmp/omr-response.json > /tmp/partitura.musicxml <<'PY'
import json
import sys

payload = json.load(open(sys.argv[1], encoding="utf-8"))
musicxml = payload.get("musicxml")
if not musicxml:
    raise SystemExit("La respuesta no contiene musicxml")
print(musicxml)
PY
```

## 11. Interpretar respuestas

Éxito:

```json
{
  "ok": true,
  "engine": "audiveris",
  "musicxml": "<score-partwise>...</score-partwise>",
  "warnings": []
}
```

Error de motor:

```json
{
  "ok": false,
  "engine": "audiveris",
  "error": {
    "code": "AUDIVERIS_FAILED",
    "message": "OMR engine failed",
    "details": "..."
  },
  "warnings": []
}
```

Errores comunes:

- `AUDIVERIS_NOT_AVAILABLE`: el binario de Audiveris no está en el contenedor.
- `AUDIVERIS_FAILED`: Audiveris corrió, pero no pudo procesar la imagen.
- `NO_MUSICXML`: Audiveris terminó, pero no generó MusicXML localizable.
- `INVALID_BASE64`: el request no contiene base64 válido.
- `UNSUPPORTED_MIME_TYPE`: el `mime_type` no está permitido.
- `IMAGE_TOO_LARGE`: la imagen excede `OMR_MAX_IMAGE_BYTES`.

## 12. Probar dentro del contenedor

Verificar binarios:

```bash
docker exec -it harmonyatlas-omr sh -lc 'which audiveris && audiveris -batch -help | head -40'
docker exec -it harmonyatlas-omr sh -lc 'java -version'
docker exec -it harmonyatlas-omr sh -lc 'tesseract --version | head -5'
docker exec -it harmonyatlas-omr sh -lc 'python3 --version'
```

## 13. Variables útiles

```bash
OMR_API_KEY="cambia-esta-llave"
OMR_ENGINE=auto
AUDIVERIS_BIN=audiveris
OMR_TIMEOUT_SECONDS=180
OMR_MAX_IMAGE_BYTES=16777216
OMR_NORMALIZE_IMAGES=true
OMR_KEEP_WORKDIR=false
LOG_LEVEL=INFO
```

## 14. Conectar WordPress

Si WordPress corre en la misma Mac que Docker, en ajustes de WordPress:

```text
Proveedor OMR: local_service
Local service URL: http://host.docker.internal:8080/omr
Local service API key: la misma que OMR_API_KEY, si configuraste una
Local service timeout: 180
```

Si WordPress corre fuera de Docker en la misma Mac, también puede funcionar:

```text
http://127.0.0.1:8080/omr
```

Si WordPress corre en otro contenedor, usa el nombre de red/servicio Docker correspondiente.

### Hosting compartido

En hosting compartido, `localhost` o `127.0.0.1` apunta al servidor del hosting, no a tu Mac. Eso significa que WordPress alojado fuera de tu máquina no puede llamar a `http://localhost:8080/omr` en tu laptop.

Para pruebas desde hosting compartido usa una URL pública HTTPS que apunte al microservicio:

```text
Proveedor OMR: local_service
Local service URL: https://tu-endpoint-publico.example.com/omr
```

Opciones prácticas:

- Desarrollo: publicar temporalmente el contenedor local con un túnel HTTPS como ngrok o Cloudflare Tunnel.
- Producción: desplegar este contenedor en un VPS o plataforma de contenedores Linux amd64 y configurar esa URL pública en WordPress.

`local_service` significa "servicio OMR propio compatible con `POST /omr`"; no significa que deba estar en el mismo servidor que WordPress.

Para hosting compartido, la configuración correcta suele ser:

```text
Proveedor OMR: local_service
Local service URL: https://omr.tu-dominio.com/omr
Local service API key: la misma que OMR_API_KEY del contenedor
```

No uses `http://localhost:8080/omr` en WordPress si WordPress está en hosting compartido remoto.

Para probar WordPress remoto contra el OMR local de esta Mac usando ngrok o Cloudflare Tunnel, usa la guia `TUNNEL.md`.
