<?php

declare(strict_types=1);

namespace App\Http;

use App\Application\DTO\CreateOrderRequest;
use App\Application\Service\OrderService;
use App\Domain\Entity\Order;

/**
 * HTTP-контроллер. Его единственная задача — перевод между миром HTTP
 * (JSON, статус-коды) и миром приложения (OrderService, DTO).
 *
 * Здесь нет бизнес-логики и нет SQL — только маршрутизация запроса
 * к нужному методу сервиса и сериализация ответа.
 */
final class OrderController
{
    public function __construct(private readonly OrderService $orderService)
    {
    }

    public function create(): void
    {
        try {
            $data = $this->readJsonBody();
            $request = CreateOrderRequest::fromArray($data);
            $order = $this->orderService->createOrder($request);

            $this->respond(201, $this->serializeOrder($order));
        } catch (\InvalidArgumentException $e) {
            $this->respond(400, ['error' => $e->getMessage()]);
        } catch (\DomainException $e) {
            $this->respond(422, ['error' => $e->getMessage()]);
        }
    }

    public function show(int $id): void
    {
        try {
            $order = $this->orderService->getOrder($id);
            $this->respond(200, $this->serializeOrder($order));
        } catch (\DomainException $e) {
            $this->respond(404, ['error' => $e->getMessage()]);
        }
    }

    public function index(): void
    {
        $customerId = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : null;

        $orders = $customerId !== null
            ? $this->orderService->getOrdersByCustomer($customerId)
            : $this->orderService->getAllOrders();

        $this->respond(200, array_map(
            fn (Order $order) => $this->serializeOrder($order),
            $orders
        ));
    }

    public function confirm(int $id): void
    {
        try {
            $order = $this->orderService->confirmOrder($id);
            $this->respond(200, $this->serializeOrder($order));
        } catch (\DomainException $e) {
            $this->respond(422, ['error' => $e->getMessage()]);
        }
    }

    public function cancel(int $id): void
    {
        try {
            $order = $this->orderService->cancelOrder($id);
            $this->respond(200, $this->serializeOrder($order));
        } catch (\DomainException $e) {
            $this->respond(422, ['error' => $e->getMessage()]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readJsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';

        if ($raw === '') {
            throw new \InvalidArgumentException('Пустое тело запроса');
        }

        try {
            $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('Некорректный JSON в теле запроса');
        }

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Тело запроса должно быть JSON-объектом');
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(Order $order): array
    {
        return [
            'id' => $order->getId(),
            'customer_id' => $order->getCustomerId(),
            'customer_tier' => $order->getCustomerTier(),
            'status' => $order->getStatus()->value,
            'subtotal' => round($order->getSubtotal(), 2),
            'discount_amount' => round($order->getDiscountAmount(), 2),
            'total' => round($order->getTotal(), 2),
            'created_at' => $order->getCreatedAt()->format(DATE_ATOM),
            'items' => array_map(
                fn ($item) => [
                    'product_name' => $item->getProductName(),
                    'quantity' => $item->getQuantity(),
                    'unit_price' => $item->getUnitPrice(),
                    'total' => round($item->getTotal(), 2),
                ],
                $order->getItems()
            ),
        ];
    }

    /**
     * @param array<int|string, mixed> $payload
     */
    private function respond(int $statusCode, array $payload): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
