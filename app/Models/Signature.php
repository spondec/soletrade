<?php

namespace App\Models;

use Database\Factories\SignatureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @property int            id
 * @property array          data
 * @property string         hash
 * @property \Carbon\Carbon created_at
 * @property \Carbon\Carbon updated_at
 *
 * @method static SignatureFactory factory($count = null, $state = [])
 */
class Signature extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['data' => 'array'];
}
