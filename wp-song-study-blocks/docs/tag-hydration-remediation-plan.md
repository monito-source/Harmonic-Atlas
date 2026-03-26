# Plan de remediación: hidratación de tags en editor de canciones

## Diagnóstico de causa raíz (priorizado)

1. **Divergencia de assets entre `src` y `admin-build` (más probable)**
   - El frontend público reutilizado en `includes/shared-render.php` tenía rutas hash hardcodeadas (`assets/admin-build/assets/index-*.js|css`), sin resolver `manifest.json`.
   - Esto rompe el contrato de build reproducible: aunque `src` cambie, producción puede seguir cargando un bundle antiguo.
   - Síntoma compatible: backend responde tags correctas, UI no refleja (código viejo en runtime).

2. **Contrato frágil al guardar tags desde REST (alta severidad)**
   - En `wpss_rest_save_cancion`, si el cliente omite o corrompe `tags`, el backend terminaba resolviendo `[]` y ejecutando `wp_set_post_terms(..., 'cancion_tag', false)`.
   - Resultado: limpieza accidental de tags ante cualquier fallo de hidratación o payload incompleto.

3. **Sin observabilidad explícita de versión de assets en runtime**
   - No había un mecanismo claro para inspeccionar qué versión de build se está sirviendo.
   - Dificulta distinguir bug de estado React vs. despliegue con cache/CDN obsoleto.

---

## Arquitectura objetivo

### 1) Flujo de datos tags (editor)
- **Source of truth**: REST `GET /cancion` devuelve `tags` como `{id, slug, name}`.
- **Hidratación**: React normaliza ese arreglo y lo mantiene en `editingSong.tags`.
- **Persistencia**: `POST /cancion` envía `tags` explícitamente cuando el usuario edita tags.
- **Guard rail backend**: si `tags` no viene en el payload, el backend **preserva** tags actuales (no limpia).

### 2) Flujo de build/enqueue (block-first)
- Fuente única: `assets/admin-app/src/**`.
- Build oficial: `vite build` -> `assets/admin-build/**` + `manifest.json`.
- Enqueue en PHP siempre vía `manifest` (nunca hashes hardcodeados).
- Versionado por `filemtime(manifest)` para cache-busting consistente entre JS/CSS.

### 3) Observabilidad de assets
- Exponer `assetVersion` en payload localizado para facilitar debug en navegador.
- Checklist operacional para confirmar coincidencia entre:
  - `manifest.json` desplegado,
  - URL final en HTML,
  - versión esperada por release.

---

## Plan por fases

### Fase rápida (hot-stabilization, sin deuda)
1. Cambiar enqueue frontend para resolver entrypoints desde `manifest`.
2. Blindar REST save para no borrar tags cuando `tags` no está presente en request.
3. Añadir `assetVersion` al payload JS para diagnóstico de caché.

### Fase definitiva (hardening)
1. Incorporar en CI un check que falle si:
   - cambia `assets/admin-app/src/**` y no se regenera `assets/admin-build/**`.
2. Agregar smoke e2e:
   - abrir canción con tags,
   - guardar sin tocar tags,
   - verificar que tags persisten.
3. Añadir endpoint/healthcheck interno (o panel debug) mostrando versión de build activa.

---

## Checklist de validación manual

1. **Abrir canción con tags existentes**
   - Esperado: chips de tags visibles en editor.
2. **Guardar sin tocar tags**
   - Esperado: tags se mantienen en backend.
3. **Quitar 1 tag y guardar**
   - Esperado: se elimina solo ese tag.
4. **Agregar tag existente y guardar**
   - Esperado: se asocia correctamente sin duplicar.
5. **Agregar tag nueva y guardar**
   - Esperado: se crea término y queda asociado.
6. **Volver a lista sin hard refresh**
   - Esperado: lista muestra tags actuales.
7. **Recargar navegador completo**
   - Esperado: estado consistente con backend.
8. **Verificar versión de assets**
   - Esperado: `assetVersion` localizada coincide con `filemtime(manifest)` en servidor desplegado.

---

## Riesgos y rollback

### Riesgos
- Si un cliente legacy dependía de “omitir `tags` para limpiar”, ahora deberá enviar `tags: []` explícitamente.
- Si falta `manifest.json` en deploy, el frontend público React no se encola (fallo visible pero seguro).

### Rollback
1. Revertir commit de este cambio.
2. Volver al comportamiento previo de enqueue hardcodeado (no recomendado).
3. Volver a comportamiento previo de save tags (riesgo de limpieza accidental).

