<?php
require __DIR__ . '/inc/app.php';

// ---- Pre-postulación (visitantes): solo contacto. La ficha completa la llenan los socios desde su panel. ----
$errores = [];
$enviado = isset($_GET['enviado']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $ip = 'postula:' . ip_cliente();
    if (post('sitio_web') !== '') {           // honeypot: los bots lo rellenan
        redirect('index.php?enviado=1#unete');
    }
    $nombre = post('nombre');
    $telefono = telefono_normalizar(post('telefono'));
    $email = post('email');
    if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 150) $errores[] = 'Escribe tu nombre.';
    if (!$telefono) $errores[] = 'El celular debe tener 8 dígitos después del +56 9.';
    if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150)) $errores[] = 'El correo no es válido.';
    if (mb_strlen(post('mensaje')) > 2000) $errores[] = 'El mensaje es demasiado largo.';
    if (!$errores && limite_superado($ip, 5, 60)) $errores[] = 'Recibimos varias solicitudes desde tu conexión. Intenta más tarde.';
    if (!$errores) {
        registrar_intento($ip);
        q('INSERT INTO postulaciones (nombre, telefono, email, mensaje) VALUES (?,?,?,?)',
            [$nombre, $telefono, $email ?: null, post('mensaje') ?: null]);
        redirect('index.php?enviado=1#unete');
    }
}

// ponytail: sitio público anónimo — sin nombres, RUT, direcciones ni cifras de socios.
$lineas = fn(string $clave, int $partes) => array_filter(array_map(
    fn($l) => array_map('trim', explode('|', $l, $partes)) + array_fill(0, $partes, ''),
    preg_split('/\R/', ajuste($clave))
), fn($h) => $h[0] !== '');
$hitos = $lineas('hitos', 3);
$valores = $lineas('valores', 2);
$avisos = q('SELECT * FROM comunicados WHERE publico = 1 ORDER BY fecha DESC, id DESC LIMIT 6')->fetchAll();

page_start('Comité de Vivienda');
?>
<header class="topbar">
  <a class="brand" href="<?= url() ?>"><?= logo() ?><span>Olas del<br>Horizonte</span></a>
  <nav aria-label="Principal"><ul>
    <li><a href="#historia">Historia</a></li>
    <li><a href="#quienes">Quiénes somos</a></li>
    <?php if ($avisos): ?><li><a href="#avisos">Comunicados</a></li><?php endif; ?>
    <li><a href="#unete">Únete</a></li>
    <li class="siempre"><a class="btn chico" href="<?= url(user() ? 'panel/index.php' : 'login.php') ?>"><?= user() ? 'Mi panel' : 'Ingresar' ?></a></li>
  </ul></nav>
</header>

<section class="hero" aria-labelledby="hero-h">
  <div class="sol-disco" aria-hidden="true"></div>
  <p class="eyebrow"><?= e(ajuste('hero_eyebrow')) ?></p>
  <h1 id="hero-h"><?= e(ajuste('hero_titulo')) ?></h1>
  <p class="bajada"><?= e(ajuste('hero_bajada')) ?></p>
  <div class="acciones">
    <a class="btn sol" href="#unete">Quiero participar</a>
    <a class="btn claro" href="#historia">Conoce nuestra historia</a>
  </div>
  <div class="olas" aria-hidden="true">
    <svg class="o3" viewBox="0 0 2880 180" preserveAspectRatio="none"><path fill="#80bdf2" d="M0 90c240-50 480-50 720 0s480 50 720 0 480-50 720 0 480 50 720 0v90H0z"/></svg>
    <svg class="o2" viewBox="0 0 2880 180" preserveAspectRatio="none"><path fill="#d9cdbf" d="M0 120c180-35 360-35 540 0s360 35 540 0 360-35 540 0 360 35 540 0 360-35 540 0 180 35 180 0v60H0z"/></svg>
    <svg class="o1" viewBox="0 0 2880 180" preserveAspectRatio="none"><path fill="#f5f2ee" d="M0 150c240-22 480-22 720 0s480 22 720 0 480-22 720 0 480 22 720 0v30H0z"/></svg>
  </div>
</section>

<section class="seccion" id="historia" aria-labelledby="historia-h">
  <p class="kicker">Nuestra historia</p>
  <h2 id="historia-h"><?= e(ajuste('historia_titulo')) ?></h2>
  <p class="lead"><?= e(ajuste('historia_intro')) ?></p>
  <ol class="hitos">
    <?php foreach ($hitos as [$f, $t, $d]): ?>
      <li><time><?= e($f) ?></time><div><h3><?= e($t) ?></h3><p><?= e($d) ?></p></div></li>
    <?php endforeach; ?>
  </ol>
</section>

<div class="oscura-wrap">
<section class="seccion oscura" id="quienes" aria-labelledby="quienes-h">
  <p class="kicker">Quiénes somos</p>
  <h2 id="quienes-h"><?= e(ajuste('quienes_titulo')) ?></h2>
  <div class="bento">
    <div class="grande pre"><?= e(ajuste('quienes_texto')) ?></div>
    <?php foreach ($valores as [$t, $d]): ?>
      <div class="valor"><h3><?= e($t) ?></h3><p><?= e($d) ?></p></div>
    <?php endforeach; ?>
  </div>
</section>
</div>

<?php if ($avisos): ?>
<section class="seccion" id="avisos" aria-labelledby="avisos-h">
  <p class="kicker">Comunicados</p>
  <h2 id="avisos-h">Lo último del comité</h2>
  <div class="avisos">
    <?php foreach ($avisos as $a): ?>
      <article class="aviso"><time><?= fecha($a['fecha']) ?></time><h3><?= e($a['titulo']) ?></h3><p class="pre"><?= e($a['cuerpo']) ?></p></article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="seccion" id="unete" aria-labelledby="unete-h">
  <div class="unete">
    <div>
      <p class="kicker">Únete</p>
      <h2 id="unete-h"><?= e(ajuste('unete_titulo')) ?></h2>
      <p class="lead"><?= e(ajuste('unete_texto')) ?></p>
      <ul class="pasos">
        <li>Déjanos tu nombre y un teléfono de contacto.</li>
        <li>La directiva te contacta y te invita a la próxima asamblea.</li>
        <li>Si decides sumarte, te registras como socio y completas tu ficha desde tu cuenta.</li>
      </ul>
    </div>
    <form method="post" action="#unete" novalidate>
      <?= csrf_field() ?>
      <?php if ($enviado): ?>
        <p class="flash" role="status">¡Gracias! Recibimos tus datos y la directiva te contactará pronto.</p>
      <?php endif; ?>
      <?php foreach ($errores as $er): ?><p class="flash error" role="alert"><?= e($er) ?></p><?php endforeach; ?>
      <h3>Pre-postulación</h3>
      <p class="muted nota">Solo necesitamos saber cómo contactarte. No te pediremos documentos en esta etapa.</p>
      <label>Nombre *<input name="nombre" required maxlength="150" autocomplete="name" value="<?= e(post('nombre')) ?>"></label>
      <div class="grid-form">
        <label>Celular (WhatsApp) *<?= campo_telefono(post('telefono'), true) ?></label>
        <label>Correo <small>(opcional)</small><input name="email" type="email" maxlength="150" autocomplete="email" value="<?= e(post('email')) ?>"></label>
      </div>
      <label>¿Algo que quieras contarnos? <small>(opcional)</small><textarea name="mensaje" maxlength="2000" rows="4"><?= e(post('mensaje')) ?></textarea></label>
      <label class="hp" aria-hidden="true">No completar<input name="sitio_web" tabindex="-1" autocomplete="off"></label>
      <button class="sol">Enviar</button>
    </form>
  </div>
</section>

<footer class="pie">
  <a class="brand" href="<?= url() ?>"><?= logo() ?><span>Comité de Vivienda<br>Olas del Horizonte</span></a>
  <div>
    <?php if (ajuste('contacto_email')): ?><a href="mailto:<?= e(ajuste('contacto_email')) ?>"><?= e(ajuste('contacto_email')) ?></a><br><?php endif; ?>
    <?= e(telefono_formato(ajuste('contacto_telefono'))) ?>
  </div>
</footer>
<?php page_end();
