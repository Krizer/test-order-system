<?php

declare(strict_types=1);

use App\Application\Factory\DiscountStrategyFactory;
use App\Application\Service\OrderService;
use App\Domain\Repository\OrderRepositoryInterface;
use App\Http\OrderController;
use App\Http\Router;
use App\Infrastructure\EventDispatcher\EventDispatcher;
use App\Infrastructure\Http\NotificationServiceListener;
use App\Infrastructure\Persistence\PostgresOrderRepository;

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';

/*
 * ---------------------------------------------------------------------
 * Композиция зависимостей (Dependency Injection "руками", без контейнера)
 * ---------------------------------------------------------------------
 * Это единственное место в приложении, где встречается `new` для
 * инфраструктурных классов. Всё остальное (OrderService, контроллеры)
 * получает свои зависимости через конструктор и ничего не создаёт сам.
 *
 * Именно эта секция демонстрирует, как все паттерны связаны вместе:
 *   PDO -> Repository -> OrderService <- Factory
 *                              |
 *                              v
 *                       EventDispatcher -> Listener (Go-сервис)
 */

// 1. Подключение к БД
$dsn = sprintf(
    'pgsql:host=%s;port=%s;dbname=%s',
    $config['db']['host'],
    $config['db']['port'],
    $config['db']['name'],
);

$pdo = new PDO($dsn, $config['db']['user'], $config['db']['password'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// 2. Repository (паттерн Repository) — конкретная реализация поверх Postgres,
//    но везде дальше используется только интерфейс OrderRepositoryInterface.
/** @var OrderRepositoryInterface $orderRepository */
$orderRepository = new PostgresOrderRepository($pdo);

// 3. Factory (паттерн Factory Method) — создаёт нужную Strategy на лету.
$discountStrategyFactory = new DiscountStrategyFactory();

// 4. EventDispatcher (паттерн Observer) + подписка слушателя,
//    который дёргает Go-сервис нотификаций по HTTP.
$eventDispatcher = new EventDispatcher();
$eventDispatcher->subscribe(
    'order.created',
    new NotificationServiceListener($config['notification_service']['url'])
);

// 5. Application Service — получает все зависимости готовыми (DI).
$orderService = new OrderService($orderRepository, $discountStrategyFactory, $eventDispatcher);

// 6. HTTP-контроллер получает сервис, ничего не создавая сам.
$orderController = new OrderController($orderService);

/*
 * ---------------------------------------------------------------------
 * Роутинг
 * ---------------------------------------------------------------------
 */
$router = new Router();

$router->post('/api/orders', fn () => $orderController->create());
$router->get('/api/orders', fn () => $orderController->index());
$router->get('/api/orders/{id}', fn (string $id) => $orderController->show((int) $id));
$router->post('/api/orders/{id}/confirm', fn (string $id) => $orderController->confirm((int) $id));
$router->post('/api/orders/{id}/cancel', fn (string $id) => $orderController->cancel((int) $id));

$router->get('/health', function (): void {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'service' => 'orders-service']);
});

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
