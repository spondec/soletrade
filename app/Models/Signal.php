<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** COLUMNS
 *
 * @property int    id
 * @property int    trade_setup_id
 * @property string type
 * @property string indicator
 * @property int    indicator_version
 * @property string side
 * @property string symbol
 * @property string interval
 * @property float  price
 * @property mixed  created_at
 * @property mixed  updated_at
 */
class Signal extends Model
{
    use HasFactory;

    protected $table = 'signals';
}
