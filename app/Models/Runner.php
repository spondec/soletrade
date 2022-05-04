<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int    id
 * @property string name
 * @property int    start_date
 * @property int    expire_date
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
