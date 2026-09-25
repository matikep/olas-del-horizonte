<?php
// Crea la clave del primer administrador. Se desactiva solo cuando ya existe un admin con clave.
// Bórralo del servidor después de usarlo.
require __DIR__ . '/inc/app.php';

if (q("SELECT COUNT(*) FROM usuarios WHERE rol = 'admin' AND password_hash IS NOT NULL")->fetchColumn() > 0) {
    exit('La instalación ya se completó. Borra instalar.php del servidor.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $rut = rut_normalizar(post('rut'));
    $pass = (string)($_POST['password'] ?? '');
    if (!$rut) {
        $error = 'RUT inválido.';
    } elseif (mb_strlen($pass) < 8) {
        $error = 'La clave debe tener al menos 8 caracteres.';
    } else {
        $existe = q('SELECT id FROM usuarios WHERE rut = ?', [$rut])->fetchColumn();
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        if ($existe) {
            q("UPDATE usuarios SET rol = 'admin', password_hash = ?, activo = 1 WHERE id = ?", [$hash, $existe]);
        } else {
            $nombre = post('nombre') ?: 'Administrador';
            q("INSERT INTO usuarios (rut, nombre, rol, fecha_ingreso, password_hash) VALUES (?,?, 'admin', CURDATE(), ?)", [$rut, $nombre, $hash]);
        }
        flash('Administrador listo. Ingresa con tu RUT y clave, y borra instalar.php del servidor.');
        redirect('login.php');
    }
}

page_start('Instalación');
?>
<div class="login-wrap">
  <form class="login-card" method="post">
    <h1 style="font-size:1.8rem">Crear administrador</h1>
    <p class="muted">Si tu RUT ya está en el registro de socios, se convierte en administrador. Si no, se crea uno nuevo.</p>
    <?php if ($error): ?><p class="flash error"><?= e($error) ?></p><?php endif; ?>
    <?= csrf_field() ?>
    <label>RUT<input name="rut" required autocomplete="off" <?= RUT_INPUT ?>></label>
    <label>Nombre <small>(solo si no estás en el registro)</small><input name="nombre"></label>
    <label>Clave <small>(mínimo 8 caracteres)</small><input name="password" type="password" required minlength="8"></label>
    <button>Crear</button>
  </form>
</div>
<?php page_end();
