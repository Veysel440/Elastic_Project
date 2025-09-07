<?php

use App\Http\Controllers\HealthController;
use Elastic\Elasticsearch\Client;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\StoreController;

Route::get('/health', fn() => response()->json(['ok' => true]));

Route::get('/es/ping', function (Client $es) {
    return response()->json($es->info()->asArray());
});


Route::middleware(['api','apikey'])->group(function () {
    Route::post('/stores',            [StoreController::class, 'create'])->middleware(['json','idem','throttle:ops']);
    Route::get('/stores/{id}', [StoreController::class, 'show'])->middleware('throttle:api');
    Route::post('/stores/{id}/area',  [StoreController::class, 'setArea'])->middleware(['json','throttle:ops']);
    Route::get ('/stores/near',       [StoreController::class, 'nearest'])->middleware('throttle:api');
    Route::get ('/delivery/eligible', [StoreController::class, 'eligible'])->middleware('throttle:api');
    Route::get ('/stores/heat',       [StoreController::class, 'heat'])->middleware('throttle:api');
    Route::get('/stores/within', [StoreController::class,'within'])->middleware('throttle:api');
});

Route::get('/healthz', [HealthController::class,'health']);
Route::get('/readyz',  [HealthController::class,'ready']);
