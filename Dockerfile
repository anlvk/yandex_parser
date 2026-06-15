FROM php:8.4-fpm

# Установка системных зависимостей для компиляции и работы Postgres
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libpq-dev \
    postgresql-client

# Очистка кэша менеджера пакетов
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Важный шаг для PHP 8.4: явное конфигурирование и установка драйверов pgsql
RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-install pdo_pgsql pgsql mbstring exif pcntl bcmath gd

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www
