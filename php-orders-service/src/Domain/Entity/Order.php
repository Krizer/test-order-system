<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Entity\OrderItem;
use App\Domain\Entity\OrderStatus;

/**
 * Доменная сущность заказа.
 *
 * Не знает ничего про БД, HTTP или фреймворки — только бизнес-правила.
 * Это намеренно: правила (нельзя отменить оплаченный заказ и т.п.)
 * живут в одном месте и не размазаны по контроллерам/репозиториям.
 */
final class Order
{
    /** @var OrderItem[] */
    private array $items = [];

    private OrderStatus $status;

    private float $discountAmount = 0.0;

    public function __construct(
        private readonly ?int $id,
        private readonly int $customerId,
        private readonly string $customerTier, // regular | vip | wholesale
        private readonly \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
    ) {
        $this->status = OrderStatus::PENDING;
    }

    public function addItem(OrderItem $item): void
    {
        if ($this->status !== OrderStatus::PENDING) {
            throw new \DomainException('Нельзя добавлять товары в заказ, который уже не в статусе PENDING');
        }

        $this->items[] = $item;
    }

    /**
     * Используется только репозиторием при восстановлении заказа из БД,
     * минуя бизнес-проверку статуса (заказ уже существовал с этими товарами).
     *
     * @internal
     */
    public function restoreItem(OrderItem $item): void
    {
        $this->items[] = $item;
    }

    /**
     * @return OrderItem[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getSubtotal(): float
    {
        return array_reduce(
            $this->items,
            fn (float $carry, OrderItem $item) => $carry + $item->getTotal(),
            0.0
        );
    }

    public function applyDiscount(float $amount): void
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('Сумма скидки не может быть отрицательной');
        }

        $this->discountAmount = $amount;
    }

    public function getDiscountAmount(): float
    {
        return $this->discountAmount;
    }

    public function getTotal(): float
    {
        return max(0.0, $this->getSubtotal() - $this->discountAmount);
    }

    public function confirm(): void
    {
        if (empty($this->items)) {
            throw new \DomainException('Нельзя подтвердить пустой заказ');
        }

        if ($this->status !== OrderStatus::PENDING) {
            throw new \DomainException("Невозможно подтвердить заказ в статусе {$this->status->value}");
        }

        $this->status = OrderStatus::CONFIRMED;
    }

    public function cancel(): void
    {
        if ($this->status === OrderStatus::SHIPPED) {
            throw new \DomainException('Нельзя отменить уже отправленный заказ');
        }

        $this->status = OrderStatus::CANCELLED;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerId(): int
    {
        return $this->customerId;
    }

    public function getCustomerTier(): string
    {
        return $this->customerTier;
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): void
    {
        $this->status = $status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
