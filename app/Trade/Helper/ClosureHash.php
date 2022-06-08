<?php

namespace App\Trade\Helper;

/**
 * @author  lisachenko - https://stackoverflow.com/users/801258/lisachenko
 *
 * @link    https://stackoverflow.com/a/14620643
 */
class ClosureHash
{
    protected static ?\WeakMap $hashes = null;

    /**
     * Returns a hash for closure.
     *
     * @param callable $closure
     *
     * @return string
     */
    public static function from(\Closure $closure): string
    {
        if (!static::$hashes) {
            static::$hashes = new \WeakMap();
        }

        if (!isset(static::$hashes[$closure])) {
            $ref = new \ReflectionFunction($closure);
            $file = new \SplFileObject($ref->getFileName());
            $file->seek($ref->getStartLine() - 1);
            $content = '';
            while ($file->key() < $ref->getEndLine()) {
                $content .= $file->current();
                $file->next();
            }

            static::$hashes[$closure] = \md5(\json_encode([
                $content,
                $ref->getStaticVariables(),
            ]));
        }

        return static::$hashes[$closure];
    }
}
