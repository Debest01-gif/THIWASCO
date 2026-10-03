#!/bin/bash
set -e

# Render binds to a custom dynamic PORT (e.g., 10000)
PORT="${PORT:-80}"

sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT}/" /etc/apache2/sites-available/000-default.conf

# Execute Apache in foreground
exec apache2-foreground
