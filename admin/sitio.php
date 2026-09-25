<?php
require __DIR__ . '/../inc/app.php';

require_admin();

// clave => [etiqueta, tipo, ayuda]
const CAMPOS = [
    'Portada' => [
        'hero_eyebrow' => ['Texto pequeño sobre el título', 'input', ''],
        'hero_titulo' => ['Título principal', 'input', ''],
        'hero_bajada' => ['Bajada', 'textarea', ''],
    ],
    'Historia' => [
        'historia_titulo' => ['Título', 'input', ''],
        'historia_intro' => ['Introducción', 'textarea', ''],
        'hitos' => ['Hitos de la línea de tiempo', 'textarea', 'Uno por línea con el formato:  Fecha | Título | Descripción'],
    ],
    'Quiénes somos' => [
        'quienes_titulo' => ['Título', 'input', ''],
        'quienes_texto' => ['Texto', 'textarea', ''],
        'valores' => ['Principios (tarjetas de colores)', 'textarea', 'Uno por línea con el formato:  Título | Descripción'],
    ],
    'Únete' => [
        'unete_titulo' => ['Título', 'input', ''],
        'unete_texto' => ['Texto', 'textarea', ''],
    ],
    'Contacto (pie de página)' => [
        'contacto_email' => ['Correo', 'input', ''],
        'contacto_telefono' => ['Celular (WhatsApp)', 'input', 'Se muestra como +56 9 1234 5678'],
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('contacto_telefono') !== '' && !telefono_normalizar(post('contacto_telefono'))) {
        flash('Contacto: ' . 'El celular debe tener 8 dígitos después del +56 9.', 'error');
        redirect('admin/sitio.php');
    }
    $_POST['contacto_telefono'] = (string)telefono_normalizar(post('contacto_telefono'));
    $st = db()->prepare('INSERT INTO ajustes (clave, valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
    foreach (array_merge(...array_values(CAMPOS)) as $clave => $_) {
        $st->execute([$clave, mb_substr(post($clave), 0, 10000)]);
    }
    flash('Contenido del sitio actualizado.');
    redirect('admin/sitio.php');
}

page_start('Contenido del sitio', 'admin/sitio.php');
?>
<p><a class="btn chico sec" href="<?= url() ?>" target="_blank" rel="noopener">Ver sitio público ↗</a></p>
<form method="post">
  <?= csrf_field() ?>
  <?php foreach (CAMPOS as $seccion => $campos): ?>
    <fieldset class="card" style="border:0">
      <h2><?= e($seccion) ?></h2>
      <?php foreach ($campos as $k => [$label, $tipo, $ayuda]): ?>
        <label><?= e($label) ?><?= $ayuda ? ' <small>' . e($ayuda) . '</small>' : '' ?>
          <?php if ($tipo === 'textarea'): ?>
            <textarea name="<?= $k ?>" rows="<?= $k === 'hitos' ? 10 : 4 ?>"><?= e(ajuste($k)) ?></textarea>
          <?php else: ?>
            <input name="<?= $k ?>" value="<?= e(ajuste($k)) ?>">
          <?php endif; ?>
        </label>
      <?php endforeach; ?>
    </fieldset>
  <?php endforeach; ?>
  <p class="muted">El sitio público es anónimo: no muestra nombres, RUT ni direcciones de socios. Evita escribirlos en estos textos. Los comunicados marcados como públicos aparecen automáticamente.</p>
  <button>Guardar cambios</button>
</form>
<?php page_end();
