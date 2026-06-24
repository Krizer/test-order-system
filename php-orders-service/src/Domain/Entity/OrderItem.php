<?php

declare(strict_types=1);

namespace App\Domain\Entity;

final class OrderItem
{
    public function __construct(
        private readonly string $productName,
        private readonly int $quantity,
        private readonly float $unitPrice,
    ) {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Количество должно быть положительным');
        }

        if ($unitPrice < 0) {
            throw new \InvalidArgumentException('Цена не может быть отрицательной');
        }
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPrice(): float
    {
        return $this->unitPrice;
    }

    public function getTotal(): float
    {
        return $this->quantity * $this->unitPrice;
    }
}
