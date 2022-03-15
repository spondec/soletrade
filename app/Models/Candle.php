<?php

namespace App\Models;

use Database\Factories\CandleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Created just to have a factory for testing
// Shouldn't be used in production to avoid massive Eloquent overhead

/**
 * @method static CandleFactory factory()
 */
class Candle extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    public $timestamps = false;

    public function symbol(): BelongsTo
    {
        return $this->belongsTo(Symbol::class);
    }
}
