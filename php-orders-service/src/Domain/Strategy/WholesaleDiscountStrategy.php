<?php

declare(strict_types=1);

namespace App\Domain\Strategy;

final class WholesaleDiscountStrategy implements DiscountStrategyInterface
{
    private const DISCOUNT_RATE = 0.30; // 30%
    private const MIN_SUBTOTAL_FOR_DISCOUNT = 500.0;

    public function calculateDiscount(float $subtotal): float
    {
        if ($subtotal < self::MIN_SUBTOTAL_FOR_DISCOUNT) {
            return 0.0;
        }

        return $subtotal * self::DISCOUNT_RATE;
    }
}
