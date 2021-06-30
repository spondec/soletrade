<?php

namespace App\Models;

use App\Trade\Exchange\AbstractExchange;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int     id
 * @property boolean is_open
 * @property string  exchange
 * @property string  account
 * @property string  symbol
 * @property string  side
 * @property float   quantity
 * @property float   quantity_type
 * @property float   entry_price
 * @property float   avg_price
 * @property float   liq_price
 * @property float   margin
 * @property float   pnl
 * @property float   stop_price
 * @property float   take_profit_price
 * @property mixed   created_at
 * @property mixed   updated_at
 */
class Position extends Model
{
    use HasFactory;

    protected $table = 'positions';

    protected ?AbstractExchange $exchange = null;

    public function getCloseAction()
    {
        return $this->side === 'BUY' ? 'SELL' : 'BUY';
    }

    public function exchange(): AbstractExchange
    {
        if ($this->exchange)
        {
            return $this->exchange;
        }

        $account = ucfirst(mb_strtolower($this->account));
        $exchange = ucfirst(mb_strtolower($this->exchange));

        $class = '\App\Trade\Exchange\\' . "$account\\$exchange";

        if (!class_exists($class))
        {
            throw new \LogicException("Exchange class couldn't found: $class");
        }

        return $this->exchange = $class::instance();
    }
}
