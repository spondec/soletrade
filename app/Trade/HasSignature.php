<?php

namespace App\Trade;

use App\Models\Signature;
use App\Trade\Helper\ClosureHash;

trait HasSignature
{
    protected Signature $signature;

    /**
     * @var Signature[]
     */
    private array $signatureCache = [];

    public function register(array $data): Signature
    {
        $json = json_encode($hashed = $this->hashCallbacksInArray($data));
        $hash = $this->hash($json);

        if ($signature = $this->signatureCache[$hash] ?? null)
        {
            return $signature;
        }

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->signatureCache[$hash] = Signature::query()->firstOrCreate(['hash' => $hash], ['hash' => $hash, 'data' => $hashed]);
    }

    protected function hashCallbacksInArray(array $array): array
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

    protected function hash(string $string): string
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