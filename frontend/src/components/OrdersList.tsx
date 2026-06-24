import { ordersApi } from '../api/client';
import type { Order, OrderStatus } from '../types/order';

const STATUS_LABELS: Record<OrderStatus, string> = {
  pending: 'Ожидает',
  confirmed: 'Подтверждён',
  shipped: 'Отправлен',
  cancelled: 'Отменён',
};

interface OrdersListProps {
  orders: Order[];
  loading: boolean;
  error: string | null;
  onChanged: () => void;
}

export function OrdersList({ orders, loading, error, onChanged }: OrdersListProps) {
  async function handleConfirm(id: number) {
    await ordersApi.confirm(id);
    onChanged();
  }

  async function handleCancel(id: number) {
    await ordersApi.cancel(id);
    onChanged();
  }

  if (loading) {
    return <p className="hint">Загружаем заказы…</p>;
  }

  if (error) {
    return <p className="error">{error}</p>;
  }

  if (orders.length === 0) {
    return <p className="hint">Заказов пока нет — создайте первый слева.</p>;
  }

  return (
    <div className="orders-list">
      {orders.map((order) => (
        <article className="order-card" key={order.id}>
          <header className="order-card__header">
            <div>
              <span className="order-card__id">Заказ #{order.id}</span>
              <span className="order-card__customer">
                клиент {order.customer_id} · {order.customer_tier}
              </span>
            </div>
            <span className={`status status--${order.status}`}>
              {STATUS_LABELS[order.status] ?? order.status}
            </span>
          </header>

          <ul className="order-card__items">
            {order.items.map((item, i) => (
              <li key={i}>
                {item.product_name} × {item.quantity} — {item.total.toFixed(2)} ₸
              </li>
            ))}
          </ul>

          <footer className="order-card__footer">
            <div className="order-card__totals">
              <span>Подытог: {order.subtotal.toFixed(2)} ₸</span>
              {order.discount_amount > 0 && (
                <span className="order-card__discount">
                  Скидка: −{order.discount_amount.toFixed(2)} ₸
                </span>
              )}
              <strong>Итого: {order.total.toFixed(2)} ₸</strong>
            </div>

            {order.status === 'pending' && (
              <div className="order-card__actions">
                <button className="secondary-button" onClick={() => handleConfirm(order.id)}>
                  Подтвердить
                </button>
                <button className="ghost-button" onClick={() => handleCancel(order.id)}>
                  Отменить
                </button>
              </div>
            )}
          </footer>
        </article>
      ))}
    </div>
  );
}
