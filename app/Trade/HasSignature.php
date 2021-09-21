<?php

namespace App\Trade;

use App\Models\Signature;
use App\Trade\Helper\ClosureHash;

trait HasSignature
{
    protected Signature $signature;

    public function register(array $data): Signature
    {
        $json = json_encode($this->hashCallbacksInArray($data));
        $hash = $this->hash($json);

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return Signature::query()->firstOrCreate(['hash' => $hash], [
            'data' => $json,
            'hash' => $hash
        ]);
    }

    public function hashCallbacksInArray(array $array): array
    {
        foreach ($array as &$item)
        {
            if (is_array($item))
            {
                $item = $this->hashCallbacksInArray($item);
            }
            else if ($item instanceof \Closure)
            {
                $item = ClosureHash::from($item);
            }
        }

        return $array;
    }

    public function hash(string $string): string
    {
        return md5($string);
    }

    public function contents(): string
    {
        return file_get_contents((new \ReflectionClass(static::class))->getFileName());
    }

    public function id(): int
    {
        return $this->signature->id;
    }
}