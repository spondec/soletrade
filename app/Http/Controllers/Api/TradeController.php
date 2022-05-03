<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TradeResource;
use App\Models\Trade;

class TradeController extends Controller
{
    public function index()
    {
        return Trade::all();
    }

    public function recent()
    {
        return TradeResource::collection(Trade::query()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->with('entry.symbol', 'exit')
            ->get());
    }
}
