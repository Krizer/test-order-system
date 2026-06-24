-- Миграция: таблица логов нотификаций для Go Notification Service
-- Это отдельная таблица в той же базе orders_db — каждый сервис
-- владеет своими таблицами и не лезет в таблицы другого сервиса
-- (orders/order_items принадлежат PHP-сервису, notification_logs — Go).

CREATE TABLE IF NOT EXISTS notification_logs (
    id SERIAL PRIMARY KEY,
    event_name VARCHAR(100) NOT NULL,
    channel VARCHAR(50) NOT NULL,
    payload TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_notification_logs_event_name ON notification_logs(event_name);
