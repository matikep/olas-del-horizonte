<?php
require __DIR__ . '/../inc/app.php';

$u = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('accion') === 'datos') {
        $email = post('email');
        $telefono = telefono_normalizar(post('telefono'));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('El correo no es válido.', 'error');
        } elseif (post('telefono') !== '' && !$telefono) {
            flash('El celular debe tener 8 dígitos después del +56 9.', 'error');
        } else {
            q('UPDATE usuarios SET email = ?, telefono = ?, direccion = ? WHERE id = ?',
                [$email ?: null, $telefono, mb_substr(post('direccion'), 0, 200) ?: null, $u['id']]);
            flash('Datos actualizados.');
        }
    } else {
        $nueva = (string)($_POST['nueva'] ?? '');
        if (!password_verify((string)($_POST['actual'] ?? ''), (string)$u['password_hash'])) {
            flash('La clave actual no es correcta.', 'error');
        } elseif (mb_strlen($nueva) < 8) {
            flash('La nueva clave debe tener al menos 8 caracteres.', 'error');
        } elseif ($nueva !== ($_POST['repetir'] ?? '')) {
            flash('Las claves no coinciden.', 'error');
        } else {
            q('UPDATE usuarios SET password_hash = ? WHERE id = ?', [password_hash($nueva, PASSWORD_DEFAULT), $u['id']]);
            session_regenerate_id(true);
            flash('Clave cambiada.');
        }
    }
    redirect('panel/cuenta.php');
}

page_start('Mi cuenta', 'panel/cuenta.php');
?>
<div class="dos-col">
  <form class="card" method="post">
    <h2>Mis datos</h2>
    <?= csrf_field() ?><input type="hidden" name="accion" value="datos">
    <p class="muted"><?= e($u['nombre']) ?> · RUT <?= rut_formato($u['rut']) ?> · socio desde <?= fecha($u['fecha_ingreso']) ?></p>
    <label>Correo<input name="email" type="email" maxlength="150" value="<?= e($u['email']) ?>"></label>
    <label>Celular (WhatsApp)<?= campo_telefono($u['telefono']) ?></label>
    <label>Dirección<input name="direccion" maxlength="200" value="<?= e($u['direccion']) ?>"></label>
    <button>Guardar</button>
  </form>
  <form class="card" method="post">
    <h2>Cambiar clave</h2>
    <?= csrf_field() ?><input type="hidden" name="accion" value="clave">
    <label>Clave actual<input name="actual" type="password" required autocomplete="current-password"></label>
    <label>Nueva clave <small>(mínimo 8 caracteres)</small><input name="nueva" type="password" required minlength="8" autocomplete="new-password"></label>
    <label>Repetir nueva clave<input name="repetir" type="password" required minlength="8" autocomplete="new-password"></label>
    <button>Cambiar clave</button>
  </form>
</div>
<?php page_end();
