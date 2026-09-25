<?php
require __DIR__ . '/../inc/app.php';

require_admin();
const ESTADOS = ['nueva' => 'Nueva', 'contactada' => 'Contactada', 'aceptada' => 'Aceptada', 'rechazada' => 'Rechazada'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int)post('id');
    $p = q('SELECT * FROM postulaciones WHERE id = ?', [$id])->fetch();
    if (!$p) redirect('admin/postulaciones.php');

    switch (post('accion')) {
        case 'estado':
            if (array_key_exists(post('estado'), ESTADOS)) {
                q('UPDATE postulaciones SET estado = ? WHERE id = ?', [post('estado'), $id]);
                flash('Estado actualizado.');
            }
            break;
        case 'socio':
            $rut = $p['rut'] ? rut_normalizar($p['rut']) : null;   // pasaportes no sirven como usuario
            if ($rut && q('SELECT id FROM usuarios WHERE rut = ?', [$rut])->fetch()) {
                flash('Ya existe un socio con ese RUT.', 'error');
                break;
            }
            q('INSERT INTO usuarios (rut, nombre, email, telefono, direccion, fecha_ingreso) VALUES (?,?,?,?,?,CURDATE())',
                [$rut, $p['nombre'], $p['email'], telefono_normalizar($p['telefono']), $p['direccion']]);
            $nuevo = (int)db()->lastInsertId();
            // La postulación pasa a ser la ficha del nuevo socio
            q("UPDATE postulaciones SET estado = 'aceptada', usuario_id = ?, enviada = creado WHERE id = ?", [$nuevo, $id]);
            flash('Socio creado. Agrégale su RUT y una clave para que ingrese y complete su ficha.');
            redirect("admin/socios.php?id=$nuevo");
        case 'eliminar':
            q('DELETE FROM postulaciones WHERE id = ?', [$id]);
            foreach ([$p['doc_cedula'], $p['doc_rsh'], $p['doc_serviu']] as $f) {
                if ($f) @unlink(UPLOADS . basename($f));
            }
            flash('Postulación eliminada.');
            break;
    }
    redirect('admin/postulaciones.php');
}

// Las fichas de socios se ven en Socios; aquí solo postulantes externos.
$lista = q("SELECT * FROM postulaciones WHERE usuario_id IS NULL ORDER BY estado = 'nueva' DESC, creado DESC")->fetchAll();
page_start('Pre-postulaciones', 'admin/postulaciones.php');
?>
<section class="card">
  <h2><?= count($lista) ?> pre-postulaciones recibidas desde el sitio</h2>
  <p class="muted">Personas que dejaron sus datos de contacto. Al registrarlas con "Crear socio", completan su ficha (RUT, documentos, etc.) desde su propio panel.</p>
  <?php if (!$lista): ?><p class="muted">Todavía nadie ha enviado la pre-postulación.</p><?php endif; ?>
  <div class="tabla-wrap"><table>
    <thead><tr><th>Fecha</th><th>Persona</th><th>Contacto</th><th>Estado</th><th></th></tr></thead>
    <tbody><?php foreach ($lista as $p): ?>
      <tr>
        <td><?= fecha($p['creado']) ?></td>
        <td><strong><?= e($p['nombre']) ?></strong>
          <?php if ($p['mensaje']): ?><details><summary>Mensaje</summary><p class="pre"><?= e($p['mensaje']) ?></p></details><?php endif; ?></td>
        <td><?php if ($wa = telefono_normalizar($p['telefono'])): ?><a href="https://wa.me/<?= substr($wa, 1) ?>" target="_blank" rel="noopener"><?= e(telefono_formato($wa)) ?></a><?php else: ?><?= e($p['telefono']) ?><?php endif; ?><br><?php if ($p['email']): ?><a href="mailto:<?= e($p['email']) ?>"><?= e($p['email']) ?></a><?php endif; ?></td>
        <td><form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id" value="<?= $p['id'] ?>">
          <select name="estado" onchange="this.form.submit()" aria-label="Estado"><?php foreach (ESTADOS as $k => $v): ?><option value="<?= $k ?>" <?= $p['estado'] === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
          <noscript><button class="chico">OK</button></noscript></form></td>
        <td class="acc">
          <?php if ($p['estado'] !== 'aceptada'): ?>
          <form method="post"><?= csrf_field() ?><input type="hidden" name="accion" value="socio"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="chico">Crear socio</button></form>
          <?php endif; ?>
          <form method="post" onsubmit="return confirm('¿Eliminar esta postulación?')"><?= csrf_field() ?><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="peligro chico" aria-label="Eliminar">×</button></form>
        </td>
      </tr>
    <?php endforeach; ?></tbody>
  </table></div>
</section>
<?php page_end();
