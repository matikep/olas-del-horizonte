<?php
require __DIR__ . '/../inc/app.php';

$u = require_login();
$pagos = q('SELECT * FROM pagos WHERE usuario_id = ? ORDER BY fecha DESC, id DESC', [$u['id']])->fetchAll();
$c = estado_cuota($u['fecha_ingreso'], (int)array_sum(array_column($pagos, 'monto')), $u['fecha_baja']);
// Reuniones sin lista de asistencia cargada (ej. online sin registro) no cuentan como ausencia.
// estado NULL = ausente. Reuniones sin lista cargada no cuentan; las justificadas no penalizan.
$reuniones = q('SELECT r.*, a.estado,
                       EXISTS(SELECT 1 FROM asistencias x WHERE x.reunion_id = r.id) AS con_registro
                FROM reuniones r LEFT JOIN asistencias a ON a.reunion_id = r.id AND a.usuario_id = ?
                WHERE r.fecha >= ? ORDER BY r.fecha DESC', [$u['id'], $u['fecha_ingreso']])->fetchAll();
$conRegistro = array_filter($reuniones, fn($r) => $r['con_registro']);
$asistidas = count(array_filter($conRegistro, fn($r) => in_array($r['estado'], ['presente', 'representante'], true)));
$justificadas = count(array_filter($conRegistro, fn($r) => $r['estado'] === 'justificado'));
$base = count($conRegistro) - $justificadas;
$pct = $base > 0 ? (int)round($asistidas * 100 / $base) : 0;
$justAnio = (int)(justificaciones_anio((int)date('Y'))[$u['id']] ?? 0);
$avisos = q('SELECT * FROM comunicados ORDER BY fecha DESC, id DESC LIMIT 5')->fetchAll();
$docs = q('SELECT * FROM documentos WHERE solo_admin = 0 ORDER BY fecha DESC, id DESC')->fetchAll();

page_start('Hola, ' . explode(' ', $u['nombre'])[0], 'panel/index.php');
?>
<div class="stats">
  <div class="stat destacado">
    <span>Mis cuotas</span>
    <strong><?= $c['saldo'] >= 0 ? 'Al día' : 'Debes ' . clp(-$c['saldo']) ?></strong>
    <small><?= $c['saldo'] > 0 ? 'Saldo a favor ' . clp($c['saldo']) : ($c['saldo'] < 0 ? $c['meses_deuda'] . ' cuota(s) pendiente(s)' : '¡Gracias por tu aporte!') ?></small>
  </div>
  <div class="stat"><span>Total aportado</span><strong><?= clp($c['pagado']) ?></strong><small>de <?= clp($c['esperado']) ?> esperado (<?= $c['meses'] ?> meses × <?= clp((int)ajuste('cuota_mensual')) ?>)</small></div>
  <div class="stat <?= $pct >= 75 ? 'bien' : ($pct < 50 ? 'alerta' : '') ?>">
    <span>Mi asistencia</span><strong><?= $pct ?>%</strong>
    <div class="barra"><i style="width:<?= $pct ?>%"></i></div>
    <small><?= $asistidas ?> de <?= $base ?> reuniones<?= $justificadas ? ' · ' . $justificadas . ' justificada' . ($justificadas > 1 ? 's' : '') : '' ?></small>
    <small class="just-anio">Justificaciones <?= date('Y') ?>: <span class="badge <?= $justAnio > MAX_JUSTIFICACIONES ? 'falta' : 'neutro' ?>"><?= $justAnio ?> de <?= MAX_JUSTIFICACIONES ?></span></small>
  </div>
</div>

<?php if ($c['meses_deuda'] > MESES_MORA): ?>
  <p class="flash error" role="alert">Tienes <?= $c['meses_deuda'] ?> cuotas pendientes. Según el estatuto, más de <?= MESES_MORA ?> meses de atraso puede considerarse falta grave. Ponte al día o conversa con la tesorería.</p>
<?php endif; ?>
<?php if (ajuste('datos_pago') !== ''): ?>
  <section class="card pago" aria-labelledby="h-pago">
    <h2 id="h-pago">¿Cómo pagar mis cuotas?</h2>
    <p class="pre"><?= e(ajuste('datos_pago')) ?></p>
  </section>
<?php endif; ?>

<div class="dos-col">
  <section class="card" aria-labelledby="h-pagos">
    <h2 id="h-pagos">Mis pagos</h2>
    <?php if (!$pagos): ?><p class="muted">Aún no hay pagos registrados.</p><?php else: ?>
    <div class="tabla-wrap"><table>
      <thead><tr><th>Fecha</th><th>Detalle</th><th class="num">Monto</th></tr></thead>
      <tbody><?php foreach ($pagos as $p): ?>
        <tr><td><?= fecha($p['fecha']) ?></td><td><?= e($p['forma_pago'] ?: '') ?> <span class="muted"><?= e($p['observacion']) ?></span></td><td class="num"><?= clp($p['monto']) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
  </section>

  <section class="card" aria-labelledby="h-asis">
    <h2 id="h-asis">Mis asistencias</h2>
    <?php if (!$reuniones): ?><p class="muted">No hay reuniones registradas desde tu ingreso.</p><?php else: ?>
    <div class="tabla-wrap"><table>
      <thead><tr><th>Fecha</th><th>Reunión</th><th></th></tr></thead>
      <tbody><?php foreach ($reuniones as $r): ?>
        <tr><td><?= fecha($r['fecha']) ?></td><td><?= e($r['titulo']) ?></td>
        <td><?= match (true) {
            !$r['con_registro'] => '<span class="badge neutro">Sin lista</span>',
            $r['estado'] === 'presente' => '<span class="badge ok">Presente</span>',
            $r['estado'] === 'representante' => '<span class="badge ok">Con representante</span>',
            $r['estado'] === 'justificado' => '<span class="badge mar">Justificado</span>',
            default => '<span class="badge warn">Ausente</span>',
        } ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
    <?php endif; ?>
  </section>
</div>

<section class="card" aria-labelledby="h-avisos">
  <h2 id="h-avisos">Comunicados</h2>
  <?php if (!$avisos): ?><p class="muted">Sin comunicados por ahora.</p><?php endif; ?>
  <?php foreach ($avisos as $a): ?>
    <details <?= $a === $avisos[0] ? 'open' : '' ?> style="margin-bottom:.8rem">
      <summary><?= e($a['titulo']) ?> <span class="muted">· <?= fecha($a['fecha']) ?></span></summary>
      <p class="pre"><?= e($a['cuerpo']) ?></p>
    </details>
  <?php endforeach; ?>
</section>

<section class="card" aria-labelledby="h-docs">
  <h2 id="h-docs">Actas y documentos</h2>
  <?php if (!$docs): ?><p class="muted">Aún no hay documentos.</p><?php else: ?>
  <div class="tabla-wrap"><table>
    <thead><tr><th>Fecha</th><th>Documento</th><th>Tipo</th><th></th></tr></thead>
    <tbody><?php foreach ($docs as $d): ?>
      <tr><td><?= fecha($d['fecha']) ?></td><td><?= e($d['titulo']) ?></td><td><span class="badge mar"><?= e(CATEGORIAS_DOC[$d['categoria']]) ?></span></td>
      <td class="acc"><a class="btn chico sec" href="<?= url('descargar.php?id=' . $d['id']) ?>" target="_blank" rel="noopener">Ver</a></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php page_end();
