<?php

declare(strict_types=1);

namespace App\Application\DTO;

/**
 * DTO (Data Transfer Object) для входящего запроса на создание заказа.
 *
 * Это не доменная сущность — у неё нет бизнес-правил, только "сырые"
 * данные из HTTP-запроса, провалидированные на уровне типов.
 * Контроллер парсит JSON в этот объект и отдаёт его в OrderService,
 * который уже знает, как превратить DTO в доменный Order.
 */
final class CreateOrderRequest
{
    /**
     * @param array<int, array{product_name: string, quantity: int, unit_price: float}> $items
     */
    public function __construct(
        public readonly int $customerId,
        public readonly string $customerTier,
        public readonly array $items,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['customer_id'], $data['customer_tier'], $data['items'])) {
            throw new \InvalidArgumentException(
                'Обязательные поля: customer_id, customer_tier, items'
            );
        }

        if (!is_array($data['items']) || empty($data['items'])) {
            throw new \InvalidArgumentException('items должен быть непустым массивом');
        }

        return new self(
            customerId: (int) $data['customer_id'],
            customerTier: (string) $data['customer_tier'],
            items: $data['items'],
        );
    }
}
