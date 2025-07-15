# Usa uma imagem oficial do PHP 8.2 com servidor Apache
FROM php:8.2-apache

# Define o diretório de trabalho padrão
WORKDIR /var/www/html

# Instala pacotes e extensões PHP essenciais para o Laravel
# Incluindo o driver do PostgreSQL (pdo_pgsql)
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-install pdo pdo_pgsql zip bcmath

# Habilita o mod_rewrite do Apache para as URLs amigáveis do Laravel
RUN a2enmod rewrite

# Copia o código da sua aplicação para o contêiner
COPY . .

# Instala o Composer a partir de sua imagem oficial
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Roda o composer install para baixar as dependências do Laravel
# --no-dev para pacotes de produção
RUN composer install --no-interaction --optimize-autoloader --no-dev

# Ajusta as permissões das pastas que o Laravel precisa escrever
RUN chown -R www-data:www-data storage bootstrap/cache

# Expõe a porta 80 do Apache, que o Render usará
EXPOSE 80
