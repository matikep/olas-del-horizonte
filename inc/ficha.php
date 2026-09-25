<?php
// Ficha de postulación de los socios (panel/ficha.php) y su vista en admin (admin/socios.php).
declare(strict_types=1);

const CAUSALES_DETALLE = [
    'adulto_mayor' => '<strong>Adulto mayor:</strong> tener 60 años cumplidos o más.',
    'discapacidad' => '<strong>Discapacidad:</strong> credencial del Registro Nacional de la Discapacidad (RND) o certificado COMPIN.',
    'indigena' => '<strong>Calidad indígena:</strong> certificado emitido por la CONADI.',
    'viudez' => '<strong>Viudo/a:</strong> certificado de matrimonio con anotación de defunción o certificado de viudez.',
    'ddhh' => '<strong>Nómina DD.HH.:</strong> pertenecer a la nómina oficial del Informe Valech / Rettig.',
    'ninguna' => 'No cumplo con ninguna de estas causales de excepción.',
];
// Estos datos viven en usuarios (una sola fuente); el resto de la ficha, en postulaciones.
const CAMPOS_PERSONALES = ['nombre', 'direccion', 'telefono', 'email'];
// columna => [campo del formulario, nombre corto, texto del formulario, ayuda, solo si es unipersonal]
const DOCS_FICHA = [
    'doc_cedula' => ['cedula', 'Cédula de identidad', 'Cédula de identidad: fotocopia por ambos lados (vigente)', '', false],
    'doc_rsh' => ['rsh', 'Cartola RSH', 'Cartola del Registro Social de Hogares (RSH) actualizada', '', false],
    'doc_serviu' => ['serviu', 'Declaraciones SERVIU', 'Declaraciones SERVIU',
        'Declaración del núcleo familiar y de propiedad habitacional, declaración jurada de postulación y mandato de ahorro. Puedes subirlas juntas en un PDF o foto.', true],
];

// Valida y limpia los campos que vienen con valor (los vacíos se ignoran). Devuelve [datos, errores].
function ficha_validar(array $in): array
{
    $reglas = [
        'nombre' => [fn($x) => mb_strlen($x) >= 5 && mb_strlen($x) <= 150 ? $x : null, 'Escribe tus nombres y apellidos completos.'],
        'direccion' => [fn($x) => mb_strlen($x) >= 5 && mb_strlen($x) <= 200 ? $x : null, 'Indica tu dirección completa.'],
        'nacionalidad' => [fn($x) => isset(NACIONALIDADES[$x]) ? $x : null, 'Nacionalidad inválida.'],
        'estado_civil' => [fn($x) => in_array($x, ESTADOS_CIVILES, true) ? $x : null, 'Estado civil inválido.'],
        'telefono' => [fn($x) => telefono_normalizar($x), 'El celular debe tener 8 dígitos después del +56 9.'],
        'email' => [fn($x) => filter_var($x, FILTER_VALIDATE_EMAIL) && mb_strlen($x) <= 150 ? $x : null, 'El correo no es válido.'],
        'formato' => [fn($x) => isset(FORMATOS[$x]) ? $x : null, 'Formato de postulación inválido.'],
        'causal' => [fn($x) => isset(CAUSALES[$x]) ? $x : null, 'Causal inválida.'],
    ];
    $d = [];
    $err = [];
    foreach ($reglas as $k => [$limpiar, $msg]) {
        $x = trim((string)($in[$k] ?? ''));
        if ($x === '') continue;
        $limpio = $limpiar($x);
        $limpio !== null ? $d[$k] = $limpio : $err[] = $msg;
    }
    if (($d['formato'] ?? '') !== 'unipersonal') $d['causal'] = null;   // la causal solo aplica a unipersonal
    $d['tiene_ahorro'] = isset($in['tiene_ahorro']) ? 1 : 0;              // declaración de buena fe, sin comprobante
    return [$d, $err];
}

// Lista lo que falta para considerar la ficha completa.
function ficha_faltantes(array $f, bool $conDocs = true): array
{
    $req = ['rut' => 'RUT', 'nombre' => 'nombre completo', 'direccion' => 'dirección', 'nacionalidad' => 'nacionalidad', 'estado_civil' => 'estado civil',
            'telefono' => 'teléfono', 'email' => 'correo', 'formato' => 'formato de postulación'];
    if (($f['formato'] ?? '') === 'unipersonal') $req['causal'] = 'causal de excepción';
    $req['tiene_ahorro'] = 'Cuenta de ahorro vivienda';
    if ($conDocs) {
        foreach (DOCS_FICHA as $col => [, $nombre, , , $soloUni]) {
            if (!$soloUni || ($f['formato'] ?? '') === 'unipersonal') $req[$col] = $nombre;
        }
    }
    return array_values(array_filter($req, fn($k) => empty($f[$k]), ARRAY_FILTER_USE_KEY));
}

// Ficha completa de un socio: datos personales desde usuarios + datos de postulación.
function ficha_datos(array $usuario, ?array $ficha): array
{
    $personales = array_intersect_key($usuario, array_flip([...CAMPOS_PERSONALES, 'rut']));
    return $personales + ($ficha ?? []);
}

// Sube los documentos que vengan adjuntos. Devuelve [['doc_cedula' => archivo, ...], errores].
function ficha_subir_docs(): array
{
    $nuevos = [];
    $err = [];
    foreach (DOCS_FICHA as $col => [$campo, $nombre]) {
        if (($_FILES[$campo]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        try {
            $nuevos[$col] = subir_archivo($campo, EXT_POSTULACION)[0];
        } catch (RuntimeException $ex) {
            $err[] = "$nombre: " . $ex->getMessage();
        }
    }
    return [$nuevos, $err];
}

// Lo que falta, como badges rojos.
function badges_faltan(array $faltan): string
{
    return $faltan ? '<span class="faltan">' . implode('', array_map(fn($x) => '<span class="badge falta">' . e(mb_strtoupper(mb_substr($x, 0, 1)) . mb_substr($x, 1)) . '</span>', $faltan)) . '</span>' : '';
}

function badge_ficha(?array $f): string
{
    if (!$f) return '<span class="badge falta">Sin ficha</span>';
    if (!$f['enviada']) return '<span class="badge warn">Borrador</span>';
    if ($f['actualizado'] && $f['actualizado'] > $f['enviada']) return '<span class="badge mar">Actualizada ' . fecha($f['actualizado']) . '</span>';
    return '<span class="badge ok">Enviada ' . fecha($f['enviada']) . '</span>';
}

function ficha_borrar_archivos(array $archivos): void
{
    foreach ($archivos as $f) {
        if ($f) @unlink(UPLOADS . basename($f));
    }
}

// Campos de la ficha, en el mismo orden que el formulario de postulación del comité.
// $v junta datos del socio (usuarios) y de la ficha (postulaciones). $docsUrl ['doc_cedula' => url|null] muestra lo ya subido.
function ficha_campos(array $v, array $docsUrl = []): string
{
    $val = fn(string $k) => e((string)($v[$k] ?? ''));
    $chk = fn(string $k, string $x) => ($v[$k] ?? '') === $x ? 'checked' : '';
    $falta = fn(bool $vacio) => $vacio ? ' <span class="badge falta">Falta</span>' : '';
    $f = fn(string $k) => $falta(empty($v[$k]));
    ob_start(); ?>
      <fieldset>
        <legend><span>1</span> Datos del postulante</legend>
        <div class="grid-form">
          <label>RUT / Pasaporte *<?= $f('rut') ?><input value="<?= e(rut_formato($v['rut'] ?? null)) ?>" readonly aria-readonly="true">
            <small>Es tu usuario para ingresar. Si está mal, avisa a la directiva.</small></label>
          <label>Nombres y apellidos completos *<?= $f('nombre') ?><input name="nombre" maxlength="150" autocomplete="name" value="<?= $val('nombre') ?>"></label>
        </div>
        <label>Dirección actual *<?= $f('direccion') ?><input name="direccion" maxlength="200" autocomplete="street-address" value="<?= $val('direccion') ?>"></label>
        <div class="grid-form">
          <div class="grupo" role="radiogroup" aria-label="Nacionalidad">
            <span class="grupo-t">Nacionalidad *<?= $f('nacionalidad') ?></span>
            <?php foreach (NACIONALIDADES as $k => $t): ?>
              <label class="check"><input type="radio" name="nacionalidad" value="<?= $k ?>" <?= $chk('nacionalidad', $k) ?>> <?= e($t) ?></label>
            <?php endforeach; ?>
          </div>
          <label>Estado civil *<?= $f('estado_civil') ?><select name="estado_civil"><option value="">Selecciona…</option>
            <?php foreach (ESTADOS_CIVILES as $ec): ?><option <?= ($v['estado_civil'] ?? '') === $ec ? 'selected' : '' ?>><?= $ec ?></option><?php endforeach; ?>
          </select></label>
        </div>
        <div class="grid-form">
          <label>Celular de contacto (WhatsApp) *<?= $f('telefono') ?><?= campo_telefono($v['telefono'] ?? null) ?></label>
          <label>Correo electrónico *<?= $f('email') ?><input name="email" type="email" maxlength="150" autocomplete="email" value="<?= $val('email') ?>"></label>
        </div>
        <div class="grupo" role="radiogroup" aria-label="Formato de postulación">
          <span class="grupo-t">Formato de postulación *<?= $f('formato') ?></span>
          <label class="opcion"><input type="radio" name="formato" value="familiar" <?= $chk('formato', 'familiar') ?>>
            <span><strong>Postulación familiar</strong> (2 o más integrantes CON cargas): hogares registrados en el RSH que incluyen cónyuge/pareja e hijos, adultos mayores o dependientes a cargo.</span></label>
          <label class="opcion"><input type="radio" name="formato" value="unipersonal" <?= $chk('formato', 'unipersonal') ?>>
            <span><strong>Unipersonal o pareja SIN cargas</strong> (sujeto a causal de excepción): si vives solo/a o solo con tu pareja, sin cargas familiares en el RSH. Requiere seleccionar una causal de excepción.</span></label>
        </div>
      </fieldset>

      <fieldset class="seccion-causal">
        <legend><span>2</span> Causal de excepción <small>(obligatoria si postulas unipersonal / sin cargas)</small><?= ($v['formato'] ?? '') === 'unipersonal' ? $f('causal') : '' ?></legend>
        <?php foreach (CAUSALES_DETALLE as $k => $txt): ?>
          <label class="opcion"><input type="radio" name="causal" value="<?= $k ?>" <?= $chk('causal', $k) ?>><span><?= $txt ?></span></label>
        <?php endforeach; ?>
      </fieldset>

      <fieldset>
        <legend><span class="num-docs">3</span> Requisitos y documentación obligatoria</legend>
        <div class="aviso-req" role="note">
          <strong>Requisito obligatorio: Cuenta de Ahorro para la Vivienda</strong>
          <p>Para postular es obligatorio tener abierta una Cuenta de Ahorro para la Vivienda. No necesitamos ver tu saldo ni comprobantes: solo indícanos si ya la tienes creada. Esta declaración es de buena fe.</p>
          <p>La meta acordada es tener cerca de <strong>$500.000</strong> depositados a mediados de 2027. No retires dinero para volver a depositarlo: el SERVIU sanciona esa práctica.</p>
          <label class="check"><input type="checkbox" name="tiene_ahorro" value="1" <?= !empty($v['tiene_ahorro']) ? 'checked' : '' ?>> Declaro que ya tengo creada mi Cuenta de Ahorro para la Vivienda<?= $f('tiene_ahorro') ?></label>
        </div>
        <p class="muted nota">PDF o foto (JPG, PNG, HEIC), máximo 15 MB cada uno. Solo la directiva puede ver estos archivos.</p>
        <?php foreach (DOCS_FICHA as $col => [$campo, , $texto, $ayuda, $soloUni]): ?>
          <label <?= $soloUni ? 'class="solo-unipersonal"' : '' ?>><?= $texto ?> *<?= $soloUni ? ' <small>(postulación unipersonal)</small>' : '' ?><?= $falta(empty($docsUrl[$col])) ?>
            <?php if (!empty($docsUrl[$col])): ?>
              <span class="doc-ok">✓ Documento cargado · <a href="<?= e($docsUrl[$col]) ?>" target="_blank" rel="noopener">ver</a> · sube otro solo si quieres reemplazarlo</span>
            <?php endif; ?>
            <?php if ($ayuda): ?><small class="ayuda"><?= e($ayuda) ?></small><?php endif; ?>
            <input type="file" name="<?= $campo ?>" accept=".pdf,.jpg,.jpeg,.png,.heic"></label>
        <?php endforeach; ?>
      </fieldset>
      <script>
        // La sección 2 solo aplica a postulación unipersonal
        (() => {
          const f = document.currentScript.closest('form'), sec = f.querySelector('.seccion-causal');
          const sync = () => {
            const uni = f.formato.value === 'unipersonal';
            sec.hidden = !uni;
            f.querySelectorAll('.solo-unipersonal').forEach(el => el.hidden = !uni);
            f.querySelector('.num-docs').textContent = uni ? '3' : '2';
          };
          f.querySelectorAll('[name=formato]').forEach(r => r.addEventListener('change', sync));
          sync();
        })();
      </script>
    <?php
    return (string)ob_get_clean();
}
