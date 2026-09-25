<?php
require __DIR__ . '/../inc/app.php';

require_admin();
const FORMAS_PAGO = ['Efectivo', 'Transferencia', 'Otro'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $monto = (int)preg_replace('/\D/', '', post('monto'));
    $fecha = post('fecha');
    switch (post('accion')) {
        case 'pago':
            $socio = (int)post('usuario_id');
            if (!$socio || $monto <= 0 || !fecha_valida($fecha)) {
                flash('Revisa socio, fecha y monto.', 'error');
                break;
            }
            q('INSERT INTO pagos (usuario_id, fecha, monto, forma_pago, observacion) VALUES (?,?,?,?,?)', [
                $socio, $fecha, $monto,
                in_array(post('forma_pago'), FORMAS_PAGO, true) ? post('forma_pago') : null,
                mb_substr(post('observacion'), 0, 255) ?: null,
            ]);
            flash('Pago de ' . clp($monto) . ' registrado.');
            break;
        case 'gasto':
            if (mb_strlen(post('concepto')) < 2 || $monto <= 0 || !fecha_valida($fecha)) {
                flash('Revisa concepto, fecha y monto.', 'error');
                break;
            }
            q('INSERT INTO gastos (fecha, concepto, monto) VALUES (?,?,?)', [$fecha, mb_substr(post('concepto'), 0, 200), $monto]);
            flash('Gasto registrado.');
            break;
        case 'borrar_pago':
            q('DELETE FROM pagos WHERE id = ?', [(int)post('id')]);
            flash('Pago eliminado.');
            break;
        case 'borrar_gasto':
            q('DELETE FROM gastos WHERE id = ?', [(int)post('id')]);
            flash('Gasto eliminado.');
            break;
        case 'cuota':
            $cuota = (int)preg_replace('/\D/', '', post('cuota_mensual'));
            if ($cuota <= 0 || !fecha_valida(post('inicio_cobro'))) {
                flash('Cuota o fecha inválida.', 'error');
                break;
            }
            $st = db()->prepare('INSERT INTO ajustes (clave, valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)');
            $st->execute(['cuota_mensual', (string)$cuota]);
            $st->execute(['inicio_cobro', post('inicio_cobro')]);
            $st->execute(['datos_pago', mb_substr(post('datos_pago'), 0, 1000)]);
            flash('Configuración de cuota guardada.');
            break;
    }
    redirect('admin/tesoreria.php' . (post('volver') ? '?socio=' . (int)post('volver') : ''));
}

$filtro = (int)($_GET['socio'] ?? 0);
$socios = socios_con_pagos(false);
$recaudado = (int)q('SELECT COALESCE(SUM(monto),0) FROM pagos')->fetchColumn();
$gastos = q('SELECT * FROM gastos ORDER BY fecha DESC, id DESC')->fetchAll();
$gastado = (int)array_sum(array_column($gastos, 'monto'));
$pagos = q('SELECT p.*, u.nombre FROM pagos p JOIN usuarios u ON u.id = p.usuario_id' . ($filtro ? ' WHERE p.usuario_id = ?' : '') . ' ORDER BY p.fecha DESC, p.id DESC', $filtro ? [$filtro] : [])->fetchAll();

page_start('Tesorería', 'admin/tesoreria.php');
?>
<div class="stats">
  <div class="stat destacado"><span>Saldo en caja</span><strong><?= clp($recaudado - $gastado) ?></strong></div>
  <div class="stat bien"><span>Total recaudado</span><strong><?= clp($recaudado) ?></strong></div>
  <div class="stat alerta"><span>Total gastos</span><strong><?= clp($gastado) ?></strong></div>
  <div class="stat"><span>Cuota mensual</span><strong><?= clp((int)ajuste('cuota_mensual')) ?></strong><small>desde <?= fecha(ajuste('inicio_cobro')) ?></small></div>
</div>

<div class="dos-col">
  <form class="card" method="post" id="pago">
    <h2>Registrar pago de cuota</h2>
    <?= csrf_field() ?><input type="hidden" name="accion" value="pago"><input type="hidden" name="volver" value="<?= $filtro ?: '' ?>">
    <label>Socio<select name="usuario_id" required><option value="">Selecciona…</option>
      <?php foreach ($socios as $s): if (!$s['activo'] && $s['id'] != $filtro) continue; ?>
        <option value="<?= $s['id'] ?>" <?= $s['id'] == $filtro ? 'selected' : '' ?>><?= e($s['nombre']) ?></option>
      <?php endforeach; ?>
    </select></label>
    <div class="grid-form">
      <label>Fecha<input type="date" name="fecha" required value="<?= date('Y-m-d') ?>"></label>
      <label>Monto ($)<input name="monto" inputmode="numeric" required placeholder="3000"></label>
      <label>Forma de pago<select name="forma_pago"><?php foreach (FORMAS_PAGO as $f): ?><option><?= $f ?></option><?php endforeach; ?></select></label>
    </div>
    <label>Observación <small>(ej. "marzo a junio 2026")</small><input name="observacion" maxlength="255"></label>
    <button>Registrar pago</button>
  </form>

  <form class="card" method="post" id="gasto">
    <h2>Registrar gasto</h2>
    <?= csrf_field() ?><input type="hidden" name="accion" value="gasto">
    <label>Concepto<input name="concepto" required maxlength="200" placeholder="Pago a sede por reunión"></label>
    <div class="grid-form">
      <label>Fecha<input type="date" name="fecha" required value="<?= date('Y-m-d') ?>"></label>
      <label>Monto ($)<input name="monto" inputmode="numeric" required></label>
    </div>
    <button>Registrar gasto</button>
    <details style="margin-top:1.5rem">
      <summary>Configurar cuota y datos de pago</summary>
      <div class="grid-form" style="margin-top:1rem">
        <label>Cuota ($)<input name="cuota_mensual" form="f-cuota" inputmode="numeric" value="<?= e(ajuste('cuota_mensual')) ?>"></label>
        <label>Inicio del cobro<input type="date" name="inicio_cobro" form="f-cuota" value="<?= e(ajuste('inicio_cobro')) ?>"></label>
      </div>
      <label>Datos para pagar <small>(se muestran solo a los socios en su panel)</small><textarea name="datos_pago" form="f-cuota" rows="4"><?= e(ajuste('datos_pago')) ?></textarea></label>
      <button form="f-cuota" class="sec">Guardar cuota</button>
    </details>
  </form>
  <form id="f-cuota" method="post" hidden><?= csrf_field() ?><input type="hidden" name="accion" value="cuota"></form>
</div>

<section class="card" id="estado">
  <h2>Estado de cuotas por socio</h2>
  <div class="tabla-wrap"><table>
    <thead><tr><th>Socio</th><th class="num">Pagado</th><th class="num">Esperado</th><th>Estado</th><th></th></tr></thead>
    <tbody><?php foreach ($socios as $s): if (!$s['activo'] && $s['cuota']['saldo'] >= 0) continue;   // de baja: solo si quedó debiendo ?>
      <tr><td><?= e($s['nombre']) ?><?= $s['activo'] ? '' : ' <span class="badge neutro">de baja desde ' . fecha($s['fecha_baja']) . '</span>' ?></td><td class="num"><?= clp($s['cuota']['pagado']) ?></td><td class="num"><?= clp($s['cuota']['esperado']) ?></td>
      <td><?= badge_cuota($s['cuota']) ?></td><td class="acc"><a class="btn chico sec" href="?socio=<?= $s['id'] ?>#pagos">Ver pagos</a></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>

<div class="dos-col">
  <section class="card" id="pagos">
    <h2>Pagos <?= $filtro ? '· ' . e(array_column($socios, 'nombre', 'id')[$filtro] ?? '') . ' <a class="btn chico sec" href="tesoreria.php#pagos">Ver todos</a>' : '' ?></h2>
    <div class="tabla-wrap"><table>
      <thead><tr><th>Fecha</th><th>Socio</th><th class="num">Monto</th><th></th></tr></thead>
      <tbody><?php foreach ($pagos as $p): ?>
        <tr><td><?= fecha($p['fecha']) ?></td><td><?= e($p['nombre']) ?><br><small class="muted"><?= e(trim(($p['forma_pago'] ?? '') . ' ' . ($p['observacion'] ?? ''))) ?></small></td><td class="num"><?= clp($p['monto']) ?></td>
        <td class="acc"><form method="post" onsubmit="return confirm('¿Eliminar este pago?')"><?= csrf_field() ?><input type="hidden" name="accion" value="borrar_pago"><input type="hidden" name="id" value="<?= $p['id'] ?>"><input type="hidden" name="volver" value="<?= $filtro ?: '' ?>"><button class="peligro chico" aria-label="Eliminar pago">×</button></form></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </section>
  <section class="card">
    <h2>Gastos</h2>
    <div class="tabla-wrap"><table>
      <thead><tr><th>Fecha</th><th>Concepto</th><th class="num">Monto</th><th></th></tr></thead>
      <tbody><?php foreach ($gastos as $g): ?>
        <tr><td><?= fecha($g['fecha']) ?></td><td><?= e($g['concepto']) ?></td><td class="num"><?= clp($g['monto']) ?></td>
        <td class="acc"><form method="post" onsubmit="return confirm('¿Eliminar este gasto?')"><?= csrf_field() ?><input type="hidden" name="accion" value="borrar_gasto"><input type="hidden" name="id" value="<?= $g['id'] ?>"><button class="peligro chico" aria-label="Eliminar gasto">×</button></form></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </section>
</div>
<?php page_end();
