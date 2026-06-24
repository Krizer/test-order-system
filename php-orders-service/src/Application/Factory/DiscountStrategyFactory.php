<?php

declare(strict_types=1);

namespace App\Application\Factory;

use App\Domain\Strategy\DiscountStrategyInterface;
use App\Domain\Strategy\RegularDiscountStrategy;
use App\Domain\Strategy\VipDiscountStrategy;
use App\Domain\Strategy\WholesaleDiscountStrategy;
use InvalidArgumentException;

/**
 * Паттерн Factory Method.
 *
 * Прячет логику "какой класс создать" от вызывающего кода.
 * OrderService просто говорит "дай мне стратегию для tier=vip"
 * и не знает о существовании конкретных классов *DiscountStrategy.
 */
final class DiscountStrategyFactory
{
    private const SUPPORTED_TIERS = ['regular', 'vip', 'wholesale'];

    public function create(string $customerTier): DiscountStrategyInterface
    {
        return match ($customerTier) {
            'regular' => new RegularDiscountStrategy(),
            'vip' => new VipDiscountStrategy(),
            'wholesale' => new WholesaleDiscountStrategy(),
            default => throw new InvalidArgumentException(
                sprintf(
                    'Неизвестный тип клиента "%s". Поддерживаются: %s',
                    $customerTier,
                    implode(', ', self::SUPPORTED_TIERS)
                )
            ),
        };
    }
}
