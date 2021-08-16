<?php

namespace App\Trade\Helper;

/**
 * @author  lisachenko - https://stackoverflow.com/users/801258/lisachenko
 * @link    https://stackoverflow.com/a/14620643
 */
class ClosureHash
{
    /**
     * List of hashes
     *
     * @var \SplObjectStorage
     */
    protected static ?\SplObjectStorage $hashes = null;

    /**
     * Returns a hash for closure
     *
     * @param callable $closure
     *
     * @return string
     */
    public static function from(\Closure $closure): string
    {
        if (!self::$hashes) {
            self::$hashes = new \SplObjectStorage();
        }

        if (!isset(self::$hashes[$closure])) {
            $ref  = new \ReflectionFunction($closure);
            $file = new \SplFileObject($ref->getFileName());
            $file->seek($ref->getStartLine()-1);
            $content = '';
            while ($file->key() < $ref->getEndLine()) {
                $content .= $file->current();
                $file->next();
            }
            self::$hashes[$closure] = md5(json_encode(array(
                $content,
                $ref->getStaticVariables()
            )));
        }
        return self::$hashes[$closure];
    }
}