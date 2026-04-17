FROM php:8.2-apache

# Configurar o Apache para usar a porta dinâmica exigida pelo Render
RUN sed -i "s/80/\${PORT}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Copiar os ficheiros do site para o servidor
COPY . /var/www/html/

# Ativar as permissões corretas
RUN chown -R www-data:www-data /var/www/html
