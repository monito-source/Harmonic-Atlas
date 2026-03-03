<?php get_header(); ?>

<main class="curso-container">
  <?php if ( have_posts() ) : the_post(); ?>
    <h1><?php the_title(); ?></h1>

    <div class="curso-descripcion">
      <?php the_field('detalles_del_curso_descripcion_del_curso_'); ?>
    </div>

    <?php
      $datos_curso = get_field('detalles_del_curso');
      $sesiones = is_array($datos_curso) ? ($datos_curso['sesiones_relacionadas'] ?? []) : [];

      if (!empty($sesiones)) :
    ?>
      <h2><span class="emoji">📆</span> Sesiones del curso</h2>
      <?php foreach ($sesiones as $sesion):
        // Asegurar que $sesion es un objeto WP_Post
        $sesion = is_numeric($sesion) ? get_post($sesion) : $sesion;
        if (!$sesion instanceof WP_Post) continue;

        $id_sesion = 'sesion_' . $sesion->ID;
        $titulo_sesion = get_the_title($sesion);
        $objetivo = get_field('detalles_de_la_sesion_objetivo_de_la_sesion', $sesion);
        $microaprendizajes = get_field('detalles_de_la_sesion_microaprendizajes', $sesion);
      ?>
        <details id="<?php echo esc_attr($id_sesion); ?>" class="bloque-sesion">
          <summary class="sesion-summary">
            <span><span class="emoji">📚</span> <?php echo esc_html($titulo_sesion); ?></span>
            <button onclick="imprimirSesion('<?php echo esc_js($id_sesion); ?>', event)">
              <span class="emoji">🖨</span> Imprimir
            </button>
          </summary>

          <?php if ($objetivo): ?>
            <p><strong><span class="emoji">🎯</span> Objetivo:</strong> <?php echo esc_html($objetivo); ?></p>
          <?php endif; ?>

          <?php if (!empty($microaprendizajes)): ?>
            <?php foreach ($microaprendizajes as $micro):
              $micro = is_numeric($micro) ? get_post($micro) : $micro;
              if (!$micro instanceof WP_Post) continue;

              $titulo_micro = get_the_title($micro);
              $detalles = get_field('detalles_del_microaprendizaje', $micro);
            ?>
              <details class="bloque-micro">
                <summary><span class="emoji">🔹</span> <?php echo esc_html($titulo_micro); ?></summary>

                <?php if ($detalles): ?>
                  <?php if (!empty($detalles['objetivo_del_microaprendizaje_'])): ?>
                    <p><strong><span class="emoji">🎯</span> Objetivo:</strong>
                      <?php echo esc_html($detalles['objetivo_del_microaprendizaje_']); ?>
                    </p>
                  <?php endif; ?>

                  <?php if (!empty($detalles['area_de_desarrollo'])): ?>
                    <p><strong><span class="emoji">🧠</span> Área de desarrollo:</strong><br>
                      <?php echo wp_kses_post($detalles['area_de_desarrollo']); ?>
                    </p>
                  <?php endif; ?>

                  <?php if (!empty($detalles['preguntas_y_pensamientos_clave'])): ?>
                    <p><strong><span class="emoji">❓</span> Preguntas clave:</strong><br>
                      <?php echo nl2br(esc_html($detalles['preguntas_y_pensamientos_clave'])); ?>
                    </p>
                  <?php endif; ?>

                  <?php if (!empty($detalles['ejercicios'])): ?>
                    <p><strong><span class="emoji">💪</span> Ejercicios:</strong><br>
                      <?php echo wp_kses_post($detalles['ejercicios']); ?>
                    </p>
                  <?php endif; ?>

                  <?php if (!empty($detalles['actividad_de_refuerzo_en_casa'])): ?>
                    <p><strong><span class="emoji">🏠</span> Refuerzo en casa:</strong><br>
                      <?php echo wp_kses_post($detalles['actividad_de_refuerzo_en_casa']); ?>
                    </p>
                  <?php endif; ?>
                <?php else: ?>
                  <em>Microaprendizaje sin detalles registrados.</em>
                <?php endif; ?>
              </details>
            <?php endforeach; ?>
          <?php else: ?>
            <p><em>Esta sesión aún no tiene microaprendizajes asignados.</em></p>
          <?php endif; ?>
        </details>
      <?php endforeach; ?>
    <?php else: ?>
      <p><em>Este curso aún no tiene sesiones asignadas.</em></p>
    <?php endif; ?>
  <?php endif; ?>
</main>

<style>
  .emoji {
    font-size: 0.95em;
    vertical-align: middle;
    line-height: 1;
  }

  .bloque-sesion {
    margin-bottom: 1.5rem;
    border: 1px solid #ccc;
    padding: 1rem;
    border-radius: 12px;
  }

  .sesion-summary {
    font-weight: bold;
    font-size: 1.1rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    padding: 10px;
  }

  .bloque-micro {
    background-color: #f9f9f9;
    border-left: 4px solid #ccc;
    border-radius: 8px;
    padding: 1rem 1.5rem;
    margin-top: 1rem;
    margin-bottom: 1.5rem;
  }

  .bloque-micro summary {
    font-size: 1rem;
    padding: 10px;
    cursor: pointer;
  }

  .bloque-micro p {
    margin-bottom: 1.2rem;
    line-height: 1.6;
  }

  summary button {
    background-color: #e0e0e0;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    padding: 4px 8px;
    font-size: 0.8rem;
    transition: background-color 0.2s ease;
  }

  summary button:hover {
    background-color: #d5d5d5;
  }

  /* 👇 Estilos para impresión */
  @media print {
    /* Para cualquier imagen emoji, sin importar si está en span o no */
    img.emoji,
    .emoji img {
      max-width: 1em !important;
      height: auto !important;
      vertical-align: middle !important;
      display: inline-block !important;
    }

    /* Por si hay algún span o contenedor con la clase emoji */
    .emoji {
      font-size: inherit !important;
      line-height: 1 !important;
      vertical-align: middle !important;
      display: inline-block !important;
    }

    /* Ocultar botones de impresión */
    summary button {
      display: none !important;
    }

    summary::marker {
      display: none !important;
    }

    /* 👇 Saltos de página entre sesiones y microaprendizajes */
    .bloque-sesion {
      page-break-before: always;
    }

    .bloque-micro {
      page-break-before: always;
    }
  }
</style>


<script>
  function imprimirSesion(id, event) {
    event.stopPropagation();

    const sesion = document.getElementById(id);
    if (!sesion) return;

    const content = sesion.cloneNode(true);
    content.querySelectorAll('button')?.forEach(btn => btn.remove());
    content.querySelectorAll('details').forEach(el => el.open = true);

    const win = window.open('', '', 'width=800,height=600');
win.document.write(`
  <html>
    <head>
      <title>Imprimir sesión</title>
      <style>
        body {
          font-family: 'Segoe UI', sans-serif;
          padding: 2rem;
          color: #222;
        }

        details {
          margin-bottom: 1rem;
        }

        summary, h1, h2 {
          font-weight: bold;
          font-size: 1.2rem;
          margin: 1rem 0;
        }

        @media print {
          /* 👇 AQUI ESTÁ LA CLAVE */
          img.emoji,
          .emoji img {
            max-width: 1em !important;
            height: auto !important;
            vertical-align: middle !important;
            display: inline-block !important;
          }

          .emoji {
            font-size: inherit !important;
            line-height: 1 !important;
            vertical-align: middle !important;
            display: inline-block !important;
          }

          summary button {
            display: none !important;
          }

          summary::marker {
            display: none !important;
          }
        }
      </style>
    </head>
    <body>
      <h1>${document.title}</h1>
      ${content.innerHTML}
    </body>
  </html>
`);


    win.document.close();
    win.focus();
    win.print();
  }
</script>

<?php get_footer(); ?>
