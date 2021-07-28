<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Position;
use Illuminate\Http\Request;

class TradeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(): array
    {
        return Position::query()
            ->orderBy('is_open', 'DESC')
            ->limit(15)
            ->get(['is_open', 'symbol', 'pnl'])
            ->toArray();
    }
}
