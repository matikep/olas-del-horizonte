<?php
// Núcleo compartido: conexión, sesión, seguridad, helpers y layout.
declare(strict_types=1);

const CARGOS = ['Presidente/a', 'Secretario/a', 'Tesorero/a', '1er Director', '2do Director', '3er Director'];
const CATEGORIAS_DOC = ['acta' => 'Actas', 'asistencia' => 'Listas de asistencia', 'tesoreria' => 'Tesorería', 'otro' => 'Otros'];
const EXT_PERMITIDAS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];
const EXT_POSTULACION = ['pdf', 'jpg', 'jpeg', 'png', 'heic'];
// Tipo real del archivo (se revisa el contenido, no solo la extensión)
const MIMES = ['pdf' => ['application/pdf'], 'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'], 'png' => ['image/png'], 'heic' => ['image/heic', 'image/heif']];

// Formulario de postulación
const NACIONALIDADES = ['chileno' => 'Chileno/a', 'extranjero' => 'Extranjero/a con residencia definitiva'];
const ESTADOS_CIVILES = ['Soltero/a', 'Casado/a', 'Conviviente Civil', 'Viudo/a', 'Divorciado/a'];
const FORMATOS = ['familiar' => 'Familiar (con cargas)', 'unipersonal' => 'Unipersonal / pareja sin cargas'];
const CAUSALES = [
    'adulto_mayor' => 'Adulto mayor (60+)',
    'discapacidad' => 'Discapacidad (RND / COMPIN)',
    'indigena' => 'Calidad indígena (CONADI)',
    'viudez' => 'Viudo/a',
    'ddhh' => 'Nómina DD.HH. (Valech / Rettig)',
    'ninguna' => 'No cumple ninguna causal',
];
const MAX_SUBIDA = 15 * 1024 * 1024;
const MESES_MORA = 2;   // estatuto: más de 2 meses de atraso puede considerarse falta grave
// Asistencia: sin fila = ausente. Representante cuenta como asistencia; justificado no penaliza.
const ESTADOS_ASISTENCIA = ['presente' => 'Presente', 'representante' => 'Representante', 'justificado' => 'Justificado'];
const MAX_JUSTIFICACIONES = 3;   // acuerdo 06/03/2026: máximo 3 justificaciones al año
const UPLOADS = __DIR__ . '/../uploads/';

// Configuración: variables de entorno (Docker/Coolify) o inc/config.php (hosting compartido).
$cfgFile = __DIR__ . '/config.php';
if (getenv('DB_HOST')) {
    $CFG = [
        'db_host' => getenv('DB_HOST'),
        'db_name' => getenv('DB_NAME') ?: 'comite',
        'db_user' => getenv('DB_USER') ?: 'comite',
        'db_pass' => (string)getenv('DB_PASSWORD'),
        'base' => getenv('BASE_PATH') ?: '',
    ];
} elseif (is_file($cfgFile)) {
    $CFG = require $cfgFile;
} else {
    exit('Falta la configuración: define DB_HOST, DB_NAME, DB_USER y DB_PASSWORD, o copia inc/config.sample.php como inc/config.php.');
}
// Detrás de un proxy (Coolify/Traefik) la IP y el HTTPS reales vienen en cabeceras X-Forwarded-*.
define('DETRAS_DE_PROXY', getenv('TRUST_PROXY') === '1');

// IP del visitante. Con proxy se usa la última IP de X-Forwarded-For (la que agregó nuestro proxy; las anteriores las puede inventar el cliente).
function ip_cliente(): string
{
    if (DETRAS_DE_PROXY && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
        return (string)end($ips);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

function es_https(): bool
{
    if (DETRAS_DE_PROXY && isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        return $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https';
    }
    return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
}

date_default_timezone_set('America/Santiago');
ini_set('display_errors', '0');
ini_set('log_errors', '1');
set_exception_handler(function (Throwable $ex): void {
    error_log((string)$ex);
    http_response_code(500);
    echo 'Ocurrió un error inesperado. Intenta nuevamente; si persiste, avisa a la directiva.';
});

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => es_https(),
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ---------- Base de datos ----------
function db(): PDO
{
    static $pdo = null;
    global $CFG;
    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host={$CFG['db_host']};dbname={$CFG['db_name']};charset=utf8mb4",
            $CFG['db_user'],
            $CFG['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
        $pdo->exec("SET time_zone = '" . date('P') . "'");   // NOW() en hora de Chile, igual que PHP
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function ajustes(): array
{
    static $a = null;
    return $a ??= q('SELECT clave, valor FROM ajustes')->fetchAll(PDO::FETCH_KEY_PAIR);
}

function ajuste(string $clave, string $default = ''): string
{
    return ajustes()[$clave] ?? $default;
}

// ---------- Helpers ----------
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    global $CFG;
    return rtrim($CFG['base'], '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function clp(int|float|null $n): string
{
    return '$' . number_format((float)$n, 0, ',', '.');
}

function fecha(?string $d): string
{
    return $d ? date('d/m/Y', strtotime($d)) : '—';
}

function flash(?string $msg = null, string $tipo = 'ok'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = [$msg, $tipo];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function post(string $k): string
{
    return trim((string)($_POST[$k] ?? ''));
}

function fecha_valida(string $d): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt !== false && $dt->format('Y-m-d') === $d;
}

// RUT chileno: devuelve "12345678-9" o null si es inválido (dígito verificador módulo 11).
function rut_normalizar(string $rut): ?string
{
    $rut = strtoupper(preg_replace('/[^0-9kK]/', '', $rut));
    if (strlen($rut) < 2) {
        return null;
    }
    $num = substr($rut, 0, -1);
    $dv = substr($rut, -1);
    if (!ctype_digit($num)) {
        return null;
    }
    $suma = 0;
    $mul = 2;
    for ($i = strlen($num) - 1; $i >= 0; $i--) {
        $suma += (int)$num[$i] * $mul;
        $mul = $mul === 7 ? 2 : $mul + 1;
    }
    $r = 11 - ($suma % 11);
    $esperado = $r === 11 ? '0' : ($r === 10 ? 'K' : (string)$r);
    return $dv === $esperado ? ltrim($num, '0') . '-' . $dv : null;
}

// Celular chileno (WhatsApp): acepta "1234 5678", "9 1234 5678", "+56 9 1234 5678"… y devuelve "+56912345678", o null si no es válido.
function telefono_normalizar(?string $t): ?string
{
    $d = preg_replace('/\D/', '', (string)$t);
    if (strlen($d) === 11 && str_starts_with($d, '569')) $d = substr($d, 2);
    if (strlen($d) === 8) $d = '9' . $d;
    return preg_match('/^9\d{8}$/', $d) ? '+56' . $d : null;
}

// "+56912345678" -> "+56 9 1234 5678" (si no es un celular válido, lo muestra tal cual)
function telefono_formato(?string $t): string
{
    $n = telefono_normalizar($t);
    return $n ? '+56 9 ' . substr($n, 4, 4) . ' ' . substr($n, 8) : (string)$t;
}

// Campo de celular: prefijo fijo "+56 9" y solo 8 dígitos editables (se formatean solos al escribir).
function campo_telefono(?string $valor, bool $requerido = false): string
{
    $n = telefono_normalizar($valor);
    $v = $n ? substr($n, 4, 4) . ' ' . substr($n, 8) : '';
    return '<span class="tel"><span class="tel-pre" aria-hidden="true">+56 9</span><input name="telefono" type="tel" inputmode="numeric"'
        . ' autocomplete="tel-national" maxlength="16" pattern="\d{4} \d{4}" placeholder="1234 5678" aria-label="Celular: 8 dígitos después de +56 9"'
        . ' title="8 dígitos después del +56 9"' . ($requerido ? ' required' : '') . ' value="' . e($v) . '"></span>';
}

// Atributos comunes de todo campo RUT (formateo automático con assets/app.js)
const RUT_INPUT = 'class="rut" maxlength="12" autocapitalize="characters" placeholder="12.345.678-9" title="RUT con dígito verificador"';

function rut_formato(?string $rut): string
{
    if (!$rut) {
        return '—';
    }
    [$num, $dv] = explode('-', $rut);
    return number_format((int)$num, 0, ',', '.') . '-' . $dv;
}

// ---------- Cuotas ----------
// ponytail: cuota única vigente aplicada a todos los meses; si la cuota cambia en el tiempo, agregar tabla de tramos.
// Cuenta desde el mes de ingreso (o inicio del cobro) hasta hoy, o hasta el mes de la baja si el socio está inactivo.
function estado_cuota(string $fechaIngreso, int $pagado, ?string $fechaBaja = null): array
{
    $cuota = (int)ajuste('cuota_mensual', '3000');
    $inicio = max(ajuste('inicio_cobro', '2025-03-01'), $fechaIngreso);
    $hasta = $fechaBaja ? min($fechaBaja, date('Y-m-d')) : date('Y-m-d');
    $mesesDesde = fn(string $d) => (int)date('Y', strtotime($d)) * 12 + (int)date('n', strtotime($d));
    $meses = max(0, $mesesDesde($hasta) - $mesesDesde($inicio) + 1);
    $esperado = $meses * $cuota;
    $saldo = $pagado - $esperado;
    return [
        'meses' => $meses,
        'esperado' => $esperado,
        'pagado' => $pagado,
        'saldo' => $saldo,
        'meses_deuda' => $saldo < 0 && $cuota > 0 ? (int)ceil(-$saldo / $cuota) : 0,
    ];
}

function socios_con_pagos(bool $soloActivos = true): array
{
    $rows = q('SELECT u.*, COALESCE(SUM(p.monto),0) AS pagado FROM usuarios u
               LEFT JOIN pagos p ON p.usuario_id = u.id ' . ($soloActivos ? 'WHERE u.activo = 1 ' : '') .
               'GROUP BY u.id ORDER BY u.nombre')->fetchAll();
    return array_map(fn($u) => $u + ['cuota' => estado_cuota($u['fecha_ingreso'], (int)$u['pagado'], $u['fecha_baja'])], $rows);
}

function badge_cuota(array $c): string
{
    if ($c['saldo'] >= 0) {
        return '<span class="badge ok">Al día' . ($c['saldo'] > 0 ? ' · a favor ' . clp($c['saldo']) : '') . '</span>';
    }
    return '<span class="badge ' . ($c['meses_deuda'] > MESES_MORA ? 'falta' : 'warn') . '">Debe ' . clp(-$c['saldo']) . ' · ' . $c['meses_deuda'] . ' ' . ($c['meses_deuda'] === 1 ? 'mes' : 'meses') . '</span>';
}

// Justificaciones por socio en un año: [usuario_id => cantidad]. $excluirReunion evita contar la que se está editando.
function justificaciones_anio(int $anio, int $excluirReunion = 0): array
{
    return q("SELECT a.usuario_id, COUNT(*) FROM asistencias a JOIN reuniones r ON r.id = a.reunion_id
              WHERE a.estado = 'justificado' AND YEAR(r.fecha) = ? AND r.id <> ? GROUP BY a.usuario_id",
        [$anio, $excluirReunion])->fetchAll(PDO::FETCH_KEY_PAIR);
}

// ---------- Seguridad ----------
function csrf_token(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_check(): void
{
    if (!hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Sesión expirada. Vuelve atrás y recarga la página.');
    }
}

// Límite simple por clave (ej. "login:IP"): true si ya superó $max intentos en $minutos.
function limite_superado(string $clave, int $max, int $minutos): bool
{
    q('DELETE FROM intentos WHERE fecha < NOW() - INTERVAL 1 DAY');
    $n = (int)q('SELECT COUNT(*) FROM intentos WHERE clave = ? AND fecha > NOW() - INTERVAL ? MINUTE', [$clave, $minutos])->fetchColumn();
    return $n >= $max;
}

function registrar_intento(string $clave): void
{
    q('INSERT INTO intentos (clave) VALUES (?)', [$clave]);
}

function user(): ?array
{
    static $u = false;
    if ($u === false) {
        $id = $_SESSION['uid'] ?? null;
        $u = $id ? (q('SELECT * FROM usuarios WHERE id = ? AND activo = 1', [$id])->fetch() ?: null) : null;
    }
    return $u;
}

function require_login(): array
{
    $u = user();
    if (!$u) {
        redirect('login.php');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['rol'] !== 'admin') {
        http_response_code(403);
        exit('No tienes permiso para ver esta página.');
    }
    return $u;
}

// Guarda un archivo subido en /uploads con nombre aleatorio. Devuelve [nombreInterno, nombreOriginal].
function subir_archivo(string $campo, array $exts = EXT_PERMITIDAS): array
{
    $f = $_FILES[$campo] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se recibió el archivo (¿falta adjuntarlo o supera el tamaño permitido?).');
    }
    if ($f['size'] > MAX_SUBIDA) {
        throw new RuntimeException('El archivo supera los 15 MB.');
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $exts, true)) {
        throw new RuntimeException('Tipo de archivo no permitido. Usa: ' . implode(', ', $exts));
    }
    if (isset(MIMES[$ext]) && function_exists('mime_content_type')
        && !in_array(mime_content_type($f['tmp_name']), MIMES[$ext], true)) {
        throw new RuntimeException('El archivo no corresponde a un ' . strtoupper($ext) . ' válido.');
    }
    $nombre = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], UPLOADS . $nombre)) {
        throw new RuntimeException('No se pudo guardar el archivo. Revisa permisos de la carpeta uploads.');
    }
    return [$nombre, mb_substr(basename($f['name']), 0, 200)];
}

// ---------- Layout ----------
function nav_app(array $u, string $activo): string
{
    $items = [['panel/index.php', 'Mi resumen'], ['panel/ficha.php', 'Mi ficha de postulación'], ['panel/cuenta.php', 'Mi cuenta']];
    if ($u['rol'] === 'admin') {
        $items = array_merge($items, [
            null,
            ['admin/index.php', 'Panel admin'],
            ['admin/socios.php', 'Socios'],
            ['admin/tesoreria.php', 'Tesorería'],
            ['admin/reuniones.php', 'Reuniones y asistencia'],
            ['admin/documentos.php', 'Actas y documentos'],
            ['admin/comunicados.php', 'Comunicados'],
            ['admin/postulaciones.php', 'Pre-postulaciones'],
            ['admin/sitio.php', 'Contenido del sitio'],
        ]);
    }
    $h = '';
    foreach ($items as $it) {
        if ($it === null) {
            $h .= '<li class="sep" aria-hidden="true">Administración</li>';
            continue;
        }
        $cur = $it[0] === $activo ? ' aria-current="page"' : '';
        $h .= '<li><a href="' . url($it[0]) . '"' . $cur . '>' . e($it[1]) . '</a></li>';
    }
    return $h;
}

function page_start(string $titulo, ?string $activo = null): void
{
    $u = $activo !== null ? user() : null;
    $GLOBALS['APP_SHELL'] = (bool)$u;
    $f = flash();
    ?><!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?> · Olas del Horizonte</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= url('assets/style.css') ?>?v=3">
<link rel="icon" href="<?= url('assets/icono.svg') ?>">
</head>
<body class="<?= $u ? 'app' : 'public' ?>">
<?php if ($u): ?>
<div class="app-shell">
  <aside class="sidebar">
    <a class="brand" href="<?= url() ?>"><?= logo() ?><span>Olas del<br>Horizonte</span></a>
    <nav aria-label="Menú"><ul><?= nav_app($u, $activo) ?></ul></nav>
    <div class="who">
      <strong><?= e($u['nombre']) ?></strong>
      <span><?= $u['rol'] === 'admin' ? 'Administrador' : 'Miembro' ?></span>
      <form method="post" action="<?= url('logout.php') ?>"><?= csrf_field() ?><button class="link">Cerrar sesión</button></form>
    </div>
  </aside>
  <main class="content">
    <h1 class="page-title"><?= e($titulo) ?></h1>
<?php else: ?>
<main>
<?php endif; ?>
<?php if ($f): ?><p class="flash <?= e($f[1]) ?>" role="status"><?= e($f[0]) ?></p><?php endif;
}

function page_end(): void
{
    echo '<script src="' . url('assets/app.js') . '?v=1"></script>';
    echo $GLOBALS['APP_SHELL'] ?? false ? "</main></div>\n</body></html>" : "</main>\n</body></html>";
}

function logo(): string
{
    return '<svg class="logo" viewBox="0 0 48 48" aria-hidden="true"><defs><linearGradient id="lg-sol" x1="0" y1="0" x2="0" y2="1">'
        . '<stop offset="0" stop-color="#f2d1a0"/><stop offset="1" stop-color="#d9924e"/></linearGradient></defs>'
        . '<circle cx="24" cy="22" r="9" fill="url(#lg-sol)"/>'
        . '<path d="M2 28h44" stroke="currentColor" stroke-width="2.5"/>'
        . '<path d="M4 35c5-4 9-4 14 0s9 4 14 0 9-4 12 0" fill="none" stroke="#80bdf2" stroke-width="3" stroke-linecap="round"/>'
        . '<path d="M8 42c4-3 8-3 12 0s8 3 12 0 6-3 8 0" fill="none" stroke="#7e9a2a" stroke-width="3" stroke-linecap="round"/></svg>';
}
