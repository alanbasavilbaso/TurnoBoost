#!/bin/bash

# Script de deployment automático
echo "🚀 Iniciando deployment..."

# Actualizar dependencias
composer install --no-dev --optimize-autoloader

# Limpiar caché
php bin/console cache:clear --env=prod

# Compilar assets
php bin/console asset-map:compile --env=prod

# Ejecutar migraciones
php bin/console doctrine:migrations:migrate --no-interaction

echo "✅ Deployment completado"