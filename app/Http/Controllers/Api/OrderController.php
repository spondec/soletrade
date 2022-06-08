<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class OrderController extends Controller
{
    public function index()
    {
        return \App\Models\Order::all();
    }
}
