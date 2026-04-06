# Arquitectura 2.1: proyectos y pertenencia digital

## Decisión

La lógica de dominio de `proyectos`, membresías y presskits vive en el plugin `wp-song-study-blocks`.
El tema `pertenencia-digital` conserva la capa visual: plantillas FSE, layouts, navegación y páginas semilla.

## Qué debe vivir en el plugin

- CPT `proyecto`
- Taxonomía `area_proyecto`
- Relación proyecto -> usuarios
- Meta de presskit del proyecto
- Meta de presskit del usuario colaborador
- Pantallas de administración y profile fields para editar esos datos
- Shortcodes y bloques que exponen estos datos al tema

## Qué debe vivir en el tema

- `single-proyecto.html`
- `author.html`
- páginas de navegación como `page-proyectos.html` y `proyectos-musica.html`
- estilos, composición visual y jerarquía editorial
- seed de páginas públicas que usen las plantillas del tema

## Contrato de compatibilidad

- El plugin expone el dominio con slugs y meta estables: `proyecto`, `area_proyecto`, `pd_proyecto_*`, `pd_colaborador_*`.
- El tema puede seguir usando los shortcodes heredados `pd_*` sin romper contenido existente.
- El plugin también expone bloques SSR para `project-presskit`, `project-gallery`, `project-contact`, `collaborator-presskit`, `collaborator-gallery`, `collaborator-contact` y `collaborator-projects`.
- Para navegación pública y páginas semilla, el plugin expone además `project-directory`, con filtros por `areaSlug` y opción de `onlyCurrentUser`.
- Para autogestión frontend, el plugin expone `current-membership`, con formulario del presskit propio y listado de proyectos del usuario autenticado.
- Si el usuario actual es administrador, `current-membership` permite cambiar el usuario objetivo y editar cualquier perfil gestionable desde frontend.
- Cuando el módulo centralizado del plugin está activo, el tema deja de registrar hooks legacy para evitar duplicados.

## Fases siguientes

1. Crear bloques adicionales para variantes editoriales, por ejemplo listados filtrados por `area_proyecto` o módulos de navegación entre proyectos relacionados.
2. Añadir plantillas o patrones semilla para páginas tipo `presskit` y `landing de proyecto` usando exclusivamente bloques del plugin.
3. Extender la autogestión frontend hacia galería, enlaces de prensa más ricos o subida de materiales si realmente hace falta fuera de `profile.php`.

## Acceso al cancionero

- `colega_musical`: puede leer y gestionar el cancionero.
- `invitado`: puede leer el cancionero autenticado, pero no entrar a la vista de edición.
- visitantes anónimos: sólo pueden ver canciones marcadas como `public` y además legalmente publicables.

## Visibilidad por canción

- `public`: visible en la vista pública si la ficha legal permite publicación.
- `private`: visible para usuarios autenticados con acceso de lectura al cancionero.
- `project`: visible para administradores o para usuarios autenticados que pertenezcan a alguno de los proyectos vinculados.
