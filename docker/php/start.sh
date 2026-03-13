#!/bin/sh
set -e

echo "MySQL listo"
echo "Preparando directorios Laravel..."
until php -r "try { new PDO('mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT') . ';dbname=' . getenv('DB_DATABASE'), getenv('DB_USERNAME'), getenv('DB_PASSWORD')); echo 'MySQL listo'; } catch (Exception \$e) { exit(1); }"; do
  sleep 2
done

echo "Preparando directorios Laravel..."
mkdir -p /var/www/html/storage/framework/cache
mkdir -p /var/www/html/storage/framework/sessions
mkdir -p /var/www/html/storage/framework/views
mkdir -p /var/www/html/storage/logs
mkdir -p /var/www/html/bootstrap/cache

chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

if [ ! -f /var/www/html/.env ]; then
  echo "Copiando .env desde .env.example..."
  cp /var/www/html/.env.example /var/www/html/.env
fi

if [ ! -f /var/www/html/vendor/autoload.php ]; then
  echo "Instalando dependencias Composer..."
  composer install --no-interaction --prefer-dist
else
  echo "Composer ya instalado."
fi

if ! grep -q "^APP_KEY=base64:" /var/www/html/.env; then
  echo "Generando APP_KEY..."
  php artisan key:generate --force
fi

echo "Migrando base..."
php artisan migrate --force

echo "Sembrando catálogos y datos demo..."
php artisan db:seed --force

echo "Limpiando cachés..."
php artisan optimize:clear || true

echo "Levantando PHP-FPM..."
exec php-fpm

echo ""
echo "=============================================="
echo "   Task Organizer listo para usar en desarrollo"
echo ""
echo "   Backend API  : http://localhost:8000"
echo "   Frontend     : http://localhost:5173"
echo "   phpMyAdmin   : http://localhost:8081"
echo ""
echo "   Usuario admin:"
echo "   admin@tareas.local.com"
echo "   administrador"
echo ""
echo "   ¡Gracias por revisar el proyecto!"
echo "   Esperamos que lo disfruten."
echo "=============================================="
echo ""