<?php

namespace App\Repositories;

use App\Models\Binding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BindingRepository
{
    public function fetchSavePoints(Binding $binding, int $startDate, int $endDate): Collection
    {
        return DB::table('save_points')
            ->where('binding_signature_id', $binding->signature_id)
            ->where('timestamp', '>=', $startDate)
            ->where('timestamp', '<=', $endDate)
            ->orderBy('timestamp', 'ASC')
            ->get(['value', 'timestamp']);
    }

    public function insertSavePoints(array $points): void
    {
        foreach (array_chunk($points, 1000) as $chunk)
        {
            DB::table('save_points')->upsert($chunk,
                ['timestamp', 'binding_signature_id'],
                ['value']);
        }
    }
}