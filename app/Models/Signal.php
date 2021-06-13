<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** COLUMNS
 *
 * @property int    id
 * @property string name
 * @property string desc
 * @property array  config
 * @property mixed  created_at
 * @property mixed  updated_at
 */
class Signal extends Model
{
    use HasFactory;

    protected $table = 'signals';
}
