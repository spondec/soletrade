<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int    id
 * @property string data
 * @property string hash
 * @property Carbon created_at
 * @property Carbon updated_at
 */
class Signature extends Model
{
    use HasFactory;
}
