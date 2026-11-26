FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip curl postgresql-client libpq-dev libzip-dev libicu-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" gd intl pdo_pgsql zip bcmath \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /opt/drupal
RUN composer create-project 'drupal/recommended-project:^11' . --no-interaction --no-progress \
 && composer require 'drush/drush:^13' 'drupal/commerce:^3' -W --no-interaction --no-progress
ENV APACHE_DOCUMENT_ROOT=/opt/drupal/web
RUN sed -ri -e 's!/var/www/html!/opt/drupal/web!g' \
    /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && printf '\n<Directory /opt/drupal/web>\nOptions FollowSymLinks\nAllowOverride All\nRequire all granted\n</Directory>\n' >> /etc/apache2/apache2.conf \
 && echo 'ServerName drupal' >> /etc/apache2/apache2.conf
COPY scripts/init-drupal.sh /usr/local/bin/init-drupal.sh
COPY scripts/init-content.php /usr/local/bin/init-content.php
COPY modules/groupec_n8n/ /opt/drupal/web/modules/custom/groupec_n8n/
RUN chmod 0755 /usr/local/bin/init-drupal.sh
ENTRYPOINT ["/usr/local/bin/init-drupal.sh"]
