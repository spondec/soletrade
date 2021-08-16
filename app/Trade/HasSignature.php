<?php

namespace App\Trade;

use App\Models\Signature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

trait HasSignature
{
    protected Model $signature;

    protected function register(array $data): Signature
    {
        DB::table('signatures')->insertOrIgnore([
            'data' => $json = json_encode($data),
            'hash' => $hash = $this->hash($json)
        ]);

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return Signature::query()->where('hash', $hash)->firstOrFail();
    }

    protected function hash(string $string): string
    {
        return md5($string);
    }

    protected function contents(): string
    {
        return file_get_contents((new \ReflectionClass(static::class))->getFileName());
    }

    public function id(): int
    {
        return $this->signature->id;
    }
}