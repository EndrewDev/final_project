FROM roundcube/roundcubemail:latest

# Copia os arquivos de configuração
COPY config/config.inc.php /var/www/html/config/config.inc.php

# Define permissões
RUN chown -R www-data:www-data /var/www/html/config
