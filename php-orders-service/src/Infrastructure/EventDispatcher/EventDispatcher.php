<?php

declare(strict_types=1);

namespace App\Infrastructure\EventDispatcher;

use App\Domain\Event\DomainEventInterface;
use App\Domain\Event\EventListenerInterface;

/**
 * Паттерн Observer.
 *
 * OrderService не вызывает Go-сервис напрямую и не знает,
 * сколько и какие "наблюдатели" подписаны на событие order.created.
 * Сегодня это один HTTP-листенер для нотификаций, завтра можно
 * добавить ещё, например листенер для аналитики — без изменений
 * в OrderService.
 */
final class EventDispatcher
{
    /** @var array<string, EventListenerInterface[]> */
    private array $listeners = [];

    public function subscribe(string $eventName, EventListenerInterface $listener): void
    {
        $this->listeners[$eventName][] = $listener;
    }

    public function dispatch(DomainEventInterface $event): void
    {
        $eventName = $event->getName();

        foreach ($this->listeners[$eventName] ?? [] as $listener) {
            // Намеренно не даём ошибке в одном листенере сломать ответ API:
            // нотификация — это побочный эффект, а не часть транзакции заказа.
            try {
                $listener->handle($event);
            } catch (\Throwable $e) {
                error_log(sprintf(
                    'Listener %s failed for event %s: %s',
                    get_class($listener),
                    $eventName,
                    $e->getMessage()
                ));
            }
        }
    }
}
