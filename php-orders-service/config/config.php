<?php

declare(strict_types=1);

/**
 * Конфигурация читается из переменных окружения (заданных в docker-compose.yml),
 * а не хардкодится — это позволяет менять окружение (dev/staging/prod)
 * без изменения кода.
 */
return [
    'db' => [
        'host' => getenv('DB_HOST') ?: 'postgres',
        'port' => getenv('DB_PORT') ?: '5432',
        'name' => getenv('DB_NAME') ?: 'orders_db',
        'user' => getenv('DB_USER') ?: 'orders_user',
        'password' => getenv('DB_PASSWORD') ?: 'orders_password',
    ],
    'notification_service' => [
        'url' => getenv('NOTIFICATION_SERVICE_URL') ?: 'http://notification-service:8080',
    ],
];
