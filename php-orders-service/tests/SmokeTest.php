<?php

declare(strict_types=1);

/**
 * Лёгкий smoke-тест бизнес-логики без БД, без HTTP и без PHPUnit —
 * проверяет, что Domain + Strategy + Factory + Observer работают
 * вместе правильно. Запуск: composer install && php tests/SmokeTest.php
 */

require __DIR__ . '/../vendor/autoload.php';

use App\Application\Factory\DiscountStrategyFactory;
use App\Domain\Entity\Order;
use App\Domain\Entity\OrderItem;
use App\Domain\Event\OrderCreatedEvent;
use App\Infrastructure\EventDispatcher\EventDispatcher;
use App\Domain\Event\DomainEventInterface;
use App\Domain\Event\EventListenerInterface;

// Фейковый листенер вместо реального HTTP-вызова в Go — для smoke-теста.
final class FakeListener implements EventListenerInterface
{
    public bool $called = false;
    public ?array $payload = null;

    public function handle(DomainEventInterface $event): void
    {
        $this->called = true;
        $this->payload = $event->toPayload();
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        echo "FAIL: $message\n";
        exit(1);
    }
    echo "OK: $message\n";
}

// --- Тест 1: VIP скидка считается через Strategy, выбранную через Factory ---
$order = new Order(id: 1, customerId: 42, customerTier: 'vip');
$order->addItem(new OrderItem('Keyboard', 2, 100.0));   // 200
$order->addItem(new OrderItem('Mouse', 1, 50.0));       // 50
// subtotal = 250

$factory = new DiscountStrategyFactory();
$strategy = $factory->create($order->getCustomerTier());
$discount = $strategy->calculateDiscount($order->getSubtotal());
$order->applyDiscount($discount);

assertTrue($order->getSubtotal() === 250.0, 'subtotal = 250');
assertTrue(abs($discount - 37.5) < 0.001, 'VIP скидка 15% от 250 = 37.5');
assertTrue(abs($order->getTotal() - 212.5) < 0.001, 'итог с учётом скидки = 212.5');

// --- Тест 2: Wholesale ниже порога не получает скидку ---
$smallOrder = new Order(id: 2, customerId: 7, customerTier: 'wholesale');
$smallOrder->addItem(new OrderItem('Cable', 1, 10.0));
$wholesaleStrategy = $factory->create('wholesale');
$smallDiscount = $wholesaleStrategy->calculateDiscount($smallOrder->getSubtotal());
assertTrue($smallDiscount === 0.0, 'Wholesale ниже 500 не получает скидку');

// --- Тест 3: Observer - событие order.created долетает до листенера ---
$dispatcher = new EventDispatcher();
$listener = new FakeListener();
$dispatcher->subscribe('order.created', $listener);
$dispatcher->dispatch(new OrderCreatedEvent($order));

assertTrue($listener->called === true, 'Listener вызван через EventDispatcher');
assertTrue($listener->payload['customer_id'] === 42, 'Payload содержит правильный customer_id');
assertTrue(abs($listener->payload['total'] - 212.5) < 0.001, 'Payload содержит правильный total');

// --- Тест 4: нельзя подтвердить пустой заказ ---
$emptyOrder = new Order(id: 3, customerId: 1, customerTier: 'regular');
$threw = false;
try {
    $emptyOrder->confirm();
} catch (\DomainException $e) {
    $threw = true;
}
assertTrue($threw, 'Подтверждение пустого заказа бросает DomainException');

// --- Тест 5: неизвестный tier в Factory бросает исключение ---
$threw2 = false;
try {
    $factory->create('unknown_tier');
} catch (\InvalidArgumentException $e) {
    $threw2 = true;
}
assertTrue($threw2, 'Factory бросает исключение для неизвестного tier');

echo "\nВСЕ ТЕСТЫ ПРОШЛИ\n";
