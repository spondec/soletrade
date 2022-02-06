<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Created just to have a factory for testing
// Shouldn't be used in production to avoid massive overhead
class Candle extends Model
{
    use HasFactory;

    public $timestamps = false;

    public function symbol(): BelongsTo
    {
        return $this->belongsTo(Symbol::class);
    }
}
