<?php

namespace App\Models;

enum OrderStatus: string
{
    case OPEN = 'OPEN';
    case REJECTED = 'REJECTED';
    case EXPIRED = 'EXPIRED';
    case NEW = 'NEW';
    case PARTIALLY_FILLED = 'PARTIALLY_FILLED';
    case CANCELED = 'CANCELED';
    case PENDING_CANCEL = 'PENDING_CANCEL';
}