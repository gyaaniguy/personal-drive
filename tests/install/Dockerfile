# Base image for the install smoke test: a bare server with the documented
# requirements (PHP + sqlite, composer, node/npm) and a normal, non-root
# deploy user with passwordless sudo - the situation setup.sh is written for.
FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip curl sudo ca-certificates gnupg sqlite3 \
        libsqlite3-dev libjpeg-dev libpng-dev libwebp-dev libfreetype6-dev zlib1g-dev libzip-dev \
        ffmpeg \
    && mkdir -p /etc/apt/keyrings \
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_20.x nodistro main" > /etc/apt/sources.list.d/nodesource.list \
    && apt-get update && apt-get install -y --no-install-recommends nodejs \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install pdo pdo_sqlite gd zip exif \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer \
    && apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Deploy user: own the app, member of no special groups, sudo without a password.
RUN useradd -m -s /bin/bash deploy \
    && echo 'deploy ALL=(ALL) NOPASSWD:ALL' > /etc/sudoers.d/deploy \
    && chmod 0440 /etc/sudoers.d/deploy \
    && chmod 755 /home/deploy

# Apache serves the deploy user's checkout as www-data, so the test hits the
# real owner/group split (deploy:www-data) instead of artisan serve's.
RUN printf '%s\n' \
    '<VirtualHost *:80>' \
    '    DocumentRoot /home/deploy/app/public' \
    '    <Directory /home/deploy/app/public>' \
    '        Options FollowSymLinks' \
    '        AllowOverride All' \
    '        Require all granted' \
    '    </Directory>' \
    '    ErrorLog ${APACHE_LOG_DIR}/error.log' \
    '    CustomLog ${APACHE_LOG_DIR}/access.log combined' \
    '</VirtualHost>' > /etc/apache2/sites-available/000-default.conf \
    && a2enmod rewrite

# WORKDIR would create this as root; the deploy user must own its own checkout.
RUN mkdir -p /home/deploy/app && chown deploy:deploy /home/deploy/app
WORKDIR /home/deploy/app
