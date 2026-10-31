#!/bin/sh
set -e
echo "[orders] esperando a PostgreSQL en $DB_HOST:$DB_PORT ..."
# Reintenta hasta que la BD acepte conexiones (el healthcheck del compose ya ayuda,
# pero asi el servicio tambien funciona si se reinicia solo)
for i in $(seq 1 30); do
if php -r 'try { new
PDO("pgsql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"),
getenv("DB_USERNAME"), getenv("DB_PASSWORD")); exit(0);} catch (Exception $e) { exit(1); }';
then
break
fi
sleep 2
done
echo "[orders] ejecutando migraciones ..."
php artisan migrate --force
echo "[orders] servidor Laravel escuchando en el puerto 8000"
exec php artisan serve --host=0.0.0.0 --port=8000