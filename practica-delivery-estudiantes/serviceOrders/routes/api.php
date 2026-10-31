<?php

use App\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

// Laravel antepone /api automáticamente (withRouting(api: ...)), por eso aquí
// solo se declara /v1 -> las rutas finales son /api/v1/orders..., igual que en nginx.
Route::prefix('v1')->middleware('jwt')->group(function () {
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{id}', [OrderController::class, 'show'])->whereNumber('id');
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus'])->whereNumber('id');
    Route::delete('/orders/{id}', [OrderController::class, 'destroy'])->whereNumber('id');
});

