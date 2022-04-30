<?php

namespace Tests\Unit\Trade;

use App\Trade\Enum;
use PHPUnit\Framework\TestCase;

enum Foo
{
    case BAR;
    case BAZ;
}

enum StringBackedFoo: string
{
    case BAR = 'BAR';
    case BAZ = 'BAZ';
}

class EnumTest extends TestCase
{
    public function test_cases()
    {
        $this->assertEquals(['BAR', 'BAZ'], Enum::cases(Foo::class));
        $this->assertEquals(['BAR', 'BAZ'], Enum::cases(StringBackedFoo::class));
    }
}
