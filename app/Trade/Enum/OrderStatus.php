<?php

namespace App\Trade\Enum;

enum OrderStatus: string
{
    case OPEN = 'OPEN';
    case REJECTED = 'REJECTED';
    case EXPIRED = 'EXPIRED';
    case NEW = 'NEW';
    case CLOSED = 'CLOSED';
    case CANCELED = 'CANCELED';
}
