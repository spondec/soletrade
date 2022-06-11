<?php

namespace App\Trade\Contract;

interface Series extends \ArrayAccess
{
    /**
     * Returns a copy of the series at the specified offset/key or self, if no offset/key is specified.
     *
     * @param int         $offset
     * @param string|null $key
     *
     * @return Series
     */
    public function value(int $offset = 0, string $key = null): Series;

    /**
     * Gets the value at the specified offset/key.
     *
     * @param int $offset
     *
     * @return mixed
     */
    public function get(int $offset = 0): mixed;
}