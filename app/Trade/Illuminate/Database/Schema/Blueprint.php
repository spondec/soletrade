<?php

namespace App\Trade\Illuminate\Database\Schema;

class Blueprint extends \Illuminate\Database\Schema\Blueprint
{
    public function decimal($column, $total = 29, $places = 10, $unsigned = false)
    {
        return parent::decimal($column, $total, $places, $unsigned);
    }
}