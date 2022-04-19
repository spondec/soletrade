<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Position;

class TradeController extends Controller
{
    public function index()
    {
        return Position::all();
    }

    public function recent()
    {
        return Position::query()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->with('entry.symbol', 'exit')
            ->get();
    }
}
