# Auditoría técnica: interfaz de `wp-song-study-blocks` (Blocks vs React)

## Objetivo
Determinar si la interfaz del plugin se construye realmente con arquitectura nativa de bloques de WordPress o si se apoya en una SPA React compilada, y evaluar si conviene migrar por completo a un enfoque “WordPress blocks-first”.

---

## 1) Hallazgos de arquitectura actual

### 1.1 Registro de bloques (sí existe capa Gutenberg)
El plugin registra 2 bloques dinámicos (SSR):

- `wp-song-study/interface`
- `wp-song-study/song-list`

Esto se realiza en `includes/blocks.php` con `register_block_type(...)` y `render_callback`. Es decir: hay integración real con el editor de bloques, inserción en páginas/plantillas y render en servidor.

### 1.2 Naturaleza de cada bloque

#### Bloque `interface`
- En editor, `src/blocks/interface/edit.js` muestra básicamente un `Placeholder` + ajustes simples.
- `save.js` devuelve `null` (bloque dinámico).
- En frontend, `wpssb_render_interface_markup()` devuelve un `div` con `id="wpss-cancion-app"`.
- Luego `wpssb_enqueue_interface_assets()` carga CSS/JS compilados desde `assets/admin-build/...` y monta React sobre ese contenedor.

**Conclusión:** el bloque `interface` es un “wrapper Gutenberg” para bootstrapping de una app React compilada. No es UI block-native en su comportamiento principal.

#### Bloque `song-list`
- En editor hay controles Gutenberg (Inspector).
- `save.js` también retorna `null`.
- El HTML final se genera en PHP (`wpssb_render_song_list_markup`) con `WP_Query` + taxonomías.

**Conclusión:** este bloque sí está mucho más alineado a patrón nativo WordPress (SSR + markup controlado por PHP), sin dependencia de SPA para funcionar.

### 1.3 SPA React independiente (núcleo de UX)
Dentro de `assets/admin-app/` existe una app Vite + React (`react`, `react-dom`) que define el flujo principal:

- `main.jsx` monta `App` en `#wpss-cancion-app`.
- `AppShell.jsx` controla vistas complejas (`SongList`, `Editor`, `ReadingView`, `PublicReader`, `ChordLibrary`, etc.).
- `includes/admin-pages.php` también monta ese mismo contenedor en admin para dashboard/nueva canción/acordes.

**Conclusión:** la experiencia funcional fuerte (editor/lector/gestión) está implementada como SPA React compilada y no como composición granular de bloques nativos.

### 1.4 Integración híbrida real
Hoy la arquitectura es **híbrida**:

1) WordPress blocks (entrada/colocación/contenedor SSR),
2) React SPA compilada (lógica de interacción principal),
3) Backend WP REST + CPT/taxonomías/roles en PHP.

---

## 2) Diagnóstico estratégico

## ¿Construye la interfaz mediante blocks o mediante React compilado?
Respuesta directa: **ambas, pero el núcleo de interfaz es React compilado**.

- **Blocks**: se usan como integración editorial y render SSR para “encapsular” funciones.
- **React compilado**: se usa para casi toda la interacción avanzada y estado de UI.

En otras palabras: el plugin **no es block-native full**, es **block-integrated + React-driven**.

---

## 3) Evaluación: migrar 100% a arquitectura WP blocks vs mantener React

## Opción A — Migrar completamente a “WP blocks-first”
### Beneficios
- Consistencia total con FSE/site editor y filosofía WordPress moderna.
- Mejor interoperabilidad con patrones de bloque, block styles, theme.json y variaciones.
- Menor deuda de “dos frontends” si se rediseña bien.

### Costos/riesgos
- Reescritura importante de UX avanzada (editor de canciones, paneles, lectura, librería de acordes).
- Riesgo de perder velocidad de iteración inicial y de introducir regresiones funcionales.
- Gutenberg está basado en React, pero con restricciones de arquitectura/estado distintas a una SPA libre.
- Parte de la UX tipo “aplicación compleja” puede sentirse forzada si se modela todo como bloques.

### Veredicto técnico
Solo recomendable si hay presupuesto/tiempo para una migración de producto completa, no solo técnica.

## Opción B — Mantener React SPA como núcleo y usar blocks como shell (estado actual evolucionado)
### Beneficios
- Aprovecha inversión existente y complejidad ya resuelta.
- Menor riesgo operativo en corto plazo.
- Permite seguir usando bloques donde sí aportan (inserción en contenido, SSR de listados, composición FSE).

### Costos/riesgos
- Arquitectura híbrida permanente (más coordinación entre capas).
- Menor “pureza WordPress” en editorial/experiencia de bloque.
- Dependencia de build pipeline JS y bundles externos.

### Veredicto técnico
Es la opción más eficiente en el corto/mediano plazo para no frenar roadmap funcional.

---

## 4) Recomendación para este proyecto (considerando que tienen block theme)

Recomendación: **no migrar “todo” de golpe a blocks nativos**. Adoptar estrategia **híbrida dirigida por dominio**:

1. **Mantener React** para módulos de alta complejidad interactiva:
   - Editor avanzado de canciones.
   - Lectura interactiva rica.
   - Gestión compleja de acordes/secciones.

2. **Expandir blocks nativos** para superficies editoriales/componibles:
   - Listados, filtros, cards de canciones, metadatos.
   - Bloques “de layout” y “de consulta” que dialoguen con `theme.json`.
   - Variaciones/patrones de bloque para plantillas del block theme.

3. **Definir frontera estable API-first**:
   - Backend REST + contratos de datos versionados.
   - Evitar acoplamiento fuerte de la SPA a markup específico.

4. **Roadmap incremental (sin big-bang):**
   - **Fase 1:** endurecer bloque `song-list` y crear más bloques SSR útiles para FSE.
   - **Fase 2:** desacoplar componentes React en micro-módulos reutilizables (si aplica, usando `@wordpress/components` donde tenga sentido).
   - **Fase 3:** evaluar migración de piezas concretas de React a bloques interactivos (solo donde realmente gane UX/editorial).
   - **Fase 4:** mantener “núcleo app” en React mientras exista necesidad de UI tipo SPA compleja.

---

## 5) Criterio de decisión práctico (go/no-go para migración total)

Migrar 100% a blocks solo si se cumplen simultáneamente:

- El producto prioriza experiencia de edición en Site Editor por encima de flujos tipo dashboard SPA.
- Se acepta rediseño funcional (no mera traducción 1:1 de pantallas).
- Existe capacidad para pruebas extensivas y estabilización post-migración.

Si no se cumplen, la mejor decisión técnica es:

> **Arquitectura híbrida intencional**: WordPress blocks para composición y distribución de contenido + React para experiencias complejas de aplicación.

---

## 6) Conclusión ejecutiva

- Actualmente el plugin ya aprovecha WordPress Blocks, pero principalmente como capa de integración.
- La interfaz principal se implementa con React compilado y montado por contenedor SSR.
- Dado el estado del código y la complejidad funcional, **la mejor decisión es evolucionar el híbrido**, no forzar migración total inmediata a blocks.
- Sí conviene aumentar gradualmente la superficie block-native para exprimir su block theme al máximo sin sacrificar productividad ni estabilidad del producto.
