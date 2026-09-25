<?php
require __DIR__ . '/inc/app.php';

if (user()) {
    redirect(user()['rol'] === 'admin' ? 'admin/index.php' : 'panel/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $clave = 'login:' . ip_cliente();
    $rut = rut_normalizar(post('rut'));
    if (limite_superado($clave, 8, 15)) {
        $error = 'Demasiados intentos. Espera 15 minutos e inténtalo de nuevo.';
    } else {
        $u = $rut ? q('SELECT * FROM usuarios WHERE rut = ? AND activo = 1', [$rut])->fetch() : null;
        if ($u && $u['password_hash'] && password_verify((string)($_POST['password'] ?? ''), $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['uid'] = (int)$u['id'];
            redirect($u['rol'] === 'admin' ? 'admin/index.php' : 'panel/index.php');
        }
        registrar_intento($clave);
        $error = 'RUT o clave incorrectos. Si aún no tienes clave, pídela a la directiva.';
    }
}

page_start('Ingresar');
?>
<div class="login-wrap">
  <form class="login-card" method="post">
    <a class="brand" href="<?= url() ?>"><?= logo() ?><span>Olas del<br>Horizonte</span></a>
    <h1 style="font-size:1.8rem">Ingreso de socios</h1>
    <?php if ($error): ?><p class="flash error" role="alert"><?= e($error) ?></p><?php endif; ?>
    <?= csrf_field() ?>
    <label>RUT<input name="rut" required autocomplete="username" <?= RUT_INPUT ?> value="<?= e(($r = rut_normalizar(post('rut'))) ? rut_formato($r) : post('rut')) ?>"></label>
    <label>Clave<input name="password" type="password" required autocomplete="current-password"></label>
    <button style="width:100%;justify-content:center">Ingresar</button>
    <p class="muted" style="font-size:.9rem;margin-top:1.2rem"><a href="<?= url() ?>">← Volver al sitio</a></p>
  </form>
</div>
<?php page_end();
