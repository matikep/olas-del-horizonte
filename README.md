# Sitio Comité de Vivienda Olas del Horizonte

PHP 8.1+ y MySQL/MariaDB. No usa librerías (solo Google Fonts: Plus Jakarta Sans + Inter). Funciona en cualquier hosting compartido con cPanel.

**El sitio público es anónimo**: no muestra nombres, RUT, direcciones, directiva ni cantidad de socios. Esos datos solo se ven con sesión iniciada.

## Desarrollo local (datos de demostración)

Necesitas Docker. Desde la carpeta del proyecto:

```bash
DB_PASSWORD=local docker compose -f docker-compose.yml -f docker-compose.local.yml up --build
```

- **Sitio:** http://localhost:8090. El código se monta en vivo: al editar un `.php` se ve el cambio sin reconstruir.
- **Datos:** la primera vez se cargan los de `sql/demo/2-demo.sql`, **todos ficticios**.
- **Accesos:**

  | Perfil | RUT | Clave |
  |---|---|---|
  | Admin | 11.111.111-1 | `demo1234` |
  | Miembro | 22.222.222-2 | `demo1234` |
- **Para empezar de cero** (borra la base local), cambia `up --build` por `down -v` en el mismo comando.

## Despliegue en Coolify (recomendado)

El repositorio trae `Dockerfile` y `docker-compose.yml`, con la web (PHP 8.2 + Apache) y MariaDB 10.11.

1. **Crear el recurso:** en Coolify, *New Resource → Git repository* (privado, con la GitHub App o una deploy key) → *Build Pack: Docker Compose* → archivo `docker-compose.yml`.
2. **Variables de entorno:**
   - `DB_PASSWORD`: una clave larga y aleatoria.
   - `TRUST_PROXY=1`: indispensable detrás de Coolify. Sin ella, todos los visitantes comparten la IP del proxy y el límite de intentos de login bloquearía a todos a la vez.
3. **Dominio:** asigna tu dominio (ej. `https://olasdelhorizonte.cl`) al servicio **web**, puerto 80. Coolify genera el certificado HTTPS.
4. **Desplegar.** La primera vez, la base se crea vacía con las tablas de `sql/1-schema.sql`.
5. **Cargar los datos de socios.** No están en git porque tienen RUT, teléfonos y documentos. Desde tu computador, en la carpeta `sitio/`:

   ```bash
   # nombres de los contenedores: en Coolify o con `docker ps` en el servidor
   scp sql/2-datos.sql usuario@servidor:/tmp/
   scp -r uploads usuario@servidor:/tmp/uploads-comite
   ssh usuario@servidor
   docker exec -i <contenedor-db> sh -c 'mariadb -ucomite -p"$MARIADB_PASSWORD" comite' < /tmp/2-datos.sql
   docker cp /tmp/uploads-comite/. <contenedor-web>:/var/www/html/uploads/
   docker exec <contenedor-web> chown -R www-data:www-data /var/www/html/uploads
   rm -rf /tmp/2-datos.sql /tmp/uploads-comite
   ```

   Si ya existe algún socio en la base, importa `2-datos.sql` antes de usar el sitio: el archivo trae IDs fijos y chocaría con los registros existentes.
6. **Admin:** entra a `https://tu-dominio/instalar.php`, pon el RUT del presidente y una clave. `instalar.php` se desactiva solo en cuanto existe un admin con clave.

### Persistencia de datos y documentos

Los datos viven en dos **volúmenes con nombre**, fuera de los contenedores:

| Volumen | Contenido |
|---|---|
| `db-data` | La base de datos: socios, pagos, asistencia, fichas… |
| `uploads` | Todos los archivos adjuntos: actas, cédulas, cartolas RSH, declaraciones… |

- **Qué los conserva:** sobreviven a redeploys (aunque se reconstruya la imagen), reinicios y actualizaciones del servidor. Se probó recreando los contenedores y los documentos siguieron idénticos.
- **Dónde verlos:** en Coolify aparecen en la pestaña *Persistent Storage* del recurso, con el prefijo del proyecto.
- **Qué los borra:** eliminar el recurso en Coolify marcando la opción de borrar volúmenes, o ejecutar `docker compose down -v`.
- **No les cambies el nombre** en `docker-compose.yml`. Coolify crearía volúmenes nuevos y vacíos, y el sitio aparecería sin datos. Los antiguos seguirían en el servidor, pero desconectados.
- **Sobre `sql/1-schema.sql`:** crea las tablas solo cuando el volumen de la base está vacío, es decir, la primera vez. Un redeploy nunca reinicia la base.

### Respaldos

Los volúmenes persisten, pero no protegen de un disco dañado ni de un borrado por error. Respalda periódicamente, desde el servidor:

```bash
# Base de datos (contenedores: ver `docker ps` o el recurso en Coolify)
docker exec <contenedor-db> sh -c 'mariadb-dump -ucomite -p"$MARIADB_PASSWORD" comite' | gzip > respaldo-$(date +%F).sql.gz
# Documentos adjuntos
docker exec <contenedor-web> tar czf - -C /var/www/html uploads > uploads-$(date +%F).tar.gz
```

Guarda las copias **fuera del servidor**, por ejemplo en tu computador o en otro almacenamiento, y automatízalo con `cron` en el servidor. Para restaurar se usan los mismos comandos del paso 5.

## Instalación en hosting compartido (cPanel)

1. **Base de datos** — En cPanel → *Bases de datos MySQL*: crea una base, un usuario y asígnale todos los privilegios.
2. **Importar** — En phpMyAdmin, selecciona la base e importa **en este orden**:
   1. `sql/1-schema.sql` (tablas)
   2. `sql/2-datos.sql` (socios, pagos, gastos, reuniones, asistencias, documentos y textos del sitio)
3. **Subir archivos** — Sube todo el contenido de esta carpeta a `public_html` (o a una subcarpeta), incluida la carpeta `uploads/` con sus archivos.
4. **Configurar** — Copia `inc/config.sample.php` como `inc/config.php` y completa los datos de la base. Si el sitio queda en una subcarpeta (ej. `midominio.cl/comite`), pon `'base' => '/comite'`.
5. **Permisos** — La carpeta `uploads/` debe permitir escritura (755 normalmente basta; si falla la subida, prueba 775).
6. **Primer administrador** — Abre `tudominio.cl/instalar.php`, escribe el RUT del presidente (ya viene como admin en los datos iniciales) y define una clave. **Después borra `instalar.php` del servidor.**
7. **HTTPS** — Activa el certificado SSL gratuito del hosting (AutoSSL / Let's Encrypt).

> Las carpetas `inc/`, `sql/` y `uploads/` traen un `.htaccess` que bloquea el acceso directo. Los documentos solo se descargan con sesión iniciada mediante `descargar.php`.

## Perfiles

| Perfil | Cómo entra | Qué puede hacer |
|---|---|---|
| **Invitado** | Sin cuenta | Ver el sitio (historia, quiénes somos, principios, comunicados públicos) y enviar una **pre-postulación** (solo nombre, teléfono y correo). |
| **Miembro** | RUT + clave | Ver su estado de cuotas y pagos, su asistencia, comunicados, actas y documentos. Completar y actualizar su **ficha de postulación**. Cambiar su clave y datos de contacto. |
| **Admin** | RUT + clave | Todo lo anterior, más socios y roles, tesorería (pagos, gastos, cuota), reuniones y asistencia, actas y documentos, comunicados, postulaciones y textos del sitio. |

Los socios vienen **sin clave**. Para darle acceso a alguien: *Admin → Socios → editar → "Clave de acceso"*, y le entregas esa clave. Luego la cambia en *Mi cuenta*.

## De interesado a socio

1. **Pre-postulación** — Un visitante deja nombre, teléfono y correo desde el sitio público. No se le piden RUT ni documentos.
2. **Contacto** — La directiva ve la pre-postulación en *Admin → Pre-postulaciones*, la contacta y, si se suma, usa **Crear socio**. Luego, en *Socios*, se le agregan el RUT y una clave.
3. **Ficha** — El socio entra a *Mi ficha de postulación* y completa los mismos datos del formulario del comité: RUT, nombre, dirección, nacionalidad, estado civil, teléfono, correo, formato familiar o unipersonal y causal de excepción. También adjunta la cédula y la cartola RSH, y si es unipersonal, las **declaraciones SERVIU**. Además marca si ya tiene creada su **Cuenta de Ahorro para la Vivienda**: es una declaración de buena fe, sin comprobante. Puede **guardar avance**, **enviarla a la directiva** y **actualizarla** cuando cambie algo.
4. **Revisión** — En *Socios* se ve el estado de la ficha de cada socio (sin ficha, borrador, enviada o actualizada) y se pueden abrir sus documentos. Esos documentos solo los ven los admins y el propio socio.

**¿Ya habías importado la base antes de este cambio?** Ejecuta además `sql/migraciones/2026-09-postulaciones.sql` en phpMyAdmin. En una instalación nueva no hace falta.

## RUT y celulares

- **RUT:** se formatea solo al escribir (`12.345.678-5`) y se valida el dígito verificador en todos los formularios. En la base se guarda sin puntos (`12345678-5`), así que el login funciona como sea que lo escriban.
- **Celular (WhatsApp):** el campo tiene el prefijo fijo `+56 9` y solo se escriben los 8 dígitos restantes. Se guarda como `+56912345678` y se muestra como `+56 9 1234 5678`. En *Pre-postulaciones*, el número abre WhatsApp directamente.

## Bajas de socios

- Para que alguien deje de participar **sin borrar su historial**, desmarca *Socio activo* en *Socios* e indica la **fecha de baja** (por defecto, hoy).
- Desde esa fecha no se generan más cuotas. El mes de la baja sí se cobra, igual que el mes de ingreso. Lo que debía hasta entonces sigue apareciendo en *Tesorería*, marcado "de baja".
- Si se reactiva, la fecha de baja se borra y vuelve a contar normalmente.
- ¿Ya habías importado la base antes? Ejecuta `sql/migraciones/2026-09-fecha-baja.sql`.

## Asistencia

- Cada socio puede quedar como **Ausente, Presente, Representante o Justificado**.
- **Representante** cuenta como asistencia. **Justificado** no penaliza el porcentaje, pero se muestra el conteo del año; al pasar de 3 (acuerdo del 06/03/2026) aparece un badge rojo.
- Las reuniones **sin lista cargada**, por ejemplo una online sin registro, no cuentan como ausencia para nadie.
- ¿Ya habías importado la base antes? Ejecuta `sql/migraciones/2026-09-asistencia-estados.sql`.

## Cómo se calculan las cuotas

`lo que debería haber pagado = meses desde el inicio del cobro (o desde que entró, si es posterior) × cuota mensual`

El saldo es lo pagado menos eso: si da negativo, el socio debe; si da positivo, tiene saldo a favor. Los pagos se registran como montos (por ejemplo, $30.000 de una vez cubre 10 meses), igual que en la planilla. La cuota y el mes de inicio se cambian en *Tesorería → Configurar cuota mensual*.

## Notas sobre los datos importados

Las notas sobre el origen de los datos de socios, lo que se corrigió y lo que falta verificar están en `sql/NOTAS-DATOS.md`. Ese archivo **no va al repositorio** porque contiene nombres y RUT; guárdalo junto a `sql/2-datos.sql`.

