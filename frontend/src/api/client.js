// Все запросы идут через Gateway (Nginx), который маршрутизирует их
// к нужному микросервису. Фронтенд не знает и не должен знать,
// что /api/orders обслуживается PHP, а /api/notifications — Go.
const BASE_URL = '/api';

async function request(path, options = {}) {
  const response = await fetch(`${BASE_URL}${path}`, {
    headers: { 'Content-Type': 'application/json' },
    ...options,
  });

  const data = await response.json().catch(() => null);

  if (!response.ok) {
    const message = data?.error || `Запрос завершился со статусом ${response.status}`;
    throw new Error(message);
  }

  return data;
}

export const ordersApi = {
  list: (customerId) => {
    const query = customerId ? `?customer_id=${encodeURIComponent(customerId)}` : '';
    return request(`/orders${query}`);
  },

  get: (id) => request(`/orders/${id}`),

  create: (payload) =>
    request('/orders', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),

  confirm: (id) => request(`/orders/${id}/confirm`, { method: 'POST' }),

  cancel: (id) => request(`/orders/${id}/cancel`, { method: 'POST' }),
};

export const notificationsApi = {
  logs: () => request('/notifications/logs'),
};
