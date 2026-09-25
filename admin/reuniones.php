<?php
require __DIR__ . '/../inc/app.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)post('id');
    if (post('accion') === 'eliminar') {
        q('DELETE FROM reuniones WHERE id = ?', [$id]);
        flash('Reunión eliminada.');
        redirect('admin/reuniones.php');
    }
    if (mb_strlen(post('titulo')) < 2 || !fecha_valida(post('fecha'))) {
        flash('Indica título y fecha.', 'error');
        redirect('admin/reuniones.php' . ($id ? "?id=$id" : ''));
    }
    $datos = [post('fecha'), mb_substr(post('titulo'), 0, 150), mb_substr(post('lugar'), 0, 150) ?: null];
    db()->beginTransaction();
    if ($id) {
        q('UPDATE reuniones SET fecha = ?, titulo = ?, lugar = ? WHERE id = ?', [...$datos, $id]);
    } else {
        q('INSERT INTO reuniones (fecha, titulo, lugar) VALUES (?,?,?)', $datos);
        $id = (int)db()->lastInsertId();
    }
    q('DELETE FROM asistencias WHERE reunion_id = ?', [$id]);
    $ins = db()->prepare('INSERT INTO asistencias (reunion_id, usuario_id, estado) VALUES (?,?,?)');
    foreach ((array)($_POST['estado'] ?? []) as $uid => $estado) {
        if (isset(ESTADOS_ASISTENCIA[$estado])) {        // vacío o desconocido = ausente
            $ins->execute([$id, (int)$uid, $estado]);
        }
    }
    db()->commit();
    flash('Reunión y asistencia guardadas.');
    redirect("admin/reuniones.php?id=$id");
}

if (isset($_GET['id']) || isset($_GET['nueva'])) {
    $r = isset($_GET['id']) ? q('SELECT * FROM reuniones WHERE id = ?', [(int)$_GET['id']])->fetch() : null;
    if (isset($_GET['id']) && !$r) redirect('admin/reuniones.php');
    $r ??= ['id' => 0, 'fecha' => date('Y-m-d'), 'titulo' => 'Asamblea ordinaria', 'lugar' => q('SELECT lugar FROM reuniones ORDER BY fecha DESC LIMIT 1')->fetchColumn() ?: ''];
    $estados = $r['id'] ? q('SELECT usuario_id, estado FROM asistencias WHERE reunion_id = ?', [$r['id']])->fetchAll(PDO::FETCH_KEY_PAIR) : [];
    $anio = (int)substr($r['fecha'], 0, 4);
    $justAnio = justificaciones_anio($anio, (int)$r['id']);   // de otras reuniones del mismo año
    // Socios activos que ya habían ingresado a la fecha de la reunión + cualquiera con asistencia registrada (histórico)
    $socios = q('SELECT id, nombre FROM usuarios WHERE (activo = 1 AND fecha_ingreso <= ?)
                 OR id IN (SELECT usuario_id FROM asistencias WHERE reunion_id = ?) ORDER BY nombre', [$r['fecha'], $r['id']])->fetchAll();
    page_start($r['id'] ? 'Editar reunión' : 'Nueva reunión', 'admin/reuniones.php');
    ?>
    <p><a href="reuniones.php">← Volver a reuniones</a></p>
    <form class="card" method="post">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>">
      <div class="grid-form">
        <label>Fecha<input type="date" name="fecha" required value="<?= e($r['fecha']) ?>"></label>
        <label>Título<input name="titulo" required maxlength="150" value="<?= e($r['titulo']) ?>"></label>
        <label>Lugar<input name="lugar" maxlength="150" value="<?= e($r['lugar']) ?>"></label>
      </div>
      <h2>Asistencia</h2>
      <div class="asis-herramientas">
        <input type="search" id="buscar-socio" placeholder="Buscar socio…" aria-label="Buscar socio" autocomplete="off">
        <button type="button" class="chico sec" data-marcar="presente">Todos presentes</button>
        <button type="button" class="chico sec" data-marcar="">Todos ausentes</button>
      </div>
      <p class="muted nota">Representante cuenta como asistencia. Justificado no penaliza, pero se permiten máximo <?= MAX_JUSTIFICACIONES ?> al año.</p>
      <ul class="asistencia-lista">
        <?php foreach ($socios as $s): $actual = $estados[$s['id']] ?? ''; ?>
          <li data-estado="<?= $actual ?>" data-just="<?= (int)($justAnio[$s['id']] ?? 0) ?>">
            <span class="asis-nombre"><?= e($s['nombre']) ?> <span class="just-badge"></span></span>
            <span class="seg" role="radiogroup" aria-label="Asistencia de <?= e($s['nombre']) ?>">
              <?php foreach (['' => 'Ausente'] + ESTADOS_ASISTENCIA as $val => $txt): ?>
                <label class="seg-<?= $val ?: 'ausente' ?>"><input type="radio" name="estado[<?= $s['id'] ?>]" value="<?= $val ?>" <?= $actual === $val ? 'checked' : '' ?>><span><?= $txt ?></span></label>
              <?php endforeach; ?>
            </span>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="muted" id="sin-resultados" hidden>Ningún socio coincide con la búsqueda.</p>
      <div class="barra-guardar">
        <span id="contador"></span>
        <button>Guardar reunión</button>
      </div>
    </form>
    <script>
      const filas = [...document.querySelectorAll('.asistencia-lista li')];
      const MAX = <?= MAX_JUSTIFICACIONES ?>, ANIO = <?= $anio ?>;
      const estadoDe = li => li.querySelector('input:checked')?.value ?? '';
      const pintar = li => {
        const e = estadoDe(li);
        li.dataset.estado = e;
        // justificaciones del año = otras reuniones + esta (si está justificado)
        const n = Number(li.dataset.just) + (e === 'justificado' ? 1 : 0), b = li.querySelector('.just-badge');
        b.hidden = n === 0;
        b.className = 'just-badge badge ' + (n > MAX ? 'falta' : 'neutro');
        b.textContent = 'Justificaciones ' + ANIO + ': ' + n + '/' + MAX;
      };
      const contar = () => {
        const c = {presente: 0, representante: 0, justificado: 0};
        filas.forEach(li => { const e = estadoDe(li); if (e) c[e]++; });
        document.getElementById('contador').innerHTML = '<strong>' + (c.presente + c.representante) + '</strong> de ' + filas.length + ' asistieron'
          + (c.representante ? ' · ' + c.representante + ' por representante' : '') + (c.justificado ? ' · ' + c.justificado + (c.justificado === 1 ? ' justificado' : ' justificados') : '');
      };
      filas.forEach(li => { pintar(li); li.addEventListener('change', () => { pintar(li); contar(); }); });
      // Los botones masivos solo afectan a los socios visibles (respetan la búsqueda)
      document.querySelectorAll('[data-marcar]').forEach(b => b.addEventListener('click', () => {
        filas.filter(li => !li.hidden).forEach(li => { li.querySelector('input[value="' + b.dataset.marcar + '"]').checked = true; pintar(li); });
        contar();
      }));
      const sinTildes = t => t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
      document.getElementById('buscar-socio').addEventListener('input', e => {
        const q = sinTildes(e.target.value.trim());
        let visibles = 0;
        filas.forEach(li => { li.hidden = q && !sinTildes(li.querySelector('.asis-nombre').firstChild.textContent).includes(q); if (!li.hidden) visibles++; });
        document.getElementById('sin-resultados').hidden = visibles > 0;
      });
      contar();
    </script>
    <?php if ($r['id']): ?>
    <form method="post" onsubmit="return confirm('¿Eliminar esta reunión y su asistencia?')">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $r['id'] ?>"><input type="hidden" name="accion" value="eliminar">
      <button class="peligro">Eliminar reunión</button>
    </form>
    <?php endif; ?>
    <?php
    page_end();
    exit;
}

$activos = (int)q('SELECT COUNT(*) FROM usuarios WHERE activo = 1')->fetchColumn();
$reuniones = q("SELECT r.*, COUNT(a.usuario_id) AS n, COALESCE(SUM(a.estado <> 'justificado'), 0) AS asistieron
                FROM reuniones r LEFT JOIN asistencias a ON a.reunion_id = r.id GROUP BY r.id ORDER BY r.fecha DESC")->fetchAll();
page_start('Reuniones y asistencia', 'admin/reuniones.php');
?>
<section class="card">
  <h2><?= count($reuniones) ?> reuniones <a class="btn chico" href="?nueva=1">+ Nueva reunión</a></h2>
  <div class="tabla-wrap"><table>
    <thead><tr><th>Fecha</th><th>Reunión</th><th>Lugar</th><th class="num">Asistentes</th><th></th></tr></thead>
    <tbody><?php foreach ($reuniones as $r): ?>
      <tr><td><?= fecha($r['fecha']) ?></td><td><?= e($r['titulo']) ?></td><td class="muted"><?= e($r['lugar']) ?></td>
      <td class="num"><?= $r['n'] ? (int)$r['asistieron'] . ' <span class="muted">/ ' . $activos . '</span>' : '<span class="badge neutro">Sin lista</span>' ?></td>
      <td class="acc"><a class="btn chico sec" href="?id=<?= $r['id'] ?>">Asistencia</a></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>
<?php page_end();
