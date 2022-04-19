<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('orders', [\App\Http\Controllers\Api\OrderController::class, 'index']);
Route::get('exchanges', [\App\Http\Controllers\Api\ExchangeController::class, 'index']);
Route::get('exchanges/balances', [\App\Http\Controllers\Api\ExchangeController::class, 'balances']);
Route::get('exchanges/{exchange}/symbols', [\App\Http\Controllers\Api\ExchangeController::class, 'symbols']);
Route::get('chart', [\App\Http\Controllers\Api\ChartController::class, 'index']);
