<?php

declare(strict_types=1);

namespace App\Domain\Entity;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case CONFIRMED = 'confirmed';
    case SHIPPED = 'shipped';
    case CANCELLED = 'cancelled';
}
