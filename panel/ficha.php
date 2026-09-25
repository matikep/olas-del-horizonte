<?php
// Ficha de postulación del socio: puede guardar avance, enviarla a la directiva y actualizarla cuando quiera.
require __DIR__ . '/../inc/app.php';
require __DIR__ . '/../inc/ficha.php';

$u = require_login();
$ficha = q('SELECT * FROM postulaciones WHERE usuario_id = ?', [$u['id']])->fetch() ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    [$d, $errores] = ficha_validar($_POST);
    if ($errores) {
        flash(implode(' ', $errores) . ' No se guardaron los cambios.', 'error');
        redirect('panel/ficha.php');
    }
    [$docs, $errDocs] = ficha_subir_docs();

    // Una sola fuente de verdad: datos personales en usuarios (los mismos que ve el admin en Socios)…
    $personales = array_intersect_key($d, array_flip(CAMPOS_PERSONALES));
    if ($personales) {
        q('UPDATE usuarios SET ' . implode(' = ?, ', array_keys($personales)) . ' = ? WHERE id = ?', [...array_values($personales), $u['id']]);
    }
    // …y solo lo propio de la postulación en la ficha.
    $campos = array_diff_key($d, $personales) + $docs + ['actualizado' => date('Y-m-d H:i:s')];
    if ($ficha) {
        q('UPDATE postulaciones SET ' . implode(' = ?, ', array_keys($campos)) . ' = ? WHERE id = ?', [...array_values($campos), $ficha['id']]);
        ficha_borrar_archivos(array_intersect_key($ficha, $docs));   // reemplazados
    } else {
        $campos += ['usuario_id' => $u['id'], 'nombre' => $u['nombre'], 'rut' => $u['rut'], 'estado' => 'aceptada'];
        q('INSERT INTO postulaciones (' . implode(',', array_keys($campos)) . ') VALUES (' . rtrim(str_repeat('?,', count($campos)), ',') . ')', array_values($campos));
    }

    $ficha = q('SELECT * FROM postulaciones WHERE usuario_id = ?', [$u['id']])->fetch();
    $faltan = ficha_faltantes(ficha_datos(q('SELECT * FROM usuarios WHERE id = ?', [$u['id']])->fetch(), $ficha));
    $msg = $errDocs ? ' ' . implode(' ', $errDocs) : '';
    if (post('accion') === 'enviar') {
        if ($faltan) {
            flash('Guardamos tu avance, pero para enviarla falta: ' . implode(', ', $faltan) . '.' . $msg, 'error');
        } else {
            q('UPDATE postulaciones SET enviada = NOW() WHERE id = ?', [$ficha['id']]);
            flash('¡Ficha enviada a la directiva!' . $msg, $errDocs ? 'error' : 'ok');
        }
    } else {
        flash('Avance guardado.' . ($ficha['enviada'] ? ' La directiva verá que actualizaste tu ficha.' : '') . $msg, $errDocs ? 'error' : 'ok');
    }
    redirect('panel/ficha.php');
}

$v = ficha_datos($u, $ficha);
$faltan = ficha_faltantes($v);
$docsUrl = [];
foreach (DOCS_FICHA as $col => [$campo]) {
    $docsUrl[$col] = !empty($ficha[$col]) ? url("descargar.php?postulacion={$ficha['id']}&doc=$campo") : null;
}

page_start('Mi ficha de postulación', 'panel/ficha.php');
?>
<div class="stats">
  <div class="stat <?= $ficha && $ficha['enviada'] ? 'bien' : ($faltan ? 'alerta' : '') ?>">
    <span>Estado</span>
    <strong><?= !$ficha ? 'Sin completar' : ($ficha['enviada'] ? 'Enviada' : 'Borrador') ?></strong>
    <small><?php if ($ficha && $ficha['enviada']): ?>
      Enviada el <?= fecha($ficha['enviada']) ?><?= $ficha['actualizado'] > $ficha['enviada'] ? ' · actualizada el ' . fecha($ficha['actualizado']) : '' ?>
    <?php else: ?>Aún no la envías a la directiva<?php endif; ?></small>
  </div>
  <div class="stat <?= $faltan ? 'alerta' : 'bien' ?>">
    <span>Avance</span>
    <strong><?= $faltan ? count($faltan) . ' pendiente' . (count($faltan) > 1 ? 's' : '') : 'Completa' ?></strong>
    <?= $faltan ? badges_faltan($faltan) : '<small>Todo listo</small>' ?>
  </div>
</div>

<form class="card postulacion" method="post" enctype="multipart/form-data">
  <p class="muted">Puedes completarla por partes con <strong>Guardar avance</strong>. Cuando esté completa, usa <strong>Enviar a la directiva</strong>. Si algo cambia (por ejemplo, una cartola RSH nueva), vuelve aquí y actualízala.</p>
  <?= csrf_field() ?>
  <?= ficha_campos($v, $docsUrl) ?>
  <div class="acciones-fila">
    <button name="accion" value="guardar" class="sec">Guardar avance</button>
    <button name="accion" value="enviar" class="sol"><?= $ficha && $ficha['enviada'] ? 'Reenviar actualizada' : 'Enviar a la directiva' ?></button>
  </div>
</form>
<?php page_end();
