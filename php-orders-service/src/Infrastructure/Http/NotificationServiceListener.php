<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Event\DomainEventInterface;
use App\Domain\Event\EventListenerInterface;

/**
 * Конкретный "наблюдатель": при событии заказа делает HTTP-запрос
 * в независимый Go-сервис нотификаций. Это и есть связь между
 * двумя микросервисами — через события, а не через прямые вызовы
 * бизнес-логики друг друга.
 */
final class NotificationServiceListener implements EventListenerInterface
{
    public function __construct(private readonly string $notificationServiceUrl)
    {
    }

    public function handle(DomainEventInterface $event): void
    {
        $payload = json_encode([
            'event' => $event->getName(),
            'data' => $event->toPayload(),
        ], JSON_THROW_ON_ERROR);

        $ch = curl_init($this->notificationServiceUrl . '/api/notifications/events');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2, // не блокируем ответ пользователю надолго
            CURLOPT_CONNECTTIMEOUT => 1,
        ]);

        curl_exec($ch);

        if (curl_errno($ch)) {
            // Логируем, но не бросаем исключение наружу — EventDispatcher
            // и так оборачивает листенеры в try/catch, это вторая линия защиты.
            error_log('NotificationServiceListener: curl error - ' . curl_error($ch));
        }

        curl_close($ch);
    }
}
