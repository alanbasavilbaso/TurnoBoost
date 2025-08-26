#!/bin/bash
set -e

echo "Starting deployment..."

# Set working directory
cd /app

# Install dependencies if needed
if [ ! -d "vendor" ]; then
    echo "Installing composer dependencies..."
    composer install --no-dev --optimize-autoloader
fi

# Clear cache
echo "Clearing cache..."
php bin/console cache:clear --env=prod --no-debug

# Run migrations
echo "Running migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --env=prod

# Process nginx config
echo "Processing nginx configuration..."
node /app/assets/scripts/prestart.mjs /app/assets/nginx.template.conf /nginx.conf

# Start services
echo "Starting PHP-FPM and Nginx..."
php-fpm -y /app/assets/php-fpm.conf &
nginx -c /nginx.conf