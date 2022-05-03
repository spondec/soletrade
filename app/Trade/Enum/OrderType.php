<?php

namespace App\Trade\Enum;

enum OrderType: string
{
    case LIMIT = 'LIMIT';
    case MARKET = 'MARKET';
    case STOP_LIMIT = 'STOP_LIMIT';
    case STOP_MARKET = 'STOP_MARKET';
}