<?php

namespace App\Trade\Enum;

enum TraderStatus: string
{
    case STOPPED = 'Stopped';
    case AWAITING_ENTRY = 'Awaiting Entry';
    case IN_POSITION = 'In Position';
    case AWAITING_TRADE = 'Awaiting Trade';
}