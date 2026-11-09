# ==============================================================================
# DOCTOR CONNECT - OPENSHIFT COMPLIANT DOCKERFILE
# Compatible with Red Hat OpenShift Restricted Security Context Constraints (SCC)
# ==============================================================================

FROM php:8.2-apache

# Install PDO MySQL and required PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite module
RUN a2enmod rewrite headers

# Reconfigure Apache to listen on port 8080 (Non-root requirement for OpenShift)
RUN sed -i 's/80/8080/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# Set working directory
WORKDIR /var/www/html

# Copy application source code
COPY . /var/www/html/

# Create entrypoint script to run migrations and start Apache
RUN echo '#!/bin/bash\n\
echo "Waiting for MySQL database to become ready..."\n\
php -r '\''\n\
$host = getenv("DB_HOST") ?: "mysql";\n\
$port = getenv("DB_PORT") ?: "3306";\n\
for ($i=0; $i<60; $i++) {\n\
    $fp = @fsockopen($host, $port, $errno, $errstr, 2);\n\
    if ($fp) { fclose($fp); exit(0); }\n\
    sleep(2);\n\
}\n\
exit(1);\n\
'\''\n\
echo "MySQL is reachable. Executing database migrations & seeding..."\n\
php /var/www/html/database/seed.php\n\
echo "Starting Apache Web Server on port 8080..."\n\
exec apache2-foreground\n\
' > /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

# OpenShift Non-Root Group 0 Permissions
# OpenShift runs containers with an arbitrary UID that belongs to root group (GID 0)
RUN mkdir -p /var/lib/php/sessions \
    && chgrp -R 0 /var/www/html /var/log/apache2 /var/run/apache2 /etc/apache2 /var/lib/php/sessions \
    && chmod -R g=u /var/www/html /var/log/apache2 /var/run/apache2 /etc/apache2 /var/lib/php/sessions \
    && chmod -R 777 /var/lib/php/sessions

EXPOSE 8080

USER 1001

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
