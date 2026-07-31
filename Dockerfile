FROM php:8.2-apache

# Extensões de sistema necessárias para PDO PostgreSQL
RUN apt-get update && apt-get install -y \
        libpq-dev \
        unzip \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && a2enmod rewrite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Corrige bug conhecido das imagens php:apache (Bookworm): o apt-get acima
# deixa mais do que um MPM ativo em simultâneo, o que impede o Apache de
# arrancar. mod_php exige mpm_prefork — removemos à força qualquer outro
# symlink de MPM em mods-enabled e garantimos que só o prefork fica ativo.
RUN rm -f /etc/apache2/mods-enabled/mpm_event.load \
           /etc/apache2/mods-enabled/mpm_event.conf \
           /etc/apache2/mods-enabled/mpm_worker.load \
           /etc/apache2/mods-enabled/mpm_worker.conf \
    && a2enmod mpm_prefork \
    && apache2ctl -M 2>&1 | grep -i mpm

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
RUN printf '#!/bin/bash\nset -e\nPORT="${PORT:-80}"\necho "=== PORT recebido do Railway ==="\necho "PORT=$PORT"\nsed -i "s/80/${PORT}/g" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf\necho "=== ports.conf apos sed ==="\ncat /etc/apache2/ports.conf\necho "=== 000-default.conf apos sed ==="\ncat /etc/apache2/sites-available/000-default.conf\necho "=== mods-enabled (mpm) ==="\nls -la /etc/apache2/mods-enabled | grep -i mpm\necho "=== Modulos MPM carregados ==="\napache2ctl -M 2>&1 | grep -i mpm || true\necho "=== apache2ctl configtest (config real de arranque) ==="\napache2ctl configtest\nexec apache2-foreground\n' > /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

# Verificação de build: testa a config original (antes do sed de runtime).
# A verificação que importa de facto acontece dentro do start.sh, já com a
# porta substituída, e os resultados vão aparecer nos Deploy Logs.
RUN apache2ctl configtest

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
