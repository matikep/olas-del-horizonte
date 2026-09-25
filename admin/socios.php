<?php
require __DIR__ . '/../inc/app.php';
require __DIR__ . '/../inc/ficha.php';

$yo = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)post('id');

    if (post('accion') === 'eliminar') {
        // Triple confirmación: (1) nombre exacto, (2) clave del admin, (3) confirm() en el navegador.
        $socio = q('SELECT * FROM usuarios WHERE id = ?', [$id])->fetch();
        $n = (int)q('SELECT COUNT(*) FROM pagos WHERE usuario_id = ?', [$id])->fetchColumn();
        $mismoNombre = $socio && mb_strtolower(trim(preg_replace('/\s+/', ' ', post('confirmar_nombre')))) === mb_strtolower(trim(preg_replace('/\s+/', ' ', $socio['nombre'])));
        if (!$socio || $id === (int)$yo['id']) {
            flash('No puedes eliminar este socio.', 'error');
        } elseif ($n > 0) {
            flash('Este socio tiene pagos registrados: márcalo como inactivo en vez de eliminarlo.', 'error');
        } elseif (!$mismoNombre) {
            flash('El nombre escrito no coincide con el del socio. No se eliminó.', 'error');
        } elseif (!password_verify((string)($_POST['confirmar_clave'] ?? ''), (string)$yo['password_hash'])) {
            flash('Tu clave de administrador no es correcta. No se eliminó.', 'error');
        } else {
            $ficha = q('SELECT * FROM postulaciones WHERE usuario_id = ?', [$id])->fetch();
            if ($ficha) {
                q('DELETE FROM postulaciones WHERE id = ?', [$ficha['id']]);
                ficha_borrar_archivos(array_intersect_key($ficha, DOCS_FICHA));
            }
            q('DELETE FROM usuarios WHERE id = ?', [$id]);
            flash('Socio "' . $socio['nombre'] . '" eliminado definitivamente.');
            redirect('admin/socios.php');
        }
        redirect("admin/socios.php?id=$id");
    }

    // Guardar (crear o editar)
    $d = [
        'nombre' => post('nombre'),
        'rut' => post('rut') === '' ? null : rut_normalizar(post('rut')),
        'email' => post('email') ?: null,
        'telefono' => telefono_normalizar(post('telefono')),
        'direccion' => mb_substr(post('direccion'), 0, 200) ?: null,
        'rol' => post('rol') === 'admin' ? 'admin' : 'miembro',
        'cargo' => in_array(post('cargo'), CARGOS, true) ? post('cargo') : null,
        'fecha_ingreso' => post('fecha_ingreso'),
        'activo' => isset($_POST['activo']) ? 1 : 0,
    ];
    // Baja: al desactivar se guarda la fecha (hoy si no se indica); al reactivar se borra.
    $d['fecha_baja'] = $d['activo'] ? null : (post('fecha_baja') ?: date('Y-m-d'));
    $clave = (string)($_POST['clave'] ?? '');
    $err = [];
    if (mb_strlen($d['nombre']) < 3) $err[] = 'Nombre requerido.';
    if (post('rut') !== '' && !$d['rut']) $err[] = 'RUT inválido.';
    if ($d['rut'] && q('SELECT id FROM usuarios WHERE rut = ? AND id <> ?', [$d['rut'], $id])->fetch()) $err[] = 'Ya existe un socio con ese RUT.';
    if ($d['email'] && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Correo inválido.';
    if (post('telefono') !== '' && !$d['telefono']) $err[] = 'El celular debe tener 8 dígitos después del +56 9.';
    if (!fecha_valida($d['fecha_ingreso'])) $err[] = 'Fecha de ingreso inválida.';
    if ($d['fecha_baja'] && (!fecha_valida($d['fecha_baja']) || $d['fecha_baja'] < $d['fecha_ingreso'])) $err[] = 'La fecha de baja debe ser válida y posterior al ingreso.';
    if ($clave !== '' && mb_strlen($clave) < 8) $err[] = 'La clave debe tener al menos 8 caracteres.';
    if ($clave !== '' && !$d['rut']) $err[] = 'Para dar acceso, el socio necesita RUT (es su usuario).';
    if ($id === (int)$yo['id'] && ($d['rol'] !== 'admin' || !$d['activo'])) $err[] = 'No puedes quitarte el rol de admin ni desactivarte.';
    if ($err) {
        flash(implode(' ', $err), 'error');
        redirect('admin/socios.php?' . ($id ? "id=$id" : 'nuevo=1'));
    }

    $cols = array_keys($d);
    if ($id) {
        q('UPDATE usuarios SET ' . implode(' = ?, ', $cols) . ' = ? WHERE id = ?', [...array_values($d), $id]);
    } else {
        q('INSERT INTO usuarios (' . implode(',', $cols) . ') VALUES (' . rtrim(str_repeat('?,', count($cols)), ',') . ')', array_values($d));
        $id = (int)db()->lastInsertId();
    }
    if ($clave !== '') {
        q('UPDATE usuarios SET password_hash = ? WHERE id = ?', [password_hash($clave, PASSWORD_DEFAULT), $id]);
    }
    flash('Socio guardado.' . ($clave !== '' ? ' Clave asignada: entrégala al socio para que ingrese con su RUT.' : ''));
    redirect("admin/socios.php?id=$id");
}

// ---------- Formulario ----------
if (isset($_GET['id']) || isset($_GET['nuevo'])) {
    $s = isset($_GET['id']) ? q('SELECT * FROM usuarios WHERE id = ?', [(int)$_GET['id']])->fetch() : null;
    if (isset($_GET['id']) && !$s) redirect('admin/socios.php');
    $s ??= ['id' => 0, 'nombre' => '', 'rut' => '', 'email' => '', 'telefono' => '', 'direccion' => '', 'rol' => 'miembro', 'cargo' => '', 'fecha_ingreso' => date('Y-m-d'), 'activo' => 1, 'fecha_baja' => null, 'password_hash' => null];
    $pagos = $s['id'] ? q('SELECT * FROM pagos WHERE usuario_id = ? ORDER BY fecha DESC', [$s['id']])->fetchAll() : [];
    $ficha = $s['id'] ? q('SELECT * FROM postulaciones WHERE usuario_id = ?', [$s['id']])->fetch() ?: null : null;
    page_start($s['id'] ? $s['nombre'] : 'Nuevo socio', 'admin/socios.php');
    ?>
    <p><a href="socios.php">← Volver a socios</a></p>
    <div class="dos-col">
      <form class="card" method="post">
        <h2>Datos del socio</h2>
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>">
        <label>Nombre completo<input name="nombre" required maxlength="150" value="<?= e($s['nombre']) ?>"></label>
        <div class="grid-form">
          <label>RUT<input name="rut" autocomplete="off" <?= RUT_INPUT ?> value="<?= e($s['rut'] ? rut_formato($s['rut']) : '') ?>"></label>
          <label>Fecha de ingreso<input name="fecha_ingreso" type="date" required value="<?= e($s['fecha_ingreso']) ?>"></label>
          <label>Correo<input name="email" type="email" maxlength="150" value="<?= e($s['email']) ?>"></label>
          <label>Celular (WhatsApp)<?= campo_telefono($s['telefono']) ?></label>
        </div>
        <label>Dirección<input name="direccion" maxlength="200" value="<?= e($s['direccion']) ?>"></label>
        <div class="grid-form">
          <label>Cargo en directiva<select name="cargo"><option value="">— Ninguno —</option>
            <?php foreach (CARGOS as $c): ?><option <?= $s['cargo'] === $c ? 'selected' : '' ?>><?= $c ?></option><?php endforeach; ?>
          </select></label>
          <label>Perfil<select name="rol">
            <option value="miembro">Miembro</option>
            <option value="admin" <?= $s['rol'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
          </select></label>
        </div>
        <label>Clave de acceso <small><?= $s['password_hash'] ? '(tiene clave; escribe una nueva solo para cambiarla)' : '(sin acceso aún; asigna una para que pueda ingresar)' ?></small>
          <input name="clave" type="text" minlength="8" autocomplete="off" placeholder="Mínimo 8 caracteres"></label>
        <label class="check"><input type="checkbox" name="activo" id="chk-activo" <?= $s['activo'] ? 'checked' : '' ?>> Socio activo</label>
        <label id="campo-baja" <?= $s['activo'] ? 'hidden' : '' ?>>Fecha de baja
          <input type="date" name="fecha_baja" value="<?= e($s['fecha_baja'] ?? date('Y-m-d')) ?>">
          <small>Desde esta fecha dejan de contarse sus cuotas (el mes de la baja se cobra). Lo que debía hasta ese momento se mantiene.</small></label>
        <script>
          document.getElementById('chk-activo').addEventListener('change', e => document.getElementById('campo-baja').hidden = e.target.checked);
        </script>
        <button>Guardar</button>
      </form>
      <?php if ($s['id']): $c = estado_cuota($s['fecha_ingreso'], (int)array_sum(array_column($pagos, 'monto')), $s['fecha_baja']); ?>
      <section class="card">
        <h2>Cuotas <a class="btn chico sec" href="tesoreria.php?socio=<?= $s['id'] ?>#pago">Registrar pago</a></h2>
        <p><?= badge_cuota($c) ?></p>
        <p class="muted">Pagado <?= clp($c['pagado']) ?> de <?= clp($c['esperado']) ?> esperado (<?= $c['meses'] ?> meses).</p>
        <?php if (!$s['activo'] && $s['fecha_baja']): ?><p class="muted">Dado de baja el <?= fecha($s['fecha_baja']) ?>: las cuotas se calculan solo hasta ese mes.</p><?php endif; ?>
        <div class="tabla-wrap"><table>
          <tbody><?php foreach ($pagos as $p): ?><tr><td><?= fecha($p['fecha']) ?></td><td class="muted"><?= e($p['observacion']) ?></td><td class="num"><?= clp($p['monto']) ?></td></tr><?php endforeach; ?></tbody>
        </table></div>
        <?php if (!$pagos && $s['id'] !== (int)$yo['id']): ?>
        <details class="zona-peligro">
          <summary>Eliminar socio…</summary>
          <form method="post" id="form-eliminar">
            <p><strong>Esto borra definitivamente al socio, su asistencia y su ficha con documentos.</strong> No se puede deshacer. Si solo dejó de participar, mejor desmárcalo como activo.</p>
            <?= csrf_field() ?><input type="hidden" name="id" value="<?= $s['id'] ?>"><input type="hidden" name="accion" value="eliminar">
            <label>1. Escribe su nombre completo para confirmar: <em><?= e($s['nombre']) ?></em>
              <input name="confirmar_nombre" required autocomplete="off" data-esperado="<?= e(mb_strtolower($s['nombre'])) ?>"></label>
            <label>2. Ingresa tu clave de administrador<input name="confirmar_clave" type="password" required autocomplete="current-password"></label>
            <button class="peligro" disabled>3. Eliminar definitivamente</button>
          </form>
          <script>
            (() => {
              const f = document.getElementById('form-eliminar'), n = f.confirmar_nombre, c = f.confirmar_clave, b = f.querySelector('button');
              const norm = s => s.trim().replace(/\s+/g, ' ').toLowerCase();
              const revisar = () => b.disabled = norm(n.value) !== norm(n.dataset.esperado) || !c.value;
              n.addEventListener('input', revisar); c.addEventListener('input', revisar);
              f.addEventListener('submit', e => {
                if (!confirm('Última confirmación: ¿eliminar DEFINITIVAMENTE a ' + <?= json_encode($s['nombre'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?> + '?')) e.preventDefault();
              });
            })();
          </script>
        </details>
        <?php endif; ?>
      </section>
      <?php endif; ?>
    </div>
    <?php if ($s['id']): ?>
    <section class="card">
      <h2>Ficha de postulación <?= badge_ficha($ficha) ?></h2>
      <p class="muted">Nombre, RUT, dirección, teléfono y correo son los mismos datos del formulario de arriba: si los corriges ahí, la ficha se actualiza.</p>
      <?php if (!$ficha): ?><p class="muted">El socio aún no completa su ficha. Puede hacerlo desde su panel, en "Mi ficha de postulación".</p>
      <?php else: $fv = ficha_datos($s, $ficha); $faltan = ficha_faltantes($fv); ?>
        <div class="tabla-wrap"><table class="ficha"><tbody>
          <?php foreach ([
              'RUT / Pasaporte' => rut_formato($fv['rut']),
              'Nombres y apellidos' => $fv['nombre'],
              'Dirección actual' => $fv['direccion'],
              'Nacionalidad' => NACIONALIDADES[$fv['nacionalidad']] ?? null,
              'Estado civil' => $fv['estado_civil'],
              'Celular (WhatsApp)' => $fv['telefono'] ? telefono_formato($fv['telefono']) : null,
              'Correo' => $fv['email'],
              'Formato de postulación' => FORMATOS[$fv['formato']] ?? null,
              'Causal de excepción' => $fv['formato'] === 'unipersonal' ? (CAUSALES[$fv['causal']] ?? null) : 'No aplica',
              'Cuenta de ahorro vivienda' => !empty($fv['tiene_ahorro']) ? 'Declara tenerla creada (buena fe)' : null,
          ] as $etq => $valor): ?>
            <tr><th scope="row"><?= $etq ?></th><td><?= $valor ? e($valor) : '<span class="badge falta">Falta</span>' ?></td></tr>
          <?php endforeach; ?>
        </tbody></table></div>
        <?php if ($faltan): ?><p><strong>Pendiente:</strong> <?= badges_faltan($faltan) ?></p><?php endif; ?>
        <p class="acciones-fila">
          <?php foreach (DOCS_FICHA as $col => [$campo, $nombreDoc]): if ($ficha[$col]): ?>
            <a class="btn chico sec" href="<?= url("descargar.php?postulacion={$ficha['id']}&doc=$campo") ?>" target="_blank" rel="noopener"><?= $nombreDoc ?></a>
          <?php endif; endforeach; ?>
        </p>
      <?php endif; ?>
    </section>
    <?php endif; ?>
    <?php
    page_end();
    exit;
}

// ---------- Listado ----------
$todos = isset($_GET['todos']);
$socios = socios_con_pagos(!$todos);
$fichas = [];
foreach (q('SELECT * FROM postulaciones WHERE usuario_id IS NOT NULL')->fetchAll() as $f) $fichas[$f['usuario_id']] = $f;
page_start('Socios', 'admin/socios.php');
?>
<section class="card">
  <h2><?= count($socios) ?> socios <?= $todos ? '' : 'activos' ?>
    <span class="acciones-fila">
      <a class="btn chico sec" href="?<?= $todos ? '' : 'todos=1' ?>"><?= $todos ? 'Solo activos' : 'Ver también inactivos' ?></a>
      <a class="btn chico" href="?nuevo=1">+ Nuevo socio</a>
    </span>
  </h2>
  <div class="tabla-wrap"><table>
    <thead><tr><th>Nombre</th><th>RUT</th><th>Cargo</th><th>Cuotas</th><th>Ficha</th><th>Acceso</th><th></th></tr></thead>
    <tbody><?php foreach ($socios as $s): ?>
      <tr>
        <td><a href="?id=<?= $s['id'] ?>"><?= e($s['nombre']) ?></a><?= $s['activo'] ? '' : ' <span class="badge neutro">de baja' . ($s['fecha_baja'] ? ' desde ' . fecha($s['fecha_baja']) : '') . '</span>' ?></td>
        <td class="num"><?= rut_formato($s['rut']) ?></td>
        <td><?= e($s['cargo'] ?? '') ?></td>
        <td><?= badge_cuota($s['cuota']) ?></td>
        <td><?= badge_ficha($fichas[$s['id']] ?? null) ?></td>
        <td><?= $s['rol'] === 'admin' ? '<span class="badge mar">Admin</span>' : ($s['password_hash'] ? '<span class="badge ok">Miembro</span>' : '<span class="badge neutro">Sin clave</span>') ?></td>
        <td class="acc"><a class="btn chico sec" href="?id=<?= $s['id'] ?>">Editar</a></td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>
<?php page_end();
