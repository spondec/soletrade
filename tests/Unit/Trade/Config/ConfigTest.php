<?php

namespace Tests\Unit\Trade\Config;

use App\Trade\Config\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testToArray(): void
    {
        $c = $this->getConfig();

        $this->assertEquals('foo', $c->toArray()['string']);
        $this->assertInstanceOf(\Closure::class, $c->toArray()['closure']);
        $this->assertEquals(true, $c->toArray()['closure']());
        $this->assertEquals(range(1, 10), $c->toArray()['array']);
    }

    protected function getConfig(): Config
    {
        return new class('foo', fn(): bool => true, range(1, 10)) extends Config {
            public function __construct(public readonly string $string,
                                        public readonly \Closure $closure,
                                        public readonly array $array)
            {
            }
        };
    }

    public function testFromArray(): void
    {
        /** @var Config $class */
        $class = get_class($this->getConfig());

        $this->assertInstanceOf(Config::class,
            $class::fromArray([
                'string'  => 'foo',
                'closure' => fn(): bool => true,
                'array'   => []
            ]));
    }
}
