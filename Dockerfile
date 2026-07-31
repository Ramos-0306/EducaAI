FROM php:8.2-apache

# Extensões de sistema necessárias para PDO PostgreSQL
RUN apt-get update && apt-get install -y \
        libpq-dev \
        unzip \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && a2enmod rewrite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Corrige bug conhecido das imagens php:apache (Bookworm): o apt-get acima
# deixa dois MPMs ativos em simultâneo (mpm_event + mpm_prefork), o que
# impede o Apache de arrancar. mod_php exige mpm_prefork, não mpm_event.
RUN a2dismod mpm_event 2>/dev/null; a2enmod mpm_prefork

# Permite que ficheiros .htaccess funcionem (regravação de URLs)
RUN { \
        echo '<Directory /var/www/html/>'; \
        echo '    AllowOverride All'; \
        echo '</Directory>'; \
    } >> /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY . /var/www/html/

# O Railway injeta a porta em runtime via $PORT; o Apache por defeito
# só sabe escutar na 80, por isso ajustamos isso no arranque do container
RUN printf '#!/bin/bash\nset -e\nPORT="${PORT:-80}"\nsed -i "s/80/${PORT}/g" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf\nexec apache2-foreground\n' > /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
