<?php
// Entrega archivos de /uploads solo a usuarios con sesión (y solo_admin solo a admins).
require __DIR__ . '/inc/app.php';
require __DIR__ . '/inc/ficha.php';

$u = require_login();
if (isset($_GET['postulacion'])) {
    // Documentos de la ficha: administradores y el propio socio dueño de la ficha.
    $campo = array_search($_GET['doc'] ?? '', array_map(fn($d) => $d[0], DOCS_FICHA), true);
    if ($campo === false) {
        http_response_code(404);
        exit('Documento no encontrado.');
    }
    $p = q("SELECT COALESCE(u.nombre, p.nombre) AS nombre, p.usuario_id, p.$campo AS archivo
            FROM postulaciones p LEFT JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = ?", [(int)$_GET['postulacion']])->fetch();
    if (!$p || !$p['archivo'] || ($u['rol'] !== 'admin' && (int)$p['usuario_id'] !== (int)$u['id'])) {
        http_response_code(404);
        exit('Documento no encontrado.');
    }
    $ext = pathinfo($p['archivo'], PATHINFO_EXTENSION);
    $doc = ['archivo' => $p['archivo'], 'nombre_original' => DOCS_FICHA[$campo][1] . ' - ' . $p['nombre'] . '.' . $ext];
} else {
    $doc = q('SELECT * FROM documentos WHERE id = ?', [(int)($_GET['id'] ?? 0)])->fetch();
    if (!$doc || ($doc['solo_admin'] && $u['rol'] !== 'admin')) {
        http_response_code(404);
        exit('Documento no encontrado.');
    }
}
$ruta = UPLOADS . basename($doc['archivo']);
if (!is_file($ruta)) {
    http_response_code(404);
    exit('El archivo ya no está disponible.');
}
$tipos = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
$ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
header('Content-Type: ' . ($tipos[$ext] ?? 'application/octet-stream'));
header('Content-Length: ' . filesize($ruta));
// PDF e imágenes se abren en el navegador; el resto se descarga.
$modo = isset($tipos[$ext]) ? 'inline' : 'attachment';
header("Content-Disposition: $modo; filename*=UTF-8''" . rawurlencode($doc['nombre_original']));
header('Cache-Control: private, max-age=0');
readfile($ruta);
