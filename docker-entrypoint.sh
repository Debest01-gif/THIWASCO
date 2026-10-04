#!/bin/bash
set -e

# Render binds to a custom dynamic PORT (default 10000)
PORT="${PORT:-10000}"

# Update Apache to listen on the correct port
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Ensure directory permissions before initialization
mkdir -p /var/www/html/database /var/www/html/uploads/meter_photos /var/www/html/uploads/inspection_photos /var/www/html/uploads/profile_photos
chmod -R 777 /var/www/html/database /var/www/html/uploads 2>/dev/null || true

# Initialize SQLite database and tables
echo "🔧 Initializing SQLite database schema and seed data..."
php /var/www/html/database/init_sqlite.php || echo "⚠️ DB init failed, will fallback in PHP runtime."

# Ensure full read/write permissions for Apache www-data user
chown -R www-data:www-data /var/www/html/database /var/www/html/uploads 2>/dev/null || true
chmod -R 777 /var/www/html/database /var/www/html/uploads 2>/dev/null || true

echo "🚀 Starting Apache on port ${PORT}..."
exec apache2-foreground
