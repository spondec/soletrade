<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** COLUMNS
 *
 * @property int    id
 * @property string class
 * @property string name
 */
class Exchange extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
}
