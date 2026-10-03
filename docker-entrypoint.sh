#!/bin/bash
set -e

# Render binds to a custom dynamic PORT (default 10000)
PORT="${PORT:-10000}"

# Update Apache to listen on the correct port
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Initialize SQLite database if it doesn't exist yet
SQLITE_DB="/var/www/html/database/thiwasco.sqlite"
if [ ! -f "$SQLITE_DB" ]; then
    echo "🔧 Initializing SQLite database..."
    php /var/www/html/database/init_sqlite.php && echo "✅ Database initialized." || echo "⚠️ DB init script not found, will init on first request."
fi

# Ensure correct ownership after volume mounts
chown -R www-data:www-data /var/www/html/database /var/www/html/uploads 2>/dev/null || true

echo "🚀 Starting Apache on port ${PORT}..."
exec apache2-foreground
