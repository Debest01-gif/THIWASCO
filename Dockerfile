FROM php:8.2-apache

# Enable Apache modules
RUN a2enmod rewrite headers

# Install required system dependencies & PHP extensions (PDO MySQL, PDO SQLite, GD)
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite gd \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Create required upload directories
RUN mkdir -p /var/www/html/uploads/meter_photos \
             /var/www/html/uploads/inspection_photos \
             /var/www/html/uploads/profile_photos \
    && echo "Options -Indexes" > /var/www/html/uploads/.htaccess

# Set proper permissions for Apache user
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/uploads \
    && chmod -R 775 /var/www/html/database

# Allow .htaccess overrides in web root
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
    && sed -i 's/AllowOverride none/AllowOverride All/g' /etc/apache2/apache2.conf

# Add ServerName to avoid warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Add entrypoint script to support dynamic PORT on Render
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 10000

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
