<?php
require __DIR__ . '/../inc/app.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)post('id');
    if (post('accion') === 'eliminar') {
        q('DELETE FROM comunicados WHERE id = ?', [$id]);
        flash('Comunicado eliminado.');
        redirect('admin/comunicados.php');
    }
    if (mb_strlen(post('titulo')) < 2 || post('cuerpo') === '' || !fecha_valida(post('fecha'))) {
        flash('Completa título, texto y fecha.', 'error');
        redirect('admin/comunicados.php' . ($id ? "?id=$id" : ''));
    }
    $d = [mb_substr(post('titulo'), 0, 200), post('cuerpo'), isset($_POST['publico']) ? 1 : 0, post('fecha')];
    if ($id) {
        q('UPDATE comunicados SET titulo = ?, cuerpo = ?, publico = ?, fecha = ? WHERE id = ?', [...$d, $id]);
    } else {
        q('INSERT INTO comunicados (titulo, cuerpo, publico, fecha) VALUES (?,?,?,?)', $d);
    }
    flash('Comunicado guardado.');
    redirect('admin/comunicados.php');
}

$ed = isset($_GET['id']) ? q('SELECT * FROM comunicados WHERE id = ?', [(int)$_GET['id']])->fetch() : null;
$ed = $ed ?: ['id' => 0, 'titulo' => '', 'cuerpo' => '', 'publico' => 0, 'fecha' => date('Y-m-d')];
$lista = q('SELECT * FROM comunicados ORDER BY fecha DESC, id DESC')->fetchAll();
page_start('Comunicados', 'admin/comunicados.php');
?>
<div class="dos-col">
  <form class="card" method="post">
    <h2><?= $ed['id'] ? 'Editar comunicado' : 'Nuevo comunicado' ?></h2>
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $ed['id'] ?>">
    <label>Título<input name="titulo" required maxlength="200" value="<?= e($ed['titulo']) ?>"></label>
    <label>Fecha<input type="date" name="fecha" required value="<?= e($ed['fecha']) ?>"></label>
    <label>Texto<textarea name="cuerpo" required rows="8"><?= e($ed['cuerpo']) ?></textarea></label>
    <label class="check"><input type="checkbox" name="publico" <?= $ed['publico'] ? 'checked' : '' ?>> Publicar también en el sitio público</label>
    <p class="muted" style="font-size:.9rem">Sin marcar, solo lo ven los socios en su panel.</p>
    <div class="acciones-fila"><button>Guardar</button><?php if ($ed['id']): ?><a href="comunicados.php">Cancelar</a><?php endif; ?></div>
  </form>
  <section class="card">
    <h2>Publicados</h2>
    <?php if (!$lista): ?><p class="muted">Aún no hay comunicados.</p><?php endif; ?>
    <div class="tabla-wrap"><table><tbody>
      <?php foreach ($lista as $c): ?>
        <tr><td><?= fecha($c['fecha']) ?></td><td><?= e($c['titulo']) ?> <?= $c['publico'] ? '<span class="badge mar">Público</span>' : '<span class="badge neutro">Socios</span>' ?></td>
        <td class="acc"><a class="btn chico sec" href="?id=<?= $c['id'] ?>">Editar</a>
          <form method="post" onsubmit="return confirm('¿Eliminar este comunicado?')"><?= csrf_field() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= $c['id'] ?>"><button class="peligro chico" aria-label="Eliminar">×</button></form></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  </section>
</div>
<?php page_end();
