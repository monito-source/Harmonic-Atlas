<?php
/**
 * Template Name: Editor de sesiones del curso 
 */
get_header(); ?>

<main class="curso-editor" style="max-width: 90%; margin: auto; padding: 2rem;">
  <h1>Editor de sesiones del curso</h1>

  <?php
    $curso_id = get_the_ID(); // este archivo se asignará a una página, no a un CPT
    $datos = get_field('detalles_del_curso', $curso_id);
    $sesiones = is_array($datos) ? ($datos['sesiones_relacionadas'] ?? []) : [];
  ?>

  <div id="editor-sesiones">
    <?php foreach ($sesiones as $sesion): ?>
      <?php
        $sesion = is_numeric($sesion) ? get_post($sesion) : $sesion;
        $objetivo = get_field('detalles_de_la_sesion_objetivo_de_la_sesion', $sesion);
      ?>
      <div class="bloque-sesion-editable" data-id="<?php echo $sesion->ID; ?>">
        <label><strong>Título:</strong></label><br>
        <input type="text" class="titulo" value="<?php echo esc_attr($sesion->post_title); ?>" style="width:100%; margin-bottom:1rem;"><br>

        <label><strong>Objetivo:</strong></label><br>
        <textarea class="objetivo" style="width:100%; height:100px;"><?php echo esc_textarea($objetivo); ?></textarea><br>

        <button class="guardar-btn">💾 Guardar cambios</button>
        <hr>
      </div>
    <?php endforeach; ?>
  </div>

  <h2>➕ Agregar nueva sesión</h2>
  <div id="nueva-sesion-form" data-curso-id="<?php echo $curso_id; ?>">
    <input type="text" id="nuevo-titulo" placeholder="Título" style="width:100%;"><br>
    <textarea id="nuevo-objetivo" placeholder="Objetivo" style="width:100%; height:100px;"></textarea><br>
    <button id="crear-sesion">Crear nueva sesión</button>
  </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.guardar-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const bloque = btn.closest('.bloque-sesion-editable');
      const id = bloque.dataset.id;
      const titulo = bloque.querySelector('.titulo').value;
      const objetivo = bloque.querySelector('.objetivo').value;

      fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
          action: 'guardar_sesion_ajax',
          sesion_id: id,
          titulo: titulo,
          objetivo: objetivo
        })
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          alert('Sesión actualizada');
        } else {
          alert('Error: ' + data.data);
        }
      });
    });
  });

  document.getElementById('crear-sesion').addEventListener('click', () => {
    const cursoId = document.getElementById('nueva-sesion-form').dataset.cursoId;
    const titulo = document.getElementById('nuevo-titulo').value;
    const objetivo = document.getElementById('nuevo-objetivo').value;

    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: new URLSearchParams({
        action: 'crear_sesion_ajax',
        curso_id: cursoId,
        titulo: titulo,
        objetivo: objetivo
      })
    })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert('Sesión creada, recarga la página para verla');
      } else {
        alert('Error: ' + data.data);
      }
    });
  });
});
</script>

<?php get_footer(); ?>