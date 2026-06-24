<?php

declare(strict_types=1);

namespace App\Domain\Strategy;

/**
 * Паттерн Strategy.
 *
 * Каждый тип клиента считает скидку по-своему. OrderService не знает
 * и не должен знать, ПОЧЕМУ скидка такая — он просто просит стратегию
 * посчитать сумму. Добавление нового типа клиента не требует правки
 * существующего кода (Open/Closed Principle).
 */
interface DiscountStrategyInterface
{
    public function calculateDiscount(float $subtotal): float;
}
