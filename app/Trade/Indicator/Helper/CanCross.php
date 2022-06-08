<?php

namespace App\Trade\Indicator\Helper;

/**
 * @method array prev()
 * @method array current()
 */
trait CanCross
{
    /**
     * Return true if x crosses over y.
     *
     * @param string $x one of the values calculated by the indicator
     * @param string $y one of the values calculated by the indicator
     */
    public function crossover(string $x, string $y): bool
    {
        $prev = $this->prev();
        $current = $this->current();

        if (empty($prev[$x]) || empty($prev[$y]) || empty($current[$x]) || empty($current[$y])) {
            return false;
        }

        return $prev[$x] < $prev[$y] && $current[$x] > $current[$y];
    }

    /**
     * Return true if x crosses under y.
     *
     * @param string $x one of the values calculated by the indicator
     * @param string $y one of the values calculated by the indicator
     */
    public function crossunder(string $x, string $y): bool
    {
        $prev = $this->prev();
        $current = $this->current();

        if (empty($prev[$x]) || empty($prev[$y]) || empty($current[$x]) || empty($current[$y])) {
            return false;
        }

        return $prev[$x] > $prev[$y] && $current[$x] < $current[$y];
    }
}
