/**
 * Типы здесь — зеркало того, что реально отдают бэкенды по HTTP.
 * Источники истины:
 *   - OrderStatus, форма Order  -> php-orders-service/src/Http/OrderController.php (serializeOrder)
 *   - CustomerTier              -> php-orders-service/src/Domain/Entity/Order.php
 *   - NotificationLogEntry      -> go-notification-service/internal/domain/event.go
 */

export type OrderStatus = 'pending' | 'confirmed' | 'shipped' | 'cancelled';

export type CustomerTier = 'regular' | 'vip' | 'wholesale';

export interface OrderItem {
  product_name: string;
  quantity: number;
  unit_price: number;
  total: number;
}

export interface Order {
  id: number;
  customer_id: number;
  customer_tier: CustomerTier;
  status: OrderStatus;
  subtotal: number;
  discount_amount: number;
  total: number;
  created_at: string;
  items: OrderItem[];
}

/**
 * Тело запроса на создание заказа — то, что фронт отправляет в PHP.
 * Зеркало App\Application\DTO\CreateOrderRequest.
 */
export interface CreateOrderPayload {
  customer_id: number;
  customer_tier: CustomerTier;
  items: Array<{
    product_name: string;
    quantity: number;
    unit_price: number;
  }>;
}

/**
 * Запись журнала нотификаций от Go-сервиса.
 * Go отдаёт поля с большой буквы (стандартный json-маршалинг
 * экспортируемых полей структуры domain.NotificationLog).
 */
export interface NotificationLogEntry {
  ID: number;
  EventName: string;
  Channel: string;
  Payload: string;
  CreatedAt: string;
}

/** Унифицированная форма ошибки, которую отдают оба сервиса. */
export interface ApiErrorResponse {
  error: string;
}
