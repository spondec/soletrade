<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache as Cache;

class MarketStrategy extends Controller
{
    public function index()
    {
        return view('welcome');
    }
}
