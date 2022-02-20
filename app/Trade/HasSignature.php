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
        $json = \json_encode($hashed = $this->hashCallbacksInArray($data));
        $hash = $this->hash($json);

        if ($signature = $this->signatureCache[$hash] ?? null)
        {
            return $signature;
        }

        /** @var Signature $signature */
        $signature = Signature::query()->firstOrCreate(['hash' => $hash], [
            'hash' => $hash,
            'data' => $hashed
        ]);

        if ($signature->data !== $hashed)
        {
            throw new \LogicException("Hash collision detected for $signature->hash");
        }

        return $this->signatureCache[$hash] = $signature;
    }

    protected function hashCallbacksInArray(array $array): array
    {
        foreach ($array as &$item)
        {
            if (\is_array($item))
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

    protected function hash(string|array $subject): string
    {
        if (is_array($subject))
        {
            $subject = \json_encode($this->hashCallbacksInArray($subject));
        }
        return \md5($subject);
    }

    public function contents(): string
    {
        return \file_get_contents((new \ReflectionClass(static::class))->getFileName());
    }

    public function id(): int
    {
        return $this->signature->id;
    }
}