<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int            id
 * @property array          data
 * @property string         hash
 * @property \Carbon\Carbon created_at
 * @property \Carbon\Carbon updated_at
 */
class Signature extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['data' => 'array'];
}
