<?php

namespace App\Trade;

class CandleMap
{
    public function __construct(public readonly int|string $t,
                                public readonly int|string $o,
                                public readonly int|string $c,
                                public readonly int|string $h,
                                public readonly int|string $l,
                                public readonly int|string $v)
    {
    }
}
