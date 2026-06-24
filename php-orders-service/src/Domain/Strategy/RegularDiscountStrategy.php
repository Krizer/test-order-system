<?php

declare(strict_types=1);

namespace App\Domain\Strategy;

final class RegularDiscountStrategy implements DiscountStrategyInterface
{
    public function calculateDiscount(float $subtotal): float
    {
        return 0.0;
    }
}
