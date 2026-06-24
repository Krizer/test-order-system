<?php

declare(strict_types=1);

namespace App\Domain\Event;

/**
 * Базовый интерфейс для всех доменных событий.
 */
interface DomainEventInterface
{
    public function getName(): string;

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array;
}
