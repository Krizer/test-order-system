import type {
  ApiErrorResponse,
  CreateOrderPayload,
  NotificationLogEntry,
  Order,
} from '../types/order';

// Все запросы идут через Gateway (Nginx), который маршрутизирует их
// к нужному микросервису. Фронтенд не знает и не должен знать,
// что /api/orders обслуживается PHP, а /api/notifications — Go.
const BASE_URL = '/api';

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const response = await fetch(`${BASE_URL}${path}`, {
    headers: { 'Content-Type': 'application/json' },
    ...options,
  });

  const data: unknown = await response.json().catch(() => null);

  if (!response.ok) {
    const message =
      (data as ApiErrorResponse | null)?.error ??
      `Запрос завершился со статусом ${response.status}`;
    throw new Error(message);
  }

  return data as T;
}

export const ordersApi = {
  list: (customerId?: number): Promise<Order[]> => {
    const query = customerId ? `?customer_id=${encodeURIComponent(customerId)}` : '';
    return request<Order[]>(`/orders${query}`);
  },

  get: (id: number): Promise<Order> => request<Order>(`/orders/${id}`),

  create: (payload: CreateOrderPayload): Promise<Order> =>
    request<Order>('/orders', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),

  confirm: (id: number): Promise<Order> =>
    request<Order>(`/orders/${id}/confirm`, { method: 'POST' }),

  cancel: (id: number): Promise<Order> =>
    request<Order>(`/orders/${id}/cancel`, { method: 'POST' }),
};

export const notificationsApi = {
  logs: (): Promise<NotificationLogEntry[]> => request<NotificationLogEntry[]>('/notifications/logs'),
};
