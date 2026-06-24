<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Order;

/**
 * Паттерн Repository.
 *
 * Домен и сервисный слой работают с заказами через этот интерфейс
 * и не знают, что под капотом — PostgreSQL, MySQL или массив в памяти.
 * Это даёт возможность подменить реализацию (например, для тестов
 * использовать InMemoryOrderRepository) без единой правки в OrderService.
 */
interface OrderRepositoryInterface
{
    public function findById(int $id): ?Order;

    /**
     * @return Order[]
     */
    public function findByCustomerId(int $customerId): array;

    /**
     * @return Order[]
     */
    public function findAll(): array;

    public function save(Order $order): Order;
}
