<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Order;
use App\Domain\Entity\OrderItem;
use App\Domain\Entity\OrderStatus;
use App\Domain\Repository\OrderRepositoryInterface;
use PDO;

/**
 * Конкретная реализация Repository поверх PostgreSQL.
 *
 * Вся "грязная" работа с SQL и гидратацией строк в объекты живёт здесь,
 * изолированно от бизнес-логики.
 */
final class PostgresOrderRepository implements OrderRepositoryInterface
{
    public function __construct(private readonly PDO $connection)
    {
    }

    public function findById(int $id): ?Order
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM orders WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    public function findByCustomerId(int $customerId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM orders WHERE customer_id = :customer_id ORDER BY created_at DESC'
        );
        $stmt->execute(['customer_id' => $customerId]);

        return array_map(
            fn (array $row) => $this->hydrate($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function findAll(): array
    {
        $stmt = $this->connection->query('SELECT * FROM orders ORDER BY created_at DESC');

        return array_map(
            fn (array $row) => $this->hydrate($row),
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    public function save(Order $order): Order
    {
        $this->connection->beginTransaction();

        try {
            if ($order->getId() === null) {
                $orderId = $this->insertOrder($order);
            } else {
                $orderId = $order->getId();
                $this->updateOrder($order);
                $this->deleteItems($orderId);
            }

            $this->insertItems($orderId, $order->getItems());

            $this->connection->commit();

            return $this->findById($orderId)
                ?? throw new \RuntimeException('Не удалось перечитать только что сохранённый заказ');
        } catch (\Throwable $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    private function insertOrder(Order $order): int
    {
        $stmt = $this->connection->prepare(
            'INSERT INTO orders (customer_id, customer_tier, status, discount_amount, created_at)
             VALUES (:customer_id, :customer_tier, :status, :discount_amount, :created_at)
             RETURNING id'
        );

        $stmt->execute([
            'customer_id' => $order->getCustomerId(),
            'customer_tier' => $order->getCustomerTier(),
            'status' => $order->getStatus()->value,
            'discount_amount' => $order->getDiscountAmount(),
            'created_at' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function updateOrder(Order $order): void
    {
        $stmt = $this->connection->prepare(
            'UPDATE orders
             SET status = :status, discount_amount = :discount_amount
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $order->getId(),
            'status' => $order->getStatus()->value,
            'discount_amount' => $order->getDiscountAmount(),
        ]);
    }

    /**
     * @param OrderItem[] $items
     */
    private function insertItems(int $orderId, array $items): void
    {
        if (empty($items)) {
            return;
        }

        $stmt = $this->connection->prepare(
            'INSERT INTO order_items (order_id, product_name, quantity, unit_price)
             VALUES (:order_id, :product_name, :quantity, :unit_price)'
        );

        foreach ($items as $item) {
            $stmt->execute([
                'order_id' => $orderId,
                'product_name' => $item->getProductName(),
                'quantity' => $item->getQuantity(),
                'unit_price' => $item->getUnitPrice(),
            ]);
        }
    }

    private function deleteItems(int $orderId): void
    {
        $stmt = $this->connection->prepare('DELETE FROM order_items WHERE order_id = :order_id');
        $stmt->execute(['order_id' => $orderId]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Order
    {
        $order = new Order(
            id: (int) $row['id'],
            customerId: (int) $row['customer_id'],
            customerTier: $row['customer_tier'],
            createdAt: new \DateTimeImmutable($row['created_at']),
        );

        $order->setStatus(OrderStatus::from($row['status']));
        $order->applyDiscount((float) $row['discount_amount']);

        $itemsStmt = $this->connection->prepare(
            'SELECT * FROM order_items WHERE order_id = :order_id'
        );
        $itemsStmt->execute(['order_id' => $row['id']]);

        foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $itemRow) {
            $order->restoreItem(new OrderItem(
                productName: $itemRow['product_name'],
                quantity: (int) $itemRow['quantity'],
                unitPrice: (float) $itemRow['unit_price'],
            ));
        }

        return $order;
    }
}
