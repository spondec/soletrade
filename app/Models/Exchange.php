<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Exchange
 *
 * @property int $id
 * @property string $class
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\ExchangeFactory factory(...$parameters)
 * @method static \Illuminate\Database\Eloquent\Builder|Exchange newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Exchange newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Exchange query()
 * @method static \Illuminate\Database\Eloquent\Builder|Exchange whereClass($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Exchange whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Exchange whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Exchange whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Exchange whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Exchange extends Model
{
    use HasFactory;

    protected $guarded = ['id'];
}
