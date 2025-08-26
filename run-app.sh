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

# Start PHP built-in server
echo "Starting PHP server..."
php -S 0.0.0.0:${PORT:-8080} -t public/