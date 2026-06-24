import { useEffect, useState } from 'react';
import { notificationsApi } from '../api/client';
import type { NotificationLogEntry } from '../types/order';

/**
 * Этот компонент — наглядное доказательство того, что Observer-паттерн
 * реально работает между сервисами: каждый раз, когда PHP создаёт заказ,
 * EventDispatcher дёргает Go-сервис по HTTP, а Go записывает это в свою
 * таблицу notification_logs. Здесь мы просто показываем эти записи.
 */
interface NotificationsLogProps {
  refreshKey: number;
}

export function NotificationsLog({ refreshKey }: NotificationsLogProps) {
  const [logs, setLogs] = useState<NotificationLogEntry[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    notificationsApi
      .logs()
      .then((data) => {
        if (!cancelled) setLogs(data ?? []);
      })
      .catch((err: unknown) => {
        if (!cancelled) {
          setError(err instanceof Error ? err.message : 'Не удалось загрузить журнал');
        }
      });

    return () => {
      cancelled = true;
    };
  }, [refreshKey]);

  return (
    <div className="panel">
      <h2 className="panel__title">Журнал уведомлений (Go-сервис)</h2>
      <p className="hint">
        Заполняется автоматически: PHP публикует событие <code>order.created</code>,
        Go-сервис его принимает и логирует здесь.
      </p>

      {error && <p className="error">{error}</p>}

      {!error && logs.length === 0 && (
        <p className="hint">Пока пусто — создайте заказ, чтобы увидеть событие.</p>
      )}

      <ul className="log-list">
        {logs.map((entry) => (
          <li key={entry.ID} className="log-item">
            <span className="log-item__event">{entry.EventName}</span>
            <span className="log-item__channel">канал: {entry.Channel}</span>
            <span className="log-item__time">
              {new Date(entry.CreatedAt).toLocaleTimeString('ru-RU')}
            </span>
          </li>
        ))}
      </ul>
    </div>
  );
}
