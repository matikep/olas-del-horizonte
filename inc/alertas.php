<?php
// Alertas calculadas en cada visita a partir de los datos actuales (siempre al día, sin procesos programados).
// Cada alerta: ['nivel' => rojo|naranja|info|ok, 'titulo' => …, 'detalle' => …, 'link' => ruta|null, 'nombres' => [...]]
declare(strict_types=1);

require_once __DIR__ . '/ficha.php';

const MAX_DIAS_SIN_REUNION = 35;   // acuerdo: reunión el primer viernes de cada mes

function alerta(string $nivel, string $titulo, string $detalle = '', ?string $link = null, array $nombres = []): array
{
    return compact('nivel', 'titulo', 'detalle', 'link', 'nombres');
}

// Meta de la postulación colectiva: al menos 70% del grupo en tramo 40.
function alertas_rsh(array $socios, array $fichas): array
{
    $revisados = array_filter($socios, fn($s) => !empty($fichas[$s['id']]['tramo_rsh']));
    $n = count($revisados);
    if ($n === 0) {
        return [alerta('naranja', 'Ningún socio tiene el tramo RSH revisado', 'Revisa las cartolas en la ficha de cada socio.', 'admin/socios.php')];
    }
    $tramo = fn($s) => (int)$fichas[$s['id']]['tramo_rsh'];
    $en40 = count(array_filter($revisados, fn($s) => $tramo($s) === 40));
    $pend = count($socios) - $n;
    $pct = (int)round($en40 * 100 / $n);
    $faltanRevisados = max(0, (int)ceil(META_TRAMO_40 / 100 * $n) - $en40);
    $faltanGrupo = (int)ceil(META_TRAMO_40 / 100 * count($socios)) - $en40;   // cuántos de los pendientes deben ser tramo 40

    $out = [];
    if ($pct >= META_TRAMO_40) {
        $out[] = alerta('ok', "Meta RSH cumplida entre los revisados: $pct% en tramo 40", "$en40 de $n socios revisados." . ($pend ? " Quedan $pend por revisar." : ''), 'admin/socios.php');
    } else {
        $detalle = "Con los $n socios revisados se necesitan " . ($en40 + $faltanRevisados) . " en tramo 40 (hay $en40: faltan $faltanRevisados).";
        if ($pend) {
            $detalle .= $faltanGrupo <= $pend
                ? " Quedan $pend socios sin revisar: para cumplir con el grupo completo, al menos $faltanGrupo de ellos deben estar en tramo 40."
                : " Quedan $pend socios sin revisar, pero aunque todos fueran tramo 40 no alcanzaría la meta con el grupo actual: hay que sumar socios en tramo 40.";
        }
        $out[] = alerta('rojo', "Meta RSH no se cumple: $pct% en tramo 40 (meta ≥ " . META_TRAMO_40 . '%)', $detalle, 'admin/socios.php');
    }
    $sobre90 = array_filter($revisados, fn($s) => $tramo($s) > 90);
    if ($sobre90) {
        $out[] = alerta('rojo', count($sobre90) . ' socio(s) con tramo sobre el 90%', 'Quedan fuera del rango permitido para la postulación colectiva.', null, array_column($sobre90, 'nombre', 'id'));
    }
    $antiguas = array_filter($revisados, fn($s) => rsh_antigua($fichas[$s['id']]['rsh_fecha']));
    if ($antiguas) {
        $out[] = alerta('naranja', count($antiguas) . ' cartola(s) RSH con más de ' . RSH_MESES_VIGENCIA . ' meses', 'Pedir una cartola actualizada y volver a revisar el tramo.', null, array_column($antiguas, 'nombre', 'id'));
    }
    return $out;
}

function alertas_fichas(array $socios, array $fichas): array
{
    $sinForm = array_filter($socios, fn($s) => empty($fichas[$s['id']]['enviada']));
    $sinCausal = array_filter($socios, fn($s) => ($fichas[$s['id']]['formato'] ?? '') === 'unipersonal'
        && in_array($fichas[$s['id']]['causal'] ?? null, [null, 'ninguna'], true));
    $pendientes = [];   // lo que falta => nombres de socios con formulario enviado
    foreach ($socios as $s) {
        if (!empty($fichas[$s['id']]['enviada'])) {
            foreach (ficha_faltantes(ficha_datos($s, $fichas[$s['id']])) as $falta) $pendientes[$falta][] = $s['nombre'];
        }
    }
    $out = [];
    if ($sinCausal) {
        $out[] = alerta('rojo', count($sinCausal) . ' postulación(es) unipersonal(es) sin causal de excepción válida', 'La postulación unipersonal exige una causal (adulto mayor, discapacidad, CONADI, viudez o DD.HH.). Revisar con el socio.', null, array_column($sinCausal, 'nombre', 'id'));
    }
    if ($sinForm) {
        $out[] = alerta('naranja', count($sinForm) . ' socio(s) no han completado el formulario de postulación', '', 'admin/socios.php?form=no');
    }
    if ($pendientes) {
        $conPendientes = count(array_unique(array_merge(...array_values($pendientes))));
        arsort($pendientes);
        $detalle = implode(' · ', array_map(fn($falta, $nombres) => $falta . ' (' . count($nombres) . ')', array_keys($pendientes), $pendientes));
        $out[] = alerta('info', "$conPendientes formulario(s) completado(s) con datos o documentos pendientes", 'Falta: ' . $detalle, 'admin/socios.php?form=si');
    }
    return $out;
}

function alertas_cuotas_asistencia(array $socios): array
{
    $mora = array_filter($socios, fn($s) => $s['cuota']['meses_deuda'] > MESES_MORA);
    $just = justificaciones_anio((int)date('Y'));
    $pasados = array_filter($socios, fn($s) => ($just[$s['id']] ?? 0) > MAX_JUSTIFICACIONES);
    $ultima = q('SELECT MAX(fecha) FROM reuniones WHERE fecha <= CURDATE()')->fetchColumn();
    $dias = $ultima ? (int)((time() - strtotime($ultima)) / 86400) : null;

    $out = [];
    if ($mora) {
        $deuda = -array_sum(array_map(fn($s) => $s['cuota']['saldo'], $mora));
        $out[] = alerta('rojo', count($mora) . ' socio(s) con más de ' . MESES_MORA . ' meses de atraso en cuotas', 'Suman ' . clp($deuda) . '. Según el estatuto puede considerarse falta grave.', 'admin/tesoreria.php#estado');
    }
    if ($pasados) {
        $out[] = alerta('naranja', count($pasados) . ' socio(s) superan las ' . MAX_JUSTIFICACIONES . ' justificaciones de ' . date('Y'), 'Acuerdo del 06/03/2026: máximo 3 justificaciones al año.', null, array_column($pasados, 'nombre', 'id'));
    }
    if ($dias !== null && $dias > MAX_DIAS_SIN_REUNION) {
        $out[] = alerta('info', "Hace $dias días que no se registra una reunión", 'El acuerdo es reunirse el primer viernes de cada mes.', 'admin/reuniones.php?nueva=1');
    }
    return $out;
}

function alertas_acceso(array $socios): array
{
    $sinRut = array_filter($socios, fn($s) => !$s['rut']);
    $sinClave = array_filter($socios, fn($s) => $s['rut'] && !$s['password_hash']);
    $nuevas = (int)q("SELECT COUNT(*) FROM postulaciones WHERE estado = 'nueva' AND usuario_id IS NULL")->fetchColumn();
    $out = [];
    if ($sinRut) {
        $out[] = alerta('naranja', count($sinRut) . ' socio(s) sin RUT registrado', 'Sin RUT no pueden ingresar al sitio.', null, array_column($sinRut, 'nombre', 'id'));
    }
    if ($nuevas) {
        $out[] = alerta('info', "$nuevas pre-postulación(es) sin revisar", '', 'admin/postulaciones.php');
    }
    if ($sinClave) {
        $out[] = alerta('info', count($sinClave) . ' socio(s) aún sin clave de acceso', 'Asígnales una clave en Socios para que vean sus cuotas y completen su ficha.', 'admin/socios.php');
    }
    return $out;
}

// Todas las alertas de la directiva, de más a menos grave. Se calcula una vez por visita.
function alertas_admin(): array
{
    static $cache = null;
    if ($cache !== null) return $cache;
    $socios = socios_con_pagos();
    $fichas = [];
    foreach (q('SELECT * FROM postulaciones WHERE usuario_id IS NOT NULL')->fetchAll() as $f) $fichas[$f['usuario_id']] = $f;
    $todas = [...alertas_rsh($socios, $fichas), ...alertas_fichas($socios, $fichas), ...alertas_cuotas_asistencia($socios), ...alertas_acceso($socios)];
    $orden = ['rojo' => 0, 'naranja' => 1, 'info' => 2, 'ok' => 3];
    usort($todas, fn($a, $b) => $orden[$a['nivel']] <=> $orden[$b['nivel']]);
    return $cache = $todas;
}

// Alertas personales del socio (su panel).
function alertas_socio(array $u, ?array $ficha, array $cuota): array
{
    $out = [];
    if ($cuota['meses_deuda'] > MESES_MORA) {
        $out[] = alerta('rojo', "Tienes {$cuota['meses_deuda']} cuotas pendientes", 'Según el estatuto, más de ' . MESES_MORA . ' meses de atraso puede considerarse falta grave. Ponte al día o conversa con la tesorería.');
    }
    if (!$ficha || !$ficha['enviada']) {
        $out[] = alerta('naranja', 'Completa tu formulario de postulación', 'Es obligatorio para mantener tu cupo en el comité.', 'panel/ficha.php');
    } elseif ($faltan = ficha_faltantes(ficha_datos($u, $ficha))) {
        $out[] = alerta('naranja', 'A tu ficha le falta: ' . implode(', ', $faltan), '', 'panel/ficha.php');
    }
    if ($ficha && rsh_antigua($ficha['rsh_fecha'])) {
        $out[] = alerta('naranja', 'Tu cartola RSH es del ' . fecha($ficha['rsh_fecha']), 'Descarga una actualizada en registrosocial.gob.cl y súbela en tu ficha.', 'panel/ficha.php');
    }
    $just = (int)(justificaciones_anio((int)date('Y'))[$u['id']] ?? 0);
    if ($just > MAX_JUSTIFICACIONES) {
        $out[] = alerta('naranja', "Llevas $just justificaciones de inasistencia este año", 'El máximo acordado es ' . MAX_JUSTIFICACIONES . ' al año.');
    }
    return $out;
}

function alertas_html(array $alertas, bool $mostrarNombres = true): string
{
    $iconos = ['rojo' => '●', 'naranja' => '▲', 'info' => 'i', 'ok' => '✓'];
    $h = '<ul class="alertas">';
    foreach ($alertas as $a) {
        $titulo = e($a['titulo']);
        if ($a['link']) $titulo = '<a href="' . url($a['link']) . '">' . $titulo . '</a>';
        $h .= '<li class="alerta-' . $a['nivel'] . '"><span class="alerta-ico" aria-hidden="true">' . $iconos[$a['nivel']] . '</span><div><strong>' . $titulo . '</strong>';
        if ($a['detalle']) $h .= '<p>' . e($a['detalle']) . '</p>';
        if ($mostrarNombres && $a['nombres']) {
            $h .= '<p class="alerta-nombres">' . implode(', ', array_map(fn($id, $n) => '<a href="' . url('admin/socios.php?id=' . $id) . '">' . e($n) . '</a>', array_keys($a['nombres']), $a['nombres'])) . '</p>';
        }
        $h .= '</div></li>';
    }
    return $h . '</ul>';
}
