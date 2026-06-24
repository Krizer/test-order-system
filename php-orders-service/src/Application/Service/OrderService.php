<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Application\DTO\CreateOrderRequest;
use App\Application\Factory\DiscountStrategyFactory;
use App\Domain\Entity\Order;
use App\Domain\Entity\OrderItem;
use App\Domain\Event\OrderCreatedEvent;
use App\Domain\Repository\OrderRepositoryInterface;
use App\Infrastructure\EventDispatcher\EventDispatcher;

/**
 * Сервис приложения — точка, где встречаются все паттерны.
 *
 * OrderService ничего не создаёт напрямую и не знает деталей реализации:
 * - получает готовую DiscountStrategyFactory и репозиторий через конструктор
 *   (Dependency Injection — зависимости приходят снаружи, не через `new` внутри);
 * - просит Factory вернуть нужную Strategy для скидки;
 * - просит Repository сохранить заказ, не зная, что это PostgreSQL;
 * - после сохранения публикует событие через EventDispatcher (Observer),
 *   не зная и не заботясь о том, кто на это событие подписан
 *   (в нашем случае — Go-сервис нотификаций через HTTP).
 *
 * Если завтра появится Kafka вместо HTTP-вебхука, поменяется только
 * Infrastructure-слой — этот класс останется нетронутым.
 */
final class OrderService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orders,
        private readonly DiscountStrategyFactory $discountStrategyFactory,
        private readonly EventDispatcher $eventDispatcher,
    ) {
    }

    public function createOrder(CreateOrderRequest $request): Order
    {
        $order = new Order(
            id: null,
            customerId: $request->customerId,
            customerTier: $request->customerTier,
        );

        foreach ($request->items as $itemData) {
            $order->addItem(new OrderItem(
                productName: (string) $itemData['product_name'],
                quantity: (int) $itemData['quantity'],
                unitPrice: (float) $itemData['unit_price'],
            ));
        }

        // Factory скрывает, какой именно класс стратегии создаётся под tier.
        $strategy = $this->discountStrategyFactory->create($request->customerTier);

        // Strategy скрывает, как именно считается скидка для этого tier.
        $discount = $strategy->calculateDiscount($order->getSubtotal());
        $order->applyDiscount($discount);

        $savedOrder = $this->orders->save($order);

        // Observer: публикуем факт "заказ создан", не зная подписчиков.
        $this->eventDispatcher->dispatch(new OrderCreatedEvent($savedOrder));

        return $savedOrder;
    }

    public function confirmOrder(int $orderId): Order
    {
        $order = $this->getOrderOrFail($orderId);
        $order->confirm();

        return $this->orders->save($order);
    }

    public function cancelOrder(int $orderId): Order
    {
        $order = $this->getOrderOrFail($orderId);
        $order->cancel();

        return $this->orders->save($order);
    }

    public function getOrder(int $orderId): Order
    {
        return $this->getOrderOrFail($orderId);
    }

    /**
     * @return Order[]
     */
    public function getOrdersByCustomer(int $customerId): array
    {
        return $this->orders->findByCustomerId($customerId);
    }

    /**
     * @return Order[]
     */
    public function getAllOrders(): array
    {
        return $this->orders->findAll();
    }

    private function getOrderOrFail(int $orderId): Order
    {
        return $this->orders->findById($orderId)
            ?? throw new \DomainException("Заказ #{$orderId} не найден");
    }
}
