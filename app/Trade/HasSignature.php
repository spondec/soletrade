<?php

namespace App\Trade;

use App\Models\Signature;
use App\Trade\Helper\ClosureHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

trait HasSignature
{
    protected Model $signature;

    public function register(array $data): Signature
    {
        try
        {
            $signature = new Signature([
                'data' => $json = json_encode($this->hashCallbacksInArray($data)),
                'hash' => $hash = $this->hash($json)
            ]);

            if ($signature->save())
            {
                return $signature;
            }
        } catch (QueryException $e)
        {
            if ($e->errorInfo[1] !== 1062)
            {
                throw $e;
            }
        }

        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return Signature::query()->where('hash', $hash)->firstOrFail();
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