<?php

namespace App\Trade;

class CandleMap
{
    public function __construct(public $t, public $o, public $c, public $h, public $l, public $v)
    {
    }
}