# Pruebas con Tunel HTTPS

Esta guia conecta un WordPress remoto en hosting compartido con el servicio OMR corriendo localmente en la Mac:

```text
WordPress remoto -> HTTPS publico temporal -> tunel -> http://localhost:8080 en la Mac -> OMR Docker
```

## Localhost en este contexto

`localhost` siempre significa "esta misma maquina" desde el punto de vista del proceso que hace la peticion.

- En tu Mac, `http://localhost:8080` apunta al contenedor Docker publicado en la Mac.
- En WordPress remoto, `http://localhost:8080` apunta al servidor del hosting compartido.
- Por eso WordPress remoto no puede alcanzar el OMR local usando `localhost`.

Un tunel HTTPS resuelve esto creando una URL publica, por ejemplo:

```text
https://abc123.ngrok-free.app
```

Esa URL recibe peticiones desde internet y las reenvia al servicio local:

```text
https://abc123.ngrok-free.app/omr -> http://localhost:8080/omr
```

## ngrok vs Cloudflare Tunnel

`ngrok` es la opcion mas directa para pruebas rapidas:

- instalacion simple con Homebrew,
- URL temporal lista en segundos,
- inspector local de requests en `http://127.0.0.1:4040`,
- requiere cuenta y authtoken.

`Cloudflare Tunnel` tambien sirve para pruebas:

- puede generar URLs temporales `trycloudflare.com`,
- puede usarse sin abrir puertos entrantes,
- es mejor si despues quieres moverlo a un dominio propio en Cloudflare,
- para una configuracion estable conviene crear un tunel nombrado desde Cloudflare.

Para esta etapa usa `ngrok` primero. Es mas simple para validar WordPress remoto -> OMR local.

## 1. Levantar OMR local

Desde esta carpeta:

```bash
cd wp-song-study-blocks/services/omr-service
```

Construye la imagen en Mac Apple Silicon:

```bash
docker buildx build --platform linux/amd64 -t harmonyatlas-omr --load .
```

Arranca el contenedor con API key. Al estar expuesto por tunel, no lo dejes sin llave:

```bash
docker rm -f harmonyatlas-omr
docker run -d --platform linux/amd64 --name harmonyatlas-omr \
  -p 8080:8080 \
  -e OMR_API_KEY="cambia-esta-llave" \
  harmonyatlas-omr
```

Verifica localmente:

```bash
curl -fsS http://localhost:8080/health | python3 -m json.tool
```

`/health` no exige API key. `/omr` si la exige cuando `OMR_API_KEY` esta configurada.

## 2. Instalar ngrok en macOS

```bash
brew install ngrok
ngrok help
```

Entra a tu cuenta de ngrok, copia tu authtoken y registralo:

```bash
ngrok config add-authtoken "<TU_NGROK_AUTHTOKEN>"
```

## 3. Abrir tunel hacia OMR local

Con el contenedor corriendo en `localhost:8080`:

```bash
ngrok http 8080
```

Tambien puedes ser explicito:

```bash
ngrok http http://localhost:8080
```

ngrok imprimira una URL HTTPS parecida a:

```text
Forwarding  https://abc123.ngrok-free.app -> http://localhost:8080
```

Deja esa terminal abierta mientras pruebas WordPress. Si detienes ngrok, la URL deja de funcionar.

## 4. Verificar endpoint publico

Guarda la URL base publica sin slash final:

```bash
PUBLIC_OMR_URL="https://abc123.ngrok-free.app"
```

Prueba `/health` desde tu Mac, pero usando la URL publica:

```bash
curl -fsS "$PUBLIC_OMR_URL/health" | python3 -m json.tool
```

Debe devolver `ok: true` y `engines.audiveris.available: true`.

Prueba `/info` para depuracion rapida:

```bash
curl -fsS "$PUBLIC_OMR_URL/info" | python3 -m json.tool
```

Prueba `/omr` con el script incluido:

```bash
SERVICE_URL="$PUBLIC_OMR_URL" \
OMR_API_KEY="cambia-esta-llave" \
./scripts/check_omr.sh ./test-assets/greensleeves-aeolian-sheet-music.png
```

Respuesta esperada:

```json
{
  "ok": true,
  "engine": "audiveris",
  "has_musicxml": true
}
```

Si quieres probar el error JSON claro por baja resolucion:

```bash
SERVICE_URL="$PUBLIC_OMR_URL" \
OMR_API_KEY="cambia-esta-llave" \
./scripts/check_omr.sh ./test-assets/twinkle-twinkle-sheet-music.png
```

Esa imagen normalmente falla por resolucion insuficiente para Audiveris, no por tunel.

## 5. Configurar WordPress remoto

En los ajustes OMR del plugin:

```text
Proveedor OMR: local_service
Local service URL: https://abc123.ngrok-free.app/omr
Local service API key: cambia-esta-llave
Local service timeout: 180
```

La URL que va en WordPress debe incluir `/omr`.

No uses:

```text
http://localhost:8080/omr
```

Eso solo sirve cuando WordPress corre en la misma Mac. En hosting compartido remoto, `localhost` apunta al hosting.

## 6. Cloudflare Tunnel opcional

Para una prueba rapida sin tunel nombrado:

```bash
brew install cloudflared
cloudflared tunnel --url http://localhost:8080
```

Cloudflare imprimira una URL parecida a:

```text
https://random-name.trycloudflare.com
```

Pruebas:

```bash
PUBLIC_OMR_URL="https://random-name.trycloudflare.com"
curl -fsS "$PUBLIC_OMR_URL/health" | python3 -m json.tool

SERVICE_URL="$PUBLIC_OMR_URL" \
OMR_API_KEY="cambia-esta-llave" \
./scripts/check_omr.sh ./test-assets/greensleeves-aeolian-sheet-music.png
```

En WordPress:

```text
Proveedor OMR: local_service
Local service URL: https://random-name.trycloudflare.com/omr
Local service API key: cambia-esta-llave
Local service timeout: 180
```

## Checklist de debugging

Verificar Docker/Colima:

```bash
colima status
docker ps --filter name=harmonyatlas-omr
docker image inspect harmonyatlas-omr --format '{{.Architecture}}/{{.Os}}'
```

La imagen debe ser:

```text
amd64/linux
```

Ver logs del contenedor:

```bash
docker logs --tail 80 harmonyatlas-omr
docker logs -f harmonyatlas-omr
```

Probar OMR local sin tunel:

```bash
curl -fsS http://localhost:8080/health | python3 -m json.tool
OMR_API_KEY="cambia-esta-llave" ./scripts/check_omr.sh ./test-assets/greensleeves-aeolian-sheet-music.png
```

Verificar que ngrok sigue activo:

```bash
ngrok http 8080
```

Mientras ngrok esta corriendo, abre:

```text
http://127.0.0.1:4040
```

Ahi puedes ver si WordPress realmente esta llamando `/omr`, que HTTP status recibe y cuanto tarda.

Detectar si WordPress no alcanza el endpoint:

- `curl "$PUBLIC_OMR_URL/health"` falla: el tunel no esta activo o la URL cambio.
- ngrok no muestra requests: WordPress no esta llamando esa URL, o el hosting bloquea la salida.
- ngrok muestra request pero Docker no muestra logs: el tunel no esta reenviando a `localhost:8080`.
- Docker muestra `/omr` y luego error JSON: la llamada llego; revisar el `error.code` de OMR.

Timeouts:

- Sube `Local service timeout` en WordPress a `180`.
- Deja `OMR_TIMEOUT_SECONDS=180` o mas en Docker si pruebas partituras grandes.
- Si ngrok muestra `504`, el proceso OMR tardo demasiado o el contenedor dejo de responder.
- Si WordPress falla antes de que ngrok muestre respuesta, el timeout probablemente esta en WordPress/PHP.

CORS y HTTPS:

- El flujo normal del plugin es server-to-server: WordPress llama al OMR desde PHP. CORS no aplica ahi.
- Si ves errores CORS en el navegador, probablemente estas llamando el tunel directamente desde JavaScript, no desde el backend WordPress.
- Usa siempre la URL `https://...` del tunel en WordPress para evitar bloqueos por contenido mixto.

API key:

- Si Docker arranco con `OMR_API_KEY`, WordPress debe tener la misma key en `Local service API key`.
- Error `UNAUTHORIZED` significa que la key falta o no coincide.
- `/health` e `/info` no prueban la key; `/omr` si.

Calidad de imagen:

- `AUDIVERIS_FAILED` con mensaje de baja resolucion indica problema de la imagen, no del tunel.
- Usa PNG limpio, escaneo alineado o PDF exportado desde editor de partituras.
- Evita fotos inclinadas o borrosas para la primera prueba real.
