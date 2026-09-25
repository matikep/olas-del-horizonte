<?php
require __DIR__ . '/../inc/app.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (post('accion') === 'eliminar') {
        $doc = q('SELECT archivo FROM documentos WHERE id = ?', [(int)post('id')])->fetch();
        if ($doc) {
            q('DELETE FROM documentos WHERE id = ?', [(int)post('id')]);
            @unlink(UPLOADS . basename($doc['archivo']));
            flash('Documento eliminado.');
        }
        redirect('admin/documentos.php');
    }
    $cat = array_key_exists(post('categoria'), CATEGORIAS_DOC) ? post('categoria') : 'otro';
    if (mb_strlen(post('titulo')) < 2 || !fecha_valida(post('fecha'))) {
        flash('Indica título y fecha.', 'error');
        redirect('admin/documentos.php');
    }
    try {
        [$archivo, $original] = subir_archivo('archivo');
        q('INSERT INTO documentos (categoria, titulo, fecha, archivo, nombre_original, solo_admin) VALUES (?,?,?,?,?,?)',
            [$cat, mb_substr(post('titulo'), 0, 200), post('fecha'), $archivo, $original, isset($_POST['solo_admin']) ? 1 : 0]);
        flash('Documento subido.');
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'error');
    }
    redirect('admin/documentos.php');
}

$docs = q('SELECT * FROM documentos ORDER BY fecha DESC, id DESC')->fetchAll();
page_start('Actas y documentos', 'admin/documentos.php');
?>
<form class="card" method="post" enctype="multipart/form-data">
  <h2>Subir documento</h2>
  <?= csrf_field() ?>
  <div class="grid-form">
    <label>Título<input name="titulo" required maxlength="200" placeholder="Acta N°4"></label>
    <label>Tipo<select name="categoria"><?php foreach (CATEGORIAS_DOC as $k => $v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?></select></label>
    <label>Fecha<input type="date" name="fecha" required value="<?= date('Y-m-d') ?>"></label>
  </div>
  <label>Archivo <small>(PDF, Word, Excel o imagen · máx. 15 MB)</small><input type="file" name="archivo" required accept=".<?= implode(',.', EXT_PERMITIDAS) ?>"></label>
  <label class="check"><input type="checkbox" name="solo_admin"> Solo visible para administradores</label>
  <button>Subir</button>
</form>

<section class="card">
  <h2><?= count($docs) ?> documentos</h2>
  <div class="tabla-wrap"><table>
    <thead><tr><th>Fecha</th><th>Título</th><th>Tipo</th><th>Visible para</th><th></th></tr></thead>
    <tbody><?php foreach ($docs as $d): ?>
      <tr><td><?= fecha($d['fecha']) ?></td><td><?= e($d['titulo']) ?><br><small class="muted"><?= e($d['nombre_original']) ?></small></td>
      <td><span class="badge mar"><?= e(CATEGORIAS_DOC[$d['categoria']]) ?></span></td>
      <td><?= $d['solo_admin'] ? '<span class="badge neutro">Admins</span>' : '<span class="badge ok">Socios</span>' ?></td>
      <td class="acc">
        <a class="btn chico sec" href="<?= url('descargar.php?id=' . $d['id']) ?>" target="_blank" rel="noopener">Ver</a>
        <form method="post" onsubmit="return confirm('¿Eliminar este documento?')"><?= csrf_field() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= $d['id'] ?>"><button class="peligro chico">Eliminar</button></form>
      </td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>
<?php page_end();
