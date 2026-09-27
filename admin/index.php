<?php
require __DIR__ . '/../inc/app.php';
require_once __DIR__ . '/../inc/alertas.php';

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

$alertas = alertas_admin();
$comp = composicion_grupo();
$conFicha = $comp['filas']['chileno']['total'] + $comp['filas']['extranjero']['total'];
page_start('Panel de administración', 'admin/index.php');
?>
<section class="card" aria-labelledby="h-alertas">
  <h2 id="h-alertas">Alertas <span class="muted" style="font-size:.9rem;font-weight:500">se actualizan solas con cada cambio</span></h2>
  <?= $alertas ? alertas_html($alertas) : '<p class="muted">Todo en orden: no hay alertas.</p>' ?>
</section>

<section class="card" aria-labelledby="h-comp">
  <h2 id="h-comp">Composición del grupo <span class="muted" style="font-size:.9rem;font-weight:500">socios activos, según sus fichas</span></h2>
  <div class="dos-col">
    <div class="tabla-wrap"><table class="composicion">
      <thead><tr><th>Nacionalidad</th><th class="num">Socios</th><th class="num">Tramo 40</th><th class="num">Tramo 50–90</th><th class="num">Sobre 90</th><th class="num">Sin revisar</th></tr></thead>
      <tbody><?php foreach ($comp['grupos'] as $k => $nombre): $f = $comp['filas'][$k]; ?>
        <tr><td><?= e($nombre) ?></td><td class="num"><strong><?= $f['total'] ?></strong></td><td class="num"><?= $f['t40'] ?></td><td class="num"><?= $f['t50_90'] ?></td>
          <td class="num"><?= $f['sobre90'] ? '<span class="badge falta">' . $f['sobre90'] . '</span>' : 0 ?></td><td class="num muted"><?= $f['sin_revisar'] ?></td></tr>
      <?php endforeach; ?></tbody>
    </table>
    <p class="muted nota">Extranjeros: <strong><?= $comp['filas']['extranjero']['total'] ?> de <?= $conFicha ?></strong> fichas (<?= $conFicha ? round($comp['filas']['extranjero']['total'] * 100 / $conFicha) : 0 ?>%). El cupo máximo de extranjeros aún no está definido.</p></div>
    <div class="tabla-wrap"><table class="composicion">
      <thead><tr><th>Tramo RSH</th><th class="num">Socios</th><th>Quiénes</th></tr></thead>
      <tbody><?php foreach ($comp['porTramo'] as $t => $socios): ?>
        <tr><td><span class="badge <?= $t === 40 ? 'ok' : ($t <= 90 ? 'mar' : 'falta') ?>">Tramo <?= $t ?></span> <small class="muted"><?= TRAMOS_RSH[$t] ?></small></td><td class="num"><strong><?= count($socios) ?></strong></td>
          <td><?php if ($socios): ?><details><summary><?= count($socios) ?> socio<?= count($socios) > 1 ? 's' : '' ?></summary><?= implode(', ', array_map(fn($id, $n) => '<a href="socios.php?id=' . $id . '">' . e($n) . '</a>', array_keys($socios), $socios)) ?></details><?php else: ?><span class="muted">—</span><?php endif; ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </div>
</section>

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
