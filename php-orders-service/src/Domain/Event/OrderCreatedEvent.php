<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\Entity\Order;

final class OrderCreatedEvent implements DomainEventInterface
{
    public function __construct(private readonly Order $order)
    {
    }

    public function getName(): string
    {
        return 'order.created';
    }

    public function toPayload(): array
    {
        return [
            'order_id' => $this->order->getId(),
            'customer_id' => $this->order->getCustomerId(),
            'customer_tier' => $this->order->getCustomerTier(),
            'total' => $this->order->getTotal(),
            'status' => $this->order->getStatus()->value,
        ];
    }
}
