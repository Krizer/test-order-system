# --- Stage 1: сборка расширений (тут нужны компиляторы) ---
FROM php:8.3-cli-alpine AS builder

# postgresql-dev даёт libpq-fe.h/pg_config для pdo_pgsql.
# curl-dev даёт заголовки для ext curl.
# Ставим ДО docker-php-ext-install, иначе сборка падает
# с "Cannot find libpq-fe.h" / "Cannot find curl".
RUN apk add --no-cache postgresql-dev curl-dev \
    && docker-php-ext-install pdo pdo_pgsql curl

# --- Stage 2: финальный образ без компиляторов ---
FROM php:8.3-cli-alpine

# В рантайме нужны только сами библиотеки (.so), без -dev пакетов
# с заголовками и без gcc/g++/make — это и есть весь смысл
# multi-stage здесь: меньше образ, меньше поверхность для сбоев apk.
RUN apk add --no-cache libpq libcurl

COPY --from=builder /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Composer берём готовым бинарником из официального образа, а не через
# `apk add composer` — пакет composer в Alpine community зависит от
# системного php85 и тянет за собой конфликт версий с нашим php:8.3.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json ./
COPY src/ ./src/
COPY public/ ./public/
COPY config/ ./config/

# В проекте пока нет внешних пакетов — composer install здесь только
# генерирует PSR-4 автозагрузчик из composer.json, в сеть/packagist
# не ходит (--no-scripts на случай, если позже появятся хуки).
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
