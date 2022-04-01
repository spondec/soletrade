<?php

namespace App\Models;

use Database\Factories\ExchangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** COLUMNS
 *
 * @property int    id
 * @property string class
 * @property string name
 *
 * @method static ExchangeFactory factory($count = null, $state = [])
 */
class Exchange extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
}
