<?php

namespace App\Trade\Indicator\Helpers;

/**
 * @property int|null                       prev
 * @property int|null                       current
 * @property int|null                       next
 * @property \Illuminate\Support\Collection data
 */
trait CanCross
{
    /**
     * Return true if x crosses over y.
     *
     * @param \Closure $x Takes indicator data as argument
     * @param \Closure $y Takes indicator data as argument
     *
     * @return bool
     */
    public function crossOver(\Closure $x, \Closure $y): bool
    {
        $prev = $this->prev();
        $current = $this->current();

        if ($prev === null || $current === null)
        {
            return false;
        }

        return $x($prev) < $y($prev) && $x($current) > $y($current);
    }

    /**
     * Return true if x crosses under y.
     *
     * @param \Closure $x Takes indicator data as argument
     * @param \Closure $y Takes indicator data as argument
     *
     * @return bool
     */
    public function crossUnder(\Closure $x, \Closure $y): bool
    {
        $prev = $this->prev();
        $current = $this->current();

        if ($prev === null || $current === null)
        {
            return false;
        }

        return $x($prev) > $y($prev) && $x($current) < $y($current);
    }
}