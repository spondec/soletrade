<?php

namespace Tests\Feature\Trade;

use App\Trade\HasSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasSignatureTest extends TestCase
{
    use RefreshDatabase;
    use HasSignature;

    public function test_id()
    {
        $this->signature = $this->register(['range' => range(1, 10)]);

        $this->assertIsInt($this->id());
    }

    public function test_hash_callbacks_in_array()
    {
        $arr = [
            fn($a) => $a,
            [
                'inner' => fn($a) => $a,
            ]
        ];

        $hashed = $this->hashCallbacksInArray($arr);

        $this->assertIsString($hashed[0]);
        $this->assertIsString($hashed[1]['inner']);
        $this->assertEquals(strlen($hashed[0]), strlen($hashed[1]['inner']));
    }

    public function test_hash()
    {
        $this->assertEquals(32, strlen($this->hash('TEST')));
    }

    public function test_contents()
    {
        $this->assertStringStartsWith('<?php', $this->contents());
    }

    public function test_register()
    {
        $this->signature = $this->register(['range' => range(1, 10), fn($a) => $a]);

        $this->assertTrue($this->signature->exists);
        $this->assertIsString($this->signature->data[0]);
    }
}
