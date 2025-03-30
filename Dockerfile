FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html/personal-drive

# Install system dependencies
RUN apt-get update && apt-get install -y \
    unzip curl sudo sqlite3 libsqlite3-dev ffmpeg \
    libjpeg-dev libpng-dev libwebp-dev libfreetype6-dev zlib1g-dev libzip-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install pdo pdo_sqlite gd zip exif \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Node.js & clean up
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Copy app files
COPY . .

# Set up environment file
RUN cp .env.example .env

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer




# Create new directory and set permissions
RUN mkdir /var/www/html/personal-drive-storage-folder \
&& chown -R www-data:www-data storage bootstrap/cache database /var/www/html/personal-drive-storage-folder \
&& chmod -R 770 storage bootstrap/cache database && touch database/database.sqlite



# Install PHP dependencies
RUN composer install --no-interaction --prefer-dist --no-dev && rm -rf /root/.composer

# Install npm dependencies
RUN npm ci && npm run build

# Clear & cache config
RUN php artisan config:clear && php artisan config:cache

# Copy Apache config
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# remove vite dev file, just in case.
RUN rm -f public/hot

# Enable Apache modules
RUN a2enmod rewrite

# Expose ports
EXPOSE 80

# Upload limits
RUN echo "upload_max_filesize=1000M\npost_max_size=1000M\nmax_file_uploads=100" > /usr/local/etc/php/conf.d/uploads.ini

# Entrypoint script will handle first-run setup
COPY docker/entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["entrypoint.sh"]

# Default command
CMD ["apache2-foreground"]