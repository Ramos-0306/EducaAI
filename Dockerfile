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
# (Esta limpeza é repetida também no start.sh, porque o Railway reinicia o
# MESMO container em vez de recriar um novo a partir da imagem, e por isso
# não podemos confiar apenas no estado gerado durante o build.)
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

# Templates imutáveis com um marcador único (__PORT__) em vez da porta fixa.
# O start.sh gera ports.conf/000-default.conf SEMPRE a partir destes
# templates (nunca edita o ficheiro já gerado), por isso é seguro correr
# o script várias vezes seguidas sem duplicar/corromper nada.
RUN sed 's/Listen 80$/Listen __PORT__/' /etc/apache2/ports.conf > /etc/apache2/ports.conf.template \
    && sed 's/\*:80>/*:__PORT__>/' /etc/apache2/sites-available/000-default.conf > /etc/apache2/sites-available/000-default.conf.template

RUN printf '#!/bin/bash\nset -e\n\nPORT="${PORT:-80}"\necho "PORT=$PORT"\n\n# Regenera sempre a partir dos templates originais (idempotente)\nsed "s/__PORT__/${PORT}/g" /etc/apache2/ports.conf.template > /etc/apache2/ports.conf\nsed "s/__PORT__/${PORT}/g" /etc/apache2/sites-available/000-default.conf.template > /etc/apache2/sites-available/000-default.conf\n\n# Garante sempre um unico MPM ativo, independentemente do estado anterior\nrm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf \\\n      /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf\na2enmod mpm_prefork >/dev/null\n\napache2ctl configtest\nexec apache2-foreground\n' > /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
