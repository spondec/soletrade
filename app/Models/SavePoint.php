<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int   id
 * @property int   binding_signature_id
 * @property int   timestamp
 * @property float value
 */
class SavePoint extends Model
{
    protected $guarded = ['id'];
}
