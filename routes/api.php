<?php

use App\Http\Controllers\Api\ProductPriceController;
use App\Http\Controllers\Api\SynchronizationController;
use Illuminate\Support\Facades\Route;

Route::post('/sincronizar/produtos', [SynchronizationController::class, 'syncProducts']);
Route::post('/sincronizar/precos', [SynchronizationController::class, 'syncPrices']);
Route::get('/produtos-precos', [ProductPriceController::class, 'index']);
