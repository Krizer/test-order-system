<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Простейший роутер без фреймворка — этот проект демонстрирует паттерны
 * ООП, а не возможности конкретного фреймворка. Сопоставляет
 * (метод, путь) с обработчиком.
 */
final class Router
{
    /** @var array<int, array{method: string, pattern: string, handler: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->routes[] = ['method' => 'GET', 'pattern' => $pattern, 'handler' => $handler];
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->routes[] = ['method' => 'POST', 'pattern' => $pattern, 'handler' => $handler];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->match($route['pattern'], $path);

            if ($params !== null) {
                ($route['handler'])(...$params);
                return;
            }
        }

        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => "Маршрут {$method} {$path} не найден"]);
    }

    /**
     * Превращает паттерн вида '/api/orders/{id}' в регулярку и возвращает
     * найденные параметры, либо null если путь не подошёл.
     *
     * @return array<int, string>|null
     */
    private function match(string $pattern, string $path): ?array
    {
        $regex = '#^' . preg_replace('#\{[a-zA-Z_]+\}#', '([^/]+)', $pattern) . '$#';

        if (preg_match($regex, $path, $matches)) {
            array_shift($matches);
            return $matches;
        }

        return null;
    }
}
