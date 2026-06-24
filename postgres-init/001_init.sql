-- Этот файл автоматически выполняется при первом старте контейнера postgres
-- (через стандартный механизм /docker-entrypoint-initdb.d/).
--
-- Каждый микросервис "владеет" своими таблицами:
--   orders, order_items       -> PHP Orders Service
--   notification_logs         -> Go Notification Service
-- Они живут в одной базе ради простоты мини-проекта, но логически
-- разделены — ни один сервис не лезет в таблицы другого.

CREATE TABLE IF NOT EXISTS orders (
    id SERIAL PRIMARY KEY,
    customer_id INTEGER NOT NULL,
    customer_tier VARCHAR(20) NOT NULL CHECK (customer_tier IN ('regular', 'vip', 'wholesale')),
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'confirmed', 'shipped', 'cancelled')),
    discount_amount NUMERIC(12, 2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS order_items (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    product_name VARCHAR(255) NOT NULL,
    quantity INTEGER NOT NULL CHECK (quantity > 0),
    unit_price NUMERIC(12, 2) NOT NULL CHECK (unit_price >= 0)
);

CREATE INDEX IF NOT EXISTS idx_orders_customer_id ON orders(customer_id);
CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items(order_id);

CREATE TABLE IF NOT EXISTS notification_logs (
    id SERIAL PRIMARY KEY,
    event_name VARCHAR(100) NOT NULL,
    channel VARCHAR(50) NOT NULL,
    payload TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_notification_logs_event_name ON notification_logs(event_name);
