<?php
require __DIR__ . '/../inc/app.php';

require_admin();
$socios = socios_con_pagos();
$recaudado = (int)q('SELECT COALESCE(SUM(monto),0) FROM pagos')->fetchColumn();
$gastado = (int)q('SELECT COALESCE(SUM(monto),0) FROM gastos')->fetchColumn();
$morosos = array_filter($socios, fn($s) => $s['cuota']['saldo'] < 0);
usort($morosos, fn($a, $b) => $a['cuota']['saldo'] <=> $b['cuota']['saldo']);
$porCobrar = -array_sum(array_map(fn($s) => $s['cuota']['saldo'], $morosos));
$nuevas = q("SELECT * FROM postulaciones WHERE estado = 'nueva' AND usuario_id IS NULL ORDER BY creado DESC")->fetchAll();
$ultima = q("SELECT r.*, (SELECT COUNT(*) FROM asistencias a WHERE a.reunion_id = r.id AND a.estado <> 'justificado') AS n FROM reuniones r ORDER BY fecha DESC LIMIT 1")->fetch();
$sinClave = count(array_filter($socios, fn($s) => !$s['password_hash']));

page_start('Panel de administración', 'admin/index.php');
?>
<div class="stats">
  <div class="stat destacado"><span>Saldo en caja</span><strong><?= clp($recaudado - $gastado) ?></strong><small>Recaudado <?= clp($recaudado) ?> − gastos <?= clp($gastado) ?></small></div>
  <div class="stat"><span>Socios activos</span><strong><?= count($socios) ?></strong><small><?= $sinClave ?> sin clave de acceso</small></div>
  <div class="stat alerta"><span>Cuotas por cobrar</span><strong><?= clp($porCobrar) ?></strong><small><?= count($morosos) ?> socios con deuda</small></div>
  <div class="stat <?= $nuevas ? 'alerta' : 'bien' ?>"><span>Pre-postulaciones nuevas</span><strong><?= count($nuevas) ?></strong><small><a href="postulaciones.php">Revisar</a></small></div>
</div>

<div class="dos-col">
  <section class="card">
    <h2>Mayores deudas <a class="btn chico sec" href="tesoreria.php#pago">Registrar pago</a></h2>
    <div class="tabla-wrap"><table>
      <thead><tr><th>Socio</th><th class="num">Debe</th><th class="num">Meses</th></tr></thead>
      <tbody><?php foreach (array_slice($morosos, 0, 10) as $s): ?>
        <tr><td><a href="socios.php?id=<?= $s['id'] ?>"><?= e($s['nombre']) ?></a></td><td class="num"><?= clp(-$s['cuota']['saldo']) ?></td><td class="num"><?= $s['cuota']['meses_deuda'] ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </section>
  <div>
    <?php if ($ultima): ?>
    <section class="card">
      <h2>Última reunión</h2>
      <p><strong><?= e($ultima['titulo']) ?></strong><br><span class="muted"><?= fecha($ultima['fecha']) ?> · <?= e($ultima['lugar']) ?></span></p>
      <p><?= (int)$ultima['n'] ?> asistentes · <a href="reuniones.php?id=<?= $ultima['id'] ?>">editar asistencia</a></p>
    </section>
    <?php endif; ?>
    <section class="card">
      <h2>Pre-postulaciones sin revisar</h2>
      <?php if (!$nuevas): ?><p class="muted">No hay pre-postulaciones nuevas.</p><?php endif; ?>
      <?php foreach (array_slice($nuevas, 0, 5) as $p): ?>
        <p><strong><?= e($p['nombre']) ?></strong><br><span class="muted"><?= e($p['telefono'] ? telefono_formato($p['telefono']) : $p['email']) ?> · <?= fecha($p['creado']) ?></span></p>
      <?php endforeach; ?>
    </section>
  </div>
</div>
<?php page_end();
