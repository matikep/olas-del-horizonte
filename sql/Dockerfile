# MariaDB con el esquema incluido: crea las tablas la primera vez que el volumen de datos está vacío.
# (Va dentro de la imagen para no depender de rutas del servidor, como pasa con los bind mounts en Coolify.)
FROM mariadb:10.11
COPY 1-schema.sql /docker-entrypoint-initdb.d/1-schema.sql
