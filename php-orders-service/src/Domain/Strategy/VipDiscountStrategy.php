<?php

declare(strict_types=1);

namespace App\Domain\Strategy;

final class VipDiscountStrategy implements DiscountStrategyInterface
{
    private const DISCOUNT_RATE = 0.15; // 15%

    public function calculateDiscount(float $subtotal): float
    {
        return $subtotal * self::DISCOUNT_RATE;
    }
}
