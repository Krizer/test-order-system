import { useCallback, useEffect, useState } from 'react';
import { ordersApi } from './api/client';
import { CreateOrderForm } from './components/CreateOrderForm';
import { OrdersList } from './components/OrdersList';
import { NotificationsLog } from './components/NotificationsLog';

export default function App() {
  const [orders, setOrders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [refreshKey, setRefreshKey] = useState(0);

  const loadOrders = useCallback(() => {
    setLoading(true);
    setError(null);

    ordersApi
      .list()
      .then((data) => setOrders(data ?? []))
      .catch((err) => setError(err.message))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    loadOrders();
  }, [loadOrders, refreshKey]);

  function handleChanged() {
    setRefreshKey((k) => k + 1);
  }

  function handleCreated() {
    handleChanged();
  }

  return (
    <div className="app">
      <header className="app__header">
        <h1>Order System</h1>
        <p className="app__subtitle">
          PHP Orders Service + Go Notification Service · через Nginx Gateway
        </p>
      </header>

      <main className="app__layout">
        <section className="app__column">
          <CreateOrderForm onCreated={handleCreated} />
        </section>

        <section className="app__column app__column--wide">
          <h2 className="panel__title panel__title--standalone">Заказы</h2>
          <OrdersList orders={orders} loading={loading} error={error} onChanged={handleChanged} />
        </section>

        <section className="app__column">
          <NotificationsLog refreshKey={refreshKey} />
        </section>
      </main>
    </div>
  );
}
