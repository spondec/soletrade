<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\Runner
 *
 * @property int $id
 * @property int $start_date
 * @property int $expire_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder|Runner newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Runner newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Runner query()
 * @method static \Illuminate\Database\Eloquent\Builder|Runner whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Runner whereExpireDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Runner whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Runner whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Runner whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Runner extends Model
{
    use HasFactory;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->start_date = \time();
    }

    public function setExpiry(int $seconds): static
    {
        $this->expire_date = \time() + $seconds;
        return $this;
    }

    public function lengthenExpiry(int $seconds): static
    {
        $this->expire_date += $seconds;
        return $this;
    }

    public static function purgeExpired(): void
    {
        \DB::table('runners')
            ->where('expire_date', '<=', \time())
            ->delete();
    }
}
