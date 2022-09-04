<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * App\Models\Signature
 *
 * @property int $id
 * @property array $data
 * @property string $hash
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Database\Factories\SignatureFactory factory(...$parameters)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Signature newModelQuery()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Signature newQuery()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Signature query()
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Signature whereCreatedAt($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Signature whereData($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Signature whereHash($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Signature whereId($value)
 * @method static \App\Trade\Illuminate\Database\Eloquent\Builder|Signature whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Signature extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = ['data' => 'array'];
}
