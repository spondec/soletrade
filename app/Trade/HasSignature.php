<?php

namespace App\Trade;

use App\Models\Signature;
use App\Trade\Helper\ClosureHash;

trait HasSignature
{
    protected Signature $signature;

    /**
     * @var \WeakReference<string,Signature>
     */
    private array $signatureCache = [];

    public function register(array $data): Signature
    {
        $json = \json_encode($hashed = $this->hashCallbacksInArray($data));
        $hash = $this->hash($json);

        if ($signature = $this->signatureCache[$hash] ?? null) {
            return $signature->get();
        }

        /** @var Signature $signature */
        $signature = Signature::query()->firstOrCreate(['hash' => $hash], [
            'hash' => $hash,
            'data' => $hashed
        ]);

        if ($collisions = $this->getKeyDiff($signature->data, $hashed)) {
            throw new \LogicException("Hash collisions detected:\n" . \var_export($collisions, true));
        }

        $this->signatureCache[$hash] = \WeakReference::create($signature);
        return $signature;
    }

    private function getKeyDiff(array $array1, array $array2): array
    {
        $collisions = [];
        foreach ($array1 as $key => $value) {
            if (\is_array($value)) {
                if ($c = $this->getKeyDiff($value, $array2[$key] ?? [])) {
                    $collisions[$key] = $c;
                }
            } else {
                if (!\array_key_exists($key, $array2) || $array2[$key] != $value) {
                    $collisions[] = $key;
                }
            }
        }
        return $collisions;
    }

    protected function hashCallbacksInArray(array $array): array
    {
        foreach ($array as &$item) {
            if (\is_array($item)) {
                $item = $this->hashCallbacksInArray($item);
            } elseif ($item instanceof \Closure) {
                $item = ClosureHash::from($item);
            }
        }

        return $array;
    }

    protected function hash(string|array $subject): string
    {
        if (\is_array($subject)) {
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
